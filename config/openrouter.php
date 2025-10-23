<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OpenRouter API Key
    |--------------------------------------------------------------------------
    |
    | Your OpenRouter API key for accessing AI models
    |
    */
    'api_key' => env('OPENROUTER_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Default Model
    |--------------------------------------------------------------------------
    |
    | The default AI model to use (DeepSeek for trading)
    |
    */
    'model' => env('OPENROUTER_MODEL', 'deepseek/deepseek-chat-v3.1'),

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | OpenRouter API base URL
    |
    */
    'base_url' => 'https://openrouter.ai/api/v1',
];
