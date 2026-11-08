<?php

namespace App\Policies;

use App\Models\ProfessorDisciplinaTurma;
use App\Models\User;
use App\Policies\Concerns\FiltersCargoByAnoLectivo;

class AssociacaoPolicy
{
    use FiltersCargoByAnoLectivo;

    public function viewAny(User $user): bool
    {
        return $user->temVisaoInstitucional()
            || $user->hasAnyRole(['coordenador_curso', 'director_turma'])
            || $user->can('associacoes.ver');
    }

    public function view(User $user, ProfessorDisciplinaTurma $associacao): bool
    {
        if ($user->temVisaoInstitucional()) {
            return true;
        }

        if ($user->hasRole('coordenador_curso')) {
            $cursosIds = $this->cursosCoordenadorAnoActual($user);
            $associacao->loadMissing('turma');

            if ($associacao->turma && $cursosIds->contains($associacao->turma->curso_id)) {
                return true;
            }
        }

        if ($user->hasRole('director_turma')) {
            if ($this->turmasDirectorAnoActual($user)->contains($associacao->turma_id)) {
                return true;
            }
        }

        if ($associacao->user_id === $user->id) {
            return true;
        }

        return $user->can('associacoes.ver');
    }

    public function create(User $user): bool
    {
        if ($user->temControloAcademicoInstitucional() || $user->hasRole('coordenador_curso')) {
            return true;
        }

        return $user->can('associacoes.gerir');
    }

    public function update(User $user, ProfessorDisciplinaTurma $associacao): bool
    {
        if ($user->temControloAcademicoInstitucional() || $user->hasRole('coordenador_curso')) {
            return true;
        }

        return $user->can('associacoes.gerir');
    }

    public function delete(User $user, ProfessorDisciplinaTurma $associacao): bool
    {
        if ($user->temControloAcademicoInstitucional() || $user->hasRole('coordenador_curso')) {
            return true;
        }

        return $user->can('associacoes.gerir');
    }
}
