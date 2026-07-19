<?php

return [
    'api_url' => env('STOCK_PREDICTION_API_URL', 'http://127.0.0.1:8001'),
    'timeout' => (int) env('STOCK_PREDICTION_TIMEOUT', 8),
    'cache_minutes' => (int) env('STOCK_PREDICTION_CACHE_MINUTES', 30),
];
