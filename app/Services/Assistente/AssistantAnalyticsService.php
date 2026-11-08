<?php

namespace App\Services\Assistente;

use App\Models\Aluno;
use App\Models\Classe;
use App\Models\Configuracao;
use App\Models\CoordenadorCurso;
use App\Models\Curso;
use App\Models\Nota;
use App\Models\Turma;
use App\Models\User;
use App\Services\MelhoriaService;
use App\Services\PautaService;
use App\Services\RelatorioAcademicoService;
use App\Support\CargosInstitucionais;
use Illuminate\Support\Collection;

/**
 * Dados analíticos e institucionais para o Assistente Facilita.
 */
class AssistantAnalyticsService
{
    public function __construct(
        protected AssistantAccess $access,
        protected RelatorioAcademicoService $relatorioAcademico,
        protected MelhoriaService $melhoriaService,
        protected AssistantDataService $dataService,
        protected PautaService $pautaService,
    ) {}

    /**
     * Dados públicos da escola (seguros para alunos).
     *
     * @return array<string, mixed>
     */
    public function instituicaoPublica(): array
    {
        return [
            'nome' => Configuracao::get('instituicao_nome'),
            'endereco' => Configuracao::get('instituicao_endereco'),
            'telefone' => Configuracao::get('instituicao_telefone'),
            'email' => Configuracao::get('instituicao_email'),
            'website' => Configuracao::get('instituicao_website'),
            'ano_lectivo_actual' => Configuracao::getAnoLectivoActual(),
        ];
    }

    /**
     * Liderança e equipa pedagógica institucional.
     *
     * @return array<string, mixed>
     */
    public function liderancaInstitucional(User $user): array
    {
        $anoId = Configuracao::getAnoLectivoActualId();
        $turmasIds = $this->access->getTurmasIdsAcessiveis($user, $anoId);

        $cursoIds = Turma::query()
            ->whereIn('id', $turmasIds)
            ->pluck('curso_id')
            ->unique()
            ->filter()
            ->values()
            ->all();

        $subdirector = User::role(CargosInstitucionais::SUBDIRECTOR)->value('name');
        $directora = Configuracao::get('director_geral_nome')
            ?: User::role(CargosInstitucionais::DIRECTORA)->value('name');

        $coordenadores = CoordenadorCurso::query()
            ->with(['coordenador', 'curso'])
            ->where('activo', true)
            ->where('ano_lectivo_id', $anoId)
            ->when($cursoIds !== [], fn ($q) => $q->whereIn('curso_id', $cursoIds))
            ->get()
            ->map(fn (CoordenadorCurso $c) => [
                'curso' => $c->curso?->nome,
                'coordenador' => $c->coordenador?->name,
            ])
            ->sortBy('curso')
            ->values()
            ->all();

        return [
            'instituicao' => $this->instituicaoPublica(),
            'directora_geral' => $directora,
            'subdirector_pedagogico' => $subdirector,
            'coordenadores_curso' => $coordenadores,
            'instrucao' => 'Use estes nomes para responder quem é coordenador, subdirector ou directora. '
                .'Para director de turma específico, use directores_turma ou gestao_institucional.',
        ];
    }

    /**
     * Contagens de alunos no âmbito do utilizador.
     *
     * @return array<string, mixed>
     */
    public function contagensAlunos(
        User $user,
        ?string $termoTurma = null,
        ?string $termoCurso = null,
        ?string $termoClasse = null,
    ): array {
        if ($user->hasRole('aluno')) {
            return [];
        }

        $anoId = Configuracao::getAnoLectivoActualId();
        $turmasIds = $this->access->getTurmasIdsAcessiveis($user, $anoId);

        $turma = $termoTurma ? $this->resolverTurma($user, $termoTurma, $anoId) : null;
        $curso = $termoCurso ? $this->resolverCurso($user, $termoCurso, $anoId) : null;
        $classe = $termoClasse ? $this->resolverClasse($termoClasse) : null;

        $query = Turma::query()
            ->with(['curso', 'classe'])
            ->whereIn('id', $turmasIds)
            ->where('activo', true);

        if ($turma) {
            $query->where('id', $turma->id);
        }
        if ($curso) {
            $query->where('curso_id', $curso->id);
        }
        if ($classe) {
            $query->where('classe_id', $classe->id);
        }

        $turmas = $query->get();
        $turmasFiltradasIds = $turmas->pluck('id')->all();

        $totalAlunos = Aluno::query()
            ->where('activo', true)
            ->whereIn('turma_id', $turmasFiltradasIds)
            ->count();

        $porTurma = $turmas->map(fn (Turma $t) => [
            'turma' => $t->codigo ?? $t->nome,
            'curso' => $t->curso?->nome,
            'classe' => $t->classe?->codigo,
            'total_alunos' => Aluno::query()->where('turma_id', $t->id)->where('activo', true)->count(),
        ])->sortBy('turma')->values()->all();

        $porCurso = $turmas->groupBy('curso_id')->map(function (Collection $grupo) {
            $cursoNome = $grupo->first()?->curso?->nome ?? '—';

            return [
                'curso' => $cursoNome,
                'total_turmas' => $grupo->count(),
                'total_alunos' => Aluno::query()
                    ->where('activo', true)
                    ->whereIn('turma_id', $grupo->pluck('id'))
                    ->count(),
            ];
        })->sortBy('curso')->values()->all();

        $porClasse = $turmas->groupBy('classe_id')->map(function (Collection $grupo) {
            $classeCod = $grupo->first()?->classe?->codigo ?? '—';

            return [
                'classe' => $classeCod,
                'total_turmas' => $grupo->count(),
                'total_alunos' => Aluno::query()
                    ->where('activo', true)
                    ->whereIn('turma_id', $grupo->pluck('id'))
                    ->count(),
            ];
        })->sortBy('classe')->values()->all();

        $escolaTotal = Aluno::query()
            ->where('activo', true)
            ->whereIn('turma_id', $turmasIds)
            ->count();

        return [
            'filtros_aplicados' => array_filter([
                'turma' => $turma?->codigo ?? $turma?->nome,
                'curso' => $curso?->nome,
                'classe' => $classe?->codigo,
            ]),
            'total_alunos' => $totalAlunos,
            'total_turmas' => $turmas->count(),
            'total_escola_escopo' => $escolaTotal,
            'total_turmas_escopo' => count($turmasIds),
            'por_turma' => array_slice($porTurma, 0, 20),
            'por_curso' => $porCurso,
            'por_classe' => $porClasse,
            'ano_lectivo' => Configuracao::getAnoLectivoActual(),
            'instrucao' => 'Use estes totais para responder quantos alunos há na turma, curso, classe ou escola (no âmbito do utilizador).',
        ];
    }

    /**
     * Pacote completo de rankings para o assistente (turma, curso, classe, trimestres).
     *
     * @return array<string, mixed>
     */
    public function pacoteRankings(
        User $user,
        ?int $trimestreExplicito = null,
        ?string $termoTurma = null,
        ?string $termoCurso = null,
        ?string $termoClasse = null,
        int $limiteTop = 10,
    ): array {
        if ($user->hasRole('aluno')) {
            return [];
        }

        $trimestreActual = (int) Configuracao::get('trimestre_actual', 1);
        $trimestreConsulta = $trimestreExplicito ?? $trimestreActual;

        $pacote = [
            'trimestre_actual' => $trimestreActual,
            'trimestre_consultado' => $trimestreConsulta,
            'metrica_consultada' => RelatorioAcademicoService::metricaRankingLabel($trimestreConsulta),
            'filtros' => array_filter([
                'turma' => $termoTurma,
                'curso' => $termoCurso,
                'curso_resolvido' => $termoCurso
                    ? ($this->resolverCurso($user, $termoCurso, Configuracao::getAnoLectivoActualId())?->nome)
                    : null,
                'classe' => $termoClasse,
            ]),
            'instrucao' => 'Use melhor_geral para «melhor aluno» agregado; use top_geral para listas («10 melhores», «ranking geral») — NÃO peça turma ou curso se top_geral existir. '
                .'Se trimestre não foi pedido na pergunta, use trimestre_consultado (= trimestre actual). '
                .'panorama_trimestres traz o melhor de T1/T2/T3 quando útil comparar períodos.',
            'limite_top_solicitado' => $limiteTop,
        ];

        if ($termoTurma) {
            $pacote['melhores_por_turma'] = $this->rankingMelhores(
                $user,
                $trimestreConsulta,
                $termoTurma,
                $termoCurso,
            );
        } else {
            $pacote['melhores_por_turma'] = $this->rankingMelhores(
                $user,
                $trimestreConsulta,
                null,
                $termoCurso,
            );
        }

        if (! $termoTurma) {
            $geral = $this->rankingMelhorGeral(
                $user,
                $trimestreConsulta,
                $termoClasse,
                $termoCurso,
                $limiteTop,
            );
            if ($geral !== []) {
                $pacote['melhor_desempenho_geral'] = $geral;
            }

            if ($termoCurso === null) {
                $pacote['melhores_por_curso'] = $this->rankingMelhorPorCurso(
                    $user,
                    $trimestreConsulta,
                    $termoClasse,
                );
            }

            if ($trimestreExplicito === null) {
                $pacote['panorama_trimestres'] = $this->panoramaMelhoresTrimestres(
                    $user,
                    $termoClasse,
                    $termoCurso,
                );
            }
        }

        if ($termoTurma || $termoCurso || $termoClasse) {
            $pacote['destaques_disciplinas'] = $this->rankingDestaquesDisciplina(
                $user,
                $trimestreConsulta,
                $termoTurma,
                $termoCurso,
                $termoClasse,
            );
        }

        return $pacote;
    }

    /**
     * Melhor aluno de cada curso (no âmbito, opcionalmente filtrado por classe).
     *
     * @return array<int, array<string, mixed>>
     */
    public function rankingMelhorPorCurso(
        User $user,
        ?int $trimestre = null,
        ?string $termoClasse = null,
    ): array {
        if ($user->hasRole('aluno')) {
            return [];
        }

        $anoId = Configuracao::getAnoLectivoActualId();
        $trimestre = $trimestre ?? (int) Configuracao::get('trimestre_actual', 1);
        $turmasIds = $this->access->getTurmasIdsAcessiveis($user, $anoId);
        $classe = $termoClasse ? $this->resolverClasse($termoClasse) : null;

        $cursoIds = Turma::query()
            ->whereIn('id', $turmasIds)
            ->where('activo', true)
            ->when($classe, fn ($q) => $q->where('classe_id', $classe->id))
            ->pluck('curso_id')
            ->unique()
            ->filter();

        return Curso::query()
            ->whereIn('id', $cursoIds)
            ->orderBy('nome')
            ->get()
            ->map(function (Curso $curso) use ($user, $trimestre, $termoClasse) {
                $geral = $this->rankingMelhorGeral($user, $trimestre, $termoClasse, $curso->nome);

                return [
                    'curso' => $curso->nome,
                    'melhor_aluno' => $geral['melhor_geral'] ?? null,
                    'total_elegiveis' => $geral['total_alunos_elegiveis'] ?? 0,
                    'metrica' => $geral['metrica'] ?? RelatorioAcademicoService::metricaRankingLabel($trimestre),
                ];
            })
            ->filter(fn ($r) => $r['melhor_aluno'] !== null)
            ->values()
            ->all();
    }

    /**
     * Melhor aluno agregado por trimestre (T1, T2, T3) — útil quando o utilizador não especifica o trimestre.
     *
     * @return array<int, array<string, mixed>>
     */
    public function panoramaMelhoresTrimestres(
        User $user,
        ?string $termoClasse = null,
        ?string $termoCurso = null,
    ): array {
        if ($user->hasRole('aluno')) {
            return [];
        }

        $resultado = [];

        foreach ([1, 2, 3] as $t) {
            $geral = $this->rankingMelhorGeral($user, $t, $termoClasse, $termoCurso);
            $resultado[] = [
                'trimestre' => $t,
                'metrica' => RelatorioAcademicoService::metricaRankingLabel($t),
                'melhor_geral' => $geral['melhor_geral'] ?? null,
                'total_elegiveis' => $geral['total_alunos_elegiveis'] ?? 0,
            ];
        }

        return $resultado;
    }

    /**
     * Melhor nota por disciplina (top aluno em cada disciplina no âmbito).
     *
     * @return array<int, array<string, mixed>>
     */
    public function rankingDestaquesDisciplina(
        User $user,
        ?int $trimestre = null,
        ?string $termoTurma = null,
        ?string $termoCurso = null,
        ?string $termoClasse = null,
        int $limite = 8,
    ): array {
        if ($user->hasRole('aluno')) {
            return [];
        }

        $anoId = Configuracao::getAnoLectivoActualId();
        $trimestre = $trimestre ?? (int) Configuracao::get('trimestre_actual', 1);
        $notaMinima = (float) Configuracao::get('nota_minima_aprovacao', 10);

        $turmasIds = $this->access->getTurmasIdsAcessiveis($user, $anoId);
        $turma = $termoTurma ? $this->resolverTurma($user, $termoTurma, $anoId) : null;
        $curso = $termoCurso ? $this->resolverCurso($user, $termoCurso, $anoId) : null;
        $classe = $termoClasse ? $this->resolverClasse($termoClasse) : null;

        $turmas = Turma::query()
            ->whereIn('id', $turmasIds)
            ->where('activo', true)
            ->when($turma, fn ($q) => $q->where('id', $turma->id))
            ->when($curso, fn ($q) => $q->where('curso_id', $curso->id))
            ->when($classe, fn ($q) => $q->where('classe_id', $classe->id))
            ->limit(6)
            ->get();

        $porDisciplina = [];

        foreach ($turmas as $t) {
            try {
                $pauta = $this->pautaService->gerarPautaTrimestralCompleta(
                    $t->id,
                    $trimestre,
                    Configuracao::getAnoLectivoActual(),
                );
                $regime = $this->pautaService->regimeDaTurma($t);
            } catch (\Throwable) {
                continue;
            }

            $nomesDisc = collect($pauta['disciplinas'] ?? [])
                ->keyBy('id')
                ->map(fn ($d) => $d->nome ?? $d->codigo ?? null);

            foreach ($pauta['alunos'] ?? [] as $aluno) {
                if ($aluno['desistente'] ?? false) {
                    continue;
                }

                foreach ($aluno['disciplinas'] ?? [] as $discId => $disc) {
                    $nomeDisc = $nomesDisc[$discId] ?? null;
                    if (! $nomeDisc) {
                        continue;
                    }

                    $nota = RelatorioAcademicoService::notaRankingDisciplina($disc, $trimestre, $regime);
                    if ($nota === null || $nota < $notaMinima) {
                        continue;
                    }

                    $chave = $nomeDisc;
                    $entrada = [
                        'disciplina' => $nomeDisc,
                        'aluno' => $aluno['aluno']->nome ?? '—',
                        'turma' => $t->codigo ?? $t->nome,
                        'nota' => round($nota, 1),
                    ];

                    if (! isset($porDisciplina[$chave]) || $nota > ($porDisciplina[$chave]['nota_raw'] ?? 0)) {
                        $entrada['nota_raw'] = $nota;
                        $porDisciplina[$chave] = $entrada;
                    }
                }
            }
        }

        return collect($porDisciplina)
            ->sortByDesc('nota_raw')
            ->take($limite)
            ->map(fn ($e) => collect($e)->except('nota_raw')->all())
            ->values()
            ->all();
    }

    public function extrairTermoCurso(string $mensagem): ?string
    {
        if (preg_match('/\bcurso\s+(?:de\s+)?(.+?)(?:\s+na\s+\d{1,2}[ªa]?|\s+da\s+\d{1,2}[ªa]?|\s+no\s+\d{1,2}[ªa]?|\s*,|\s*$)/ui', $mensagem, $m)) {
            return $this->limparTermoCurso(trim($m[1]));
        }

        if (preg_match('/\b(?:no|do|em)\s+curso\s+(?:de\s+)?(.+?)(?:\s+na\s+\d|\s*$)/ui', $mensagem, $m)) {
            return $this->limparTermoCurso(trim($m[1]));
        }

        return null;
    }

    protected function limparTermoCurso(string $termo): string
    {
        $termo = preg_replace('/\s+na\s+\d.*$/ui', '', $termo) ?? $termo;
        $termo = preg_replace('/\s+da\s+\d.*$/ui', '', $termo) ?? $termo;

        return trim($termo);
    }

    /**
     * Melhores alunos por turma (ranking oficial da pauta).
     *
     * @return array<string, mixed>
     */
    public function rankingMelhores(
        User $user,
        ?int $trimestre = null,
        ?string $termoTurma = null,
        ?string $termoCurso = null,
        int $top = 5,
    ): array {
        if ($user->hasRole('aluno')) {
            return [];
        }

        $anoId = Configuracao::getAnoLectivoActualId();
        $ano = Configuracao::getAnoLectivoActual();
        $trimestre = $trimestre ?? (int) Configuracao::get('trimestre_actual', 1);
        $notaMinima = (float) Configuracao::get('nota_minima_aprovacao', 10);

        $turma = $termoTurma ? $this->resolverTurma($user, $termoTurma, $anoId) : null;
        $curso = $termoCurso ? $this->resolverCurso($user, $termoCurso, $anoId) : null;

        $relatorio = $this->relatorioAcademico->getRelatorio(
            $anoId,
            $ano,
            $trimestre,
            $curso?->id,
            $turma?->id,
        );

        $melhores = collect($relatorio['melhores_por_turma'] ?? [])
            ->map(function (array $item) use ($top) {
                /** @var Turma $turmaModel */
                $turmaModel = $item['turma'];

                return [
                    'turma' => $turmaModel->codigo ?? $turmaModel->nome,
                    'curso' => $turmaModel->curso?->nome,
                    'classe' => $turmaModel->classe?->codigo,
                    'metrica' => $item['tipo_media'] ?? RelatorioAcademicoService::metricaRankingLabel(1),
                    'total_alunos_turma' => $item['total_elegiveis'] ?? 0,
                    'melhores' => collect($item['ranking'] ?? [])
                        ->take($top)
                        ->map(fn (array $r) => [
                            'nome' => $r['aluno']->nome ?? '—',
                            'numero_processo' => $r['aluno']->numero_processo ?? null,
                            'media' => $r['media'],
                            'media_precisa' => $r['media_precisa'],
                            'disciplinas_avaliadas' => $r['total_disc'],
                            'resultado_pauta' => $r['resultado'] ?? '',
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->filter(fn (array $t) => $t['melhores'] !== [])
            ->values()
            ->all();

        return [
            'trimestre' => $trimestre,
            'metrica_ranking' => $relatorio['metrica_ranking'] ?? RelatorioAcademicoService::metricaRankingLabel($trimestre),
            'nota_minima' => $notaMinima,
            'melhores_por_turma' => $melhores,
            'instrucao' => 'Ranking baseado na pauta oficial: T1→MT, T2→MFT2/MT, T3/anual→CA. '
                .'Só inclui alunos sem disciplinas negativas no período. '
                .'Para o melhor aluno agregado (classe/escola), use melhor_desempenho_geral.',
        ];
    }

    /**
     * Melhor desempenho agregado — todas as turmas de uma classe, curso ou escola (no âmbito).
     *
     * @return array<string, mixed>
     */
    public function rankingMelhorGeral(
        User $user,
        ?int $trimestre = null,
        ?string $termoClasse = null,
        ?string $termoCurso = null,
        int $top = 10,
    ): array {
        if ($user->hasRole('aluno')) {
            return [];
        }

        $anoId = Configuracao::getAnoLectivoActualId();
        $trimestre = $trimestre ?? (int) Configuracao::get('trimestre_actual', 1);
        $notaMinima = (float) Configuracao::get('nota_minima_aprovacao', 10);

        $turmasIds = $this->access->getTurmasIdsAcessiveis($user, $anoId);
        $classe = $termoClasse ? $this->resolverClasse($termoClasse) : null;
        $curso = $termoCurso ? $this->resolverCurso($user, $termoCurso, $anoId) : null;

        $turmas = Turma::query()
            ->with(['curso', 'classe'])
            ->whereIn('id', $turmasIds)
            ->where('activo', true)
            ->when($classe, fn ($q) => $q->where('classe_id', $classe->id))
            ->when($curso, fn ($q) => $q->where('curso_id', $curso->id))
            ->get();

        if ($turmas->isEmpty()) {
            return [];
        }

        $metrica = RelatorioAcademicoService::metricaRankingLabel($trimestre);
        $todos = [];

        foreach ($turmas as $turma) {
            foreach ($this->rankingElegiveisTurma($turma, $trimestre) as $entrada) {
                $todos[] = array_merge($entrada, [
                    'turma' => $turma->codigo ?? $turma->nome,
                    'curso' => $turma->curso?->nome,
                    'classe' => $turma->classe?->codigo,
                ]);
            }
        }

        $ordenado = collect($todos)
            ->sortBy([
                ['media_precisa', 'desc'],
                fn ($a, $b) => strcasecmp($a['nome'], $b['nome']),
            ])
            ->values();

        $topLista = $ordenado->take($top)->values()->all();

        return [
            'trimestre' => $trimestre,
            'metrica' => $metrica,
            'nota_minima' => $notaMinima,
            'filtros' => array_filter([
                'classe' => $classe?->codigo ?? $termoClasse,
                'curso' => $curso?->nome,
            ]),
            'total_turmas' => $turmas->count(),
            'total_alunos_elegiveis' => $ordenado->count(),
            'melhor_geral' => $topLista[0] ?? null,
            'top_geral' => $topLista,
            'instrucao' => 'Responda «melhor desempenho no geral» com melhor_geral (nome, turma, curso, média). '
                .'Para listas («N melhores»), apresente top_geral completo. '
                .'Ranking unificado de todas as turmas do filtro, só alunos sem disciplinas negativas.',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function rankingElegiveisTurma(Turma $turma, int $trimestre): array
    {
        return collect($this->rankingAlunosTurmaInterno($turma, $trimestre, true))
            ->filter(fn ($e) => $e['media_precisa'] !== null && $e['disciplinas_avaliadas'] > 0 && $e['negativas'] === 0)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function rankingAlunosTurmaInterno(Turma $turma, int $trimestre, bool $somenteElegiveis): array
    {
        $ano = Configuracao::getAnoLectivoActual();
        $notaMinima = (float) Configuracao::get('nota_minima_aprovacao', 10);

        try {
            $pauta = $this->pautaService->gerarPautaTrimestralCompleta($turma->id, $trimestre, $ano);
            $regime = $this->pautaService->regimeDaTurma($turma);
        } catch (\Throwable) {
            return [];
        }

        return collect($pauta['alunos'] ?? [])
            ->filter(fn (array $a) => ! ($a['desistente'] ?? false))
            ->map(function (array $aluno) use ($trimestre, $regime, $notaMinima) {
                $notasDisc = collect($aluno['disciplinas'] ?? [])
                    ->map(fn (array $d) => RelatorioAcademicoService::notaRankingDisciplina($d, $trimestre, $regime))
                    ->filter(fn ($v) => $v !== null);

                $mediaPrecisa = $notasDisc->isNotEmpty() ? (float) $notasDisc->avg() : null;
                $negativas = $notasDisc->filter(fn ($v) => $v < $notaMinima)->count();

                return [
                    'nome' => $aluno['aluno']->nome ?? '—',
                    'numero_processo' => $aluno['aluno']->numero_processo ?? null,
                    'media' => $mediaPrecisa !== null ? (int) round($mediaPrecisa) : null,
                    'media_precisa' => $mediaPrecisa !== null ? round($mediaPrecisa, 4) : null,
                    'negativas' => $negativas,
                    'disciplinas_avaliadas' => $notasDisc->count(),
                    'resultado_pauta' => $aluno['resultado'] ?? '',
                ];
            })
            ->when($somenteElegiveis, fn ($c) => $c->filter(
                fn ($e) => $e['media_precisa'] !== null && $e['disciplinas_avaliadas'] > 0 && $e['negativas'] === 0
            ))
            ->values()
            ->all();
    }

    /**
     * Comparação de desempenho entre alunos de uma turma.
     *
     * @return array<string, mixed>|null
     */
    public function comparacaoAlunosTurma(User $user, Turma $turma, ?int $trimestre = null): ?array
    {
        if ($user->hasRole('aluno') || ! $this->access->canViewTurma($user, $turma)) {
            return null;
        }

        $ano = Configuracao::getAnoLectivoActual();
        $trimestre = $trimestre ?? (int) Configuracao::get('trimestre_actual', 1);
        $notaMinima = (float) Configuracao::get('nota_minima_aprovacao', 10);

        try {
            $pauta = $this->pautaService->gerarPautaTrimestralCompleta($turma->id, $trimestre, $ano);
            $regime = $this->pautaService->regimeDaTurma($turma);
        } catch (\Throwable) {
            return null;
        }

        $metrica = RelatorioAcademicoService::metricaRankingLabel($trimestre);

        $ranking = collect($this->rankingAlunosTurmaInterno($turma, $trimestre, false))
            ->filter(fn ($e) => $e['media_precisa'] !== null)
            ->map(fn (array $e) => [
                'nome' => $e['nome'],
                'numero_processo' => $e['numero_processo'],
                'media' => round($e['media_precisa'], 1),
                'negativas' => $e['negativas'],
                'disciplinas_avaliadas' => $e['disciplinas_avaliadas'],
                'resultado' => $e['resultado_pauta'] ?? '',
            ])
            ->sortBy([
                ['negativas', 'asc'],
                ['media', 'desc'],
                fn ($a, $b) => strcasecmp($a['nome'], $b['nome']),
            ])
            ->values()
            ->take(30)
            ->values()
            ->all();

        return [
            'turma' => $turma->codigo ?? $turma->nome,
            'curso' => $turma->curso?->nome,
            'classe' => $turma->classe?->codigo,
            'trimestre' => $trimestre,
            'metrica' => $metrica,
            'total_alunos' => count($ranking),
            'ranking_comparativo' => $ranking,
            'instrucao' => 'Use para comparar alunos da mesma turma. Posição = ordem na lista (1.º = melhor média, sem negativas primeiro).',
        ];
    }

    /**
     * Alunos com notas baixas (MT/CA abaixo do mínimo).
     *
     * @return array<string, mixed>
     */
    public function alunosComNotasBaixas(
        User $user,
        ?int $trimestre = null,
        ?string $termoTurma = null,
    ): array {
        if ($user->hasRole('aluno')) {
            return [];
        }

        $anoId = Configuracao::getAnoLectivoActualId();
        $ano = Configuracao::getAnoLectivoActual();
        $trimestre = $trimestre ?? (int) Configuracao::get('trimestre_actual', 1);
        $notaMinima = (float) Configuracao::get('nota_minima_aprovacao', 10);

        $turmasIds = $this->access->getTurmasIdsAcessiveis($user, $anoId);
        $turma = $termoTurma ? $this->resolverTurma($user, $termoTurma, $anoId) : null;

        if ($turma) {
            $turmasIds = [$turma->id];
        }

        $notasBaixas = Nota::query()
            ->with(['aluno', 'disciplina', 'turma'])
            ->whereIn('turma_id', $turmasIds)
            ->where('ano_lectivo_id', $anoId)
            ->where('trimestre', $trimestre)
            ->where('tipo', Nota::TIPO_MT)
            ->where('valor', '<', $notaMinima)
            ->whereNull('archived_at')
            ->orderBy('valor')
            ->limit(40)
            ->get()
            ->map(fn (Nota $n) => [
                'aluno' => $n->aluno?->nome,
                'numero_processo' => $n->aluno?->numero_processo,
                'turma' => $n->turma?->codigo ?? $n->turma?->nome,
                'disciplina' => $n->disciplina?->nome,
                'mt' => (float) $n->valor,
                'trimestre' => $trimestre,
            ])
            ->values()
            ->all();

        $resumoPorTurma = collect($notasBaixas)
            ->groupBy('turma')
            ->map(fn (Collection $g, $turmaNome) => [
                'turma' => $turmaNome,
                'total_registos_baixos' => $g->count(),
                'alunos_distintos' => $g->pluck('numero_processo')->unique()->count(),
            ])
            ->values()
            ->all();

        return [
            'trimestre' => $trimestre,
            'nota_minima' => $notaMinima,
            'ano_lectivo' => $ano,
            'detalhes' => $notasBaixas,
            'resumo_por_turma' => $resumoPorTurma,
            'instrucao' => '«Baixas» = MT abaixo da nota mínima no trimestre. '
                .'Para exame de melhoria (notas 10–16 aprovadas), use dados_melhorias.',
        ];
    }

    /**
     * Resumo de elegibilidade ao exame de melhoria de nota.
     *
     * @return array<string, mixed>
     */
    public function resumoMelhorias(User $user, ?string $termoTurma = null): array
    {
        if ($user->hasRole('aluno')) {
            return [];
        }

        $anoId = Configuracao::getAnoLectivoActualId();
        $turmasIds = $this->access->getTurmasIdsAcessiveis($user, $anoId);

        $turma = $termoTurma ? $this->resolverTurma($user, $termoTurma, $anoId) : null;

        $turmas = Turma::query()
            ->with('curso')
            ->whereIn('id', $turma ? [$turma->id] : $turmasIds)
            ->where('activo', true)
            ->limit($turma ? 1 : 5)
            ->get();

        $cfg = $this->melhoriaService->configuracoes();
        $resumos = [];

        foreach ($turmas as $t) {
            $elegibilidade = $this->melhoriaService->calcularElegibilidade($t);
            $elegiveis = $elegibilidade->filter(fn ($e) => ($e['elegivel'] ?? false) === true);

            $resumos[] = [
                'turma' => $t->codigo ?? $t->nome,
                'curso' => $t->curso?->nome,
                'total_alunos_elegiveis' => $elegiveis->count(),
                'amostra' => $elegiveis->take(8)->map(fn ($e) => [
                    'aluno' => $e['nome'] ?? '—',
                    'disciplinas_elegiveis' => count($e['disciplinas_elegiveis'] ?? []),
                ])->values()->all(),
            ];
        }

        return [
            'configuracao' => $cfg,
            'por_turma' => $resumos,
            'instrucao' => 'Exame de Melhoria (Art.º 34.º DE 424/25): alunos APROVADOS (CFD≥10) '
                .'com CFD na faixa configurada que podem tentar melhorar a nota.',
        ];
    }

    /**
     * Professores agrupados por turma com disciplinas.
     *
     * @return array<int, array<string, mixed>>
     */
    public function professoresPorTurmas(User $user, ?string $termoTurma = null): array
    {
        $anoId = Configuracao::getAnoLectivoActualId();
        $turmasIds = $this->access->getTurmasIdsAcessiveis($user, $anoId);

        $turma = $termoTurma ? $this->resolverTurma($user, $termoTurma, $anoId) : null;
        if ($turma) {
            $turmasIds = [$turma->id];
        }

        return Turma::query()
            ->with(['curso', 'classe'])
            ->whereIn('id', $turmasIds)
            ->where('activo', true)
            ->orderBy('nome')
            ->limit($termoTurma ? 1 : 10)
            ->get()
            ->map(function (Turma $t) use ($user) {
                $resumo = $this->dataService->getResumoTurma($user, $t);

                return [
                    'turma' => $t->codigo ?? $t->nome,
                    'curso' => $t->curso?->nome,
                    'classe' => $t->classe?->codigo,
                    'director_turma' => $resumo['director_turma'] ?? null,
                    'professores' => $resumo['professores'] ?? [],
                ];
            })
            ->values()
            ->all();
    }

    public function extrairLimiteTop(string $mensagem): int
    {
        $msg = mb_strtolower($mensagem);

        if (preg_match('/\btop\s+(\d{1,2})\b/u', $msg, $m)) {
            return min(50, max(1, (int) $m[1]));
        }

        if (preg_match('/\blista\s+(de\s+)?(\d{1,2})\s+melhores\b/u', $msg, $m)) {
            return min(50, max(1, (int) $m[2]));
        }

        if (preg_match('/\blista\s+(dos|das)\s+(\d{1,2})\b/u', $msg, $m)) {
            return min(50, max(1, (int) $m[2]));
        }

        if (preg_match('/\b(\d{1,2})\s+melhores\b/u', $msg, $m)) {
            return min(50, max(1, (int) $m[1]));
        }

        return 10;
    }

    public function extrairTrimestre(string $mensagem): ?int
    {
        $msg = mb_strtolower($mensagem);

        if (preg_match('/\b(1|2|3)[º°]?\s*(trimestre|trim\.?)\b/u', $msg, $m)) {
            return (int) $m[1];
        }

        if (preg_match('/\bt\s*([123])\b/u', $msg, $m)) {
            return (int) $m[1];
        }

        if (preg_match('/\bprimeiro\s+trimestre\b/u', $msg)) {
            return 1;
        }
        if (preg_match('/\bsegundo\s+trimestre\b/u', $msg)) {
            return 2;
        }
        if (preg_match('/\bterceiro\s+trimestre\b/u', $msg)) {
            return 3;
        }

        return null;
    }

    protected function resolverTurma(User $user, string $termo, int $anoId): ?Turma
    {
        $termo = trim($termo);
        $turmasIds = $this->access->getTurmasIdsAcessiveis($user, $anoId);

        return Turma::query()
            ->whereIn('id', $turmasIds)
            ->where('activo', true)
            ->where(function ($q) use ($termo) {
                $q->where('codigo', 'like', "%{$termo}%")
                    ->orWhere('nome', 'like', "%{$termo}%");
            })
            ->first();
    }

    protected function resolverCurso(User $user, string $termo, int $anoId): ?Curso
    {
        $termo = $this->limparTermoCurso(trim($termo));
        if ($termo === '') {
            return null;
        }

        $turmasIds = $this->access->getTurmasIdsAcessiveis($user, $anoId);

        $cursoIds = Turma::query()
            ->whereIn('id', $turmasIds)
            ->pluck('curso_id')
            ->unique();

        $curso = Curso::query()
            ->whereIn('id', $cursoIds)
            ->where(function ($q) use ($termo) {
                $q->where('nome', 'like', "%{$termo}%")
                    ->orWhere('codigo', 'like', "%{$termo}%");
            })
            ->first();

        if ($curso) {
            return $curso;
        }

        // Fallback: match parcial por palavras-chave (ex.: «gestão empresas»)
        $palavras = array_filter(explode(' ', mb_strtolower($termo)), fn ($p) => mb_strlen($p) >= 4);

        if ($palavras === []) {
            return null;
        }

        return Curso::query()
            ->whereIn('id', $cursoIds)
            ->get()
            ->sortByDesc(function (Curso $c) use ($palavras) {
                $nome = mb_strtolower($c->nome ?? '');
                $score = 0;
                foreach ($palavras as $p) {
                    if (str_contains($nome, $p)) {
                        $score++;
                    }
                }

                return $score;
            })
            ->first(fn (Curso $c) => collect($palavras)->every(
                fn ($p) => str_contains(mb_strtolower($c->nome ?? ''), $p)
            ));
    }

    protected function resolverClasse(string $termo): ?Classe
    {
        $termo = trim($termo);

        return Classe::query()
            ->where('activo', true)
            ->where(function ($q) use ($termo) {
                $q->where('codigo', 'like', "%{$termo}%")
                    ->orWhere('nome', 'like', "%{$termo}%");
            })
            ->first();
    }
}
