<?php

namespace App\Services\Pedagogico;

use App\Models\Pedagogico\PedGeracaoIA;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class IaGenerationLogger
{
    public function iniciar(User $user, string $tipo, array $requestPayload, ?Model $geravel = null): PedGeracaoIA
    {
        return PedGeracaoIA::create([
            'tipo' => $tipo,
            'user_id' => $user->id,
            'provider' => config('pedagogico.ai_driver', 'openrouter'),
            'request_hash' => hash('sha256', json_encode($requestPayload)),
            'request_json' => $requestPayload,
            'status' => PedGeracaoIA::STATUS_PROCESSING,
            'geravel_type' => $geravel ? $geravel->getMorphClass() : null,
            'geravel_id' => $geravel?->getKey(),
        ]);
    }

    public function concluir(PedGeracaoIA $geracao, array $meta, ?array $response = null): void
    {
        app(IaCostMonitor::class)->registarConclusao($geracao, $meta);

        $geracao->update([
            'status' => PedGeracaoIA::STATUS_COMPLETED,
            'response_json' => $response,
        ]);
    }

    public function falhar(PedGeracaoIA $geracao, string $mensagem): void
    {
        $geracao->update([
            'status' => PedGeracaoIA::STATUS_FAILED,
            'error_message' => $mensagem,
        ]);
    }
}
