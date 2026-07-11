<?php

namespace Piwik\Plugins\HubSpotDashboard;

use Piwik\Config;

class HubSpotClient
{
    private string $token;

    public function __construct()
    {
        $config = Config::getInstance()->HubSpotDashboard;
        $this->token = $config['hubspot_token'] ?? '';
    }

    private function request(string $method, string $endpoint, ?array $payload = null): array
    {
        if (empty($this->token)) {
            return ['error' => true, 'message' => 'HubSpot token is missing in config.ini.php'];
        }

        if (!function_exists('curl_init')) {
            return ['error' => true, 'message' => 'PHP cURL extension is not enabled on this hosting.'];
        }

        $url = 'https://api.hubapi.com' . $endpoint;
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['error' => true, 'message' => $error];
        }

        curl_close($ch);

        $data = json_decode($response, true);

        if ($statusCode >= 400) {
            return [
                'error' => true,
                'status' => $statusCode,
                'message' => $data['message'] ?? 'HubSpot API error',
                'raw' => $data
            ];
        }

        return $data ?? [];
    }

    private function searchAll(string $objectType, array $payload, int $maxRecords = 1000): array
    {
        $allResults = [];
        $total = 0;
        $after = null;

        do {
            $payload['limit'] = 100;
            if ($after !== null) {
                $payload['after'] = $after;
            } else {
                unset($payload['after']);
            }

            $response = $this->request('POST', '/crm/v3/objects/' . $objectType . '/search', $payload);

            if (!empty($response['error'])) {
                return $response;
            }

            $total = $response['total'] ?? $total;
            $results = $response['results'] ?? [];
            $allResults = array_merge($allResults, $results);

            $after = $response['paging']['next']['after'] ?? null;

            if (count($allResults) >= $maxRecords) {
                break;
            }

        } while ($after !== null);

        return [
            'total' => $total,
            'results' => $allResults,
            'fetched' => count($allResults),
            'truncated' => count($allResults) < $total
        ];
    }

    public function getConnectionStatus(): array
    {
        $response = $this->request('POST', '/crm/v3/objects/contacts/search', [
            'limit' => 1,
            'properties' => ['email']
        ]);

        if (!empty($response['error'])) {
            return ['connected' => false, 'message' => $response['message'] ?? 'Connection failed'];
        }

        return ['connected' => true, 'message' => 'Connected'];
    }

    public function searchContactsByLifecycleStage(string $stage): array
    {
        return $this->searchAll('contacts', [
            'filterGroups' => [[
                'filters' => [[
                    'propertyName' => 'lifecyclestage',
                    'operator' => 'EQ',
                    'value' => $stage
                ]]
            ]],
            'properties' => [
                'email',
                'firstname',
                'lastname',
                'company',
                'lifecyclestage',
                'createdate',
                'lastmodifieddate',
                'matomo_visitor_id',
                'signalatlas_page_url',
                'signalatlas_utm_source',
                'signalatlas_utm_campaign'
            ]
        ], 1000);
    }

    public function searchDeals(): array
    {
        return $this->searchAll('deals', [
            'filterGroups' => [[
                'filters' => [[
                    'propertyName' => 'dealstage',
                    'operator' => 'HAS_PROPERTY'
                ]]
            ]],
            'properties' => [
                'dealname',
                'dealstage',
                'amount',
                'closedate',
                'pipeline',
                'createdate'
            ]
        ], 1000);
    }

    public function searchContactByEmail(string $email): array
    {
        $response = $this->request('POST', '/crm/v3/objects/contacts/search', [
            'filterGroups' => [[
                'filters' => [[
                    'propertyName' => 'email',
                    'operator' => 'EQ',
                    'value' => $email
                ]]
            ]],
            'properties' => [
                'email',
                'firstname',
                'lastname',
                'company',
                'lifecyclestage',
                'createdate',
                'lastmodifieddate',
                'hubspotscore',
                'matomo_visitor_id',
                'signalatlas_page_url',
                'signalatlas_utm_source',
                'signalatlas_utm_campaign'
            ],
            'limit' => 1
        ]);

        if (!empty($response['error'])) {
            return $response;
        }

        $results = $response['results'] ?? [];
        if (empty($results)) {
            return ['error' => true, 'message' => 'No HubSpot contact found for this email.'];
        }

        return $results[0];
    }

    public function getDealsForContact(string $contactId): array
    {
        $association = $this->request('GET', '/crm/v4/objects/contacts/' . urlencode($contactId) . '/associations/deals?limit=100');

        if (!empty($association['error'])) {
            return $association;
        }

        $associatedDeals = $association['results'] ?? [];
        $deals = [];

        foreach ($associatedDeals as $row) {
            $dealId = $row['toObjectId'] ?? null;
            if (!$dealId) {
                continue;
            }

            $deal = $this->request('GET', '/crm/v3/objects/deals/' . urlencode($dealId) . '?properties=dealname,dealstage,amount,closedate,pipeline,createdate');

            if (empty($deal['error'])) {
                $deals[] = $deal;
            }
        }

        return [
            'total' => count($deals),
            'results' => $deals
        ];
    }
}
