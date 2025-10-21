# 🚀 OpenRouter + DeepSeek AI Trading Bot

## ✅ Kurulum Tamamlandı!

Trading bot artık **OpenRouter** üzerinden **DeepSeek AI** kullanıyor.

---

## 📋 Yapılandırma

### .env Dosyası
```env
# OpenRouter API (DeepSeek için)
OPENROUTER_API_KEY=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

# Binance API
BINANCE_API_KEY=KNJYZSbYLHXjVsvQdfPK6WZMydJFzMKs8H7HWR6QwFlEsIziNSScgA9PyjDvVpJw
BINANCE_API_SECRET=MTOTyPNRAQl78f4fZjPltgQkuqk0wcJZPriZJ3TkM3g1YcyAG7QW8DzDUxcdjJpu

# Trading Mode
TRADING_MODE=live  # mock, testnet, live
```

---

## 🤖 AI Modeli: DeepSeek via OpenRouter

### Model Bilgileri:
- **Model ID**: `deepseek/deepseek-chat`
- **Provider**: OpenRouter
- **Avantajlar**:
  - ✅ Yüksek performans
  - ✅ Düşük maliyet
  - ✅ JSON mode desteği
  - ✅ Güvenilir API

### Alternatif Modeller (OpenRouter):
```php
// AIService.php içinde değiştirilebilir:
'model' => 'deepseek/deepseek-chat',           // DeepSeek (önerilen)
'model' => 'anthropic/claude-3.5-sonnet',      // Claude 3.5 Sonnet
'model' => 'openai/gpt-4-turbo',               // GPT-4 Turbo
'model' => 'google/gemini-pro',                // Gemini Pro
'model' => 'meta-llama/llama-3-70b-instruct',  // Llama 3 70B
```

---

## 🎯 AI Özellikler

### 1. Akıllı Market Analizi
```php
// BTC/USDT ve ETH/USDT için real-time data
- Fiyat
- 24h değişim
- Volume
- High/Low
```

### 2. Risk Yönetimi
```php
- Confidence threshold: 0.7 (altında HOLD)
- Position sizing: $100 USDT
- Max leverage: 2x
- Stop loss: -3%
- Take profit: +5%
```

### 3. Trading Kişiliği
```
✅ Capital preservation odaklı
✅ High probability setup bekler
✅ Teknik analiz kullanır
✅ FOMO/panic yapmaz
✅ Risk/reward oranına dikkat eder
```

---

## 🧪 Test Etme

### 1. AI'yı Aktif Et
```bash
php artisan tinker
```

```php
use App\Models\BotSetting;
BotSetting::set('use_ai', true);
exit
```

### 2. Manuel Test
```bash
# Sunucu zaten çalışıyor
curl -X POST http://localhost:8000/api/trade/execute
```

### 3. Beklenen Akış:
```
1. 🔍 Market verilerini topla (Binance API)
2. 📊 Hesap durumunu analiz et
3. 🤖 OpenRouter'a prompt gönder
4. 💡 DeepSeek AI karar verir
5. ✅ Decision validation (confidence > 0.7)
6. 💸 Trade execute / HOLD
7. 📝 Log kaydı
```

---

## 📊 Örnek AI Response

```json
{
  "action": "buy",
  "symbol": "BTC/USDT",
  "reasoning": "BTC showing strong bullish momentum with +2.5% gain. Price broke above resistance at $67k. Volume confirms buyer interest. RSI at 62 (neutral-bullish), MACD crossing up. Entry here offers good risk/reward with stop below $66k support.",
  "confidence": 0.85,
  "technical_analysis": "Bullish trend, RSI: 62, MACD positive, Support: $66k",
  "risk_level": "medium",
  "entry_price": 67500.00,
  "target_price": 70875.00,
  "stop_price": 65475.00
}
```

---

## 🔄 AI vs Simple Strategy

| Feature | Simple Strategy | AI Strategy (DeepSeek) |
|---------|----------------|------------------------|
| Market Analysis | ❌ None | ✅ Real-time data |
| Technical Analysis | ❌ None | ✅ RSI, MACD, Trend |
| Risk Assessment | ❌ Basic | ✅ Advanced |
| Confidence Score | ❌ None | ✅ 0-1 scale |
| Decision Reasoning | ❌ Fixed rules | ✅ Context-aware |
| Adaptability | ❌ Static | ✅ Dynamic |

---

## 💰 Maliyet (OpenRouter)

### DeepSeek Pricing:
- **Input**: ~$0.14 / 1M tokens
- **Output**: ~$0.28 / 1M tokens

### Örnek Hesaplama:
```
Her trade için:
- Prompt: ~1,500 tokens (~$0.0002)
- Response: ~200 tokens (~$0.00006)
- Toplam: ~$0.00026 per trade

1000 trade = ~$0.26
10,000 trade = ~$2.60

Çok düşük maliyet! 🎉
```

---

## 🔐 Güvenlik Önlemleri

### 1. Confidence Threshold
```php
if ($confidence < 0.7) {
    return 'hold';  // Emin değilse risk almaz
}
```

### 2. Error Handling
```php
catch (\Exception $e) {
    return [
        'action' => 'hold',  // Hata = HOLD
        'risk_level' => 'high'
    ];
}
```

### 3. Decision Validation
```php
- Required fields check
- Confidence range validation
- Position size limits
- Leverage limits
```

---

## 📈 Monitoring

### Log Dosyası
```bash
tail -f storage/logs/laravel.log | grep "🤖"
```

### Database Query
```sql
SELECT
    action,
    JSON_EXTRACT(decision_data, '$.confidence') as confidence,
    JSON_EXTRACT(decision_data, '$.reasoning') as reasoning,
    success,
    executed_at
FROM trade_logs
WHERE JSON_EXTRACT(decision_data, '$.confidence') > 0.7
ORDER BY executed_at DESC
LIMIT 20;
```

---

## 🎛️ Ayarlar

### Bot Settings (Database)
```php
BotSetting::set('use_ai', true);           // AI açık/kapalı
BotSetting::set('max_leverage', 2);        // Max kaldıraç
BotSetting::set('position_size_usdt', 100); // Pozisyon büyüklüğü
BotSetting::set('take_profit_percent', 5); // Kar al %
BotSetting::set('stop_loss_percent', 3);   // Zarar kes %
```

---

## 🚨 Sorun Giderme

### Problem: AI response boş
```
Çözüm: OpenRouter API key kontrol et
env OPENROUTER_API_KEY değerinin doğru olduğundan emin ol
```

### Problem: Invalid JSON response
```
Çözüm: Model response_format destekliyor mu kontrol et
DeepSeek JSON mode destekler, sorun olmamalı
```

### Problem: Confidence her zaman düşük
```
Çözüm: Market koşulları belirsiz olabilir
AI emin olmadığında hold yapması normaldir
```

---

## 📚 Dokümantasyon

- [OpenRouter Docs](https://openrouter.ai/docs)
- [DeepSeek Model](https://openrouter.ai/models/deepseek/deepseek-chat)
- [Laravel OpenRouter Package](https://github.com/moe-mizrak/laravel-openrouter)

---

## ✨ Sonuç

DeepSeek AI trading bot'unuz hazır! 🎉

**Özellikler:**
- ✅ OpenRouter entegrasyonu
- ✅ DeepSeek AI decision making
- ✅ Real-time market analysis
- ✅ Risk management
- ✅ Confidence-based trading
- ✅ Comprehensive logging

**Sıradaki adımlar:**
1. Test trading yapın
2. Logları izleyin
3. Performance metrics toplayın
4. Stratejileri optimize edin

Good luck trading! 🚀💰
