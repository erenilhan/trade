<?php

namespace App\Console\Commands;

use App\Models\BotSetting;
use App\Services\BinanceService;
use App\Services\MultiCoinAIService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Exception;

class ExecuteMultiCoinTrading extends Command
{
    protected $signature = 'trading:multi-coin';
    protected $description = 'Execute multi-coin trading with AI';

    public function __construct(
        private readonly MultiCoinAIService $ai,
        private readonly BinanceService     $binance
    )
    {
        parent::__construct();
    }

    public function handle(): int
    {
        // Check if bot is enabled
        if (!BotSetting::get('bot_enabled', true)) {
            $this->warn('⚠️ Bot is disabled');
            return self::FAILURE;
        }

        $this->info('🚀 Starting multi-coin trading...');

        try {
            // Get account state
            $balance = $this->binance->fetchBalance();
            $cash = $balance['USDT']['free'] ?? 10000;
            $totalValue = $balance['USDT']['total'] ?? 10000;

            $account = [
                'cash' => $cash,
                'total_value' => $totalValue,
                'return_percent' => (($totalValue - 10000) / 10000) * 100,
            ];

            $this->info("💰 Cash: \${$cash}, Total: \${$totalValue}");

            // Get AI decision
            $aiDecision = $this->ai->makeDecision($account);

            $this->info("🤖 AI made " . count($aiDecision['decisions'] ?? []) . " decisions");

            // Execute decisions (simplified for cron)
            foreach ($aiDecision['decisions'] ?? [] as $decision) {
                $symbol = $decision['symbol'];
                $action = $decision['action'];
                $confidence = $decision['confidence'] ?? 0;

                if ($confidence < 0.7 && $action !== 'hold') {
                    $this->warn("⚠️ {$symbol}: Low confidence, holding");
                    continue;
                }

                $this->line("🎯 {$symbol}: {$action} (confidence: {$confidence})");
            }

            $this->info('✅ Trading cycle complete');
            return self::SUCCESS;

        } catch (Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            Log::error('Trading command failed', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }
    }
}
