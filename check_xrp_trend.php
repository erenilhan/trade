<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\MarketDataService;
use Illuminate\Support\Facades\App;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$marketData = new MarketDataService();

try {
    echo "🔍 XRP/USDT Trend Analysis\n";
    echo "========================\n\n";
    
    // Get 3m data for current signals
    $data3m = $marketData->collectMarketData('XRP/USDT', '3m');
    
    // Get 4h data for trend context
    $data4h = $marketData->collectMarketData('XRP/USDT', '4h');
    
    $currentPrice = $data3m['close'];
    
    echo "📊 Current Price: $" . number_format($currentPrice, 4) . "\n\n";
    
    echo "🕐 3m Timeframe:\n";
    echo "  EMA20: $" . number_format($data3m['ema20'], 4) . "\n";
    echo "  EMA50: $" . number_format($data3m['ema50'], 4) . "\n";
    echo "  MACD: " . number_format($data3m['macd'], 6) . "\n";
    echo "  Signal: " . number_format($data3m['macd_signal'], 6) . "\n";
    echo "  RSI7: " . number_format($data3m['rsi7'], 2) . "\n";
    echo "  ADX: " . number_format($data3m['adx'], 2) . "\n\n";
    
    echo "🕓 4h Timeframe:\n";
    echo "  EMA20: $" . number_format($data4h['ema20'], 4) . "\n";
    echo "  EMA50: $" . number_format($data4h['ema50'], 4) . "\n";
    echo "  ADX: " . number_format($data4h['adx'], 2) . "\n\n";
    
    // Check trend invalidation signals for SHORT position
    $invalidationReasons = [];
    
    // For SHORT: Price should stay BELOW EMA20
    if ($currentPrice > $data3m['ema20']) {
        $invalidationReasons[] = "❌ Price ABOVE EMA20 (bearish setup broken)";
    } else {
        echo "✅ Price below EMA20 (SHORT setup intact)\n";
    }
    
    // For SHORT: MACD should stay negative
    if ($data3m['macd'] > 0) {
        $invalidationReasons[] = "❌ MACD turned positive (momentum shift)";
    } else {
        echo "✅ MACD negative (bearish momentum)\n";
    }
    
    // Check 4H trend strength
    if ($data4h['adx'] < 20) {
        $invalidationReasons[] = "❌ 4H ADX weak (" . number_format($data4h['adx'], 1) . " < 20)";
    } else {
        echo "✅ 4H ADX strong (" . number_format($data4h['adx'], 1) . " >= 20)\n";
    }
    
    // For SHORT: 4H should be bearish (EMA20 < EMA50)
    if ($data4h['ema20'] > $data4h['ema50']) {
        $invalidationReasons[] = "❌ 4H trend turned bullish (EMA20 > EMA50)";
    } else {
        echo "✅ 4H trend bearish (EMA20 < EMA50)\n";
    }
    
    echo "\n🚨 TREND INVALIDATION CHECK:\n";
    if (count($invalidationReasons) == 0) {
        echo "✅ No invalidation signals - SHORT setup still valid\n";
        echo "💡 Recommendation: HOLD position\n";
    } else {
        echo "⚠️ Invalidation signals detected:\n";
        foreach ($invalidationReasons as $reason) {
            echo "  $reason\n";
        }
        
        if (count($invalidationReasons) >= 3) {
            echo "\n🚨 CRITICAL: 3+ signals - CLOSE IMMEDIATELY\n";
        } elseif (count($invalidationReasons) >= 2) {
            echo "\n⚠️ WARNING: 2+ signals - Consider closing if PNL < 2%\n";
            echo "💡 Current PNL: 4.17% - Position still profitable, monitor closely\n";
        } else {
            echo "\n💡 Recommendation: Monitor closely, 1 warning signal\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
