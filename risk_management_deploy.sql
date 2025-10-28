-- ========================================
-- 🛡️ RISK MANAGEMENT SYSTEM - LIVE DEPLOYMENT
-- ========================================
-- Generated: $(date)
-- Description: Sleep Mode, Daily Max Drawdown, Cluster Loss Cooldown
-- ========================================

-- 1. Create daily_stats table
CREATE TABLE IF NOT EXISTS `daily_stats` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL COMMENT 'Trading date (UTC)',
  `starting_balance` decimal(20,8) NOT NULL DEFAULT 0.00000000 COMMENT 'Balance at start of day',
  `current_balance` decimal(20,8) NOT NULL DEFAULT 0.00000000 COMMENT 'Current balance',
  `daily_pnl` decimal(20,8) NOT NULL DEFAULT 0.00000000 COMMENT 'Daily profit/loss (USDT)',
  `daily_pnl_percent` decimal(10,4) NOT NULL DEFAULT 0.0000 COMMENT 'Daily P&L percentage',
  `max_drawdown_hit` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether max drawdown limit was hit',
  `cooldown_until` timestamp NULL DEFAULT NULL COMMENT 'Cooldown end time if max drawdown hit',
  `trades_count` int(11) NOT NULL DEFAULT 0 COMMENT 'Number of trades today',
  `wins_count` int(11) NOT NULL DEFAULT 0 COMMENT 'Number of winning trades',
  `losses_count` int(11) NOT NULL DEFAULT 0 COMMENT 'Number of losing trades',
  `metadata` json DEFAULT NULL COMMENT 'Additional daily stats',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `daily_stats_date_unique` (`date`),
  KEY `daily_stats_date_index` (`date`),
  KEY `daily_stats_max_drawdown_hit_index` (`max_drawdown_hit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Insert Risk Management Settings
-- Sleep Mode Settings
INSERT INTO `bot_settings` (`key`, `value`, `type`, `description`, `created_at`, `updated_at`) 
VALUES 
  ('sleep_mode_enabled', '1', 'bool', 'Enable sleep mode during low liquidity hours (23:00-04:00 UTC)', NOW(), NOW())
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW();

INSERT INTO `bot_settings` (`key`, `value`, `type`, `description`, `created_at`, `updated_at`) 
VALUES 
  ('sleep_mode_start_hour', '23', 'int', 'Sleep mode start hour (UTC, 0-23)', NOW(), NOW())
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW();

INSERT INTO `bot_settings` (`key`, `value`, `type`, `description`, `created_at`, `updated_at`) 
VALUES 
  ('sleep_mode_end_hour', '4', 'int', 'Sleep mode end hour (UTC, 0-23)', NOW(), NOW())
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW();

INSERT INTO `bot_settings` (`key`, `value`, `type`, `description`, `created_at`, `updated_at`) 
VALUES 
  ('sleep_mode_max_positions', '3', 'int', 'Maximum positions allowed during sleep mode', NOW(), NOW())
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW();

INSERT INTO `bot_settings` (`key`, `value`, `type`, `description`, `created_at`, `updated_at`) 
VALUES 
  ('sleep_mode_tighter_stops', '1', 'bool', 'Tighten stop losses during sleep mode', NOW(), NOW())
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW();

INSERT INTO `bot_settings` (`key`, `value`, `type`, `description`, `created_at`, `updated_at`) 
VALUES 
  ('sleep_mode_stop_multiplier', '0.75', 'float', 'Stop loss multiplier during sleep mode (0.75 = 25% tighter)', NOW(), NOW())
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW();

-- Daily Max Drawdown Settings
INSERT INTO `bot_settings` (`key`, `value`, `type`, `description`, `created_at`, `updated_at`) 
VALUES 
  ('daily_max_drawdown_enabled', '1', 'bool', 'Enable daily max drawdown protection', NOW(), NOW())
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW();

INSERT INTO `bot_settings` (`key`, `value`, `type`, `description`, `created_at`, `updated_at`) 
VALUES 
  ('daily_max_drawdown_percent', '8', 'float', 'Stop trading if daily loss exceeds this % (default: 8%)', NOW(), NOW())
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW();

INSERT INTO `bot_settings` (`key`, `value`, `type`, `description`, `created_at`, `updated_at`) 
VALUES 
  ('daily_max_drawdown_cooldown_hours', '24', 'int', 'Hours to pause trading after max drawdown hit', NOW(), NOW())
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW();

-- Cluster Loss Cooldown Settings
INSERT INTO `bot_settings` (`key`, `value`, `type`, `description`, `created_at`, `updated_at`) 
VALUES 
  ('cluster_loss_cooldown_enabled', '1', 'bool', 'Enable cooldown after consecutive losses', NOW(), NOW())
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW();

INSERT INTO `bot_settings` (`key`, `value`, `type`, `description`, `created_at`, `updated_at`) 
VALUES 
  ('cluster_loss_consecutive_trigger', '3', 'int', 'Number of consecutive losses to trigger cooldown', NOW(), NOW())
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW();

INSERT INTO `bot_settings` (`key`, `value`, `type`, `description`, `created_at`, `updated_at`) 
VALUES 
  ('cluster_loss_cooldown_hours', '24', 'int', 'Hours to pause trading after cluster losses', NOW(), NOW())
  ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW();

-- ========================================
-- 🎉 DEPLOYMENT COMPLETE!
-- ========================================
-- Next steps:
-- 1. Run this SQL on your live database
-- 2. Clear cache: php artisan cache:clear
-- 3. Restart queue workers
-- 4. Check admin panel: /admin/manage-bot-settings
-- ========================================
