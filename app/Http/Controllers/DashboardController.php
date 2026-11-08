<?php

namespace App\Http\Controllers;

use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\CoordenadorCurso;
use App\Models\Configuracao;
use App\Models\Curso;
use App\Models\DirectorTurma;
use App\Models\Disciplina;
use App\Models\Nota;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** TTL do cache dos dashboards: 10 minutos */
    private const CACHE_TTL = 600;

    /**
     * Gera a chave de cache para o dashboard de um utilizador.
     * Inclui o ano lectivo para invalidação automática quando o ano muda.
     */
    private function cacheKey(string $tipo, int $userId, ?int $anoLectivoId): string
    {
        return "dashboard.{$tipo}.{$userId}." . ($anoLectivoId ?? 'null');
    }

    /**
     * Invalida o cache do dashboard de um utilizador específico.
     * Chamado pelo listener RecalcularMediasAposAlteracaoNota.
     */
    public static function invalidarCacheProfessor(int $userId, ?int $anoLectivoId): void
    {
        Cache::forget("dashboard.professor.{$userId}." . ($anoLectivoId ?? 'null'));
    }

    public static function invalidarCacheDirector(int $userId, ?int $anoLectivoId): void
    {
        Cache::forget("dashboard.director.{$userId}." . ($anoLectivoId ?? 'null'));
    }

    public static function invalidarCacheCoordenador(int $userId, ?int $anoLectivoId): void
    {
        Cache::forget("dashboard.coordenador.{$userId}." . ($anoLectivoId ?? 'null'));
    }

    public static function invalidarCacheAdmin(?int $anoLectivoId): void
    {
        Cache::forget('dashboard.admin.'.($anoLectivoId ?? 'null'));
    }

    /**
     * Dashboard principal - redireciona conforme cargo
     */
    public function index(): View|RedirectResponse
    {
        // Instalação hub = painel central, não dashboard académico da escola
        if (config('control.mode') === 'hub') {
            return redirect()->route('control.hub.index');
        }

        $user = auth()->user();

        if ($user->hasRole('aluno')) {
            return redirect()->route('portal.aluno.dashboard');
        }

        if ($user->hasRole('encarregado') && $user->encarregado_id) {
            return redirect()->route('portal.encarregado.dashboard');
        }

        // Admin Full ve o dashboard administrativo
        if ($user->hasRole('admin_full')) {
            return $this->dashboardAdmin($user);
        }

        // Gestor académico e directora geral — visão institucional completa
        if ($user->temControloAcademicoInstitucional()) {
            return $this->dashboardAdmin($user);
        }

        // Subdiretor Pedagógico ve o dashboard administrativo (READ-ONLY)
        if ($user->hasRole('subdiretor_pedagogico')) {
            return $this->dashboardAdmin($user);
        }

        // Coordenador de Curso
        if ($user->hasRole('coordenador_curso')) {
            $coordenacao = CoordenadorCurso::where('user_id', $user->id)
                ->where('activo', true)
                ->first();

            if ($coordenacao) {
                return $this->dashboardCoordenador($user);
            }
        }

        // Director de Turma
        if ($user->hasRole('director_turma')) {
            $direccao = DirectorTurma::where('user_id', $user->id)
                ->where('activo', true)
                ->first();

            if ($direccao) {
                return $this->dashboardDirector($user);
            }
        }

        return $this->dashboardProfessor($user);
    }

    /**
     * Dashboard do Professor
     */
    protected function dashboardProfessor(User $user): View
    {
        $anoLectivo = \App\Models\Configuracao::getAnoLectivoActual();
        $anoLectivoId = \App\Models\AnoLectivo::getIdByNome($anoLectivo);

        // Obter associacoes activas
        $associacoes = ProfessorDisciplinaTurma::where('user_id', $user->id)
            ->where('activo', true)
            ->with(['turma.curso', 'disciplina'])
            ->get();

        // Estatisticas por disciplina/turma - OTIMIZADO (sem N+1)
        // Buscar totais de alunos de uma vez
        $turmaIds = $associacoes->pluck('turma_id')->unique();
        $alunosPorTurma = Aluno::whereIn('turma_id', $turmaIds)
            ->where('activo', true)
            ->select('turma_id', \DB::raw('count(*) as total'))
            ->groupBy('turma_id')
            ->pluck('total', 'turma_id');

        // Buscar notas lançadas de uma vez
        $notasPorAssociacao = Nota::where('professor_id', $user->id)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->whereIn('turma_id', $turmaIds)
            ->whereIn('disciplina_id', $associacoes->pluck('disciplina_id')->unique())
            ->select('turma_id', 'disciplina_id', \DB::raw('COUNT(DISTINCT aluno_id) as total'))
            ->groupBy('turma_id', 'disciplina_id')
            ->get()
            ->keyBy(fn($item) => $item->turma_id . '_' . $item->disciplina_id);

        $estatisticas = [];
        foreach ($associacoes as $assoc) {
            $key = $assoc->turma_id . '_' . $assoc->disciplina_id;

            $estatisticas[] = [
                'turma' => $assoc->turma,
                'disciplina' => $assoc->disciplina,
                'total_alunos' => $alunosPorTurma->get($assoc->turma_id, 0),
                'notas_lancadas' => $notasPorAssociacao->get($key)?->total ?? 0,
            ];
        }

        // GRÁFICO 1: Distribuição de Notas (Positivas vs Negativas)
        $chartDistribuicaoNotas = [];
        $turmaIds = $associacoes->pluck('turma_id');
        $disciplinaIds = $associacoes->pluck('disciplina_id');

        if ($turmaIds->isNotEmpty() && $disciplinaIds->isNotEmpty()) {
            $notasAgrupadas = Nota::whereIn('turma_id', $turmaIds)
                ->whereIn('disciplina_id', $disciplinaIds)
                ->where('professor_id', $user->id)
                ->where('ano_lectivo_id', $anoLectivoId)
                ->where('tipo', Nota::TIPO_MT)
                ->select(
                    'turma_id',
                    'disciplina_id',
                    \DB::raw('SUM(CASE WHEN valor >= 10 THEN 1 ELSE 0 END) as positivas'),
                    \DB::raw('SUM(CASE WHEN valor < 10 THEN 1 ELSE 0 END) as negativas')
                )
                ->groupBy('turma_id', 'disciplina_id')
                ->get()
                ->keyBy(fn($item) => $item->turma_id . '_' . $item->disciplina_id);

            foreach ($associacoes as $assoc) {
                $key = $assoc->turma_id . '_' . $assoc->disciplina_id;
                $dados = $notasAgrupadas->get($key);

                if ($dados && ($dados->positivas > 0 || $dados->negativas > 0)) {
                    $chartDistribuicaoNotas[] = [
                        'label' => $assoc->disciplina->nome . ' - ' . $assoc->turma->nome,
                        'positivas' => $dados->positivas,
                        'negativas' => $dados->negativas,
                    ];
                }
            }
        }

        // GRÁFICO 2: Progresso de Lançamento - OTIMIZADO (reutiliza dados já carregados)
        $totalEsperado = $alunosPorTurma->sum();
        $totalLancado = $notasPorAssociacao->sum('total');

        $chartProgresso = [
            'labels' => ['Lançadas', 'Pendentes'],
            'data' => [$totalLancado, max(0, $totalEsperado - $totalLancado)],
        ];

        // GRÁFICO 3: Média por Turma/Disciplina - OTIMIZADO
        $mediasPorAssociacao = Nota::where('professor_id', $user->id)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->where('tipo', Nota::TIPO_MT)
            ->whereIn('turma_id', $turmaIds)
            ->whereIn('disciplina_id', $associacoes->pluck('disciplina_id')->unique())
            ->select('turma_id', 'disciplina_id', \DB::raw('AVG(valor) as media'))
            ->groupBy('turma_id', 'disciplina_id')
            ->get()
            ->keyBy(fn($item) => $item->turma_id . '_' . $item->disciplina_id);

        $chartMediasPorTurma = [];
        foreach ($associacoes as $assoc) {
            $key = $assoc->turma_id . '_' . $assoc->disciplina_id;
            $mediaItem = $mediasPorAssociacao->get($key);

            if ($mediaItem && $mediaItem->media !== null) {
                $chartMediasPorTurma[] = [
                    'label' => $assoc->turma->nome . ' - ' . $assoc->disciplina->nome,
                    'media' => round($mediaItem->media, 2),
                ];
            }
        }

        // Notas recentes lancadas pelo professor
        $notasRecentes = Nota::where('professor_id', $user->id)
            ->with(['aluno', 'disciplina', 'turma'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $saude = app(\App\Services\SaudeTurmasService::class)->indicadores($user, $anoLectivo);

        try {
            app(\App\Services\ProactiveNotificacaoDispatcher::class)->aoVisitarArea($user);
        } catch (\Throwable) {
            // notificações não bloqueiam o dashboard
        }

        return view('dashboard.professor', compact(
            'associacoes',
            'estatisticas',
            'notasRecentes',
            'anoLectivo',
            'chartDistribuicaoNotas',
            'chartProgresso',
            'chartMediasPorTurma',
            'saude',
        ));
    }

    /**
     * Dashboard do Director de Turma
     */
    protected function dashboardDirector(User $user): View
    {
        $anoLectivo = Configuracao::getAnoLectivoActual();
        $anoLectivoId = AnoLectivo::getIdByNome($anoLectivo);
        $cacheKey = $this->cacheKey('director', $user->id, $anoLectivoId);

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user, $anoLectivo, $anoLectivoId) {
            return $this->buildDirectorDashboardData($user, $anoLectivo, $anoLectivoId);
        });

        $data['saude'] = app(\App\Services\SaudeTurmasService::class)->indicadores($user, $anoLectivo);
        $data['briefingFamilias'] = app(\App\Services\DirectorTurmaBriefingService::class)->briefing($user);

        try {
            app(\App\Services\ProactiveNotificacaoDispatcher::class)->aoVisitarArea($user);
        } catch (\Throwable) {
        }

        return view('dashboard.director', $data);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildDirectorDashboardData(User $user, string $anoLectivo, ?int $anoLectivoId): array
    {
        // Obter turmas que dirige no ano lectivo actual
        $direccoes = DirectorTurma::where('user_id', $user->id)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->where('activo', true)
            ->with(['turma.curso.disciplinas', 'turma.alunos' => function ($q) {
                $q->where('activo', true);
            }])
            ->get();

        $turmas = $direccoes->pluck('turma')->filter(); // Filter para remover nulls

        // Buscar notas finais de uma vez - OTIMIZADO
        $turmaIds = $turmas->pluck('id');
        $notasFinaisPorTurma = Nota::whereIn('turma_id', $turmaIds)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->whereIn('tipo', [Nota::TIPO_CFD, Nota::TIPO_CA10, Nota::TIPO_CA11, Nota::TIPO_CA12, Nota::TIPO_CF])
            ->select('turma_id', \DB::raw('COUNT(DISTINCT aluno_id) as total'))
            ->groupBy('turma_id')
            ->pluck('total', 'turma_id');

        // Estatisticas por turma
        $estatisticasTurmas = [];
        foreach ($turmas as $turma) {
            $totalAlunos = $turma->alunos->count();

            // Disciplinas do curso (já carregadas via eager loading)
            $totalDisciplinas = $turma->curso->disciplinas->count();

            $estatisticasTurmas[] = [
                'turma' => $turma,
                'total_alunos' => $totalAlunos,
                'total_disciplinas' => $totalDisciplinas,
                'alunos_com_final' => $notasFinaisPorTurma->get($turma->id, 0),
            ];
        }

        // Alunos com situacao critica (muitas negativas) - OTIMIZADO
        $notasNegativasPorAluno = Nota::whereIn('turma_id', $turmaIds)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->where('tipo', Nota::TIPO_MT)
            ->where('valor', '<', 10)
            ->with('aluno.turma')
            ->get()
            ->groupBy('aluno_id');

        $alunosCriticos = [];
        foreach ($notasNegativasPorAluno as $alunoId => $notas) {
            if ($notas->count() >= 3) { // 3 ou mais negativas
                $alunosCriticos[] = [
                    'aluno' => $notas->first()->aluno,
                    'turma' => $notas->first()->aluno->turma,
                    'negativas' => $notas->count(),
                ];
            }
        }

        // GRÁFICO 1: Desempenho por Disciplina — agregado numa query
        $chartDesempenhoDisciplinas = [];
        $turmaIds = $turmas->pluck('id');

        if ($turmaIds->isNotEmpty()) {
            $chartDesempenhoDisciplinas = Nota::query()
                ->join('disciplinas', 'disciplinas.id', '=', 'notas.disciplina_id')
                ->whereIn('notas.turma_id', $turmaIds)
                ->where('notas.ano_lectivo_id', $anoLectivoId)
                ->where('notas.tipo', Nota::TIPO_MT)
                ->whereNull('notas.archived_at')
                ->select('disciplinas.nome', \DB::raw('AVG(notas.valor) as media'))
                ->groupBy('disciplinas.id', 'disciplinas.nome')
                ->get()
                ->map(fn ($row) => [
                    'nome' => $row->nome,
                    'media' => round((float) $row->media, 2),
                ])
                ->sortBy('media')
                ->values()
                ->all();
        }

        // GRÁFICO 2: Aprovados vs Reprovados
        $aprovados = 0;
        $reprovados = 0;

        if ($turmaIds->isNotEmpty()) {
            $notasFinais = Nota::whereIn('turma_id', $turmaIds)
                ->where('ano_lectivo_id', $anoLectivoId)
                ->whereIn('tipo', [Nota::TIPO_CFD, Nota::TIPO_CA10, Nota::TIPO_CA11, Nota::TIPO_CA12, Nota::TIPO_CF])
                ->get();

            $aprovados = $notasFinais->where('valor', '>=', 10)->count();
            $reprovados = $notasFinais->where('valor', '<', 10)->count();
        }

        $chartAprovacao = [
            'labels' => ['Aprovados', 'Reprovados'],
            'data' => [$aprovados, $reprovados],
        ];

        // GRÁFICO 3: Tendência de Desempenho por Trimestre — agregado
        $chartTendencia = [
            'labels' => ['1º Trimestre', '2º Trimestre', '3º Trimestre'],
            'datasets' => [],
        ];

        if ($turmaIds->isNotEmpty()) {
            $mediasPorTurmaTrimestre = Nota::query()
                ->whereIn('turma_id', $turmaIds)
                ->where('ano_lectivo_id', $anoLectivoId)
                ->where('tipo', Nota::TIPO_MT)
                ->whereBetween('trimestre', [1, 3])
                ->whereNull('archived_at')
                ->select('turma_id', 'trimestre', \DB::raw('AVG(valor) as media'))
                ->groupBy('turma_id', 'trimestre')
                ->get()
                ->groupBy('turma_id');

            foreach ($turmas as $turma) {
                $rows = $mediasPorTurmaTrimestre->get($turma->id, collect());
                $mediasPorTrimestre = [];
                for ($trim = 1; $trim <= 3; $trim++) {
                    $media = $rows->firstWhere('trimestre', $trim)?->media;
                    $mediasPorTrimestre[] = $media !== null ? round((float) $media, 2) : null;
                }

                if (array_filter($mediasPorTrimestre, fn ($v) => $v !== null)) {
                    $chartTendencia['datasets'][] = [
                        'label' => $turma->nome,
                        'data' => $mediasPorTrimestre,
                    ];
                }
            }
        }

        return compact(
            'turmas',
            'estatisticasTurmas',
            'alunosCriticos',
            'anoLectivo',
            'chartDesempenhoDisciplinas',
            'chartAprovacao',
            'chartTendencia'
        );
    }

    /**
     * Dashboard do Coordenador de Curso
     */
    protected function dashboardCoordenador(User $user): View
    {
        $anoLectivo = \App\Models\Configuracao::getAnoLectivoActual();
        $anoLectivoId = \App\Models\AnoLectivo::getIdByNome($anoLectivo);
        $cacheKey = $this->cacheKey('coordenador', $user->id, $anoLectivoId);

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user, $anoLectivo, $anoLectivoId) {
            return $this->buildCoordenadorDashboardData($user, $anoLectivo, $anoLectivoId);
        });

        $data['saude'] = app(\App\Services\SaudeTurmasService::class)->indicadores($user, $anoLectivo);

        try {
            app(\App\Services\ProactiveNotificacaoDispatcher::class)->aoVisitarArea($user);
        } catch (\Throwable) {
        }

        return view('dashboard.coordenador', $data);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildCoordenadorDashboardData(User $user, string $anoLectivo, ?int $anoLectivoId): array
    {
        // Obter cursos que coordena
        $coordenacoes = CoordenadorCurso::where('user_id', $user->id)
            ->where('activo', true)
            ->with(['curso.turmas.alunos' => function ($q) {
                $q->where('activo', true);
            }, 'curso.disciplinas'])
            ->get();

        $cursos = $coordenacoes->pluck('curso');

        // Tipos finais e fallback (MT) — durante o ano usa MT; no fim usa CFD/CA/CF
        $tiposFinais = [Nota::TIPO_CFD, Nota::TIPO_CA10, Nota::TIPO_CA11, Nota::TIPO_CA12, Nota::TIPO_CF];

        // Estatisticas por curso
        $estatisticasCursos = [];
        foreach ($cursos as $curso) {
            $turmaIds = $curso->turmas->pluck('id');
            $totalTurmas = $curso->turmas->count();
            $totalAlunos = $curso->turmas->sum(fn($t) => $t->alunos->count());
            $totalDisciplinas = $curso->disciplinas->count();

            // Taxa de aprovacao — preferir notas finais; fallback para MT durante o ano
            $notasReferencia = $this->coordNotasReferencia($turmaIds, $anoLectivoId, $tiposFinais);

            $aprovados = $notasReferencia->where('valor', '>=', 10)->count();
            $taxaAprovacao = $notasReferencia->count() > 0
                ? round(($aprovados / $notasReferencia->count()) * 100, 1)
                : null;

            $estatisticasCursos[] = [
                'curso' => $curso,
                'total_turmas' => $totalTurmas,
                'total_alunos' => $totalAlunos,
                'total_disciplinas' => $totalDisciplinas,
                'taxa_aprovacao' => $taxaAprovacao,
                'fonte_referencia' => $notasReferencia->isNotEmpty() ? $notasReferencia->first()->fonte ?? 'final' : null,
            ];
        }

        // Media por disciplina (Top/Bottom 5)
        $mediasPorDisciplina = [];
        foreach ($cursos as $curso) {
            $turmaIds = $curso->turmas->pluck('id');
            foreach ($curso->disciplinas as $disciplina) {
                $notasRef = $this->coordNotasReferencia($turmaIds, $anoLectivoId, $tiposFinais, $disciplina->id);

                if ($notasRef->count() > 0) {
                    $mediasPorDisciplina[] = [
                        'disciplina'  => $disciplina,
                        'curso'       => $curso,
                        'media'       => round($notasRef->avg('valor'), 2),
                        'total_notas' => $notasRef->count(),
                    ];
                }
            }
        }

        // Ordenar por media (ascendente para destacar disciplinas com problemas)
        usort($mediasPorDisciplina, fn($a, $b) => $a['media'] <=> $b['media']);

        // GRÁFICO 1: Comparativo entre Turmas (Média geral) — usa MT (sempre disponível)
        $chartComparativoTurmas = [];

        foreach ($cursos as $curso) {
            foreach ($curso->turmas as $turma) {
                $mtNotas = $this->coordMtDeduplicadas([$turma->id], $anoLectivoId);

                if ($mtNotas->isNotEmpty()) {
                    $chartComparativoTurmas[] = [
                        'nome'  => $turma->nome,
                        'media' => round($mtNotas->avg('valor'), 2),
                    ];
                }
            }
        }

        // GRÁFICO 2: Taxa de Aprovação por Turma — preferir finais, fallback MT
        $chartTaxaAprovacao = [];

        foreach ($cursos as $curso) {
            foreach ($curso->turmas as $turma) {
                $notasRef = $this->coordNotasReferencia(collect([$turma->id]), $anoLectivoId, $tiposFinais);

                if ($notasRef->count() > 0) {
                    $aprovados = $notasRef->where('valor', '>=', 10)->count();
                    $taxaAprovacao = round(($aprovados / $notasRef->count()) * 100, 1);

                    $chartTaxaAprovacao[] = [
                        'nome' => $turma->nome,
                        'taxa' => $taxaAprovacao,
                    ];
                }
            }
        }

        // Ordenar ascendente (destaca turmas problemáticas)
        usort($chartTaxaAprovacao, fn($a, $b) => $a['taxa'] <=> $b['taxa']);

        // GRÁFICO 3: Distribuição de Notas (5 faixas)
        $turmasIds = collect();
        foreach ($cursos as $curso) {
            $turmasIds = $turmasIds->merge($curso->turmas->pluck('id'));
        }

        $excelente = 0;
        $muitoBom = 0;
        $bom = 0;
        $suficiente = 0;
        $insuficiente = 0;

        if ($turmasIds->isNotEmpty()) {
            $todasNotas = $this->coordMtDeduplicadas($turmasIds->all(), $anoLectivoId)->pluck('valor');

            foreach ($todasNotas as $nota) {
                if ($nota >= 18) {
                    $excelente++;
                } elseif ($nota >= 16) {
                    $muitoBom++;
                } elseif ($nota >= 14) {
                    $bom++;
                } elseif ($nota >= 10) {
                    $suficiente++;
                } else {
                    $insuficiente++;
                }
            }
        }

        $chartDistribuicaoNotas = [
            'labels' => ['Excelente (≥18)', 'Muito Bom (16-17)', 'Bom (14-15)', 'Suficiente (10-13)', 'Insuficiente (<10)'],
            'data' => [$excelente, $muitoBom, $bom, $suficiente, $insuficiente],
        ];

        // GRÁFICO 4: Top 5 e Bottom 5 Disciplinas
        $chartTopBottomDisciplinas = [];

        if (count($mediasPorDisciplina) > 0) {
            // Bottom 5 (piores)
            $bottom5 = array_slice($mediasPorDisciplina, 0, min(5, count($mediasPorDisciplina)));

            // Top 5 (melhores)
            $top5 = array_slice($mediasPorDisciplina, max(0, count($mediasPorDisciplina) - 5));
            $top5 = array_reverse($top5); // Reverter para mostrar o melhor primeiro

            // Combinar: bottom 5 + separator + top 5
            foreach ($bottom5 as $item) {
                $chartTopBottomDisciplinas[] = [
                    'nome' => $item['disciplina']->nome,
                    'media' => $item['media'],
                    'tipo' => 'bottom',
                ];
            }

            foreach ($top5 as $item) {
                $chartTopBottomDisciplinas[] = [
                    'nome' => $item['disciplina']->nome,
                    'media' => $item['media'],
                    'tipo' => 'top',
                ];
            }
        }

        // Fonte global dos gráficos analíticos (taxa por turma + top/bottom disciplinas)
        // 'final' se há QUALQUER nota final em cursos do coordenador; senão 'mt'; null se não há dados
        $todasTurmaIds = collect();
        foreach ($cursos as $c) {
            $todasTurmaIds = $todasTurmaIds->merge($c->turmas->pluck('id'));
        }
        $fonteGlobal = null;
        if ($todasTurmaIds->isNotEmpty()) {
            $temFinais = Nota::whereIn('turma_id', $todasTurmaIds->all())
                ->where('ano_lectivo_id', $anoLectivoId)
                ->whereIn('tipo', $tiposFinais)
                ->whereNull('archived_at')
                ->exists();
            $temMt = $temFinais ? false : Nota::whereIn('turma_id', $todasTurmaIds->all())
                ->where('ano_lectivo_id', $anoLectivoId)
                ->where('tipo', Nota::TIPO_MT)
                ->whereNull('archived_at')
                ->exists();
            $fonteGlobal = $temFinais ? 'final' : ($temMt ? 'mt' : null);
        }

        return [
            'cursos' => $cursos,
            'estatisticasCursos' => $estatisticasCursos,
            'mediasPorDisciplina' => $mediasPorDisciplina,
            'anoLectivo' => $anoLectivo,
            'chartComparativoTurmas' => $chartComparativoTurmas,
            'chartTaxaAprovacao' => $chartTaxaAprovacao,
            'chartDistribuicaoNotas' => $chartDistribuicaoNotas,
            'chartTopBottomDisciplinas' => $chartTopBottomDisciplinas,
            'fonteGlobal' => $fonteGlobal,
        ];
    }

    /**
     * Devolve notas de referência para cálculo de aprovação/média:
     *   - Preferir notas finais (CFD/CA10/CA11/CA12/CF) deduplicadas pós-conselho > pré
     *   - Se nenhuma final existir (durante o ano), faz fallback para MT também deduplicada
     *   - Filtra notas arquivadas
     *
     * Cada item retornado tem propriedade extra ->fonte = 'final' | 'mt'.
     */
    private function coordNotasReferencia(
        $turmaIds,
        ?int $anoLectivoId,
        array $tiposFinais,
        ?int $disciplinaId = null
    ): \Illuminate\Support\Collection {
        $turmaIdsArr = collect($turmaIds)->all();
        if (empty($turmaIdsArr) || $anoLectivoId === null) {
            return collect();
        }

        // 1) Tentar notas finais deduplicadas pré/pós-conselho
        $finais = Nota::whereIn('turma_id', $turmaIdsArr)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->whereIn('tipo', $tiposFinais)
            ->whereNull('archived_at')
            ->when($disciplinaId, fn($q) => $q->where('disciplina_id', $disciplinaId))
            ->get(['id', 'aluno_id', 'disciplina_id', 'tipo', 'valor', 'estado_conselho']);

        $finaisDedup = $finais
            ->groupBy(fn($n) => $n->aluno_id . '|' . $n->disciplina_id)
            ->map(fn($g) => $g->firstWhere('estado_conselho', Nota::ESTADO_POS_CONSELHO) ?? $g->first())
            ->values();

        if ($finaisDedup->isNotEmpty()) {
            return $finaisDedup->each(fn($n) => $n->fonte = 'final');
        }

        // 2) Fallback: MT deduplicadas (durante o ano lectivo)
        return $this->coordMtDeduplicadas($turmaIdsArr, $anoLectivoId, $disciplinaId)
            ->each(fn($n) => $n->fonte = 'mt');
    }

    /**
     * Devolve MTs deduplicadas por (aluno, disciplina, trimestre) preferindo
     * pós-conselho. Filtra archived_at.
     */
    private function coordMtDeduplicadas(
        array $turmaIds,
        ?int $anoLectivoId,
        ?int $disciplinaId = null
    ): \Illuminate\Support\Collection {
        if (empty($turmaIds) || $anoLectivoId === null) {
            return collect();
        }

        return Nota::whereIn('turma_id', $turmaIds)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->where('tipo', Nota::TIPO_MT)
            ->whereNull('archived_at')
            ->when($disciplinaId, fn($q) => $q->where('disciplina_id', $disciplinaId))
            ->get(['id', 'aluno_id', 'disciplina_id', 'trimestre', 'valor', 'estado_conselho'])
            ->groupBy(fn($n) => $n->aluno_id . '|' . $n->disciplina_id . '|' . $n->trimestre)
            ->map(fn($g) => $g->firstWhere('estado_conselho', Nota::ESTADO_POS_CONSELHO) ?? $g->first())
            ->values();
    }

    /**
     * Dashboard do Admin Full
     */
    protected function dashboardAdmin(User $user): View
    {
        $anoLectivo = \App\Models\Configuracao::getAnoLectivoActual();
        $anoLectivoId = $anoLectivo
            ? \App\Models\AnoLectivo::getIdByNome($anoLectivo)
            : null;

        $cached = Cache::remember(
            'dashboard.admin.'.($anoLectivoId ?? 'null'),
            self::CACHE_TTL,
            fn () => $this->buildAdminDashboardData($anoLectivo, $anoLectivoId)
        );

        // Dados “vivos” — fora do cache
        $contasPendentes = User::where('estado', 'pendente')->count();
        $notasHoje = Nota::whereDate('created_at', today())->count();
        $actividadeRecente = \Spatie\Activitylog\Models\Activity::with('causer')
            ->orderBy('created_at', 'desc')
            ->limit(15)
            ->get();
        $alertasSeguranca = app(\App\Services\SecurityAlertService::class)->alertasRecentes();

        $ultimosSete = collect();
        for ($i = 6; $i >= 0; $i--) {
            $data = today()->subDays($i);
            $ultimosSete->push([
                'data' => $data->format('d/m'),
                'count' => Nota::whereDate('created_at', $data)->count(),
            ]);
        }
        $chartNotasDiarias = [
            'labels' => $ultimosSete->pluck('data')->toArray(),
            'data' => $ultimosSete->pluck('count')->toArray(),
        ];

        $saude = app(\App\Services\SaudeEscolaService::class)->indicadores($anoLectivo);

        try {
            app(\App\Services\ProactiveNotificacaoDispatcher::class)->aoVisitarArea($user);
        } catch (\Throwable) {
            // notificações não bloqueiam o dashboard
        }

        return view('dashboard.admin', array_merge($cached, compact(
            'contasPendentes',
            'notasHoje',
            'actividadeRecente',
            'chartNotasDiarias',
            'saude',
            'alertasSeguranca',
        )));
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildAdminDashboardData(?string $anoLectivo, ?int $anoLectivoId): array
    {
        $totalCursos = Curso::count();
        $totalTurmas = $anoLectivoId
            ? Turma::where('ano_lectivo_id', $anoLectivoId)->count()
            : 0;
        $totalAlunos = $anoLectivoId
            ? Aluno::where('activo', true)
                ->whereHas('turma', fn ($q) => $q->where('ano_lectivo_id', $anoLectivoId))
                ->count()
            : 0;
        $totalProfessores = User::role('professor')->where('estado', 'activo')->count();
        $totalDisciplinas = Disciplina::where('activo', true)->count();

        $estatisticasCursos = Curso::withCount([
            'turmas' => fn ($q) => $anoLectivoId
                ? $q->where('ano_lectivo_id', $anoLectivoId)
                : $q->whereRaw('0 = 1'),
            'disciplinas',
        ])
            ->with(['turmas' => function ($q) use ($anoLectivoId) {
                if ($anoLectivoId) {
                    $q->where('ano_lectivo_id', $anoLectivoId);
                } else {
                    $q->whereRaw('0 = 1');
                }
                $q->withCount(['alunos' => function ($q) {
                    $q->where('activo', true);
                }]);
            }])
            ->get()
            ->map(function ($curso) {
                return [
                    'curso' => $curso,
                    'total_turmas' => $curso->turmas_count,
                    'total_disciplinas' => $curso->disciplinas_count,
                    'total_alunos' => $curso->turmas->sum('alunos_count'),
                ];
            });

        $chartAlunosCurso = [
            'labels' => $estatisticasCursos->pluck('curso.nome')->toArray(),
            'data' => $estatisticasCursos->pluck('total_alunos')->toArray(),
        ];

        $turmasComAlunos = $anoLectivoId
            ? Turma::where('ano_lectivo_id', $anoLectivoId)
                ->withCount(['alunos' => function ($q) {
                    $q->where('activo', true);
                }])
                ->with('curso')
                ->having('alunos_count', '>', 0)
                ->orderBy('alunos_count', 'desc')
                ->limit(10)
                ->get()
            : collect();

        $chartTurmas = [
            'labels' => $turmasComAlunos->map(fn ($t) => $t->nome)->toArray(),
            'data' => $turmasComAlunos->pluck('alunos_count')->toArray(),
        ];

        $generoMasculino = $anoLectivoId
            ? Aluno::where('activo', true)
                ->where('genero', 'M')
                ->whereHas('turma', fn ($q) => $q->where('ano_lectivo_id', $anoLectivoId))
                ->count()
            : 0;
        $generoFeminino = $anoLectivoId
            ? Aluno::where('activo', true)
                ->where('genero', 'F')
                ->whereHas('turma', fn ($q) => $q->where('ano_lectivo_id', $anoLectivoId))
                ->count()
            : 0;

        return [
            'totalCursos' => $totalCursos,
            'totalTurmas' => $totalTurmas,
            'totalAlunos' => $totalAlunos,
            'totalProfessores' => $totalProfessores,
            'totalDisciplinas' => $totalDisciplinas,
            'estatisticasCursos' => $estatisticasCursos,
            'anoLectivo' => $anoLectivo,
            'chartAlunosCurso' => $chartAlunosCurso,
            'chartTurmas' => $chartTurmas,
            'chartGenero' => [
                'labels' => ['Masculino', 'Feminino'],
                'data' => [$generoMasculino, $generoFeminino],
            ],
        ];
    }
}
