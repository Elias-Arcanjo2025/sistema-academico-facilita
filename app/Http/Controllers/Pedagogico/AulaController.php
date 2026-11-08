<?php

namespace App\Http\Controllers\Pedagogico;

use App\Http\Controllers\Controller;
use App\Models\Pedagogico\PedAula;
use App\Models\Pedagogico\PedPlanificacao;
use App\Services\Pedagogico\AulaMinistradaService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AulaController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private AulaMinistradaService $aulaMinistradaService,
    ) {}

    public function index(PedPlanificacao $planificacao): View
    {
        $this->authorize('view', $planificacao);

        $planificacao->load(['aulas.quinzena', 'turma', 'disciplina']);

        return view('pedagogico.aulas.index', compact('planificacao'));
    }

    public function update(Request $request, PedAula $aula): RedirectResponse
    {
        $this->authorize('view', $aula->planificacao);

        $validated = $request->validate([
            'accao' => 'required|in:ministrada,adiar,cancelar',
            'data' => 'required_if:accao,ministrada,adiar|nullable|date',
            'tema_ministrado' => 'nullable|string|max:255',
            'conteudo_ministrado' => 'nullable|string',
            'observacoes' => 'nullable|string',
            'motivo' => 'nullable|string|max:500',
        ]);

        try {
            match ($validated['accao']) {
                'ministrada' => $this->aulaMinistradaService->registarMinistrada(
                    $aula,
                    $validated['data'],
                    $validated
                ),
                'adiar' => $this->aulaMinistradaService->adiar(
                    $aula,
                    $validated['data'],
                    $validated['motivo'] ?? null
                ),
                'cancelar' => $this->aulaMinistradaService->cancelar(
                    $aula,
                    $validated['motivo'] ?? null
                ),
            };

            return back()->with('success', 'Aula actualizada com sucesso.');
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }
}
