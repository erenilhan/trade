<?php

return [
    /*
    |--------------------------------------------------------------------------
    | DeepSeek API Key
    |--------------------------------------------------------------------------
    |
    | Your DeepSeek API key for direct API access
    |
    */
    'api_key' => env('DEEPSEEK_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | DeepSeek API base URL
    |
    */
    'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com/v1'),

    /*
    |--------------------------------------------------------------------------
    | Default Model
    |--------------------------------------------------------------------------
    |
    | The default DeepSeek model to use
    |
    */
    'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum time to wait for API response (in seconds)
    |
    */
    'timeout' => env('DEEPSEEK_TIMEOUT', 30),
];
