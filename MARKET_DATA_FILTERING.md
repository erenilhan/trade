# Market Data Filtering Guide

## Overview

The trading bot now automatically filters out coins with missing or invalid market data before sending them to AI. This prevents:
- ✅ Wasted AI tokens on invalid data
- ✅ False trading signals from coins with RSI:0, ADX:0
- ✅ Attempting to trade delisted or illiquid coins

---

## How It Works

### 1. Pre-Filtering Check (Line 201-206)

Before building the AI prompt, the system checks:

```php
// CRITICAL: Skip coins with no market data (RSI=0, ADX=0)
$hasMarketData = ($data3m['rsi7'] ?? 0) > 0 && ($data4h['adx'] ?? 0) > 0;
if (!$hasMarketData) {
    Log::info("⏭️ Pre-filtered {$symbol} - No market data available");
    continue;
}
```

**Filtered if:**
- RSI (3m) = 0 **AND**
- ADX (4h) = 0

**Why these indicators?**
- RSI should always be between 0-100 (never exactly 0 unless no data)
- ADX should always be > 0 if there's price movement
- If both are 0, it's a clear sign of missing market data

---

## Console Output

### Before (Showing Invalid Data)
```
📤 Sending 30 coins to AI:
  📉 ALPACA/USDT - RSI:0 MACD:0.0000 ADX:0 Vol:1.0x ATR:0.0% 😴
  📉 PORT3/USDT - RSI:0 MACD:0.0000 ADX:0 Vol:1.0x ATR:0.0% 😴
  📈 BTC/USDT - RSI:65 MACD:0.0015 ADX:28 Vol:1.2x ATR:2.1% 💪
```

### After (Filtered Out)
```
📤 Sending 30 coins to AI:
  ⚠️ ALPACA/USDT - NO MARKET DATA (will be filtered)
  ⚠️ PORT3/USDT - NO MARKET DATA (will be filtered)
  📈 BTC/USDT - RSI:65 MACD:0.0015 ADX:28 Vol:1.2x ATR:2.1% 💪

⚠️ 2 coin(s) filtered due to missing market data

📝 AI Prompt Preview:
Length: 800 characters (only BTC sent to AI)
```

---

## Common Scenarios

### Scenario 1: Coin Recently Added to `supported_coins`

**Problem:** You added a new coin but market data hasn't been collected yet.

**What happens:**
```
⚠️ NEWCOIN/USDT - NO MARKET DATA (will be filtered)
```

**Solution:** Wait 10 minutes for the next `trading:multi-coin` cycle to collect data, or run manually:
```bash
php artisan trading:multi-coin
```

---

### Scenario 2: Coin Delisted from Binance

**Problem:** Coin was removed from Binance Futures.

**Example:** PORT3/USDT (volume = 0, price frozen at 0.01182)

**What happens:**
```
⏭️ Pre-filtered PORT3/USDT - No market data available (RSI=100, ADX=100)
```

**Solution:** Remove from `supported_coins`:
```bash
php artisan tinker
```

```php
$coins = App\Models\BotSetting::get('supported_coins', []);
if (is_string($coins)) $coins = json_decode($coins, true);

// Remove delisted coin
$coins = array_filter($coins, fn($c) => $c !== 'PORT3/USDT');

App\Models\BotSetting::set('supported_coins', array_values($coins));
```

---

### Scenario 3: Database Empty (Fresh Install)

**Problem:** `market_data` table is empty.

**What happens:**
```
📤 Sending 30 coins to AI:
  ⚪ BTC/USDT - No data in database
  ⚪ ETH/USDT - No data in database
  ...

⚠️ 30 coin(s) filtered due to missing market data
```

**Solution:** Run trading command once to populate database:
```bash
php artisan trading:multi-coin
```

This will:
1. Fetch OHLCV from Binance
2. Calculate indicators (RSI, MACD, ADX, ATR)
3. Store in `market_data` table
4. Next run will have valid data

---

## Log Examples

### Successful Filtering
```
[2025-12-23 14:00:01] ⏭️ Pre-filtered ALPACA/USDT - No market data available (RSI=0, ADX=0)
[2025-12-23 14:00:01] ⏭️ Pre-filtered PORT3/USDT - No market data available (RSI=100, ADX=100)
[2025-12-23 14:00:02] ✅ BTC/USDT passed pre-filter (potential LONG, score 4/4, volume 1.2x)
```

### Database Check
```bash
php artisan tinker
```

```php
// Check which coins have valid data
$symbols = App\Models\BotSetting::get('supported_coins', []);
if (is_string($symbols)) $symbols = json_decode($symbols, true);

foreach ($symbols as $symbol) {
    $data = App\Models\MarketData::getLatest($symbol, '3m');
    if ($data && $data->rsi_7 > 0) {
        echo "✅ {$symbol}: RSI={$data->rsi_7}\n";
    } else {
        echo "❌ {$symbol}: No data\n";
    }
}
```

---

## Performance Impact

### Token Savings

**Before filtering:**
```
30 coins × 200 tokens = 6,000 tokens per request
```

**After filtering (assuming 10 coins have no data):**
```
20 coins × 200 tokens = 4,000 tokens per request
Savings: 33% reduction in token usage
```

### Trade Quality

**Before:** AI might recommend trades on coins with invalid data
```json
{"symbol": "ALPACA/USDT", "action": "buy", "confidence": 0.75}
// But RSI=0, ADX=0 → Invalid signal!
```

**After:** AI only sees valid coins
```json
{"symbol": "BTC/USDT", "action": "buy", "confidence": 0.80}
// RSI=65, ADX=28 → Valid signal ✅
```

---

## Troubleshooting

### Issue: "All coins filtered, no data sent to AI"

**Cause:** Market data not collected yet.

**Fix:**
```bash
# Check if data exists
php artisan tinker
```

```php
App\Models\MarketData::count(); // Should be > 0
```

```bash
# If 0, run trading once to collect
php artisan trading:multi-coin
```

---

### Issue: "Coin has data but still filtered"

**Cause:** RSI or ADX calculated as 0 (edge case).

**Fix:** Check raw data:
```php
$data3m = App\Models\MarketData::getLatest('SYMBOL/USDT', '3m');
$data4h = App\Models\MarketData::getLatest('SYMBOL/USDT', '4h');

echo "RSI: {$data3m->rsi_7}\n";
echo "ADX: {$data4h->adx}\n";
```

If both are 0:
- Check if coin is delisted
- Check Binance volume
- Consider removing from `supported_coins`

---

## Configuration

### Disable Filtering (Not Recommended)

If you want to disable this safety check:

```php
// In app/Services/MultiCoinAIService.php, comment out:
// if (!$hasMarketData) {
//     Log::info("⏭️ Pre-filtered {$symbol} - No market data available");
//     continue;
// }
```

**Warning:** This will send invalid data to AI, wasting tokens and potentially causing bad trades.

---

## Summary

✅ **Automatic filtering** of coins with missing market data
✅ **Console warnings** show filtered coins
✅ **Token savings** by not sending invalid data to AI
✅ **Trade quality** improved by using only valid signals

**Key Rule:** If RSI=0 AND ADX=0, coin is filtered.

---

**Last Updated:** December 23, 2025
**Version:** 2.0.0
