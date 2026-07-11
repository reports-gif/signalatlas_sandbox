<?php

namespace Piwik\Plugins\HubSpotDashboard;

use Piwik\Common;
use Piwik\Db;

class CacheStore
{
    private string $table;

    public function __construct()
    {
        $this->table = Common::prefixTable('hubspot_dashboard_cache');
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        try {
            Db::exec("CREATE TABLE IF NOT EXISTS `{$this->table}` (
                `idsite` INT UNSIGNED NOT NULL DEFAULT 0,
                `cache_key` VARCHAR(120) NOT NULL,
                `payload` MEDIUMTEXT NOT NULL,
                `created_at` DATETIME NOT NULL,
                `expires_at` DATETIME NOT NULL,
                PRIMARY KEY (`idsite`, `cache_key`),
                KEY `idx_expires_at` (`expires_at`)
            ) DEFAULT CHARSET=utf8mb4");
        } catch (\Throwable $e) {
            // Cache is an optimization only. Dashboard can still work without it.
        }
    }

    public function get(int $idSite, string $key): ?array
    {
        try {
            $row = Db::fetchRow(
                "SELECT payload FROM `{$this->table}` WHERE idsite = ? AND cache_key = ? AND expires_at > UTC_TIMESTAMP() LIMIT 1",
                [$idSite, $key]
            );

            if (empty($row['payload'])) {
                return null;
            }

            $payload = json_decode($row['payload'], true);
            return is_array($payload) ? $payload : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function set(int $idSite, string $key, array $payload, int $ttlSeconds): void
    {
        try {
            $ttlSeconds = max(60, min($ttlSeconds, 86400));
            $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            Db::query(
                "REPLACE INTO `{$this->table}` (idsite, cache_key, payload, created_at, expires_at)
                 VALUES (?, ?, ?, UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND))",
                [$idSite, $key, $json, $ttlSeconds]
            );
        } catch (\Throwable $e) {
            // Ignore cache failures.
        }
    }

    public function clearExpired(): void
    {
        try {
            Db::query("DELETE FROM `{$this->table}` WHERE expires_at <= UTC_TIMESTAMP()");
        } catch (\Throwable $e) {
            // Ignore cache cleanup failures.
        }
    }
}
