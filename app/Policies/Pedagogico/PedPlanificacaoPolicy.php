<?php

namespace App\Policies\Pedagogico;

use App\Models\Pedagogico\PedPlanificacao;
use App\Models\User;
use App\Services\Pedagogico\PedagogicoAccess;

class PedPlanificacaoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pedagogico.ver');
    }

    public function view(User $user, PedPlanificacao $planificacao): bool
    {
        return PedagogicoAccess::temAcessoPlanificacao($user, $planificacao);
    }

    public function create(User $user): bool
    {
        return $user->can('pedagogico.planificacao.criar');
    }

    public function update(User $user, PedPlanificacao $planificacao): bool
    {
        return PedagogicoAccess::podeEditarPlanificacao($user, $planificacao);
    }

    public function delete(User $user, PedPlanificacao $planificacao): bool
    {
        return PedagogicoAccess::podeEditarPlanificacao($user, $planificacao);
    }
}
