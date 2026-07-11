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
            return [
                'error' => true,
                'message' => 'HubSpot token is missing in config.ini.php'
            ];
        }

        if (!function_exists('curl_init')) {
            return [
                'error' => true,
                'message' => 'PHP cURL extension is not enabled on this hosting.'
            ];
        }

        $url = 'https://api.hubapi.com' . $endpoint;
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);

            return [
                'error' => true,
                'message' => $error
            ];
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

    public function getConnectionStatus(): array
    {
        $response = $this->request('POST', '/crm/v3/objects/contacts/search', [
            'limit' => 1,
            'properties' => ['email']
        ]);

        if (!empty($response['error'])) {
            return [
                'connected' => false,
                'message' => $response['message'] ?? 'Connection failed'
            ];
        }

        return [
            'connected' => true,
            'message' => 'Connected'
        ];
    }

    public function searchContactsByLifecycleStage(string $stage): array
    {
        return $this->request('POST', '/crm/v3/objects/contacts/search', [
            'filterGroups' => [
                [
                    'filters' => [
                        [
                            'propertyName' => 'lifecyclestage',
                            'operator' => 'EQ',
                            'value' => $stage
                        ]
                    ]
                ]
            ],
            'properties' => [
                'email',
                'lifecyclestage',
                'matomo_visitor_id',
                'signalatlas_page_url',
                'signalatlas_utm_source',
                'signalatlas_utm_campaign'
            ],
            'limit' => 100
        ]);
    }

    public function searchDeals(): array
    {
        return $this->request('POST', '/crm/v3/objects/deals/search', [
            'filterGroups' => [
                [
                    'filters' => [
                        [
                            'propertyName' => 'dealstage',
                            'operator' => 'HAS_PROPERTY'
                        ]
                    ]
                ]
            ],
            'properties' => [
                'dealname',
                'dealstage',
                'amount',
                'closedate',
                'pipeline',
                'createdate'
            ],
            'limit' => 100
        ]);
    }
}
