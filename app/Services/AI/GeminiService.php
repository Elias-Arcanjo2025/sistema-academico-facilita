<?php

namespace App\Services\AI;

use App\Services\AI\AiUserMessage;
use App\Services\AI\Concerns\DecodesAiJsonResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class GeminiService implements AIServiceInterface
{
    use DecodesAiJsonResponse;

    public function chat(array $messages, array $options = []): AIResponse
    {
        $apiKey = $this->apiKey();
        $model = $this->resolveModelName($options['model'] ?? null);
        $start = microtime(true);

        $payload = $this->buildPayload($messages, $options, jsonMode: false);
        $response = $this->requestWithRetry($model, $payload, $apiKey);
        $durationMs = (int) round((microtime(true) - $start) * 1000);

        $candidate = $response['candidates'][0] ?? [];
        $content = $this->normalizeMessageContent($candidate['content']['parts'] ?? []);

        return new AIResponse(
            content: $content,
            json: null,
            tokensIn: (int) ($response['usageMetadata']['promptTokenCount'] ?? 0),
            tokensOut: (int) ($response['usageMetadata']['candidatesTokenCount'] ?? 0),
            model: $model,
            durationMs: $durationMs,
        );
    }

    public function chatJson(array $messages, array $schema = [], array $options = []): array
    {
        $apiKey = $this->apiKey();
        $model = $this->resolveModelName($options['model'] ?? null);
        $start = microtime(true);
        $maxTokens = (int) ($options['max_tokens'] ?? config('gemini.max_tokens', 4096));
        $lastContent = '';
        $lastFinishReason = null;
        $decoded = null;

        for ($jsonAttempt = 0; $jsonAttempt < 2; $jsonAttempt++) {
            $attemptMessages = $messages;
            if ($jsonAttempt > 0) {
                $attemptMessages = array_merge($messages, [[
                    'role' => 'user',
                    'content' => 'A resposta anterior não era JSON válido. '
                        .'Responde APENAS com um único objecto JSON (RFC 8259), sem markdown, '
                        .'sem texto extra, com aspas escapadas e \\n para quebras de linha nas strings.',
                ]]);
            }

            $payload = $this->buildPayload($attemptMessages, array_merge($options, [
                'max_tokens' => $maxTokens,
            ]), jsonMode: true);

            $response = $this->requestWithRetry($model, $payload, $apiKey);
            $candidate = $response['candidates'][0] ?? [];
            $lastContent = $this->normalizeMessageContent($candidate['content']['parts'] ?? '{}');
            $lastFinishReason = $candidate['finishReason'] ?? null;
            $decoded = $this->decodeJsonResponse($lastContent);

            if (is_array($decoded)) {
                $durationMs = (int) round((microtime(true) - $start) * 1000);

                return [
                    'data' => $decoded,
                    'meta' => [
                        'tokens_in' => (int) ($response['usageMetadata']['promptTokenCount'] ?? 0),
                        'tokens_out' => (int) ($response['usageMetadata']['candidatesTokenCount'] ?? 0),
                        'model' => $model,
                        'duration_ms' => $durationMs,
                        'finish_reason' => $lastFinishReason,
                        'json_attempt' => $jsonAttempt + 1,
                        'driver' => 'gemini',
                    ],
                ];
            }

            if ($jsonAttempt < 1) {
                $maxTokens = min(
                    (int) config('pedagogico.plano_aula_max_tokens_cap', 12288),
                    (int) round($maxTokens * 1.25)
                );
            }
        }

        Log::warning('Gemini: resposta não é JSON válido', [
            'model' => $model,
            'finish_reason' => $lastFinishReason,
            'json_error' => json_last_error_msg(),
            'preview' => Str::limit($lastContent, 1200),
        ]);

        $message = 'Resposta IA não é JSON válido.';
        if (in_array($lastFinishReason, ['MAX_TOKENS', 'length'], true)) {
            $message .= ' A resposta foi cortada (limite de tokens). Tente com menos tempos ou regenere.';
        }

        throw new RuntimeException($message);
    }

    /**
     * @return array{texto:string, confianca:float, meta:array}
     */
    public function extractTextFromImage(string $imagePath, string $mimeType = 'image/jpeg'): array
    {
        $apiKey = $this->apiKey();
        $model = $this->resolveModelName(config('gemini.vision_model'));
        $base64 = base64_encode(file_get_contents($imagePath));

        $payload = [
            'contents' => [[
                'role' => 'user',
                'parts' => [
                    [
                        'text' => 'Extrai todo o texto visível desta imagem de prova escolar em português de Angola/Portugal. '
                            .'Preserva a ortografia original do documento (acção, actividade, objectivo, etc.). '
                            .'Responde apenas com o texto extraído, preservando numeração de questões.',
                    ],
                    [
                        'inline_data' => [
                            'mime_type' => $mimeType,
                            'data' => $base64,
                        ],
                    ],
                ],
            ]],
        ];

        $start = microtime(true);
        $response = $this->requestWithRetry($model, $payload, $apiKey);
        $durationMs = (int) round((microtime(true) - $start) * 1000);
        $candidate = $response['candidates'][0] ?? [];
        $texto = $this->normalizeMessageContent($candidate['content']['parts'] ?? '');

        return [
            'texto' => $texto,
            'confianca' => 0.85,
            'meta' => [
                'tokens_in' => (int) ($response['usageMetadata']['promptTokenCount'] ?? 0),
                'tokens_out' => (int) ($response['usageMetadata']['candidatesTokenCount'] ?? 0),
                'model' => $model,
                'duration_ms' => $durationMs,
                'driver' => 'gemini',
            ],
        ];
    }

    /**
     * @param  array<int, array{role:string, content:string|array}>  $messages
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    protected function buildPayload(array $messages, array $options, bool $jsonMode): array
    {
        $systemParts = [];
        $contents = [];

        foreach ($messages as $message) {
            $role = $message['role'] ?? 'user';
            $text = is_string($message['content'] ?? null)
                ? $message['content']
                : $this->normalizeMessageContent($message['content'] ?? '');

            if ($role === 'system') {
                $systemParts[] = ['text' => $text];
                continue;
            }

            $contents[] = [
                'role' => $role === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $text]],
            ];
        }

        $payload = ['contents' => $contents];

        if ($systemParts !== []) {
            $payload['systemInstruction'] = ['parts' => $systemParts];
        }

        $generationConfig = [];
        if (! empty($options['temperature'])) {
            $generationConfig['temperature'] = $options['temperature'];
        }
        $generationConfig['maxOutputTokens'] = (int) ($options['max_tokens'] ?? config('gemini.max_tokens', 4096));
        if ($jsonMode) {
            $generationConfig['responseMimeType'] = 'application/json';
        }
        $payload['generationConfig'] = $generationConfig;

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function requestWithRetry(string $model, array $payload, string $apiKey): array
    {
        $maxRetries = config('gemini.max_retries', 2);
        $timeout = config('gemini.timeout', 120);
        $lastException = null;
        $url = rtrim(config('gemini.base_url'), '/').'/models/'.$model.':generateContent';

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = Http::timeout($timeout)
                    ->withQueryParameters(['key' => $apiKey])
                    ->post($url, $payload);

                if ($response->successful()) {
                    $body = $response->json();
                    if (! empty($body['error'])) {
                        throw new RuntimeException(
                            'Gemini erro: '.($body['error']['message'] ?? json_encode($body['error']))
                        );
                    }

                    return $body;
                }

                if ($response->status() === 429) {
                    $erro = $response->json('error.message')
                        ?? 'Quota gratuita Gemini esgotada. Tente novamente mais tarde.';
                    throw new RuntimeException('Quota Gemini esgotada. '.$erro);
                }

                if (in_array($response->status(), [503, 502, 504], true) && $attempt < $maxRetries) {
                    usleep(800000 * ($attempt + 1));
                    continue;
                }

                if ($response->status() === 503) {
                    throw new RuntimeException(
                        'Gemini indisponível: modelo sobrecarregado. Tente novamente em alguns segundos.'
                    );
                }

                if ($response->status() >= 500 && $attempt < $maxRetries) {
                    usleep(500000 * ($attempt + 1));
                    continue;
                }

                throw new RuntimeException(
                    'Gemini erro '.$response->status().': '.$response->body()
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
            AiUserMessage::redactSecrets(
                'Falha na comunicação com Gemini: '.($lastException?->getMessage() ?? 'erro desconhecido')
            )
        );
    }

    protected function apiKey(): string
    {
        $apiKey = config('gemini.api_key');
        if (empty($apiKey)) {
            throw new RuntimeException('GEMINI_API_KEY não configurada. Obtenha em https://aistudio.google.com/apikey');
        }

        return $apiKey;
    }
}
