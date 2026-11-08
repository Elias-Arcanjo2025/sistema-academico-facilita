<?php

namespace App\Http\Controllers\Pedagogico;

use App\Http\Controllers\Controller;
use App\Jobs\Pedagogico\ProcessarCorrecaoJob;
use App\Models\Aluno;
use App\Models\Pedagogico\PedCorrecao;
use App\Models\Pedagogico\PedProva;
use App\Services\Pedagogico\CorrecaoAssistidaService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CorrecaoController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private CorrecaoAssistidaService $correcaoAssistidaService,
    ) {}

    public function index(Request $request): View
    {
        abort_unless(auth()->user()->can('pedagogico.correcao.usar'), 403);

        $provaId = $request->get('prova_id');
        $correcoes = PedCorrecao::with(['prova', 'aluno', 'professor'])
            ->when($provaId, fn ($q) => $q->where('prova_id', $provaId))
            ->latest()
            ->paginate(15);

        return view('pedagogico.correcoes.index', compact('correcoes', 'provaId'));
    }

    public function create(Request $request): View
    {
        abort_unless(auth()->user()->can('pedagogico.correcao.usar'), 403);

        $provaId = $request->get('prova_id');
        $provas = PedProva::with(['turma', 'disciplina'])->latest()->limit(50)->get();
        $alunos = collect();

        if ($provaId) {
            $prova = PedProva::findOrFail($provaId);
            $this->authorize('view', $prova->planificacao);
            $alunos = Aluno::where('turma_id', $prova->turma_id)->where('activo', true)->orderBy('nome')->get();
        }

        return view('pedagogico.correcoes.create', compact('provas', 'provaId', 'alunos'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('pedagogico.correcao.usar'), 403);

        $validated = $request->validate([
            'prova_id' => 'required|exists:ped_provas,id',
            'aluno_id' => 'nullable|exists:alunos,id',
            'ficheiro' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $prova = PedProva::findOrFail($validated['prova_id']);
        $this->authorize('view', $prova->planificacao);

        if (config('pedagogico.correcao_sync', true)) {
            try {
                $correcao = $this->correcaoAssistidaService->processar(
                    $prova,
                    $request->file('ficheiro'),
                    auth()->user(),
                    $validated['aluno_id'] ?? null,
                );

                return redirect()
                    ->route('pedagogico.correcoes.show', $correcao)
                    ->with('success', 'Correcção processada com sucesso. Revise a sugestão antes de confirmar.');
            } catch (\Throwable $e) {
                return redirect()
                    ->route('pedagogico.correcoes.create', ['prova_id' => $prova->id])
                    ->with('error', $this->mensagemErroProcessamento($e));
            }
        }

        $disk = config('pedagogico.ocr.storage_disk', 'local');
        $path = config('pedagogico.ocr.storage_path', 'pedagogico/correcoes');
        $ficheiroPath = $request->file('ficheiro')->store($path, $disk);

        ProcessarCorrecaoJob::dispatch(
            $prova->id,
            $ficheiroPath,
            auth()->id(),
            $validated['aluno_id'] ?? null,
        );

        return redirect()
            ->route('pedagogico.correcoes.index', ['prova_id' => $prova->id])
            ->with('success', 'Correcção enfileirada. Execute php artisan queue:work ou active PEDAGOGICO_CORRECAO_SYNC=true.');
    }

    public function show(PedCorrecao $correcao): View
    {
        $this->authorize('view', $correcao->prova->planificacao);

        $correcao->load(['prova.turma', 'prova.disciplina', 'aluno', 'itens']);

        return view('pedagogico.correcoes.show', compact('correcao'));
    }

    public function confirmar(Request $request, PedCorrecao $correcao): RedirectResponse
    {
        $this->authorize('view', $correcao->prova->planificacao);
        abort_unless(auth()->user()->can('pedagogico.correcao.usar'), 403);

        $validated = $request->validate([
            'nota_confirmada' => 'required|numeric|min:0|max:20',
            'itens' => 'required|array',
            'itens.*.id' => 'required|exists:ped_correcao_itens,id',
            'itens.*.pontos_confirmados' => 'required|numeric|min:0',
        ]);

        try {
            DB::transaction(function () use ($correcao, $validated) {
                foreach ($validated['itens'] as $itemData) {
                    $correcao->itens()
                        ->where('id', $itemData['id'])
                        ->update(['pontos_confirmados' => $itemData['pontos_confirmados']]);
                }

                $correcao->update([
                    'nota_confirmada' => $validated['nota_confirmada'],
                    'estado' => 'confirmada',
                ]);
            });

            return back()->with('success', 'Correcção confirmada.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function rejeitar(PedCorrecao $correcao): RedirectResponse
    {
        $this->authorize('view', $correcao->prova->planificacao);
        abort_unless(auth()->user()->can('pedagogico.correcao.usar'), 403);

        $correcao->update(['estado' => 'rejeitada']);

        return back()->with('success', 'Correcção rejeitada.');
    }

    protected function mensagemErroProcessamento(\Throwable $e): string
    {
        $msg = $e->getMessage();

        if (str_contains($msg, 'chave de correção')) {
            return 'Esta prova ainda não tem chave de correção. Gere ou regenere a prova primeiro.';
        }

        if (str_contains($msg, 'GEMINI_API_KEY não configurada')) {
            return 'Chave Gemini em falta. Configure GEMINI_API_KEY no ficheiro .env.';
        }

        if (str_contains($msg, 'Tesseract') || str_contains($msg, 'tesseract')) {
            return 'OCR local indisponível. Verifique se o Tesseract está instalado (ver docs/TESSERACT.md).';
        }

        if (str_contains($msg, 'Quota Gemini') || str_contains($msg, '429')) {
            return 'Quota gratuita da API Gemini esgotada. Tente novamente mais tarde.';
        }

        if (str_contains($msg, 'JSON válido')) {
            return 'A IA devolveu uma resposta inválida na correção. Tente enviar uma imagem mais nítida.';
        }

        return 'Erro ao processar correcção: '.Str::limit($msg, 300);
    }
}
