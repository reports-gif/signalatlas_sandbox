<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\CompanyFromIP;

use Piwik\DataTable;
use Piwik\Metrics;

class Archiver extends \Piwik\Plugin\Archiver
{
    const COMPANY_VISITS_RECORD_NAME = 'CompanyFromIP_companyVisits';

    public function aggregateDayReport(): void
    {
        $logAggregator = $this->getLogAggregator();

        $query = $logAggregator->queryVisitsByDimension(
            ['company_name' => 'log_visit.company_name'],
            $where            = false,
            $additionalSelects = [],
            $metrics           = [
                Metrics::INDEX_NB_UNIQ_VISITORS,
                Metrics::INDEX_NB_VISITS,
                Metrics::INDEX_NB_ACTIONS,
            ]
        );

        $dataTable = new DataTable();

        if ($query) {
            while ($row = $query->fetch()) {
                $companyName = $row['company_name'] ?? null;

                if (empty($companyName)) {
                    continue;
                }

                $dataTable->sumRowWithLabel($companyName, [
                    Metrics::INDEX_NB_UNIQ_VISITORS => (int) ($row[Metrics::INDEX_NB_UNIQ_VISITORS] ?? 0),
                    Metrics::INDEX_NB_VISITS        => (int) ($row[Metrics::INDEX_NB_VISITS]        ?? 0),
                    Metrics::INDEX_NB_ACTIONS       => (int) ($row[Metrics::INDEX_NB_ACTIONS]       ?? 0),
                ]);
            }
        }

        $this->getProcessor()->insertBlobRecord(
            self::COMPANY_VISITS_RECORD_NAME,
            $dataTable->getSerialized()
        );
    }

    public function aggregateMultipleReports(): void
    {
        $this->getProcessor()->aggregateDataTableRecords([self::COMPANY_VISITS_RECORD_NAME]);
    }
}
