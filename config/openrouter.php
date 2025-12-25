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
    | The default AI model to use (GPT-oss-120b - Best for structured output)
    |
    */
    'model' => env('OPENROUTER_MODEL', 'openai/gpt-oss-120b'),

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
