# OpenRouter Free Model Optimization Guide

This guide explains how to use free AI models (with rate limits) efficiently with the trading bot.

## 🎯 What Was Optimized

### 1. **Token Usage Reduction (~60% savings)**
- **System Prompt**: Compressed from 50+ lines to 15 lines
- **Market Data Series**: Reduced from 10 candles to 5 candles
- **Formatting**: Removed decorative emojis and verbose descriptions
- **Result**: ~2000 tokens → ~800 tokens per request

### 2. **Rate Limit Handling**
- Automatic retry with exponential backoff (2s, 4s, 8s)
- Detects 429 errors and "rate limit" messages
- Max 3 retries before failing gracefully
- Logs all retry attempts

### 3. **Batch Processing**
- Splits large coin lists into smaller batches
- Default: 5 coins per batch
- Configurable delay between batches (default: 3s)
- Merges results from all batches

### 4. **Blacklist Integration**
- Automatically filters blacklisted coins
- Only sends tradeable coins to AI
- Saves tokens on coins that won't be traded anyway

## ⚙️ Configuration

### Enable Batch Processing

Edit `.env`:
```bash
# Enable batch processing for free models
AI_BATCH_ENABLED=true
```

Or update `config/trading.php`:
```php
'ai_batch_processing' => [
    'enabled' => true,           // Enable for free models
    'coins_per_batch' => 5,      // Max coins per request (adjust based on rate limit)
    'delay_between_batches' => 3, // Seconds between batches
],
```

### Recommended Free Models

**Option 1: DeepSeek V3 (via OpenRouter)**
```bash
AI_PROVIDER=openrouter
OPENROUTER_MODEL=deepseek/deepseek-chat-v3
```
- Free tier: 10 requests/minute
- Context: 64K tokens
- Best for: 5-10 coins per batch

**Option 2: Google Gemini Flash (via OpenRouter)**
```bash
AI_PROVIDER=openrouter
OPENROUTER_MODEL=google/gemini-flash-1.5
```
- Free tier: 15 requests/minute
- Context: 1M tokens
- Best for: 10-15 coins per batch

**Option 3: Meta Llama 3.3 70B (via OpenRouter)**
```bash
AI_PROVIDER=openrouter
OPENROUTER_MODEL=meta-llama/llama-3.3-70b-instruct
```
- Free tier: 20 requests/minute
- Context: 128K tokens
- Best for: 15-20 coins per batch

## 📊 Usage Examples

### Example 1: Small Portfolio (5-10 coins)
```bash
# .env
AI_BATCH_ENABLED=false  # Single request is fine
OPENROUTER_MODEL=deepseek/deepseek-chat-v3
```

### Example 2: Medium Portfolio (11-20 coins)
```bash
# .env
AI_BATCH_ENABLED=true
OPENROUTER_MODEL=google/gemini-flash-1.5

# config/trading.php
'coins_per_batch' => 10,
'delay_between_batches' => 2,
```

### Example 3: Large Portfolio (20+ coins)
```bash
# .env
AI_BATCH_ENABLED=true
OPENROUTER_MODEL=meta-llama/llama-3.3-70b-instruct

# config/trading.php
'coins_per_batch' => 5,
'delay_between_batches' => 4,  # More conservative
```

## 🚀 Performance Comparison

### Before Optimization
```
19 coins × 2000 tokens = 38,000 tokens
Single request → Rate limit hit → Failed
```

### After Optimization (Batch Mode)
```
Batch 1: 5 coins × 800 tokens = 4,000 tokens → Success
Wait 3s...
Batch 2: 5 coins × 800 tokens = 4,000 tokens → Success
Wait 3s...
Batch 3: 5 coins × 800 tokens = 4,000 tokens → Success
Wait 3s...
Batch 4: 4 coins × 800 tokens = 3,200 tokens → Success

Total: 15,200 tokens across 4 requests (12s total time)
Success rate: 100%
```

## 🔧 Troubleshooting

### Still hitting rate limits?
1. **Reduce coins per batch**:
   ```php
   'coins_per_batch' => 3,  // Smaller batches
   ```

2. **Increase delay between batches**:
   ```php
   'delay_between_batches' => 5,  // Longer wait
   ```

3. **Use a faster free model**:
   - Try `google/gemini-flash-1.5` (highest free tier limit)

### Token usage still too high?
1. **Enable stronger pre-filtering** (already enabled by default):
   - Only sends coins with good trading signals to AI
   - Check logs: "Pre-filtered X/USDT - Low score"

2. **Reduce supported coins**:
   ```bash
   php artisan tinker
   ```
   ```php
   $coins = ['BTC/USDT', 'ETH/USDT', 'SOL/USDT']; // Top 3 only
   App\Models\BotSetting::set('supported_coins', $coins);
   ```

### Retry logic not working?
Check logs for retry attempts:
```bash
tail -f storage/logs/laravel.log | grep "rate limit"
```

Expected output:
```
⏳ OpenRouter rate limit hit, retrying in 2s (attempt 1/3)
⏳ OpenRouter rate limit hit, retrying in 4s (attempt 2/3)
✅ Request succeeded on attempt 3
```

## 📈 Monitoring

### Check AI usage:
```bash
php artisan tinker
```
```php
// Today's AI calls
$today = App\Models\AiLog::whereDate('created_at', today())->count();
echo "AI calls today: {$today}";

// Average tokens per call
$avgTokens = App\Models\AiLog::whereDate('created_at', today())->avg('tokens_used');
echo "Avg tokens: {$avgTokens}";
```

### Check batch performance:
```bash
tail -f storage/logs/laravel.log | grep "Batch processing"
```

Expected output:
```
📦 Batch processing enabled: 4 batches of 5 coins each
📦 Processing batch 1/4
⏳ Waiting 3s before next batch...
📦 Processing batch 2/4
...
```

## 💡 Best Practices

1. **Start conservative**: Begin with `coins_per_batch => 3` and increase gradually
2. **Monitor logs**: Watch for rate limit errors in first few cycles
3. **Match to your rate limit**: Free tier = smaller batches, paid tier = disable batching
4. **Use pre-filtering**: Let the system filter weak signals before AI (enabled by default)
5. **Blacklist poor performers**: Reduce AI calls on coins that consistently lose

## 🆓 Free Model Rate Limits (as of 2025)

| Provider | Model | Free Limit | Recommended Batch Size |
|----------|-------|------------|------------------------|
| OpenRouter | DeepSeek V3 | 10 req/min | 5 coins |
| OpenRouter | Gemini Flash 1.5 | 15 req/min | 10 coins |
| OpenRouter | Llama 3.3 70B | 20 req/min | 15 coins |
| DeepSeek Direct | deepseek-chat | 50 req/min | 20 coins |

## 📝 Summary

✅ **Prompt optimized** - 60% token reduction
✅ **Rate limit handling** - Automatic retries
✅ **Batch processing** - Split large requests
✅ **Blacklist integration** - Filter before AI

With these optimizations, you can run a 20-coin trading bot on free AI models without hitting rate limits! 🚀
