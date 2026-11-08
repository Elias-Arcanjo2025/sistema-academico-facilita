<?php

namespace App\Http\Controllers;

use App\Support\EnsinoPerfil;
use App\Support\SetupInicial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SetupInicialController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function show(): View|RedirectResponse
    {
        if (! SetupInicial::estaPendente()) {
            return redirect()->route('dashboard');
        }

        abort_unless(auth()->user()?->hasRole('admin_full'), 403);

        return view('setup.inicial', [
            'perfis' => EnsinoPerfil::perfisActivos(),
            'rotulos' => EnsinoPerfil::todosRotulos(),
            'mapa' => config('ensino.mapa', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->hasRole('admin_full'), 403);
        abort_unless(SetupInicial::estaPendente(), 403);

        $validos = array_keys(config('ensino.mapa', []));

        $validated = $request->validate([
            'perfis' => ['required', 'array', 'min:1'],
            'perfis.*' => ['string', 'in:'.implode(',', $validos)],
        ], [
            'perfis.required' => 'Seleccione pelo menos um perfil de ensino.',
            'perfis.min' => 'Seleccione pelo menos um perfil de ensino.',
        ]);

        $resultado = EnsinoPerfil::gravarESincronizar($validated['perfis']);
        SetupInicial::marcarConcluido();

        activity()
            ->causedBy(auth()->user())
            ->withProperties([
                'perfis' => $validated['perfis'],
                'classes' => $resultado['permitidas'],
            ])
            ->log('Configuração inicial concluída');

        $lista = implode(', ', array_map(fn ($n) => $n.'ª', $resultado['permitidas']));

        return redirect()
            ->route('configuracoes.index')
            ->with('success', "Configuração inicial concluída. Classes activas: {$lista}. Crie agora o primeiro ano lectivo.")
            ->with('highlight_ano_lectivo', true);
    }

    public function aguardar(): View|RedirectResponse
    {
        if (! SetupInicial::estaPendente()) {
            return redirect()->route('dashboard');
        }

        if (auth()->user()?->hasRole('admin_full')) {
            return redirect()->route('setup.inicial');
        }

        return view('setup.aguardar');
    }
}
