<?php

namespace App\Policies;

use App\Models\PautaArquivo;
use App\Models\User;

class PautaArquivoPolicy
{
    /**
     * Determina se o utilizador pode ver a listagem de arquivos
     */
    public function viewAny(User $user): bool
    {
        return $user->can('pauta_arquivos.ver');
    }

    /**
     * Determina se o utilizador pode ver um arquivo específico
     */
    public function view(User $user, PautaArquivo $arquivo): bool
    {
        // Visão / controlo institucional (admin, directora, gestor, DP)
        if ($user->temVisaoInstitucional() || $user->temControloAcademicoInstitucional()) {
            return true;
        }

        // Director de turma pode ver arquivos da sua turma
        if ($user->hasRole('director_turma')) {
            if ($arquivo->turma_id) {
                return $user->turmasComoDirector()
                    ->where('turmas.id', $arquivo->turma_id)
                    ->where('director_turma.activo', true)
                    ->exists();
            }
        }

        // Coordenador de curso pode ver arquivos do seu curso
        if ($user->hasRole('coordenador_curso')) {
            return $user->cursosComoCoordenador()
                ->where('cursos.id', $arquivo->curso_id)
                ->where('coordenador_curso.activo', true)
                ->exists();
        }

        // Professor pode ver se leciona na turma (se houver turma associada)
        if ($arquivo->turma_id) {
            return $user->turmasComoProfessor()
                ->where('turmas.id', $arquivo->turma_id)
                ->where('professor_disciplina_turma.activo', true)
                ->exists();
        }

        // Se não há turma, verificar se leciona no curso
        return $user->turmasComoProfessor()
            ->where('turmas.curso_id', $arquivo->curso_id)
            ->where('professor_disciplina_turma.activo', true)
            ->exists();
    }

    /**
     * Determina se o utilizador pode criar um novo arquivo
     */
    public function create(User $user): bool
    {
        return $user->can('pauta_arquivos.criar')
            || $user->hasRole('admin_full');
    }

    /**
     * Determina se o utilizador pode editar um arquivo
     */
    public function update(User $user, PautaArquivo $arquivo): bool
    {
        // Admin full pode editar qualquer arquivo
        if ($user->hasRole('admin_full')) {
            return true;
        }

        // Apenas quem fez upload pode editar (apenas metadata)
        if ($arquivo->uploaded_by === $user->id) {
            return $user->can('pauta_arquivos.editar');
        }

        return false;
    }

    /**
     * Determina se o utilizador pode eliminar um arquivo (soft delete)
     */
    public function delete(User $user, PautaArquivo $arquivo): bool
    {
        // Admin full pode eliminar qualquer arquivo
        if ($user->hasRole('admin_full')) {
            return true;
        }

        // Quem fez upload pode eliminar nas primeiras 24 horas
        if ($arquivo->uploaded_by === $user->id) {
            if ($arquivo->created_at->diffInHours(now()) < 24) {
                return $user->can('pauta_arquivos.eliminar');
            }
        }

        return false;
    }

    /**
     * Determina se o utilizador pode fazer force delete (hard delete)
     */
    public function forceDelete(User $user, PautaArquivo $arquivo): bool
    {
        // Apenas admin full pode fazer hard delete
        return $user->hasRole('admin_full')
            && $user->can('pauta_arquivos.force_delete');
    }

    /**
     * Determina se o utilizador pode fazer download de um arquivo
     */
    public function download(User $user, PautaArquivo $arquivo): bool
    {
        // Mesmo critério do método view()
        return $this->view($user, $arquivo);
    }

    /**
     * Determina se o utilizador pode restaurar um arquivo soft deleted
     */
    public function restore(User $user, PautaArquivo $arquivo): bool
    {
        // Apenas admin full pode restaurar
        return $user->hasRole('admin_full');
    }
}
