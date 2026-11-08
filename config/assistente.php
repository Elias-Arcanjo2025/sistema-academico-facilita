<?php

return [
    'enabled' => env('ASSISTENTE_ENABLED', true),

    'model' => env('ASSISTENTE_MODEL', env('GEMINI_DEFAULT_MODEL', 'gemini-2.5-flash-lite')),

    'max_tokens' => (int) env('ASSISTENTE_MAX_TOKENS', 2048),

    'historico_max_mensagens' => (int) env('ASSISTENTE_HISTORICO_MAX', 10),

    'rate_limit' => [
        'max_attempts' => (int) env('ASSISTENTE_RATE_LIMIT', 40),
        'decay_minutes' => 60,
    ],

    'busca' => [
        'alunos_max' => 5,
        'turmas_max' => 3,
        'professores_max' => 8,
        'termo_min_chars' => 3,
    ],

    'escopo' => [
        'turmas_max' => (int) env('ASSISTENTE_ESCOPO_TURMAS_MAX', 15),
        'associacoes_max' => (int) env('ASSISTENTE_ESCOPO_ASSOCIACOES_MAX', 20),
    ],

    'contexto' => [
        // Limite aproximado de caracteres do JSON injectado no prompt
        'max_chars' => (int) env('ASSISTENTE_CONTEXTO_MAX_CHARS', 48000),
    ],

    'tools' => [
        'enabled' => env('ASSISTENTE_TOOLS_ENABLED', true),
    ],
];
