<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\CompanyFromIP;

use Piwik\Common;
use Piwik\DataTable;
use Piwik\Db;
use Piwik\Period;
use Piwik\Piwik;
use Piwik\Plugin\API as PluginAPI;

class API extends PluginAPI
{
    public function getCompanyVisits(
        int $idSite,
        string $period,
        string $date,
        ?string $segment = null
    ): DataTable {
        Piwik::checkUserHasViewAccess($idSite);

        $periodObj = Period\Factory::build($period, $date);

        $startDate = $periodObj->getDateStart()->toString('Y-m-d') . ' 00:00:00';
        $endDate   = $periodObj->getDateEnd()->addDay(1)->toString('Y-m-d') . ' 00:00:00';

        $logVisitTable = Common::prefixTable('log_visit');

        $sql = "
            SELECT
                lv.company_name AS company_name,
                lv.company_type AS company_type,
                lv.company_confidence AS company_confidence,

                MAX(
                    CASE
                        WHEN ta.id IS NOT NULL THEN 1
                        ELSE 0
                    END
                ) AS is_target_account,

                GROUP_CONCAT(
                    DISTINCT ta.account_name
                    ORDER BY ta.account_name
                    SEPARATOR ', '
                ) AS matched_target_account,

                COUNT(*) AS nb_visits,
                COUNT(DISTINCT lv.idvisitor) AS nb_uniq_visitors,
                SUM(lv.visit_total_actions) AS nb_actions

            FROM $logVisitTable lv

            LEFT JOIN target_accounts ta
                ON LOWER(lv.company_name) LIKE CONCAT('%', LOWER(ta.account_keyword), '%')
               AND ta.account_keyword != ''
               AND ta.account_status = 'active'
               AND CHAR_LENGTH(ta.account_keyword) >= 4

            WHERE lv.idsite = ?
              AND lv.visit_last_action_time >= ?
              AND lv.visit_last_action_time < ?
              AND lv.company_name IS NOT NULL
              AND lv.company_name != ''

            GROUP BY
                lv.company_name,
                lv.company_type,
                lv.company_confidence

            ORDER BY
                is_target_account DESC,
                nb_visits DESC,
                nb_actions DESC
        ";

        $rows = Db::fetchAll($sql, [$idSite, $startDate, $endDate]);

        $dataTable = new DataTable();

        foreach ($rows as $row) {
            $isTarget = (int) $row['is_target_account'] === 1;
            $companyName = (string) $row['company_name'];
            $companyType = (string) ($row['company_type'] ?? '');
            $companyConfidence = (string) ($row['company_confidence'] ?? '');

            if ($isTarget) {
                $companyName = '[TARGET] ' . $companyName . ' - ' . (string) $row['matched_target_account'];
            } elseif ($companyType === 'Corporate Company') {
                $companyName = '[COMPANY] ' . $companyName . ' - High Confidence';
            } elseif ($companyType === 'Cloud / Data Center') {
                $companyName = '[CLOUD] ' . $companyName . ' - Verify Manually';
            } elseif ($companyType === 'Network Provider') {
                $companyName = '[NETWORK] ' . $companyName . ' - Actual Company Unknown';
            } elseif ($companyType === 'Possible Company') {
                $companyName = '[POSSIBLE] ' . $companyName . ' - Medium Confidence';
            }

            $dataTable->addRowFromSimpleArray([
                'label'            => $companyName,
                'nb_visits'        => (int) $row['nb_visits'],
                'nb_uniq_visitors' => (int) $row['nb_uniq_visitors'],
                'nb_actions'       => (int) $row['nb_actions'],
            ]);
        }

        return $dataTable;
    }
}