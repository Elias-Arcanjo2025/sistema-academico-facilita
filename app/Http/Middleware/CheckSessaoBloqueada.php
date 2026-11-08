<?php

namespace App\Http\Middleware;

use App\Services\ConfiguracaoService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSessaoBloqueada
{
    public function __construct(
        protected ConfiguracaoService $configuracaoService
    ) {}

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $this->configuracaoService->bloqueioInactividadeActivo()) {
            return $next($request);
        }

        if ($request->routeIs(
            'sessao.desbloquear',
            'sessao.bloquear',
            'logout',
            'password.force.edit',
            'password.force.update',
        )) {
            return $next($request);
        }

        $bloqueada = (bool) $request->session()->get('facilita_sessao_bloqueada');

        // Qualquer pedido autenticado conta como actividade — evita bloqueio
        // enquanto o utilizador navega entre páginas.
        if (! $bloqueada) {
            $request->session()->put('facilita_ultima_actividade', now()->timestamp);
        }

        if (! $bloqueada) {
            return $next($request);
        }

        if ($request->isMethodSafe()) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'message' => 'Sessão bloqueada por inactividade. Introduza a palavra-passe para continuar.',
                'locked' => true,
            ], 423);
        }

        return back()->with('error', 'Sessão bloqueada por inactividade. Desbloqueie para continuar.');
    }
}
