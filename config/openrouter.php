<?php

return [
    'api_key' => env('OPENROUTER_API_KEY'),
    'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
    'default_model' => env('OPENROUTER_DEFAULT_MODEL', 'google/gemini-2.5-flash'),
    'vision_model' => env('OPENROUTER_VISION_MODEL', 'google/gemini-2.5-flash'),
    'timeout' => (int) env('OPENROUTER_TIMEOUT', 120),
    'max_retries' => (int) env('OPENROUTER_MAX_RETRIES', 2),
    'max_tokens' => (int) env('OPENROUTER_MAX_TOKENS', 4096),
    'site_url' => env('APP_URL'),
    'site_name' => env('APP_NAME', 'Facilita'),
];
