<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Em CONTROL_MODE=hub, esta instalação é só o painel central —
 * bloqueia o uso como escola (rotas académicas).
 */
class RestrictToControlHub
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('control.mode') !== 'hub') {
            return $next($request);
        }

        if ($this->allowed($request)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Esta instalação é o hub de controlo Facilita. Use a instalação da escola para operações académicas.',
            ], 403);
        }

        if (auth()->check() && auth()->user()->hasRole('admin_full')) {
            return redirect()->route('control.hub.index')
                ->with('info', 'Este site é o Controlo Facilita (hub), não a instalação da escola.');
        }

        return redirect()->route('home');
    }

    private function allowed(Request $request): bool
    {
        if ($request->routeIs([
            'home',
            'login',
            'login.store',
            'logout',
            'register',
            'password.*',
            'verification.*',
            'control.*',
            'profile.*',
            'sessao.*',
            'arquivos.publicos',
            'dashboard',
            'webhooks.*',
            'up',
        ])) {
            return true;
        }

        // POST login/logout/register podem não ter nome de rota — permitir pelo path
        if ($request->is(['login', 'logout', 'register', 'forgot-password', 'reset-password', 'reset-password/*'])) {
            return true;
        }

        return false;
    }
}
