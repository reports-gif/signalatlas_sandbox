<?php

namespace Piwik\Plugins\HubSpotDashboard;

use Piwik\API\Request;

class MatomoClient
{
    public function getRecentVisits(int $idSite, int $limit = 100): array
    {
        try {
            $query = http_build_query([
                'method' => 'Live.getLastVisitsDetails',
                'idSite' => $idSite,
                'period' => 'day',
                'date' => 'today',
                'filter_limit' => max(1, min($limit, 500)),
                'format' => 'original'
            ]);

            $request = new Request($query);
            $response = $request->process();

            if (is_array($response)) {
                return ['error' => false, 'results' => $response, 'total' => count($response)];
            }

            return [
                'error' => true,
                'message' => 'Matomo Live API did not return an array response.',
                'results' => [],
                'total' => 0
            ];
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage(), 'results' => [], 'total' => 0];
        }
    }

    public function normalizeVisits(array $visits): array
    {
        $rows = [];

        foreach ($visits as $visit) {
            $actionDetails = $visit['actionDetails'] ?? [];
            $firstUrl = 'Not captured';
            $pageTitle = 'Not captured';

            if (!empty($actionDetails) && is_array($actionDetails)) {
                foreach ($actionDetails as $action) {
                    if (!empty($action['url'])) {
                        $firstUrl = $action['url'];
                        $pageTitle = $action['pageTitle'] ?? $action['title'] ?? 'Not captured';
                        break;
                    }
                }
            }

            $companyCandidate = $visit['organization']
                ?? $visit['company']
                ?? $visit['companyName']
                ?? $visit['provider']
                ?? $visit['isp']
                ?? 'Not captured';

            $rows[] = [
                'visitTime' => $visit['serverDatePretty'] ?? $visit['serverTimePretty'] ?? $visit['serverDate'] ?? 'Not captured',
                'visitorId' => $visit['visitorId'] ?? 'Not captured',
                'visitIp' => $visit['visitIp'] ?? $visit['visitorIp'] ?? 'Hidden / anonymized',
                'country' => $visit['country'] ?? $visit['location_country'] ?? 'Not captured',
                'companyCandidate' => $companyCandidate ?: 'Not captured',
                'pageUrl' => $firstUrl,
                'pageTitle' => $pageTitle,
                'browser' => $visit['browserName'] ?? 'Not captured',
                'device' => $visit['deviceType'] ?? 'Not captured',
                'referrer' => $visit['referrerName'] ?? $visit['referrerType'] ?? 'Not captured'
            ];
        }

        return $rows;
    }
}
