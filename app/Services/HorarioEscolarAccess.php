<?php

namespace App\Services;

use App\Models\Configuracao;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Support\Collection;

class HorarioEscolarAccess
{
    public static function podeGerir(User $user): bool
    {
        if ($user->temControloAcademicoInstitucional()) {
            return true;
        }

        return $user->hasRole('coordenador_curso');
    }

    public static function podeAprovar(User $user): bool
    {
        return $user->can('horarios.aprovar')
            || $user->temControloAcademicoInstitucional();
    }

    public static function podeVer(User $user): bool
    {
        return $user->can('horarios.ver')
            || $user->can('calendario_provas.ver')
            || self::podeAprovar($user);
    }

    /**
     * @return Collection<int, Turma>
     */
    public static function turmasDisponiveis(User $user, ?int $anoLectivoId = null): Collection
    {
        $anoLectivoId = $anoLectivoId ?? Configuracao::getAnoLectivoActualId();

        if ($user->temControloAcademicoInstitucional() || $user->temVisaoInstitucional()) {
            return Turma::activo()
                ->where('ano_lectivo_id', $anoLectivoId)
                ->with(['curso', 'classe', 'sala', 'turno'])
                ->orderBy('nome')
                ->get();
        }

        if ($user->hasRole('coordenador_curso')) {
            $cursosIds = $user->cursosComoCoordenador()
                ->wherePivot('activo', true)
                ->wherePivot('ano_lectivo_id', $anoLectivoId)
                ->pluck('cursos.id');

            return Turma::activo()
                ->where('ano_lectivo_id', $anoLectivoId)
                ->whereIn('curso_id', $cursosIds)
                ->with(['curso', 'classe', 'sala', 'turno'])
                ->orderBy('nome')
                ->get();
        }

        return collect();
    }

    public static function turmaAcessivel(User $user, Turma $turma, ?int $anoLectivoId = null): bool
    {
        return self::turmasDisponiveis($user, $anoLectivoId)
            ->contains(fn (Turma $t) => (int) $t->id === (int) $turma->id);
    }
}
