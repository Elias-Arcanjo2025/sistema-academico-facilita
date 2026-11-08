<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();
        $request->session()->forget('facilita_sessao_bloqueada');

        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->save();

        if ($user->must_change_password) {
            return redirect()->route('password.force.edit');
        }

        // Contas de portal: ir sempre ao portal (ignorar intended=/dashboard)
        if ($user->eContaPortalExclusiva() || $user->hasRole('aluno') || $user->hasRole('encarregado')) {
            return redirect()->to($user->rotaInicio());
        }

        // Hub: ir directo ao painel de controlo (evita intended=/ e loop)
        if (config('control.mode') === 'hub') {
            return redirect()->route('control.hub.index');
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if ($user) {
            activity()
                ->causedBy($user)
                ->withProperties(['ip' => $request->ip()])
                ->log('Logout efectuado');
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('status', 'Sessão terminada com sucesso.');
    }
}
