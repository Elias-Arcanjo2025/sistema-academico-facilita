<?php

namespace App\Policies;

use App\Models\Aluno;
use App\Models\User;
use App\Policies\Concerns\FiltersCargoByAnoLectivo;
use App\Services\Pedagogico\DisciplinaCoordEscopoService;

class AlunoPolicy
{
    use FiltersCargoByAnoLectivo;

    public function viewAny(User $user): bool
    {
        return $user->can('alunos.ver');
    }

    public function view(User $user, Aluno $aluno): bool
    {
        if (! $user->can('alunos.ver')) {
            return false;
        }

        if ($user->temVisaoInstitucional() || $user->temAcessoSecretariaAlunos() || $user->temVisaoAssiduidade()) {
            return true;
        }

        if ($this->coordenadorCursoPodeAcederAluno($user, $aluno)) {
            return true;
        }

        if ($this->coordenadorDisciplinaPodeAcederAluno($user, $aluno)) {
            return true;
        }

        if ($user->hasRole('director_turma')) {
            if ($this->turmasDirectorAnoActual($user)->contains($aluno->turma_id)) {
                return true;
            }
        }

        if ($this->turmasProfessorAnoActual($user)->contains($aluno->turma_id)) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->can('alunos.criar')
            || $user->can('secretaria.matriculas.gerir');
    }

    public function update(User $user, Aluno $aluno): bool
    {
        if (! $user->can('alunos.editar')) {
            return false;
        }

        if ($user->temVisaoInstitucional() || $user->temAcessoSecretariaAlunos()) {
            return true;
        }

        if ($this->coordenadorCursoPodeAcederAluno($user, $aluno)) {
            return true;
        }

        if ($user->hasRole('director_turma')) {
            return $this->turmasDirectorAnoActual($user)->contains($aluno->turma_id);
        }

        return false;
    }

    public function delete(User $user, Aluno $aluno): bool
    {
        return $user->can('alunos.eliminar');
    }

    public function viewStatistics(User $user): bool
    {
        return $user->temVisaoInstitucional()
            || $user->hasAnyRole(['coordenador_curso', 'director_turma', 'coordenador_disciplina']);
    }

    public function updateSituacao(User $user, Aluno $aluno): bool
    {
        if ($user->temVisaoInstitucional()) {
            return true;
        }

        if ($this->coordenadorCursoPodeAcederAluno($user, $aluno)) {
            return true;
        }

        if ($user->hasRole('director_turma')) {
            return $this->turmasDirectorAnoActual($user)->contains($aluno->turma_id);
        }

        return false;
    }

    public function consolidarSituacoes(User $user): bool
    {
        return $user->hasRole('admin_full');
    }

    public function reporPalavraPasse(User $user, Aluno $aluno): bool
    {
        if ($user->temVisaoInstitucional() || $user->temAcessoSecretariaAlunos()) {
            return true;
        }

        if ($this->coordenadorCursoPodeAcederAluno($user, $aluno)) {
            return true;
        }

        if ($user->hasRole('director_turma')) {
            return $this->turmasDirectorAnoActual($user)->contains($aluno->turma_id);
        }

        return false;
    }

    protected function coordenadorCursoPodeAcederAluno(User $user, Aluno $aluno): bool
    {
        if (! $user->hasRole('coordenador_curso') || ! $aluno->turma_id) {
            return false;
        }

        $cursosIds = $this->cursosCoordenadorAnoActual($user);
        if ($cursosIds->isEmpty()) {
            $cursosIds = $user->cursosComoCoordenador()->wherePivot('activo', true)->pluck('cursos.id');
        }

        $aluno->loadMissing('turma');

        return $aluno->turma && $cursosIds->contains($aluno->turma->curso_id);
    }

    protected function coordenadorDisciplinaPodeAcederAluno(User $user, Aluno $aluno): bool
    {
        if (! $user->hasRole('coordenador_disciplina') || ! $aluno->turma_id) {
            return false;
        }

        return app(DisciplinaCoordEscopoService::class)
            ->turmaIdsPorClasses($user)
            ->contains((int) $aluno->turma_id);
    }
}
