<?php

namespace App\Services\Pedagogico;

use App\Models\Configuracao;
use App\Models\CoordenadorDisciplina;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Âmbito canónico do Coordenador de Disciplina: pares disciplina + classe
 * da atribuição activa no ano lectivo.
 */
class DisciplinaCoordEscopoService
{
    public function __construct(
        protected PedagogicoAprovacaoService $aprovacao,
    ) {}

    public function ehCoordenadorActivo(User $user, ?int $anoLectivoId = null): bool
    {
        if (! $user->hasRole('coordenador_disciplina')) {
            return false;
        }

        return $this->paresDisciplinaClasse($user, $anoLectivoId)->isNotEmpty();
    }

    public function coordena(
        User $user,
        int $disciplinaId,
        ?int $classeId = null,
        ?int $anoLectivoId = null,
    ): bool {
        return $this->aprovacao->coordenaDisciplina($user, $disciplinaId, $anoLectivoId, $classeId);
    }

    /**
     * @return Collection<int, array{disciplina_id: int, classe_id: int}>
     */
    public function paresDisciplinaClasse(User $user, ?int $anoLectivoId = null): Collection
    {
        if (! $user->hasRole('coordenador_disciplina')) {
            return collect();
        }

        $anoLectivoId = $anoLectivoId ?? Configuracao::getAnoLectivoActualId();

        return CoordenadorDisciplina::query()
            ->where('user_id', $user->id)
            ->where('activo', true)
            ->when($anoLectivoId, fn ($q) => $q->where('ano_lectivo_id', $anoLectivoId))
            ->with('classes:id')
            ->get()
            ->flatMap(function (CoordenadorDisciplina $cd) {
                return $cd->classes->map(fn ($c) => [
                    'disciplina_id' => (int) $cd->disciplina_id,
                    'classe_id' => (int) $c->id,
                ]);
            })
            ->unique(fn (array $p) => $p['disciplina_id'].':'.$p['classe_id'])
            ->values();
    }

    /**
     * @return Collection<int, int>
     */
    public function disciplinaIds(User $user, ?int $anoLectivoId = null): Collection
    {
        return $this->paresDisciplinaClasse($user, $anoLectivoId)
            ->pluck('disciplina_id')
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, int>
     */
    public function classeIds(User $user, ?int $anoLectivoId = null): Collection
    {
        return $this->paresDisciplinaClasse($user, $anoLectivoId)
            ->pluck('classe_id')
            ->unique()
            ->values();
    }

    /**
     * Turmas do ano nas classes atribuídas ao coordenador de disciplina
     * (para boletins, listagem de alunos, etc. — não exige PDT da disciplina).
     *
     * @return Collection<int, int>
     */
    public function turmaIdsPorClasses(User $user, ?int $anoLectivoId = null): Collection
    {
        $anoLectivoId = $anoLectivoId ?? Configuracao::getAnoLectivoActualId();
        $classeIds = $this->classeIds($user, $anoLectivoId);

        if ($classeIds->isEmpty() || ! $anoLectivoId) {
            return collect();
        }

        return Turma::query()
            ->where('activo', true)
            ->where('ano_lectivo_id', $anoLectivoId)
            ->whereIn('classe_id', $classeIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    /**
     * Turmas do ano cuja classe + disciplina leccionada estão no âmbito do coordenador.
     *
     * @return Collection<int, int>
     */
    public function turmaIds(User $user, ?int $anoLectivoId = null): Collection
    {
        $anoLectivoId = $anoLectivoId ?? Configuracao::getAnoLectivoActualId();
        $pares = $this->paresDisciplinaClasse($user, $anoLectivoId);

        if ($pares->isEmpty()) {
            return collect();
        }

        $turmaIds = collect();

        foreach ($pares as $par) {
            $ids = ProfessorDisciplinaTurma::query()
                ->where('disciplina_id', $par['disciplina_id'])
                ->where('activo', true)
                ->when($anoLectivoId, fn ($q) => $q->where('ano_lectivo_id', $anoLectivoId))
                ->whereHas('turma', function (Builder $q) use ($par, $anoLectivoId) {
                    $q->where('classe_id', $par['classe_id'])
                        ->when($anoLectivoId, fn ($t) => $t->where('ano_lectivo_id', $anoLectivoId));
                })
                ->pluck('turma_id');

            $turmaIds = $turmaIds->merge($ids);
        }

        return $turmaIds->map(fn ($id) => (int) $id)->unique()->values();
    }

    /**
     * @return Collection<int, Turma>
     */
    public function turmas(User $user, ?int $anoLectivoId = null): Collection
    {
        $anoLectivoId = $anoLectivoId ?? Configuracao::getAnoLectivoActualId();
        $ids = $this->turmaIds($user, $anoLectivoId);

        if ($ids->isEmpty()) {
            return collect();
        }

        return Turma::activo()
            ->where('ano_lectivo_id', $anoLectivoId)
            ->whereIn('id', $ids)
            ->with(['curso', 'classe'])
            ->orderBy('nome')
            ->get();
    }

    /**
     * Associações professor–disciplina–turma no âmbito coordenado.
     *
     * @return Builder<ProfessorDisciplinaTurma>
     */
    public function queryAssociacoes(User $user, ?int $anoLectivoId = null): Builder
    {
        $anoLectivoId = $anoLectivoId ?? Configuracao::getAnoLectivoActualId();

        $query = ProfessorDisciplinaTurma::query()
            ->where('activo', true)
            ->when($anoLectivoId, fn ($q) => $q->where('ano_lectivo_id', $anoLectivoId));

        // Admin / direcção / gestão: visão de todas as associações do ano
        if ($user->temVisaoInstitucional()) {
            return $query;
        }

        $pares = $this->paresDisciplinaClasse($user, $anoLectivoId);

        if ($pares->isEmpty()) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where(function (Builder $q) use ($pares) {
            foreach ($pares as $par) {
                $q->orWhere(function (Builder $q2) use ($par) {
                    $q2->where('disciplina_id', $par['disciplina_id'])
                        ->whereHas('turma', fn (Builder $t) => $t->where('classe_id', $par['classe_id']));
                });
            }
        });
    }

    /**
     * Professores que leccionam a disciplina nas classes coordenadas.
     *
     * @return Collection<int, int>
     */
    public function professorIds(
        User $user,
        ?int $disciplinaId = null,
        ?int $anoLectivoId = null,
    ): Collection {
        $query = $this->queryAssociacoes($user, $anoLectivoId);

        if ($disciplinaId) {
            $query->where('disciplina_id', $disciplinaId);
        }

        return $query->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * Aplica filtro disciplina+classe a queries com disciplina_id e relação turma.
     */
    public function aplicarEscopoPlanificacao(Builder $query, User $user, ?int $anoLectivoId = null): Builder
    {
        $pares = $this->paresDisciplinaClasse($user, $anoLectivoId);

        if ($pares->isEmpty()) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where(function (Builder $q) use ($pares) {
            foreach ($pares as $par) {
                $q->orWhere(function (Builder $q2) use ($par) {
                    $q2->where('disciplina_id', $par['disciplina_id'])
                        ->whereHas('turma', fn (Builder $t) => $t->where('classe_id', $par['classe_id']));
                });
            }
        });
    }
}
