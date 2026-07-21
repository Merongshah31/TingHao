<?php

return [
    'default' => env('AI_PROVIDER', 'qwen'),

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('OPENAI_MODEL', 'gpt-5.6'),
        'mock_mode' => env('OPENAI_MOCK_MODE', true),
        'timeout' => env('OPENAI_TIMEOUT', 20),
    ],
];
