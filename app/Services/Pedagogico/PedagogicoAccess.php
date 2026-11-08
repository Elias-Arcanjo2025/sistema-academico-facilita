<?php

namespace App\Services\Pedagogico;

use App\Models\Configuracao;
use App\Models\Pedagogico\PedPlanificacao;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Support\Collection;

class PedagogicoAccess
{
    public static function podeVerTudo(User $user): bool
    {
        return $user->temVisaoInstitucional();
    }

    /**
     * Ferramentas de coordenação (lançamentos / rendimento / avisos):
     * visão institucional OU coordenador de disciplina com atribuição activa no ano.
     */
    public static function podeAcederCoordenacaoDisciplina(User $user, string $permission): bool
    {
        if (! $user->can($permission)) {
            return false;
        }

        if ($user->temVisaoInstitucional()) {
            return true;
        }

        return app(DisciplinaCoordEscopoService::class)->ehCoordenadorActivo($user);
    }

    public static function temAcessoPlanificacao(User $user, PedPlanificacao $planificacao): bool
    {
        if (! $user->can('pedagogico.ver')) {
            return false;
        }

        if (self::podeVerTudo($user)) {
            return true;
        }

        if ($user->hasRole('coordenador_disciplina')) {
            $planificacao->loadMissing('turma');
            $classeId = $planificacao->turma?->classe_id
                ? (int) $planificacao->turma->classe_id
                : null;

            if (app(DisciplinaCoordEscopoService::class)->coordena(
                $user,
                (int) $planificacao->disciplina_id,
                $classeId,
            )) {
                return true;
            }
        }

        if ($user->hasRole('coordenador_curso')) {
            $anoLectivoId = Configuracao::getAnoLectivoActualId();
            $cursosIds = $user->cursosComoCoordenador()
                ->wherePivot('activo', true)
                ->wherePivot('ano_lectivo_id', $anoLectivoId)
                ->pluck('cursos.id');

            $planificacao->loadMissing('turma');
            if ($planificacao->turma && $cursosIds->contains($planificacao->turma->curso_id)) {
                return true;
            }
        }

        if ($user->hasRole('director_turma')) {
            $turmasIds = $user->turmasComoDirector()
                ->where('director_turma.activo', true)
                ->pluck('turmas.id');

            if ($turmasIds->contains($planificacao->turma_id)) {
                return true;
            }
        }

        if ($user->hasRole('professor')) {
            if ((int) $planificacao->user_id === (int) $user->id) {
                return self::temAssociacao($user, $planificacao);
            }
        }

        return false;
    }

    public static function podeEditarPlanificacao(User $user, PedPlanificacao $planificacao): bool
    {
        if (! $user->can('pedagogico.planificacao.editar')) {
            return false;
        }

        if (self::podeVerTudo($user)) {
            return true;
        }

        if ($user->hasRole('coordenador_curso') || $user->hasRole('director_turma')) {
            return self::temAcessoPlanificacao($user, $planificacao);
        }

        return (int) $planificacao->user_id === (int) $user->id
            && self::temAssociacao($user, $planificacao);
    }

    public static function temAssociacao(User $user, PedPlanificacao $planificacao): bool
    {
        return ProfessorDisciplinaTurma::where('user_id', $user->id)
            ->where('turma_id', $planificacao->turma_id)
            ->where('disciplina_id', $planificacao->disciplina_id)
            ->where('activo', true)
            ->where('ano_lectivo_id', $planificacao->ano_lectivo_id)
            ->exists();
    }

    /**
     * Turmas que o utilizador pode seleccionar em planificações e filtros pedagógicos.
     *
     * @return Collection<int, Turma>
     */
    public static function turmasDisponiveis(User $user, ?int $anoLectivoId = null): Collection
    {
        $anoLectivoId = $anoLectivoId ?? Configuracao::getAnoLectivoActualId();
        $soActivas = ! \App\Support\NotaLancamentoRules::anoLectivoEhHistorico(
            $anoLectivoId ? (int) $anoLectivoId : null
        );

        $queryTurmas = fn () => Turma::query()
            ->when($soActivas, fn ($q) => $q->activo())
            ->where('ano_lectivo_id', $anoLectivoId)
            ->with('curso', 'classe')
            ->orderBy('nome');

        if (self::podeVerTudo($user)) {
            return $queryTurmas()->get();
        }

        $turmaIds = ProfessorDisciplinaTurma::query()
            ->where('user_id', $user->id)
            ->where('activo', true)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->pluck('turma_id');

        if ($user->hasRole('director_turma')) {
            $directorQuery = $user->turmasComoDirector()
                ->where('director_turma.activo', true)
                ->where('turmas.ano_lectivo_id', $anoLectivoId);
            if ($soActivas) {
                $directorQuery->where('turmas.activo', true);
            }
            $turmaIds = $turmaIds->merge($directorQuery->pluck('turmas.id'));
        }

        $turmaIds = $turmaIds->unique()->filter()->values();

        if ($turmaIds->isEmpty() && $user->hasRole('coordenador_disciplina')) {
            $turmaIds = app(DisciplinaCoordEscopoService::class)
                ->turmaIds($user, $anoLectivoId);
        }

        if ($turmaIds->isEmpty() && $user->hasRole('coordenador_curso')) {
            $cursosIds = $user->cursosComoCoordenador()
                ->wherePivot('activo', true)
                ->wherePivot('ano_lectivo_id', $anoLectivoId)
                ->pluck('cursos.id');

            return $queryTurmas()
                ->whereIn('curso_id', $cursosIds)
                ->get();
        }

        if ($turmaIds->isEmpty()) {
            return collect();
        }

        return $queryTurmas()
            ->whereIn('id', $turmaIds)
            ->get();
    }

    public static function turmaAcessivel(User $user, int $turmaId, ?int $anoLectivoId = null): bool
    {
        return self::turmasDisponiveis($user, $anoLectivoId)
            ->contains(fn (Turma $turma) => (int) $turma->id === $turmaId);
    }
}
