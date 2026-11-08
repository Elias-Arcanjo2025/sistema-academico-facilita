<?php

namespace App\Services\Assistente;

use App\Models\Aluno;
use App\Models\Configuracao;
use App\Models\Turma;
use App\Models\User;

class AssistantContextBuilder
{
    public function __construct(
        protected AssistantDataService $data,
        protected AssistantAccess $access,
        protected AssistantAnalyticsService $analytics,
        protected AssistantSystemDataService $systemData,
        protected AssistantToolRegistry $tools,
    ) {}

    /**
     * @param  array<int, array{papel:string, conteudo:string}>  $historico
     * @return array<string, mixed>
     */
    public function build(User $user, string $mensagem, array $historico = []): array
    {
        $textoRankings = $this->textoCombinadoParaRankings($mensagem, $historico);
        $msgLower = mb_strtolower($mensagem);
        $textoRankingsLower = mb_strtolower($textoRankings);

        $trimestre = $this->analytics->extrairTrimestre($textoRankings);
        $limiteTop = $this->analytics->extrairLimiteTop($textoRankings);

        $escopo = $this->data->getEscopoResumo($user);
        $semAnoLectivo = ! empty($escopo['sem_ano_lectivo']);

        $contexto = [
            'perfil' => $this->access->perfilResposta($user),
            'regras_resposta' => $this->access->regrasResposta($user),
            'escopo' => $escopo,
            'sem_ano_lectivo' => $semAnoLectivo,
            'dados_consultados' => [],
        ];

        if ($semAnoLectivo) {
            $contexto['aviso_operacional'] = [
                'codigo' => 'sem_ano_lectivo',
                'mensagem' => 'Não existe ano lectivo registado ou activo. Sem ano lectivo não há turmas, notas, pautas nem indicadores académicos.',
                'accao' => 'Orientar o utilizador a criar e activar um ano lectivo em Configurações → Anos Lectivos (botão «Novo Ano Lectivo»).',
                'instrucao' => 'Pode explicar o Facilita e o passo a passo para criar o ano. Não invente turmas, alunos, notas nem estatísticas.',
            ];

            $intencoes = $this->systemData->detectarIntencoes($mensagem, $historico);
            $contexto['dados_consultados']['sistema'] = $this->systemData->modulosSistema($user);
            $contexto['dados_consultados']['instituicao'] = $this->analytics->instituicaoPublica();
            if ($intencoes !== []) {
                $contexto['dados_consultados']['intencoes_detectadas'] = $intencoes;
            }

            return $contexto;
        }

        $intencoes = $this->systemData->detectarIntencoes($mensagem, $historico);
        $contexto['dados_consultados']['sistema'] = $this->systemData->modulosSistema($user);

        if ($intencoes !== []) {
            $contexto['dados_consultados']['intencoes_detectadas'] = $intencoes;
        }

        // Contexto base: escola e liderança (todos os perfis)
        $contexto['dados_consultados']['instituicao'] = $this->analytics->instituicaoPublica();

        if ($user->hasRole('aluno')) {
            $ctxAluno = $this->data->getContextoAlunoLogado($user);
            if ($ctxAluno !== null) {
                $contexto['dados_consultados']['aluno_sessao'] = $ctxAluno;
                if (! empty($ctxAluno['professores_da_turma'])) {
                    $contexto['dados_consultados']['professores'] = $ctxAluno['professores_da_turma'];
                }
                $contexto['dados_consultados']['lideranca'] = [
                    'directora_geral' => Configuracao::get('director_geral_nome')
                        ?: \App\Models\User::role(\App\Support\CargosInstitucionais::DIRECTORA)->value('name'),
                    'subdirector_pedagogico' => \App\Models\User::role(\App\Support\CargosInstitucionais::SUBDIRECTOR)->value('name'),
                    'gestao_da_turma' => $ctxAluno['gestao_institucional'] ?? [],
                ];
            }
        } elseif ($user->hasRole('encarregado')) {
            $ctxEnc = $this->data->getContextoEncarregadoLogado($user);
            if ($ctxEnc !== null) {
                $contexto['dados_consultados']['encarregado_sessao'] = $ctxEnc;
            }
            $contexto['dados_consultados']['lideranca'] = [
                'directora_geral' => Configuracao::get('director_geral_nome')
                    ?: \App\Models\User::role(\App\Support\CargosInstitucionais::DIRECTORA)->value('name'),
                'subdirector_pedagogico' => \App\Models\User::role(\App\Support\CargosInstitucionais::SUBDIRECTOR)->value('name'),
            ];
        } else {
            $ctxProfessor = $this->data->getPendenciasProfessor($user);
            if ($ctxProfessor !== null) {
                $contexto['dados_consultados']['professor_sessao'] = $ctxProfessor;
            }

            $contexto['dados_consultados']['lideranca'] = $this->analytics->liderancaInstitucional($user);
        }

        $msgLower = mb_strtolower($mensagem);
        $termos = $this->data->extrairTermosBusca($mensagem);

        if ($this->mencionaDirector($msgLower) || $this->mencionaLideranca($msgLower)) {
            $termoTurma = $this->extrairTermoTurma($termos, $msgLower);
            $directores = $this->data->getDirectoresTurma($user, $termoTurma);
            if ($directores !== []) {
                $contexto['dados_consultados']['directores_turma'] = $directores;
            }
        }

        if ($this->mencionaCoordenador($msgLower) || $this->mencionaLideranca($msgLower)) {
            $coordenadores = $this->data->getCoordenadoresCurso($user);
            if ($coordenadores !== []) {
                $contexto['dados_consultados']['coordenadores_curso'] = $coordenadores;
            }
        }

        if ($this->mencionaSubdirector($msgLower)) {
            $contexto['dados_consultados']['subdirector'] = [
                'nome' => \App\Models\User::role(\App\Support\CargosInstitucionais::SUBDIRECTOR)->value('name'),
                'cargo' => 'Subdirector Pedagógico',
            ];
        }

        if ($this->mencionaPendenciasProfessor($msgLower) && ! $user->hasRole('aluno')) {
            $pendencias = $this->data->getPendenciasProfessor($user);
            if ($pendencias !== null) {
                $contexto['dados_consultados']['pendencias_lancamento'] = $pendencias;
            }
        }

        if (! $user->hasRole('aluno') && ($this->mencionaAluno($msgLower) || $termos !== [])) {
            $alunos = $this->buscarAlunosRelevantes($user, $mensagem, $termos);
            if ($alunos !== []) {
                $contexto['dados_consultados']['alunos'] = $alunos;
            }
        }

        if ($this->mencionaTurma($msgLower) && ! $user->hasRole('aluno')) {
            $turmas = $this->buscarTurmasRelevantes($user, $termos);
            if ($turmas !== []) {
                $contexto['dados_consultados']['turmas'] = $turmas;
            }
        }

        if ($this->mencionaProfessor($msgLower)) {
            $termoTurma = $this->extrairTermoTurma($termos, $msgLower);
            if ($termoTurma && ! $user->hasRole('aluno')) {
                $porTurma = $this->analytics->professoresPorTurmas($user, $termoTurma);
                if ($porTurma !== []) {
                    $contexto['dados_consultados']['professores_por_turma'] = $porTurma;
                }
            }

            if (! $user->hasRole('aluno')) {
                $termoProfessor = $this->extrairTermoProfessor($termos);
                $professores = $this->data->getProfessores($user, $termoProfessor);
                if ($professores !== []) {
                    $contexto['dados_consultados']['professores'] = $professores;
                }
            }
        }

        if ($this->mencionaContagens($msgLower) && ! $user->hasRole('aluno')) {
            $termoCurso = $this->analytics->extrairTermoCurso($mensagem) ?? $this->extrairTermoCurso($termos, $msgLower);
            $contagens = $this->analytics->contagensAlunos(
                $user,
                $this->extrairTermoTurma($termos, $msgLower),
                $termoCurso,
                $this->extrairTermoClasse($msgLower),
            );
            if ($contagens !== []) {
                $contexto['dados_consultados']['contagens'] = $contagens;
            }
        }

        $termosRankings = $this->data->extrairTermosBusca($textoRankings);

        if ($this->deveCarregarRankings($msgLower, $textoRankingsLower) && ! $user->hasRole('aluno')) {
            $termoTurma = $this->extrairTermoTurma($termosRankings, $textoRankingsLower);
            $termoCurso = $this->analytics->extrairTermoCurso($textoRankings)
                ?? $this->extrairTermoCurso($termosRankings, $textoRankingsLower);
            $termoClasse = $this->extrairTermoClasse($textoRankingsLower);

            $contexto['dados_consultados']['rankings'] = $this->analytics->pacoteRankings(
                $user,
                $trimestre,
                $termoTurma,
                $termoCurso,
                $termoClasse,
                $limiteTop,
            );
        }

        if ($this->mencionaComparacao($msgLower) && ! $user->hasRole('aluno')) {
            $termoTurma = $this->extrairTermoTurma($termos, $msgLower);
            if ($termoTurma) {
                $anoId = Configuracao::getAnoLectivoActualId();
                $turma = Turma::query()
                    ->whereIn('id', $this->access->getTurmasIdsAcessiveis($user, $anoId))
                    ->where(function ($q) use ($termoTurma) {
                        $q->where('codigo', 'like', "%{$termoTurma}%")
                            ->orWhere('nome', 'like', "%{$termoTurma}%");
                    })
                    ->first();

                if ($turma) {
                    $comparacao = $this->analytics->comparacaoAlunosTurma($user, $turma, $trimestre);
                    if ($comparacao !== null) {
                        $contexto['dados_consultados']['comparacao_turma'] = $comparacao;
                    }
                }
            }
        }

        if ($this->mencionaBaixas($msgLower) && ! $user->hasRole('aluno')) {
            $baixas = $this->analytics->alunosComNotasBaixas(
                $user,
                $trimestre,
                $this->extrairTermoTurma($termos, $msgLower),
            );
            if ($baixas !== []) {
                $contexto['dados_consultados']['notas_baixas'] = $baixas;
            }
        }

        if ($this->mencionaMelhoria($msgLower) && ! $user->hasRole('aluno')) {
            $melhorias = $this->analytics->resumoMelhorias(
                $user,
                $this->extrairTermoTurma($termos, $msgLower),
            );
            if ($melhorias !== []) {
                $contexto['dados_consultados']['exame_melhoria'] = $melhorias;
            }
        }

        if ($this->mencionaEstatisticas($msgLower) && $this->access->canViewEstatisticas($user)) {
            $stats = $this->data->getEstatisticasResumo($user);
            if ($stats !== null) {
                $contexto['dados_consultados']['estatisticas'] = $stats;
            }
        }

        if (count($contexto['dados_consultados']) <= 2 && ! $user->hasRole('aluno')) {
            $contexto['dados_consultados']['sugestao'] = 'Use o escopo do utilizador para responder. '
                .'Pode perguntar sobre desempenho de alunos, melhores da turma, contagens, professores, coordenadores ou directores.';
        }

        $toolsResolvidas = $this->tools->resolverTools($user, $mensagem, $historico);
        $resultadosTools = $this->tools->executar($user, $toolsResolvidas, $mensagem, $historico);

        $contexto['tools'] = [
            'executadas' => $toolsResolvidas,
            'resultados' => $resultadosTools,
        ];

        $this->fundirResultadosTools($contexto, $resultadosTools);

        return $contexto;
    }

    /**
     * @param  array<string, mixed>  $contexto
     * @param  array<string, mixed>  $resultados
     */
    protected function fundirResultadosTools(array &$contexto, array $resultados): void
    {
        $mapa = [
            'aluno_sessao' => 'aluno_sessao',
            'horario' => 'horario',
            'calendario_provas' => 'calendario_provas',
            'rankings' => 'rankings',
            'pendencias_professor' => 'pendencias_lancamento',
            'estatisticas' => 'estatisticas',
            'sistema' => 'sistema',
        ];

        foreach ($mapa as $tool => $chave) {
            if (isset($resultados[$tool]) && $resultados[$tool] !== null) {
                $contexto['dados_consultados'][$chave] = $resultados[$tool];
            }
        }

        if (isset($resultados['buscar_alunos']['alunos']) && $resultados['buscar_alunos']['alunos'] !== []) {
            $contexto['dados_consultados']['alunos'] = $resultados['buscar_alunos']['alunos'];
        }

        if (isset($resultados['buscar_colaboradores']['colaboradores'])
            && $resultados['buscar_colaboradores']['colaboradores'] !== []) {
            $contexto['dados_consultados']['colaboradores'] = $resultados['buscar_colaboradores']['colaboradores'];
        }

        if (isset($resultados['professores_turma']) && $resultados['professores_turma'] !== []) {
            $contexto['dados_consultados']['professores_por_turma'] = $resultados['professores_turma'];
        }
    }

    /**
     * @param  array<int, string>  $termos
     * @return array<int, array<string, mixed>>
     */
    protected function buscarAlunosRelevantes(User $user, string $mensagem, array $termos): array
    {
        $resultados = [];
        $idsVistos = [];

        foreach ($termos as $termo) {
            foreach ($this->data->searchAlunos($user, $termo) as $resumo) {
                if (in_array($resumo['id'], $idsVistos, true)) {
                    continue;
                }
                $idsVistos[] = $resumo['id'];

                $aluno = Aluno::find($resumo['id']);
                if ($aluno) {
                    $relatorio = $this->data->getRelatorioAluno($user, $aluno);
                    if ($relatorio !== null) {
                        $resultados[] = $relatorio;
                    }
                }
            }
        }

        if ($resultados === [] && preg_match('/\b(\d{4,})\b/u', $mensagem, $m)) {
            $porProcesso = $this->data->searchAlunos($user, $m[1]);
            foreach ($porProcesso as $resumo) {
                $aluno = Aluno::find($resumo['id']);
                if ($aluno) {
                    $relatorio = $this->data->getRelatorioAluno($user, $aluno);
                    if ($relatorio !== null) {
                        $resultados[] = $relatorio;
                    }
                }
            }
        }

        return array_slice($resultados, 0, config('assistente.busca.alunos_max', 5));
    }

    /**
     * @param  array<int, string>  $termos
     * @return array<int, array<string, mixed>>
     */
    protected function buscarTurmasRelevantes(User $user, array $termos): array
    {
        $turmas = [];
        foreach ($termos as $termo) {
            foreach ($this->data->searchTurmas($user, $termo) as $turma) {
                $turmas[] = $turma;
            }
        }

        return array_slice($turmas, 0, config('assistente.busca.turmas_max', 3));
    }

    protected function mencionaAluno(string $msg): bool
    {
        return (bool) preg_match(
            '/\b(aluno|aluna|estudante|processo|desempenho|notas?|recomenda|sugest|melhorar|evolu)\b/u',
            $msg
        );
    }

    protected function mencionaTurma(string $msg): bool
    {
        return (bool) preg_match('/\b(turma|classe|curso|director|directora)\b/u', $msg);
    }

    protected function mencionaProfessor(string $msg): bool
    {
        return (bool) preg_match('/\b(professor|professora|docente|leciona|lecciona|meus?\s+professor)\b/u', $msg)
            || str_contains($msg, 'meus professores')
            || str_contains($msg, 'minhas professoras');
    }

    protected function mencionaDirector(string $msg): bool
    {
        return (bool) preg_match('/\b(director|directora|diretor|diretora)\s+(de\s+)?(turma|classe)\b/u', $msg)
            || str_contains($msg, 'director de turma')
            || str_contains($msg, 'diretor de turma');
    }

    protected function mencionaCoordenador(string $msg): bool
    {
        return (bool) preg_match('/\b(coordenador|coordenadora)\s+(de\s+)?(curso)?\b/u', $msg)
            || str_contains($msg, 'coordenador do curso')
            || str_contains($msg, 'coordenadora do curso');
    }

    protected function mencionaSubdirector(string $msg): bool
    {
        return (bool) preg_match('/\b(subdirector|subdirectora|subdiretor|subdiretora)\s*(pedag[oó]gic)?/u', $msg);
    }

    protected function mencionaLideranca(string $msg): bool
    {
        return (bool) preg_match('/\b(directora|direc[cç][aã]o|lideran[cç]a|equipa\s+pedag[oó]gica|gest[aã]o\s+escolar)\b/u', $msg);
    }

    protected function mencionaContagens(string $msg): bool
    {
        return (bool) preg_match(
            '/\b(quantos|quantas|n[uú]mero\s+de|total\s+de|contagem|contar)\s+(alunos?|estudantes?|turmas?|cursos?|escola)\b/u',
            $msg
        ) || (bool) preg_match('/\b(alunos?\s+(tem|h[aá]|existem))\b/u', $msg);
    }

    protected function mencionaMelhores(string $msg): bool
    {
        return (bool) preg_match(
            '/\b(melhor(es|es)?\s+aluno|melhor\s+desempenho|top\s+\d|ranking|destaques?|excel[eê]ncia|\d+\s+melhores|lista\s+(de\s+)?(\d+\s+)?melhores)\b/u',
            $msg
        ) || (bool) preg_match('/\blista\s+(dos|das)\s+\d+\b/u', $msg);
    }

    protected function deveCarregarRankings(string $msgActual, string $textoCombinado): bool
    {
        if ($this->mencionaMelhores($msgActual) || $this->mencionaMelhores($textoCombinado)) {
            return true;
        }

        if ($this->mencionaNoGeral($msgActual) || $this->mencionaNoGeral($textoCombinado)) {
            return (bool) preg_match('/\b(melhor|ranking|lista|top|desempenho|trimestre|classe|curso)\b/u', $textoCombinado);
        }

        return (bool) preg_match(
            '/\b(lista|top\s+\d|classifica[cç][aã]o|ranking)\b/u',
            $msgActual
        ) && (bool) preg_match('/\b(trimestre|melhor|desempenho|geral|classe)\b/u', $textoCombinado);
    }

    /**
     * @param  array<int, array{papel:string, conteudo:string}>  $historico
     */
    protected function textoCombinadoParaRankings(string $mensagem, array $historico): string
    {
        $partes = [];

        foreach (array_slice($historico, -8) as $item) {
            if (($item['papel'] ?? '') === 'user') {
                $partes[] = (string) ($item['conteudo'] ?? '');
            }
        }

        $partes[] = $mensagem;

        return implode(' ', $partes);
    }

    protected function mencionaNoGeral(string $msg): bool
    {
        return (bool) preg_match('/\b(no\s+)?geral\b/u', $msg)
            || (bool) preg_match('/\b(todas?\s+as?\s+turmas?|toda\s+a\s+(classe|escola))\b/u', $msg);
    }

    protected function mencionaComparacao(string $msg): bool
    {
        return (bool) preg_match(
            '/\b(comparar|compara[cç][aã]o|versus|vs\.?|entre\s+alunos|posi[cç][aã]o\s+na\s+turma|classifica[cç][aã]o\s+da\s+turma)\b/u',
            $msg
        );
    }

    protected function mencionaBaixas(string $msg): bool
    {
        return (bool) preg_match(
            '/\b(notas?\s+baixas?|negativas?|abaixo\s+(do\s+)?m[ií]nimo|reprovad|insuficiente|mt\s+baixa)\b/u',
            $msg
        );
    }

    protected function mencionaMelhoria(string $msg): bool
    {
        return (bool) preg_match(
            '/\b(exame\s+de\s+)?melhoria(\s+de\s+(nota|classifica[cç][aã]o))?|art\.?\s*34\b/u',
            $msg
        );
    }

    protected function mencionaPendenciasProfessor(string $msg): bool
    {
        return (bool) preg_match(
            '/\b(não\s+avali|nao\s+avali|sem\s+nota|notas?\s+em\s+falta|não\s+lanç|nao\s+lanc|pendente|falta\s+lançar|falta\s+lancar)\b/u',
            $msg
        ) || (bool) preg_match('/\b(alunos?)\b/u', $msg) && (bool) preg_match('/\b(nota|avali|lanç|lanc)\b/u', $msg);
    }

    protected function mencionaEstatisticas(string $msg): bool
    {
        return (bool) preg_match('/\b(estatística|estatistica|aproveitamento|reprovad|aprovad|resumo geral)\b/u', $msg);
    }

    /**
     * @param  array<int, string>  $termos
     */
    protected function extrairTermoProfessor(array $termos): ?string
    {
        return $termos[0] ?? null;
    }

    /**
     * @param  array<int, string>  $termos
     */
    protected function extrairTermoTurma(array $termos, string $msgLower): ?string
    {
        if (preg_match('/\b(\d{1,2})[ªa]?\s*classe\b/u', $msgLower)) {
            return null;
        }

        if (preg_match('/\bturma\s+([a-z0-9]{2,12})\b/ui', $msgLower, $m)) {
            return $m[1];
        }

        foreach ($termos as $termo) {
            if (preg_match('/\d/u', $termo) || preg_match('/^[a-z]{1,3}\d/ui', $termo)) {
                return $termo;
            }
        }

        return $termos[0] ?? null;
    }

    /**
     * @param  array<int, string>  $termos
     */
    protected function extrairTermoCurso(array $termos, string $msgLower): ?string
    {
        if (preg_match('/\bcurso\s+(?:de\s+)?([a-zà-ú\s]{3,40})\b/ui', $msgLower, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    protected function extrairTermoClasse(string $msgLower): ?string
    {
        if (preg_match('/\b(\d{1,2})[ªa]?\s*classe\b/u', $msgLower, $m)) {
            return 'T'.$m[1];
        }

        if (preg_match('/\bclasse\s+([a-z0-9]+)\b/u', $msgLower, $m)) {
            return $m[1];
        }

        return null;
    }
}
