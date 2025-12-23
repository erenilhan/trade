<?php

namespace App\Console\Commands;

use App\Models\MarketData;
use App\Models\AiLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupDatabase extends Command
{
    protected $signature = 'db:cleanup {--days=7 : Days to keep market data} {--ai-days=3 : Days to keep AI logs}';
    protected $description = 'Clean old database records to save space';

    public function handle()
    {
        $marketDays = $this->option('days');
        $aiDays = $this->option('ai-days');
        
        $this->info("🧹 Starting database cleanup...");
        
        // Market Data cleanup
        $marketCutoff = now()->subDays($marketDays);
        $oldMarketCount = MarketData::where('created_at', '<', $marketCutoff)->count();
        
        if ($oldMarketCount > 0) {
            $this->info("Deleting {$oldMarketCount} market data records older than {$marketDays} days...");
            MarketData::where('created_at', '<', $marketCutoff)->delete();
            $this->info("✅ Market data cleaned");
        }
        
        // AI Logs cleanup
        $aiCutoff = now()->subDays($aiDays);
        $oldAiCount = AiLog::where('created_at', '<', $aiCutoff)->count();
        
        if ($oldAiCount > 0) {
            $this->info("Deleting {$oldAiCount} AI log records older than {$aiDays} days...");
            AiLog::where('created_at', '<', $aiCutoff)->delete();
            $this->info("✅ AI logs cleaned");
        }
        
        // Cache cleanup
        $cacheCount = DB::table('cache')->count();
        if ($cacheCount > 0) {
            DB::table('cache')->truncate();
            DB::table('cache_locks')->truncate();
            $this->info("✅ Cache cleared ({$cacheCount} records)");
        }
        
        $this->info("🎉 Database cleanup completed!");
    }
}
