<?php

namespace App\Policies;

use App\Models\CoordenadorCurso;
use App\Models\User;

class CoordenadorCursoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->temVisaoInstitucional();
    }

    public function view(User $user, CoordenadorCurso $coordenadorCurso): bool
    {
        return $user->temVisaoInstitucional();
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin_full', 'directora_geral']);
    }

    public function update(User $user, CoordenadorCurso $coordenadorCurso): bool
    {
        return $user->hasAnyRole(['admin_full', 'directora_geral']);
    }

    public function delete(User $user, CoordenadorCurso $coordenadorCurso): bool
    {
        return $user->hasAnyRole(['admin_full', 'directora_geral']);
    }
}
