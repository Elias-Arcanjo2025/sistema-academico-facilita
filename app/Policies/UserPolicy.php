<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('utilizadores.ver');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('utilizadores.ver');
    }

    public function create(User $user): bool
    {
        return $user->can('utilizadores.criar');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('utilizadores.editar');
    }

    public function delete(User $user, User $model): bool
    {
        if (! $user->can('utilizadores.eliminar')) {
            return false;
        }

        return $user->hasRole('admin_full') && ! $model->hasRole('admin_full');
    }

    public function aprovar(User $user, User $model): bool
    {
        return $user->can('utilizadores.aprovar') && $model->estado === 'pendente';
    }

    public function rejeitar(User $user, User $model): bool
    {
        return $user->can('utilizadores.aprovar') && $model->estado === 'pendente';
    }

    public function gerirCargos(User $user, User $model): bool
    {
        return $user->can('utilizadores.gerir_cargos') && ! $model->hasRole('admin_full');
    }

    /** Compatível com ConfiguracaoPolicy — página pública e configurações. */
    public function gerirConfiguracoes(User $user): bool
    {
        return $user->hasRole('admin_full');
    }

    /** Autorizar publicação global de notas no portal do aluno. */
    public function autorizarPortalNotas(User $user): bool
    {
        return $user->hasRole('admin_full') || $user->can('notas.publicar');
    }

    public function viewAnyConfiguracao(User $user): bool
    {
        return $user->hasAnyRole(['admin_full', 'subdiretor_pedagogico'])
            || $user->can('configuracoes.ver')
            || $user->can('notas.publicar');
    }
}
