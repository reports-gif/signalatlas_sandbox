<?php

namespace Piwik\Plugins\HubSpotDashboard;

use Piwik\Config;

class HubSpotClient
{
    private string $token;
    private int $timeout;
    private int $maxRecords;

    public function __construct()
    {
        $config = Config::getInstance()->HubSpotDashboard;
        $this->token = trim((string)($config['hubspot_token'] ?? ''));
        $this->timeout = (int)($config['hubspot_timeout_seconds'] ?? 25);
        $this->maxRecords = (int)($config['max_hubspot_records'] ?? 500);
    }

    private function request(string $method, string $endpoint, ?array $payload = null, int $retry = 0): array
    {
        if ($this->token === '') {
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
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, max(10, $this->timeout));

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['error' => true, 'message' => $error];
        }

        $headerText = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        curl_close($ch);

        if ($statusCode === 429 && $retry < 1) {
            $wait = 2;
            if (preg_match('/Retry-After:\s*(\d+)/i', $headerText, $m)) {
                $wait = min(5, (int)$m[1]);
            }
            sleep($wait);
            return $this->request($method, $endpoint, $payload, $retry + 1);
        }

        $data = json_decode($body, true);

        if ($statusCode >= 400) {
            return [
                'error' => true,
                'status' => $statusCode,
                'message' => $data['message'] ?? 'HubSpot API error',
                'raw' => $data
            ];
        }

        return is_array($data) ? $data : [];
    }

    private function searchAll(string $objectType, array $payload, ?int $maxRecords = null): array
    {
        $maxRecords = $maxRecords ?? $this->maxRecords;
        $allResults = [];
        $total = 0;
        $after = null;

        do {
            $payload['limit'] = min(100, max(1, $maxRecords - count($allResults)));

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

    private function contactProperties(): array
    {
        return [
            'email', 'firstname', 'lastname', 'company', 'phone', 'mobilephone', 'jobtitle', 'website',
            'lifecyclestage', 'hs_lead_status', 'hubspot_owner_id', 'createdate', 'lastmodifieddate', 'hubspotscore',
            'hs_analytics_source', 'hs_analytics_source_data_1', 'hs_analytics_source_data_2',
            'hs_latest_source', 'hs_latest_source_data_1', 'hs_latest_source_data_2', 'hs_latest_source_timestamp',
            'num_conversion_events', 'first_conversion_event_name', 'recent_conversion_event_name', 'recent_conversion_date',
            'first_deal_created_date', 'num_associated_deals', 'hs_email_last_open_date', 'hs_email_last_click_date',
            'hs_email_last_send_date', 'hs_email_last_reply_date', 'notes_last_contacted', 'notes_last_updated',
            'matomo_visitor_id', 'signalatlas_page_url', 'signalatlas_utm_source', 'signalatlas_utm_medium', 'signalatlas_utm_campaign', 'signalatlas_utm_content', 'signalatlas_utm_term'
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
            'properties' => $this->contactProperties(),
            'sorts' => ['-lastmodifieddate']
        ]);
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
                'dealname', 'dealstage', 'amount', 'closedate', 'pipeline',
                'createdate', 'hs_is_closed_won', 'hs_is_closed', 'hubspot_owner_id'
            ],
            'sorts' => ['-createdate']
        ]);
    }

    public function searchCompanies(): array
    {
        return $this->searchAll('companies', [
            'filterGroups' => [[
                'filters' => [[
                    'propertyName' => 'domain',
                    'operator' => 'HAS_PROPERTY'
                ]]
            ]],
            'properties' => [
                'name', 'domain', 'industry', 'numberofemployees', 'city', 'state', 'country',
                'lifecyclestage', 'createdate', 'hs_lastmodifieddate'
            ],
            'sorts' => ['-hs_lastmodifieddate']
        ]);
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
            'properties' => $this->contactProperties(),
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
        $deals = [];
        $after = null;

        do {
            $endpoint = '/crm/v4/objects/contacts/' . urlencode($contactId) . '/associations/deals?limit=100';
            if ($after !== null) {
                $endpoint .= '&after=' . urlencode($after);
            }

            $association = $this->request('GET', $endpoint);

            if (!empty($association['error'])) {
                return $association;
            }

            foreach (($association['results'] ?? []) as $row) {
                $dealId = $row['toObjectId'] ?? null;
                if (!$dealId) {
                    continue;
                }

                $deal = $this->request('GET', '/crm/v3/objects/deals/' . urlencode($dealId) . '?properties=dealname,dealstage,amount,closedate,pipeline,createdate,hs_is_closed_won,hs_is_closed,hubspot_owner_id');

                if (empty($deal['error'])) {
                    $deals[] = $deal;
                }
            }

            $after = $association['paging']['next']['after'] ?? null;
        } while ($after !== null && count($deals) < 100);

        return ['total' => count($deals), 'results' => $deals];
    }

    public function getCompaniesForContact(string $contactId): array
    {
        $companies = [];
        $assoc = $this->request('GET', '/crm/v4/objects/contacts/' . urlencode($contactId) . '/associations/companies?limit=20');
        if (!empty($assoc['error'])) {
            return ['total' => 0, 'results' => [], 'error' => $assoc['message'] ?? 'Unable to load associated companies'];
        }
        foreach (($assoc['results'] ?? []) as $row) {
            $companyId = $row['toObjectId'] ?? null;
            if (!$companyId) {
                continue;
            }
            $company = $this->request('GET', '/crm/v3/objects/companies/' . urlencode($companyId) . '?properties=name,domain,industry,numberofemployees,city,state,country,lifecyclestage,createdate,hs_lastmodifieddate');
            if (empty($company['error'])) {
                $companies[] = $company;
            }
        }
        return ['total' => count($companies), 'results' => $companies];
    }

    private function getAssociatedObjectIds(string $contactId, string $objectType, int $limit = 15): array
    {
        $ids = [];
        $after = null;
        do {
            $endpoint = '/crm/v4/objects/contacts/' . urlencode($contactId) . '/associations/' . $objectType . '?limit=100';
            if ($after !== null) {
                $endpoint .= '&after=' . urlencode($after);
            }
            $assoc = $this->request('GET', $endpoint);
            if (!empty($assoc['error'])) {
                return ['error' => true, 'message' => $assoc['message'] ?? ('Unable to load ' . $objectType)];
            }
            foreach (($assoc['results'] ?? []) as $row) {
                $id = $row['toObjectId'] ?? null;
                if ($id) {
                    $ids[] = (string)$id;
                    if (count($ids) >= $limit) {
                        break 2;
                    }
                }
            }
            $after = $assoc['paging']['next']['after'] ?? null;
        } while ($after !== null);
        return ['ids' => $ids];
    }

    private function getObject(string $objectType, string $id, array $properties): array
    {
        $endpoint = '/crm/v3/objects/' . $objectType . '/' . urlencode($id) . '?properties=' . urlencode(implode(',', $properties));
        return $this->request('GET', $endpoint);
    }

    public function getContactEngagementTimeline(string $contactId): array
    {
        $definitions = [
            'emails' => [
                'label' => 'Email',
                'properties' => ['hs_timestamp', 'hs_email_subject', 'hs_email_text', 'hs_email_direction', 'hs_email_status', 'hubspot_owner_id']
            ],
            'calls' => [
                'label' => 'Call',
                'properties' => ['hs_timestamp', 'hs_call_title', 'hs_call_body', 'hs_call_duration', 'hs_call_status', 'hubspot_owner_id']
            ],
            'meetings' => [
                'label' => 'Meeting',
                'properties' => ['hs_timestamp', 'hs_meeting_title', 'hs_meeting_body', 'hs_meeting_outcome', 'hubspot_owner_id']
            ],
            'notes' => [
                'label' => 'Note',
                'properties' => ['hs_timestamp', 'hs_note_body', 'hubspot_owner_id']
            ],
            'tasks' => [
                'label' => 'Task',
                'properties' => ['hs_timestamp', 'hs_task_subject', 'hs_task_body', 'hs_task_status', 'hubspot_owner_id']
            ]
        ];

        $timeline = [];
        $errors = [];

        foreach ($definitions as $objectType => $def) {
            $assoc = $this->getAssociatedObjectIds($contactId, $objectType, 12);
            if (!empty($assoc['error'])) {
                $errors[] = $def['label'] . ': ' . ($assoc['message'] ?? 'association error');
                continue;
            }

            foreach (($assoc['ids'] ?? []) as $id) {
                $obj = $this->getObject($objectType, $id, $def['properties']);
                if (!empty($obj['error'])) {
                    continue;
                }
                $p = $obj['properties'] ?? [];
                $title = $def['label'];
                $body = '';
                if ($objectType === 'emails') {
                    $title = $p['hs_email_subject'] ?? 'Email activity';
                    $body = $p['hs_email_text'] ?? '';
                } elseif ($objectType === 'calls') {
                    $title = $p['hs_call_title'] ?? 'Call activity';
                    $body = $p['hs_call_body'] ?? '';
                } elseif ($objectType === 'meetings') {
                    $title = $p['hs_meeting_title'] ?? 'Meeting activity';
                    $body = $p['hs_meeting_body'] ?? '';
                } elseif ($objectType === 'notes') {
                    $title = 'Note';
                    $body = $p['hs_note_body'] ?? '';
                } elseif ($objectType === 'tasks') {
                    $title = $p['hs_task_subject'] ?? 'Task activity';
                    $body = $p['hs_task_body'] ?? '';
                }

                $timeline[] = [
                    'type' => $def['label'],
                    'timestamp' => $p['hs_timestamp'] ?? ($obj['createdAt'] ?? ''),
                    'title' => $title,
                    'body' => trim(strip_tags((string)$body)),
                    'status' => $p['hs_email_status'] ?? $p['hs_call_status'] ?? $p['hs_meeting_outcome'] ?? $p['hs_task_status'] ?? '',
                    'owner' => $p['hubspot_owner_id'] ?? ''
                ];
            }
        }

        usort($timeline, function ($a, $b) {
            return strcmp((string)($b['timestamp'] ?? ''), (string)($a['timestamp'] ?? ''));
        });

        return ['total' => count($timeline), 'results' => array_slice($timeline, 0, 30), 'errors' => $errors];
    }
}
