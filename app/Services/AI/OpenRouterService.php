<?php

namespace App\Services\AI;

use App\Services\AI\Concerns\DecodesAiJsonResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class OpenRouterService implements AIServiceInterface
{
    use DecodesAiJsonResponse;

    public function chat(array $messages, array $options = []): AIResponse
    {
        $apiKey = config('openrouter.api_key');
        if (empty($apiKey)) {
            throw new RuntimeException('OPENROUTER_API_KEY não configurada.');
        }

        $model = $options['model'] ?? config('openrouter.default_model');
        $start = microtime(true);
        $payload = [
            'model' => $model,
            'messages' => $messages,
        ];

        if (! empty($options['temperature'])) {
            $payload['temperature'] = $options['temperature'];
        }
        if (! empty($options['max_tokens'])) {
            $payload['max_tokens'] = $options['max_tokens'];
        } else {
            $payload['max_tokens'] = config('openrouter.max_tokens', 4096);
        }

        $response = $this->requestWithRetry($payload, $apiKey);
        $durationMs = (int) round((microtime(true) - $start) * 1000);

        $content = $response['choices'][0]['message']['content'] ?? '';
        $usage = $response['usage'] ?? [];

        return new AIResponse(
            content: is_string($content) ? $content : json_encode($content),
            json: null,
            tokensIn: (int) ($usage['prompt_tokens'] ?? 0),
            tokensOut: (int) ($usage['completion_tokens'] ?? 0),
            model: $response['model'] ?? $model,
            durationMs: $durationMs,
        );
    }

    public function chatJson(array $messages, array $schema = [], array $options = []): array
    {
        $apiKey = config('openrouter.api_key');
        if (empty($apiKey)) {
            throw new RuntimeException('OPENROUTER_API_KEY não configurada.');
        }

        $model = $options['model'] ?? config('openrouter.default_model');
        $start = microtime(true);
        $maxTokens = (int) ($options['max_tokens'] ?? config('openrouter.max_tokens', 4096));
        $lastContent = '';
        $lastFinishReason = null;
        $decoded = null;

        for ($jsonAttempt = 0; $jsonAttempt < 2; $jsonAttempt++) {
            $payload = [
                'model' => $model,
                'messages' => $messages,
                'response_format' => ['type' => 'json_object'],
                'max_tokens' => $maxTokens,
            ];

            if (! empty($options['temperature'])) {
                $payload['temperature'] = $options['temperature'];
            }

            if ($jsonAttempt > 0) {
                $payload['messages'] = array_merge($messages, [[
                    'role' => 'user',
                    'content' => 'A resposta anterior não era JSON válido. '
                        .'Responde APENAS com um único objecto JSON (RFC 8259), sem markdown, '
                        .'sem texto extra, com aspas escapadas e \\n para quebras de linha nas strings.',
                ]]);
            }

            $response = $this->requestWithRetry($payload, $apiKey);
            $choice = $response['choices'][0] ?? [];
            $lastContent = $this->normalizeMessageContent($choice['message']['content'] ?? '{}');
            $lastFinishReason = $choice['finish_reason'] ?? null;
            $decoded = $this->decodeJsonResponse($lastContent);

            if (is_array($decoded)) {
                $durationMs = (int) round((microtime(true) - $start) * 1000);
                $usage = $response['usage'] ?? [];

                return [
                    'data' => $decoded,
                    'meta' => [
                        'tokens_in' => (int) ($usage['prompt_tokens'] ?? 0),
                        'tokens_out' => (int) ($usage['completion_tokens'] ?? 0),
                        'model' => $response['model'] ?? $model,
                        'duration_ms' => $durationMs,
                        'finish_reason' => $lastFinishReason,
                        'json_attempt' => $jsonAttempt + 1,
                    ],
                ];
            }

            if ($jsonAttempt < 1) {
                $maxTokens = min(
                    (int) config('pedagogico.plano_aula_max_tokens_cap', 12288),
                    (int) round($maxTokens * 1.25)
                );
                continue;
            }

            break;
        }

        // Fallback sem response_format (alguns modelos devolvem JSON mais limpo assim)
        $fallbackPayload = [
            'model' => $model,
            'messages' => array_merge($messages, [[
                'role' => 'user',
                'content' => 'Responde somente com JSON válido numa única linha ou bloco, sem explicações.',
            ]]),
            'max_tokens' => $maxTokens,
        ];
        if (! empty($options['temperature'])) {
            $fallbackPayload['temperature'] = $options['temperature'];
        }

        $response = $this->requestWithRetry($fallbackPayload, $apiKey);
        $choice = $response['choices'][0] ?? [];
        $lastContent = $this->normalizeMessageContent($choice['message']['content'] ?? '{}');
        $lastFinishReason = $choice['finish_reason'] ?? null;
        $decoded = $this->decodeJsonResponse($lastContent);

        if (is_array($decoded)) {
            $durationMs = (int) round((microtime(true) - $start) * 1000);
            $usage = $response['usage'] ?? [];

            return [
                'data' => $decoded,
                'meta' => [
                    'tokens_in' => (int) ($usage['prompt_tokens'] ?? 0),
                    'tokens_out' => (int) ($usage['completion_tokens'] ?? 0),
                    'model' => $response['model'] ?? $model,
                    'duration_ms' => $durationMs,
                    'finish_reason' => $lastFinishReason,
                    'json_attempt' => 'fallback',
                ],
            ];
        }

        Log::warning('OpenRouter: resposta não é JSON válido', [
            'model' => $model,
            'finish_reason' => $lastFinishReason,
            'json_error' => json_last_error_msg(),
            'preview' => Str::limit($lastContent, 1200),
        ]);

        $message = 'Resposta IA não é JSON válido.';
        if ($lastFinishReason === 'length') {
            $message .= ' A resposta foi cortada (limite de tokens). Tente com menos tempos ou regenere.';
        }

        throw new RuntimeException($message);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function requestWithRetry(array $payload, string $apiKey): array
    {
        $maxRetries = config('openrouter.max_retries', 2);
        $timeout = config('openrouter.timeout', 120);
        $lastException = null;

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            try {
                $http = Http::withHeaders([
                    'Authorization' => 'Bearer '.$apiKey,
                    'HTTP-Referer' => config('openrouter.site_url'),
                    'X-Title' => config('openrouter.site_name'),
                    'Content-Type' => 'application/json',
                ])->timeout($timeout);

                $response = $http->post(rtrim(config('openrouter.base_url'), '/').'/chat/completions', $payload);

                if ($response->successful()) {
                    return $response->json();
                }

                if ($response->status() === 402) {
                    $erro = $response->json('error.message')
                        ?? 'Créditos OpenRouter insuficientes para esta geração.';
                    throw new RuntimeException(
                        'Créditos OpenRouter insuficientes. '.$erro
                        .' Adicione créditos em https://openrouter.ai/settings/credits'
                    );
                }

                if ($response->status() >= 500 && $attempt < $maxRetries) {
                    usleep(500000 * ($attempt + 1));
                    continue;
                }

                throw new RuntimeException(
                    'OpenRouter erro '.$response->status().': '.$response->body()
                );
            } catch (ConnectionException $e) {
                $lastException = $e;
                if ($attempt < $maxRetries) {
                    usleep(500000 * ($attempt + 1));
                    continue;
                }
            }
        }

        throw new RuntimeException(
            'Falha na comunicação com OpenRouter: '.($lastException?->getMessage() ?? 'erro desconhecido')
        );
    }

    /**
     * Extrai texto de imagem via modelo vision.
     *
     * @return array{texto:string, confianca:float, meta:array}
     */
    public function extractTextFromImage(string $imagePath, string $mimeType = 'image/jpeg'): array
    {
        $base64 = base64_encode(file_get_contents($imagePath));
        $dataUri = 'data:'.$mimeType.';base64,'.$base64;
        $model = config('openrouter.vision_model');

        $messages = [
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Extrai todo o texto visível desta imagem de prova escolar em português de Angola/Portugal. '
                            .'Preserva a ortografia original do documento (acção, actividade, objectivo, etc.). '
                            .'Responde apenas com o texto extraído, preservando numeração de questões.',
                    ],
                    ['type' => 'image_url', 'image_url' => ['url' => $dataUri]],
                ],
            ],
        ];

        $result = $this->chat($messages, ['model' => $model]);

        return [
            'texto' => $result->content,
            'confianca' => 0.85,
            'meta' => [
                'tokens_in' => $result->tokensIn,
                'tokens_out' => $result->tokensOut,
                'model' => $result->model,
                'duration_ms' => $result->durationMs,
            ],
        ];
    }

    public static function hashRequest(array $payload): string
    {
        return hash('sha256', json_encode($payload));
    }
}
