<?php

namespace App\Http\Controllers\Pedagogico;

use App\Http\Controllers\Controller;
use App\Models\Pedagogico\PedPlanificacao;
use App\Services\Pedagogico\AulaMinistradaService;
use App\Services\Pedagogico\ExecucaoPlanificacaoService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ExecucaoController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private ExecucaoPlanificacaoService $execucaoService,
        private AulaMinistradaService $aulaMinistradaService,
    ) {}

    public function show(PedPlanificacao $planificacao): View
    {
        $this->authorize('view', $planificacao);

        $planificacao->load(['turma', 'disciplina', 'aulas', 'execucaoAnalises']);
        $percentagem = $this->aulaMinistradaService->percentagemExecucao($planificacao);
        $ultimaAnalise = $planificacao->execucaoAnalises->first();

        $estadosAulas = $planificacao->aulas->groupBy('estado')->map->count();

        return view('pedagogico.execucao.show', compact(
            'planificacao',
            'percentagem',
            'ultimaAnalise',
            'estadosAulas',
        ));
    }

    public function analisar(PedPlanificacao $planificacao): RedirectResponse
    {
        $this->authorize('view', $planificacao);
        abort_unless(auth()->user()->can('pedagogico.ia.gerar'), 403);

        try {
            $this->execucaoService->analisar($planificacao, auth()->user());

            return back()->with('success', 'Análise de execução concluída.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
