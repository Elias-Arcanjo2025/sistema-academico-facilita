<?php

namespace App\Services;

use App\Models\Aluno;
use App\Models\Classe;
use App\Models\Configuracao;
use App\Models\Curso;
use App\Models\Nota;
use App\Models\Turma;
use App\Support\SituacaoAcademicaOficial;
use Illuminate\Support\Facades\DB;

class EstatisticasService
{
    /** Período especial: estatística anual (usa cálculo do T3 / CA final). */
    public const PERIODO_ANUAL = 0;

    public function __construct(
        protected PautaService $pautaService
    ) {}

    /**
     * Normaliza o período seleccionado (trimestre 1–3 ou anual).
     *
     * @return array{periodo: int, trimestre_calculo: int, anual: bool}
     */
    public static function resolverPeriodo(mixed $entrada): array
    {
        $anual = $entrada === 'anual' || $entrada === 0 || $entrada === '0';

        if ($anual) {
            return ['periodo' => self::PERIODO_ANUAL, 'trimestre_calculo' => 3, 'anual' => true];
        }

        $periodo = max(1, min(3, (int) $entrada));

        return ['periodo' => $periodo, 'trimestre_calculo' => $periodo, 'anual' => false];
    }

    public static function rotuloPeriodoEstatisticas(int $periodo): string
    {
        return match ($periodo) {
            self::PERIODO_ANUAL => 'Anual',
            1 => 'I Trimestre',
            2 => 'II Trimestre',
            3 => 'III Trimestre',
            default => (string) $periodo,
        };
    }

    public static function tituloMapaPdf(int $periodo, string $anoLectivo): string
    {
        if ($periodo === self::PERIODO_ANUAL) {
            return "MAPA DE APROVEITAMENTO E ESTATÍSTICA ANUAL DO ANO LECTIVO {$anoLectivo}";
        }

        $romano = match ($periodo) {
            1 => 'I',
            2 => 'II',
            3 => 'III',
            default => (string) $periodo,
        };

        return "MAPA DE APROVEITAMENTO E ESTATÍSTICA DO {$romano} TRIMESTRE ANO LECTIVO {$anoLectivo}";
    }

    private function trimestreParaCalculo(int $periodo): int
    {
        return $periodo === self::PERIODO_ANUAL ? 3 : $periodo;
    }

    /**
     * Estatísticas gerais de alunos
     */
    public function getEstatisticasGerais(?int $anoLectivoId = null): array
    {
        $anoLectivoId = $anoLectivoId ?? Configuracao::getAnoLectivoActualId();
        $anoLectivo   = \App\Models\AnoLectivo::find($anoLectivoId)->nome ?? Configuracao::getAnoLectivoActual();

        // Buscar estatísticas agregadas por situacao e genero
        $stats = Aluno::select(
            'situacao_academica',
            'genero',
            DB::raw('COUNT(*) as total')
        )
        ->where('activo', true)
        ->whereHas('turma', function($q) use ($anoLectivoId) {
            $q->where('ano_lectivo_id', $anoLectivoId);
        })
        ->groupBy('situacao_academica', 'genero')
        ->get();

        // Processar dados
        $totalAlunos = $stats->sum('total');
        $porSituacao = [];

        foreach (['matriculado', 'aprovado', 'reprovado', 'desistente', 'transferido'] as $situacao) {
            $filtrado = $stats->where('situacao_academica', $situacao);
            $totalSituacao = $filtrado->sum('total');
            // sum() na Collection é seguro mesmo sem registos (retorna 0)
            $masculino = (int) $filtrado->where('genero', 'M')->sum('total');
            $feminino  = (int) $filtrado->where('genero', 'F')->sum('total');

            $porSituacao[$situacao] = [
                'total' => $totalSituacao,
                'masculino' => $masculino,
                'masculino_percent' => $totalSituacao > 0 ? round(($masculino / $totalSituacao) * 100, 1) : 0,
                'feminino' => $feminino,
                'feminino_percent' => $totalSituacao > 0 ? round(($feminino / $totalSituacao) * 100, 1) : 0,
            ];
        }

        return [
            'ano_lectivo' => $anoLectivo,
            'total_alunos' => $totalAlunos,
            'por_situacao' => $porSituacao,
        ];
    }

    /**
     * Estatísticas por classe com breakdown por gênero e situação
     */
    public function getEstatisticasPorClasse(?int $anoLectivoId = null): array
    {
        $anoLectivoId = $anoLectivoId ?? Configuracao::getAnoLectivoActualId();
        $anoLectivo   = \App\Models\AnoLectivo::find($anoLectivoId)->nome ?? Configuracao::getAnoLectivoActual();

        // Buscar estatísticas agregadas por classe, situacao e genero
        $stats = Aluno::select(
            'classes.codigo as classe',
            'situacao_academica',
            'genero',
            DB::raw('COUNT(*) as total')
        )
        ->join('turmas', 'alunos.turma_id', '=', 'turmas.id')
        ->join('classes', 'turmas.classe_id', '=', 'classes.id')
        ->where('alunos.activo', true)
        ->where('turmas.ano_lectivo_id', $anoLectivoId)
        ->groupBy('classes.codigo', 'situacao_academica', 'genero')
        ->orderBy('classes.codigo')
        ->get();

        // Processar por classe
        $classes = [];

        // Inicializar total geral com todas as situações zeradas
        $totalGeral = [
            'total' => 0,
            'por_situacao' => [
                'matriculado' => ['total' => 0, 'masculino' => 0, 'feminino' => 0],
                'aprovado' => ['total' => 0, 'masculino' => 0, 'feminino' => 0],
                'reprovado' => ['total' => 0, 'masculino' => 0, 'feminino' => 0],
                'desistente' => ['total' => 0, 'masculino' => 0, 'feminino' => 0],
                'transferido' => ['total' => 0, 'masculino' => 0, 'feminino' => 0],
            ],
        ];

        foreach ($stats->groupBy('classe') as $classe => $classeStats) {
            $classeData = [
                'classe' => $classe,
                'total' => $classeStats->sum('total'),
                'por_situacao' => [],
            ];

            foreach (['matriculado', 'aprovado', 'reprovado', 'desistente', 'transferido'] as $situacao) {
                $filtrado = $classeStats->where('situacao_academica', $situacao);
                $totalSituacao = $filtrado->sum('total');
                $masculino = (int) $filtrado->where('genero', 'M')->sum('total');
                $feminino  = (int) $filtrado->where('genero', 'F')->sum('total');

                $classeData['por_situacao'][$situacao] = [
                    'total' => $totalSituacao,
                    'masculino' => $masculino,
                    'feminino' => $feminino,
                ];

                // Acumular totais gerais
                $totalGeral['por_situacao'][$situacao]['total'] += $totalSituacao;
                $totalGeral['por_situacao'][$situacao]['masculino'] += $masculino;
                $totalGeral['por_situacao'][$situacao]['feminino'] += $feminino;
            }

            $classes[$classe] = $classeData;
            $totalGeral['total'] += $classeData['total'];
        }

        return [
            'ano_lectivo' => $anoLectivo,
            'classes' => $classes,
            'total_geral' => $totalGeral,
        ];
    }

    /**
     * Consolida situações académicas com base nas regras DE 424/25 (10.ª–12.ª, pauta T3)
     * ou CFD simples para outras classes / pauta incompleta.
     * Processa matriculados, aprovados e reprovados (permite re-consolidação).
     */
    public function consolidarSituacoesAcademicas(?int $anoLectivoId = null): array
    {
        $anoLectivo = $anoLectivoId
            ? \App\Models\AnoLectivo::find($anoLectivoId)->nome
            : Configuracao::getAnoLectivoActual();

        $notaMinima = (float) Configuracao::get('nota_minima_aprovacao', 10);
        $maxReprovacoes = (int) Configuracao::get('max_disciplinas_reprovacao', 2);

        $atualizados = 0;
        $aprovados = 0;
        $reprovados = 0;
        $mantidos = 0;
        $oficial = 0;
        $cfdFallback = 0;

        $resolvedAnoLectivoId = $anoLectivoId ?? Configuracao::getAnoLectivoActualId();
        $recuperacaoService = app(RecuperacaoTransicaoService::class);

        DB::transaction(function () use (
            $anoLectivo,
            $resolvedAnoLectivoId,
            $notaMinima,
            $maxReprovacoes,
            $recuperacaoService,
            &$atualizados,
            &$aprovados,
            &$reprovados,
            &$mantidos,
            &$oficial,
            &$cfdFallback
        ) {
            $alunos = Aluno::with('turma.classe')
                ->where('activo', true)
                ->whereHas('turma', fn ($q) => $q->where('ano_lectivo_id', $resolvedAnoLectivoId))
                ->whereIn('situacao_academica', [
                    Aluno::SITUACAO_MATRICULADO,
                    Aluno::SITUACAO_APROVADO,
                    Aluno::SITUACAO_REPROVADO,
                ])
                ->get();

            if ($alunos->isEmpty()) {
                return;
            }

            $alunoIds = $alunos->pluck('id')->toArray();
            $notasPorAluno = $this->carregarNotasCfdPorAluno($alunoIds, $anoLectivo, $resolvedAnoLectivoId);

            foreach ($alunos->groupBy('turma_id') as $grupo) {
                $turma = $grupo->first()->turma;
                $classeNumero = (int) ($turma?->classe?->codigo ?? 0);

                $analises = in_array($classeNumero, [10, 11, 12], true)
                    ? $recuperacaoService->resumoPorTurma($turma, $anoLectivo)->keyBy('aluno_id')
                    : collect();

                foreach ($grupo as $aluno) {
                    $situacao = null;
                    $viaOficial = false;

                    if ($analises->isNotEmpty()) {
                        $situacao = SituacaoAcademicaOficial::situacaoAcademicaDeAnalise(
                            $analises->get($aluno->id, []),
                            $aluno
                        );
                        if ($situacao !== null) {
                            $viaOficial = true;
                        }
                    }

                    if ($situacao === null) {
                        $situacao = $this->situacaoAcademicaPorCfdSimples(
                            $notasPorAluno->get($aluno->id, collect()),
                            $notaMinima,
                            $maxReprovacoes
                        );
                        if ($situacao === null) {
                            $mantidos++;

                            continue;
                        }
                        $cfdFallback++;
                    } else {
                        $oficial++;
                    }

                    if ($aluno->situacao_academica !== $situacao) {
                        $aluno->update(['situacao_academica' => $situacao]);
                        $atualizados++;
                        $situacao === Aluno::SITUACAO_APROVADO ? $aprovados++ : $reprovados++;
                    } else {
                        $mantidos++;
                    }
                }
            }
        });

        activity()
            ->causedBy(auth()->user())
            ->withProperties([
                'ano_lectivo' => $anoLectivo,
                'atualizados' => $atualizados,
                'aprovados' => $aprovados,
                'reprovados' => $reprovados,
                'mantidos' => $mantidos,
                'via_oficial' => $oficial,
                'via_cfd_fallback' => $cfdFallback,
            ])
            ->log('Consolidação de situações académicas');

        return [
            'total_processados' => $atualizados + $mantidos,
            'atualizados' => $atualizados,
            'aprovados' => $aprovados,
            'reprovados' => $reprovados,
            'mantidos' => $mantidos,
            'via_oficial' => $oficial,
            'via_cfd_fallback' => $cfdFallback,
        ];
    }

    /**
     * @param  array<int, int>  $alunoIds
     */
    private function carregarNotasCfdPorAluno(array $alunoIds, string $anoLectivo, ?int $anoLectivoId): \Illuminate\Support\Collection
    {
        return DB::table('notas')
            ->whereIn('aluno_id', $alunoIds)
            ->where(function ($q) use ($anoLectivo, $anoLectivoId) {
                $q->where('ano_lectivo', $anoLectivo);
                if ($anoLectivoId) {
                    $q->orWhere('ano_lectivo_id', $anoLectivoId);
                }
            })
            ->whereIn('tipo', [Nota::TIPO_CFD, Nota::TIPO_FINAL])
            ->whereNull('archived_at')
            ->select('aluno_id', 'disciplina_id', 'tipo', 'valor')
            ->get()
            ->groupBy('aluno_id');
    }

    /**
     * Regra simplificada por CFD (outras classes ou pauta T3 incompleta).
     */
    private function situacaoAcademicaPorCfdSimples(
        \Illuminate\Support\Collection $notasFinais,
        float $notaMinima,
        int $maxReprovacoes
    ): ?string {
        if ($notasFinais->isEmpty()) {
            return null;
        }

        $notasUnicas = $notasFinais
            ->groupBy('disciplina_id')
            ->map(fn ($g) => $g->firstWhere('tipo', Nota::TIPO_CFD) ?? $g->first());

        $reprov = $notasUnicas->filter(fn ($n) => (float) $n->valor < $notaMinima)->count();

        return $reprov <= $maxReprovacoes
            ? Aluno::SITUACAO_APROVADO
            : Aluno::SITUACAO_REPROVADO;
    }

    /**
     * Estatísticas gerais calculadas das notas oficiais do trimestre:
     * T1 → MT | T2 → MFT2 (politécnico) ou MT | T3 → CA (pauta).
     */
    public function getEstatisticasGeraisPorNotas(string $anoLectivo, int $periodo): array
    {
        $trimestre = $this->trimestreParaCalculo($periodo);
        $notaMinima     = (float) Configuracao::get('nota_minima_aprovacao', 10);
        $maxReprovacoes = (int)   Configuracao::get('max_disciplinas_reprovacao', 2);
        $anoLectivoId   = \App\Models\AnoLectivo::where('nome', $anoLectivo)->value('id')
                          ?? Configuracao::getAnoLectivoActualId();

        $alunosRaw = DB::table('alunos')
            ->join('turmas', 'alunos.turma_id', '=', 'turmas.id')
            ->where('turmas.ano_lectivo_id', $anoLectivoId)
            ->where('alunos.activo', true)
            ->select('alunos.id', 'alunos.genero', 'alunos.situacao_academica')
            ->get();

        $alunoIds = $alunosRaw->pluck('id')->toArray();
        $indices  = $this->indicesAproveitamentoPorAluno($anoLectivo, $trimestre, $anoLectivoId, $alunoIds);
        $autoDesistentes = $trimestre === 3
            ? []
            : $this->getAutoDesistenteIds($alunoIds, $anoLectivoId, $trimestre);

        $isDesistente = function ($aluno) use ($autoDesistentes, $indices, $trimestre) {
            if ($aluno->situacao_academica === 'desistente') {
                return true;
            }
            if ($trimestre === 3) {
                return (bool) ($indices[$aluno->id]['desistente_pauta'] ?? false);
            }

            return isset($autoDesistentes[$aluno->id]);
        };

        $totalMF       = $alunosRaw->count();
        $totalF        = $alunosRaw->where('genero', 'F')->count();
        $desistentesMF = $alunosRaw->filter($isDesistente)->count();
        $desistentesF  = $alunosRaw->filter($isDesistente)->where('genero', 'F')->count();
        $transferidosMF = $alunosRaw->where('situacao_academica', 'transferido')->count();
        $avaliadosMF   = $totalMF - $desistentesMF - $transferidosMF;
        $avaliadosF    = $totalF  - $desistentesF  - $alunosRaw->where('situacao_academica', 'transferido')->where('genero', 'F')->count();

        $comMF = 0; $comF = 0; $semMF = 0; $semF = 0;
        $semNotasMF = 0; $semNotasF = 0;

        $avaliados = $alunosRaw
            ->whereNotIn('situacao_academica', ['desistente', 'transferido'])
            ->reject(fn ($a) => $isDesistente($a));

        foreach ($avaliados as $aluno) {
            $valores = $indices[$aluno->id]['valores'] ?? [];
            $isF     = $aluno->genero === 'F';

            if ($valores === []) {
                $semNotasMF++;
                if ($isF) {
                    $semNotasF++;
                }
                $semMF++;
                if ($isF) {
                    $semF++;
                }
                continue;
            }

            if ($this->temAproveitamento($valores, $notaMinima, $maxReprovacoes)) {
                $comMF++;
                if ($isF) {
                    $comF++;
                }
            } else {
                $semMF++;
                if ($isF) {
                    $semF++;
                }
            }
        }

        return [
            'ano_lectivo'    => $anoLectivo,
            'periodo'        => $periodo,
            'trimestre'      => $periodo,
            'anual'          => $periodo === self::PERIODO_ANUAL,
            'metrica'        => self::metricaAproveitamentoLabel($periodo),
            'total_mf'       => $totalMF,
            'total_f'        => $totalF,
            'desistentes_mf' => $desistentesMF,
            'desistentes_f'  => $desistentesF,
            'transferidos_mf'=> $transferidosMF,
            'avaliados_mf'   => $avaliadosMF,
            'avaliados_f'    => $avaliadosF,
            'sem_notas_mf'   => $semNotasMF,
            'com_mf'         => $comMF,
            'com_f'          => $comF,
            'com_pct'        => $avaliadosMF > 0 ? round($comMF / $avaliadosMF * 100, 1) : 0.0,
            'sem_mf'         => $semMF,
            'sem_f'          => $semF,
            'sem_pct'        => $avaliadosMF > 0 ? round($semMF / $avaliadosMF * 100, 1) : 0.0,
        ];
    }

    /**
     * Estatísticas por classe — mesma métrica trimestral que getEstatisticasGeraisPorNotas.
     */
    public function getEstatisticasPorClassePorNotas(string $anoLectivo, int $periodo): array
    {
        $trimestre = $this->trimestreParaCalculo($periodo);
        $notaMinima     = (float) Configuracao::get('nota_minima_aprovacao', 10);
        $maxReprovacoes = (int)   Configuracao::get('max_disciplinas_reprovacao', 2);
        $anoLectivoId   = \App\Models\AnoLectivo::where('nome', $anoLectivo)->value('id')
                          ?? Configuracao::getAnoLectivoActualId();

        $alunosRaw = DB::table('alunos')
            ->join('turmas', 'alunos.turma_id', '=', 'turmas.id')
            ->join('classes', 'turmas.classe_id', '=', 'classes.id')
            ->where('turmas.ano_lectivo_id', $anoLectivoId)
            ->where('alunos.activo', true)
            ->select(
                'alunos.id', 'alunos.genero', 'alunos.situacao_academica',
                'classes.codigo as classe', 'classes.nome as classe_nome', 'classes.ordem as classe_ordem'
            )
            ->get();

        $alunoIds = $alunosRaw->pluck('id')->toArray();
        $indices  = $this->indicesAproveitamentoPorAluno($anoLectivo, $trimestre, $anoLectivoId, $alunoIds);
        $autoDesistentes = $trimestre === 3
            ? []
            : $this->getAutoDesistenteIds($alunoIds, $anoLectivoId, $trimestre);

        $classes    = [];
        $totalGeral = ['total' => 0, 'avaliados' => 0, 'com' => 0, 'sem' => 0, 'desistentes' => 0];

        foreach ($alunosRaw->groupBy('classe') as $classeCode => $classeAlunos) {
            $total       = $classeAlunos->count();
            $desist      = $classeAlunos->filter(function ($a) use ($autoDesistentes, $indices, $trimestre) {
                if ($a->situacao_academica === 'desistente') {
                    return true;
                }
                if ($trimestre === 3) {
                    return (bool) ($indices[$a->id]['desistente_pauta'] ?? false);
                }

                return isset($autoDesistentes[$a->id]);
            })->count();
            $transf      = $classeAlunos->where('situacao_academica', 'transferido')->count();
            $avaliados   = $total - $desist - $transf;
            $comMF = 0; $comF = 0; $semMF = 0; $semF = 0;

            $alunosAvaliar = $classeAlunos
                ->whereNotIn('situacao_academica', ['desistente', 'transferido'])
                ->reject(function ($a) use ($autoDesistentes, $indices, $trimestre) {
                    if ($trimestre === 3) {
                        return (bool) ($indices[$a->id]['desistente_pauta'] ?? false);
                    }

                    return isset($autoDesistentes[$a->id]);
                });

            foreach ($alunosAvaliar as $aluno) {
                $valores = $indices[$aluno->id]['valores'] ?? [];
                $isF     = $aluno->genero === 'F';

                if ($valores !== [] && $this->temAproveitamento($valores, $notaMinima, $maxReprovacoes)) {
                    $comMF++;
                    if ($isF) {
                        $comF++;
                    }
                } else {
                    $semMF++;
                    if ($isF) {
                        $semF++;
                    }
                }
            }

            $classes[$classeCode] = [
                'classe'      => $classeCode,
                'nome'        => $classeAlunos->first()->classe_nome,
                'ordem'       => $classeAlunos->first()->classe_ordem,
                'total'       => $total,
                'desistentes' => $desist,
                'avaliados'   => $avaliados,
                'com_mf'      => $comMF,
                'com_f'       => $comF,
                'com_pct'     => $avaliados > 0 ? round($comMF / $avaliados * 100, 1) : 0.0,
                'sem_mf'      => $semMF,
                'sem_f'       => $semF,
                'sem_pct'     => $avaliados > 0 ? round($semMF / $avaliados * 100, 1) : 0.0,
            ];

            $totalGeral['total']       += $total;
            $totalGeral['desistentes'] += $desist;
            $totalGeral['avaliados']   += $avaliados;
            $totalGeral['com']         += $comMF;
            $totalGeral['sem']         += $semMF;
        }

        $totalGeral['com_pct'] = $totalGeral['avaliados'] > 0
            ? round($totalGeral['com'] / $totalGeral['avaliados'] * 100, 1) : 0.0;
        $totalGeral['sem_pct'] = $totalGeral['avaliados'] > 0
            ? round($totalGeral['sem'] / $totalGeral['avaliados'] * 100, 1) : 0.0;

        uasort($classes, fn($a, $b) => $a['ordem'] <=> $b['ordem']);

        return [
            'ano_lectivo' => $anoLectivo,
            'periodo'     => $periodo,
            'trimestre'   => $periodo,
            'anual'       => $periodo === self::PERIODO_ANUAL,
            'metrica'     => self::metricaAproveitamentoLabel($periodo),
            'classes'     => $classes,
            'total_geral' => $totalGeral,
        ];
    }

    /**
     * Mapa de Aproveitamento e Estatística por trimestre ou anual.
     * Estrutura idêntica ao documento oficial: por turma, com SUB-TOTAL e TOTAL GERAL.
     */
    public function getMapaAproveitamento(string $anoLectivo, int $periodo, ?int $cursoId = null): array
    {
        $trimestre = $this->trimestreParaCalculo($periodo);
        $notaMinima     = (float) Configuracao::get('nota_minima_aprovacao', 10);
        $maxReprovacoes = (int)   Configuracao::get('max_disciplinas_reprovacao', 2);
        $anoLectivoId   = \App\Models\AnoLectivo::where('nome', $anoLectivo)->value('id')
                          ?? Configuracao::getAnoLectivoActualId();

        // 1. Turmas do ano lectivo (opcionalmente filtradas por curso)
        $turmasQuery = DB::table('turmas')
            ->join('classes', 'turmas.classe_id', '=', 'classes.id')
            ->join('cursos', 'turmas.curso_id', '=', 'cursos.id')
            ->where('turmas.ano_lectivo_id', $anoLectivoId)
            ->where('turmas.activo', true)
            ->select(
                'turmas.id as turma_id',
                'turmas.nome as turma_nome',
                'classes.id as classe_id',
                'classes.nome as classe_nome',
                'classes.codigo as classe_codigo',
                'classes.ordem as classe_ordem',
                'cursos.id as curso_id',
                'cursos.nome as curso_nome'
            )
            ->orderBy('cursos.nome')
            ->orderBy('classes.ordem')
            ->orderBy('turmas.nome');

        if ($cursoId) {
            $turmasQuery->where('turmas.curso_id', $cursoId);
        }

        $turmas = $turmasQuery->get();

        if ($turmas->isEmpty()) {
            return [
                'ano_lectivo'  => $anoLectivo,
                'periodo'      => $periodo,
                'trimestre'    => $periodo,
                'anual'        => $periodo === self::PERIODO_ANUAL,
                'por_curso'    => collect(),
                'total_geral'  => $this->initMapaRow(),
            ];
        }

        $turmaIds = $turmas->pluck('turma_id')->toArray();

        // 2. Alunos activos por turma
        $alunosRaw = DB::table('alunos')
            ->whereIn('turma_id', $turmaIds)
            ->where('activo', true)
            ->select('id', 'turma_id', 'genero', 'situacao_academica')
            ->get();

        $alunosPorTurma = $alunosRaw->groupBy('turma_id');
        $alunoIds       = $alunosRaw->pluck('id')->toArray();
        $indices        = $this->indicesAproveitamentoPorAluno($anoLectivo, $trimestre, $anoLectivoId, $alunoIds);
        $autoDesistentes = $trimestre === 3
            ? []
            : $this->getAutoDesistenteIds($alunoIds, $anoLectivoId, $trimestre);

        // 4. Professores por turma (distintos) e por classe+curso
        $professoresRaw = DB::table('professor_disciplina_turma')
            ->join('turmas as t2', 'professor_disciplina_turma.turma_id', '=', 't2.id')
            ->whereIn('professor_disciplina_turma.turma_id', $turmaIds)
            ->where('professor_disciplina_turma.ano_lectivo', $anoLectivo)
            ->where('professor_disciplina_turma.activo', true)
            ->select(
                'professor_disciplina_turma.turma_id',
                't2.classe_id',
                't2.curso_id',
                'professor_disciplina_turma.user_id'
            )
            ->distinct()
            ->get();

        // Professores distintos por classe_id+curso_id
        $professPorClasse = $professoresRaw
            ->groupBy(fn($p) => $p->classe_id . '_' . $p->curso_id)
            ->map(fn($g) => $g->unique('user_id')->count());

        // Todos os professores distintos do query (para TOTAL GERAL)
        $todosProfsDistinct = $professoresRaw->unique('user_id')->count();

        // 5. Processar cada turma
        $resultados = [];
        foreach ($turmas as $turma) {
            $turmaAlunos = $alunosPorTurma->get($turma->turma_id, collect());

            $matriculadosMF = $turmaAlunos->count();
            $matriculadosF  = $turmaAlunos->where('genero', 'F')->count();

            $isDesist = function ($a) use ($autoDesistentes, $indices, $trimestre) {
                if ($a->situacao_academica === 'desistente') {
                    return true;
                }
                if ($trimestre === 3) {
                    return (bool) ($indices[$a->id]['desistente_pauta'] ?? false);
                }

                return isset($autoDesistentes[$a->id]);
            };

            $desistentesMF  = $turmaAlunos->filter($isDesist)->count();
            $desistentesF   = $turmaAlunos->filter($isDesist)->where('genero', 'F')->count();
            $transferidosMF = $turmaAlunos->where('situacao_academica', 'transferido')->count();
            $transferidosF  = $turmaAlunos->where('situacao_academica', 'transferido')->where('genero', 'F')->count();

            // Avaliados = total − desistentes − transferidos (coerente com estatísticas gerais)
            $avaliadosMF = $matriculadosMF - $desistentesMF - $transferidosMF;
            $avaliadosF  = $matriculadosF  - $desistentesF  - $transferidosF;

            $comMF = 0; $comF = 0; $semMF = 0; $semF = 0;

            $alunosAvaliar = $turmaAlunos
                ->whereNotIn('situacao_academica', ['desistente', 'transferido'])
                ->reject(fn ($a) => $isDesist($a));

            foreach ($alunosAvaliar as $aluno) {
                $valores = $indices[$aluno->id]['valores'] ?? [];

                if ($valores !== [] && $this->temAproveitamento($valores, $notaMinima, $maxReprovacoes)) {
                    $comMF++;
                    if ($aluno->genero === 'F') {
                        $comF++;
                    }
                } else {
                    $semMF++;
                    if ($aluno->genero === 'F') {
                        $semF++;
                    }
                }
            }

            $comPct = $avaliadosMF > 0 ? round($comMF / $avaliadosMF * 100, 1) : 0.0;
            $semPct = $avaliadosMF > 0 ? round($semMF / $avaliadosMF * 100, 1) : 0.0;

            $classeChave = $turma->classe_id . '_' . $turma->curso_id;

            $resultados[] = [
                'curso_id'         => $turma->curso_id,
                'curso_nome'       => $turma->curso_nome,
                'turma_id'         => $turma->turma_id,
                'turma_nome'       => $turma->turma_nome,
                'classe_id'        => $turma->classe_id,
                'classe_nome'      => $turma->classe_nome,
                'classe_ordem'     => $turma->classe_ordem,
                'nivel'            => (int) $turma->classe_codigo >= 10 ? 'II CÍCLO' : 'I CÍCLO',
                'matriculados_mf'  => $matriculadosMF,
                'matriculados_f'   => $matriculadosF,
                'desistentes_mf'   => $desistentesMF,
                'desistentes_f'    => $desistentesF,
                'avaliados_mf'     => $avaliadosMF,
                'avaliados_f'      => $avaliadosF,
                'com_mf'           => $comMF,
                'com_f'            => $comF,
                'com_pct'          => $comPct,
                'sem_mf'           => $semMF,
                'sem_f'            => $semF,
                'sem_pct'          => $semPct,
                'professores_mf'   => $professPorClasse->get($classeChave, 0),
                'professores_f'    => 0,
            ];
        }

        // 6. Agrupar por curso → (lista de turmas + total do curso)
        $porCurso = collect($resultados)
            ->groupBy('curso_nome')
            ->map(function ($cursoTurmas) use ($professoresRaw) {
                $total = $this->initMapaRow();
                foreach ($cursoTurmas as $t) {
                    $total['matriculados_mf'] += $t['matriculados_mf'];
                    $total['matriculados_f']  += $t['matriculados_f'];
                    $total['desistentes_mf']  += $t['desistentes_mf'];
                    $total['desistentes_f']   += $t['desistentes_f'];
                    $total['avaliados_mf']    += $t['avaliados_mf'];
                    $total['avaliados_f']     += $t['avaliados_f'];
                    $total['com_mf']          += $t['com_mf'];
                    $total['com_f']           += $t['com_f'];
                    $total['sem_mf']          += $t['sem_mf'];
                    $total['sem_f']           += $t['sem_f'];
                }
                $total['com_pct'] = $total['avaliados_mf'] > 0
                    ? round($total['com_mf'] / $total['avaliados_mf'] * 100, 1) : 0.0;
                $total['sem_pct'] = $total['avaliados_mf'] > 0
                    ? round($total['sem_mf'] / $total['avaliados_mf'] * 100, 1) : 0.0;

                $cursoTurmaIds = $cursoTurmas->pluck('turma_id')->toArray();
                $total['professores_mf'] = $professoresRaw
                    ->filter(fn($p) => in_array($p->turma_id, $cursoTurmaIds))
                    ->unique('user_id')
                    ->count();

                return [
                    'turmas' => $cursoTurmas->sortBy([
                        ['classe_ordem', 'asc'],
                        ['turma_nome', 'asc'],
                    ])->values()->toArray(),
                    'total'  => $total,
                ];
            });

        // 7. Total Geral
        $totalGeral = $this->initMapaRow();
        foreach ($resultados as $t) {
            $totalGeral['matriculados_mf'] += $t['matriculados_mf'];
            $totalGeral['matriculados_f']  += $t['matriculados_f'];
            $totalGeral['desistentes_mf']  += $t['desistentes_mf'];
            $totalGeral['desistentes_f']   += $t['desistentes_f'];
            $totalGeral['avaliados_mf']    += $t['avaliados_mf'];
            $totalGeral['avaliados_f']     += $t['avaliados_f'];
            $totalGeral['com_mf']          += $t['com_mf'];
            $totalGeral['com_f']           += $t['com_f'];
            $totalGeral['sem_mf']          += $t['sem_mf'];
            $totalGeral['sem_f']           += $t['sem_f'];
        }
        $totalGeral['com_pct'] = $totalGeral['avaliados_mf'] > 0
            ? round($totalGeral['com_mf'] / $totalGeral['avaliados_mf'] * 100, 1) : 0.0;
        $totalGeral['sem_pct'] = $totalGeral['avaliados_mf'] > 0
            ? round($totalGeral['sem_mf'] / $totalGeral['avaliados_mf'] * 100, 1) : 0.0;
        $totalGeral['professores_mf'] = $todosProfsDistinct;

        return [
            'ano_lectivo'  => $anoLectivo,
            'periodo'      => $periodo,
            'trimestre'    => $periodo,
            'anual'        => $periodo === self::PERIODO_ANUAL,
            'metrica'      => self::metricaAproveitamentoLabel($periodo),
            'por_curso'    => $porCurso,
            'total_geral'  => $totalGeral,
        ];
    }

    public static function metricaAproveitamentoLabel(int $periodo): string
    {
        return match ($periodo) {
            1 => 'MT',
            2 => 'MFT2',
            self::PERIODO_ANUAL => 'CA (anual)',
            default => 'CA',
        };
    }

    /**
     * @param  array<int>  $alunoIds
     * @return array<int, array{valores: array<float>, desistente_pauta: bool}>
     */
    private function indicesAproveitamentoPorAluno(
        string $anoLectivo,
        int $trimestre,
        int $anoLectivoId,
        array $alunoIds,
    ): array {
        if ($alunoIds === []) {
            return [];
        }

        if ($trimestre === 3) {
            return $this->indicesAproveitamentoT3($anoLectivo, $anoLectivoId, $alunoIds);
        }

        return $trimestre === 2
            ? $this->indicesAproveitamentoT2($anoLectivo, $anoLectivoId, $alunoIds)
            : $this->indicesAproveitamentoT1($anoLectivo, $alunoIds);
    }

    /**
     * @param  array<int>  $alunoIds
     * @return array<int, array{valores: array<float>, desistente_pauta: bool}>
     */
    private function indicesAproveitamentoT1(string $anoLectivo, array $alunoIds): array
    {
        $rows = DB::table('notas')
            ->whereIn('aluno_id', $alunoIds)
            ->where('tipo', Nota::TIPO_MT)
            ->where('trimestre', 1)
            ->where('ano_lectivo', $anoLectivo)
            ->whereNull('archived_at')
            ->select('aluno_id', 'disciplina_id', 'valor', 'estado_conselho')
            ->get();

        return $this->agruparValoresDedup($rows, $alunoIds);
    }

    /**
     * @param  array<int>  $alunoIds
     * @return array<int, array{valores: array<float>, desistente_pauta: bool}>
     */
    private function indicesAproveitamentoT2(string $anoLectivo, int $anoLectivoId, array $alunoIds): array
    {
        $alunosTurma = DB::table('alunos')
            ->whereIn('alunos.id', $alunoIds)
            ->join('turmas', 'alunos.turma_id', '=', 'turmas.id')
            ->join('cursos', 'turmas.curso_id', '=', 'cursos.id')
            ->select('alunos.id as aluno_id', 'turmas.id as turma_id', 'cursos.regime_calculo')
            ->get()
            ->keyBy('aluno_id');

        $indices = array_fill_keys($alunoIds, ['valores' => [], 'desistente_pauta' => false]);

        $idsAdmin = $alunosTurma->filter(fn ($a) => ($a->regime_calculo ?? '') === Curso::REGIME_ADMINISTRACAO)->pluck('aluno_id')->all();
        $idsPoli  = $alunosTurma->filter(fn ($a) => ($a->regime_calculo ?? Curso::REGIME_POLITECNICO) !== Curso::REGIME_ADMINISTRACAO)->pluck('aluno_id')->all();

        if ($idsAdmin !== []) {
            foreach ($this->indicesAproveitamentoT1MtTrimestre($anoLectivo, $idsAdmin, 2) as $id => $data) {
                $indices[$id] = $data;
            }
        }

        if ($idsPoli !== []) {
            $mft2Rows = DB::table('notas')
                ->whereIn('aluno_id', $idsPoli)
                ->where('tipo', Nota::TIPO_MFT2)
                ->where('ano_lectivo', $anoLectivo)
                ->whereNull('archived_at')
                ->select('aluno_id', 'disciplina_id', 'valor', 'estado_conselho', 'trimestre')
                ->get();

            $dedupMft2 = $mft2Rows
                ->groupBy(fn ($n) => "{$n->aluno_id}|{$n->disciplina_id}")
                ->map(fn ($g) => $g->firstWhere('estado_conselho', Nota::ESTADO_POS_CONSELHO) ?? $g->first());

            foreach ($dedupMft2 as $n) {
                $indices[$n->aluno_id]['valores'][] = (float) $n->valor;
            }

            $semMft2 = array_diff($idsPoli, $dedupMft2->pluck('aluno_id')->unique()->all());
            if ($semMft2 !== []) {
                $mts = DB::table('notas')
                    ->whereIn('aluno_id', $semMft2)
                    ->where('tipo', Nota::TIPO_MT)
                    ->whereIn('trimestre', [1, 2])
                    ->where('ano_lectivo', $anoLectivo)
                    ->whereNull('archived_at')
                    ->select('aluno_id', 'disciplina_id', 'trimestre', 'valor', 'estado_conselho')
                    ->get()
                    ->groupBy(fn ($n) => "{$n->aluno_id}|{$n->disciplina_id}|{$n->trimestre}")
                    ->map(fn ($g) => $g->firstWhere('estado_conselho', Nota::ESTADO_POS_CONSELHO) ?? $g->first())
                    ->values()
                    ->groupBy(fn ($n) => "{$n->aluno_id}|{$n->disciplina_id}");

                foreach ($mts as $key => $group) {
                    [$alunoId] = explode('|', $key);
                    $alunoId = (int) $alunoId;
                    $mt1 = $group->firstWhere('trimestre', 1)?->valor;
                    $mt2 = $group->firstWhere('trimestre', 2)?->valor;
                    if ($mt1 !== null && $mt2 !== null) {
                        $indices[$alunoId]['valores'][] = round(((float) $mt1 + (float) $mt2) / 2);
                    }
                }
            }
        }

        return $indices;
    }

    /**
     * @param  array<int>  $alunoIds
     * @return array<int, array{valores: array<float>, desistente_pauta: bool}>
     */
    private function indicesAproveitamentoT1MtTrimestre(string $anoLectivo, array $alunoIds, int $trimestre): array
    {
        $rows = DB::table('notas')
            ->whereIn('aluno_id', $alunoIds)
            ->where('tipo', Nota::TIPO_MT)
            ->where('trimestre', $trimestre)
            ->where('ano_lectivo', $anoLectivo)
            ->whereNull('archived_at')
            ->select('aluno_id', 'disciplina_id', 'valor', 'estado_conselho')
            ->get();

        return $this->agruparValoresDedup($rows, $alunoIds);
    }

    /**
     * @param  array<int>  $alunoIds
     * @return array<int, array{valores: array<float>, desistente_pauta: bool}>
     */
    private function indicesAproveitamentoT3(string $anoLectivo, int $anoLectivoId, array $alunoIds): array
    {
        $indices = array_fill_keys($alunoIds, ['valores' => [], 'desistente_pauta' => false]);
        $alunoIdSet = array_flip($alunoIds);

        $turmas = Turma::activo()
            ->where('ano_lectivo_id', $anoLectivoId)
            ->with('curso')
            ->get();

        foreach ($turmas as $turma) {
            try {
                $pauta = $this->pautaService->gerarPautaTrimestralCompleta($turma->id, 3, $anoLectivo);
                $regime = $this->pautaService->regimeDaTurma($turma);
            } catch (\Throwable) {
                continue;
            }

            foreach ($pauta['alunos'] ?? [] as $alunoPauta) {
                $aluno = $alunoPauta['aluno'] ?? null;
                if (! $aluno || ! isset($alunoIdSet[$aluno->id])) {
                    continue;
                }

                $indices[$aluno->id]['desistente_pauta'] = (bool) ($alunoPauta['desistente'] ?? false);

                if ($indices[$aluno->id]['desistente_pauta']) {
                    continue;
                }

                foreach ($alunoPauta['disciplinas'] ?? [] as $d) {
                    $ca = RelatorioAcademicoService::notaRankingDisciplina($d, 3, $regime);
                    if ($ca !== null) {
                        $indices[$aluno->id]['valores'][] = $ca;
                    }
                }
            }
        }

        return $indices;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @param  array<int>  $alunoIds
     * @return array<int, array{valores: array<float>, desistente_pauta: bool}>
     */
    private function agruparValoresDedup($rows, array $alunoIds): array
    {
        $indices = array_fill_keys($alunoIds, ['valores' => [], 'desistente_pauta' => false]);

        $dedup = $rows
            ->groupBy(fn ($n) => "{$n->aluno_id}|{$n->disciplina_id}")
            ->map(fn ($g) => $g->firstWhere('estado_conselho', Nota::ESTADO_POS_CONSELHO) ?? $g->first());

        foreach ($dedup as $n) {
            $indices[$n->aluno_id]['valores'][] = (float) $n->valor;
        }

        return $indices;
    }

    /**
     * @param  array<float>  $valores
     */
    private function temAproveitamento(array $valores, float $notaMinima, int $maxReprovacoes): bool
    {
        if ($valores === []) {
            return false;
        }

        $reprov = count(array_filter($valores, fn ($v) => $v < $notaMinima));

        return $reprov <= $maxReprovacoes;
    }

    private function initMapaRow(): array
    {
        return [
            'matriculados_mf' => 0, 'matriculados_f' => 0,
            'desistentes_mf'  => 0, 'desistentes_f'  => 0,
            'avaliados_mf'    => 0, 'avaliados_f'    => 0,
            'com_mf'          => 0, 'com_f'          => 0, 'com_pct' => 0.0,
            'sem_mf'          => 0, 'sem_f'          => 0, 'sem_pct' => 0.0,
            'professores_mf'  => 0, 'professores_f'  => 0,
        ];
    }

    /**
     * Atalho — delega no PautaService. Mantido para compatibilidade com
     * código existente que invoca este método através de EstatisticasService.
     *
     * @return array<int,bool>
     */
    public function getAutoDesistenteIds(array $alunoIds, int $anoLectivoId, ?int $trimestre = null): array
    {
        return $this->pautaService->getAutoDesistenteIds($alunoIds, $anoLectivoId, $trimestre);
    }
}
