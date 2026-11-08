<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Impede contas exclusivas de portal (aluno / encarregado) de usar o painel staff.
 */
class RedirectPortalAccounts
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->eContaPortalExclusiva()) {
            return $next($request);
        }

        $rotasPermitidas = [
            'portal.*',
            'assistente.*',
            'regras-avaliacao',
            'sessao.*',
            'password.force.*',
            'logout',
            'profile.*',
            'verification.*',
            'password.*',
        ];

        if ($request->routeIs(...$rotasPermitidas)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'Esta área não está disponível para a sua conta.',
            ], 403);
        }

        return redirect()->to($user->rotaInicio());
    }
}
