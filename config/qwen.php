<?php

return [
    'api_key' => env('QWEN_API_KEY'),
    'base_url' => env('QWEN_BASE_URL', 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1'),
    'model' => env('QWEN_MODEL', 'qwen-plus'),
    'mock_mode' => env('QWEN_MOCK_MODE', true),
    'temperature' => (float) env('QWEN_TEMPERATURE', 0.2),
    'cache_minutes' => (int) env('QWEN_CACHE_MINUTES', 30),
    'max_tokens' => [
        'parse' => (int) env('QWEN_MAX_TOKENS_PARSE', 350),
        'email' => (int) env('QWEN_MAX_TOKENS_EMAIL', 500),
        'email_draft' => (int) env('QWEN_MAX_TOKENS_EMAIL_DRAFT', env('QWEN_MAX_TOKENS_EMAIL', 500)),
        'expiry' => (int) env('QWEN_MAX_TOKENS_EXPIRY', 350),
        'stock_reasoning' => (int) env('QWEN_MAX_TOKENS_STOCK_REASONING', 300),
        'restock_decision' => (int) env('QWEN_MAX_TOKENS_RESTOCK_DECISION', 220),
    ],
    'stock_reasoning_cache_minutes' => (int) env('QWEN_STOCK_REASONING_CACHE_MINUTES', 30),
    'email_draft_cache_minutes' => (int) env('QWEN_EMAIL_DRAFT_CACHE_MINUTES', 30),
];
