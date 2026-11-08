<?php

namespace App\Http\Controllers;

use App\Http\Requests\TurnoRequest;
use App\Models\Turno;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TurnoController extends Controller
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('viewAny', Turno::class);

        $turnos = Turno::withCount('turmas')
            ->orderBy('nome')
            ->paginate(10);

        return view('turnos.index', compact('turnos'));
    }

    public function create(): View
    {
        $this->authorize('create', Turno::class);

        return view('turnos.create');
    }

    public function store(TurnoRequest $request): RedirectResponse
    {
        $this->authorize('create', Turno::class);

        Turno::create($request->validated());

        return redirect()->route('turnos.index')
            ->with('success', 'Turno criado com sucesso.');
    }

    public function show(Turno $turno): View
    {
        $this->authorize('view', $turno);

        $turno->load('turmas');

        return view('turnos.show', compact('turno'));
    }

    public function edit(Turno $turno): View
    {
        $this->authorize('update', $turno);

        return view('turnos.edit', compact('turno'));
    }

    public function update(TurnoRequest $request, Turno $turno): RedirectResponse
    {
        $this->authorize('update', $turno);

        $turno->update($request->validated());

        return redirect()->route('turnos.index')
            ->with('success', 'Turno actualizado com sucesso.');
    }

    public function destroy(Turno $turno): RedirectResponse
    {
        $this->authorize('delete', $turno);

        if ($turno->turmas()->exists()) {
            return redirect()->route('turnos.index')
                ->with('error', 'Nao e possivel eliminar este turno porque tem turmas associadas.');
        }

        $turno->delete();

        return redirect()->route('turnos.index')
            ->with('success', 'Turno eliminado com sucesso.');
    }
}
