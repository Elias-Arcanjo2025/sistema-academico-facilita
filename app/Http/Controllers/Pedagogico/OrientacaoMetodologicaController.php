<?php

namespace App\Http\Controllers\Pedagogico;

use App\Http\Controllers\Controller;
use App\Models\AnoLectivo;
use App\Models\Classe;
use App\Models\Configuracao;
use App\Models\Disciplina;
use App\Models\OrientacaoMetodologica;
use App\Models\User;
use App\Services\Pedagogico\DisciplinaCoordEscopoService;
use App\Services\Pedagogico\OrientacaoMetodologicaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class OrientacaoMetodologicaController extends Controller
{
    public function __construct(
        private OrientacaoMetodologicaService $service,
        private DisciplinaCoordEscopoService $escopo,
    ) {
        $this->middleware('can:orientacoes.ver');
        $this->middleware('can:orientacoes.gerir')->only([
            'create', 'store', 'edit', 'update', 'publicar', 'arquivar',
        ]);
    }

    public function index(Request $request): View
    {
        $filtros = $request->only(['disciplina_id', 'classe_id', 'estado']);
        $itens = $this->service->queryVisivelPara($request->user(), $filtros)
            ->paginate(20)
            ->withQueryString();

        return view('pedagogico.orientacoes.index', [
            'itens' => $itens,
            'filtros' => $filtros,
            'disciplinas' => $this->disciplinasFiltro($request->user()),
            'classes' => Classe::queryPermitidas()->get(['id', 'nome']),
            'estados' => OrientacaoMetodologica::estados(),
            'podeGerir' => $this->service->podeGerir($request->user()),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->service->podeGerir($request->user()), 403);

        return view('pedagogico.orientacoes.create', [
            'pares' => $this->escopo->paresDisciplinaClasse($request->user()),
            'disciplinas' => $this->disciplinasCoord($request->user()),
            'classes' => $this->classesCoord($request->user()),
            'anosLectivos' => AnoLectivo::orderByDesc('nome')->pluck('nome', 'id'),
            'anoActualId' => Configuracao::getAnoLectivoActualId(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'disciplina_id' => ['required', 'exists:disciplinas,id'],
            'classe_id' => ['required', 'exists:classes,id'],
            'ano_lectivo_id' => ['nullable', 'exists:anos_lectivos,id'],
            'trimestre' => ['nullable', 'integer', 'in:1,2,3'],
            'titulo' => ['required', 'string', 'max:200'],
            'conteudo' => ['required', 'string', 'max:20000'],
        ]);

        $orientacao = $this->service->criar($request->user(), $data);

        return redirect()
            ->route('pedagogico.orientacoes.show', $orientacao)
            ->with('success', 'Orientação criada como rascunho.');
    }

    public function show(Request $request, OrientacaoMetodologica $orientacao): View
    {
        abort_unless($this->service->podeVer($request->user(), $orientacao), 403);

        $orientacao->load(['disciplina', 'classe', 'anoLectivo', 'autor', 'publicador']);

        return view('pedagogico.orientacoes.show', [
            'orientacao' => $orientacao,
            'estados' => OrientacaoMetodologica::estados(),
            'podeGerir' => $this->service->podeGerir($request->user(), $orientacao),
        ]);
    }

    public function edit(Request $request, OrientacaoMetodologica $orientacao): View
    {
        abort_unless($this->service->podeGerir($request->user(), $orientacao), 403);

        return view('pedagogico.orientacoes.edit', [
            'orientacao' => $orientacao,
        ]);
    }

    public function update(Request $request, OrientacaoMetodologica $orientacao): RedirectResponse
    {
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:200'],
            'conteudo' => ['required', 'string', 'max:20000'],
            'trimestre' => ['nullable', 'integer', 'in:1,2,3'],
        ]);

        $this->service->actualizar($request->user(), $orientacao, $data);

        return redirect()
            ->route('pedagogico.orientacoes.show', $orientacao)
            ->with('success', 'Orientação actualizada.');
    }

    public function publicar(Request $request, OrientacaoMetodologica $orientacao): RedirectResponse
    {
        $this->service->publicar($request->user(), $orientacao);

        return redirect()
            ->route('pedagogico.orientacoes.show', $orientacao)
            ->with('success', 'Orientação publicada. Professores da disciplina foram notificados.');
    }

    public function arquivar(Request $request, OrientacaoMetodologica $orientacao): RedirectResponse
    {
        $this->service->arquivar($request->user(), $orientacao);

        return redirect()
            ->route('pedagogico.orientacoes.show', $orientacao)
            ->with('success', 'Orientação arquivada.');
    }

    private function disciplinasFiltro(User $user): Collection
    {
        return $this->disciplinasCoord($user);
    }

    private function disciplinasCoord(User $user): Collection
    {
        if ($user->temVisaoInstitucional()) {
            return Disciplina::where('activo', true)->orderBy('nome')->get(['id', 'nome']);
        }

        $ids = $this->escopo->disciplinaIds($user);

        return Disciplina::whereIn('id', $ids)->orderBy('nome')->get(['id', 'nome']);
    }

    private function classesCoord(User $user): Collection
    {
        if ($user->temVisaoInstitucional()) {
            return Classe::queryPermitidas()->get(['id', 'nome']);
        }

        $ids = $this->escopo->classeIds($user);
        $permitidas = \App\Support\EnsinoPerfil::classesPermitidas();

        return Classe::query()
            ->whereIn('id', $ids)
            ->whereIn('ordem', $permitidas)
            ->ordenado()
            ->get(['id', 'nome']);
    }
}
