<?php

namespace App\Http\Controllers\Pedagogico;

use App\Http\Controllers\Controller;
use App\Jobs\Pedagogico\GerarExercicioJob;
use App\Models\Pedagogico\PedExercicio;
use App\Models\Pedagogico\PedPlanificacao;
use App\Services\Pedagogico\PlanificacaoService;
use App\Services\PdfExportService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ExercicioController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private PlanificacaoService $planificacaoService,
        private PdfExportService $pdfExportService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', PedPlanificacao::class);

        $planificacaoId = $request->get('planificacao_id');
        $exercicios = collect();
        $planificacao = null;

        if ($planificacaoId) {
            $planificacao = PedPlanificacao::findOrFail($planificacaoId);
            $this->authorize('view', $planificacao);
            $exercicios = $planificacao->exercicios()->with('quinzena')->latest()->get();
        }

        $planificacoes = $this->planificacaoService->listar(auth()->user(), ['per_page' => 100]);

        return view('pedagogico.exercicios.index', compact('exercicios', 'planificacao', 'planificacoes'));
    }

    public function create(Request $request): View
    {
        $this->authorize('viewAny', PedPlanificacao::class);
        abort_unless(auth()->user()->can('pedagogico.ia.gerar'), 403);

        $planificacoes = $this->planificacaoService->listar(auth()->user(), ['per_page' => 100]);
        $planificacaoId = $request->get('planificacao_id');

        return view('pedagogico.exercicios.create', compact('planificacoes', 'planificacaoId'));
    }

    public function gerar(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('pedagogico.ia.gerar'), 403);

        $validated = $request->validate([
            'planificacao_id' => 'required|exists:ped_planificacoes,id',
            'tipo' => 'required|string|max:30',
            'dificuldade' => 'nullable|in:facil,medio,dificil',
            'quinzena_id' => 'nullable|exists:ped_quinzenas,id',
        ]);

        $planificacao = PedPlanificacao::findOrFail($validated['planificacao_id']);
        $this->authorize('view', $planificacao);

        GerarExercicioJob::dispatch(
            $planificacao->id,
            $validated['tipo'],
            $validated['dificuldade'] ?? 'medio',
            $validated['quinzena_id'] ?? null,
            auth()->id(),
        );

        return redirect()
            ->route('pedagogico.exercicios.index', ['planificacao_id' => $planificacao->id])
            ->with('success', 'Geração de exercício enfileirada.');
    }

    public function show(PedExercicio $exercicio): View
    {
        $this->authorize('view', $exercicio->planificacao);

        $exercicio->load(['planificacao.turma', 'planificacao.disciplina', 'quinzena']);

        return view('pedagogico.exercicios.show', compact('exercicio'));
    }

    public function pdf(PedExercicio $exercicio): Response
    {
        $this->authorize('view', $exercicio->planificacao);

        $exercicio->load(['planificacao.turma', 'planificacao.disciplina', 'quinzena']);

        $pdf = $this->pdfExportService->loadView('pedagogico.pdf.exercicio', compact('exercicio'));

        return $pdf->download(sprintf('exercicio_%d.pdf', $exercicio->id));
    }
}
