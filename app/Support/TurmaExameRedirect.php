<?php

namespace App\Support;

use App\Models\Configuracao;
use App\Models\Turma;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Redirecciona índices de exames para o lançamento quando turma_id vem na URL.
 */
class TurmaExameRedirect
{
    /**
     * @param  array<int, int>  $classesPermitidas
     */
    public static function paraLancamento(
        Request $request,
        string $routeName,
        array $classesPermitidas,
    ): ?RedirectResponse {
        if (! $request->filled('turma_id')) {
            return null;
        }

        $turma = Turma::with('classe')->find((int) $request->input('turma_id'));
        if (! $turma || ! in_array($turma->numeroClasse(), $classesPermitidas, true)) {
            return null;
        }

        return redirect()->route($routeName, [
            'turma' => $turma,
            'ano_lectivo' => $request->input('ano_lectivo', Configuracao::getAnoLectivoActual()),
        ]);
    }
}
