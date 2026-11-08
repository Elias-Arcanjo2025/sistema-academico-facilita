<?php

namespace App\Http\Middleware;

use App\Services\Control\ControlHubService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateControlInstallation
{
    public function __construct(
        private readonly ControlHubService $hubService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (config('control.mode') !== 'hub') {
            return response()->json(['message' => 'Hub de controlo desactivado.'], 503);
        }

        $installationId = (string) $request->header('X-Facilita-Installation', '');
        $timestamp = (string) $request->header('X-Facilita-Timestamp', '');
        $signature = (string) $request->header('X-Facilita-Signature', '');
        $secret = (string) $request->bearerToken();

        if ($installationId === '' || $timestamp === '' || $signature === '' || $secret === '') {
            return response()->json(['message' => 'Credenciais de instalação em falta.'], 401);
        }

        $escola = $this->hubService->findEscolaByInstallation($installationId);
        if (! $escola) {
            return response()->json(['message' => 'Instalação não registada.'], 401);
        }

        $ts = (int) $timestamp;
        if ($ts < now()->subMinutes(5)->timestamp || $ts > now()->addMinutes(5)->timestamp) {
            return response()->json(['message' => 'Pedido expirado. Sincronize o relógio do servidor.'], 401);
        }

        if (! $this->hubService->verifyCredentials($escola, $secret, $timestamp, $signature)) {
            return response()->json(['message' => 'Assinatura ou secret inválidos.'], 401);
        }

        $request->attributes->set('control_escola', $escola);

        return $next($request);
    }
}
