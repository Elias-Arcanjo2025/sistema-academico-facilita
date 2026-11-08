<?php

namespace App\Http\Controllers\Secretaria;

use App\Http\Controllers\Controller;
use App\Models\Aluno;
use App\Models\Turma;
use App\Services\CartaoEstudanteService;
use App\Services\ConfiguracaoService;
use App\Services\PdfExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class CartaoController extends Controller
{
    public function __construct(
        private readonly CartaoEstudanteService $cartaoService,
        private readonly ConfiguracaoService $configuracaoService,
        private readonly PdfExportService $pdfExportService,
    ) {
        $this->middleware('can:secretaria.ver');
    }

    public function configurar(): View
    {
        abort_unless(
            auth()->user()->can('secretaria.cartao.configurar')
            || auth()->user()->temControloAcademicoInstitucional(),
            403
        );

        return view('secretaria.cartao.configurar', [
            'config' => $this->cartaoService->getConfig(),
            'instituicao' => $this->configuracaoService->getInstituicao(),
        ]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        abort_unless(
            auth()->user()->can('secretaria.cartao.configurar')
            || auth()->user()->temControloAcademicoInstitucional(),
            403
        );

        $validated = $request->validate([
            'titulo' => ['required', 'string', 'max:120'],
            'cor_primaria' => ['required', 'string', 'max:20'],
            'cor_secundaria' => ['required', 'string', 'max:20'],
            'cor_texto' => ['required', 'string', 'max:20'],
            'mostrar_foto' => ['nullable', 'boolean'],
            'mostrar_processo' => ['nullable', 'boolean'],
            'mostrar_bi' => ['nullable', 'boolean'],
            'mostrar_turma' => ['nullable', 'boolean'],
            'mostrar_curso' => ['nullable', 'boolean'],
            'mostrar_ano' => ['nullable', 'boolean'],
            'mostrar_validade' => ['nullable', 'boolean'],
            'mostrar_verso' => ['nullable', 'boolean'],
            'mostrar_logo' => ['nullable', 'boolean'],
            'validade_texto' => ['nullable', 'string', 'max:120'],
            'verso_texto' => ['nullable', 'string', 'max:500'],
            'orientacao' => ['required', 'in:landscape,portrait'],
        ]);

        $validated['mostrar_foto'] = $request->boolean('mostrar_foto');
        $validated['mostrar_processo'] = $request->boolean('mostrar_processo');
        $validated['mostrar_bi'] = $request->boolean('mostrar_bi');
        $validated['mostrar_turma'] = $request->boolean('mostrar_turma');
        $validated['mostrar_curso'] = $request->boolean('mostrar_curso');
        $validated['mostrar_ano'] = $request->boolean('mostrar_ano');
        $validated['mostrar_validade'] = $request->boolean('mostrar_validade');
        $validated['mostrar_verso'] = $request->boolean('mostrar_verso');
        $validated['mostrar_logo'] = $request->boolean('mostrar_logo');

        $this->cartaoService->guardar($validated);

        return back()->with('success', 'Personalização do cartão guardada.');
    }

    public function emitirForm(): View
    {
        abort_unless(
            auth()->user()->can('secretaria.documentos.emitir')
            || auth()->user()->temControloAcademicoInstitucional(),
            403
        );

        $turmas = Turma::activo()->with('curso')->orderBy('nome')->get();
        $alunos = Aluno::query()
            ->activo()
            ->orderBy('nome')
            ->limit(2000)
            ->get(['id', 'nome', 'numero_processo', 'turma_id']);

        $alunosOptions = $alunos->map(static function (Aluno $a): array {
            return [
                'id' => $a->id,
                'text' => $a->numero_processo.' — '.$a->nome,
                'turma_id' => $a->turma_id,
            ];
        })->values()->all();

        return view('secretaria.cartao.emitir', [
            'turmas' => $turmas,
            'alunosOptions' => $alunosOptions,
            'config' => $this->cartaoService->getConfig(),
        ]);
    }

    public function emitir(Request $request): Response|RedirectResponse
    {
        abort_unless(
            auth()->user()->can('secretaria.documentos.emitir')
            || auth()->user()->temControloAcademicoInstitucional(),
            403
        );

        $validated = $request->validate([
            'aluno_id' => ['nullable', 'exists:alunos,id'],
            'turma_id' => ['nullable', 'exists:turmas,id'],
        ]);

        if (empty($validated['aluno_id']) && empty($validated['turma_id'])) {
            return back()->with('error', 'Seleccione um aluno ou uma turma.');
        }

        $alunos = Aluno::query()
            ->with(['turma.curso', 'turma.anoLectivo'])
            ->when(! empty($validated['aluno_id']), fn ($q) => $q->where('id', $validated['aluno_id']))
            ->when(! empty($validated['turma_id']) && empty($validated['aluno_id']), fn ($q) => $q->where('turma_id', $validated['turma_id']))
            ->activo()
            ->orderBy('nome')
            ->get();

        if ($alunos->isEmpty()) {
            return back()->with('error', 'Nenhum aluno encontrado para emitir cartões.');
        }

        $instituicao = $this->configuracaoService->getInstituicao();
        $logoDataUri = $this->logoDataUri($instituicao['logo'] ?? null);
        $fotos = [];
        foreach ($alunos as $aluno) {
            $fotos[$aluno->id] = $this->fotoDataUri($aluno);
        }

        $pdf = $this->pdfExportService->loadView('exports.pdf.cartao-estudante-lote', [
            'alunos' => $alunos,
            'instituicao' => $instituicao,
            'cartao' => $this->cartaoService->getConfig(),
            'logoDataUri' => $logoDataUri,
            'fotos' => $fotos,
        ], 'landscape', 'a4');

        return $pdf->download('cartoes-estudante.pdf');
    }

    protected function logoDataUri(?string $logoPath): ?string
    {
        if (! $logoPath) {
            return null;
        }
        $full = storage_path('app/public/'.$logoPath);
        if (! is_file($full)) {
            return null;
        }

        return 'data:'.(mime_content_type($full) ?: 'image/png').';base64,'.base64_encode((string) file_get_contents($full));
    }

    protected function fotoDataUri(Aluno $aluno): ?string
    {
        if (! $aluno->foto_path) {
            return null;
        }
        $full = storage_path('app/public/'.$aluno->foto_path);
        if (! is_file($full)) {
            return null;
        }

        return 'data:'.(mime_content_type($full) ?: 'image/jpeg').';base64,'.base64_encode((string) file_get_contents($full));
    }
}
