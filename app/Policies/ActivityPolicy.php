<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Activitylog\Models\Activity;

class ActivityPolicy
{
    /**
     * Admin Full e Subdiretor Pedagógico podem visualizar logs de auditoria
     */
    public function viewAny(User $user): bool
    {
        return $user->temVisaoInstitucional()
            || $user->can('auditoria.ver');
    }

    /**
     * Admin Full, Directora Geral e Subdiretor Pedagógico podem ver detalhes de um log
     */
    public function view(User $user, Activity $activity): bool
    {
        return $user->temVisaoInstitucional()
            || $user->can('auditoria.ver');
    }

    /**
     * Ninguém pode criar logs manualmente
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Ninguém pode editar logs
     */
    public function update(User $user, Activity $activity): bool
    {
        return false;
    }

    /**
     * Ninguém pode eliminar logs
     */
    public function delete(User $user, Activity $activity): bool
    {
        return false;
    }
}
