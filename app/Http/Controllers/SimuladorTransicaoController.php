<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesNotasTurma;
use App\Models\Aluno;
use App\Models\Turma;
use App\Services\SimuladorTransicaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SimuladorTransicaoController extends Controller
{
    use AuthorizesNotasTurma;

    public function __construct(private SimuladorTransicaoService $simulador)
    {
        $this->middleware('auth');
    }

    public function simular(Request $request, Turma $turma, Aluno $aluno): JsonResponse
    {
        $this->assertAcessoTurma($turma);

        abort_unless((int) $aluno->turma_id === (int) $turma->id, 404);

        $validated = $request->validate([
            'tipo_exame' => 'required|in:recuperacao,recurso',
            'notas' => 'required|array',
            'notas.*' => 'nullable|numeric|min:0|max:20',
            'ano_lectivo' => 'nullable|string',
        ]);

        $notas = [];
        foreach ($validated['notas'] as $discId => $valor) {
            $notas[(int) $discId] = $valor === null || $valor === '' ? null : (float) $valor;
        }

        $resultado = $this->simulador->simular(
            $turma,
            $aluno->id,
            $validated['tipo_exame'],
            $notas,
            $validated['ano_lectivo'] ?? null,
        );

        if (isset($resultado['erro'])) {
            return response()->json($resultado, 422);
        }

        return response()->json($resultado);
    }
}
