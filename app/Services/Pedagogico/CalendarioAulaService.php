<?php

namespace App\Services\Pedagogico;

use App\Models\Pedagogico\PedAula;
use App\Models\Pedagogico\PedPlanificacao;
use App\Models\Pedagogico\PedQuinzenaDia;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CalendarioAulaService
{
    public function gerarCalendario(PedPlanificacao $planificacao): Collection
    {
        $planificacao->load(['horariosSemanais', 'quinzenas.dias']);

        $diasQuinzena = $planificacao->quinzenas
            ->flatMap(fn ($q) => $q->dias)
            ->sortBy('data');

        if ($diasQuinzena->isNotEmpty()) {
            return $this->gerarDesdeDiasQuinzena($planificacao, $diasQuinzena);
        }

        if ($planificacao->horariosSemanais->isEmpty()) {
            throw new \Exception('Defina os dias da quinzena (data, tema e tempos) ou horários semanais.');
        }

        return $this->gerarDesdeHorarioSemanal($planificacao);
    }

    /**
     * @param  Collection<int, PedQuinzenaDia>  $diasQuinzena
     */
    protected function gerarDesdeDiasQuinzena(PedPlanificacao $planificacao, Collection $diasQuinzena): Collection
    {
        $horariosPorDia = $planificacao->horariosSemanais->groupBy('dia_semana');

        return DB::transaction(function () use ($planificacao, $diasQuinzena, $horariosPorDia) {
            PedAula::where('planificacao_id', $planificacao->id)
                ->where('estado', PedAula::ESTADO_PLANEADA)
                ->delete();

            $aulasCriadas = collect();
            $ordem = (int) (PedAula::where('planificacao_id', $planificacao->id)->max('ordem') ?? 0);

            foreach ($diasQuinzena as $dia) {
                $ordem++;
                $data = Carbon::parse($dia->data);
                $horaInicio = $this->resolverHoraInicio($dia, $data, $horariosPorDia);
                $tempos = max(1, (int) $dia->tempos);
                $horaFim = TempoAulaHelper::calcularHoraFim($horaInicio, $tempos);

                $aula = PedAula::create([
                    'planificacao_id' => $planificacao->id,
                    'quinzena_id' => $dia->quinzena_id,
                    'data' => $data->toDateString(),
                    'hora_inicio' => $horaInicio,
                    'hora_fim' => $horaFim,
                    'tempos' => $tempos,
                    'tema_planeado' => $dia->tema,
                    'objetivos_geral' => $dia->objetivos_geral,
                    'objetivos_especificos' => $dia->objetivos_especificos,
                    'estado' => PedAula::ESTADO_PLANEADA,
                    'ordem' => $ordem,
                ]);

                $aulasCriadas->push($aula);
            }

            return $aulasCriadas;
        });
    }

    protected function gerarDesdeHorarioSemanal(PedPlanificacao $planificacao): Collection
    {
        $horariosPorDia = $planificacao->horariosSemanais->groupBy('dia_semana');
        $quinzenas = $planificacao->quinzenas->sortBy('numero');

        return DB::transaction(function () use ($planificacao, $horariosPorDia, $quinzenas) {
            PedAula::where('planificacao_id', $planificacao->id)
                ->where('estado', PedAula::ESTADO_PLANEADA)
                ->delete();

            $aulasCriadas = collect();
            $ordem = (int) (PedAula::where('planificacao_id', $planificacao->id)->max('ordem') ?? 0);
            $data = Carbon::parse($planificacao->data_inicial)->startOfDay();
            $dataFinal = Carbon::parse($planificacao->data_final)->startOfDay();

            while ($data->lte($dataFinal)) {
                $diaSemana = $data->dayOfWeekIso;

                if ($horariosPorDia->has($diaSemana)) {
                    foreach ($horariosPorDia->get($diaSemana) as $horario) {
                        $ordem++;
                        $quinzena = $this->resolverQuinzena($quinzenas, $data);
                        $inicio = substr((string) $horario->hora_inicio, 0, 5);
                        $tempos = $this->calcularTemposDeHorario($inicio, substr((string) $horario->hora_fim, 0, 5));

                        $aula = PedAula::create([
                            'planificacao_id' => $planificacao->id,
                            'quinzena_id' => $quinzena?->id,
                            'data' => $data->toDateString(),
                            'hora_inicio' => $horario->hora_inicio,
                            'hora_fim' => $horario->hora_fim,
                            'tempos' => $tempos,
                            'tema_planeado' => $quinzena?->tema,
                            'objetivos_geral' => $quinzena?->objetivos_geral,
                            'objetivos_especificos' => $quinzena?->objetivos_especificos,
                            'estado' => PedAula::ESTADO_PLANEADA,
                            'ordem' => $ordem,
                        ]);

                        $aulasCriadas->push($aula);
                    }
                }

                $data->addDay();
            }

            return $aulasCriadas;
        });
    }

    protected function resolverHoraInicio(PedQuinzenaDia $dia, Carbon $data, Collection $horariosPorDia): string
    {
        if ($dia->hora_inicio) {
            return substr((string) $dia->hora_inicio, 0, 5).':00';
        }

        $horario = $horariosPorDia->get($data->dayOfWeekIso)?->first();
        if ($horario) {
            return substr((string) $horario->hora_inicio, 0, 5).':00';
        }

        return '08:00:00';
    }

    protected function calcularTemposDeHorario(string $inicio, string $fim): int
    {
        [$h1, $m1] = array_map('intval', explode(':', $inicio));
        [$h2, $m2] = array_map('intval', explode(':', $fim));
        $diff = max(45, ($h2 * 60 + $m2) - ($h1 * 60 + $m1));

        return max(1, (int) round($diff / TempoAulaHelper::minutosPorTempo()));
    }

    protected function resolverQuinzena(Collection $quinzenas, Carbon $data): ?object
    {
        return $quinzenas->first(function ($quinzena) use ($data) {
            $inicio = Carbon::parse($quinzena->data_inicial)->startOfDay();
            $fim = Carbon::parse($quinzena->data_final)->endOfDay();

            return $data->between($inicio, $fim);
        });
    }
}
