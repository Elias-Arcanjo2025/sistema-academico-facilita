<?php

return [
    'ai_driver' => env('PEDAGOGICO_AI_DRIVER', 'gemini'),

    'models' => [
        'plano_aula' => env('PEDAGOGICO_MODEL_PLANO_AULA', 'gemini-2.5-flash-lite'),
        'plano_aula_fallback' => env('PEDAGOGICO_MODEL_PLANO_AULA_FALLBACK', 'gemini-2.5-flash'),
        'exercicio' => env('PEDAGOGICO_MODEL_EXERCICIO', 'gemini-2.5-flash-lite'),
        'prova' => env('PEDAGOGICO_MODEL_PROVA', 'gemini-2.5-flash-lite'),
        'banco_questao' => env('PEDAGOGICO_MODEL_BANCO_QUESTAO', 'gemini-2.5-flash-lite'),
        'execucao' => env('PEDAGOGICO_MODEL_EXECUCAO', 'gemini-2.5-flash-lite'),
        'correcao' => env('PEDAGOGICO_MODEL_CORRECAO', 'gemini-2.5-flash'),
    ],

    // Plano de aula: geração síncrona (resposta imediata, ~5–15 s) em vez de fila
    'plano_aula_sync' => env('PEDAGOGICO_PLANO_AULA_SYNC', true),
    'prova_sync' => env('PEDAGOGICO_PROVA_SYNC', true),
    'exercicio_sync' => env('PEDAGOGICO_EXERCICIO_SYNC', true),
    'correcao_sync' => env('PEDAGOGICO_CORRECAO_SYNC', true),
    'boletim_obs_ia' => env('PEDAGOGICO_BOLETIM_OBS_IA', true),
    'prova_max_tokens' => (int) env('PEDAGOGICO_PROVA_MAX_TOKENS', 4096),
    'banco_questao_max_tokens' => (int) env('PEDAGOGICO_BANCO_QUESTAO_MAX_TOKENS', 4096),

    'prova_notas_padrao' => [
        'Desligar o telemóvel',
        'O silêncio é obrigatório',
        'A prova é intransmissível e individual',
        'Proibido pedir qualquer material ao colega',
    ],

    'prova_pdf' => [
        'uma_pagina' => env('PEDAGOGICO_PROVA_PDF_UMA_PAGINA', true),
        'margins' => [
            'top' => (int) env('PEDAGOGICO_PROVA_PDF_MARGIN_TOP', 10),
            'right' => (int) env('PEDAGOGICO_PROVA_PDF_MARGIN_RIGHT', 12),
            'bottom' => (int) env('PEDAGOGICO_PROVA_PDF_MARGIN_BOTTOM', 10),
            'left' => (int) env('PEDAGOGICO_PROVA_PDF_MARGIN_LEFT', 14),
        ],
        'font_body' => (int) env('PEDAGOGICO_PROVA_PDF_FONT_BODY', 8),
        'font_header' => (int) env('PEDAGOGICO_PROVA_PDF_FONT_HEADER', 9),
        'logo_width' => (int) env('PEDAGOGICO_PROVA_PDF_LOGO_WIDTH', 40),
    ],
    'plano_aula_max_tokens' => (int) env('PEDAGOGICO_PLANO_AULA_MAX_TOKENS', 2048),
    'plano_aula_max_tokens_cap' => (int) env('PEDAGOGICO_PLANO_AULA_MAX_TOKENS_CAP', 12288),
    'palavras_por_tempo' => (int) env('PEDAGOGICO_PALAVRAS_POR_TEMPO', 380),

    'minutos_por_tempo' => (int) env('PEDAGOGICO_MINUTOS_POR_TEMPO', 45),
    'tempos_max' => (int) env('PEDAGOGICO_TEMPOS_MAX', 8),

    'rate_limit' => [
        'max_attempts' => (int) env('PEDAGOGICO_AI_RATE_LIMIT', 20),
        'decay_minutes' => 60,
    ],

    'ocr' => [
        'driver' => env('PEDAGOGICO_OCR_DRIVER', 'tesseract'),
        'tesseract_path' => env('TESSERACT_PATH'),
        'tesseract_lang' => env('TESSERACT_LANG', 'por'),
        'confianca_minima' => (float) env('PEDAGOGICO_OCR_CONFIANCA_MIN', 0.75),
        'storage_disk' => 'local',
        'storage_path' => 'pedagogico/correcoes',
    ],

    'dashboard_cache_ttl' => (int) env('PEDAGOGICO_DASHBOARD_CACHE_TTL', 300),

    'alerta_custo_diario_usd' => (float) env('PEDAGOGICO_ALERTA_CUSTO_DIARIO', 5.0),

    'custo_por_milhao' => [
        'default' => ['input' => 0.10, 'output' => 0.40],
        'google/gemini-2.0-flash-001' => ['input' => 0.10, 'output' => 0.40],
    ],
];
