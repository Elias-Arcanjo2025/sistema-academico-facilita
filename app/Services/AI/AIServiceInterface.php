<?php

namespace App\Services\AI;

interface AIServiceInterface
{
    /**
     * @param  array<int, array{role:string, content:string|array}>  $messages
     * @param  array<string, mixed>  $options
     */
    public function chat(array $messages, array $options = []): AIResponse;

    /**
     * @param  array<int, array{role:string, content:string|array}>  $messages
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function chatJson(array $messages, array $schema = [], array $options = []): array;
}
