<?php

namespace App\Http\Controllers;

use App\Models\Aluno;
use App\Models\Nota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotaArchivedController extends Controller
{
    /**
     * Lista todas as notas arquivadas de um aluno
     */
    public function index(Aluno $aluno): View
    {
        $this->authorize('view', $aluno);

        $notasArquivadas = Nota::where('aluno_id', $aluno->id)
            ->archived()
            ->with(['disciplina', 'turma', 'professor', 'archivedBy'])
            ->orderBy('archived_at', 'desc')
            ->get();

        // Agrupar por trimestre e ano lectivo para melhor visualização
        $notasAgrupadas = $notasArquivadas->groupBy(function ($nota) {
            return $nota->ano_lectivo . ' - Trimestre ' . $nota->trimestre;
        });

        return view('alunos.notas-arquivadas', [
            'aluno' => $aluno,
            'notasArquivadas' => $notasArquivadas,
            'notasAgrupadas' => $notasAgrupadas,
        ]);
    }

    /**
     * Restaura uma nota arquivada
     * Qualquer admin pode restaurar
     */
    public function restore(Nota $nota): RedirectResponse
    {
        // Verificar permissão
        if (!auth()->user()->temVisaoInstitucional() && !auth()->user()->hasRole('coordenador_curso')) {
            return back()->with('error', 'Não tem permissão para restaurar notas arquivadas.');
        }

        if (!$nota->isArchived()) {
            return back()->with('error', 'Esta nota não está arquivada.');
        }

        $aluno = $nota->aluno;
        $disciplina = $nota->disciplina->nome ?? 'N/A';

        if ($nota->restore()) {
            return redirect()
                ->route('alunos.notas-arquivadas', $aluno)
                ->with('success', "Nota de {$disciplina} restaurada com sucesso!");
        }

        return back()->with('error', 'Não foi possível restaurar a nota.');
    }

    /**
     * Deleta permanentemente uma nota arquivada
     * APENAS admin_full pode fazer isso
     */
    public function forceDelete(Nota $nota): RedirectResponse
    {
        // Verificar permissão STRICT
        if (!auth()->user()->hasRole('admin_full')) {
            activity()
                ->causedBy(auth()->user())
                ->performedOn($nota)
                ->withProperties([
                    'tentativa_negada' => true,
                    'user_role' => auth()->user()->getRoleNames(),
                ])
                ->log('Tentativa não autorizada de eliminar permanentemente nota arquivada');

            return back()->with('error',
                'Apenas Administradores Full podem eliminar permanentemente notas arquivadas. ' .
                'Esta tentativa foi registada na auditoria.'
            );
        }

        if (!$nota->isArchived()) {
            return back()->with('error', 'Apenas notas arquivadas podem ser eliminadas permanentemente.');
        }

        $aluno = $nota->aluno;
        $disciplina = $nota->disciplina->nome ?? 'N/A';
        $tipo = $nota->tipo;
        $valor = $nota->valor;

        // Log crítico ANTES de deletar
        activity()
            ->causedBy(auth()->user())
            ->performedOn($nota)
            ->withProperties([
                'aluno_id' => $nota->aluno_id,
                'aluno_nome' => $aluno->nome,
                'disciplina_id' => $nota->disciplina_id,
                'disciplina_nome' => $disciplina,
                'tipo' => $tipo,
                'valor' => $valor,
                'ano_lectivo' => $nota->ano_lectivo,
                'trimestre' => $nota->trimestre,
                'archived_at' => $nota->archived_at,
                'archived_reason' => $nota->archived_reason,
                'archived_by' => $nota->archived_by,
                'action' => 'FORCE DELETE - IRREVERSÍVEL',
            ])
            ->log('Nota arquivada ELIMINADA PERMANENTEMENTE');

        // Delete permanente
        $nota->forceDelete();

        return redirect()
            ->route('alunos.notas-arquivadas', $aluno)
            ->with('warning',
                "Nota de {$disciplina} ({$tipo}: {$valor}) foi ELIMINADA PERMANENTEMENTE. " .
                "Esta ação é irreversível e foi registada na auditoria."
            );
    }
}
