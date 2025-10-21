# 🚀 Trading Bot API Kullanımı

## 📍 Base URL
```
http://localhost:8000/api
```

---

## 🔥 API Endpoints

### 1. **Otomatik Trade Çalıştır**
Bot'un AI veya basit stratejiye göre karar vermesini ve trade yapmasını sağlar.

```bash
POST /api/trade/execute

# cURL örneği:
curl -X POST http://localhost:8000/api/trade/execute

# Response:
{
  "success": true,
  "message": "Trade executed successfully",
  "data": {
    "success": true,
    "action": "buy",
    "result": {
      "order_id": "MOCK_1234567890_5678",
      "symbol": "BTC/USDT",
      "price": 45000,
      "amount": 0.00444444
    },
    "duration": "2s"
  }
}
```

---

### 2. **Hesap Durumu**
Bakiye, açık pozisyonlar ve bot ayarlarını gösterir.

```bash
GET /api/trade/status

# cURL örneği:
curl http://localhost:8000/api/trade/status

# Response:
{
  "success": true,
  "data": {
    "balance": {
      "free": 9900,
      "total": 10100
    },
    "positions": [
      {
        "id": 1,
        "symbol": "BTC/USDT",
        "side": "long",
        "quantity": 0.00444444,
        "entry_price": 45000,
        "current_price": 45500,
        "unrealized_pnl": 2.22,
        "profit_percent": 1.11,
        "opened_at": "2025-10-20T13:00:00.000000Z"
      }
    ],
    "settings": {
      "bot_enabled": true,
      "use_ai": false,
      "max_leverage": 2,
      "position_size": 100
    }
  }
}
```

---

### 3. **Manuel Satın Alma**
İstediğiniz sembol ve miktarda manuel olarak satın alın.

```bash
POST /api/trade/buy
Content-Type: application/json

{
  "symbol": "BTC/USDT",
  "cost": 100,
  "leverage": 2
}

# cURL örneği:
curl -X POST http://localhost:8000/api/trade/buy \
  -H "Content-Type: application/json" \
  -d '{
    "symbol": "BTC/USDT",
    "cost": 100,
    "leverage": 2
  }'

# Response:
{
  "success": true,
  "message": "Buy order executed",
  "data": {
    "order_id": "MOCK_1234567890_5678",
    "symbol": "BTC/USDT",
    "price": 45000,
    "amount": 0.00444444
  }
}
```

---

### 4. **Pozisyon Kapatma**
Açık bir pozisyonu kapatır.

```bash
POST /api/trade/close/{positionId}

# cURL örneği:
curl -X POST http://localhost:8000/api/trade/close/1

# Response:
{
  "success": true,
  "message": "Position closed",
  "data": {
    "order_id": "MOCK_1234567890_9999",
    "symbol": "BTC/USDT",
    "pnl": 2.22
  }
}
```

---

### 5. **Trade Geçmişi**
Geçmiş trade'leri listeler.

```bash
GET /api/trade/history?limit=50

# cURL örneği:
curl http://localhost:8000/api/trade/history

# Response:
{
  "success": true,
  "data": [
    {
      "id": 1,
      "order_id": "MOCK_1234567890_5678",
      "symbol": "BTC/USDT",
      "side": "buy",
      "type": "market",
      "amount": 0.00444444,
      "price": 45000,
      "cost": 100,
      "leverage": 2,
      "status": "filled",
      "created_at": "2025-10-20T13:00:00.000000Z"
    }
  ]
}
```

---

### 6. **Trade Logları**
Bot kararlarını ve logları gösterir.

```bash
GET /api/trade/logs?limit=100

# cURL örneği:
curl http://localhost:8000/api/trade/logs

# Response:
{
  "success": true,
  "data": [
    {
      "id": 1,
      "action": "buy",
      "success": true,
      "message": "No positions and sufficient cash available",
      "account_state": {
        "cash": 10000,
        "positions": []
      },
      "executed_at": "2025-10-20T13:00:00.000000Z"
    }
  ]
}
```

---

## 🧪 Test Senaryosu

### Adım 1: Durumu Kontrol Et
```bash
curl http://localhost:8000/api/trade/status
```

### Adım 2: Manuel Satın Al
```bash
curl -X POST http://localhost:8000/api/trade/buy \
  -H "Content-Type: application/json" \
  -d '{"symbol":"BTC/USDT","cost":100,"leverage":2}'
```

### Adım 3: Durumu Tekrar Kontrol Et
```bash
curl http://localhost:8000/api/trade/status
```

### Adım 4: Pozisyonu Kapat
```bash
curl -X POST http://localhost:8000/api/trade/close/1
```

---

## ⚙️ Trading Modları

### Mock Mode (Varsayılan)
```env
TRADING_MODE=mock
```
- Gerçek API'ye bağlanmaz
- Sanal para ile test eder
- Fiyat dalgalanmalarını simüle eder

### Testnet Mode
```env
TRADING_MODE=testnet
BINANCE_TESTNET=true
```
- Binance Testnet API'yi kullanır
- Gerçek API davranışı ama test parası
- https://testnet.binancefuture.com

### Live Mode ⚠️
```env
TRADING_MODE=live
BINANCE_TESTNET=false
```
- GERÇEK PARA KULLANIR!
- Dikkatli kullanın!

---

## 🎨 Filament Admin Panel

Admin panele erişmek için:

```bash
# Admin kullanıcısı oluştur
php artisan make:filament-user

# Sunucuyu başlat
php artisan serve

# Tarayıcıda aç
http://localhost:8000/admin
```

---

## 🤖 Otomatik Trading (Cron)

`app/Console/Kernel.php` dosyasına ekle:

```php
protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        app(\App\Services\TradingService::class)->executeAutoTrade();
    })->everyFiveMinutes();
}
```

Cron'u çalıştır:
```bash
php artisan schedule:work
```

---

## 📊 Response Formatları

### Başarılı Response
```json
{
  "success": true,
  "message": "İşlem başarılı",
  "data": { ... }
}
```

### Hata Response
```json
{
  "success": false,
  "message": "İşlem başarısız",
  "error": "Hata mesajı"
}
```

---

## 🔒 Güvenlik Notları

1. **Mock mode ile başlayın** - Gerçek para harcamadan test edin
2. **API anahtarlarını güvende tutun** - .env dosyasını commit etmeyin
3. **Küçük miktarlarla test edin** - Live mode'da dikkatli olun
4. **Stop loss kullanın** - Her zaman risk yönetimi yapın

---

## 📝 Örnek JavaScript Kullanımı

```javascript
// Fetch API ile
async function executeTrade() {
  const response = await fetch('http://localhost:8000/api/trade/execute', {
    method: 'POST',
  });
  const data = await response.json();
  console.log(data);
}

// Axios ile
import axios from 'axios';

async function getStatus() {
  const { data } = await axios.get('http://localhost:8000/api/trade/status');
  console.log(data);
}

// Manuel satın alma
async function buyBTC() {
  const { data } = await axios.post('http://localhost:8000/api/trade/buy', {
    symbol: 'BTC/USDT',
    cost: 100,
    leverage: 2
  });
  console.log(data);
}
```
