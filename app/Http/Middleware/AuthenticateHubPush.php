<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica pedidos do hub para a escola (push de sync).
 * Usa o mesmo secret da instalação (CONTROL_SECRET).
 */
class AuthenticateHubPush
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('control.mode') !== 'school') {
            return response()->json(['message' => 'Endpoint só disponível em modo school.'], 503);
        }

        $installationId = (string) $request->header('X-Facilita-Installation', '');
        $timestamp = (string) $request->header('X-Facilita-Timestamp', '');
        $signature = (string) $request->header('X-Facilita-Signature', '');
        $secret = (string) $request->bearerToken();

        $expectedId = (string) config('control.installation_id');
        $expectedSecret = (string) config('control.secret');

        if ($installationId === '' || $timestamp === '' || $signature === '' || $secret === '') {
            return response()->json(['message' => 'Credenciais em falta.'], 401);
        }

        if (! hash_equals($expectedId, $installationId) || ! hash_equals($expectedSecret, $secret)) {
            return response()->json(['message' => 'Credenciais inválidas.'], 401);
        }

        $ts = (int) $timestamp;
        if ($ts < now()->subMinutes(5)->timestamp || $ts > now()->addMinutes(5)->timestamp) {
            return response()->json(['message' => 'Pedido expirado.'], 401);
        }

        $expected = hash_hmac('sha256', $installationId.'|'.$timestamp, $secret);
        if (! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Assinatura inválida.'], 401);
        }

        return $next($request);
    }
}
