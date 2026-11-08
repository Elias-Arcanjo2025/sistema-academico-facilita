<?php

namespace App\Policies;

use App\Models\ConselhoTrimestre;
use App\Models\ProfessorDisciplinaTurma;
use App\Models\User;

class ConselhoPolicy
{
    /**
     * Determina se o utilizador pode listar conselhos
     */
    public function viewAny(User $user): bool
    {
        return $user->can('conselhos.ver');
    }

    /**
     * Determina se o utilizador pode ver um conselho específico.
     * Professores podem ver os conselhos das turmas onde leccionam.
     * Coordenadores, directores, admin e subdirector têm acesso total à visualização.
     */
    public function view(User $user, ConselhoTrimestre $conselho): bool
    {
        if (!$user->can('conselhos.ver')) {
            return false;
        }

        // Admin, Directora Geral e Subdiretor Pedagógico veem todos
        if ($user->temVisaoInstitucional()) {
            return true;
        }

        // Coordenador de Curso vê conselhos dos seus cursos
        if ($user->hasRole('coordenador_curso')) {
            $anoLectivoId = \App\Models\Configuracao::getAnoLectivoActualId();

            $cursosIds = $user->cursosComoCoordenador()
                ->wherePivot('activo', true)
                ->wherePivot('ano_lectivo_id', $anoLectivoId)
                ->pluck('cursos.id');

            if ($conselho->turma && $cursosIds->contains($conselho->turma->curso_id)) {
                return true;
            }
        }

        // Director de Turma vê conselhos das suas turmas
        if ($user->hasRole('director_turma')) {
            $turmasIds = $user->turmasComoDirector()
                ->where('director_turma.activo', true)
                ->pluck('turmas.id');

            if ($turmasIds->contains($conselho->turma_id)) {
                return true;
            }
        }

        // Professor vê conselhos das turmas onde lecciona
        if ($user->hasRole('professor')) {
            $anoLectivoId = \App\Models\Configuracao::getAnoLectivoActualId();

            $temAssociacao = ProfessorDisciplinaTurma::where('user_id', $user->id)
                ->where('turma_id', $conselho->turma_id)
                ->where('activo', true)
                ->where('ano_lectivo_id', $anoLectivoId)
                ->exists();

            if ($temAssociacao) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determina se o utilizador pode iniciar um conselho
     */
    public function iniciar(User $user, ConselhoTrimestre $conselho): bool
    {
        if (!$user->can('conselhos.gerenciar')) {
            return false;
        }

        // Verificar se o conselho pode ser iniciado (estado pendente)
        if (!$conselho->podeIniciar()) {
            return false;
        }

        // Admin, Directora Geral e Subdiretor podem iniciar todos
        if ($user->temVisaoInstitucional()) {
            return true;
        }

        // Coordenador de Curso pode iniciar conselhos dos seus cursos
        if ($user->hasRole('coordenador_curso')) {
            $anoLectivoId = \App\Models\Configuracao::getAnoLectivoActualId();

            $cursosIds = $user->cursosComoCoordenador()
                ->wherePivot('activo', true)
                ->wherePivot('ano_lectivo_id', $anoLectivoId)
                ->pluck('cursos.id');

            if ($conselho->turma && $cursosIds->contains($conselho->turma->curso_id)) {
                return true;
            }
        }

        // Director de Turma pode iniciar conselhos das suas turmas
        if ($user->hasRole('director_turma')) {
            $anoLectivoId = \App\Models\Configuracao::getAnoLectivoActualId();
            $anoLectivoNome = \App\Models\AnoLectivo::getNomeById($anoLectivoId);

            $turmasIds = $user->turmasComoDirector()
                ->wherePivot('activo', true)
                ->wherePivot('ano_lectivo', $anoLectivoNome)
                ->pluck('turmas.id');

            if ($turmasIds->contains($conselho->turma_id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determina se o utilizador pode ajustar notas no conselho
     */
    public function ajustar(User $user, ConselhoTrimestre $conselho): bool
    {
        if (!$user->can('conselhos.gerenciar')) {
            return false;
        }

        // Verificar se o conselho está em estado de ajuste (em_curso)
        if (!$conselho->podeAjustar()) {
            return false;
        }

        // Admin, Directora Geral e Subdiretor podem ajustar todos
        if ($user->temVisaoInstitucional()) {
            return true;
        }

        // Coordenador de Curso pode ajustar conselhos dos seus cursos
        if ($user->hasRole('coordenador_curso')) {
            $anoLectivoId = \App\Models\Configuracao::getAnoLectivoActualId();

            $cursosIds = $user->cursosComoCoordenador()
                ->wherePivot('activo', true)
                ->wherePivot('ano_lectivo_id', $anoLectivoId)
                ->pluck('cursos.id');

            if ($conselho->turma && $cursosIds->contains($conselho->turma->curso_id)) {
                return true;
            }
        }

        // Director de Turma pode ajustar conselhos das suas turmas
        if ($user->hasRole('director_turma')) {
            $anoLectivoId = \App\Models\Configuracao::getAnoLectivoActualId();
            $anoLectivoNome = \App\Models\AnoLectivo::getNomeById($anoLectivoId);

            $turmasIds = $user->turmasComoDirector()
                ->wherePivot('activo', true)
                ->wherePivot('ano_lectivo', $anoLectivoNome)
                ->pluck('turmas.id');

            if ($turmasIds->contains($conselho->turma_id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determina se o utilizador pode concluir um conselho
     */
    public function concluir(User $user, ConselhoTrimestre $conselho): bool
    {
        if (!$user->can('conselhos.gerenciar')) {
            return false;
        }

        // Verificar se o conselho pode ser concluído (em_curso)
        if (!$conselho->podeConcluir()) {
            return false;
        }

        // Admin, Directora Geral e Subdiretor podem concluir todos
        if ($user->temVisaoInstitucional()) {
            return true;
        }

        // Coordenador de Curso pode concluir conselhos dos seus cursos
        if ($user->hasRole('coordenador_curso')) {
            $anoLectivoId = \App\Models\Configuracao::getAnoLectivoActualId();

            $cursosIds = $user->cursosComoCoordenador()
                ->wherePivot('activo', true)
                ->wherePivot('ano_lectivo_id', $anoLectivoId)
                ->pluck('cursos.id');

            if ($conselho->turma && $cursosIds->contains($conselho->turma->curso_id)) {
                return true;
            }
        }

        // Director de Turma pode concluir conselhos das suas turmas
        if ($user->hasRole('director_turma')) {
            $anoLectivoId = \App\Models\Configuracao::getAnoLectivoActualId();
            $anoLectivoNome = \App\Models\AnoLectivo::getNomeById($anoLectivoId);

            $turmasIds = $user->turmasComoDirector()
                ->wherePivot('activo', true)
                ->wherePivot('ano_lectivo', $anoLectivoNome)
                ->pluck('turmas.id');

            if ($turmasIds->contains($conselho->turma_id)) {
                return true;
            }
        }

        return false;
    }
}
