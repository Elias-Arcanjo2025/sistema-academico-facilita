<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Fallback entre provedores de IA
    |--------------------------------------------------------------------------
    |
    | Com fallback activo, o sistema tenta o driver primário (ex.: Gemini directo)
    | e, se falhar por quota, indisponibilidade ou timeout, usa o secundário
    | (ex.: OpenRouter), desde que a respectiva API key esteja configurada.
    |
    */
    'fallback_enabled' => env('AI_FALLBACK_ENABLED', true),

    'primary_driver' => env('AI_PRIMARY_DRIVER', env('PEDAGOGICO_AI_DRIVER', 'gemini')),

    'fallback_driver' => env('AI_FALLBACK_DRIVER', 'openrouter'),

    'models' => [
        'assistente' => [
            'gemini' => env('ASSISTENTE_MODEL', env('GEMINI_DEFAULT_MODEL', 'gemini-2.5-flash-lite')),
            'openrouter' => env('ASSISTENTE_MODEL_OPENROUTER', env('OPENROUTER_DEFAULT_MODEL', 'google/gemini-2.5-flash')),
        ],
    ],
];
