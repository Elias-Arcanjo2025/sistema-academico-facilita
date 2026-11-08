<?php

namespace App\Services;

use App\Models\ProfessorDisciplinaTurma;
use App\Models\Turma;
use App\Models\TurmaHorario;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CalendarioProvaSupervisorService
{
    /**
     * @param  Collection<int, array<string, mixed>>  $provas
     * @return Collection<int, array<string, mixed>>
     */
    public function atribuirSupervisores(Collection $provas, int $anoLectivoId): Collection
    {
        if ($provas->isEmpty()) {
            return $provas;
        }

        $turmaIds = $provas->pluck('turma_id')->unique()->filter()->values();
        $turmas = Turma::with(['turno', 'curso'])
            ->whereIn('id', $turmaIds)
            ->get()
            ->keyBy('id');

        $todasAssociacoes = ProfessorDisciplinaTurma::query()
            ->with(['turma', 'professor'])
            ->where('ano_lectivo_id', $anoLectivoId)
            ->where('activo', true)
            ->get();

        $professoresPorTurma = $todasAssociacoes
            ->groupBy('turma_id')
            ->map(fn ($items) => $items->pluck('user_id')->unique());

        /** Docente da disciplina X na turma Y (associação exacta). */
        $professorDisciplinaTurma = $todasAssociacoes
            ->groupBy(fn ($a) => $a->turma_id.'-'.$a->disciplina_id)
            ->map(fn ($items) => $items->pluck('user_id')->unique());

        $cursosPorProfessor = $todasAssociacoes
            ->groupBy('user_id')
            ->map(fn ($items) => $items->pluck('turma.curso_id')->filter()->unique());

        $presencaPorProfessor = $this->montarMapaPresencaHorario($anoLectivoId);
        $professorIdsComHorario = collect(array_keys($presencaPorProfessor));

        $supervisorDiaTurno = [];
        $supervisorDiaGlobal = [];

        return $provas->map(function (array $prova) use (
            $turmas,
            $professoresPorTurma,
            $professorDisciplinaTurma,
            $cursosPorProfessor,
            $presencaPorProfessor,
            $professorIdsComHorario,
            &$supervisorDiaTurno,
            &$supervisorDiaGlobal,
        ) {
            if (! empty($prova['supervisor_nome'])) {
                return $prova;
            }

            $turmaId = (int) ($prova['turma_id'] ?? 0);
            $turma = $turmas->get($turmaId);
            if (! $turma) {
                return $prova;
            }

            $data = substr((string) ($prova['data_prova'] ?? ''), 0, 10);
            $slot = (int) ($prova['slot'] ?? 1);
            $disciplinaId = (int) ($prova['disciplina_id'] ?? 0);
            $diaSemana = Carbon::parse($data)->dayOfWeekIso;
            $turnoId = $turma->turnoIdResolvido();

            $excluidos = $this->professoresExcluidos(
                $turmaId,
                $disciplinaId,
                $professoresPorTurma,
                $professorDisciplinaTurma,
            );

            $candidatos = $this->candidatosSupervisores(
                $turma,
                $excluidos,
                $cursosPorProfessor,
                $presencaPorProfessor,
                $professorIdsComHorario,
                $diaSemana,
                $turnoId,
            );

            $supervisor = $this->escolherSupervisor(
                $candidatos,
                $data,
                $slot,
                $turmaId,
                $supervisorDiaTurno,
                $supervisorDiaGlobal,
            );

            if ($supervisor) {
                $prova['supervisor_nome'] = $supervisor->name;
                $prova['supervisor_id'] = $supervisor->id;

                $supervisorDiaTurno["{$turmaId}-{$data}-{$slot}"] = $supervisor->id;
                $supervisorDiaGlobal["{$supervisor->id}-{$data}"] = true;
            }

            return $prova;
        });
    }

    /**
     * Professores que nunca podem supervisionar esta prova.
     *
     * Regras (por turma):
     * - Quem tem qualquer associação activa na turma da prova (ex.: Patrício em TI11AM → só SEAC, mas fica excluído de toda a turma).
     * - Quem lecciona exactamente esta disciplina nesta turma.
     *
     * Nota: leccionar SEAC em TI12AM não impede supervisionar outras turmas/disciplinas.
     *
     * @param  Collection<int, Collection<int, int>>  $professoresPorTurma
     * @param  Collection<int, Collection<int, int>>  $professorDisciplinaTurma
     * @return Collection<int, int>
     */
    protected function professoresExcluidos(
        int $turmaId,
        int $disciplinaId,
        Collection $professoresPorTurma,
        Collection $professorDisciplinaTurma,
    ): Collection {
        $excluidos = collect();

        $excluidos = $excluidos->merge($professoresPorTurma->get($turmaId, collect()));
        $excluidos = $excluidos->merge($professorDisciplinaTurma->get("{$turmaId}-{$disciplinaId}", collect()));

        return $excluidos->unique()->filter()->values();
    }

    /**
     * @return array<int, array{turnos: array<int, array<int, int>>, geral: array<int, int>}>
     */
    protected function montarMapaPresencaHorario(int $anoLectivoId): array
    {
        $horarios = TurmaHorario::query()
            ->with(['turma.turno'])
            ->where('ano_lectivo_id', $anoLectivoId)
            ->get();

        $mapa = [];

        foreach ($horarios as $horario) {
            $profId = (int) $horario->professor_id;
            if (! $profId) {
                continue;
            }

            $dia = (int) $horario->dia_semana;
            $turma = $horario->turma;
            $turnoId = $turma ? ($turma->turnoIdResolvido() ?? $turma->turno_id) : null;

            $mapa[$profId]['geral'][$dia] = $dia;

            if ($turnoId) {
                $mapa[$profId]['turnos'][(int) $turnoId][$dia] = $dia;
            }
        }

        return $mapa;
    }

    /**
     * @param  Collection<int, int>  $excluidos
     * @param  Collection<int, Collection<int, int>>  $cursosPorProfessor
     * @param  array<int, array{turnos: array<int, array<int, int>>, geral: array<int, int>}>  $presencaPorProfessor
     * @param  Collection<int, int>  $professorIdsComHorario
     * @return Collection<int, User>
     */
    protected function candidatosSupervisores(
        Turma $turma,
        Collection $excluidos,
        Collection $cursosPorProfessor,
        array $presencaPorProfessor,
        Collection $professorIdsComHorario,
        int $diaSemana,
        ?int $turnoId,
    ): Collection {
        $excluidosFlip = $excluidos->flip();
        $cursoTurma = (int) $turma->curso_id;

        $idsElegiveis = $professorIdsComHorario->filter(function ($userId) use (
            $excluidosFlip,
            $presencaPorProfessor,
            $diaSemana,
            $turnoId,
        ) {
            $userId = (int) $userId;

            if ($excluidosFlip->has($userId)) {
                return false;
            }

            return $this->professorPresenteNoDia($presencaPorProfessor, $userId, $diaSemana, $turnoId);
        });

        if ($idsElegiveis->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $idsElegiveis)
            ->get()
            ->sortBy(function (User $user) use ($cursosPorProfessor, $cursoTurma) {
                $cursos = $cursosPorProfessor->get($user->id, collect())
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->unique();

                // 0 = lecciona só noutros cursos (prioridade)
                // 1 = sem associações ou misto
                // 2 = mesmo curso, outra turma (permitido se não associado à turma da prova)
                $prioridade = match (true) {
                    $cursos->isNotEmpty() && ! $cursos->contains($cursoTurma) => 0,
                    $cursos->contains($cursoTurma) => 2,
                    default => 1,
                };

                return [$prioridade, $user->name];
            })
            ->values();
    }

    /**
     * @param  array<int, array{turnos: array<int, array<int, int>>, geral: array<int, int>}>  $presencaPorProfessor
     */
    protected function professorPresenteNoDia(array $presencaPorProfessor, int $userId, int $diaSemana, ?int $turnoId): bool
    {
        $presenca = $presencaPorProfessor[$userId] ?? null;

        if (! $presenca) {
            return false;
        }

        if ($turnoId) {
            return isset($presenca['turnos'][$turnoId][$diaSemana]);
        }

        return isset($presenca['geral'][$diaSemana]);
    }

    /**
     * @param  Collection<int, User>  $candidatos
     */
    protected function escolherSupervisor(
        Collection $candidatos,
        string $data,
        int $slot,
        int $turmaId,
        array $supervisorDiaTurno,
        array $supervisorDiaGlobal,
    ): ?User {
        foreach ($candidatos as $candidato) {
            $chaveTurma = "{$turmaId}-{$data}-{$slot}";
            $chaveGlobal = "{$candidato->id}-{$data}";

            if (isset($supervisorDiaTurno[$chaveTurma])) {
                continue;
            }

            if (isset($supervisorDiaGlobal[$chaveGlobal])) {
                continue;
            }

            return $candidato;
        }

        return null;
    }
}
