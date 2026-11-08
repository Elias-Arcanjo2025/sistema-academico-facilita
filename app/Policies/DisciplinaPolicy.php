<?php

namespace App\Policies;

use App\Models\Disciplina;
use App\Models\User;

class DisciplinaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('disciplinas.ver');
    }

    public function view(User $user, Disciplina $disciplina): bool
    {
        if ($user->can('disciplinas.ver')) {
            if ($user->hasRole('coordenador_curso')) {
                $cursosDisciplina = $disciplina->cursos->pluck('id')->toArray();
                return $user->cursosComoCoordenador()
                    ->whereIn('cursos.id', $cursosDisciplina)
                    ->exists();
            }
            return true;
        }
        return false;
    }

    public function create(User $user): bool
    {
        return $user->can('disciplinas.criar');
    }

    public function update(User $user, Disciplina $disciplina): bool
    {
        if ($user->can('disciplinas.editar')) {
            if ($user->hasRole('coordenador_curso') && !$user->hasRole('admin_full')) {
                $cursosDisciplina = $disciplina->cursos->pluck('id')->toArray();
                return $user->cursosComoCoordenador()
                    ->whereIn('cursos.id', $cursosDisciplina)
                    ->exists();
            }
            return true;
        }
        return false;
    }

    public function delete(User $user, Disciplina $disciplina): bool
    {
        if ($disciplina->notas()->exists()) {
            return false;
        }
        return $user->can('disciplinas.eliminar');
    }
}
