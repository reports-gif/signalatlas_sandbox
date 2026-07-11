<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\CompanyFromIP;

use Piwik\Updater;
use Piwik\Updates as PiwikUpdates;
use Piwik\Updater\Migration\Db as DbMigration;
use Piwik\Updater\Migration\Factory as MigrationFactory;

class Updates_1_0_0 extends PiwikUpdates
{
    /** @var MigrationFactory */
    private $migration;

    public function __construct(MigrationFactory $factory)
    {
        $this->migration = $factory;
    }

    public function getMigrations(Updater $updater): array
    {
        return [
            $this->migration->db->createTable(
                'company_from_ip_cache',
                [
                    'ip_hash'      => 'CHAR(64) NOT NULL',
                    'company_name' => 'VARCHAR(255) NULL',
                    'lookup_date'  => 'DATETIME NOT NULL',
                ],
                ['ip_hash']
            ),
            $this->migration->db->addIndex(
                'company_from_ip_cache',
                ['lookup_date'],
                'idx_lookup_date'
            ),
        ];
    }

    public function doUpdate(Updater $updater): void
    {
        $updater->executeMigrations(__FILE__, $this->getMigrations($updater));
    }
}
