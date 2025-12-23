# Database SQL Scripts

## Strategy Improvements (December 2025)

This directory contains SQL scripts for applying strategy improvements to the database.

---

## 📄 Available Scripts

### `strategy_improvements_2025.sql`
Applies all strategy improvements to `bot_settings` table:
- RSI range tightening (50-70 LONG, 30-55 SHORT)
- Regional volume thresholds (US/Asia/Europe/Off-peak)
- Dynamic TP/SL based on ATR
- Trailing stops optimization (L3: 8% → 10%)
- Pre-sleep position closing (21:00 UTC)
- AI scoring fix (volume separate)
- Strategy version tracking

---

## 🚀 How to Apply

### Method 1: Laravel Migration (Recommended)

```bash
# Run the migration
php artisan migrate

# Verify
php artisan tinker
```

```php
// Check strategy version
App\Models\BotSetting::get('strategy_version');
// Should return: "2.0.0"

// Check RSI settings
App\Models\BotSetting::whereIn('key', ['rsi_long_min', 'rsi_long_max'])->get();

// Get all strategy settings
App\Models\BotSetting::where('key', 'like', 'rsi_%')->get();
App\Models\BotSetting::where('key', 'like', 'volume_threshold%')->get();
App\Models\BotSetting::where('key', 'like', 'dynamic_%')->get();
```

### Method 2: Direct SQL

```bash
# MySQL CLI
mysql -u your_user -p your_database < database/sql/strategy_improvements_2025.sql

# Or import via phpMyAdmin / Adminer
```

### Method 3: Artisan Tinker (Quick Test)

```bash
php artisan tinker
```

```php
use Illuminate\Support\Facades\DB;

// Apply all settings
$settings = [
    ['key' => 'rsi_long_min', 'value' => '50'],
    ['key' => 'rsi_long_max', 'value' => '70'],
    ['key' => 'rsi_short_min', 'value' => '30'],
    ['key' => 'rsi_short_max', 'value' => '55'],
    ['key' => 'volume_threshold_us', 'value' => '0.9'],
    ['key' => 'volume_threshold_asia', 'value' => '0.8'],
    ['key' => 'volume_threshold_europe', 'value' => '0.95'],
    ['key' => 'dynamic_tp_enabled', 'value' => 'true'],
    ['key' => 'dynamic_tp_min_percent', 'value' => '7.5'],
    ['key' => 'trailing_stop_l3_trigger', 'value' => '10'],
    ['key' => 'pre_sleep_close_enabled', 'value' => 'true'],
    ['key' => 'pre_sleep_close_hour_utc', 'value' => '21'],
    ['key' => 'strategy_version', 'value' => '2.0.0'],
];

foreach ($settings as $s) {
    DB::table('bot_settings')->updateOrInsert(['key' => $s['key']], ['value' => $s['value'], 'updated_at' => now()]);
}

echo "✅ Applied " . count($settings) . " settings\n";
```

---

## ✅ Verification

### Check Applied Settings

```bash
php artisan tinker
```

```php
// Method 1: Check specific keys
$keys = ['rsi_long_min', 'rsi_long_max', 'strategy_version'];
App\Models\BotSetting::whereIn('key', $keys)->get(['key', 'value', 'updated_at']);

// Method 2: Count strategy settings
DB::table('bot_settings')
    ->where('key', 'regexp', '(rsi_|volume_threshold|dynamic_|trailing_stop|sleep|ai_score|strategy_version)')
    ->count();
// Should return: 29 settings

// Method 3: Group by category
DB::select("
    SELECT
        CASE
            WHEN `key` LIKE 'rsi_%' THEN '1. RSI'
            WHEN `key` LIKE 'volume_%' THEN '2. Volume'
            WHEN `key` LIKE 'dynamic_%' THEN '3. TP/SL'
            WHEN `key` LIKE 'trailing_%' THEN '4. Trailing'
            WHEN `key` LIKE '%sleep%' THEN '5. Sleep'
            WHEN `key` LIKE 'ai_%' THEN '6. AI'
            ELSE '7. Other'
        END as category,
        COUNT(*) as count
    FROM bot_settings
    WHERE `key` REGEXP '(rsi_|volume_threshold|dynamic_|trailing_stop|sleep|ai_score|strategy_version)'
    GROUP BY category
    ORDER BY category
");
```

Expected output:
```
┌──────────────┬───────┐
│ category     │ count │
├──────────────┼───────┤
│ 1. RSI       │ 4     │
│ 2. Volume    │ 4     │
│ 3. TP/SL     │ 5     │
│ 4. Trailing  │ 6     │
│ 5. Sleep     │ 4     │
│ 6. AI        │ 3     │
│ 7. Other     │ 2     │
└──────────────┴───────┘
```

---

## 🔄 Rollback (if needed)

### Method 1: Laravel Migration

```bash
# Rollback last migration
php artisan migrate:rollback --step=1

# Rollback specific migration
php artisan migrate:rollback --path=database/migrations/2025_12_23_133253_add_strategy_improvements_to_bot_settings.php
```

### Method 2: Direct SQL

Uncomment the rollback section in `strategy_improvements_2025.sql` and run:

```sql
DELETE FROM `bot_settings`
WHERE `key` IN (
    'rsi_long_min', 'rsi_long_max', 'rsi_short_min', 'rsi_short_max',
    'volume_threshold_us', 'volume_threshold_asia', 'volume_threshold_europe', 'volume_threshold_offpeak',
    'dynamic_tp_enabled', 'dynamic_tp_min_percent', 'dynamic_tp_atr_multiplier',
    'dynamic_sl_enabled', 'dynamic_sl_atr_multiplier',
    'trailing_stop_l2_trigger', 'trailing_stop_l2_target',
    'trailing_stop_l3_trigger', 'trailing_stop_l3_target',
    'trailing_stop_l4_trigger', 'trailing_stop_l4_target',
    'pre_sleep_close_enabled', 'pre_sleep_close_hour_utc',
    'sleep_mode_start_hour', 'sleep_mode_end_hour',
    'ai_score_required', 'ai_score_max', 'ai_volume_separate_check',
    'strategy_version', 'strategy_updated_at'
);
```

---

## 📊 Performance Testing

After applying, monitor these metrics:

```php
// Win rate comparison
$oldStrategy = App\Models\Position::where('created_at', '<', '2025-12-23')->where('is_open', false);
$newStrategy = App\Models\Position::where('created_at', '>=', '2025-12-23')->where('is_open', false);

echo "Old Win Rate: " . ($oldStrategy->where('realized_pnl', '>', 0)->count() / $oldStrategy->count() * 100) . "%\n";
echo "New Win Rate: " . ($newStrategy->where('realized_pnl', '>', 0)->count() / $newStrategy->count() * 100) . "%\n";

// Average profit
echo "Old Avg Profit: $" . $oldStrategy->avg('realized_pnl') . "\n";
echo "New Avg Profit: $" . $newStrategy->avg('realized_pnl') . "\n";

// Pre-sleep closes
$preSleepCloses = App\Models\Position::where('close_reason', 'pre_sleep_close')->count();
echo "Pre-sleep closes: {$preSleepCloses}\n";
```

---

## ⚠️ Important Notes

- **Safe to re-run**: Uses `ON DUPLICATE KEY UPDATE`
- **No data loss**: Only updates `bot_settings`, doesn't touch `positions` or `trades`
- **Backward compatible**: Existing positions use original TP/SL until closed
- **Recommended time**: Apply during low-activity hours (00:00-06:00 UTC)

---

## 🆘 Troubleshooting

### Error: "Duplicate entry for key 'PRIMARY'"
**Cause:** Key already exists
**Solution:** This is normal, the `ON DUPLICATE KEY UPDATE` will handle it

### Error: "Table 'bot_settings' doesn't exist"
**Cause:** Migration hasn't run
**Solution:** Run `php artisan migrate` first

### Settings not taking effect
**Cause:** Code needs to read from `bot_settings`
**Solution:** Restart trading command:
```bash
# Stop trading
pkill -f "trading:multi-coin"

# Start again
php artisan trading:multi-coin
```

---

**Last Updated:** December 23, 2025
**Strategy Version:** 2.0.0
**Status:** Production Ready ✅
