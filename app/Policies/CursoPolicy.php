<?php

namespace App\Policies;

use App\Models\Curso;
use App\Models\User;

class CursoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('cursos.ver');
    }

    public function view(User $user, Curso $curso): bool
    {
        return $user->can('cursos.ver');
    }

    public function create(User $user): bool
    {
        return $user->can('cursos.criar');
    }

    public function update(User $user, Curso $curso): bool
    {
        return $user->can('cursos.editar');
    }

    public function delete(User $user, Curso $curso): bool
    {
        if ($curso->turmas()->exists()) {
            return false;
        }
        return $user->can('cursos.eliminar');
    }
}
