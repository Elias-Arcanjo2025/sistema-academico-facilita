<?php

namespace App\Services;

use App\Models\User;
use App\Services\Portal\EncarregadoNotificacaoProactivaService;

/**
 * Dispara notificações proactivas conforme o perfil (sem bloquear a página).
 */
class ProactiveNotificacaoDispatcher
{
    public function __construct(
        private StaffNotificacaoService $staffNotificacao,
        private AlunoNotificacaoProactivaService $alunoNotificacao,
        private EncarregadoNotificacaoProactivaService $encarregadoNotificacao,
    ) {}

    public function aoVisitarArea(User $user): void
    {
        try {
            $roles = $user->getRoleNames();

            if ($roles->contains('aluno')) {
                $this->alunoNotificacao->verificarEnotificar($user);

                return;
            }

            if ($roles->contains('encarregado')) {
                $this->encarregadoNotificacao->verificarEnotificar($user);

                return;
            }

            if ($roles->intersect(['admin_full', 'directora_geral', 'subdiretor_pedagogico', 'secretaria', 'gestor_academico'])->isNotEmpty()) {
                $this->staffNotificacao->verificarEnotificarAdmin();
            }

            if ($roles->intersect(['professor', 'director_turma', 'coordenador_curso'])->isNotEmpty()) {
                $this->staffNotificacao->verificarEnotificarProfessor($user);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
