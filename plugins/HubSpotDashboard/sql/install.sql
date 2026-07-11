-- Optional manual SQL. Normally the plugin creates this table automatically on activate/install.
-- Replace matomo_ with your Matomo DB table prefix if different.
CREATE TABLE IF NOT EXISTS `matomo_hubspot_dashboard_cache` (
  `idsite` INT UNSIGNED NOT NULL DEFAULT 0,
  `cache_key` VARCHAR(120) NOT NULL,
  `payload` MEDIUMTEXT NOT NULL,
  `created_at` DATETIME NOT NULL,
  `expires_at` DATETIME NOT NULL,
  PRIMARY KEY (`idsite`, `cache_key`),
  KEY `idx_expires_at` (`expires_at`)
) DEFAULT CHARSET=utf8mb4;
