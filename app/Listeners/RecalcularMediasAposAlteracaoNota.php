<?php

namespace App\Listeners;

use App\Events\NotaAlterada;
use App\Http\Controllers\DashboardController;
use App\Models\CoordenadorCurso;
use App\Models\DirectorTurma;
use App\Models\Nota;
use App\Models\Turma;
use App\Services\PautaService;

class RecalcularMediasAposAlteracaoNota
{
    public function __construct(
        protected PautaService $pautaService
    ) {}

    public function handle(NotaAlterada $event): void
    {
        $nota = $event->nota;

        // Apenas recalcula se for uma nota de avaliação trimestral (MAC, PP ou PT)
        // ou a Prova Global (afecta CA).
        $tiposTrigger = [Nota::TIPO_MAC, Nota::TIPO_PP, Nota::TIPO_PT, Nota::TIPO_PG];
        if (! in_array($nota->tipo, $tiposTrigger, true)) {
            return;
        }

        $professorId = $event->professorId;
        $anoLectivo = $nota->ano_lectivo;

        if (in_array($nota->tipo, [Nota::TIPO_MAC, Nota::TIPO_PP, Nota::TIPO_PT], true)
            && $nota->trimestre) {
            // MT só é gravada quando TODAS as componentes existem.
            $this->pautaService->calcularEGuardarMediaTrimestral(
                $nota->aluno_id,
                $nota->disciplina_id,
                $nota->turma_id,
                (int) $nota->trimestre,
                $professorId,
                $anoLectivo
            );
        }

        // Cadeia automática: MFT2 → CF → CA (quando possível).
        $this->pautaService->calcularEGuardarMFT2(
            $nota->aluno_id,
            $nota->disciplina_id,
            $nota->turma_id,
            $professorId,
            $anoLectivo
        );

        $this->pautaService->calcularEGuardarCF(
            $nota->aluno_id,
            $nota->disciplina_id,
            $nota->turma_id,
            $professorId,
            $anoLectivo
        );

        $classeNumero = $this->classeNumeroDaTurma((int) $nota->turma_id);
        $this->pautaService->calcularEGuardarCA(
            $nota->aluno_id,
            $nota->disciplina_id,
            $nota->turma_id,
            $classeNumero,
            $professorId,
            $anoLectivo
        );

        $this->pautaService->calcularEGuardarNotaFinal(
            $nota->aluno_id,
            $nota->disciplina_id,
            $nota->turma_id,
            $professorId,
            $anoLectivo
        );

        DashboardController::invalidarCacheProfessor($professorId, $nota->ano_lectivo_id);
        DashboardController::invalidarCacheAdmin($nota->ano_lectivo_id);

        if ($nota->turma_id) {
            DirectorTurma::where('turma_id', $nota->turma_id)
                ->where('ano_lectivo_id', $nota->ano_lectivo_id)
                ->where('activo', true)
                ->pluck('user_id')
                ->each(fn ($directorId) => DashboardController::invalidarCacheDirector((int) $directorId, $nota->ano_lectivo_id));

            $cursoId = Turma::where('id', $nota->turma_id)->value('curso_id');
            if ($cursoId) {
                CoordenadorCurso::where('curso_id', $cursoId)
                    ->where('activo', true)
                    ->pluck('user_id')
                    ->each(fn ($coordId) => DashboardController::invalidarCacheCoordenador((int) $coordId, $nota->ano_lectivo_id));
            }
        }
    }

    protected function classeNumeroDaTurma(int $turmaId): int
    {
        $turma = Turma::with('classe')->find($turmaId);
        $codigo = $turma?->classe?->codigo ?? $turma?->classe?->nome ?? '10';

        return (int) preg_replace('/\D/', '', (string) $codigo) ?: 10;
    }
}
