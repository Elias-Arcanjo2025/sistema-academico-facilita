<?php

namespace Tests\Unit;

use App\Services\AI\GeminiService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiServiceTest extends TestCase
{
    public function test_chat_json_decodes_gemini_response(): void
    {
        config([
            'gemini.api_key' => 'test-key',
            'gemini.base_url' => 'https://gemini.test/v1beta',
            'gemini.default_model' => 'gemini-2.5-flash-lite',
        ]);

        Http::fake([
            'gemini.test/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => '{"titulo":"Prova MAC","questoes":[]}']]],
                    'finishReason' => 'STOP',
                ]],
                'usageMetadata' => ['promptTokenCount' => 12, 'candidatesTokenCount' => 8],
            ], 200),
        ]);

        $service = new GeminiService;
        $result = $service->chatJson([
            ['role' => 'user', 'content' => 'Gera JSON'],
        ]);

        $this->assertSame('Prova MAC', $result['data']['titulo']);
        $this->assertSame(12, $result['meta']['tokens_in']);
        $this->assertSame('gemini', $result['meta']['driver']);
    }

    public function test_resolves_openrouter_style_model_names(): void
    {
        config([
            'gemini.api_key' => 'test-key',
            'gemini.base_url' => 'https://gemini.test/v1beta',
        ]);

        Http::fake([
            'gemini.test/*' => function ($request) {
                $this->assertStringContainsString('models/gemini-2.5-flash-lite:generateContent', $request->url());

                return Http::response([
                    'candidates' => [[
                        'content' => ['parts' => [['text' => '{"ok":true}']]],
                        'finishReason' => 'STOP',
                    ]],
                    'usageMetadata' => ['promptTokenCount' => 1, 'candidatesTokenCount' => 1],
                ], 200);
            },
        ]);

        $service = new GeminiService;
        $result = $service->chatJson(
            [['role' => 'user', 'content' => 'teste']],
            [],
            ['model' => 'google/gemini-2.5-flash-lite']
        );

        $this->assertTrue($result['data']['ok']);
    }
}
