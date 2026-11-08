<?php

namespace App\Services;

use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\Sala;
use App\Models\Turma;
use App\Models\Turno;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class OcupacaoSalaService
{
    public function resolverAnoLectivoId(?int $anoLectivoId = null): int
    {
        if ($anoLectivoId) {
            return $anoLectivoId;
        }

        return Configuracao::getAnoLectivoActualId()
            ?? AnoLectivo::actual()->value('id')
            ?? AnoLectivo::orderByDesc('nome')->value('id')
            ?? 0;
    }

    public function anosLectivosDisponiveis(): Collection
    {
        $anos = AnoLectivo::query()->orderByDesc('nome')->get();

        if ($anos->isNotEmpty()) {
            return $anos;
        }

        $actualId = Configuracao::getAnoLectivoActualId();
        $actualNome = Configuracao::getAnoLectivoActual() ?? date('Y');

        if ($actualId) {
            return collect([(object) ['id' => $actualId, 'nome' => $actualNome]]);
        }

        return collect();
    }

    public function labelAnoLectivo(int $anoLectivoId): string
    {
        return AnoLectivo::find($anoLectivoId)?->nome
            ?? Configuracao::getAnoLectivoActual()
            ?? (string) $anoLectivoId;
    }

    /**
     * @return array{
     *     salas: LengthAwarePaginator,
     *     turnos: Collection,
     *     anoLectivoId: int,
     *     anoLectivoLabel: string,
     *     anosLectivos: Collection,
     *     turnoId: ?int,
     *     stats: array<string, int|float>,
     *     conflitos: array<int, array<string, mixed>>
     * }
     */
    public function dadosPorSala(int $anoLectivoId, ?int $turnoId, Request $request): array
    {
        $turnos = Turno::activo()->orderBy('nome')->get();

        $turmasAno = Turma::query()
            ->where('activo', true)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->when($turnoId, fn ($q) => $q->where('turno_id', $turnoId))
            ->with(['curso', 'turno', 'classe', 'sala'])
            ->withCount('alunos')
            ->get();

        $salas = Sala::activo()
            ->with(['turmas' => function ($query) use ($anoLectivoId, $turnoId) {
                $query->where('activo', true)
                    ->where('ano_lectivo_id', $anoLectivoId)
                    ->with(['curso', 'turno', 'classe'])
                    ->withCount('alunos')
                    ->orderBy('nome');

                if ($turnoId) {
                    $query->where('turno_id', $turnoId);
                }
            }])
            ->orderBy('nome')
            ->paginate(12)
            ->withQueryString();

        $salas->through(function (Sala $sala) {
            $sala->capacidade_info = $this->capacidadeTurma($sala, $sala->turmas);

            return $sala;
        });

        $turmasAlocadas = $turmasAno->count();
        $totalSalas = Sala::activo()->count();

        $salasOcupadasGlobal = Sala::activo()
            ->whereHas('turmas', function ($query) use ($anoLectivoId, $turnoId) {
                $query->where('activo', true)->where('ano_lectivo_id', $anoLectivoId);
                if ($turnoId) {
                    $query->where('turno_id', $turnoId);
                }
            })
            ->count();

        $stats = [
            'total_salas' => $totalSalas,
            'salas_ocupadas' => $salasOcupadasGlobal,
            'salas_livres' => max(0, $totalSalas - $salasOcupadasGlobal),
            'turmas_alocadas' => $turmasAlocadas,
            'taxa_ocupacao' => $totalSalas > 0
                ? round(($salasOcupadasGlobal / $totalSalas) * 100, 1)
                : 0.0,
        ];

        return [
            'salas' => $salas,
            'turnos' => $turnos,
            'anoLectivoId' => $anoLectivoId,
            'anoLectivoLabel' => $this->labelAnoLectivo($anoLectivoId),
            'anosLectivos' => $this->anosLectivosDisponiveis(),
            'turnoId' => $turnoId,
            'stats' => $stats,
            'conflitos' => $this->detectarConflitos($turmasAno),
        ];
    }

    /**
     * @return array{
     *     turnos: LengthAwarePaginator,
     *     anoLectivoId: int,
     *     anoLectivoLabel: string,
     *     anosLectivos: Collection,
     *     stats: array<string, int|float>
     * }
     */
    public function dadosPorTurno(int $anoLectivoId): array
    {
        $turnos = Turno::activo()
            ->withCount(['turmas' => function ($query) use ($anoLectivoId) {
                $query->where('activo', true)->where('ano_lectivo_id', $anoLectivoId);
            }])
            ->with(['turmas' => function ($query) use ($anoLectivoId) {
                $query->where('activo', true)
                    ->where('ano_lectivo_id', $anoLectivoId)
                    ->with(['curso', 'sala', 'classe'])
                    ->withCount('alunos')
                    ->orderBy('nome');
            }])
            ->orderBy('nome')
            ->paginate(9)
            ->withQueryString();

        $turmasTotal = Turma::query()
            ->where('activo', true)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->count();

        $alunosTotal = Turma::query()
            ->where('activo', true)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->withCount('alunos')
            ->get()
            ->sum('alunos_count');

        return [
            'turnos' => $turnos,
            'anoLectivoId' => $anoLectivoId,
            'anoLectivoLabel' => $this->labelAnoLectivo($anoLectivoId),
            'anosLectivos' => $this->anosLectivosDisponiveis(),
            'stats' => [
                'turnos_activos' => Turno::activo()->count(),
                'turmas_total' => $turmasTotal,
                'alunos_total' => $alunosTotal,
            ],
        ];
    }

    /**
     * Mesma sala + mesmo turno com várias turmas = conflito de horário potencial.
     *
     * @param  Collection<int, Turma>  $turmas
     * @return array<int, array<string, mixed>>
     */
    protected function detectarConflitos(Collection $turmas): array
    {
        $conflitos = [];

        $turmas->filter(fn (Turma $t) => $t->sala_id)
            ->groupBy(fn (Turma $t) => $t->sala_id.'-'.$t->turno_id)
            ->each(function (Collection $grupo, string $chave) use (&$conflitos) {
                if ($grupo->count() < 2) {
                    return;
                }

                $primeira = $grupo->first();
                $conflitos[] = [
                    'sala' => $primeira->sala?->nome ?? 'Sala #'.$primeira->sala_id,
                    'turno' => $primeira->turno?->nome ?? '-',
                    'turmas' => $grupo->pluck('nome')->all(),
                    'total' => $grupo->count(),
                ];
            });

        return $conflitos;
    }

    public function capacidadeTurma(Sala $sala, Collection $turmas): ?array
    {
        if (! $sala->capacidade) {
            return null;
        }

        $alunos = $turmas->sum(fn ($t) => (int) ($t->alunos_count ?? 0));
        $percent = min(100, round(($alunos / $sala->capacidade) * 100, 1));

        return [
            'alunos' => $alunos,
            'capacidade' => (int) $sala->capacidade,
            'percent' => $percent,
            'excede' => $alunos > $sala->capacidade,
        ];
    }
}
