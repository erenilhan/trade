<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TaapiUsage extends Model
{
    protected $table = 'taapi_usage';

    protected $fillable = [
        'date',
        'request_count',
        'daily_limit',
    ];

    protected $casts = [
        'date' => 'date',
        'request_count' => 'integer',
        'daily_limit' => 'integer',
    ];

    /**
     * Increment request count for today
     */
    public static function incrementRequestCount(int $count = 1): void
    {
        $today = now()->toDateString();

        DB::table('taapi_usage')
            ->updateOrInsert(
                ['date' => $today],
                [
                    'request_count' => DB::raw("request_count + {$count}"),
                    'updated_at' => now(),
                ]
            );
    }

    /**
     * Get today's usage
     */
    public static function getTodayUsage(): array
    {
        $today = now()->toDateString();

        $usage = self::where('date', $today)->first();

        if (!$usage) {
            return [
                'request_count' => 0,
                'daily_limit' => 5000,
                'remaining' => 5000,
            ];
        }

        return [
            'request_count' => $usage->request_count,
            'daily_limit' => $usage->daily_limit,
            'remaining' => max(0, $usage->daily_limit - $usage->request_count),
        ];
    }

    /**
     * Check if limit is reached
     */
    public static function isLimitReached(): bool
    {
        $usage = self::getTodayUsage();
        return $usage['remaining'] <= 0;
    }

    /**
     * Get remaining requests for today
     */
    public static function getRemainingRequests(): int
    {
        $usage = self::getTodayUsage();
        return $usage['remaining'];
    }
}
