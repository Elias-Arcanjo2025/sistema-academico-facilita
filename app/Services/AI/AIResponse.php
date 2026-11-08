<?php

namespace App\Services\AI;

class AIResponse
{
    public function __construct(
        public readonly string $content,
        public readonly ?array $json = null,
        public readonly int $tokensIn = 0,
        public readonly int $tokensOut = 0,
        public readonly ?string $model = null,
        public readonly int $durationMs = 0,
    ) {}
}
