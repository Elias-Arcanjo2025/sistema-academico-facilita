<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class FallbackAIService implements AIServiceInterface
{
    public function __construct(
        protected GeminiService $gemini,
        protected OpenRouterService $openRouter,
    ) {}

    public function chat(array $messages, array $options = []): AIResponse
    {
        $lastException = null;

        foreach ($this->drivers() as $index => $driver) {
            if (! $this->driverDisponivel($driver)) {
                continue;
            }

            try {
                $response = $this->servico($driver)->chat(
                    $messages,
                    $this->opcoesParaDriver($driver, $options),
                );

                if ($index > 0) {
                    Log::info('Assistente IA: fallback activado', [
                        'driver' => $driver,
                        'model' => $response->model,
                    ]);
                }

                return $response;
            } catch (Throwable $e) {
                $lastException = $e;

                if (! $this->deveTentarFallback($e) || $this->ultimoDriver($index)) {
                    throw $e;
                }

                Log::warning('Assistente IA: driver primário falhou, a tentar alternativa', [
                    'driver' => $driver,
                    'erro' => AiUserMessage::redactSecrets($e->getMessage()),
                ]);
            }
        }

        throw $lastException ?? new RuntimeException('Nenhum serviço de IA disponível.');
    }

    public function chatJson(array $messages, array $schema = [], array $options = []): array
    {
        $lastException = null;

        foreach ($this->drivers() as $index => $driver) {
            if (! $this->driverDisponivel($driver)) {
                continue;
            }

            try {
                $resultado = $this->servico($driver)->chatJson(
                    $messages,
                    $schema,
                    $this->opcoesParaDriver($driver, $options),
                );

                if ($index > 0) {
                    Log::info('IA pedagógica: fallback activado', [
                        'driver' => $driver,
                        'model' => $resultado['meta']['model'] ?? null,
                    ]);
                }

                $resultado['meta']['driver'] = $driver;
                $resultado['meta']['used_fallback'] = $index > 0;

                return $resultado;
            } catch (Throwable $e) {
                $lastException = $e;

                if (! $this->deveTentarFallback($e) || $this->ultimoDriver($index)) {
                    throw $e;
                }

                Log::warning('IA pedagógica: driver primário falhou, a tentar alternativa', [
                    'driver' => $driver,
                    'erro' => AiUserMessage::redactSecrets($e->getMessage()),
                ]);
            }
        }

        throw $lastException ?? new RuntimeException('Nenhum serviço de IA disponível.');
    }

    /**
     * @return array<int, string>
     */
    protected function drivers(): array
    {
        if (! config('ai.fallback_enabled', true)) {
            return [config('ai.primary_driver', 'gemini')];
        }

        $primary = config('ai.primary_driver', 'gemini');
        $fallback = config('ai.fallback_driver', 'openrouter');

        if ($primary === $fallback) {
            return [$primary];
        }

        return [$primary, $fallback];
    }

    protected function servico(string $driver): AIServiceInterface
    {
        return match ($driver) {
            'openrouter' => $this->openRouter,
            default => $this->gemini,
        };
    }

    protected function driverDisponivel(string $driver): bool
    {
        return match ($driver) {
            'gemini' => ! empty(config('gemini.api_key')),
            'openrouter' => ! empty(config('openrouter.api_key')),
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function opcoesParaDriver(string $driver, array $options): array
    {
        $options['model'] = AiModelMapper::forDriver($driver, $options['model'] ?? null);

        return $options;
    }

    protected function deveTentarFallback(Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());

        if (str_contains($msg, 'créditos openrouter')
            || str_contains($msg, 'openrouter insuficientes')
            || str_contains($msg, '"402"')
            || str_contains($msg, ' 402')) {
            return true;
        }

        if (str_contains($msg, 'não configurada')
            || str_contains($msg, 'nao configurada')) {
            return true;
        }

        return str_contains($msg, 'quota gemini')
            || str_contains($msg, '429')
            || str_contains($msg, 'sobrecarregado')
            || str_contains($msg, 'unavailable')
            || str_contains($msg, 'indisponível')
            || str_contains($msg, 'indisponivel')
            || str_contains($msg, ' 503')
            || str_contains($msg, ' 502')
            || str_contains($msg, ' 504')
            || str_contains($msg, 'timed out')
            || str_contains($msg, 'cURL error 28')
            || str_contains($msg, 'cURL error 6')
            || str_contains($msg, 'cURL error 7')
            || str_contains($msg, 'could not resolve host')
            || str_contains($msg, 'failed to connect')
            || str_contains($msg, 'falha na comunicação');
    }

    protected function ultimoDriver(int $index): bool
    {
        $drivers = array_values(array_filter(
            $this->drivers(),
            fn (string $driver) => $this->driverDisponivel($driver),
        ));

        return $index >= count($drivers) - 1;
    }
}
