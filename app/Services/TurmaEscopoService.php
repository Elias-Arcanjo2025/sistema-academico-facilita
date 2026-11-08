<?php

namespace App\Services;

use App\Models\Aluno;
use App\Models\Configuracao;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\Turma;
use App\Models\User;
use App\Services\Pedagogico\DisciplinaCoordEscopoService;

/**
 * Verificação centralizada de acesso a turmas/disciplinas por cargo.
 */
class TurmaEscopoService
{
    /**
     * IDs de turmas que o utilizador pode consultar no ano lectivo
     * (união: professor + director + coordenador de curso + coordenador de disciplina).
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function turmaIdsConsultaveis(User $user, ?int $anoLectivoId = null): \Illuminate\Support\Collection
    {
        $anoLectivoId = $anoLectivoId ?? Configuracao::getAnoLectivoActualId();

        if (! $anoLectivoId) {
            return collect();
        }

        if ($user->temVisaoInstitucional()) {
            return Turma::query()
                ->where('activo', true)
                ->where('ano_lectivo_id', $anoLectivoId)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values();
        }

        $ids = collect();

        $ids = $ids->merge(
            ProfessorDisciplinaTurma::query()
                ->where('user_id', $user->id)
                ->where('activo', true)
                ->where('ano_lectivo_id', $anoLectivoId)
                ->pluck('turma_id')
        );

        if ($user->hasRole('director_turma')) {
            $ids = $ids->merge(
                $user->turmasComoDirector()
                    ->where('director_turma.activo', true)
                    ->where('turmas.activo', true)
                    ->where('turmas.ano_lectivo_id', $anoLectivoId)
                    ->pluck('turmas.id')
            );
        }

        if ($user->hasRole('coordenador_curso')) {
            $cursosIds = $user->coordenadorCursos()
                ->where('activo', true)
                ->where(function ($q) use ($anoLectivoId) {
                    $q->where('ano_lectivo_id', $anoLectivoId)
                        ->orWhereNull('ano_lectivo_id');
                })
                ->pluck('curso_id');

            if ($cursosIds->isNotEmpty()) {
                $ids = $ids->merge(
                    Turma::query()
                        ->where('activo', true)
                        ->where('ano_lectivo_id', $anoLectivoId)
                        ->whereIn('curso_id', $cursosIds)
                        ->pluck('id')
                );
            }
        }

        if ($user->hasRole('coordenador_disciplina')) {
            $ids = $ids->merge(
                app(DisciplinaCoordEscopoService::class)->turmaIdsPorClasses($user, $anoLectivoId)
            );
        }

        return $ids->map(fn ($id) => (int) $id)->unique()->values();
    }

    public function podeAcederTurma(User $user, int $turmaId, ?int $disciplinaId = null): bool
    {
        if ($user->temVisaoInstitucional() || $user->temAcessoSecretariaAlunos()) {
            return true;
        }

        if (ProfessorDisciplinaTurma::where('user_id', $user->id)
            ->where('turma_id', $turmaId)
            ->where('activo', true)
            ->when($disciplinaId, fn ($q) => $q->where('disciplina_id', $disciplinaId))
            ->exists()) {
            return true;
        }

        if ($user->hasRole('director_turma')) {
            $anoId = Configuracao::getAnoLectivoActualId();
            $query = $user->turmasComoDirector()
                ->where('director_turma.activo', true)
                ->where('turmas.activo', true)
                ->where('turmas.id', $turmaId);
            if ($anoId) {
                $query->where('turmas.ano_lectivo_id', $anoId);
            }

            return $query->exists();
        }

        if ($user->hasRole('coordenador_curso')) {
            $turma = Turma::find($turmaId);
            if (! $turma) {
                return false;
            }

            $anoId = Configuracao::getAnoLectivoActualId();
            $query = $user->coordenadorCursos()
                ->where('activo', true)
                ->where('curso_id', $turma->curso_id);

            // Preferir atribuição do ano actual; se não houver, aceitar qualquer activa (legado)
            if ($anoId) {
                $noAno = (clone $query)->where('ano_lectivo_id', $anoId)->exists();
                if ($noAno) {
                    return true;
                }
            }

            return $query->exists();
        }

        if ($user->hasRole('coordenador_disciplina')) {
            $turmaIds = app(DisciplinaCoordEscopoService::class)->turmaIdsPorClasses($user);

            return $turmaIds->contains($turmaId);
        }

        return false;
    }

    public function assertAcessoTurma(User $user, int $turmaId, ?int $disciplinaId = null): void
    {
        if (! $this->podeAcederTurma($user, $turmaId, $disciplinaId)) {
            abort(403, 'Não tem permissão para aceder a esta turma. Só pode trabalhar com turmas do seu âmbito (curso, direcção ou disciplinas atribuídas).');
        }
    }

    public function assertAcessoAluno(User $user, Aluno $aluno): void
    {
        if ($user->hasRole('aluno')) {
            if ((int) $user->aluno_id !== (int) $aluno->id) {
                abort(403, 'Só pode aceder aos seus próprios dados académicos.');
            }

            return;
        }

        if (! $aluno->turma_id) {
            abort(404, 'Aluno sem turma associada.');
        }

        $this->assertAcessoTurma($user, (int) $aluno->turma_id);
    }
}
