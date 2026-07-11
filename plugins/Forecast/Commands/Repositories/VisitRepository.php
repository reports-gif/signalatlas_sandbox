<?php

declare(strict_types=1);

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\Forecast\Commands\Repositories;

use Piwik\Common;
use Piwik\Db;

class VisitRepository
{
    /**
     * Get dates from log_visits table.
     *
     * @param string $startDate
     * @param string $endDate
     * @param int $siteId
     * @return array
     * @throws \Exception
     */
    public function fetchDays(string $startDate, string $endDate, int $siteId = 1): array
    {
        $tableLogVisit = Common::prefixTable('log_visit');

        $startBound = $startDate . ' 00:00:00';
        $endExclusive = (new \DateTime($endDate))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';

        $sql = "
            SELECT 
                DATE(visit_first_action_time) as date,
                COUNT(DISTINCT idvisitor) unique_visits,
                COUNT(*) as total_visits
            FROM {$tableLogVisit}
            WHERE idsite = ?
                AND visit_first_action_time >= ?
                AND visit_first_action_time < ?
            GROUP BY DATE(visit_first_action_time)
            ORDER BY date DESC
        ";

        return Db::fetchAssoc($sql, [$siteId, $startBound, $endExclusive]);
    }
}