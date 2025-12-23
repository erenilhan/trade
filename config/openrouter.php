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
    | The default AI model to use (Xiaomi MIMO v2 Flash - FREE!)
    |
    */
    'model' => env('OPENROUTER_MODEL', 'xiaomi/mimo-v2-flash:free'),

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
