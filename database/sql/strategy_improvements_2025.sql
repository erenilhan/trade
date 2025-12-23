-- ============================================
-- STRATEGY IMPROVEMENTS SQL (December 2025)
-- ============================================
-- Run this to apply all strategy improvements to bot_settings table
-- Safe to re-run (uses ON DUPLICATE KEY UPDATE)

USE `trade`; -- Change to your database name

-- ============================================
-- 1. RSI Ranges (Tightened for Safety)
-- ============================================
-- LONG: 45-72 → 50-70
-- SHORT: 28-55 → 30-55

INSERT INTO `bot_settings` (`key`, `value`, `created_at`, `updated_at`)
VALUES
('rsi_long_min', '50', NOW(), NOW()),
('rsi_long_max', '70', NOW(), NOW()),
('rsi_short_min', '30', NOW(), NOW()),
('rsi_short_max', '55', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `value` = VALUES(`value`),
    `updated_at` = NOW();

-- ============================================
-- 2. Regional Volume Thresholds
-- ============================================
-- US: 0.9x, Asia: 0.8x, Europe: 0.95x, Off-peak: 1.0x

INSERT INTO `bot_settings` (`key`, `value`, `created_at`, `updated_at`)
VALUES
('volume_threshold_us', '0.9', NOW(), NOW()),
('volume_threshold_asia', '0.8', NOW(), NOW()),
('volume_threshold_europe', '0.95', NOW(), NOW()),
('volume_threshold_offpeak', '1.0', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `value` = VALUES(`value`),
    `updated_at` = NOW();

-- ============================================
-- 3. Dynamic TP/SL (ATR-based)
-- ============================================
-- TP: max(7.5%, ATR14 * 1.5)
-- SL: min(ATR14 * 0.75, maxPnlLoss / leverage)

INSERT INTO `bot_settings` (`key`, `value`, `created_at`, `updated_at`)
VALUES
('dynamic_tp_enabled', 'true', NOW(), NOW()),
('dynamic_tp_min_percent', '7.5', NOW(), NOW()),
('dynamic_tp_atr_multiplier', '1.5', NOW(), NOW()),
('dynamic_sl_enabled', 'true', NOW(), NOW()),
('dynamic_sl_atr_multiplier', '0.75', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `value` = VALUES(`value`),
    `updated_at` = NOW();

-- ============================================
-- 4. Trailing Stops (L3 Optimized)
-- ============================================
-- L2: 8% → 2%
-- L3: 10% → 5% (was 8%, collision fixed)
-- L4: 12% → 8%

INSERT INTO `bot_settings` (`key`, `value`, `created_at`, `updated_at`)
VALUES
('trailing_stop_l2_trigger', '8', NOW(), NOW()),
('trailing_stop_l2_target', '2', NOW(), NOW()),
('trailing_stop_l3_trigger', '10', NOW(), NOW()),
('trailing_stop_l3_target', '5', NOW(), NOW()),
('trailing_stop_l4_trigger', '12', NOW(), NOW()),
('trailing_stop_l4_target', '8', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `value` = VALUES(`value`),
    `updated_at` = NOW();

-- ============================================
-- 5. Pre-Sleep Position Closing
-- ============================================
-- Close profitable positions at 21:00 UTC (2h before sleep)
-- Sleep mode: 23:00-04:00 UTC

INSERT INTO `bot_settings` (`key`, `value`, `created_at`, `updated_at`)
VALUES
('pre_sleep_close_enabled', 'true', NOW(), NOW()),
('pre_sleep_close_hour_utc', '21', NOW(), NOW()),
('sleep_mode_start_hour', '23', NOW(), NOW()),
('sleep_mode_end_hour', '4', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `value` = VALUES(`value`),
    `updated_at` = NOW();

-- ============================================
-- 6. AI Scoring (Volume Separate from Score)
-- ============================================
-- Required: 3/4 criteria (volume checked separately)

INSERT INTO `bot_settings` (`key`, `value`, `created_at`, `updated_at`)
VALUES
('ai_score_required', '3', NOW(), NOW()),
('ai_score_max', '4', NOW(), NOW()),
('ai_volume_separate_check', 'true', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `value` = VALUES(`value`),
    `updated_at` = NOW();

-- ============================================
-- 7. Strategy Version Tracking
-- ============================================

INSERT INTO `bot_settings` (`key`, `value`, `created_at`, `updated_at`)
VALUES
('strategy_version', '2.0.0', NOW(), NOW()),
('strategy_updated_at', NOW(), NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `value` = VALUES(`value`),
    `updated_at` = NOW();

-- ============================================
-- VERIFICATION QUERIES
-- ============================================

-- Check all new settings
SELECT '=== RSI Settings ===' as section;
SELECT `key`, `value`, `updated_at`
FROM `bot_settings`
WHERE `key` IN ('rsi_long_min', 'rsi_long_max', 'rsi_short_min', 'rsi_short_max')
ORDER BY `key`;

SELECT '=== Volume Thresholds ===' as section;
SELECT `key`, `value`, `updated_at`
FROM `bot_settings`
WHERE `key` LIKE 'volume_threshold%'
ORDER BY `key`;

SELECT '=== Dynamic TP/SL ===' as section;
SELECT `key`, `value`, `updated_at`
FROM `bot_settings`
WHERE `key` LIKE 'dynamic_%'
ORDER BY `key`;

SELECT '=== Trailing Stops ===' as section;
SELECT `key`, `value`, `updated_at`
FROM `bot_settings`
WHERE `key` LIKE 'trailing_stop%'
ORDER BY `key`;

SELECT '=== Sleep Mode ===' as section;
SELECT `key`, `value`, `updated_at`
FROM `bot_settings`
WHERE `key` LIKE '%sleep%'
ORDER BY `key`;

SELECT '=== AI Scoring ===' as section;
SELECT `key`, `value`, `updated_at`
FROM `bot_settings`
WHERE `key` LIKE 'ai_score%'
ORDER BY `key`;

SELECT '=== Strategy Version ===' as section;
SELECT `key`, `value`, `updated_at`
FROM `bot_settings`
WHERE `key` IN ('strategy_version', 'strategy_updated_at')
ORDER BY `key`;

-- Summary: Count of all strategy settings
SELECT
    'Total Strategy Settings' as metric,
    COUNT(*) as count
FROM `bot_settings`
WHERE `key` REGEXP '(rsi_|volume_threshold|dynamic_|trailing_stop|sleep|ai_score|strategy_version)';

-- ============================================
-- ROLLBACK (if needed)
-- ============================================
-- Uncomment to remove all strategy improvements:

/*
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
*/
