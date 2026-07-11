<?php

declare(strict_types=1);

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\Forecast\Repositories;

use Piwik\Common;
use Piwik\Db;

class ForecastRepository
{
    /**
     * Inserts or updates the forecast data for a given site.
     *
     * @param string $resultForDatabase JSON-encoded forecast data to persist.
     * @param int    $siteId            Matomo site ID.
     * @return void
     * @throws \Exception
     */
    public function persist(string $resultForDatabase, int $siteId): void
    {
        Db::query(
            "INSERT INTO " . Common::prefixTable('forecast_access_count') . " (access_siteid, access_data)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE
            access_data = ?",
            [
                $siteId,
                $resultForDatabase,
                $resultForDatabase,
            ]
        );
    }

    /**
     * Retrieves the stored forecast data for a given site.
     *
     * @param int $siteId Matomo site ID.
     * @return string JSON-encoded forecast data, or an empty string when no data exists.
     * @throws \Exception
     */
    public function fetchBySiteId(int $siteId): string
    {
        $tableAccessCount = Common::prefixTable('forecast_access_count');

        $sql = "
            SELECT access_data
            FROM {$tableAccessCount}
            WHERE access_siteid = ?
            LIMIT 1
        ";

        return (string)Db::fetchOne($sql, [$siteId]);
    }
}
