<?php

return [
    'api_key' => env('GEMINI_API_KEY'),
    'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
    'default_model' => env('GEMINI_DEFAULT_MODEL', 'gemini-2.5-flash-lite'),
    'vision_model' => env('GEMINI_VISION_MODEL', 'gemini-2.5-flash-lite'),
    'timeout' => (int) env('GEMINI_TIMEOUT', 120),
    'max_retries' => (int) env('GEMINI_MAX_RETRIES', 2),
    'max_tokens' => (int) env('GEMINI_MAX_TOKENS', 4096),
];
