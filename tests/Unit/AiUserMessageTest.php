<?php

namespace Tests\Unit;

use App\Services\AI\AiUserMessage;
use RuntimeException;
use Tests\TestCase;

class AiUserMessageTest extends TestCase
{
    public function test_redact_api_key_from_curl_error(): void
    {
        $msg = 'cURL error 6: Could not resolve host for https://generativelanguage.googleapis.com/v1beta/models/gemini:generateContent?key=AQ.Ab8RN6LSecretKey123';

        $safe = AiUserMessage::fromThrowable(new RuntimeException($msg));

        $this->assertStringNotContainsString('AQ.Ab8RN', $safe);
        $this->assertStringNotContainsString('SecretKey', $safe);
        $this->assertStringContainsString('Internet', $safe);
    }

    public function test_preserves_business_validation_message(): void
    {
        $safe = AiUserMessage::fromThrowable(new RuntimeException('Mensagem vazia.'));

        $this->assertSame('Mensagem vazia.', $safe);
    }
}
