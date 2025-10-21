# ⚡ Quick Start - AI Trading Bot

## 🎯 3 Adımda Başla

### 1. AI Provider Seç (.env)

```env
# Seçenek 1: OpenRouter (ÖNERİLEN)
AI_PROVIDER=openrouter
OPENROUTER_API_KEY=sk-or-v1-your-key

# Seçenek 2: DeepSeek Direkt (EN HIZLI)
AI_PROVIDER=deepseek
DEEPSEEK_API_KEY=sk-your-key

# Seçenek 3: OpenAI (EN KALİTELİ)
AI_PROVIDER=openai
OPENAI_API_KEY=sk-your-key
```

### 2. AI'yı Aktif Et

```bash
php artisan tinker --execute="use App\Models\BotSetting; BotSetting::set('use_ai', true); echo 'AI enabled';"
```

### 3. Trade Yap!

```bash
curl -X POST http://localhost:8000/api/trade/execute
```

---

## 🔄 Provider Değiştir (2 Saniyede)

```bash
# .env dosyasında değiştir
AI_PROVIDER=deepseek

# Hemen test et (restart gerekmez!)
curl -X POST http://localhost:8000/api/trade/execute
```

---

## 📊 Mevcut Durum

### Kurulu Paketler
✅ OpenRouter (`moe-mizrak/laravel-openrouter`)
✅ DeepSeek Direct (`deepseek-php/deepseek-php-client`)
✅ OpenAI (`openai-php/client`)

### Hazır Özellikler
✅ 3 AI Provider desteği
✅ Otomatik geçiş (runtime)
✅ Market analizi (BTC/USDT, ETH/USDT)
✅ Risk yönetimi (confidence > 0.7)
✅ Stop loss / Take profit
✅ Comprehensive logging
✅ Mock/Testnet/Live modları

---

## 🎮 Kullanım Örnekleri

### Otomatik Trade
```bash
curl -X POST http://localhost:8000/api/trade/execute
```

### Manuel BUY
```bash
curl -X POST http://localhost:8000/api/trade/buy \
  -H "Content-Type: application/json" \
  -d '{"symbol":"BTC/USDT","cost":100,"leverage":2}'
```

### Bot Status
```bash
curl http://localhost:8000/api/trade/status
```

### Trade Geçmişi
```bash
curl http://localhost:8000/api/trade/history
```

---

## 📝 Log İzle

```bash
# Tüm AI aktiviteleri
tail -f storage/logs/laravel.log | grep "🤖"

# Sadece kararlar
tail -f storage/logs/laravel.log | grep "AI Decision"

# Hatalar
tail -f storage/logs/laravel.log | grep "❌"
```

---

## ⚙️ Ayarlar (Database)

```php
// AI aç/kapa
BotSetting::set('use_ai', true);

// Pozisyon büyüklüğü
BotSetting::set('position_size_usdt', 100);

// Maksimum kaldıraç
BotSetting::set('max_leverage', 2);

// Kar al %
BotSetting::set('take_profit_percent', 5);

// Zarar kes %
BotSetting::set('stop_loss_percent', 3);
```

---

## 🚨 Acil Durum

### AI'yı Kapat
```bash
php artisan tinker --execute="BotSetting::set('use_ai', false);"
```

### Provider Değiştir
```env
AI_PROVIDER=openrouter  # En güvenilir
```

### Mock Mode'a Geç
```env
TRADING_MODE=mock
```

---

## 💡 Hangi Provider?

| Provider | Ne Zaman Kullan | Maliyet |
|----------|-----------------|---------|
| **OpenRouter** | Genel kullanım, esneklik | ~$0.26/1k trade |
| **DeepSeek** | Maksimum hız | ~$0.26/1k trade |
| **OpenAI** | Maksimum kalite | ~$100/1k trade |

**Tavsiye**: OpenRouter ile başla!

---

## 📚 Detaylı Dokümantasyon

- [AI Provider Kılavuzu](AI_PROVIDER_GUIDE.md) - Detaylı provider karşılaştırması
- [API Kullanımı](API_USAGE.md) - Tüm API endpoint'leri
- [OpenRouter Setup](OPENROUTER_SETUP.md) - OpenRouter detayları
- [AI Testing](TEST_AI_TRADING.md) - AI test senaryoları

---

## ✅ Checklist

- [ ] AI provider seçtim (.env)
- [ ] API key ekledim
- [ ] AI'yı aktif ettim (use_ai = true)
- [ ] İlk test trade'i yaptım
- [ ] Log'ları kontrol ettim
- [ ] Provider değiştirmeyi denedim

---

## 🎉 Hazırsın!

Bot şu anda **OpenRouter** kullanıyor ve hazır. İstediğin zaman:

```bash
# DeepSeek'e geç (daha hızlı)
AI_PROVIDER=deepseek

# OpenAI'ye geç (daha kaliteli)
AI_PROVIDER=openai
```

Happy Trading! 🚀💰
