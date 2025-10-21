# 🤖 AI Provider Geçiş Kılavuzu

Trading bot'u artık **3 farklı AI provider** destekliyor ve aralarında kolayca geçiş yapabilirsin!

---

## 🎯 Desteklenen Provider'lar

### 1. OpenRouter (Önerilen)
- **Avantajlar**:
  - ✅ Çok sayıda model desteği (DeepSeek, Claude, GPT-4, Gemini, Llama...)
  - ✅ Tek API key ile multiple modeller
  - ✅ Otomatik fallback
  - ✅ Düşük maliyet
- **Model**: `deepseek/deepseek-chat` (varsayılan)
- **Maliyet**: ~$0.00026 per trade

### 2. DeepSeek (Direkt API)
- **Avantajlar**:
  - ✅ En düşük latency
  - ✅ Direkt bağlantı
  - ✅ Çok düşük maliyet
- **Model**: `deepseek-chat`
- **Maliyet**: ~$0.00026 per trade

### 3. OpenAI
- **Avantajlar**:
  - ✅ En güçlü modeller (GPT-4 Turbo)
  - ✅ Yüksek kalite
- **Model**: `gpt-4-turbo-preview`
- **Maliyet**: ~$0.10 per trade (daha pahalı)

---

## ⚡ Hızlı Geçiş

### .env Dosyasında Tek Satır!

```env
# OpenRouter kullan (önerilen)
AI_PROVIDER=openrouter

# DeepSeek direkt API kullan (en hızlı)
AI_PROVIDER=deepseek

# OpenAI kullan (en güçlü)
AI_PROVIDER=openai
```

**Hepsi bu!** Cache temizlemeye gerek yok, restart'a gerek yok. Bir sonraki trade'de otomatik değişecek.

---

## 📋 Provider Kurulum

### OpenRouter (Varsayılan)

**1. API Key Al**
- https://openrouter.ai/ adresine git
- Hesap aç ve API key oluştur

**2. .env Ayarla**
```env
AI_PROVIDER=openrouter
OPENROUTER_API_KEY=sk-or-v1-your-api-key-here

# İsteğe bağlı: Farklı model seç
OPENROUTER_MODEL=deepseek/deepseek-chat  # Varsayılan
# OPENROUTER_MODEL=anthropic/claude-3.5-sonnet
# OPENROUTER_MODEL=openai/gpt-4-turbo
# OPENROUTER_MODEL=google/gemini-pro
```

**3. Test Et**
```bash
curl -X POST http://localhost:8000/api/trade/execute
```

---

### DeepSeek (Direkt)

**1. API Key Al**
- https://platform.deepseek.com/ adresine git
- Hesap aç ve API key oluştur

**2. .env Ayarla**
```env
AI_PROVIDER=deepseek
DEEPSEEK_API_KEY=sk-your-deepseek-key-here

# İsteğe bağlı: Model değiştir
DEEPSEEK_MODEL=deepseek-chat  # Varsayılan
```

**3. Test Et**
```bash
curl -X POST http://localhost:8000/api/trade/execute
```

---

### OpenAI

**1. API Key Al**
- https://platform.openai.com/ adresine git
- Hesap aç ve API key oluştur

**2. .env Ayarla**
```env
AI_PROVIDER=openai
OPENAI_API_KEY=sk-your-openai-key-here
```

**3. Test Et**
```bash
curl -X POST http://localhost:8000/api/trade/execute
```

---

## 🔄 Runtime'da Geçiş

### Senaryo: OpenRouter → DeepSeek

```bash
# 1. .env'de değiştir
AI_PROVIDER=deepseek

# 2. Hemen test et (restart gerekmez)
curl -X POST http://localhost:8000/api/trade/execute
```

### Senaryo: A/B Testing

Test için farklı provider'ları dene:

```bash
# OpenRouter ile 10 trade
AI_PROVIDER=openrouter
# ... trade yap ...

# DeepSeek ile 10 trade
AI_PROVIDER=deepseek
# ... trade yap ...

# OpenAI ile 10 trade
AI_PROVIDER=openai
# ... trade yap ...

# Logları karşılaştır
tail -f storage/logs/laravel.log | grep "🤖"
```

---

## 📊 Provider Karşılaştırma

| Feature | OpenRouter | DeepSeek Direct | OpenAI |
|---------|-----------|----------------|--------|
| **Kurulum** | ⭐⭐⭐⭐⭐ Kolay | ⭐⭐⭐⭐⭐ Kolay | ⭐⭐⭐⭐⭐ Kolay |
| **Maliyet** | ⭐⭐⭐⭐⭐ Çok düşük | ⭐⭐⭐⭐⭐ Çok düşük | ⭐⭐⭐ Orta |
| **Hız** | ⭐⭐⭐⭐ İyi | ⭐⭐⭐⭐⭐ En hızlı | ⭐⭐⭐⭐ İyi |
| **Model Seçimi** | ⭐⭐⭐⭐⭐ 50+ model | ⭐⭐⭐ 1 model | ⭐⭐⭐⭐ GPT ailess |
| **Güvenilirlik** | ⭐⭐⭐⭐⭐ Yüksek | ⭐⭐⭐⭐ İyi | ⭐⭐⭐⭐⭐ En iyi |
| **Karar Kalitesi** | ⭐⭐⭐⭐ İyi | ⭐⭐⭐⭐ İyi | ⭐⭐⭐⭐⭐ En iyi |

---

## 💡 Hangi Provider'ı Seçmeliyim?

### OpenRouter - Genel Kullanım (Önerilen)
```
✅ Kullan:
- Genel trading için
- Farklı modelleri denemek istiyorsan
- Düşük maliyet + esneklik istiyorsan
- İlk defa AI trading yapıyorsan

❌ Kullanma:
- Mutlak minimum latency gerekiyorsa
```

### DeepSeek Direct - Performance Odaklı
```
✅ Kullan:
- Maksimum hız gerekiyorsa
- Sadece DeepSeek kullanacaksan
- En düşük maliyet istiyorsan

❌ Kullanma:
- Farklı modeller denemek istiyorsan
- GPT-4 kalitesinde AI gerekiyorsa
```

### OpenAI - Maximum Quality
```
✅ Kullan:
- En iyi karar kalitesi gerekiyorsa
- Maliyet sorun değilse
- GPT-4 Turbo özellikleri istiyorsan

❌ Kullanma:
- Maliyet önemliyse (10x daha pahalı)
- DeepSeek yeterli performans veriyorsa
```

---

## 🧪 Test Senaryosu

Tüm provider'ları test etmek için:

```bash
#!/bin/bash

echo "=== Testing All AI Providers ==="

# Test OpenRouter
echo "Testing OpenRouter..."
export AI_PROVIDER=openrouter
curl -s -X POST http://localhost:8000/api/trade/execute | jq '.data.ai_provider'

# Test DeepSeek
echo "Testing DeepSeek..."
export AI_PROVIDER=deepseek
curl -s -X POST http://localhost:8000/api/trade/execute | jq '.data.ai_provider'

# Test OpenAI
echo "Testing OpenAI..."
export AI_PROVIDER=openai
curl -s -X POST http://localhost:8000/api/trade/execute | jq '.data.ai_provider'

echo "=== Test Complete ==="
```

---

## 📝 Logları İzle

Her provider log'larda belirtiliyor:

```bash
tail -f storage/logs/laravel.log
```

Örnek log:
```
[2025-10-20 14:30:00] 🤖 AI Prompt (openrouter): # TRADING CONTEXT...
[2025-10-20 14:30:05] 🤖 AI Decision (openrouter): {"action":"buy"...}

[2025-10-20 14:35:00] 🤖 AI Prompt (deepseek): # TRADING CONTEXT...
[2025-10-20 14:35:03] 🤖 AI Decision (deepseek): {"action":"hold"...}
```

---

## ⚙️ İleri Seviye Konfigürasyon

### OpenRouter - Farklı Model Kullan

```env
AI_PROVIDER=openrouter

# DeepSeek (düşük maliyet)
OPENROUTER_MODEL=deepseek/deepseek-chat

# Claude 3.5 Sonnet (dengeli)
OPENROUTER_MODEL=anthropic/claude-3.5-sonnet

# GPT-4 Turbo (güçlü)
OPENROUTER_MODEL=openai/gpt-4-turbo

# Gemini Pro (Google)
OPENROUTER_MODEL=google/gemini-pro

# Llama 3 70B (açık kaynak)
OPENROUTER_MODEL=meta-llama/llama-3-70b-instruct
```

### DeepSeek - Custom Endpoint

```env
AI_PROVIDER=deepseek
DEEPSEEK_BASE_URL=https://api.deepseek.com/v1
DEEPSEEK_TIMEOUT=30
```

### OpenAI - Farklı Model

```php
// app/Services/AIService.php içinde:
private function callOpenAI(string $prompt): array
{
    $response = OpenAI::chat()->create([
        'model' => 'gpt-4-turbo-preview',  // veya 'gpt-3.5-turbo'
        // ...
    ]);
}
```

---

## 🚨 Sorun Giderme

### Problem: "Invalid AI provider"
```bash
Çözüm: .env'de AI_PROVIDER değerini kontrol et
Geçerli değerler: openrouter, deepseek, openai
```

### Problem: "Empty API response"
```bash
Çözüm: İlgili provider'ın API key'ini kontrol et
- OpenRouter: OPENROUTER_API_KEY
- DeepSeek: DEEPSEEK_API_KEY
- OpenAI: OPENAI_API_KEY
```

### Problem: Rate limit hatası
```bash
Çözüm 1: Daha az trade yap
Çözüm 2: Farklı provider'a geç
Çözüm 3: API key'inde limit artır
```

### Problem: Yavaş response
```bash
Çözüm 1: DeepSeek direkt API kullan (en hızlı)
Çözüm 2: DEEPSEEK_TIMEOUT'u artır
Çözüm 3: Farklı model dene
```

---

## 💰 Maliyet Optimizasyonu

### Senaryoya Göre Provider Seçimi

**Development/Testing:**
```env
AI_PROVIDER=deepseek  # En ucuz
DEEPSEEK_API_KEY=your-key
```

**Production (Balanced):**
```env
AI_PROVIDER=openrouter  # Dengeli
OPENROUTER_MODEL=deepseek/deepseek-chat
```

**Production (High-Stakes):**
```env
AI_PROVIDER=openai  # En kaliteli
OPENAI_API_KEY=your-key
```

### Maliyet Tahmini (1000 Trade)

| Provider | Model | Maliyet |
|----------|-------|---------|
| DeepSeek | deepseek-chat | ~$0.26 |
| OpenRouter | deepseek/deepseek-chat | ~$0.26 |
| OpenRouter | claude-3.5-sonnet | ~$3.00 |
| OpenRouter | gpt-4-turbo | ~$10.00 |
| OpenAI | gpt-4-turbo | ~$100.00 |

---

## ✅ Checklist: Yeni Provider Ekleme

Gelecekte başka bir AI provider eklemek isterseniz:

- [ ] `composer require` ile package yükle
- [ ] `config/` klasörüne config dosyası ekle
- [ ] `.env.example`'a API key ekle
- [ ] `AIService.php`'de yeni method oluştur (`callNewProvider`)
- [ ] `match()` statement'a yeni case ekle
- [ ] Bu dokümana ekle
- [ ] Test et

---

## 🎉 Özet

Artık 3 AI provider arasında **saniyeler içinde** geçiş yapabilirsin:

1. **.env** dosyasında `AI_PROVIDER` değiştir
2. Hemen trade yap
3. Log'larda hangi provider'ın kullanıldığını gör

**Tavsiye**: OpenRouter ile başla, ihtiyaç duyarsan diğerlerine geç!

---

## 📚 Kaynaklar

- [OpenRouter Docs](https://openrouter.ai/docs)
- [OpenRouter Models](https://openrouter.ai/models)
- [DeepSeek API Docs](https://platform.deepseek.com/docs)
- [DeepSeek PHP Client](https://github.com/deepseek-php/deepseek-php-client)
- [OpenAI API Docs](https://platform.openai.com/docs)

Happy Trading! 🚀💰
