<?php

namespace App\Policies;

use App\Models\Turno;
use App\Models\User;

class TurnoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('turnos.ver');
    }

    public function view(User $user, Turno $turno): bool
    {
        if (!$user->can('turnos.ver')) {
            return false;
        }

        if ($user->temVisaoInstitucional()) {
            return true;
        }

        // Coordenador: apenas turnos usados pelas turmas do seu curso
        if ($user->hasRole('coordenador_curso')) {
            $cursosIds = $user->cursosComoCoordenador()->where('activo', true)->get()->pluck('id');

            // Verifica se o turno é usado por alguma turma do curso coordenado
            $turnoUsadoPeloCurso = $turno->turmas()
                ->whereIn('curso_id', $cursosIds)
                ->exists();

            return $turnoUsadoPeloCurso;
        }

        return false; // Negar acesso se nenhuma regra se aplicar
    }

    public function create(User $user): bool
    {
        return $user->can('turnos.criar');
    }

    public function update(User $user, Turno $turno): bool
    {
        if (!$user->can('turnos.editar')) {
            return false;
        }

        if ($user->temControloAcademicoInstitucional()) {
            return true;
        }

        // Coordenador: apenas turnos usados pelas turmas do seu curso
        if ($user->hasRole('coordenador_curso')) {
            $cursosIds = $user->cursosComoCoordenador()->where('activo', true)->get()->pluck('id');

            $turnoUsadoPeloCurso = $turno->turmas()
                ->whereIn('curso_id', $cursosIds)
                ->exists();

            return $turnoUsadoPeloCurso;
        }

        return false; // Negar acesso se nenhuma regra se aplicar
    }

    public function delete(User $user, Turno $turno): bool
    {
        // Coordenador NÃO pode eliminar (conforme decisão do usuário)
        if ($user->hasRole('coordenador_curso')) {
            return false;
        }

        if ($turno->turmas()->exists()) {
            return false;
        }

        return $user->can('turnos.eliminar');
    }
}
