<?php

namespace App\Policies\Concerns;

use App\Models\Configuracao;
use App\Models\User;

trait FiltersCargoByAnoLectivo
{
    protected function turmasDirectorAnoActual(User $user): \Illuminate\Support\Collection
    {
        $anoId = Configuracao::getAnoLectivoActualId();
        $query = $user->turmasComoDirector()->wherePivot('activo', true);
        if ($anoId) {
            $query->where('turmas.ano_lectivo_id', $anoId);
        }

        return $query->pluck('turmas.id');
    }

    protected function turmasProfessorAnoActual(User $user): \Illuminate\Support\Collection
    {
        $anoId = Configuracao::getAnoLectivoActualId();
        $query = $user->turmasComoProfessor()->wherePivot('activo', true);
        if ($anoId) {
            $query->where('turmas.ano_lectivo_id', $anoId);
        }

        return $query->pluck('turmas.id');
    }

    protected function cursosCoordenadorAnoActual(User $user): \Illuminate\Support\Collection
    {
        $anoId = Configuracao::getAnoLectivoActualId();
        $query = $user->cursosComoCoordenador()->wherePivot('activo', true);
        if ($anoId) {
            $query->wherePivot('ano_lectivo_id', $anoId);
        }

        return $query->pluck('cursos.id');
    }
}
