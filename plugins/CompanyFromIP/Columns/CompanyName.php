<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\CompanyFromIP\Columns;

use Piwik\Columns\DimensionSegmentFactory;
use Piwik\Container\StaticContainer;
use Piwik\Db;
use Piwik\Network\IP;
use Piwik\Plugin\Dimension\VisitDimension;
use Piwik\Plugins\CompanyFromIP\CompanyResolver;
use Piwik\Plugins\CompanyFromIP\SystemSettings;
use Piwik\Segment\SegmentsList;
use Piwik\Tracker\Request;
use Piwik\Tracker\Visitor;

class CompanyName extends VisitDimension
{
    protected $columnName = 'company_name';
    protected $columnType = 'VARCHAR(255) NULL DEFAULT NULL';
    protected $nameSingular = 'CompanyFromIP_CompanyName';
    protected $segmentName = 'companyName';
    protected $type = self::TYPE_TEXT;

    public function onNewVisit(Request $request, Visitor $visitor, $action): mixed
    {
        $this->debug('onNewVisit triggered');

        return $this->resolveAndReturnCompany($request);
    }

    public function onExistingVisit(Request $request, Visitor $visitor, $action): mixed
    {
        $this->debug('onExistingVisit triggered');

        return $this->resolveAndReturnCompany($request);
    }

    private function resolveAndReturnCompany(Request $request): mixed
    {
        try {
            $settings = StaticContainer::get(SystemSettings::class);

            if (!$settings->enableLookup->getValue()) {
                $this->debug('lookup disabled');
                return false;
            }

            $ip = $this->getReadableIp($request);

            if (empty($ip)) {
                $this->debug('empty IP after conversion');
                return false;
            }

            $this->debug('visitor IP converted: ' . $ip);

            $resolver = StaticContainer::get(CompanyResolver::class);
            $companyName = $resolver->resolveCompany($ip);

            $this->debug('resolved company: ' . ($companyName ?: 'NULL'));

            if (!empty($companyName)) {
                $companyName = $this->formatCompanyLabel($companyName);
                $this->debug('formatted company: ' . $companyName);
            }

            return $companyName ?: false;
        } catch (\Throwable $e) {
            $this->debug('error: ' . $e->getMessage());
            return false;
        }
    }

    private function getReadableIp(Request $request): ?string
    {
        $ipRaw = $request->getIp();

        if (empty($ipRaw)) {
            return null;
        }

        try {
            return IP::fromBinaryIP($ipRaw)->toString();
        } catch (\Throwable $e) {
            $this->debug('IP::fromBinaryIP failed: ' . $e->getMessage());
        }

        return (string) $ipRaw;
    }

    private function formatCompanyLabel(string $companyName): string
    {
        if (
            stripos($companyName, '[TARGET]') === 0 ||
            stripos($companyName, '[COMPANY]') === 0 ||
            stripos($companyName, '[NETWORK]') === 0 ||
            stripos($companyName, '[CLOUD]') === 0 ||
            stripos($companyName, '[POSSIBLE]') === 0
        ) {
            return $companyName;
        }

        $matchedTarget = $this->getMatchedTargetAccount($companyName);

        if (!empty($matchedTarget)) {
            return '[TARGET] ' . $companyName . ' - ' . $matchedTarget;
        }

        if ($this->isNetworkProvider($companyName)) {
            return '[NETWORK] ' . $companyName . ' - Actual Company Unknown';
        }

        if ($this->isCloudProvider($companyName)) {
            return '[CLOUD] ' . $companyName . ' - Verify Manually';
        }

        if ($this->isCorporateCompany($companyName)) {
            return '[COMPANY] ' . $companyName . ' - High Confidence';
        }

        return '[POSSIBLE] ' . $companyName . ' - Medium Confidence';
    }

    private function getMatchedTargetAccount(string $companyName): ?string
    {
        try {
            $sql = "
                SELECT account_name
                FROM target_accounts
                WHERE account_status = 'active'
                  AND account_keyword != ''
                  AND CHAR_LENGTH(account_keyword) >= 4
                  AND LOWER(?) LIKE CONCAT('%', LOWER(account_keyword), '%')
                ORDER BY CHAR_LENGTH(account_keyword) DESC
                LIMIT 1
            ";

            $matchedAccount = Db::fetchOne($sql, [$companyName]);

            return !empty($matchedAccount) ? (string) $matchedAccount : null;
        } catch (\Throwable $e) {
            $this->debug('target match error: ' . $e->getMessage());
            return null;
        }
    }

    private function isNetworkProvider(string $companyName): bool
    {
        return (bool) preg_match(
            '/Comcast|Cable|Telecom|Teleservices|Communications|Broadband|Internet|Wireless|Mobile|Jio|Airtel|Vodafone|Verizon|AT&T|British Telecommunications|CenturyLink|Lumen|GTT|Reliance|Tata Teleservices|Banglalink|Orange|Telefonica|Deutsche Telekom|NTT|SK Telecom|China Telecom|China Mobile/i',
            $companyName
        );
    }

    private function isCloudProvider(string $companyName): bool
    {
        return (bool) preg_match(
            '/Google Cloud|Amazon Web Services|AWS|Microsoft Azure|Cloudflare|DigitalOcean|OVH|Hetzner|Fastly|Linode|Akamai Cloud|Oracle Cloud|Alibaba Cloud/i',
            $companyName
        );
    }

    private function isCorporateCompany(string $companyName): bool
    {
        return (bool) preg_match(
            '/Google LLC|Google Inc|Microsoft|Cisco|Akamai|Accenture|Capgemini|IBM|Oracle|SAP|Adobe|Salesforce|ServiceNow|Palo Alto|Fortinet|CrowdStrike|Zscaler|Infosys|Wipro|TCS|Tata Consultancy|Cognizant|HCL|Deloitte|EY|KPMG|PwC|Apple|Meta|LinkedIn|Intel|Nvidia|Dell|HP|Broadcom|VMware|Snowflake|Databricks|MongoDB|Okta|Splunk|Atlassian|Workday|Zoom/i',
            $companyName
        );
    }

    private function debug(string $message): void
    {
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;

        @file_put_contents(
            PIWIK_INCLUDE_PATH . '/tmp/companyfromip-debug.log',
            $line,
            FILE_APPEND
        );
    }

    public function configureSegments(SegmentsList $segmentsList, DimensionSegmentFactory $dimensionSegmentFactory): void
    {
        $segment = $dimensionSegmentFactory->createSegment();
        $segment->setName('CompanyFromIP_SegmentName');
        $segmentsList->addSegment($segment);
    }
}