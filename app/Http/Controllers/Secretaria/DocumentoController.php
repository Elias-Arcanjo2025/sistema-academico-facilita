<?php

namespace App\Http\Controllers\Secretaria;

use App\Http\Controllers\Controller;
use App\Models\Aluno;
use App\Models\AlunoDocumento;
use App\Models\Turma;
use App\Services\CartaoEstudanteService;
use App\Services\ConfiguracaoService;
use App\Services\DocumentoAcademicoService;
use App\Services\PdfExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentoController extends Controller
{
    public function __construct(
        private readonly PdfExportService $pdfExportService,
        private readonly ConfiguracaoService $configuracaoService,
        private readonly CartaoEstudanteService $cartaoService,
        private readonly DocumentoAcademicoService $documentoService,
    ) {
        $this->middleware('can:secretaria.ver');
    }

    public function index(): View
    {
        abort_unless(
            auth()->user()->can('secretaria.documentos.emitir')
            || auth()->user()->temControloAcademicoInstitucional(),
            403
        );

        $turmas = Turma::activo()->with('curso')->orderBy('nome')->get();

        return view('secretaria.documentos.index', compact('turmas'));
    }

    public function configurar(): View
    {
        abort_unless(
            auth()->user()->can('secretaria.documentos.emitir')
            || auth()->user()->can('secretaria.cartao.configurar')
            || auth()->user()->temControloAcademicoInstitucional(),
            403
        );

        return view('secretaria.documentos.configurar', [
            'config' => $this->documentoService->getConfig(),
            'instituicao' => $this->configuracaoService->getInstituicao(),
        ]);
    }

    public function guardarConfig(Request $request): RedirectResponse
    {
        abort_unless(
            auth()->user()->can('secretaria.documentos.emitir')
            || auth()->user()->can('secretaria.cartao.configurar')
            || auth()->user()->temControloAcademicoInstitucional(),
            403
        );

        $validated = $request->validate([
            'titulo_frequencia' => ['required', 'string', 'max:120'],
            'titulo_matricula' => ['required', 'string', 'max:120'],
            'titulo_comprovativo' => ['required', 'string', 'max:120'],
            'titulo_ficha' => ['required', 'string', 'max:120'],
            'cor_destaque' => ['required', 'string', 'max:20'],
            'mostrar_logo' => ['nullable', 'boolean'],
            'mostrar_bi' => ['nullable', 'boolean'],
            'mostrar_filiacao' => ['nullable', 'boolean'],
            'mostrar_encarregado' => ['nullable', 'boolean'],
            'mostrar_nascimento' => ['nullable', 'boolean'],
            'mostrar_genero' => ['nullable', 'boolean'],
            'mostrar_foto_ficha' => ['nullable', 'boolean'],
            'mostrar_assinatura' => ['nullable', 'boolean'],
            'cargo_assinatura' => ['nullable', 'string', 'max:120'],
            'texto_rodape' => ['nullable', 'string', 'max:255'],
            'texto_fecho_frequencia' => ['nullable', 'string', 'max:500'],
            'texto_fecho_matricula' => ['nullable', 'string', 'max:500'],
            'local_emissao' => ['nullable', 'string', 'max:255'],
        ]);

        foreach ([
            'mostrar_logo', 'mostrar_bi', 'mostrar_filiacao', 'mostrar_encarregado',
            'mostrar_nascimento', 'mostrar_genero', 'mostrar_foto_ficha', 'mostrar_assinatura',
        ] as $campo) {
            $validated[$campo] = $request->boolean($campo);
        }

        $this->documentoService->guardar($validated);

        return back()->with('success', 'Personalização dos documentos guardada.');
    }

    public function emitir(Request $request): Response|RedirectResponse
    {
        abort_unless(
            auth()->user()->can('secretaria.documentos.emitir')
            || auth()->user()->temControloAcademicoInstitucional(),
            403
        );

        $validated = $request->validate([
            'aluno_id' => ['required', 'exists:alunos,id'],
            'tipo' => ['required', 'in:frequencia,matricula,comprovativo,ficha,cartao'],
        ]);

        $aluno = Aluno::with(['turma.curso', 'turma.classe', 'turma.anoLectivo', 'encarregados'])->findOrFail($validated['aluno_id']);
        $this->authorize('view', $aluno);

        $instituicao = $this->configuracaoService->getInstituicao();
        $logoDataUri = $this->resolveLogoDataUri($instituicao['logo'] ?? null);
        $docConfig = $this->documentoService->getConfig();

        $views = [
            'frequencia' => 'exports.pdf.declaracao-frequencia',
            'matricula' => 'exports.pdf.declaracao-matricula',
            'comprovativo' => 'exports.pdf.comprovativo-inscricao',
            'ficha' => 'exports.pdf.ficha-aluno',
            'cartao' => 'exports.pdf.cartao-estudante',
        ];

        $data = [
            'aluno' => $aluno,
            'instituicao' => $instituicao,
            'logoDataUri' => $logoDataUri,
            'emitidoEm' => now(),
            'cartao' => $this->cartaoService->getConfig(),
            'docConfig' => $docConfig,
            'fotoDataUri' => $this->resolveFotoDataUri($aluno),
        ];

        if ($validated['tipo'] === 'cartao') {
            $pdf = $this->pdfExportService->loadView($views['cartao'], $data, 'portrait', 'a4');
        } else {
            $pdf = $this->pdfExportService->loadView($views[$validated['tipo']], $data);
        }

        $filename = $validated['tipo'].'-'.$aluno->numero_processo.'.pdf';

        return $pdf->download($filename);
    }

    public function storeDocumento(Request $request, Aluno $aluno): RedirectResponse
    {
        $this->authorize('update', $aluno);
        abort_unless(auth()->user()->can('secretaria.alunos.documentos') || auth()->user()->temAcessoSecretariaAlunos(), 403);

        $validated = $request->validate([
            'tipo' => ['required', 'in:'.implode(',', array_keys(AlunoDocumento::tipos()))],
            'titulo' => ['required', 'string', 'max:255'],
            'ficheiro' => ['required', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        $file = $request->file('ficheiro');
        $path = $file->store('alunos/documentos/'.$aluno->id, 'public');

        AlunoDocumento::create([
            'aluno_id' => $aluno->id,
            'tipo' => $validated['tipo'],
            'titulo' => $validated['titulo'],
            'path' => $path,
            'mime' => $file->getMimeType(),
            'tamanho' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Documento anexado com sucesso.');
    }

    public function destroyDocumento(Aluno $aluno, AlunoDocumento $documento): RedirectResponse
    {
        $this->authorize('update', $aluno);
        abort_unless(auth()->user()->can('secretaria.alunos.documentos') || auth()->user()->temAcessoSecretariaAlunos(), 403);
        abort_unless((int) $documento->aluno_id === (int) $aluno->id, 404);

        Storage::disk('public')->delete($documento->path);
        $documento->delete();

        return back()->with('success', 'Documento removido.');
    }

    public function downloadDocumento(Aluno $aluno, AlunoDocumento $documento): StreamedResponse
    {
        $this->authorize('view', $aluno);
        abort_unless((int) $documento->aluno_id === (int) $aluno->id, 404);

        return Storage::disk('public')->download($documento->path, $documento->titulo);
    }

    protected function resolveLogoDataUri(?string $logoPath): ?string
    {
        if (! $logoPath) {
            return null;
        }
        $full = storage_path('app/public/'.$logoPath);
        if (! is_file($full)) {
            return null;
        }
        $mime = mime_content_type($full) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($full));
    }

    protected function resolveFotoDataUri(Aluno $aluno): ?string
    {
        if (! $aluno->foto_path) {
            return null;
        }
        $full = storage_path('app/public/'.$aluno->foto_path);
        if (! is_file($full)) {
            return null;
        }
        $mime = mime_content_type($full) ?: 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($full));
    }
}
