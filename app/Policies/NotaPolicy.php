<?php

namespace App\Policies;

use App\Models\Nota;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\Turma;
use App\Models\User;
use App\Policies\Concerns\FiltersCargoByAnoLectivo;
use App\Support\NotaLancamentoRules;

class NotaPolicy
{
    use FiltersCargoByAnoLectivo;
    public function viewAny(User $user): bool
    {
        return $user->can('notas.ver');
    }

    public function view(User $user, Nota $nota): bool
    {
        if (!$user->can('notas.ver')) {
            return false;
        }

        if ($user->temVisaoInstitucional()) {
            return true;
        }

        // Professor ve notas que lancou
        if ($nota->professor_id === $user->id) {
            return true;
        }

        // Director de Turma ve notas da sua turma (ano lectivo actual)
        if ($user->hasRole('director_turma')) {
            if ($this->turmasDirectorAnoActual($user)->contains($nota->turma_id)) {
                return true;
            }
        }

        // Coordenador ve notas do seu curso (ano lectivo actual)
        if ($user->hasRole('coordenador_curso')) {
            $cursosIds = $this->cursosCoordenadorAnoActual($user);
            if ($nota->turma && $cursosIds->contains($nota->turma->curso_id)) {
                return true;
            }
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->can('notas.lancar');
    }

    public function update(User $user, Nota $nota): bool
    {
        if (!$user->can('notas.editar')) {
            return false;
        }

        // Controlo académico institucional pode editar todas
        if ($user->temControloAcademicoInstitucional()) {
            return true;
        }

        // Director de Turma pode editar notas das suas turmas (se tiver notas.editar)
        if ($user->hasRole('director_turma') && $user->can('notas.editar')) {
            if ($this->turmasDirectorAnoActual($user)->contains($nota->turma_id)) {
                return true;
            }
        }

        if ($user->hasRole('coordenador_curso')) {
            $cursosIds = $this->cursosCoordenadorAnoActual($user);

            if ($nota->turma && $cursosIds->contains($nota->turma->curso_id)) {
                return true;
            }
        }

        // Professor so pode editar notas que lancou e na sua associacao activa
        if ($nota->professor_id === $user->id) {
            return $this->professorTemAssociacaoActiva($user, $nota->disciplina_id, $nota->turma_id);
        }

        return false;
    }

    public function delete(User $user, Nota $nota): bool
    {
        if (!$user->can('notas.eliminar')) {
            return false;
        }

        return $user->temControloAcademicoInstitucional();
    }

    /**
     * Verifica se o professor pode lancar notas para uma disciplina/turma especifica
     */
    public function lancar(User $user, int $disciplinaId, int $turmaId): bool
    {
        if (! $user->can('notas.lancar')) {
            return false;
        }

        return NotaLancamentoRules::utilizadorPodeLancarNaTurmaDisciplina($user, $turmaId, $disciplinaId);
    }

    /**
     * Verifica se o utilizador pode ver as notas de uma turma
     */
    public function viewTurma(User $user, int $turmaId): bool
    {
        if (!$user->can('notas.ver')) {
            return false;
        }

        if ($user->temVisaoInstitucional()) {
            return true;
        }

        // Director de Turma
        if ($user->hasRole('director_turma')) {
            if ($this->turmasDirectorAnoActual($user)->contains($turmaId)) {
                return true;
            }
        }

        if ($user->hasRole('coordenador_curso')) {
            $turma = Turma::find($turmaId);
            if ($turma) {
                $cursosIds = $this->cursosCoordenadorAnoActual($user);
                if ($cursosIds->contains($turma->curso_id)) {
                    return true;
                }
            }
        }

        if ($user->hasRole('coordenador_disciplina')) {
            $turmaIdsCoord = app(\App\Services\Pedagogico\DisciplinaCoordEscopoService::class)
                ->turmaIdsPorClasses($user);
            if ($turmaIdsCoord->contains($turmaId)) {
                return true;
            }
        }

        $turmasIds = $this->turmasProfessorAnoActual($user);
        return $turmasIds->contains($turmaId);
    }

    private function professorTemAssociacaoActiva(User $user, int $disciplinaId, int $turmaId): bool
    {
        $anoLectivoId = \App\Models\Configuracao::getAnoLectivoActualId();

        return ProfessorDisciplinaTurma::where('user_id', $user->id)
            ->where('disciplina_id', $disciplinaId)
            ->where('turma_id', $turmaId)
            ->where('activo', true)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->exists();
    }
}
