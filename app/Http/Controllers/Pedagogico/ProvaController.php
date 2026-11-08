<?php

namespace App\Http\Controllers\Pedagogico;

use App\Http\Controllers\Controller;
use App\Jobs\Pedagogico\GerarProvaJob;
use App\Models\Nota;
use App\Models\Pedagogico\PedPlanificacao;
use App\Models\Pedagogico\PedProva;
use App\Services\Pedagogico\ChaveCorrecaoService;
use App\Services\Pedagogico\MatrizProvaService;
use App\Services\Pedagogico\PedagogicoAprovacaoService;
use App\Services\Pedagogico\BancoQuestaoService;
use App\Services\Pedagogico\PlanificacaoService;
use App\Services\Pedagogico\ProvaGeneratorService;
use App\Services\Pedagogico\ProvaPdfService;
use App\Services\PdfExportService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProvaController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private PlanificacaoService $planificacaoService,
        private ProvaGeneratorService $provaGeneratorService,
        private ProvaPdfService $provaPdfService,
        private MatrizProvaService $matrizProvaService,
        private ChaveCorrecaoService $chaveCorrecaoService,
        private PdfExportService $pdfExportService,
        private PedagogicoAprovacaoService $aprovacaoService,
        private BancoQuestaoService $bancoQuestaoService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', PedPlanificacao::class);

        $planificacaoId = $request->get('planificacao_id');
        $provas = collect();
        $planificacao = null;

        if ($planificacaoId) {
            $planificacao = PedPlanificacao::findOrFail($planificacaoId);
            $this->authorize('view', $planificacao);
            $provas = $planificacao->provas()->with(['turma', 'disciplina'])->latest()->get();
        }

        $planificacoes = $this->planificacaoService->listar(auth()->user(), ['per_page' => 100]);

        return view('pedagogico.provas.index', compact('provas', 'planificacao', 'planificacoes'));
    }

    public function create(Request $request): View
    {
        $this->authorize('viewAny', PedPlanificacao::class);
        abort_unless(auth()->user()->can('pedagogico.ia.gerar'), 403);

        $planificacoes = $this->planificacaoService->listar(auth()->user(), ['per_page' => 100]);
        $planificacaoId = $request->get('planificacao_id');
        $tipos = [Nota::TIPO_MAC, Nota::TIPO_PP, Nota::TIPO_PT];

        return view('pedagogico.provas.create', compact('planificacoes', 'planificacaoId', 'tipos'));
    }

    public function gerar(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('pedagogico.ia.gerar'), 403);

        $validated = $request->validate([
            'planificacao_id' => 'required|exists:ped_planificacoes,id',
            'tipo' => 'required|in:MAC,PP,PT',
            'tempo_minutos' => 'nullable|integer|min:30|max:300',
            'cotacao_total' => 'nullable|numeric|min:1|max:100',
        ]);

        $planificacao = PedPlanificacao::findOrFail($validated['planificacao_id']);
        $this->authorize('view', $planificacao);

        $config = [
            'tempo_minutos' => $validated['tempo_minutos'] ?? 90,
            'cotacao_total' => $validated['cotacao_total'] ?? 20,
        ];

        if (config('pedagogico.prova_sync', true)) {
            try {
                $prova = $this->provaGeneratorService->gerar(
                    $planificacao,
                    $validated['tipo'],
                    auth()->user(),
                    $config,
                );

                return redirect()
                    ->route('pedagogico.provas.show', $prova)
                    ->with('success', 'Prova gerada com sucesso.');
            } catch (\Throwable $e) {
                $mensagem = $this->mensagemErroGeracao($e);

                return redirect()
                    ->route('pedagogico.provas.index', ['planificacao_id' => $planificacao->id])
                    ->with('error', $mensagem);
            }
        }

        GerarProvaJob::dispatch(
            $planificacao->id,
            $validated['tipo'],
            auth()->id(),
            $config,
        );

        return redirect()
            ->route('pedagogico.provas.index', ['planificacao_id' => $planificacao->id])
            ->with('success', 'Geração de prova enfileirada. Execute o worker da fila ou active PEDAGOGICO_PROVA_SYNC=true.');
    }

    public function regenerar(PedProva $prova): RedirectResponse
    {
        abort_unless(auth()->user()->can('pedagogico.ia.gerar'), 403);
        $this->authorize('view', $prova->planificacao);

        try {
            $prova = $this->provaGeneratorService->regenerar($prova, auth()->user());

            return redirect()
                ->route('pedagogico.provas.show', $prova)
                ->with('success', 'Prova regenerada com sucesso.');
        } catch (\Throwable $e) {
            $mensagem = $this->mensagemErroGeracao($e);

            return redirect()
                ->route('pedagogico.provas.show', $prova)
                ->with('error', $mensagem);
        }
    }

    public function show(PedProva $prova): View
    {
        $this->authorize('view', $prova->planificacao);

        $prova->load(['planificacao', 'turma', 'disciplina', 'matriz', 'chaveCorrecao', 'correcoes']);

        $bancoQuestoes = collect();
        if ($prova->estado === PedProva::ESTADO_RASCUNHO && auth()->user()->can('pedagogico.banco.ver')) {
            $bancoQuestoes = \App\Models\Pedagogico\PedBancoQuestao::query()
                ->where('disciplina_id', $prova->disciplina_id)
                ->activas()
                ->orderBy('id', 'desc')
                ->limit(50)
                ->get();
        }

        return view('pedagogico.provas.show', compact('prova', 'bancoQuestoes'));
    }

    public function submeter(PedProva $prova): RedirectResponse
    {
        $this->authorize('view', $prova->planificacao);

        try {
            $this->aprovacaoService->submeterProva(auth()->user(), $prova);

            return back()->with('success', 'Prova submetida para aprovação.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function aprovar(PedProva $prova): RedirectResponse
    {
        abort_unless(auth()->user()->can('pedagogico.aprovar'), 403);

        try {
            $this->aprovacaoService->aprovarProva(auth()->user(), $prova);

            return back()->with('success', 'Prova aprovada.');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function rejeitar(Request $request, PedProva $prova): RedirectResponse
    {
        abort_unless(auth()->user()->can('pedagogico.aprovar'), 403);

        $data = $request->validate([
            'motivo_rejeicao' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->aprovacaoService->rejeitarProva(auth()->user(), $prova, $data['motivo_rejeicao']);

            return back()->with('success', 'Prova rejeitada.');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function publicar(PedProva $prova): RedirectResponse
    {
        $this->authorize('view', $prova->planificacao);

        try {
            $this->aprovacaoService->publicarProva(auth()->user(), $prova);

            return back()->with('success', 'Prova publicada.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function importarBanco(Request $request, PedProva $prova): RedirectResponse
    {
        $this->authorize('view', $prova->planificacao);
        abort_unless(auth()->user()->can('pedagogico.banco.ver'), 403);

        $data = $request->validate([
            'questao_ids' => ['required', 'array', 'min:1'],
            'questao_ids.*' => ['integer', 'exists:ped_banco_questoes,id'],
        ]);

        try {
            $this->bancoQuestaoService->importarParaProva(auth()->user(), $prova, $data['questao_ids']);

            return back()->with('success', 'Questões importadas do banco.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function pdf(PedProva $prova): Response
    {
        $this->authorize('view', $prova->planificacao);

        $dados = $this->provaPdfService->dadosView($prova);
        $pdfConfig = $dados['pdfConfig'];

        $pdf = $this->pdfExportService->loadView('pedagogico.pdf.prova', $dados);
        $pdf->setOptions([
            'defaultFont' => $pdfConfig['fonts']['family'] ?? 'DejaVu Sans',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'dpi' => 96,
        ]);

        return $pdf->download(sprintf('prova_%s_%d.pdf', $prova->tipo, $prova->id));
    }

    public function pdfMatriz(PedProva $prova): Response
    {
        $this->authorize('view', $prova->planificacao);

        $this->matrizProvaService->construir($prova);
        $prova->load(['turma', 'disciplina', 'matriz']);

        $pdf = $this->pdfExportService->loadView('pedagogico.pdf.matriz', compact('prova'), 'landscape');

        return $pdf->download(sprintf('matriz_prova_%d.pdf', $prova->id));
    }

    public function pdfChave(PedProva $prova): Response
    {
        $this->authorize('view', $prova->planificacao);

        $this->chaveCorrecaoService->construir($prova);
        $prova->load(['turma', 'disciplina', 'chaveCorrecao']);

        $pdf = $this->pdfExportService->loadView('pedagogico.pdf.chave', compact('prova'));

        return $pdf->download(sprintf('chave_prova_%d.pdf', $prova->id));
    }

    protected function mensagemErroGeracao(\Throwable $e): string
    {
        $msg = $e->getMessage();

        if (str_contains($msg, 'GEMINI_API_KEY não configurada')) {
            return 'Chave Gemini em falta. Crie uma gratuita em aistudio.google.com/apikey e adicione GEMINI_API_KEY no ficheiro .env.';
        }

        if (str_contains($msg, 'Quota Gemini') || str_contains($msg, 'RESOURCE_EXHAUSTED') || str_contains($msg, '429')) {
            return 'Quota gratuita da API Gemini esgotada. Aguarde até amanhã ou use outra chave/projeto no Google AI Studio.';
        }

        if (str_contains($msg, 'Créditos OpenRouter') || str_contains($msg, '402') || str_contains($msg, 'credits')) {
            return 'Créditos da API OpenRouter insuficientes. '
                .'Adicione créditos em openrouter.ai/settings/credits ou mude para Gemini (PEDAGOGICO_AI_DRIVER=gemini).';
        }

        if (str_contains($msg, 'JSON válido')) {
            return 'A IA devolveu uma resposta inválida. Tente gerar novamente em alguns segundos.';
        }

        return 'Erro ao gerar prova: '.Str::limit($msg, 300);
    }
}
