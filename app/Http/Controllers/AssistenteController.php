<?php

namespace App\Http\Controllers;

use App\Services\AI\AiUserMessage;
use App\Services\Assistente\AssistantAcoesService;
use App\Services\Assistente\AssistantSugestoesService;
use App\Services\Assistente\VirtualAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AssistenteController extends Controller
{
    public function __construct(
        protected VirtualAssistantService $assistant,
        protected AssistantSugestoesService $sugestoes,
        protected AssistantAcoesService $acoes,
    ) {}

    public function mensagem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mensagem' => ['required', 'string', 'min:2', 'max:4000'],
            'conversa_id' => ['nullable', 'integer', 'exists:assistente_conversas,id'],
            'route' => ['nullable', 'string', 'max:120'],
        ]);

        $this->authorize('assistente.usar');

        try {
            $resultado = $this->assistant->responder(
                $request->user(),
                $validated['mensagem'],
                $validated['conversa_id'] ?? null,
                $validated['route'] ?? null,
            );

            return response()->json([
                'success' => true,
                ...$resultado,
            ]);
        } catch (RuntimeException $e) {
            report($e);

            return response()->json([
                'success' => false,
                'retryable' => true,
                'message' => AiUserMessage::fromThrowable($e),
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'retryable' => true,
                'message' => AiUserMessage::fromThrowable($e),
            ], 500);
        }
    }

    public function conversas(Request $request): JsonResponse
    {
        $this->authorize('assistente.usar');

        return response()->json([
            'success' => true,
            'conversas' => $this->assistant->listarConversas($request->user()),
        ]);
    }

    public function novaConversa(Request $request): JsonResponse
    {
        $this->authorize('assistente.usar');

        $conversa = $this->assistant->novaConversa($request->user());

        return response()->json([
            'success' => true,
            'conversa_id' => $conversa->id,
        ]);
    }

    public function historico(Request $request, int $conversa): JsonResponse
    {
        $this->authorize('assistente.usar');

        try {
            $mensagens = $this->assistant->historicoConversa($request->user(), $conversa);

            return response()->json([
                'success' => true,
                'conversa_id' => $conversa,
                'mensagens' => $mensagens,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Conversa não encontrada.',
            ], 404);
        }
    }

    public function feedback(Request $request, int $mensagem): JsonResponse
    {
        $validated = $request->validate([
            'feedback' => ['required', 'in:util,inutil'],
        ]);

        $this->authorize('assistente.usar');

        try {
            $resultado = $this->assistant->registarFeedback(
                $request->user(),
                $mensagem,
                $validated['feedback'],
            );

            return response()->json($resultado);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Não foi possível registar o feedback.',
            ], 404);
        }
    }

    public function meta(Request $request): JsonResponse
    {
        $this->authorize('assistente.usar');

        $route = $request->query('route');

        return response()->json([
            'success' => true,
            'sugestoes' => $this->sugestoes->paraUtilizador($request->user(), is_string($route) ? $route : null),
            'acoes' => $this->acoes->paraUtilizador($request->user(), is_string($route) ? $route : null),
        ]);
    }
}
