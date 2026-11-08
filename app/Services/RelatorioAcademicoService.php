<?php

namespace App\Services;

use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\Nota;
use App\Models\Turma;
use App\Services\RegimePauta\RegimePautaInterface;
use App\Support\PautaCalculos;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RelatorioAcademicoService
{
    public function __construct(
        protected PautaService $pautaService,
    ) {}

    /**
     * @param  int|null  $turmaId  Filtro opcional de turma
     */
    public function getRelatorio(
        int $anoLectivoId,
        string $anoLectivo,
        ?int $trimestre,
        ?int $cursoId = null,
        ?int $turmaId = null,
    ): array {
        $notaMinima = (float) Configuracao::get('nota_minima_aprovacao', 10);
        $maxReprovacoes = (int) Configuracao::get('max_disciplinas_reprovacao', 2);

        $turmas = $this->getTurmas($anoLectivoId, $cursoId, $turmaId);
        $usaT3Oficial = $trimestre === 3 || $trimestre === null;

        return [
            'total_turmas' => $turmas->count(),
            'turmas' => $turmas,
            'aproveitamento' => $usaT3Oficial
                ? $this->getAproveitamentoT3Oficial($turmas, $anoLectivo)
                : $this->getAproveitamentoTrimestralMT($turmas, $anoLectivo, $anoLectivoId, $trimestre, $notaMinima, $maxReprovacoes),
            'melhores_por_turma' => $this->getMelhoresPorTurma(
                $turmas,
                $anoLectivo,
                $trimestre === null ? 3 : $trimestre,
                $notaMinima,
            ),
            'metrica_ranking' => self::metricaRankingLabel($trimestre === null ? 3 : $trimestre),
            'top_professores_negativos' => $this->getTopProfessoresComMaisNotasNegativas(
                $anoLectivoId,
                $anoLectivo,
                $trimestre === null ? null : $trimestre,
                $notaMinima,
                $cursoId,
                $turmaId,
            ),
            'modo_aproveitamento' => $usaT3Oficial ? 't3_oficial' : 'trimestral_mt',
        ];
    }

    private function getTurmas(int $anoLectivoId, ?int $cursoId, ?int $turmaId): Collection
    {
        return Turma::activo()
            ->where('ano_lectivo_id', $anoLectivoId)
            ->when($cursoId, fn ($q) => $q->where('curso_id', $cursoId))
            ->when($turmaId, fn ($q) => $q->where('id', $turmaId))
            ->with(['curso', 'classe', 'alunos' => fn ($q) => $q->activo()])
            ->orderBy('nome')
            ->get();
    }

    /**
     * T3 / anual — resultados oficiais da pauta (CA/CFD, regras de transição 10.ª/11.ª).
     */
    private function getAproveitamentoT3Oficial(Collection $turmas, string $anoLectivo): array
    {
        $stats = $this->aproveitamentoVazio();
        $stats['modo'] = 't3_oficial';
        $stats['transita'] = 0;
        $stats['transita_deficiencia'] = 0;
        $stats['recuperacao'] = 0;
        $stats['reprova'] = 0;
        $stats['aprovado'] = 0;
        $stats['pendente'] = 0;

        foreach ($turmas as $turma) {
            try {
                $pauta = $this->pautaService->gerarPautaTrimestralCompleta($turma->id, 3, $anoLectivo);
            } catch (\Throwable) {
                continue;
            }

            foreach ($pauta['alunos'] ?? [] as $aluno) {
                $stats['total']++;
                $resultado = strtoupper(trim((string) ($aluno['resultado'] ?? '')));
                $desistente = (bool) ($aluno['desistente'] ?? false);

                if ($desistente || $resultado === 'DESISTENTE') {
                    $stats['desistentes']++;
                    continue;
                }

                $stats['activos']++;
                $bucket = self::classificarResultadoT3($resultado);

                match ($bucket) {
                    'transita' => $stats['transita']++,
                    'transita_deficiencia' => $stats['transita_deficiencia']++,
                    'recuperacao' => $stats['recuperacao']++,
                    'reprova' => $stats['reprova']++,
                    'aprovado' => $stats['aprovado']++,
                    default => $stats['pendente']++,
                };
            }
        }

        $stats['comAproveitamento'] = $stats['transita'] + $stats['transita_deficiencia'] + $stats['aprovado'];
        $stats['semAproveitamento'] = $stats['recuperacao'] + $stats['reprova'];

        return $stats;
    }

    /**
     * T1/T2 — MT trimestral + regra genérica de reprovação.
     */
    private function getAproveitamentoTrimestralMT(
        Collection $turmas,
        string $anoLectivo,
        int $anoLectivoId,
        ?int $trimestre,
        float $notaMinima,
        int $maxReprovacoes,
    ): array {
        $alunoIds = $turmas->flatMap(fn ($t) => $t->alunos->pluck('id'));
        $todosAlunos = $turmas->flatMap(fn ($t) => $t->alunos);

        if ($alunoIds->isEmpty()) {
            $vazio = $this->aproveitamentoVazio();
            $vazio['modo'] = 'trimestral_mt';

            return $vazio;
        }

        $notasAprov = $this->fetchNotasMTEfetivas($alunoIds, $anoLectivo, $trimestre);
        $autoDesistentes = $this->pautaService->getAutoDesistenteIds(
            $alunoIds->all(),
            $anoLectivoId,
            $trimestre,
        );

        $stats = $this->aproveitamentoVazio();
        $stats['modo'] = 'trimestral_mt';

        foreach ($todosAlunos as $aluno) {
            $stats['total']++;

            if ($aluno->situacao_academica === Aluno::SITUACAO_DESISTENTE || isset($autoDesistentes[$aluno->id])) {
                $stats['desistentes']++;
                continue;
            }

            $stats['activos']++;
            $notasAluno = $notasAprov->get($aluno->id, collect());

            if ($notasAluno->isEmpty()) {
                $stats['semAproveitamento']++;
                $stats['pendente'] = ($stats['pendente'] ?? 0) + 1;
                continue;
            }

            $reprov = $notasAluno->filter(fn ($n) => (float) $n->valor < $notaMinima)->count();

            if ($reprov <= $maxReprovacoes) {
                $stats['comAproveitamento']++;
            } else {
                $stats['semAproveitamento']++;
            }
        }

        return $stats;
    }

    /**
     * @return 'transita'|'transita_deficiencia'|'recuperacao'|'reprova'|'aprovado'|'pendente'
     */
    public static function classificarResultadoT3(string $resultado): string
    {
        if ($resultado === '') {
            return 'pendente';
        }

        if ($resultado === 'TRANSITA' || $resultado === 'APTO' || $resultado === 'APROVADO') {
            return $resultado === 'APROVADO' ? 'aprovado' : 'transita';
        }

        if (\App\Support\PautaResultadoTransicao::ehTransitaComDeficiencia($resultado)) {
            return 'transita_deficiencia';
        }

        if ($resultado === 'RECURSO') {
            return 'recuperacao';
        }

        if (in_array($resultado, ['N/TRANSITA', 'N/APTO', 'REPROVADO', 'NÃO APTO', 'NAO APTO'], true)) {
            return 'reprova';
        }

        return 'pendente';
    }

    /**
     * Ranking por turma com base na pauta oficial:
     * T1 → MT | T2 → MFT2 (politécnico) ou MT (administração) | T3/anual → CA.
     */
    private function getMelhoresPorTurma(
        Collection $turmas,
        string $anoLectivo,
        int $trimestre,
        float $notaMinima,
        int $top = 5,
    ): Collection {
        return $turmas->map(function (Turma $turma) use ($anoLectivo, $trimestre, $notaMinima, $top) {
            try {
                $pauta = $this->pautaService->gerarPautaTrimestralCompleta($turma->id, $trimestre, $anoLectivo);
                $regime = $this->pautaService->regimeDaTurma($turma);
            } catch (\Throwable) {
                return $this->rankingTurmaVazio($turma, self::metricaRankingLabel($trimestre));
            }

            $tipoMedia = self::metricaRankingLabel($trimestre);

            $ranking = collect($pauta['alunos'] ?? [])
                ->filter(fn (array $aluno) => ! ($aluno['desistente'] ?? false))
                ->map(function (array $aluno) use ($trimestre, $regime, $notaMinima) {
                    $notasDisc = collect($aluno['disciplinas'] ?? [])
                        ->map(fn (array $d) => self::notaRankingDisciplina($d, $trimestre, $regime))
                        ->filter(fn ($v) => $v !== null);

                    $mediaPrecisa = $notasDisc->isNotEmpty() ? (float) $notasDisc->avg() : 0.0;
                    $negativas = $notasDisc->filter(fn ($v) => $v < $notaMinima)->count();

                    return [
                        'aluno' => $aluno['aluno'],
                        'media' => (int) round($mediaPrecisa),
                        'media_precisa' => round($mediaPrecisa, 4),
                        'total_disc' => $notasDisc->count(),
                        'negativas' => $negativas,
                        'resultado' => $aluno['resultado'] ?? '',
                    ];
                })
                ->filter(fn ($e) => $e['total_disc'] > 0 && $e['negativas'] === 0)
                ->sortBy([
                    ['media_precisa', 'desc'],
                    fn ($a, $b) => strcasecmp($a['aluno']->nome ?? '', $b['aluno']->nome ?? ''),
                ])
                ->values()
                ->take($top);

            return [
                'turma' => $turma,
                'ranking' => $ranking,
                'tipo_media' => $tipoMedia,
                'total_elegiveis' => collect($pauta['alunos'] ?? [])
                    ->filter(fn ($a) => ! ($a['desistente'] ?? false))
                    ->count(),
            ];
        });
    }

    public static function metricaRankingLabel(int $trimestre): string
    {
        return match ($trimestre) {
            1 => 'MT',
            2 => 'MFT2',
            default => 'CA',
        };
    }

    /**
     * Nota usada no ranking «melhores alunos» por disciplina.
     *
     * @param  array<string, mixed>  $dadosDisciplina
     */
    public static function notaRankingDisciplina(
        array $dadosDisciplina,
        int $trimestre,
        RegimePautaInterface $regime,
    ): ?float {
        if ($trimestre === 1) {
            return self::mtEfectivaDisciplina($dadosDisciplina);
        }

        if ($trimestre === 2) {
            if ($regime->precisaMFT2()) {
                return self::mft2EfectivaDisciplina($dadosDisciplina);
            }

            return self::mtEfectivaDisciplina($dadosDisciplina);
        }

        return PautaCalculos::caEfectivaDisciplina($dadosDisciplina);
    }

    /**
     * @param  array<string, mixed>  $dadosDisciplina
     */
    private static function mtEfectivaDisciplina(array $dadosDisciplina): ?float
    {
        $mt = $dadosDisciplina['mt'] ?? null;
        if ($mt === null || $mt === '') {
            return null;
        }

        $rec = $dadosDisciplina['recuperacao'] ?? null;

        return RecuperacaoService::mtEfectiva((float) $mt, $rec !== null && $rec !== '' ? (float) $rec : null);
    }

    /**
     * @param  array<string, mixed>  $dadosDisciplina
     */
    private static function mft2EfectivaDisciplina(array $dadosDisciplina): ?float
    {
        $mft2 = $dadosDisciplina['mft2'] ?? null;
        if ($mft2 !== null && $mft2 !== '') {
            return (float) $mft2;
        }

        $mt1 = $dadosDisciplina['mt1'] ?? null;
        $mt2 = $dadosDisciplina['mt'] ?? null;
        if ($mt1 !== null && $mt1 !== '' && $mt2 !== null && $mt2 !== '') {
            return round(((float) $mt1 + (float) $mt2) / 2);
        }

        return null;
    }

    /**
     * @return array{turma: Turma, ranking: Collection, tipo_media: string, total_elegiveis: int}
     */
    private function rankingTurmaVazio(Turma $turma, string $tipoMedia): array
    {
        return [
            'turma' => $turma,
            'ranking' => collect(),
            'tipo_media' => $tipoMedia,
            'total_elegiveis' => $turma->alunos->count(),
        ];
    }

    private function fetchNotasMTEfetivas(Collection $alunoIds, string $anoLectivo, ?int $trimestre): Collection
    {
        $rows = DB::table('notas')
            ->whereIn('aluno_id', $alunoIds)
            ->where('tipo', Nota::TIPO_MT)
            ->where('ano_lectivo', $anoLectivo)
            ->whereNull('archived_at')
            ->when($trimestre !== null, fn ($q) => $q->where('trimestre', $trimestre))
            ->select('aluno_id', 'disciplina_id', 'trimestre', 'valor', 'estado_conselho')
            ->get();

        return $rows
            ->groupBy(fn ($n) => $n->aluno_id.'|'.$n->disciplina_id.'|'.$n->trimestre)
            ->map(fn ($g) => $g->firstWhere('estado_conselho', Nota::ESTADO_POS_CONSELHO) ?? $g->first())
            ->values()
            ->groupBy('aluno_id');
    }

    private function getTopProfessoresComMaisNotasNegativas(
        int $anoLectivoId,
        string $anoLectivo,
        ?int $trimestre,
        float $notaMinima,
        ?int $cursoId,
        ?int $turmaId,
        int $top = 5,
    ): Collection {
        $rows = DB::table('notas')
            ->join('users', 'notas.professor_id', '=', 'users.id')
            ->join('turmas', 'notas.turma_id', '=', 'turmas.id')
            ->where('turmas.ano_lectivo_id', $anoLectivoId)
            ->where('turmas.activo', true)
            ->where('notas.ano_lectivo', $anoLectivo)
            ->whereIn('notas.tipo', [Nota::TIPO_MAC, Nota::TIPO_PP, Nota::TIPO_PT])
            ->whereNotNull('notas.professor_id')
            ->whereNull('notas.archived_at')
            ->when($trimestre !== null, fn ($q) => $q->where('notas.trimestre', $trimestre))
            ->when($cursoId, fn ($q) => $q->where('turmas.curso_id', $cursoId))
            ->when($turmaId, fn ($q) => $q->where('turmas.id', $turmaId))
            ->select(
                'users.id as professor_id',
                'users.name as professor_nome',
                'notas.aluno_id',
                'notas.disciplina_id',
                'notas.trimestre',
                'notas.tipo',
                'notas.valor',
                'notas.estado_conselho',
            )
            ->get();

        // Deduplicar pré/pós-conselho (mesma chave que nas MT)
        $deduped = $rows
            ->groupBy(fn ($n) => "{$n->professor_id}|{$n->aluno_id}|{$n->disciplina_id}|{$n->trimestre}|{$n->tipo}")
            ->map(fn ($g) => $g->firstWhere('estado_conselho', Nota::ESTADO_POS_CONSELHO) ?? $g->first())
            ->values();

        return $deduped
            ->groupBy('professor_id')
            ->map(function ($group) use ($notaMinima) {
                $negativas = $group->filter(fn ($n) => (float) $n->valor < $notaMinima);
                $total = $group->count();
                $totalNeg = $negativas->count();

                return (object) [
                    'professor_id' => $group->first()->professor_id,
                    'professor_nome' => $group->first()->professor_nome,
                    'total_negativas' => $totalNeg,
                    'total_lancamentos' => $total,
                    'media_valor' => $totalNeg > 0 ? $negativas->avg('valor') : null,
                    'pct_negativas' => $total > 0 ? round($totalNeg / $total * 100, 1) : 0,
                ];
            })
            ->filter(fn ($p) => $p->total_negativas > 0)
            ->sortByDesc('total_negativas')
            ->take($top)
            ->values();
    }

    private function aproveitamentoVazio(): array
    {
        return [
            'comAproveitamento' => 0,
            'semAproveitamento' => 0,
            'desistentes' => 0,
            'activos' => 0,
            'total' => 0,
            'transita' => 0,
            'recuperacao' => 0,
            'reprova' => 0,
            'aprovado' => 0,
            'pendente' => 0,
            'modo' => 'trimestral_mt',
        ];
    }
}
