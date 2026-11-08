<?php

namespace App\Http\Middleware;

use App\Services\ConfiguracaoService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RegistoPublicoActivo
{
    public function __construct(
        protected ConfiguracaoService $configuracaoService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->configuracaoService->registoPublicoActivo()) {
            abort(404);
        }

        return $next($request);
    }
}
