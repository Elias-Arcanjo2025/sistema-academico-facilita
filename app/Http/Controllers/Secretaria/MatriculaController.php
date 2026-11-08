<?php

namespace App\Http\Controllers\Secretaria;

use App\Http\Controllers\Controller;
use App\Http\Requests\AlunoRequest;
use App\Imports\MatriculasImport;
use App\Models\Aluno;
use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\MatriculaHistorico;
use App\Models\Turma;
use App\Services\AlunoAuthService;
use App\Services\MatriculaService;
use App\Support\AlunoImportTempFile;
use App\Support\AlunoImportTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MatriculaController extends Controller
{
    public function __construct(
        private readonly MatriculaService $matriculaService,
    ) {
        $this->middleware('can:secretaria.ver');
    }

    public function index(Request $request): View
    {
        $anoLectivoId = (int) $request->get('ano_lectivo_id', Configuracao::getAnoLectivoActualId());

        $historicos = MatriculaHistorico::query()
            ->with(['aluno', 'turma.curso', 'registadoPor', 'anoLectivo'])
            ->when($anoLectivoId, fn ($q) => $q->where('ano_lectivo_id', $anoLectivoId))
            ->when($request->get('tipo'), fn ($q, $t) => $q->where('tipo', $t))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('secretaria.matriculas.index', [
            'historicos' => $historicos,
            'anoLectivoId' => $anoLectivoId,
            'anosLectivos' => AnoLectivo::getParaSelect(),
            'tipoFiltro' => $request->get('tipo'),
            'tipos' => MatriculaHistorico::tipos(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Aluno::class);
        abort_unless(auth()->user()->can('secretaria.matriculas.gerir'), 403);

        $anoLectivoId = Configuracao::getAnoLectivoActualId();
        $turmas = Turma::activo()
            ->where('ano_lectivo_id', $anoLectivoId)
            ->with('curso')
            ->orderBy('nome')
            ->get();

        return view('secretaria.matriculas.create', compact('turmas'));
    }

    public function store(AlunoRequest $request): RedirectResponse
    {
        $this->authorize('create', Aluno::class);
        abort_unless(auth()->user()->can('secretaria.matriculas.gerir'), 403);

        $validated = $request->validated();
        unset($validated['foto']);

        if ($request->hasFile('foto')) {
            $validated['foto_path'] = $request->file('foto')->store('alunos/fotos', 'public');
        }

        $validated['activo'] = $request->boolean('activo');
        $validated['motivo'] = 'Matrícula via secretaria';

        $aluno = $this->matriculaService->matricular($validated, $request->user());

        return redirect()
            ->route('secretaria.matriculas.historico', $aluno)
            ->with('success', 'Matrícula registada com sucesso.');
    }

    public function renovarForm(Aluno $aluno): View
    {
        $this->authorize('update', $aluno);
        abort_unless(auth()->user()->can('secretaria.matriculas.gerir'), 403);

        $anoLectivoId = Configuracao::getAnoLectivoActualId();
        $turmas = Turma::activo()
            ->where('ano_lectivo_id', $anoLectivoId)
            ->with('curso')
            ->orderBy('nome')
            ->get();

        return view('secretaria.matriculas.renovar', compact('aluno', 'turmas'));
    }

    public function renovar(Request $request, Aluno $aluno): RedirectResponse
    {
        $this->authorize('update', $aluno);
        abort_unless(auth()->user()->can('secretaria.matriculas.gerir'), 403);

        $validated = $request->validate([
            'turma_id' => ['required', 'exists:turmas,id'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ]);

        $this->matriculaService->renovar($aluno, $validated, $request->user());

        return redirect()
            ->route('secretaria.matriculas.historico', $aluno)
            ->with('success', 'Renovação de matrícula concluída.');
    }

    public function historico(Aluno $aluno): View
    {
        $this->authorize('view', $aluno);

        $aluno->load(['turma.curso', 'historicosMatricula.turma', 'historicosMatricula.deTurma', 'historicosMatricula.paraTurma', 'historicosMatricula.registadoPor', 'historicosMatricula.anoLectivo']);

        return view('secretaria.matriculas.historico', [
            'aluno' => $aluno,
            'tipos' => MatriculaHistorico::tipos(),
        ]);
    }

    public function importForm(): View
    {
        $this->authorize('create', Aluno::class);
        abort_unless(auth()->user()->can('secretaria.matriculas.gerir'), 403);

        $anoLectivoId = Configuracao::getAnoLectivoActualId();
        $turmas = Turma::activo()
            ->where('ano_lectivo_id', $anoLectivoId)
            ->with('curso')
            ->orderBy('nome')
            ->get();

        return view('secretaria.matriculas.import', compact('turmas'));
    }

    public function importPreview(Request $request): View|RedirectResponse
    {
        $this->authorize('create', Aluno::class);
        abort_unless(auth()->user()->can('secretaria.matriculas.gerir'), 403);

        $request->validate([
            'turma_id' => ['nullable', 'exists:turmas,id'],
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:2048'],
        ]);

        $turmaId = $request->filled('turma_id') ? (int) $request->turma_id : null;
        $file = $request->file('file');

        try {
            $file = $request->file('file');
            $path = AlunoImportTempFile::storeFromUpload($file, (int) auth()->id());

            $rows = Excel::toCollection(new MatriculasImport($turmaId, $request->user()), $path)->first();
            $totalRows = $rows->count();
            $previewRows = $rows->take(50);
            $exceedsLimit = $totalRows > MatriculasImport::MAX_ROWS;

            $importToken = AlunoImportTempFile::registerInSession(
                $request,
                $path,
                (int) auth()->id(),
                $turmaId
            );

            return view('secretaria.matriculas.import-preview', [
                'previewRows' => $previewRows,
                'totalRows' => $totalRows,
                'exceedsLimit' => $exceedsLimit,
                'maxRows' => MatriculasImport::MAX_ROWS,
                'turmaId' => $turmaId,
                'turma' => $turmaId ? Turma::with('curso')->find($turmaId) : null,
                'importToken' => $importToken,
                'fileName' => $file->getClientOriginalName(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Erro no preview de importação de matrículas: '.$e->getMessage());

            return redirect()
                ->route('secretaria.matriculas.import')
                ->with('error', 'Não foi possível ler o ficheiro: '.$e->getMessage());
        }
    }

    public function importStore(Request $request): RedirectResponse
    {
        $this->authorize('create', Aluno::class);
        abort_unless(auth()->user()->can('secretaria.matriculas.gerir'), 403);

        $request->validate([
            'turma_id' => ['nullable', 'exists:turmas,id'],
            'file' => ['required_without:import_token', 'file', 'mimes:xlsx,xls,csv', 'max:2048'],
            'import_token' => ['required_without:file', 'string', 'size:64'],
        ]);

        $turmaId = $request->filled('turma_id') ? (int) $request->turma_id : null;
        $importToken = $request->input('import_token');
        $file = $request->file('file');
        $filePath = null;

        if ($importToken) {
            $filePath = AlunoImportTempFile::resolveFromSession($request, $importToken, (int) auth()->id());
            $file = $filePath;
        }

        $import = null;
        $importedCount = 0;
        $failures = collect();

        try {
            DB::transaction(function () use ($file, $turmaId, $request, &$import, &$importedCount, &$failures) {
                $import = new MatriculasImport($turmaId, $request->user());
                Excel::import($import, $file);
                $importedCount = $import->getImportedCount();
                $failures = $import->failures();

                if ($failures->count() > 0) {
                    throw new \RuntimeException('Falhas na importação');
                }
            }, 3);

            foreach ($import->getTurmasAfetadas() as $turmaIdAfetada) {
                $this->matriculaService->recalcularNumerosOrdem((int) $turmaIdAfetada);
            }

            try {
                $authService = app(AlunoAuthService::class);
                foreach ($import->getTurmasAfetadas() as $turmaIdAfetada) {
                    Aluno::query()
                        ->where('turma_id', $turmaIdAfetada)
                        ->where('activo', true)
                        ->each(fn (Aluno $a) => $authService->criarOuActualizarConta($a));
                }
            } catch (\Throwable $e) {
                Log::warning('Falha ao sincronizar contas após importação de matrículas: '.$e->getMessage());
            }

            if ($importToken) {
                AlunoImportTempFile::deleteFile($filePath, (int) auth()->id());
                AlunoImportTempFile::forgetSession($request, $importToken);
            }

            return redirect()
                ->route('secretaria.matriculas.index')
                ->with('success', "{$importedCount} matrículas importadas com sucesso.");
        } catch (\Throwable $e) {
            if ($importToken) {
                AlunoImportTempFile::deleteFile($filePath, (int) auth()->id());
                AlunoImportTempFile::forgetSession($request, $importToken);
            }

            if ($e->getMessage() === 'Falhas na importação' && $failures->count() > 0) {
                $msgs = $failures->map(function ($failure) {
                    return 'Linha '.$failure->row().': '.implode('; ', $failure->errors());
                })->take(20)->implode(' | ');

                return redirect()
                    ->route('secretaria.matriculas.import')
                    ->with('error', 'Importação cancelada. Corrija os erros: '.$msgs);
            }

            return redirect()
                ->route('secretaria.matriculas.import')
                ->with('error', 'Erro na importação: '.$e->getMessage());
        }
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        $this->authorize('create', Aluno::class);
        abort_unless(auth()->user()->can('secretaria.matriculas.gerir'), 403);

        return AlunoImportTemplate::download('template_importacao_alunos.xlsx');
    }
}
