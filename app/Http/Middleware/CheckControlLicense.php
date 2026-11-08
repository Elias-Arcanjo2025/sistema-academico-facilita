<?php

namespace App\Http\Middleware;

use App\Services\Control\ControlLicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckControlLicense
{
    public function __construct(
        private readonly ControlLicenseService $licenseService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->licenseService->isSchoolMode()) {
            return $next($request);
        }

        // Rotas sempre permitidas (login, logout, página de bloqueio, assets públicos)
        if ($this->shouldBypass($request)) {
            return $next($request);
        }

        // Consulta leve ao hub (throttled) para aplicar bloqueio sem esperar o cron
        $this->licenseService->maybeRefreshLicenseFromHub();

        if (! $this->licenseService->shouldBlockAccess()) {
            return $next($request);
        }

        // Grace period: última tentativa imediata ao hub (evita ficar preso com status
        // local activo no hub mas checked_at antigo na escola)
        if ($this->licenseService->isGracePeriodExpired()) {
            $this->licenseService->forceRefreshFromHub();

            if (! $this->licenseService->shouldBlockAccess()) {
                return $next($request);
            }
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->licenseService->blockMessage(),
                'license_blocked' => true,
            ], 403);
        }

        return redirect()->route('control.license.blocked');
    }

    private function shouldBypass(Request $request): bool
    {
        if ($request->routeIs([
            'login',
            'logout',
            'password.*',
            'register',
            'home',
            'arquivos.publicos',
            'documentos.boletim.signed',
            'control.license.blocked',
            'up',
        ])) {
            return true;
        }

        // API de health do Laravel
        if ($request->is('up')) {
            return true;
        }

        return false;
    }
}
