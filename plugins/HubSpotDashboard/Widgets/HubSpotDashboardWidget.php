<?php

namespace Piwik\Plugins\HubSpotDashboard\Widgets;

use Piwik\Common;
use Piwik\Config;
use Piwik\Plugins\HubSpotDashboard\CacheStore;
use Piwik\Plugins\HubSpotDashboard\CompanyMatcher;
use Piwik\Plugins\HubSpotDashboard\HubSpotClient;
use Piwik\Plugins\HubSpotDashboard\MatomoClient;
use Piwik\Widget\Widget;
use Piwik\Widget\WidgetConfig;

class HubSpotDashboardWidget extends Widget
{
    public static function configure(WidgetConfig $config)
    {
        $config->setCategoryId('Dashboard_Dashboard');
        $config->setName('SignalAtlas Revenue Intelligence');
        $config->setOrder(10);
    }

    public function render()
    {
        $idSite = Common::getRequestVar('idSite', 1, 'int');
        $forceRefresh = Common::getRequestVar('hs_refresh', 0, 'int') === 1;

        $config = Config::getInstance()->HubSpotDashboard;
        $ttl = (int)($config['cache_ttl_seconds'] ?? 900);
        $visitLimit = (int)($config['matomo_visit_limit'] ?? 100);

        $cache = new CacheStore();
        $cache->clearExpired();

        $cacheKey = 'dashboard_v9_advanced_revenue_intelligence';
        $cached = $forceRefresh ? null : $cache->get($idSite, $cacheKey);

        if (is_array($cached)) {
            $cached['fromCache'] = true;
            $cached['refreshUrl'] = $this->getRefreshUrl();
            return $this->renderTemplate('@HubSpotDashboard/dashboard', $cached);
        }

        $client = new HubSpotClient();

        $connection = $client->getConnectionStatus();
        $mqlResponse = $client->searchContactsByLifecycleStage('marketingqualifiedlead');
        $sqlResponse = $client->searchContactsByLifecycleStage('salesqualifiedlead');
        $dealResponse = $client->searchDeals();
        $companyResponse = $client->searchCompanies();

        $mqlContacts = $mqlResponse['results'] ?? [];
        $sqlContacts = $sqlResponse['results'] ?? [];
        $deals = $dealResponse['results'] ?? [];
        $companies = $companyResponse['results'] ?? [];
        $allContacts = array_merge($mqlContacts, $sqlContacts);

        $mqlCount = $mqlResponse['total'] ?? count($mqlContacts);
        $sqlCount = $sqlResponse['total'] ?? count($sqlContacts);
        $opportunityCount = $dealResponse['total'] ?? count($deals);

        $revenue = 0;
        $closedWonDeals = 0;
        foreach ($deals as $deal) {
            $properties = $deal['properties'] ?? [];
            $amount = isset($properties['amount']) ? (float)$properties['amount'] : 0;
            $dealstage = strtolower((string)($properties['dealstage'] ?? ''));
            $isClosedWon = strtolower((string)($properties['hs_is_closed_won'] ?? '')) === 'true';

            if ($isClosedWon || $dealstage === 'closedwon' || $dealstage === 'closed_won') {
                $revenue += $amount;
                $closedWonDeals++;
            }
        }

        $exampleRows = $this->buildEvidenceRows($allContacts);
        $hotAccounts = $this->buildHotAccounts($allContacts);
        $sourceRows = $this->buildSourceRows($allContacts);
        $pageRows = $this->buildTopPageRows($allContacts);
        $dataQuality = $this->buildDataQuality($allContacts, $companies);

        $matomoClient = new MatomoClient();
        $matomoVisitsResponse = $matomoClient->getRecentVisits($idSite, $visitLimit);
        $matomoVisitRows = $matomoClient->normalizeVisits($matomoVisitsResponse['results'] ?? []);

        $matcher = new CompanyMatcher();
        $companyMatches = $matcher->match($matomoVisitRows, $companies);

        $strongMatches = 0;
        $mediumMatches = 0;
        $weakMatches = 0;
        $ispRows = 0;
        $liveHotVisitors = [];
        foreach ($companyMatches as $match) {
            if ($match['confidence'] === 'Strong') {
                $strongMatches++;
            } elseif ($match['confidence'] === 'Medium') {
                $mediumMatches++;
            } elseif ($match['confidence'] === 'Weak') {
                $weakMatches++;
            } elseif ($match['confidence'] === 'ISP / Network') {
                $ispRows++;
            }

            if (count($liveHotVisitors) < 8 && in_array($match['confidence'], ['Strong', 'Medium'], true)) {
                $liveHotVisitors[] = $match;
            }
        }

        $attributionCoverage = count($allContacts) > 0
            ? (int)round((($dataQuality['contactsWithSource'] + $dataQuality['contactsWithUtm'] + $dataQuality['contactsWithMatomo']) / (count($allContacts) * 3)) * 100)
            : 0;

        $highIntentAccountsCount = 0;
        foreach ($hotAccounts as $row) {
            if (($row['score'] ?? 0) >= 70) {
                $highIntentAccountsCount++;
            }
        }

        $nextActions = $this->buildNextActions($hotAccounts, $dataQuality, $sourceRows, $pageRows);

        $viewData = [
            'connection' => $connection,
            'mqlCount' => $mqlCount,
            'sqlCount' => $sqlCount,
            'opportunityCount' => $opportunityCount,
            'closedWonDeals' => $closedWonDeals,
            'revenue' => $revenue,
            'highIntentAccountsCount' => $highIntentAccountsCount,
            'attributionCoverage' => $attributionCoverage,
            'mqlFetched' => $mqlResponse['fetched'] ?? count($mqlContacts),
            'sqlFetched' => $sqlResponse['fetched'] ?? count($sqlContacts),
            'dealsFetched' => $dealResponse['fetched'] ?? count($deals),
            'companiesFetched' => $companyResponse['fetched'] ?? count($companies),
            'mqlTruncated' => $mqlResponse['truncated'] ?? false,
            'sqlTruncated' => $sqlResponse['truncated'] ?? false,
            'dealsTruncated' => $dealResponse['truncated'] ?? false,
            'companiesTruncated' => $companyResponse['truncated'] ?? false,
            'exampleRows' => $exampleRows,
            'hotAccounts' => $hotAccounts,
            'sourceRows' => $sourceRows,
            'topPageRows' => $pageRows,
            'dataQuality' => $dataQuality,
            'nextActions' => $nextActions,
            'hubspotCompanies' => $companies,
            'hubspotCompanyCount' => $companyResponse['total'] ?? count($companies),
            'matomoVisitsError' => $matomoVisitsResponse['error'] ?? false,
            'matomoVisitsErrorMessage' => $matomoVisitsResponse['message'] ?? '',
            'matomoRecentVisitsCount' => $matomoVisitsResponse['total'] ?? count($matomoVisitRows),
            'companyMatches' => $companyMatches,
            'liveHotVisitors' => $liveHotVisitors,
            'strongCompanyMatches' => $strongMatches,
            'mediumCompanyMatches' => $mediumMatches,
            'weakCompanyMatches' => $weakMatches,
            'ispRows' => $ispRows,
            'fromCache' => false,
            'cacheTtlSeconds' => $ttl,
            'generatedAt' => gmdate('Y-m-d H:i:s') . ' UTC',
            'refreshUrl' => $this->getRefreshUrl(),
            'errors' => [
                'mql' => $mqlResponse['error'] ?? false ? ($mqlResponse['message'] ?? 'MQL API error') : '',
                'sql' => $sqlResponse['error'] ?? false ? ($sqlResponse['message'] ?? 'SQL API error') : '',
                'deals' => $dealResponse['error'] ?? false ? ($dealResponse['message'] ?? 'Deals API error') : '',
                'companies' => $companyResponse['error'] ?? false ? ($companyResponse['message'] ?? 'Companies API error') : '',
            ]
        ];

        $cache->set($idSite, $cacheKey, $viewData, $ttl);

        return $this->renderTemplate('@HubSpotDashboard/dashboard', $viewData);
    }

    private function buildEvidenceRows(array $contacts): array
    {
        $rows = [];
        foreach ($contacts as $contact) {
            $p = $contact['properties'] ?? [];
            $email = $p['email'] ?? 'Not captured';
            $source = $this->sourceName($p);
            $rows[] = [
                'email' => $email,
                'journeyUrl' => '?module=HubSpotDashboard&action=journey&email=' . urlencode($email),
                'name' => trim(($p['firstname'] ?? '') . ' ' . ($p['lastname'] ?? '')) ?: 'Not captured',
                'company' => $this->companyName($p),
                'lifecycle' => $p['lifecyclestage'] ?? 'Not captured',
                'source' => $source,
                'matomoVisitorId' => $p['matomo_visitor_id'] ?? 'Not captured',
                'pageUrl' => $p['signalatlas_page_url'] ?? 'Not captured',
                'utmSource' => $p['signalatlas_utm_source'] ?? 'Not captured',
                'utmCampaign' => $p['signalatlas_utm_campaign'] ?? 'Not captured',
                'score' => $this->contactScore($p),
            ];
            if (count($rows) >= 60) {
                break;
            }
        }
        usort($rows, function ($a, $b) { return ($b['score'] ?? 0) <=> ($a['score'] ?? 0); });
        return $rows;
    }

    private function buildHotAccounts(array $contacts): array
    {
        $accounts = [];
        foreach ($contacts as $contact) {
            $p = $contact['properties'] ?? [];
            $company = $this->companyName($p);
            if ($company === 'Not captured') {
                $company = $this->emailDomain($p['email'] ?? '') ?: 'Unmapped Account';
            }
            $key = strtolower($company);
            if (!isset($accounts[$key])) {
                $accounts[$key] = [
                    'company' => $company,
                    'contacts' => 0,
                    'mql' => 0,
                    'sql' => 0,
                    'score' => 0,
                    'dealSignals' => 0,
                    'source' => 'Not captured',
                    'stage' => 'Researching',
                    'journeyUrl' => '',
                    'reason' => []
                ];
            }

            $score = $this->contactScore($p);
            $lifecycle = strtolower((string)($p['lifecyclestage'] ?? ''));
            $dealSignals = (int)($p['num_associated_deals'] ?? 0);
            if (!empty($p['first_deal_created_date'])) {
                $dealSignals++;
            }

            $accounts[$key]['contacts']++;
            $accounts[$key]['score'] += $score;
            $accounts[$key]['dealSignals'] += $dealSignals;
            if ($lifecycle === 'marketingqualifiedlead') {
                $accounts[$key]['mql']++;
            }
            if ($lifecycle === 'salesqualifiedlead') {
                $accounts[$key]['sql']++;
            }
            if ($accounts[$key]['source'] === 'Not captured') {
                $accounts[$key]['source'] = $this->sourceName($p);
            }
            if ($accounts[$key]['journeyUrl'] === '' && !empty($p['email'])) {
                $accounts[$key]['journeyUrl'] = '?module=HubSpotDashboard&action=journey&email=' . urlencode($p['email']);
            }

            if ($this->hasValue($p['signalatlas_page_url'] ?? '')) {
                $accounts[$key]['reason'][] = 'SignalAtlas page evidence';
            }
            if ($this->hasValue($p['hs_analytics_source'] ?? '') || $this->hasValue($p['hs_latest_source'] ?? '')) {
                $accounts[$key]['reason'][] = 'HubSpot source evidence';
            }
            if ($dealSignals > 0) {
                $accounts[$key]['reason'][] = 'Deal signal';
            }
        }

        $rows = [];
        foreach ($accounts as $account) {
            $avgScore = $account['contacts'] > 0 ? (int)round($account['score'] / $account['contacts']) : 0;
            if ($account['dealSignals'] > 0) {
                $stage = 'Opportunity';
            } elseif ($account['sql'] > 0) {
                $stage = 'Sales Ready';
            } elseif ($avgScore >= 70) {
                $stage = 'Evaluating';
            } else {
                $stage = 'Researching';
            }

            $rows[] = [
                'company' => $account['company'],
                'contacts' => $account['contacts'],
                'mql' => $account['mql'],
                'sql' => $account['sql'],
                'score' => min(99, max(0, $avgScore)),
                'stage' => $stage,
                'source' => $account['source'],
                'dealSignals' => $account['dealSignals'],
                'journeyUrl' => $account['journeyUrl'] ?: '#',
                'reason' => implode(', ', array_slice(array_unique($account['reason']), 0, 2)) ?: 'CRM lifecycle evidence'
            ];
        }

        usort($rows, function ($a, $b) { return ($b['score'] ?? 0) <=> ($a['score'] ?? 0); });
        return array_slice($rows, 0, 12);
    }

    private function buildSourceRows(array $contacts): array
    {
        $groups = [];
        foreach ($contacts as $contact) {
            $p = $contact['properties'] ?? [];
            $source = $this->sourceName($p);
            $key = strtolower($source);
            if (!isset($groups[$key])) {
                $groups[$key] = ['source' => $source, 'contacts' => 0, 'mql' => 0, 'sql' => 0, 'dealLinked' => 0, 'scoreTotal' => 0];
            }
            $lifecycle = strtolower((string)($p['lifecyclestage'] ?? ''));
            $groups[$key]['contacts']++;
            $groups[$key]['scoreTotal'] += $this->contactScore($p);
            if ($lifecycle === 'marketingqualifiedlead') {
                $groups[$key]['mql']++;
            }
            if ($lifecycle === 'salesqualifiedlead') {
                $groups[$key]['sql']++;
            }
            if ((int)($p['num_associated_deals'] ?? 0) > 0 || !empty($p['first_deal_created_date'])) {
                $groups[$key]['dealLinked']++;
            }
        }

        $rows = [];
        foreach ($groups as $g) {
            $contactsCount = max(1, $g['contacts']);
            $rows[] = [
                'source' => $g['source'],
                'contacts' => $g['contacts'],
                'mql' => $g['mql'],
                'sql' => $g['sql'],
                'dealLinked' => $g['dealLinked'],
                'avgScore' => (int)round($g['scoreTotal'] / $contactsCount),
                'sqlRate' => (int)round(($g['sql'] / $contactsCount) * 100),
            ];
        }
        usort($rows, function ($a, $b) { return ($b['contacts'] ?? 0) <=> ($a['contacts'] ?? 0); });
        return array_slice($rows, 0, 10);
    }

    private function buildTopPageRows(array $contacts): array
    {
        $groups = [];
        foreach ($contacts as $contact) {
            $p = $contact['properties'] ?? [];
            $page = trim((string)($p['signalatlas_page_url'] ?? ''));
            if ($page === '') {
                $page = trim((string)($p['recent_conversion_event_name'] ?? ''));
            }
            if ($page === '') {
                continue;
            }

            $key = strtolower($page);
            if (!isset($groups[$key])) {
                $groups[$key] = ['page' => $page, 'contacts' => 0, 'sql' => 0, 'dealLinked' => 0];
            }
            $lifecycle = strtolower((string)($p['lifecyclestage'] ?? ''));
            $groups[$key]['contacts']++;
            if ($lifecycle === 'salesqualifiedlead') {
                $groups[$key]['sql']++;
            }
            if ((int)($p['num_associated_deals'] ?? 0) > 0 || !empty($p['first_deal_created_date'])) {
                $groups[$key]['dealLinked']++;
            }
        }

        $rows = array_values($groups);
        usort($rows, function ($a, $b) {
            $left = ($b['dealLinked'] * 5) + ($b['sql'] * 3) + $b['contacts'];
            $right = ($a['dealLinked'] * 5) + ($a['sql'] * 3) + $a['contacts'];
            return $left <=> $right;
        });
        return array_slice($rows, 0, 8);
    }

    private function buildDataQuality(array $contacts, array $companies): array
    {
        $total = count($contacts);
        $missingCompany = 0;
        $missingSource = 0;
        $missingLifecycle = 0;
        $missingUtm = 0;
        $missingMatomo = 0;
        $contactsWithSource = 0;
        $contactsWithUtm = 0;
        $contactsWithMatomo = 0;

        foreach ($contacts as $contact) {
            $p = $contact['properties'] ?? [];
            if (!$this->hasValue($p['company'] ?? '')) {
                $missingCompany++;
            }
            if (!$this->hasValue($p['hs_analytics_source'] ?? '') && !$this->hasValue($p['hs_latest_source'] ?? '')) {
                $missingSource++;
            } else {
                $contactsWithSource++;
            }
            if (!$this->hasValue($p['lifecyclestage'] ?? '')) {
                $missingLifecycle++;
            }
            if (!$this->hasValue($p['signalatlas_utm_source'] ?? '') && !$this->hasValue($p['signalatlas_utm_campaign'] ?? '')) {
                $missingUtm++;
            } else {
                $contactsWithUtm++;
            }
            if (!$this->hasValue($p['matomo_visitor_id'] ?? '')) {
                $missingMatomo++;
            } else {
                $contactsWithMatomo++;
            }
        }

        $companiesMissingDomain = 0;
        foreach ($companies as $company) {
            $p = $company['properties'] ?? [];
            if (!$this->hasValue($p['domain'] ?? '')) {
                $companiesMissingDomain++;
            }
        }

        return [
            'totalContacts' => $total,
            'missingCompany' => $missingCompany,
            'missingSource' => $missingSource,
            'missingLifecycle' => $missingLifecycle,
            'missingUtm' => $missingUtm,
            'missingMatomo' => $missingMatomo,
            'companiesMissingDomain' => $companiesMissingDomain,
            'contactsWithSource' => $contactsWithSource,
            'contactsWithUtm' => $contactsWithUtm,
            'contactsWithMatomo' => $contactsWithMatomo,
        ];
    }

    private function buildNextActions(array $hotAccounts, array $dataQuality, array $sourceRows, array $pageRows): array
    {
        $topAccount = $hotAccounts[0]['company'] ?? 'top account';
        $topSource = $sourceRows[0]['source'] ?? 'best-performing source';
        $topPage = $pageRows[0]['page'] ?? 'highest-intent page';
        return [
            ['priority' => 'High', 'title' => 'Prioritize hot account follow-up', 'detail' => 'Start with ' . $topAccount . ' and route the best matched contact to SDR follow-up within 24 hours.'],
            ['priority' => 'High', 'title' => 'Close attribution gaps', 'detail' => 'Fix contacts missing source/UTM/visitor ID. Current contacts missing source: ' . ($dataQuality['missingSource'] ?? 0) . '.'],
            ['priority' => 'Medium', 'title' => 'Double down on working channels', 'detail' => 'Review ' . $topSource . ' because it currently contributes the strongest contact evidence.'],
            ['priority' => 'Medium', 'title' => 'Improve page-to-pipeline proof', 'detail' => 'Use ' . $topPage . ' as the first page to validate against SQL/deal creation.'],
        ];
    }

    private function contactScore(array $p): int
    {
        $score = 35;
        $stage = strtolower((string)($p['lifecyclestage'] ?? ''));
        if ($stage === 'salesqualifiedlead') {
            $score += 25;
        } elseif ($stage === 'marketingqualifiedlead') {
            $score += 15;
        }
        if ($this->hasValue($p['hs_analytics_source'] ?? '') || $this->hasValue($p['hs_latest_source'] ?? '')) {
            $score += 10;
        }
        if ($this->hasValue($p['signalatlas_page_url'] ?? '')) {
            $score += 10;
        }
        if ($this->hasValue($p['signalatlas_utm_source'] ?? '') || $this->hasValue($p['signalatlas_utm_campaign'] ?? '')) {
            $score += 8;
        }
        if ($this->hasValue($p['matomo_visitor_id'] ?? '')) {
            $score += 7;
        }
        if ((int)($p['num_associated_deals'] ?? 0) > 0 || !empty($p['first_deal_created_date'])) {
            $score += 20;
        }
        $hubspotScore = (int)($p['hubspotscore'] ?? 0);
        if ($hubspotScore > 0) {
            $score += min(10, (int)round($hubspotScore / 20));
        }
        return min(99, max(0, $score));
    }

    private function sourceName(array $p): string
    {
        $utm = trim((string)($p['signalatlas_utm_source'] ?? ''));
        if ($utm !== '') {
            return $utm;
        }
        $latest = trim((string)($p['hs_latest_source'] ?? ''));
        if ($latest !== '') {
            return $latest;
        }
        $original = trim((string)($p['hs_analytics_source'] ?? ''));
        if ($original !== '') {
            return $original;
        }
        return 'Not captured';
    }

    private function companyName(array $p): string
    {
        $company = trim((string)($p['company'] ?? ''));
        return $company !== '' ? $company : 'Not captured';
    }

    private function emailDomain(string $email): string
    {
        if (strpos($email, '@') === false) {
            return '';
        }
        $domain = strtolower(substr(strrchr($email, '@'), 1));
        $blocked = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'icloud.com', 'aol.com', 'live.com', 'msn.com'];
        if (in_array($domain, $blocked, true)) {
            return 'Unmapped Account';
        }
        return $domain;
    }

    private function hasValue($value): bool
    {
        return trim((string)$value) !== '';
    }

    private function getRefreshUrl(): string
    {
        $query = $_GET;
        $query['hs_refresh'] = 1;
        return '?' . http_build_query($query);
    }
}
