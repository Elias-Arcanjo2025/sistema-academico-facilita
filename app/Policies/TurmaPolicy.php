<?php

namespace App\Policies;

use App\Models\Turma;
use App\Models\User;
use App\Policies\Concerns\FiltersCargoByAnoLectivo;

class TurmaPolicy
{
    use FiltersCargoByAnoLectivo;
    public function viewAny(User $user): bool
    {
        return $user->can('turmas.ver');
    }

    public function view(User $user, Turma $turma): bool
    {
        return $user->can('turmas.ver');
    }

    public function create(User $user): bool
    {
        return $user->can('turmas.criar');
    }

    public function update(User $user, Turma $turma): bool
    {
        if (!$user->can('turmas.editar')) {
            return false;
        }

        // Controlo académico institucional: pode editar todas
        if ($user->temControloAcademicoInstitucional()) {
            return true;
        }

        // Director de Turma: pode editar APENAS a turma que dirige
        if ($user->hasRole('director_turma')) {
            return $this->turmasDirectorAnoActual($user)->contains($turma->id);
        }

        if ($user->hasRole('coordenador_curso')) {
            return $this->cursosCoordenadorAnoActual($user)->contains($turma->curso_id);
        }

        return false; // Negar acesso se nenhuma regra se aplicar
    }

    public function delete(User $user, Turma $turma): bool
    {
        // Director de Turma NÃO pode eliminar turmas
        if ($user->hasRole('director_turma')) {
            return false;
        }

        // Coordenador NÃO pode eliminar (conforme decisão do usuário)
        if ($user->hasRole('coordenador_curso')) {
            return false;
        }

        if ($turma->alunos()->exists()) {
            return false;
        }

        return $user->can('turmas.eliminar');
    }
}
