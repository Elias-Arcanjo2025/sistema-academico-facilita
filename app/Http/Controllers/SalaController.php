<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalaRequest;
use App\Models\Sala;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SalaController extends Controller
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('viewAny', Sala::class);

        // Estatísticas gerais (antes da paginação)
        $totalSalas = Sala::count();
        $salasAtivas = Sala::where('activo', true)->count();
        $salasInativas = Sala::where('activo', false)->count();

        // Listagem paginada
        $salas = Sala::withCount('turmas')
            ->orderBy('nome')
            ->paginate(10);

        return view('salas.index', compact('salas', 'totalSalas', 'salasAtivas', 'salasInativas'));
    }

    public function create(): View
    {
        $this->authorize('create', Sala::class);

        return view('salas.create');
    }

    public function store(SalaRequest $request): RedirectResponse
    {
        $this->authorize('create', Sala::class);

        Sala::create($request->validated());

        return redirect()->route('salas.index')
            ->with('success', 'Sala criada com sucesso.');
    }

    public function show(Sala $sala): View
    {
        $this->authorize('view', $sala);

        $sala->load('turmas');

        return view('salas.show', compact('sala'));
    }

    public function edit(Sala $sala): View
    {
        $this->authorize('update', $sala);

        return view('salas.edit', compact('sala'));
    }

    public function update(SalaRequest $request, Sala $sala): RedirectResponse
    {
        $this->authorize('update', $sala);

        $sala->update($request->validated());

        return redirect()->route('salas.index')
            ->with('success', 'Sala actualizada com sucesso.');
    }

    public function destroy(Sala $sala): RedirectResponse
    {
        $this->authorize('delete', $sala);

        if ($sala->turmas()->exists()) {
            return redirect()->route('salas.index')
                ->with('error', 'Nao e possivel eliminar esta sala porque tem turmas associadas.');
        }

        $sala->delete();

        return redirect()->route('salas.index')
            ->with('success', 'Sala eliminada com sucesso.');
    }
}
