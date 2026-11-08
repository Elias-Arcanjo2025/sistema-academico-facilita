<?php

namespace App\Policies;

use App\Models\Sala;
use App\Models\User;

class SalaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('salas.ver');
    }

    public function view(User $user, Sala $sala): bool
    {
        if (!$user->can('salas.ver')) {
            return false;
        }

        if ($user->temVisaoInstitucional()) {
            return true;
        }

        // Coordenador: apenas salas usadas pelas turmas do seu curso
        if ($user->hasRole('coordenador_curso')) {
            $cursosIds = $user->cursosComoCoordenador()->where('activo', true)->get()->pluck('id');

            // Verifica se a sala é usada por alguma turma do curso coordenado
            $salaUsadaPeloCurso = $sala->turmas()
                ->whereIn('curso_id', $cursosIds)
                ->exists();

            return $salaUsadaPeloCurso;
        }

        return false; // Negar acesso se nenhuma regra se aplicar
    }

    public function create(User $user): bool
    {
        return $user->can('salas.criar');
    }

    public function update(User $user, Sala $sala): bool
    {
        if (!$user->can('salas.editar')) {
            return false;
        }

        if ($user->temControloAcademicoInstitucional()) {
            return true;
        }

        // Coordenador: apenas salas usadas pelas turmas do seu curso
        if ($user->hasRole('coordenador_curso')) {
            $cursosIds = $user->cursosComoCoordenador()->where('activo', true)->get()->pluck('id');

            $salaUsadaPeloCurso = $sala->turmas()
                ->whereIn('curso_id', $cursosIds)
                ->exists();

            return $salaUsadaPeloCurso;
        }

        return false; // Negar acesso se nenhuma regra se aplicar
    }

    public function delete(User $user, Sala $sala): bool
    {
        // Coordenador NÃO pode eliminar (conforme decisão do usuário)
        if ($user->hasRole('coordenador_curso')) {
            return false;
        }

        if ($sala->turmas()->exists()) {
            return false;
        }

        return $user->can('salas.eliminar');
    }
}
