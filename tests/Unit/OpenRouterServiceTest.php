<?php

namespace Tests\Unit;

use App\Services\AI\OpenRouterService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenRouterServiceTest extends TestCase
{
    public function test_hash_request_is_deterministic(): void
    {
        $payload = ['model' => 'test', 'messages' => [['role' => 'user', 'content' => 'olá']]];
        $hash1 = OpenRouterService::hashRequest($payload);
        $hash2 = OpenRouterService::hashRequest($payload);

        $this->assertSame($hash1, $hash2);
        $this->assertSame(64, strlen($hash1));
    }

    public function test_chat_parses_successful_response(): void
    {
        config([
            'openrouter.api_key' => 'test-key',
            'openrouter.base_url' => 'https://openrouter.test/api/v1',
            'openrouter.default_model' => 'google/gemini-2.0-flash-001',
        ]);

        Http::fake([
            'openrouter.test/*' => Http::response([
                'model' => 'google/gemini-2.0-flash-001',
                'choices' => [
                    ['message' => ['content' => 'Resposta de teste']],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
            ], 200),
        ]);

        $service = new OpenRouterService;
        $response = $service->chat([
            ['role' => 'user', 'content' => 'Teste'],
        ]);

        $this->assertSame('Resposta de teste', $response->content);
        $this->assertSame(10, $response->tokensIn);
        $this->assertSame(5, $response->tokensOut);
    }

    public function test_chat_json_decodes_response(): void
    {
        config([
            'openrouter.api_key' => 'test-key',
            'openrouter.base_url' => 'https://openrouter.test/api/v1',
            'openrouter.default_model' => 'google/gemini-2.5-flash',
        ]);

        Http::fake([
            'openrouter.test/*' => Http::response([
                'model' => 'google/gemini-2.5-flash',
                'choices' => [
                    ['message' => ['content' => '{"tema":"Álgebra"}'], 'finish_reason' => 'stop'],
                ],
                'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 10],
            ], 200),
        ]);

        $service = new OpenRouterService;
        $result = $service->chatJson([
            ['role' => 'user', 'content' => 'Gera JSON'],
        ]);

        $this->assertSame('Álgebra', $result['data']['tema']);
        $this->assertSame(20, $result['meta']['tokens_in']);
    }

    public function test_chat_json_extracts_from_markdown_fence(): void
    {
        config([
            'openrouter.api_key' => 'test-key',
            'openrouter.base_url' => 'https://openrouter.test/api/v1',
            'openrouter.default_model' => 'google/gemini-2.5-flash',
        ]);

        Http::fake([
            'openrouter.test/*' => Http::response([
                'model' => 'google/gemini-2.5-flash',
                'choices' => [
                    ['message' => ['content' => "```json\n{\"tema\":\"Redes\"}\n```"], 'finish_reason' => 'stop'],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
            ], 200),
        ]);

        $service = new OpenRouterService;
        $result = $service->chatJson([
            ['role' => 'user', 'content' => 'Gera JSON'],
        ]);

        $this->assertSame('Redes', $result['data']['tema']);
    }
}
