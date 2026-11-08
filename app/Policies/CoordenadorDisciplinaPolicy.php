<?php

namespace App\Policies;

use App\Models\CoordenadorDisciplina;
use App\Models\User;

class CoordenadorDisciplinaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->temVisaoInstitucional();
    }

    public function view(User $user, CoordenadorDisciplina $coordenadorDisciplina): bool
    {
        return $user->temVisaoInstitucional();
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin_full', 'directora_geral']);
    }

    public function update(User $user, CoordenadorDisciplina $coordenadorDisciplina): bool
    {
        return $user->hasAnyRole(['admin_full', 'directora_geral']);
    }

    public function delete(User $user, CoordenadorDisciplina $coordenadorDisciplina): bool
    {
        return $user->hasAnyRole(['admin_full', 'directora_geral']);
    }
}
