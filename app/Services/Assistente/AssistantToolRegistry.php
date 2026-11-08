<?php

namespace App\Services\Assistente;

use App\Models\Aluno;
use App\Models\Configuracao;
use App\Models\Turma;
use App\Models\User;
use InvalidArgumentException;

/**
 * Registo formal de tools server-side — o LLM nunca executa SQL directamente.
 * Cada tool delega a serviços existentes e respeita AssistantAccess.
 */
class AssistantToolRegistry
{
    public function __construct(
        protected AssistantAccess $access,
        protected AssistantDataService $data,
        protected AssistantAnalyticsService $analytics,
        protected AssistantSystemDataService $systemData,
    ) {}

    /**
     * @return array<string, array{descricao: string, parametros: array<string, string>}>
     */
    public function catalogo(): array
    {
        return [
            'sistema' => [
                'descricao' => 'Metadados do sistema (ano lectivo, trimestre, módulos activos)',
                'parametros' => [],
            ],
            'aluno_sessao' => [
                'descricao' => 'Dados do aluno autenticado (notas, turma, professores)',
                'parametros' => [],
            ],
            'horario' => [
                'descricao' => 'Horário de aulas da turma no ano lectivo actual',
                'parametros' => ['turma_codigo' => 'opcional — código da turma'],
            ],
            'calendario_provas' => [
                'descricao' => 'Provas agendadas da turma',
                'parametros' => ['turma_codigo' => 'opcional'],
            ],
            'buscar_alunos' => [
                'descricao' => 'Pesquisa alunos por nome ou n.º processo (escopo do utilizador)',
                'parametros' => ['termo' => 'texto de busca (mín. 3 caracteres)'],
            ],
            'buscar_colaboradores' => [
                'descricao' => 'Pesquisa colaboradores (professores, direcção, coordenação, secretaria) por nome',
                'parametros' => ['termo' => 'texto de busca (mín. 3 caracteres)'],
            ],
            'relatorio_aluno' => [
                'descricao' => 'Relatório completo de um aluno',
                'parametros' => ['aluno_id' => 'ID do aluno'],
            ],
            'rankings' => [
                'descricao' => 'Rankings de desempenho por turma/curso',
                'parametros' => ['trimestre' => '1|2|3', 'turma_codigo' => 'opcional'],
            ],
            'pendencias_professor' => [
                'descricao' => 'Notas MAC/PP em falta nas turmas do professor',
                'parametros' => [],
            ],
            'estatisticas' => [
                'descricao' => 'Estatísticas gerais institucionais',
                'parametros' => [],
            ],
            'professores_turma' => [
                'descricao' => 'Professores por turma',
                'parametros' => ['turma_codigo' => 'opcional'],
            ],
        ];
    }

    /**
     * Decide quais tools executar com base em intenções, perfil e mensagem.
     *
     * @return list<string>
     */
    public function resolverTools(User $user, string $mensagem, array $historico = []): array
    {
        $tools = ['sistema'];
        $intencoes = $this->systemData->detectarIntencoes($mensagem, $historico);
        $msgLower = mb_strtolower($mensagem);

        if ($user->hasRole('aluno')) {
            $tools[] = 'aluno_sessao';
            $tools[] = 'horario';
            $tools[] = 'calendario_provas';
        } else {
            if (in_array('pendencias', $intencoes, true) || $this->mencionaPendencias($msgLower)) {
                $tools[] = 'pendencias_professor';
            }
            if (in_array('rankings', $intencoes, true) || $this->mencionaRankings($msgLower)) {
                $tools[] = 'rankings';
            }
            if ($this->access->canViewEstatisticas($user) && $this->mencionaEstatisticas($msgLower)) {
                $tools[] = 'estatisticas';
            }
            if ($this->mencionaProfessor($msgLower)) {
                $tools[] = 'professores_turma';
            }
        }

        if (in_array('horario', $intencoes, true) || $this->mencionaHorario($msgLower)) {
            $tools[] = 'horario';
        }
        if (in_array('provas', $intencoes, true) || $this->mencionaProvas($msgLower)) {
            $tools[] = 'calendario_provas';
        }

        $termos = $this->termosComFallbackHistorico($mensagem, $historico);
        if (! $user->hasRole('aluno') && ($termos !== [] || $this->mencionaAluno($msgLower))) {
            $tools[] = 'buscar_alunos';
        }

        // Nomes genéricos podem referir-se a colaboradores (professores, direcção, etc.)
        if (! $user->hasRole('aluno') && ! $user->hasRole('encarregado')
            && ($termos !== [] || $this->mencionaProfessor($msgLower))) {
            $tools[] = 'buscar_colaboradores';
        }

        return array_values(array_unique($tools));
    }

    /**
     * Termos da mensagem actual; se vazios (ex.: «E nos professores?»),
     * recorre à última mensagem do utilizador no histórico.
     *
     * @param  array<int, array{papel:string, conteudo:string}>  $historico
     * @return list<string>
     */
    protected function termosComFallbackHistorico(string $mensagem, array $historico): array
    {
        $termos = $this->data->extrairTermosBusca($mensagem);
        if ($termos !== []) {
            return $termos;
        }

        foreach (array_reverse($historico) as $item) {
            if (($item['papel'] ?? '') !== 'user') {
                continue;
            }
            $termos = $this->data->extrairTermosBusca((string) ($item['conteudo'] ?? ''));
            if ($termos !== []) {
                return $termos;
            }
        }

        return [];
    }

    /**
     * Executa tools resolvidas e devolve resultados agregados.
     *
     * @param  list<string>  $toolNames
     * @return array<string, mixed>
     */
    public function executar(User $user, array $toolNames, string $mensagem, array $historico = []): array
    {
        $resultados = [];
        $parametros = $this->extrairParametros($mensagem, $historico);

        foreach ($toolNames as $nome) {
            try {
                $resultado = $this->executarTool($user, $nome, $parametros, $mensagem, $historico);
                if ($resultado !== null) {
                    $resultados[$nome] = $resultado;
                }
            } catch (\Throwable $e) {
                $resultados[$nome] = ['erro' => 'Não foi possível obter dados: '.$e->getMessage()];
            }
        }

        return $resultados;
    }

    /**
     * @param  array<string, mixed>  $parametros
     * @return array<string, mixed>|null
     */
    protected function executarTool(User $user, string $nome, array $parametros, string $mensagem, array $historico): ?array
    {
        return match ($nome) {
            'sistema' => $this->systemData->modulosSistema($user),
            'aluno_sessao' => $this->data->getContextoAlunoLogado($user),
            'horario' => $this->systemData->horarioTurma(
                $user,
                $this->resolverTurma($user, $parametros['turma_codigo'] ?? null)
            ),
            'calendario_provas' => $this->systemData->provasTurma(
                $user,
                $this->resolverTurma($user, $parametros['turma_codigo'] ?? null)
            ),
            'buscar_alunos' => $this->executarBuscarAlunos($user, $mensagem, $historico),
            'buscar_colaboradores' => $this->executarBuscarColaboradores($user, $mensagem, $historico),
            'relatorio_aluno' => $this->executarRelatorioAluno($user, (int) ($parametros['aluno_id'] ?? 0)),
            'rankings' => $this->executarRankings($user, $mensagem, $historico, $parametros),
            'pendencias_professor' => $this->data->getPendenciasProfessor($user),
            'estatisticas' => $this->data->getEstatisticasResumo($user),
            'professores_turma' => $this->executarProfessoresTurma($user, $parametros['turma_codigo'] ?? null),
            default => throw new InvalidArgumentException("Tool desconhecida: {$nome}"),
        };
    }

    /**
     * @param  array<int, array{papel:string, conteudo:string}>  $historico
     * @return array<string, mixed>
     */
    protected function executarBuscarAlunos(User $user, string $mensagem, array $historico = []): array
    {
        if ($user->hasRole('aluno')) {
            return ['alunos' => []];
        }

        $termos = $this->termosComFallbackHistorico($mensagem, $historico);
        $alunos = [];

        foreach ($termos as $termo) {
            foreach ($this->data->searchAlunos($user, $termo) as $resumo) {
                $alunos[$resumo['id']] = $resumo;
            }
        }

        if ($alunos === [] && $termos !== []) {
            foreach ($this->data->searchAlunos($user, implode(' ', array_slice($termos, 0, 2))) as $resumo) {
                $alunos[$resumo['id']] = $resumo;
            }
        }

        return ['alunos' => array_values($alunos)];
    }

    /**
     * @param  array<int, array{papel:string, conteudo:string}>  $historico
     * @return array<string, mixed>
     */
    protected function executarBuscarColaboradores(User $user, string $mensagem, array $historico = []): array
    {
        if ($user->hasRole('aluno') || $user->hasRole('encarregado')) {
            return ['colaboradores' => []];
        }

        $termos = $this->termosComFallbackHistorico($mensagem, $historico);
        $colaboradores = $this->pesquisarColaboradoresPorTermos($user, $termos);

        // Respostas curtas (ex.: só o código da turma) não contêm o nome —
        // recupera os termos das mensagens anteriores do utilizador.
        if ($colaboradores === []) {
            foreach (array_reverse($historico) as $item) {
                if (($item['papel'] ?? '') !== 'user') {
                    continue;
                }
                $termosAnteriores = $this->data->extrairTermosBusca((string) ($item['conteudo'] ?? ''));
                $colaboradores = $this->pesquisarColaboradoresPorTermos($user, $termosAnteriores);
                if ($colaboradores !== []) {
                    break;
                }
            }
        }

        return ['colaboradores' => array_values($colaboradores)];
    }

    /**
     * @param  list<string>  $termos
     * @return array<string, array<string, mixed>>
     */
    protected function pesquisarColaboradoresPorTermos(User $user, array $termos): array
    {
        $colaboradores = [];

        foreach ($termos as $termo) {
            foreach ($this->data->searchColaboradores($user, $termo) as $resumo) {
                $colaboradores[$resumo['nome']] = $resumo;
            }
        }

        if ($colaboradores === [] && count($termos) > 1) {
            foreach ($this->data->searchColaboradores($user, implode(' ', array_slice($termos, 0, 2))) as $resumo) {
                $colaboradores[$resumo['nome']] = $resumo;
            }
        }

        return $colaboradores;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function executarRelatorioAluno(User $user, int $alunoId): ?array
    {
        if ($alunoId <= 0) {
            return null;
        }

        $aluno = Aluno::query()->find($alunoId);

        return $aluno ? $this->data->getRelatorioAluno($user, $aluno) : null;
    }

    /**
     * @param  array<string, mixed>  $parametros
     * @return array<string, mixed>|null
     */
    protected function executarRankings(User $user, string $mensagem, array $historico, array $parametros): ?array
    {
        if ($user->hasRole('aluno')) {
            return null;
        }

        $texto = $mensagem.' '.$this->combinarHistorico($historico);
        $trimestre = $parametros['trimestre'] ?? $this->analytics->extrairTrimestre($texto);

        return $this->analytics->pacoteRankings(
            $user,
            $trimestre,
            $parametros['turma_codigo'] ?? null,
            $this->analytics->extrairTermoCurso($texto),
            null,
            $this->analytics->extrairLimiteTop($texto),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function executarProfessoresTurma(User $user, ?string $turmaCodigo): array
    {
        if ($user->hasRole('aluno')) {
            return [];
        }

        return $this->analytics->professoresPorTurmas($user, $turmaCodigo);
    }

    /**
     * @param  array<int, array{papel:string, conteudo:string}>  $historico
     * @return array<string, mixed>
     */
    protected function extrairParametros(string $mensagem, array $historico): array
    {
        $texto = $mensagem.' '.$this->combinarHistorico($historico);
        $params = [
            'trimestre' => $this->analytics->extrairTrimestre($texto),
        ];

        if (preg_match('/\bturma\s+([A-Za-z0-9\-]+)/ui', $mensagem, $m)) {
            $params['turma_codigo'] = $m[1];
        }

        return $params;
    }

    protected function resolverTurma(User $user, ?string $codigo): ?Turma
    {
        if ($codigo) {
            $turmasIds = $this->access->getTurmasIdsAcessiveis($user);
            $turma = Turma::query()
                ->whereIn('id', $turmasIds)
                ->where(function ($q) use ($codigo) {
                    $q->where('codigo', 'like', "%{$codigo}%")
                        ->orWhere('nome', 'like', "%{$codigo}%");
                })
                ->first();

            if ($turma && $this->access->canViewTurma($user, $turma)) {
                return $turma;
            }
        }

        if ($user->hasRole('aluno') && $user->aluno_id) {
            return Aluno::query()->find($user->aluno_id)?->turma;
        }

        $ids = $this->access->getTurmasIdsAcessiveis($user);

        return $ids !== [] ? Turma::query()->find($ids[0]) : null;
    }

    /**
     * @param  array<int, array{papel:string, conteudo:string}>  $historico
     */
    protected function combinarHistorico(array $historico): string
    {
        return collect($historico)->take(-3)->pluck('conteudo')->implode(' ');
    }

    protected function mencionaHorario(string $msg): bool
    {
        return (bool) preg_match('/\b(hor[aá]rio|aulas?)\b/u', $msg);
    }

    protected function mencionaProvas(string $msg): bool
    {
        return (bool) preg_match('/\b(provas?|exames?|calend[aá]rio)\b/u', $msg);
    }

    protected function mencionaPendencias(string $msg): bool
    {
        return (bool) preg_match('/\b(pendente|em falta|lan[cç]ar)\b/u', $msg);
    }

    protected function mencionaRankings(string $msg): bool
    {
        return (bool) preg_match('/\b(ranking|melhores?|piores?|top)\b/u', $msg);
    }

    protected function mencionaEstatisticas(string $msg): bool
    {
        return (bool) preg_match('/\b(estat[ií]stica|resumo institucional|vis[aã]o geral)\b/u', $msg);
    }

    protected function mencionaProfessor(string $msg): bool
    {
        return (bool) preg_match('/\b(professor|professora|docente)\b/u', $msg);
    }

    protected function mencionaAluno(string $msg): bool
    {
        return (bool) preg_match('/\b(aluno|aluna|estudante)\b/u', $msg);
    }
}
