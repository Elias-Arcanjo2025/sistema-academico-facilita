<?php

namespace App\Policies;

use App\Models\Configuracao;
use App\Models\DirectorTurma;
use App\Models\User;

class DirectorTurmaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin_full', 'directora_geral', 'coordenador_curso', 'subdiretor_pedagogico']);
    }

    public function view(User $user, DirectorTurma $directorTurma): bool
    {
        if ($user->temVisaoInstitucional()) {
            return true;
        }

        // Coordenador pode ver se for diretor de turma do seu curso
        if ($user->hasRole('coordenador_curso')) {
            $anoLectivoId = $directorTurma->turma->ano_lectivo_id ?? Configuracao::getAnoLectivoActualId();

            $cursosIds = $user->cursosComoCoordenador()
                ->wherePivot('activo', true)
                ->wherePivot('ano_lectivo_id', $anoLectivoId)
                ->pluck('cursos.id');

            return $cursosIds->contains($directorTurma->turma->curso_id);
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin_full', 'directora_geral']);
    }

    public function update(User $user, DirectorTurma $directorTurma): bool
    {
        return $user->hasAnyRole(['admin_full', 'directora_geral']);
    }

    public function delete(User $user, DirectorTurma $directorTurma): bool
    {
        return $user->hasAnyRole(['admin_full', 'directora_geral']);
    }
}
