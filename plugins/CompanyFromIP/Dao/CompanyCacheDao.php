<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\CompanyFromIP\Dao;

use Piwik\Common;
use Piwik\Db;
use Piwik\DbHelper;

class CompanyCacheDao
{
    private string $table = 'company_from_ip_cache';

    private function getTable(): string
    {
        return Common::prefixTable($this->table);
    }

    public function install(): void
    {
        DbHelper::createTable($this->table, "
            `ip_hash`      CHAR(64)     NOT NULL,
            `company_name` VARCHAR(255) NULL,
            `lookup_date`  DATETIME     NOT NULL,
            PRIMARY KEY (`ip_hash`),
            INDEX `idx_lookup_date` (`lookup_date`)
        ");
    }

    public function uninstall(): void
    {
        Db::dropTables([$this->getTable()]);
    }

    /**
     * @return array{company_name: string|null, lookup_date: string}|null
     */
    public function findByHash(string $ipHash): ?array
    {
        $sql = "SELECT company_name, lookup_date FROM " . $this->getTable() . " WHERE ip_hash = ?";
        $row = Db::fetchRow($sql, [$ipHash]);

        return $row ?: null;
    }

    public function upsert(string $ipHash, ?string $companyName): void
    {
        $sql = "INSERT INTO " . $this->getTable() . " (ip_hash, company_name, lookup_date)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    company_name = VALUES(company_name),
                    lookup_date  = VALUES(lookup_date)";

        Db::query($sql, [$ipHash, $companyName]);
    }

    public function deleteExpired(int $ttlDays): void
    {
        $sql = "DELETE FROM " . $this->getTable() . " WHERE lookup_date < DATE_SUB(NOW(), INTERVAL ? DAY)";

        Db::query($sql, [$ttlDays]);
    }
}
