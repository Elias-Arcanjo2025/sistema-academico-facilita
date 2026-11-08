<?php

namespace App\Services\AI;

use Throwable;

class AiUserMessage
{
    /**
     * Mensagem segura para mostrar ao utilizador (sem chaves API nem detalhes técnicos).
     */
    public static function fromThrowable(Throwable $e): string
    {
        $msg = self::redactSecrets($e->getMessage());

        if (str_contains($msg, 'GEMINI_API_KEY não configurada')
            || str_contains($msg, 'OPENROUTER_API_KEY não configurada')) {
            return 'O assistente de IA não está configurado. Contacte o administrador do sistema.';
        }

        if (self::isDnsOrConnectionError($msg)) {
            return 'Não foi possível ligar ao serviço de IA. Verifique se o servidor tem acesso à Internet '
                .'e se a chave GEMINI_API_KEY está correcta no ficheiro .env.';
        }

        if (str_contains($msg, 'cURL error 28') || str_contains($msg, 'timed out')) {
            return 'O serviço de IA demorou demasiado a responder. Tente novamente.';
        }

        if (str_contains($msg, 'Quota Gemini') || str_contains($msg, '429')) {
            return 'Limite de utilização da IA atingido. Tente novamente mais tarde.';
        }

        if (str_contains($msg, 'sobrecarregado') || str_contains($msg, 'UNAVAILABLE')) {
            return 'O serviço de IA está ocupado neste momento. Aguarde alguns segundos e tente novamente.';
        }

        if (str_contains($msg, 'Mensagem vazia')
            || str_contains($msg, 'Sem permissão')
            || str_contains($msg, 'desactivado')) {
            return $msg;
        }

        if (str_contains($msg, 'Gemini')
            || str_contains($msg, 'OpenRouter')
            || str_contains($msg, 'cURL error')
            || str_contains($msg, 'Falha na comunicação')) {
            return 'O serviço de IA está temporariamente indisponível. Tente novamente dentro de momentos.';
        }

        return 'Não foi possível obter resposta do assistente. Tente novamente.';
    }

    public static function redactSecrets(string $message): string
    {
        $message = preg_replace('/[?&]key=[^&\s\'"]+/i', '?key=[REDACTED]', $message) ?? $message;
        $message = preg_replace('/\bAIza[A-Za-z0-9_\-]{10,}\b/', '[REDACTED]', $message) ?? $message;
        $message = preg_replace('/\bAQ\.[A-Za-z0-9_\-]{10,}\b/', '[REDACTED]', $message) ?? $message;

        return $message;
    }

    protected static function isDnsOrConnectionError(string $msg): bool
    {
        return str_contains($msg, 'cURL error 6')
            || str_contains($msg, 'Could not resolve host')
            || str_contains($msg, 'cURL error 7')
            || str_contains($msg, 'Failed to connect');
    }
}
