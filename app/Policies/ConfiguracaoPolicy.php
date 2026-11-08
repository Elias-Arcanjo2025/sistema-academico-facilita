<?php

namespace App\Policies;

use App\Models\User;

class ConfiguracaoPolicy
{
    /**
     * Admin Full e Subdiretor Pedagógico podem visualizar configurações
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin_full', 'subdiretor_pedagogico'])
            || $user->can('configuracoes.ver');
    }

    /**
     * Admin Full e Subdiretor Pedagógico podem ver detalhes de configuração
     */
    public function view(User $user): bool
    {
        return $user->hasAnyRole(['admin_full', 'subdiretor_pedagogico'])
            || $user->can('configuracoes.ver');
    }

    /**
     * Apenas Admin Full pode gerir (editar/criar/eliminar) configuracoes
     */
    public function gerirConfiguracoes(User $user): bool
    {
        return $user->hasRole('admin_full');
    }

    public function autorizarPortalNotas(User $user): bool
    {
        return $user->hasRole('admin_full') || $user->can('notas.publicar');
    }
}
