# 🤖 DeepSeek AI Trading Bot - Test Guide

## ✅ Yapılanlar

### 1. Gelişmiş AI Service
- ✅ Detaylı market analizi (BTC/USDT, ETH/USDT)
- ✅ Teknik analiz ve trend tespiti
- ✅ Risk yönetimi ve confidence threshold (0.7)
- ✅ Trading geçmişi analizi
- ✅ Akıllı decision validation

### 2. AI Özellikleri

**System Prompt:**
- Kripto trading uzmanı kişiliği
- Risk yönetimi odaklı yaklaşım
- Teknik analiz bilgisi (RSI, MACD, Support/Resistance)
- Capital preservation önceliği

**Prompt İçeriği:**
- 📊 Hesap durumu (bakiye, pozisyonlar, kullanım oranı)
- 📈 Real-time market data (fiyat, volume, 24h değişim)
- 📉 Geçmiş performans
- ⚙️ Trading kuralları ve kısıtlamalar
- 🎯 Detaylı karar kriterleri

### 3. AI Kararları

```json
{
  "action": "buy|close_profitable|stop_loss|hold",
  "symbol": "BTC/USDT",
  "reasoning": "Detaylı analiz ve karar gerekçesi",
  "confidence": 0.85,
  "technical_analysis": "TA özeti",
  "risk_level": "low|medium|high",
  "entry_price": 45000.50,
  "target_price": 47250.00,
  "stop_price": 43650.00
}
```

---

## 🚀 AI'yı Aktif Etme

### Adım 1: Database'de AI'yı Aç
```bash
php artisan tinker
```

```php
use App\Models\BotSetting;

// AI'yı aktif et
BotSetting::set('use_ai', true);

// Kontrol et
BotSetting::get('use_ai'); // true döndürmeli
```

### Adım 2: .env Dosyasını Kontrol Et
```env
DEEPSEEK_API_KEY=sk-5b7506c49b514a46a09432deec0625e5
TRADING_MODE=live  # veya mock test için
```

---

## 🧪 Test Senaryoları

### Test 1: AI Status Check
```bash
curl http://localhost:8000/api/trade/status
```

Beklenen:
```json
{
  "success": true,
  "data": {
    "settings": {
      "use_ai": true  // ✅ AI aktif
    }
  }
}
```

### Test 2: AI ile Otomatik Trade
```bash
curl -X POST http://localhost:8000/api/trade/execute
```

AI şunları yapacak:
1. 🔍 Market verilerini topla (BTC, ETH fiyat, volume, değişim)
2. 📊 Hesap durumunu analiz et
3. 📈 Geçmiş trade'leri incele
4. 🤖 DeepSeek'e detaylı prompt gönder
5. ✅ AI kararını al ve validate et (confidence > 0.7)
6. 💸 Trade'i çalıştır veya HOLD et

---

## 📝 Örnek AI Prompt (Gönderilen)

```markdown
# TRADING CONTEXT & MARKET ANALYSIS

## 📊 ACCOUNT STATUS
- Total Portfolio Value: $10,000.00 USDT
- Available Cash: $10,000.00 USDT
- Open Positions: 0
- Portfolio Utilization: 0%

## 💼 OPEN POSITIONS
None - Ready to enter new positions when conditions are favorable.

## 📈 MARKET DATA
### BTC/USDT
- Current Price: $67,234.50
- 24h Change: 📈 2.34%
- 24h High: $68,100.00
- 24h Low: $65,800.00
- 24h Volume: 23,456.78

### ETH/USDT
- Current Price: $3,456.20
- 24h Change: 📈 1.89%
- 24h High: $3,520.00
- 24h Low: $3,390.00
- 24h Volume: 156,789.45

## 📉 RECENT PERFORMANCE
No recent trading history.

## ⚙️ TRADING RULES & CONSTRAINTS
- Maximum Leverage: 2x
- Position Size: $100 USDT per trade
- Take Profit Target: >5%
- Stop Loss: <-3%
- Max Open Positions: 1
- Trading Mode: Conservative with risk management

## 🎯 DECISION CRITERIA

### FOR BUY:
- Must have NO open positions ✅
- Available cash > $100 ✅
- Market showing bullish momentum
- Technical indicators aligned
- Clear support levels identified
- Confidence level > 0.7

...

## 🤔 YOUR TASK
Analyze and make a decision.
```

---

## 🤖 Örnek AI Response

```json
{
  "action": "buy",
  "symbol": "BTC/USDT",
  "reasoning": "BTC shows strong bullish momentum with +2.34% gain in 24h. Price broke above resistance at $66,800 and is holding above key support levels. Volume is healthy indicating strong buyer interest. RSI not overbought, leaving room for upside. Entry at current level offers favorable risk/reward with stop below $65,800 support and target at $70,700 (~5% gain).",
  "confidence": 0.82,
  "technical_analysis": "Bullish trend, RSI: 58 (neutral-bullish), MACD crossing up, Support: $66k, Resistance: $68k",
  "risk_level": "medium",
  "entry_price": 67234.50,
  "target_price": 70696.23,
  "stop_price": 65237.47
}
```

### AI Karar Süreci:
1. ✅ **Confidence 0.82 > 0.7** → Yeterince emin
2. ✅ **No positions** → Yeni pozisyon açabilir
3. ✅ **Cash available** → Yeterli bakiye var
4. ✅ **Bullish signals** → Alım sinyali güçlü
5. ✅ **Risk level: medium** → Kabul edilebilir risk
6. 💰 **Execute BUY** → $100 USDT ile BTC al

---

## 🔄 AI vs Simple Strategy Karşılaştırma

### Simple Strategy (AI yok)
```php
if (no_positions && cash > 100) {
    return 'buy';  // Basit kural
}
```

### AI Strategy (DeepSeek aktif)
```php
if (no_positions && cash > 100) {
    // Market analizi yap
    // Trend kontrol et
    // Risk/reward hesapla
    // Teknik indikatörler
    // Confidence > 0.7 mi?

    if (confident && favorable_setup) {
        return 'buy';
    } else {
        return 'hold';  // Belirsizse bekle
    }
}
```

---

## ⚠️ AI Güvenlik Önlemleri

### 1. Confidence Threshold
```php
if ($confidence < 0.7 && $action !== 'hold') {
    $action = 'hold';  // Override to HOLD
    // AI emin değilse risk almaz
}
```

### 2. Fallback Mechanism
```php
catch (\Exception $e) {
    return [
        'action' => 'hold',  // Hata olursa HOLD
        'reasoning' => 'AI error, safety first'
    ];
}
```

### 3. Decision Validation
- ✅ Required fields check
- ✅ Confidence range (0-1)
- ✅ Symbol validation
- ✅ Position size limits

---

## 📊 Monitoring & Logs

### Log Dosyası
```bash
tail -f storage/logs/laravel.log
```

AI logları:
```
[2025-10-20 13:00:00] 🤖 AI Prompt: # TRADING CONTEXT...
[2025-10-20 13:00:05] 🤖 AI Decision: {"action":"buy","confidence":0.82...}
[2025-10-20 13:00:06] 💡 Decision: buy BTC/USDT
[2025-10-20 13:00:07] ✅ Trade executed successfully
```

### Database Logs
```sql
SELECT * FROM trade_logs
WHERE decision_data->>'confidence' > 0.7
ORDER BY executed_at DESC
LIMIT 10;
```

---

## 🎯 Production Checklist

- [ ] DeepSeek API key doğru mu?
- [ ] `use_ai` = true olarak ayarlandı mı?
- [ ] Trading mode doğru mu? (mock/testnet/live)
- [ ] API rate limits kontrol edildi mi?
- [ ] Log monitoring aktif mi?
- [ ] Stop loss/Take profit seviyeleri uygun mu?
- [ ] İlk testler küçük miktarlarla yapıldı mı?

---

## 🚨 Acil Durum

### AI'yı Kapat
```bash
php artisan tinker
```
```php
BotSetting::set('use_ai', false);
```

### Tüm Pozisyonları Kapat
```bash
curl -X POST http://localhost:8000/api/trade/close/1
# Her pozisyon ID'si için tekrarla
```

### Botu Durdur
```bash
# Cron job'ı durdur
BotSetting::set('bot_enabled', false);
```

---

## 💡 Tips & Best Practices

1. **İlk testleri mock mode'da yap**
   ```env
   TRADING_MODE=mock
   ```

2. **Küçük position size ile başla**
   ```php
   BotSetting::set('position_size_usdt', 50);
   ```

3. **Confidence threshold'ı ayarla**
   - 0.7 = Balanced (önerilen)
   - 0.8 = Conservative (daha güvenli)
   - 0.6 = Aggressive (riskli)

4. **Logları sürekli izle**
   ```bash
   tail -f storage/logs/laravel.log | grep "🤖"
   ```

5. **Performansı track et**
   - Win rate
   - Average profit
   - Max drawdown
   - Sharpe ratio

---

## 📚 Daha Fazla Geliştirme

### Eklenebilecek Özellikler:
- [ ] Multi-timeframe analysis
- [ ] Sentiment analysis (Twitter, News)
- [ ] Backtesting framework
- [ ] Portfolio optimization
- [ ] Risk-adjusted position sizing
- [ ] Machine learning model integration
- [ ] Real-time alerts (Telegram, Email)

---

Hazırsın! 🚀 AI trading bot'u test etmeye başla.
