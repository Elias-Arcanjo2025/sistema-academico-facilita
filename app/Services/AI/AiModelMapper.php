<?php

namespace App\Services\AI;

class AiModelMapper
{
    public static function forDriver(string $driver, ?string $model = null): string
    {
        $model ??= match ($driver) {
            'gemini' => config('gemini.default_model', 'gemini-2.5-flash-lite'),
            'openrouter' => config('openrouter.default_model', 'google/gemini-2.5-flash'),
            default => config('gemini.default_model', 'gemini-2.5-flash-lite'),
        };

        if ($driver === 'openrouter') {
            if (str_contains($model, '/')) {
                return $model;
            }

            return 'google/'.$model;
        }

        if ($driver === 'gemini' && str_starts_with($model, 'google/')) {
            return substr($model, strlen('google/'));
        }

        return $model;
    }
}
