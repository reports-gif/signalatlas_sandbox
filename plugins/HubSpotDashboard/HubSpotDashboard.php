<?php

namespace Piwik\Plugins\HubSpotDashboard;

use Piwik\Common;
use Piwik\Db;
use Piwik\Plugin;

class HubSpotDashboard extends Plugin
{
    public function install()
    {
        $this->createCacheTable();
    }

    public function uninstall()
    {
        // Keep historical cache by default. Uncomment only if you intentionally want cleanup.
        // Db::exec('DROP TABLE IF EXISTS `' . Common::prefixTable('hubspot_dashboard_cache') . '`');
    }

    public function activate()
    {
        $this->createCacheTable();
    }

    private function createCacheTable(): void
    {
        $table = Common::prefixTable('hubspot_dashboard_cache');

        $sql = "CREATE TABLE IF NOT EXISTS `$table` (
            `idsite` INT UNSIGNED NOT NULL DEFAULT 0,
            `cache_key` VARCHAR(120) NOT NULL,
            `payload` MEDIUMTEXT NOT NULL,
            `created_at` DATETIME NOT NULL,
            `expires_at` DATETIME NOT NULL,
            PRIMARY KEY (`idsite`, `cache_key`),
            KEY `idx_expires_at` (`expires_at`)
        ) DEFAULT CHARSET=utf8mb4";

        Db::exec($sql);
    }
}
