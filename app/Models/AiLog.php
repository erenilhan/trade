<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiLog extends Model
{
    protected $table = 'ai_logs';

    protected $fillable = [
        'provider',
        'prompt',
        'response',
        'decision',
    ];

    protected $casts = [
        'decision' => 'array',
    ];
}
