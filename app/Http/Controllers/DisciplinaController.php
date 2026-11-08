<?php

namespace App\Http\Controllers;

use App\Http\Requests\DisciplinaRequest;
use App\Models\Classe;
use App\Models\Curso;
use App\Models\Disciplina;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DisciplinaController extends Controller
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('viewAny', Disciplina::class);

        $disciplinas = Disciplina::with(['cursos', 'classes'])
            ->orderBy('nome')
            ->paginate(10);

        return view('disciplinas.index', compact('disciplinas'));
    }

    public function create(): View
    {
        $this->authorize('create', Disciplina::class);

        $cursos = Curso::activo()->orderBy('nome')->get();
        $classes = Classe::queryPermitidas()->get();

        return view('disciplinas.create', compact('cursos', 'classes'));
    }

    public function store(DisciplinaRequest $request): RedirectResponse
    {
        $this->authorize('create', Disciplina::class);

        $data = $request->validated();
        $cursos = $data['cursos'];
        $classes = $data['classes'] ?? [];
        unset($data['cursos'], $data['classes']);

        $disciplina = Disciplina::create($data);
        $disciplina->cursos()->sync($cursos);
        $disciplina->classes()->sync($classes);

        return redirect()
            ->route('disciplinas.index')
            ->with('success', 'Disciplina criada com sucesso.');
    }

    public function show(Disciplina $disciplina): View
    {
        $this->authorize('view', $disciplina);

        $disciplina->load(['cursos', 'classes']);

        return view('disciplinas.show', compact('disciplina'));
    }

    public function edit(Disciplina $disciplina): View
    {
        $this->authorize('update', $disciplina);

        $cursos = Curso::activo()->orderBy('nome')->get();
        $classes = Classe::queryPermitidas()->get();
        $disciplina->load(['cursos', 'classes']);

        return view('disciplinas.edit', compact('disciplina', 'cursos', 'classes'));
    }

    public function update(DisciplinaRequest $request, Disciplina $disciplina): RedirectResponse
    {
        $this->authorize('update', $disciplina);

        $data = $request->validated();
        $cursos = $data['cursos'];
        $classes = $data['classes'] ?? [];
        unset($data['cursos'], $data['classes']);

        $disciplina->update($data);
        $disciplina->cursos()->sync($cursos);
        $disciplina->classes()->sync($classes);

        return redirect()
            ->route('disciplinas.index')
            ->with('success', 'Disciplina atualizada com sucesso.');
    }

    public function destroy(Disciplina $disciplina): RedirectResponse
    {
        $this->authorize('delete', $disciplina);

        if ($disciplina->notas()->exists()) {
            return redirect()
                ->route('disciplinas.index')
                ->with('error', 'Não é possível eliminar esta disciplina porque tem notas associadas.');
        }

        $disciplina->delete();

        return redirect()
            ->route('disciplinas.index')
            ->with('success', 'Disciplina eliminada com sucesso.');
    }
}
