<?php

namespace App\Http\Controllers\Pedagogico;

use App\Http\Controllers\Controller;
use App\Models\AnoLectivo;
use App\Models\Classe;
use App\Models\ConteudoMinimo;
use App\Models\ConteudoMinimoItem;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Services\Pedagogico\ConteudoMinimoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConteudoMinimoController extends Controller
{
    public function __construct(
        private ConteudoMinimoService $service,
    ) {
        $this->middleware('can:conteudos.ver');
        $this->middleware('can:conteudos.gerir')->only([
            'create', 'store', 'edit', 'update', 'storeItem', 'updateItem', 'destroyItem',
        ]);
        $this->middleware('can:conteudos.publicar')->only(['publicar', 'arquivar']);
    }

    public function index(Request $request): View
    {
        $filtros = $request->only(['disciplina_id', 'classe_id', 'estado']);
        $programas = $this->service->queryVisivelPara($request->user(), $filtros)->paginate(20)->withQueryString();

        return view('pedagogico.conteudos.index', [
            'programas' => $programas,
            'filtros' => $filtros,
            'disciplinas' => Disciplina::where('activo', true)->orderBy('nome')->get(['id', 'nome']),
            'classes' => Classe::queryPermitidas()->get(['id', 'nome']),
            'estados' => ConteudoMinimo::estados(),
            'podeGerir' => $request->user()->can('conteudos.gerir'),
        ]);
    }

    public function create(Request $request): View
    {
        return view('pedagogico.conteudos.create', [
            'disciplinas' => Disciplina::where('activo', true)->orderBy('nome')->get(['id', 'nome']),
            'classes' => Classe::queryPermitidas()->get(['id', 'nome']),
            'cursos' => Curso::where('activo', true)->orderBy('nome')->get(['id', 'nome']),
            'anosLectivos' => AnoLectivo::orderByDesc('nome')->pluck('nome', 'id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'disciplina_id' => ['required', 'exists:disciplinas,id'],
            'classe_id' => ['required', 'exists:classes,id'],
            'curso_id' => ['nullable', 'exists:cursos,id'],
            'ano_lectivo_id' => ['nullable', 'exists:anos_lectivos,id'],
            'trimestre' => ['nullable', 'integer', 'in:1,2,3'],
            'titulo' => ['required', 'string', 'max:200'],
            'codigo_oficial' => ['nullable', 'string', 'max:64'],
            'descricao' => ['nullable', 'string', 'max:5000'],
        ]);

        $conteudo = $this->service->criar($request->user(), $data);

        return redirect()
            ->route('pedagogico.conteudos.show', $conteudo)
            ->with('success', 'Programa criado como rascunho.');
    }

    public function show(Request $request, ConteudoMinimo $conteudo): View
    {
        abort_unless($this->service->podeVer($request->user(), $conteudo), 403);

        $conteudo->load(['disciplina', 'classe', 'curso', 'anoLectivo', 'criador', 'publicador', 'itensRaiz.filhos']);

        return view('pedagogico.conteudos.show', [
            'conteudo' => $conteudo,
            'estados' => ConteudoMinimo::estados(),
            'podeGerir' => $this->service->podeGerir($request->user(), $conteudo),
            'podePublicar' => $this->service->podePublicar($request->user(), $conteudo),
            'podeNovaVersao' => $conteudo->isPublicado() || $conteudo->estado === ConteudoMinimo::ESTADO_ARQUIVADO,
        ]);
    }

    public function edit(Request $request, ConteudoMinimo $conteudo): View
    {
        abort_unless($this->service->podeGerir($request->user(), $conteudo), 403);

        return view('pedagogico.conteudos.edit', [
            'conteudo' => $conteudo,
            'cursos' => Curso::where('activo', true)->orderBy('nome')->get(['id', 'nome']),
            'anosLectivos' => AnoLectivo::orderByDesc('nome')->pluck('nome', 'id'),
        ]);
    }

    public function update(Request $request, ConteudoMinimo $conteudo): RedirectResponse
    {
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:200'],
            'codigo_oficial' => ['nullable', 'string', 'max:64'],
            'descricao' => ['nullable', 'string', 'max:5000'],
            'curso_id' => ['nullable', 'exists:cursos,id'],
            'ano_lectivo_id' => ['nullable', 'exists:anos_lectivos,id'],
            'trimestre' => ['nullable', 'integer', 'in:1,2,3'],
        ]);

        $this->service->actualizar($request->user(), $conteudo, $data);

        return redirect()
            ->route('pedagogico.conteudos.show', $conteudo)
            ->with('success', 'Programa actualizado.');
    }

    public function storeItem(Request $request, ConteudoMinimo $conteudo): RedirectResponse
    {
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'codigo' => ['nullable', 'string', 'max:64'],
            'descricao' => ['nullable', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'exists:conteudo_minimo_itens,id'],
            'ordem' => ['nullable', 'integer', 'min:0'],
            'obrigatorio' => ['nullable', 'boolean'],
            'carga_horaria_sugerida' => ['nullable', 'integer', 'min:0', 'max:999'],
            'trimestre_sugerido' => ['nullable', 'integer', 'in:1,2,3'],
        ]);

        $data['obrigatorio'] = $request->boolean('obrigatorio', true);
        $this->service->adicionarItem($request->user(), $conteudo, $data);

        return back()->with('success', 'Item adicionado.');
    }

    public function updateItem(Request $request, ConteudoMinimo $conteudo, ConteudoMinimoItem $item): RedirectResponse
    {
        abort_unless((int) $item->conteudo_minimo_id === (int) $conteudo->id, 404);

        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'codigo' => ['nullable', 'string', 'max:64'],
            'descricao' => ['nullable', 'string', 'max:5000'],
            'ordem' => ['nullable', 'integer', 'min:0'],
            'obrigatorio' => ['nullable', 'boolean'],
            'carga_horaria_sugerida' => ['nullable', 'integer', 'min:0', 'max:999'],
            'trimestre_sugerido' => ['nullable', 'integer', 'in:1,2,3'],
        ]);

        $data['obrigatorio'] = $request->boolean('obrigatorio', true);
        $this->service->actualizarItem($request->user(), $item, $data);

        return back()->with('success', 'Item actualizado.');
    }

    public function destroyItem(Request $request, ConteudoMinimo $conteudo, ConteudoMinimoItem $item): RedirectResponse
    {
        abort_unless((int) $item->conteudo_minimo_id === (int) $conteudo->id, 404);
        $this->service->eliminarItem($request->user(), $item);

        return back()->with('success', 'Item removido.');
    }

    public function publicar(Request $request, ConteudoMinimo $conteudo): RedirectResponse
    {
        $this->service->publicar($request->user(), $conteudo);

        return back()->with('success', 'Programa publicado. Versões anteriores do mesmo âmbito foram arquivadas.');
    }

    public function arquivar(Request $request, ConteudoMinimo $conteudo): RedirectResponse
    {
        $this->service->arquivar($request->user(), $conteudo);

        return back()->with('success', 'Programa arquivado.');
    }

    public function novaVersao(Request $request, ConteudoMinimo $conteudo): RedirectResponse
    {
        $novo = $this->service->novaVersao($request->user(), $conteudo);

        return redirect()
            ->route('pedagogico.conteudos.show', $novo)
            ->with('success', 'Nova versão criada como rascunho (v'.$novo->versao.').');
    }
}
