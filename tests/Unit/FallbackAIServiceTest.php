<?php

namespace Tests\Unit;

use App\Services\AI\FallbackAIService;
use App\Services\AI\GeminiService;
use App\Services\AI\OpenRouterService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class FallbackAIServiceTest extends TestCase
{
    public function test_usa_openrouter_quando_gemini_atinge_quota(): void
    {
        config([
            'ai.fallback_enabled' => true,
            'ai.primary_driver' => 'gemini',
            'ai.fallback_driver' => 'openrouter',
            'gemini.api_key' => 'gemini-key',
            'gemini.base_url' => 'https://gemini.test/v1beta',
            'openrouter.api_key' => 'or-key',
            'openrouter.base_url' => 'https://openrouter.test/api/v1',
        ]);

        Http::fake([
            'gemini.test/*' => Http::response([
                'error' => ['message' => 'Quota exceeded'],
            ], 429),
            'openrouter.test/*' => Http::response([
                'model' => 'google/gemini-2.5-flash-lite',
                'choices' => [[
                    'message' => ['content' => 'Resposta via OpenRouter'],
                    'finish_reason' => 'stop',
                ]],
                'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 3],
            ], 200),
        ]);

        $service = new FallbackAIService(new GeminiService, new OpenRouterService);
        $response = $service->chat([
            ['role' => 'user', 'content' => 'Olá'],
        ], ['model' => 'gemini-2.5-flash-lite']);

        $this->assertSame('Resposta via OpenRouter', $response->content);
    }

    public function test_nao_faz_fallback_em_erro_de_validacao_json(): void
    {
        config([
            'ai.fallback_enabled' => true,
            'ai.primary_driver' => 'gemini',
            'ai.fallback_driver' => 'openrouter',
            'gemini.api_key' => 'gemini-key',
            'gemini.base_url' => 'https://gemini.test/v1beta',
            'openrouter.api_key' => 'or-key',
            'openrouter.base_url' => 'https://openrouter.test/api/v1',
        ]);

        Http::fake([
            'gemini.test/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'isto não é json']]],
                    'finishReason' => 'STOP',
                ]],
                'usageMetadata' => ['promptTokenCount' => 1, 'candidatesTokenCount' => 1],
            ], 200),
        ]);

        $service = new FallbackAIService(new GeminiService, new OpenRouterService);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('JSON válido');

        $service->chatJson([
            ['role' => 'user', 'content' => 'Gera JSON'],
        ], [], ['model' => 'gemini-2.5-flash-lite']);
    }

    public function test_mapeia_modelo_gemini_para_openrouter(): void
    {
        $this->assertSame(
            'google/gemini-2.5-flash-lite',
            \App\Services\AI\AiModelMapper::forDriver('openrouter', 'gemini-2.5-flash-lite')
        );
    }

    public function test_mapeia_modelo_openrouter_para_gemini(): void
    {
        $this->assertSame(
            'gemini-2.5-flash',
            \App\Services\AI\AiModelMapper::forDriver('gemini', 'google/gemini-2.5-flash')
        );
    }
}
