<?php

namespace App\Http\Controllers;

use App\Models\AnoLectivo;
use App\Models\Configuracao;
use App\Models\Disciplina;
use App\Models\MaterialAcademico;
use App\Models\Turma;
use App\Services\MaterialAcademicoService;
use App\Services\Pedagogico\DisciplinaCoordEscopoService;
use App\Services\Pedagogico\MaterialApoioIaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MaterialAcademicoController extends Controller
{
    public function __construct(
        private MaterialAcademicoService $materiais,
        private MaterialApoioIaService $materialIa,
        private DisciplinaCoordEscopoService $escopo,
    ) {
        $this->middleware('can:materiais.ver');
        $this->middleware('can:materiais.gerir')->only(['create', 'store', 'destroy', 'gerarIaForm', 'gerarIa']);
        $this->middleware('can:pedagogico.ia.gerar')->only(['gerarIaForm', 'gerarIa']);
    }

    public function index(Request $request): View
    {
        $anoLectivoId = $request->get('ano_lectivo_id', Configuracao::getAnoLectivoActualId());
        $query = $this->materiais->queryVisivelPara($request->user(), $anoLectivoId ? (int) $anoLectivoId : null);

        if ($request->filled('disciplina_id')) {
            $query->where('disciplina_id', $request->disciplina_id);
        }
        if ($request->filled('turma_id')) {
            $query->where('turma_id', $request->turma_id);
        }

        return view('materiais.index', [
            'materiais' => $query->latest()->paginate(20)->withQueryString(),
            'filtros' => $request->only(['disciplina_id', 'turma_id', 'ano_lectivo_id']),
            'disciplinas' => Disciplina::where('activo', true)->orderBy('nome')->get(['id', 'nome']),
            'turmas' => Turma::where('ano_lectivo_id', $anoLectivoId)->orderBy('nome')->get(['id', 'nome']),
            'anosLectivos' => AnoLectivo::orderByDesc('nome')->pluck('nome', 'id'),
            'anoLectivoId' => $anoLectivoId,
        ]);
    }

    public function create(): View
    {
        $anoId = Configuracao::getAnoLectivoActualId();

        return view('materiais.create', [
            'disciplinas' => Disciplina::where('activo', true)->orderBy('nome')->get(['id', 'nome']),
            'turmas' => Turma::where('ano_lectivo_id', $anoId)->orderBy('nome')->get(['id', 'nome']),
            'anosLectivos' => AnoLectivo::orderByDesc('nome')->pluck('nome', 'id'),
            'anoLectivoId' => $anoId,
        ]);
    }

    public function gerarIaForm(Request $request): View
    {
        $user = $request->user();
        $anoId = Configuracao::getAnoLectivoActualId();

        if ($user->temVisaoInstitucional()) {
            $disciplinas = Disciplina::where('activo', true)->orderBy('nome')->get(['id', 'nome']);
        } else {
            $ids = $this->escopo->disciplinaIds($user);
            $disciplinas = Disciplina::whereIn('id', $ids)->orderBy('nome')->get(['id', 'nome']);
        }

        return view('materiais.gerar-ia', [
            'disciplinas' => $disciplinas,
            'anosLectivos' => AnoLectivo::orderByDesc('nome')->pluck('nome', 'id'),
            'anoLectivoId' => $anoId,
        ]);
    }

    public function gerarIa(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'disciplina_id' => ['required', 'exists:disciplinas,id'],
            'tema' => ['required', 'string', 'max:300'],
            'titulo' => ['nullable', 'string', 'max:200'],
            'tipo' => ['required', 'in:ficha_apoio,material_apoio,resumo_materia,explicacao'],
            'classe' => ['nullable', 'string', 'max:80'],
            'ano_lectivo_id' => ['required', 'exists:anos_lectivos,id'],
        ]);

        $material = $this->materialIa->gerar($request->user(), $data);

        return redirect()
            ->route('materiais.index')
            ->with('success', 'Material gerado por IA: '.$material->titulo);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:200'],
            'descricao' => ['nullable', 'string', 'max:5000'],
            'disciplina_id' => ['nullable', 'exists:disciplinas,id'],
            'turma_id' => ['nullable', 'exists:turmas,id'],
            'ano_lectivo_id' => ['required', 'exists:anos_lectivos,id'],
            'publicado' => ['nullable', 'boolean'],
            'ficheiro' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,png,jpg,jpeg,zip'],
        ]);

        $data['publicado'] = $request->boolean('publicado', true);
        $this->materiais->criar($request->user(), $data, $request->file('ficheiro'));

        return redirect()->route('materiais.index')
            ->with('success', 'Material publicado.');
    }

    public function download(Request $request, MaterialAcademico $material): BinaryFileResponse
    {
        if (! $this->materiais->podeDescarregar($request->user(), $material)) {
            abort(403);
        }

        return Response::download(
            $this->materiais->caminhoAbsoluto($material),
            $this->materiais->nomeDownload($material)
        );
    }

    public function destroy(Request $request, MaterialAcademico $material): RedirectResponse
    {
        $this->materiais->eliminar($request->user(), $material);

        return back()->with('success', 'Material removido.');
    }
}
