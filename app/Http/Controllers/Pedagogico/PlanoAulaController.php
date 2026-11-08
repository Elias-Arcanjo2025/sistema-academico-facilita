<?php

namespace App\Http\Controllers\Pedagogico;

use App\Http\Controllers\Controller;
use App\Jobs\Pedagogico\GerarPlanoAulaJob;
use App\Models\Pedagogico\PedAula;
use App\Models\Pedagogico\PedPlanificacao;
use App\Models\Pedagogico\PedPlanoAula;
use App\Models\Pedagogico\PedQuinzena;
use App\Services\Pedagogico\PlanoAulaGeneratorService;
use App\Services\PdfExportService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PlanoAulaController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private PlanoAulaGeneratorService $planoAulaGenerator,
        private PdfExportService $pdfExportService,
    ) {}

    public function index(PedPlanificacao $planificacao): View
    {
        $this->authorize('view', $planificacao);

        $planificacao->load(['planosAula.quinzena', 'planosAula.aula', 'turma', 'disciplina', 'quinzenas', 'aulas.quinzena']);

        return view('pedagogico.planos.index', compact('planificacao'));
    }

    public function gerar(Request $request, PedPlanificacao $planificacao): RedirectResponse
    {
        $this->authorize('view', $planificacao);
        abort_unless(auth()->user()->can('pedagogico.ia.gerar'), 403);

        $validated = $request->validate([
            'quinzena_id' => 'nullable|exists:ped_quinzenas,id',
            'aula_id' => 'nullable|exists:ped_aulas,id',
            'tempos' => 'required|integer|min:1|max:'.config('pedagogico.tempos_max', 8),
        ]);

        $quinzena = isset($validated['quinzena_id'])
            ? PedQuinzena::find($validated['quinzena_id'])
            : null;
        $aula = isset($validated['aula_id'])
            ? PedAula::find($validated['aula_id'])
            : null;

        if (config('pedagogico.plano_aula_sync', true)) {
            try {
                $plano = $this->planoAulaGenerator->gerar(
                    $planificacao,
                    $quinzena,
                    $aula,
                    auth()->user(),
                    (int) $validated['tempos'],
                );

                return redirect()
                    ->route('pedagogico.planos.show', $plano)
                    ->with('success', 'Plano de aula gerado com sucesso.');
            } catch (\Throwable $e) {
                return back()->with('error', 'Erro ao gerar plano: '.$e->getMessage());
            }
        }

        GerarPlanoAulaJob::dispatch(
            $planificacao->id,
            $validated['quinzena_id'] ?? null,
            $validated['aula_id'] ?? null,
            auth()->id(),
            null,
            (int) $validated['tempos'],
        );

        return back()->with('success', 'Geração enfileirada. Actualize a página em alguns segundos.');
    }

    public function show(PedPlanoAula $planoAula): View
    {
        $this->authorize('view', $planoAula->planificacao);

        $planoAula->load(['planificacao.turma', 'planificacao.disciplina', 'quinzena', 'aula']);

        return view('pedagogico.planos.show', compact('planoAula'));
    }

    public function update(Request $request, PedPlanoAula $planoAula): RedirectResponse
    {
        $this->authorize('update', $planoAula->planificacao);

        $validated = $request->validate([
            'conteudo_json' => 'nullable|array',
            'estado' => 'nullable|in:rascunho,aprovado,publicado',
        ]);

        $planoAula->update(collect($validated)->filter()->all());

        return back()->with('success', 'Plano de aula actualizado.');
    }

    public function pdf(PedPlanoAula $planoAula): Response
    {
        $this->authorize('view', $planoAula->planificacao);

        $planoAula->load(['planificacao.turma', 'planificacao.disciplina', 'quinzena']);

        $pdf = $this->pdfExportService->loadView('pedagogico.pdf.plano-aula', compact('planoAula'));

        $nome = sprintf('plano_aula_%d.pdf', $planoAula->id);

        return $pdf->download($nome);
    }

    public function marcarComoDado(PedPlanoAula $planoAula): RedirectResponse
    {
        $this->authorize('update', $planoAula->planificacao);

        $planoAula->update([
            'estado' => PedPlanoAula::ESTADO_DADO,
            'ministrado_em' => now(),
        ]);

        if ($planoAula->aula_id) {
            $aula = PedAula::find($planoAula->aula_id);
            if ($aula && $aula->estado === PedAula::ESTADO_PLANEADA) {
                $aula->update([
                    'estado' => PedAula::ESTADO_MINISTRADA,
                    'tema_ministrado' => $planoAula->conteudo_json['tema'] ?? $aula->tema_planeado,
                    'conteudo_ministrado' => $planoAula->conteudo_json['conteudo_ditar'] ?? null,
                ]);
            }
        }

        return back()->with('success', 'Plano marcado como dado/ministrado.');
    }
}
