# 🤖 Trading Automation Guide

## 🕐 Cron Job Setup

### Otomatik Trading'i Aktif Et

Bot her 3 dakikada bir otomatik olarak çalışsın istiyorsan:

#### 1. Crontab Aç
```bash
crontab -e
```

#### 2. Laravel Scheduler Ekle
```bash
* * * * * cd /Users/erenilhan/Sites/trade && php artisan schedule:run >> /dev/null 2>&1
```

Bu satır **her dakika** Laravel scheduler'ı çalıştırır. Laravel kendi içinde `everyThreeMinutes()` kontrolü yapar.

#### 3. Bot'u Aktif Et
```bash
php artisan tinker --execute="use App\Models\BotSetting; BotSetting::set('bot_enabled', true);"
```

#### 4. Manuel Test
Cron beklemeden test et:
```bash
php artisan trading:multi-coin
```

Çıktı:
```
🚀 Starting multi-coin trading...
💰 Cash: $4927.64, Total: $13384.14
🤖 AI made 6 decisions
🎯 BTC/USDT: hold (confidence: 0.65)
🎯 ETH/USDT: buy (confidence: 0.82)
...
✅ Trading cycle complete
```

---

## ⏱️ Çalışma Sıklığı Ayarla

`routes/console.php` dosyasında değiştirebilirsin:

```php
// Her 3 dakikada (varsayılan - 3m candle'larla uyumlu)
Schedule::command('trading:multi-coin')->everyThreeMinutes();

// Her 5 dakikada
Schedule::command('trading:multi-coin')->everyFiveMinutes();

// Her 15 dakikada
Schedule::command('trading:multi-coin')->everyFifteenMinutes();

// Her 1 saatte
Schedule::command('trading:multi-coin')->hourly();

// Custom (her 2 dakika)
Schedule::command('trading:multi-coin')->cron('*/2 * * * *');
```

**Tavsiye**: 3m timeframe kullanıyorsan → `everyThreeMinutes()`

---

## 💸 Otomatik TP/SL (Take Profit / Stop Loss)

### Şu Anki Durum

Bot **sadece pozisyon açıyor**, TP/SL için **şu an manual karar veriyor**:

```php
// AI kararı:
"action": "close_profitable"  // Kar al
"action": "stop_loss"         // Zarar kes
```

### Otomatik TP/SL Nasıl Çalışır?

#### Seçenek 1: Binance TP/SL Order'ları (ÖNERİLEN)

Pozisyon açılırken **Binance'e otomatik TP/SL order** gönderir:

```php
// Position açıldığında:
1. Market Buy BTC (entry)
2. Stop Loss Order (fiyat < stop_price → otomatik sat)
3. Take Profit Order (fiyat > target_price → otomatik sat)
```

**Avantaj**:
- ✅ Binance otomatik çalıştırır
- ✅ Bot crash olsa bile çalışır
- ✅ 7/24 aktif

**Dezavantaj**:
- ❌ Futures API gerektirir (şu an yok)

#### Seçenek 2: Cron Job Kontrolü (MEVCUT)

Her 3 dakikada AI kontrol eder:

```php
// Her çalıştırmada:
foreach (positions as $position) {
    if ($position->profit > 5%) {
        AI decision: "close_profitable"
    }
    if ($position->loss < -3%) {
        AI decision: "stop_loss"
    }
}
```

**Avantaj**:
- ✅ Esnek (AI dinamik karar verebilir)
- ✅ Şu an çalışıyor

**Dezavantaj**:
- ❌ 3 dakika gecikmeli
- ❌ Bot kapalıysa çalışmaz

---

## 🔧 Binance TP/SL Eklemek İster misin?

Şu an `BinanceService` sadece **market order** yapıyor. TP/SL için:

### 1. Position Açarken TP/SL Order Gönder

```php
// executeBuy() içinde:

// 1. Market Buy
$order = $binance->createMarketBuy($symbol, $quantity, ['leverage' => $leverage]);

// 2. Stop Loss Order (otomatik)
$slOrder = $binance->createStopLoss(
    symbol: $symbol,
    amount: $quantity,
    stopPrice: $stopPrice,  // AI'dan gelen
    side: 'sell'
);

// 3. Take Profit Order (otomatik)
$tpOrder = $binance->createTakeProfit(
    symbol: $symbol,
    amount: $quantity,
    profitPrice: $targetPrice,  // AI'dan gelen
    side: 'sell'
);

// 4. Order ID'leri kaydet
$position->update([
    'sl_order_id' => $slOrder['id'],
    'tp_order_id' => $tpOrder['id'],
]);
```

Böylece **Binance** otomatik kapatır, bot müdahale etmez!

---

## 📊 Monitoring

### Log İzle
```bash
# Cron çalışmalarını izle
tail -f storage/logs/laravel.log | grep "trading:multi-coin"

# Sadece kararları göster
tail -f storage/logs/laravel.log | grep "🎯"
```

### Database Query
```sql
-- Son 10 cron çalışması
SELECT * FROM trade_logs
ORDER BY executed_at DESC
LIMIT 10;

-- Aktif pozisyonlar
SELECT symbol, entry_price, current_price, unrealized_pnl
FROM positions
WHERE is_open = 1;
```

---

## 🚨 Bot Kontrolü

### Bot'u Durdur
```bash
php artisan tinker --execute="BotSetting::set('bot_enabled', false);"
```

### Bot Durumunu Kontrol
```bash
php artisan tinker --execute="echo BotSetting::get('bot_enabled') ? 'ENABLED' : 'DISABLED';"
```

### Cron'u Kaldır
```bash
crontab -e
# Laravel satırını sil veya comment out:
# * * * * * cd /path && php artisan schedule:run
```

---

## ⚙️ Gelişmiş Ayarlar

### Paralel Çalışma Önle
```php
Schedule::command('trading:multi-coin')
    ->everyThreeMinutes()
    ->withoutOverlapping()  // ✅ Önceki bitmeden yenisini başlatma
    ->onOneServer();        // ✅ Tek server'da çalıştır
```

### Hata Durumunda Email
```php
Schedule::command('trading:multi-coin')
    ->everyThreeMinutes()
    ->onFailure(function () {
        // Email gönder veya Telegram bildirimi
    });
```

### Run in Background
```php
Schedule::command('trading:multi-coin')
    ->everyThreeMinutes()
    ->runInBackground();  // ✅ Diğer scheduler'ları bloklamaz
```

---

## 🎯 Özet: Şu Anki Sistem

### ✅ Çalışan
1. **Manuel Trigger**: `curl -X POST /api/multi-coin/execute`
2. **Artisan Command**: `php artisan trading:multi-coin`
3. **Cron Schedule**: Her 3 dakikada otomatik
4. **AI Decision**: 6 coin için buy/hold/close kararı
5. **Position Tracking**: Database'de takip

### ⏳ Eksik (İstersen Eklerim)
1. **Binance TP/SL Orders**: Otomatik TP/SL order gönderimi
2. **Real-time Price Update**: Position current_price güncelleme
3. **Telegram Notifications**: Trade bildirimleri
4. **Emergency Stop**: Hızlı pozisyon kapatma

---

## 💡 Tavsiyelerim

### Başlangıç İçin (Güvenli):
```bash
# 1. Cron'u kur (her 5 dakika)
Schedule::command('trading:multi-coin')->everyFiveMinutes();

# 2. Küçük position size
BotSetting::set('position_size_usdt', 50);

# 3. Düşük leverage
BotSetting::set('max_leverage', 2);

# 4. Sadece izle (mock mode)
TRADING_MODE=mock
```

### Production İçin:
```bash
# 1. Her 3 dakika
Schedule::command('trading:multi-coin')->everyThreeMinutes();

# 2. Binance TP/SL ekle (güvenli)
# (Bunu istersen yapabilirim)

# 3. Live mode
TRADING_MODE=live

# 4. Monitoring aktif
tail -f storage/logs/laravel.log
```

---

## 📞 Sıradaki Adım?

1. **Cron'u test et**: `php artisan trading:multi-coin`
2. **Binance TP/SL ister misin?** (Otomatik TP/SL order)
3. **Telegram bildirimleri?** (Her trade'de bildirim)

Hangisini yapalım? 🚀
