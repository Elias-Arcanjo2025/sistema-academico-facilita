<?php

namespace App\Http\Controllers;

use App\Services\ConfiguracaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SessionLockController extends Controller
{
    public function __construct(
        protected ConfiguracaoService $configuracaoService
    ) {}

    public function bloquear(Request $request): JsonResponse
    {
        if (! $this->configuracaoService->bloqueioInactividadeActivo()) {
            return response()->json([
                'ok' => true,
                'locked' => false,
                'message' => 'Bloqueio por inactividade desactivado.',
            ]);
        }

        // Se ainda há actividade recente (navegação noutro separador, pedidos HTTP, etc.),
        // não marcar a sessão como bloqueada.
        if (! $this->estaInactivo($request)) {
            return response()->json([
                'ok' => true,
                'locked' => false,
                'message' => 'Ainda há actividade recente na sessão.',
            ]);
        }

        $request->session()->put('facilita_sessao_bloqueada', true);

        return response()->json([
            'ok' => true,
            'locked' => true,
        ]);
    }

    public function desbloquear(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Palavra-passe incorrecta.'],
            ]);
        }

        $request->session()->forget('facilita_sessao_bloqueada');
        $request->session()->put('facilita_ultima_actividade', now()->timestamp);

        return response()->json([
            'ok' => true,
            'message' => 'Sessão desbloqueada.',
        ]);
    }

    protected function estaInactivo(Request $request): bool
    {
        $ultima = (int) $request->session()->get('facilita_ultima_actividade', 0);
        if ($ultima <= 0) {
            return true;
        }

        $limiteSegundos = $this->configuracaoService->bloqueioInactividadeMinutos() * 60;

        return (now()->timestamp - $ultima) >= $limiteSegundos;
    }
}
