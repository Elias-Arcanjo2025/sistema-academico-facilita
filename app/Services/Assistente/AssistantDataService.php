<?php

namespace App\Services\Assistente;

use App\Models\Aluno;
use App\Models\Configuracao;
use App\Models\Disciplina;
use App\Models\Nota;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\Turma;
use App\Models\User;
use App\Services\ConfiguracaoService;
use App\Services\EstatisticasService;
use Illuminate\Support\Collection;

class AssistantDataService
{
    public function __construct(
        protected AssistantAccess $access,
        protected ConfiguracaoService $configuracao,
        protected EstatisticasService $estatisticas,
        protected AssistantInsightsService $insights,
    ) {}

    public function getEscopoResumo(User $user): array
    {
        $anoId = Configuracao::getAnoLectivoActualId();
        $turmasIds = $anoId ? $this->access->getTurmasIdsAcessiveis($user, $anoId) : [];

        $limiteTurmas = (int) config('assistente.escopo.turmas_max', 15);
        $limiteAssociacoes = (int) config('assistente.escopo.associacoes_max', 20);

        $turmas = $turmasIds === []
            ? []
            : Turma::query()
                ->with(['curso', 'classe'])
                ->whereIn('id', $turmasIds)
                ->orderBy('nome')
                ->limit($limiteTurmas)
                ->get()
                ->map(fn (Turma $t) => [
                    'id' => $t->id,
                    'codigo' => $t->codigo,
                    'nome' => $t->nome,
                    'curso' => $t->curso?->nome,
                    'classe' => $t->classe?->codigo,
                ])
                ->values()
                ->all();

        $associacoes = ! $anoId
            ? []
            : $user->professorDisciplinaTurmas()
                ->with(['disciplina', 'turma'])
                ->where('activo', true)
                ->whereHas('turma', fn ($q) => $q->where('ano_lectivo_id', $anoId))
                ->limit($limiteAssociacoes)
                ->get()
                ->map(fn (ProfessorDisciplinaTurma $p) => [
                    'disciplina' => $p->disciplina?->nome,
                    'turma' => $p->turma?->codigo ?? $p->turma?->nome,
                ])
                ->values()
                ->all();

        return [
            'utilizador' => [
                'nome' => $user->name,
                'cargos' => $user->getRoleNames()->values()->all(),
                'e_aluno' => $user->hasRole('aluno'),
            ],
            'escopo' => $this->access->descricaoEscopo($user),
            'nivel_pii' => $this->access->nivelPii($user),
            'ano_lectivo' => $anoId ? Configuracao::getAnoLectivoActual() : null,
            'trimestre_actual' => $anoId ? $this->configuracao->getTrimestreActual() : null,
            'nota_minima_aprovacao' => $this->configuracao->getNotaMinimaAprovacao(),
            'instituicao' => [
                'nome' => Configuracao::get('instituicao_nome'),
                'endereco' => Configuracao::get('instituicao_endereco'),
                'telefone' => Configuracao::get('instituicao_telefone'),
                'email' => Configuracao::get('instituicao_email'),
                'website' => Configuracao::get('instituicao_website'),
            ],
            'turmas_acessiveis' => count($turmasIds),
            'turmas' => $turmas,
            'disciplinas_leccionadas' => $associacoes,
            // Sessões detalhadas ficam em dados_consultados (evita duplicar JSON pesado)
            'sem_ano_lectivo' => ! $anoId,
        ];
    }

    /**
     * Contexto automático do aluno autenticado (turma, professores, notas).
     *
     * @return array<string, mixed>|null
     */
    public function getContextoAlunoLogado(User $user): ?array
    {
        if (! $user->hasRole('aluno') || ! $user->aluno_id) {
            return null;
        }

        $aluno = Aluno::query()
            ->with(['turma.curso', 'turma.classe', 'turma.turno'])
            ->find($user->aluno_id);

        if (! $aluno) {
            return null;
        }

        $anoId = Configuracao::getAnoLectivoActualId();

        $relatorio = $this->getRelatorioAluno($user, $aluno);
        if ($relatorio === null) {
            return null;
        }

        $turma = $aluno->turma;
        $notasEscopo = $this->notasEscopoAluno($aluno, $anoId);
        $notaMinima = $this->configuracao->getNotaMinimaAprovacao();

        return [
            'perfil' => $relatorio['aluno'] ?? null,
            'turma' => $relatorio['turma'] ?? null,
            'gestao_institucional' => $this->insights->gestaoInstitucionalTurma($turma, $anoId),
            'desempenho_resumo' => $relatorio['desempenho_resumo'] ?? null,
            'disciplinas_da_turma' => $relatorio['disciplinas_da_turma'] ?? [],
            'analise_desempenho' => $this->insights->analisarDesempenhoAluno($notasEscopo, $notaMinima, $anoId),
            'notas_por_disciplina' => $relatorio['notas_por_disciplina'],
            'professores_da_turma' => $relatorio['professores_da_turma'] ?? [],
            'resumo_turma' => $turma ? $this->getResumoTurma($user, $turma) : null,
            'instrucao' => 'Este é o próprio aluno autenticado. Use APENAS disciplinas_da_turma e notas_por_disciplina '
                .'(plano curricular da turma actual — não inclua disciplinas de outros cursos/turmas). '
                .'Responda com analise_desempenho e gestao_institucional (coordenador, director de turma).',
        ];
    }

    /**
     * Contexto automático do encarregado autenticado (educandos associados).
     *
     * @return array<string, mixed>|null
     */
    public function getContextoEncarregadoLogado(User $user): ?array
    {
        if (! $user->hasRole('encarregado') || ! $user->encarregado_id) {
            return null;
        }

        $encarregado = $user->encarregado;
        if (! $encarregado) {
            return null;
        }

        $filhos = $encarregado->alunos()
            ->with(['turma.curso', 'turma.classe', 'turma.turno'])
            ->where('alunos.activo', true)
            ->orderBy('nome')
            ->get();

        if ($filhos->isEmpty()) {
            return [
                'encarregado' => ['nome' => $encarregado->nome],
                'educandos' => [],
                'instrucao' => 'Não há educandos associados. Oriente a contactar a secretaria.',
            ];
        }

        $educandos = [];
        foreach ($filhos as $aluno) {
            $podeVerNotas = (bool) ($aluno->pivot->pode_ver_notas ?? false);
            $item = [
                'id' => $aluno->id,
                'nome' => $aluno->nome,
                'numero_processo' => $aluno->numero_processo,
                'turma' => $aluno->turma?->codigo ?? $aluno->turma?->nome,
                'curso' => $aluno->turma?->curso?->nome,
                'classe' => $aluno->turma?->classe?->codigo,
                'pode_ver_notas' => $podeVerNotas,
            ];

            if ($podeVerNotas) {
                $relatorio = $this->getRelatorioAluno($user, $aluno);
                if ($relatorio !== null) {
                    $anoId = Configuracao::getAnoLectivoActualId();
                    $notasEscopo = $this->notasEscopoAluno($aluno, $anoId);
                    $notaMinima = $this->configuracao->getNotaMinimaAprovacao();
                    $item['desempenho_resumo'] = $relatorio['desempenho_resumo'] ?? null;
                    $item['notas_por_disciplina'] = $relatorio['notas_por_disciplina'] ?? [];
                    $item['analise_desempenho'] = $this->insights->analisarDesempenhoAluno($notasEscopo, $notaMinima, $anoId);
                    $item['professores_da_turma'] = $relatorio['professores_da_turma'] ?? [];
                    $item['gestao_institucional'] = $this->insights->gestaoInstitucionalTurma($aluno->turma, $anoId);
                }
            } else {
                $item['aviso'] = 'Sem permissão para ver notas deste educando.';
            }

            $educandos[] = $item;
        }

        $pedidosDocumentos = \App\Models\DocumentoPedido::query()
            ->with('aluno:id,nome')
            ->where('encarregado_id', $encarregado->id)
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (\App\Models\DocumentoPedido $p) => [
                'documento' => $p->tipoLabel(),
                'educando' => $p->aluno?->nome,
                'estado' => $p->estadoLabel(),
                'pedido_em' => $p->created_at?->format('Y-m-d'),
                'nota_secretaria' => $p->nota_secretaria,
            ])
            ->values()
            ->all();

        return [
            'encarregado' => [
                'nome' => $encarregado->nome,
                'telefone' => $encarregado->telefone,
            ],
            'educandos' => $educandos,
            'pedidos_documentos' => $pedidosDocumentos,
            'instrucao' => 'Este é o encarregado autenticado. Use APENAS os educandos listados. '
                .'Só mostre notas quando pode_ver_notas for true. Nunca invente outros alunos. '
                .'pedidos_documentos são os pedidos deste encarregado à secretaria — use para responder sobre o estado de documentos.',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchAlunos(User $user, string $termo, ?int $limit = null): array
    {
        $termo = trim($termo);
        if (mb_strlen($termo) < config('assistente.busca.termo_min_chars', 3)) {
            return [];
        }

        $limit = $limit ?? config('assistente.busca.alunos_max', 5);
        $turmasIds = $this->access->getTurmasIdsAcessiveis($user);

        if ($turmasIds === []) {
            return [];
        }

        $query = Aluno::query()
            ->with(['turma.curso'])
            ->where('activo', true)
            ->whereIn('turma_id', $turmasIds)
            ->where(function ($q) use ($termo) {
                $q->where('nome', 'like', "%{$termo}%")
                    ->orWhere('numero_processo', 'like', "%{$termo}%");
            })
            ->orderBy('nome')
            ->limit($limit);

        return $query->get()
            ->filter(fn (Aluno $a) => $this->access->canViewAluno($user, $a))
            ->map(fn (Aluno $a) => $this->formatAlunoResumo($user, $a))
            ->values()
            ->all();
    }

    public function getRelatorioAluno(User $user, Aluno $aluno): ?array
    {
        if (! $this->access->canViewAluno($user, $aluno)) {
            return null;
        }

        $aluno->load(['turma.curso', 'turma.classe', 'turma.turno']);

        $anoId = Configuracao::getAnoLectivoActualId();
        $notasEscopo = $this->notasEscopoAluno($aluno, $anoId);
        $disciplinasEscopo = $this->disciplinasEscopoOrdenadas($aluno);

        $notasPorDisciplina = $this->montarNotasPorDisciplina($aluno, $notasEscopo, $disciplinasEscopo);

        $professoresDisciplina = ProfessorDisciplinaTurma::query()
            ->with(['professor', 'disciplina'])
            ->where('turma_id', $aluno->turma_id)
            ->where('activo', true)
            ->whereIn('disciplina_id', $disciplinasEscopo->pluck('id'))
            ->get()
            ->map(fn (ProfessorDisciplinaTurma $p) => [
                'disciplina' => $p->disciplina?->nome,
                'professor' => $p->professor?->name,
            ])
            ->values()
            ->all();

        $resumo = $this->calcularResumoDesempenho($notasEscopo);
        $notaMinima = $this->configuracao->getNotaMinimaAprovacao();

        return [
            'aluno' => $this->formatAlunoDetalhe($user, $aluno),
            'turma' => [
                'codigo' => $aluno->turma?->codigo,
                'nome' => $aluno->turma?->nome,
                'curso' => $aluno->turma?->curso?->nome,
                'classe' => $aluno->turma?->classe?->codigo,
                'turno' => $aluno->turma?->turno?->nome,
            ],
            'ano_lectivo' => Configuracao::getAnoLectivoActual(),
            'disciplinas_da_turma' => $disciplinasEscopo->map(fn (Disciplina $d) => [
                'id' => $d->id,
                'nome' => $d->nome,
                'codigo' => $d->codigo,
            ])->values()->all(),
            'desempenho_resumo' => $resumo,
            'analise_desempenho' => $this->insights->analisarDesempenhoAluno($notasEscopo, $notaMinima, $anoId),
            'gestao_institucional' => $this->insights->gestaoInstitucionalTurma($aluno->turma, $anoId),
            'notas_por_disciplina' => $notasPorDisciplina,
            'professores_da_turma' => $professoresDisciplina,
            'instrucao' => 'Apresente apenas disciplinas_da_turma (curso + classe da turma actual). '
                .'Use analise_desempenho para comparações trimestrais, recomendações e disciplinas a melhorar.',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchTurmas(User $user, string $termo): array
    {
        $termo = trim($termo);
        if (mb_strlen($termo) < 2) {
            return [];
        }

        $turmasIds = $this->access->getTurmasIdsAcessiveis($user);
        $limit = config('assistente.busca.turmas_max', 3);

        return Turma::query()
            ->with(['curso', 'classe'])
            ->whereIn('id', $turmasIds)
            ->where(function ($q) use ($termo) {
                $q->where('codigo', 'like', "%{$termo}%")
                    ->orWhere('nome', 'like', "%{$termo}%");
            })
            ->orderBy('nome')
            ->limit($limit)
            ->get()
            ->map(fn (Turma $t) => $this->getResumoTurma($user, $t))
            ->values()
            ->all();
    }

    public function getResumoTurma(User $user, Turma $turma): array
    {
        if (! $this->access->canViewTurma($user, $turma)) {
            return [];
        }

        $turma->load(['curso', 'classe', 'turno']);

        $alunos = Aluno::query()
            ->where('turma_id', $turma->id)
            ->where('activo', true)
            ->get();

        $porSituacao = $alunos->groupBy('situacao_academica')
            ->map->count()
            ->all();

        $professores = ProfessorDisciplinaTurma::query()
            ->with(['professor', 'disciplina'])
            ->where('turma_id', $turma->id)
            ->where('activo', true)
            ->get()
            ->map(fn (ProfessorDisciplinaTurma $p) => [
                'professor' => $p->professor?->name,
                'disciplina' => $p->disciplina?->nome,
            ])
            ->values()
            ->all();

        $director = $turma->directores()
            ->wherePivot('activo', true)
            ->first();

        return [
            'turma' => [
                'codigo' => $turma->codigo,
                'nome' => $turma->nome,
                'curso' => $turma->curso?->nome,
                'classe' => $turma->classe?->codigo,
                'turno' => $turma->turno?->nome,
            ],
            'total_alunos' => $alunos->count(),
            'por_situacao' => $porSituacao,
            'director_turma' => $director?->name,
            'professores' => $professores,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getProfessores(User $user, ?string $termo = null): array
    {
        if ($user->hasRole('aluno')) {
            $turmaId = $user->aluno?->turma_id;
            if (! $turmaId) {
                return [];
            }

            return ProfessorDisciplinaTurma::query()
                ->with(['professor', 'disciplina'])
                ->where('turma_id', $turmaId)
                ->where('activo', true)
                ->get()
                ->map(fn (ProfessorDisciplinaTurma $p) => [
                    'nome' => $p->professor?->name,
                    'disciplina' => $p->disciplina?->nome,
                ])
                ->values()
                ->all();
        }

        $turmasIds = $this->access->getTurmasIdsAcessiveis($user);
        if ($turmasIds === []) {
            return [];
        }

        $query = ProfessorDisciplinaTurma::query()
            ->with(['professor', 'disciplina', 'turma'])
            ->whereIn('turma_id', $turmasIds)
            ->where('activo', true);

        if ($termo && mb_strlen(trim($termo)) >= 2) {
            $termo = trim($termo);
            $query->whereHas('professor', fn ($q) => $q->where('name', 'like', "%{$termo}%"));
        }

        $limit = config('assistente.busca.professores_max', 8);

        return $query->get()
            ->unique(fn (ProfessorDisciplinaTurma $p) => $p->user_id.'-'.$p->disciplina_id.'-'.$p->turma_id)
            ->take($limit)
            ->map(fn (ProfessorDisciplinaTurma $p) => [
                'nome' => $p->professor?->name,
                'email' => null,
                'disciplina' => $p->disciplina?->nome,
                'turma' => $p->turma?->codigo ?? $p->turma?->nome,
            ])
            ->values()
            ->all();
    }

    /**
     * Pesquisa colaboradores (professores, direcção, coordenação, secretaria…) por nome.
     * Permite responder a perguntas genéricas como «Quem é o Fulano?» quando a pessoa
     * não é aluno mas sim membro da equipa da escola.
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchColaboradores(User $user, string $termo, ?int $limit = null): array
    {
        if ($user->hasRole('aluno') || $user->hasRole('encarregado')) {
            return [];
        }

        $termo = trim($termo);
        if (mb_strlen($termo) < config('assistente.busca.termo_min_chars', 3)) {
            return [];
        }

        $limit = $limit ?? config('assistente.busca.professores_max', 8);

        $colaboradores = User::query()
            ->where('name', 'like', "%{$termo}%")
            ->whereHas('roles', fn ($q) => $q->whereNotIn('name', ['aluno', 'encarregado']))
            ->orderBy('name')
            ->limit($limit)
            ->get();

        if ($colaboradores->isEmpty()) {
            return [];
        }

        $anoId = Configuracao::getAnoLectivoActualId();
        $nivelPii = $this->access->nivelPii($user);
        // Emails de colaboradores não vão para o LLM (PII desnecessário para respostas académicas)
        $incluirEmail = false;
        $incluirLancamentos = in_array($nivelPii, [AssistantAccess::PII_COMPLETO, AssistantAccess::PII_GESTAO], true);

        return $colaboradores
            ->map(function (User $colaborador) use ($anoId, $incluirEmail, $incluirLancamentos) {
                $atribuicoes = ProfessorDisciplinaTurma::query()
                    ->with(['disciplina', 'turma'])
                    ->where('user_id', $colaborador->id)
                    ->where('activo', true)
                    ->when($anoId, fn ($q) => $q->where('ano_lectivo_id', $anoId))
                    ->limit(10)
                    ->get()
                    ->map(function (ProfessorDisciplinaTurma $p) use ($anoId, $incluirLancamentos) {
                        $item = [
                            'disciplina' => $p->disciplina?->nome,
                            'turma' => $p->turma?->codigo ?? $p->turma?->nome,
                        ];

                        if ($incluirLancamentos) {
                            $item['total_alunos'] = Aluno::query()
                                ->where('turma_id', $p->turma_id)
                                ->where('activo', true)
                                ->count();
                            $item['notas_lancadas'] = $this->resumoLancamentos($p, $anoId);
                        }

                        return $item;
                    })
                    ->values()
                    ->all();

                return [
                    'nome' => $colaborador->name,
                    'cargos' => $colaborador->getRoleNames()->values()->all(),
                    'email' => $incluirEmail ? $colaborador->email : null,
                    'lecciona' => $atribuicoes,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Resumo de notas lançadas por trimestre/tipo para uma atribuição
     * disciplina+turma (n.º de alunos avaliados por tipo de nota).
     *
     * @return array<string, array<string, int>>
     */
    protected function resumoLancamentos(ProfessorDisciplinaTurma $atribuicao, ?int $anoId): array
    {
        $linhas = Nota::query()
            ->where('disciplina_id', $atribuicao->disciplina_id)
            ->where('turma_id', $atribuicao->turma_id)
            ->when($anoId, fn ($q) => $q->where('ano_lectivo_id', $anoId))
            ->whereNull('archived_at')
            ->selectRaw('trimestre, tipo, COUNT(DISTINCT aluno_id) as alunos_avaliados')
            ->groupBy('trimestre', 'tipo')
            ->orderBy('trimestre')
            ->get();

        $resumo = [];
        foreach ($linhas as $linha) {
            $chave = $linha->trimestre ? "{$linha->trimestre}º trimestre" : 'sem trimestre';
            $resumo[$chave][strtoupper((string) $linha->tipo)] = (int) $linha->alunos_avaliados;
        }

        return $resumo;
    }

    public function getEstatisticasResumo(User $user): ?array
    {
        if (! $this->access->canViewEstatisticas($user)) {
            return null;
        }

        $turmasIds = $this->access->getTurmasIdsAcessiveis($user);

        if ($user->temVisaoInstitucional()) {
            return $this->estatisticas->getEstatisticasGerais();
        }

        $stats = [
            'total_alunos' => Aluno::query()
                ->where('activo', true)
                ->whereIn('turma_id', $turmasIds)
                ->count(),
            'por_situacao' => Aluno::query()
                ->selectRaw('situacao_academica, COUNT(*) as total')
                ->where('activo', true)
                ->whereIn('turma_id', $turmasIds)
                ->groupBy('situacao_academica')
                ->pluck('total', 'situacao_academica')
                ->all(),
            'turmas_no_escopo' => count($turmasIds),
        ];

        return $stats;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDirectoresTurma(User $user, ?string $termo = null): array
    {
        $anoId = Configuracao::getAnoLectivoActualId();
        if (! $anoId) {
            return [];
        }

        $turmasIds = $this->access->getTurmasIdsAcessiveis($user, $anoId);

        return $this->insights->listarDirectoresTurmas($turmasIds, $anoId, $termo);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCoordenadoresCurso(User $user): array
    {
        $anoId = Configuracao::getAnoLectivoActualId();
        if (! $anoId) {
            return [];
        }

        $turmasIds = $this->access->getTurmasIdsAcessiveis($user, $anoId);

        $cursoIds = Turma::query()
            ->whereIn('id', $turmasIds)
            ->pluck('curso_id')
            ->unique()
            ->filter()
            ->values()
            ->all();

        return $this->insights->listarCoordenadoresCursos($cursoIds, $anoId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPendenciasProfessor(User $user): ?array
    {
        $anoId = Configuracao::getAnoLectivoActualId();

        return $this->insights->contextoProfessor($user, $anoId);
    }

    /**
     * @return array<int, string>
     */
    public function extrairTermosBusca(string $mensagem): array
    {
        $stopWords = [
            'como', 'está', 'esta', 'estao', 'qual', 'quais', 'quem', 'onde', 'quando',
            'sobre', 'para', 'por', 'com', 'sem', 'uma', 'uns', 'umas', 'dos', 'das',
            'nos', 'nas', 'que', 'quê', 'meu', 'minha', 'seu', 'sua', 'aluno', 'aluna',
            'alunos', 'alunas', 'estudante', 'estudantes', 'turma', 'turmas',
            'professor', 'professora', 'professores', 'professoras', 'docente', 'docentes',
            'colaborador', 'colaboradora', 'colaboradores', 'colaboradoras',
            'disciplina', 'disciplinas', 'classe', 'classes', 'curso', 'cursos',
            'nota', 'notas', 'consta', 'constam', 'lista', 'listas', 'então', 'entao',
            'lançou', 'lancou', 'lançar', 'lancar', 'lançadas', 'lancadas',
            'lançamento', 'lancamento', 'pauta', 'pautas', 'todas', 'todos',
            'desempenho', 'relatório', 'relatorio', 'facilita', 'sistema', 'diga', 'mostre',
            'mostra', 'preciso', 'quero', 'saber', 'estao', 'estão', 'está', 'the', 'and',
        ];

        $palavras = preg_split('/\s+/u', mb_strtolower($mensagem)) ?: [];
        $termos = [];

        foreach ($palavras as $palavra) {
            $palavra = preg_replace('/[^\p{L}\p{N}]/u', '', $palavra) ?? '';
            if ($palavra === '' || in_array($palavra, $stopWords, true)) {
                continue;
            }
            if (mb_strlen($palavra) >= config('assistente.busca.termo_min_chars', 3)) {
                $termos[] = $palavra;
            }
        }

        return array_values(array_unique($termos));
    }

    protected function formatAlunoResumo(User $user, Aluno $aluno): array
    {
        return [
            'id' => $aluno->id,
            'nome' => $aluno->nome,
            'numero_processo' => $aluno->numero_processo,
            'situacao_academica' => $aluno->situacao_academica,
            'turma' => $aluno->turma?->codigo ?? $aluno->turma?->nome,
            'genero' => $this->access->incluirDadosPessoais($user, $aluno) ? $aluno->genero : null,
        ];
    }

    protected function formatAlunoDetalhe(User $user, Aluno $aluno): array
    {
        $dados = [
            'nome' => $aluno->nome,
            'numero_processo' => $aluno->numero_processo,
            'situacao_academica' => $aluno->situacao_academica,
            'genero' => $aluno->genero,
            'numero_ordem' => $aluno->numero_ordem,
            'activo' => $aluno->activo,
        ];

        if ($this->access->incluirDadosPessoais($user, $aluno)) {
            // Idade agregada basta para o LLM — sem NEE nem data de nascimento (PII sensível)
            $dados['idade'] = $aluno->idade;
        } else {
            unset($dados['genero']);
        }

        return $dados;
    }

    /**
     * Disciplinas do plano curricular da turma (curso + classe actual).
     *
     * @return Collection<int, Disciplina>
     */
    public function disciplinasDaTurma(?Turma $turma): Collection
    {
        if (! $turma?->curso_id || ! $turma->classe_id) {
            return collect();
        }

        return Disciplina::query()
            ->whereHas('cursos', fn ($q) => $q->where('cursos.id', $turma->curso_id))
            ->whereHas('classes', fn ($q) => $q->where('classes.id', $turma->classe_id))
            ->where('activo', true)
            ->ordenadaPorTipo()
            ->get();
    }

    /**
     * Disciplinas terminais de classes anteriores do mesmo curso (CFD carry-over na pauta).
     *
     * @return Collection<int, Disciplina>
     */
    protected function disciplinasTerminaisAnteriores(?Turma $turma): Collection
    {
        if (! $turma?->curso_id || ! $turma->classe_id) {
            return collect();
        }

        $classeNumero = (int) preg_replace('/\D/', '', $turma->classe?->codigo ?? $turma->classe?->nome ?? '');
        if ($classeNumero <= 10) {
            return collect();
        }

        return Disciplina::query()
            ->whereHas('cursos', function ($q) use ($turma, $classeNumero) {
                $q->where('cursos.id', $turma->curso_id)
                    ->where('curso_disciplina.classe_fim', '<', $classeNumero);
            })
            ->where('activo', true)
            ->ordenadaPorTipo()
            ->get();
    }

    /**
     * Plano curricular completo para relatório: disciplinas da classe + terminais anteriores (CFD).
     *
     * @return Collection<int, Disciplina>
     */
    public function disciplinasEscopoOrdenadas(Aluno $aluno): Collection
    {
        $turma = $aluno->turma;
        if (! $turma) {
            return collect();
        }

        return $this->disciplinasDaTurma($turma)
            ->concat($this->disciplinasTerminaisAnteriores($turma))
            ->unique('id')
            ->values();
    }

    /**
     * Notas do aluno limitadas à turma, ano lectivo e disciplinas do plano curricular.
     *
     * @return Collection<int, Nota>
     */
    public function notasEscopoAluno(Aluno $aluno, ?int $anoLectivoId = null): Collection
    {
        $turma = $aluno->turma;
        $anoLectivoId = $anoLectivoId ?? Configuracao::getAnoLectivoActualId();

        if (! $turma || ! $anoLectivoId) {
            return collect();
        }

        $disciplinasClasse = $this->disciplinasDaTurma($turma);
        $disciplinasTerminais = $this->disciplinasTerminaisAnteriores($turma);
        $idsClasse = $disciplinasClasse->pluck('id')->all();
        $idsTerminais = $disciplinasTerminais->pluck('id')->all();

        if ($idsClasse === [] && $idsTerminais === []) {
            return collect();
        }

        $notas = collect();

        if ($idsClasse !== []) {
            $notas = Nota::query()
                ->where('aluno_id', $aluno->id)
                ->where('turma_id', $turma->id)
                ->where('ano_lectivo_id', $anoLectivoId)
                ->whereIn('disciplina_id', $idsClasse)
                ->whereNull('archived_at')
                ->with('disciplina')
                ->get();
        }

        if ($idsTerminais !== []) {
            $cfdTerminais = Nota::query()
                ->where('aluno_id', $aluno->id)
                ->whereIn('disciplina_id', $idsTerminais)
                ->where('tipo', Nota::TIPO_CFD)
                ->whereNull('archived_at')
                ->orderByDesc('ano_lectivo_id')
                ->get()
                ->unique('disciplina_id');

            $notas = $notas->merge($cfdTerminais);
        }

        return $notas->loadMissing('disciplina');
    }

    /**
     * @param  Collection<int, Disciplina>  $disciplinasEscopo
     * @return array<int, array<string, mixed>>
     */
    protected function montarNotasPorDisciplina(
        Aluno $aluno,
        Collection $notas,
        Collection $disciplinasEscopo,
    ): array {
        $porDisciplina = $notas->groupBy('disciplina_id');

        return $disciplinasEscopo
            ->map(function (Disciplina $disciplina) use ($porDisciplina, $aluno) {
                $notasDisc = $porDisciplina->get($disciplina->id, collect());
                $linhas = AssistantNotaPresenter::paraDisciplina($notasDisc, $aluno->turma);

                if ($linhas === []) {
                    return null;
                }

                return [
                    'disciplina' => $disciplina->nome,
                    'codigo' => $disciplina->codigo,
                    'notas' => $linhas,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }


    /**
     * @param  Collection<int, Nota>  $notas
     * @return array<string, mixed>
     */
    protected function calcularResumoDesempenho(Collection $notas): array
    {
        $notaMinima = $this->configuracao->getNotaMinimaAprovacao();

        $cfd = $notas->where('tipo', Nota::TIPO_CFD);
        $negativas = $cfd->filter(fn (Nota $n) => (float) $n->valor < $notaMinima)->count();
        $positivas = $cfd->filter(fn (Nota $n) => (float) $n->valor >= $notaMinima)->count();

        $mediasCfd = $cfd->pluck('valor')->map(fn ($v) => (float) $v);
        $mediaGeral = $mediasCfd->isNotEmpty() ? round($mediasCfd->avg(), 2) : null;

        $mt = $notas->where('tipo', Nota::TIPO_MT);
        $mediasTrimestre = $mt->groupBy('trimestre')
            ->map(fn (Collection $grupo) => round($grupo->avg('valor'), 2))
            ->all();

        return [
            'disciplinas_com_cfd' => $cfd->count(),
            'negativas' => $negativas,
            'positivas' => $positivas,
            'media_cfd' => $mediaGeral,
            'medias_trimestrais_mt' => $mediasTrimestre,
        ];
    }
}
