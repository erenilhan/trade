# 🎯 Multi-Coin Trading System - Usage Guide

## ✅ Kurulum Tamamlandı!

Multi-coin trading sistemi hazır. 6 coin'i simultane olarak trade edebilirsin:
- BTC/USDT
- ETH/USDT
- SOL/USDT
- BNB/USDT
- XRP/USDT
- DOGE/USDT

---

## 🚀 Hızlı Başlangıç

### 1. AI'yı Aktif Et

```bash
php artisan tinker --execute="use App\Models\BotSetting; BotSetting::set('use_ai', true); echo 'AI enabled';"
```

### 2. Multi-Coin Trading Çalıştır

```bash
curl -X POST http://localhost:8000/api/multi-coin/execute
```

### 3. Status Kontrol

```bash
curl http://localhost:8000/api/multi-coin/status
```

---

## 📋 API Endpoints

### POST /api/multi-coin/execute
**Multi-coin trading çalıştır**

Tüm 6 coin için:
1. Market data topla (3m + 4h timeframes)
2. Teknik indikatörler hesapla (EMA, MACD, RSI, ATR)
3. Funding rate & Open Interest çek
4. AI'ya detaylı prompt gönder
5. Her coin için decision al
6. Trade'leri execute et

**Response:**
```json
{
  "success": true,
  "data": {
    "results": {
      "BTC/USDT": {
        "action": "buy",
        "position_id": 1,
        "quantity": 0.12,
        "entry_price": 110847.5,
        "leverage": 10
      },
      "ETH/USDT": {
        "action": "hold",
        "reason": "Waiting for better entry"
      },
      ...
    },
    "chain_of_thought": "BTC showing strong momentum...",
    "account": {
      "cash": 4927.64,
      "total_value": 13384.14,
      "return_percent": 33.84
    }
  }
}
```

---

### GET /api/multi-coin/status
**Tüm coinlerin durumunu al**

**Response:**
```json
{
  "success": true,
  "data": {
    "positions": {
      "BTC/USDT": {
        "symbol": "BTC/USDT",
        "quantity": 0.12,
        "entry_price": 107343.0,
        "current_price": 110847.5,
        "liquidation_price": 97902.07,
        "unrealized_pnl": 420.54,
        "leverage": 10,
        "exit_plan": {
          "profit_target": 118136.15,
          "stop_loss": 102026.675
        },
        "confidence": 0.75
      }
    },
    "market_data": {
      "BTC/USDT": {
        "price": 110847.5,
        "ema20": 110723.665,
        "macd": 105.318,
        "rsi7": 60.49,
        "funding_rate": 0.0000076187,
        "open_interest": 27391.46
      },
      ...
    },
    "supported_coins": ["BTC/USDT", "ETH/USDT", ...]
  }
}
```

---

## 🤖 AI Decision Format

AI her coin için şu formatta karar verir:

```json
{
  "decisions": [
    {
      "symbol": "BTC/USDT",
      "action": "buy",
      "reasoning": "BTC showing strong bullish momentum with +2.5% gain. Price broke above resistance at $67k. Volume confirms buyer interest. RSI at 62 (neutral-bullish), MACD crossing up.",
      "confidence": 0.85,
      "entry_price": 67500.00,
      "target_price": 70875.00,
      "stop_price": 65475.00,
      "invalidation": "If price closes below $66k on 3-minute candle"
    },
    {
      "symbol": "ETH/USDT",
      "action": "hold",
      "reasoning": "Waiting for RSI cooldown, currently overbought at 76",
      "confidence": 0.65
    }
  ],
  "chain_of_thought": "Market analysis: BTC leading with strong momentum. ETH following but overbought. SOL showing consolidation..."
}
```

---

## 📊 AI Prompt Yapısı

Her coin için gönderilen data:

### 3-Minute Timeframe
- Current price, EMA20, MACD, RSI7
- Last 10 prices (oldest → newest)
- EMA20 series (last 10)
- MACD series (last 10)
- RSI7 series (last 10)
- RSI14 series (last 10)
- Funding rate
- Open Interest

### 4-Hour Timeframe
- EMA20 vs EMA50
- ATR3 vs ATR14
- Current volume
- MACD trends
- RSI14 trends

### Account Info
- Available cash
- Total account value
- Return %
- All open positions with exit plans

---

## 🎯 Trading Rules

### BUY Conditions:
- ✅ No existing position for that coin
- ✅ Available cash > position_size
- ✅ AI confidence > 0.7
- ✅ Market showing favorable setup

### CLOSE Conditions:
- ✅ **close_profitable**: Profit > 5% or target reached
- ✅ **stop_loss**: Loss < -3% or stop triggered

### HOLD:
- Market uncertain
- Low confidence (<0.7)
- Position already exists
- Insufficient cash

---

## ⚙️ Configuration

### .env Settings

```env
# AI Provider
AI_PROVIDER=openrouter
OPENROUTER_API_KEY=your-key

# Trading Settings
TRADING_MODE=live  # mock, testnet, live
```

### Database Settings

```php
// Position size per trade
BotSetting::set('position_size_usdt', 100);

// Max leverage
BotSetting::set('max_leverage', 10);

// Profit/Loss thresholds
BotSetting::set('take_profit_percent', 5);
BotSetting::set('stop_loss_percent', 3);

// AI on/off
BotSetting::set('use_ai', true);
```

---

## 🧪 Test Senaryosu

### 1. İlk Test (Dry Run)
```bash
# Status kontrol
curl http://localhost:8000/api/multi-coin/status

# Execute (AI decision al ama trade yapma)
# Set TRADING_MODE=mock in .env first
curl -X POST http://localhost:8000/api/multi-coin/execute
```

### 2. Live Trading
```env
# .env
TRADING_MODE=live
```

```bash
curl -X POST http://localhost:8000/api/multi-coin/execute
```

### 3. Log İzle
```bash
tail -f storage/logs/laravel.log | grep "🤖\|🎯\|✅\|❌"
```

---

## 📈 Monitoring

### Log Categories

- `🤖` AI decisions
- `🎯` Trade decisions per coin
- `✅` Successful operations
- `❌` Errors
- `⚠️` Warnings
- `📊` Market data collection

### Database Queries

```sql
-- Active positions
SELECT symbol, quantity, entry_price, unrealized_pnl, confidence
FROM positions
WHERE is_open = 1;

-- Market data
SELECT symbol, timeframe, price, ema20, macd, rsi7
FROM market_data
WHERE timeframe = '3m'
ORDER BY data_timestamp DESC
LIMIT 6;

-- Recent decisions
SELECT symbol, action, JSON_EXTRACT(decision_data, '$.confidence') as confidence
FROM trade_logs
ORDER BY executed_at DESC
LIMIT 10;
```

---

## 🔧 Troubleshooting

### Problem: "No OHLCV data"
```
Çözüm: Binance API bağlantısını kontrol et
- API key doğru mu?
- Rate limit aşıldı mı?
- Symbol formatı doğru mu? (BTC/USDT)
```

### Problem: "AI response empty"
```
Çözüm: AI provider settings kontrol et
- OPENROUTER_API_KEY doğru mu?
- API credit var mı?
- Model destekliyor mu JSON mode?
```

### Problem: "Insufficient cash"
```
Çözüm: Position size azalt
BotSetting::set('position_size_usdt', 50);
```

### Problem: "All coins HOLD"
```
Normal: AI emin değilse HOLD yapar
- Market belirsiz olabilir
- Confidence threshold > 0.7 gerekiyor
- Zaten pozisyonlar açık olabilir
```

---

## 💡 Best Practices

### 1. Start Small
```php
// İlk günler küçük position
BotSetting::set('position_size_usdt', 50);
BotSetting::set('max_leverage', 2);
```

### 2. Monitor Frequently
```bash
# Her 5 dakikada status kontrol
watch -n 300 'curl -s http://localhost:8000/api/multi-coin/status | jq'
```

### 3. Review Decisions
```bash
# AI reasoning'leri oku
tail -f storage/logs/laravel.log | grep "chain_of_thought"
```

### 4. Track Performance
```sql
SELECT
    DATE(created_at) as date,
    COUNT(*) as trades,
    SUM(CASE WHEN realized_pnl > 0 THEN 1 ELSE 0 END) as wins,
    SUM(realized_pnl) as total_pnl
FROM positions
WHERE is_open = 0
GROUP BY DATE(created_at);
```

---

## 🎉 Özet

**Multi-coin system hazır!**

✅ 6 coin simultane trading
✅ Advanced technical analysis
✅ AI-powered decisions
✅ Funding rate & OI tracking
✅ Liquidation price calculation
✅ Exit plan per position
✅ Chain of thought logging

**Sıradaki Adımlar**:
1. Test et (mock mode)
2. Logları incele
3. Performance tracking
4. Live'a geç (dikkatli!)

Good luck! 🚀💰
