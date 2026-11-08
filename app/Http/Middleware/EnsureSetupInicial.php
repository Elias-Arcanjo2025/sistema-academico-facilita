<?php

namespace App\Http\Middleware;

use App\Support\SetupInicial;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupInicial
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! SetupInicial::estaPendente()) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        // Portais não dependem deste passo
        if ($user->hasAnyRole(['aluno', 'encarregado'])) {
            return $next($request);
        }

        if ($request->routeIs([
            'setup.inicial',
            'setup.inicial.guardar',
            'setup.aguardar',
            'logout',
            'password.force.edit',
            'password.force.update',
            'login',
            'home',
            'arquivos.publicos',
            'control.license.blocked',
            'up',
        ])) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Conclua a configuração inicial do Facilita.',
                'redirect' => $user->hasRole('admin_full')
                    ? route('setup.inicial')
                    : route('setup.aguardar'),
            ], 403);
        }

        if ($user->hasRole('admin_full')) {
            return redirect()->route('setup.inicial');
        }

        return redirect()->route('setup.aguardar');
    }
}
