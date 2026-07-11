<?php

namespace Piwik\Plugins\HubSpotDashboard\Widgets;

use Piwik\Widget\Widget;
use Piwik\Widget\WidgetConfig;
use Piwik\Plugins\HubSpotDashboard\HubSpotClient;

class HubSpotDashboardWidget extends Widget
{
    public static function configure(WidgetConfig $config)
    {
        $config->setCategoryId('Dashboard_Dashboard');
        $config->setName('HubSpot Attribution Dashboard');
        $config->setOrder(10);
    }

    public function render()
    {
        $client = new HubSpotClient();

        $connection = $client->getConnectionStatus();

        $mqlResponse = $client->searchContactsByLifecycleStage('marketingqualifiedlead');
        $sqlResponse = $client->searchContactsByLifecycleStage('salesqualifiedlead');
        $dealResponse = $client->searchDeals();

        $mqlContacts = $mqlResponse['results'] ?? [];
        $sqlContacts = $sqlResponse['results'] ?? [];
        $deals = $dealResponse['results'] ?? [];

        $mqlCount = $mqlResponse['total'] ?? count($mqlContacts);
        $sqlCount = $sqlResponse['total'] ?? count($sqlContacts);

        $opportunityCount = count($deals);
        $revenue = 0;

        foreach ($deals as $deal) {
            $properties = $deal['properties'] ?? [];
            $amount = isset($properties['amount']) ? (float) $properties['amount'] : 0;
            $dealstage = $properties['dealstage'] ?? '';

            if ($dealstage === 'closedwon') {
                $revenue += $amount;
            }
        }

        $exampleRows = [];

        foreach (array_merge($mqlContacts, $sqlContacts) as $contact) {
            $p = $contact['properties'] ?? [];

            $exampleRows[] = [
                'email' => $p['email'] ?? 'Unknown',
                'lifecycle' => $p['lifecyclestage'] ?? 'Unknown',
                'matomoVisitorId' => $p['matomo_visitor_id'] ?? 'Unknown',
                'pageUrl' => $p['signalatlas_page_url'] ?? 'Unknown',
                'utmSource' => $p['signalatlas_utm_source'] ?? 'Unknown',
                'utmCampaign' => $p['signalatlas_utm_campaign'] ?? 'Unknown',
            ];

            if (count($exampleRows) >= 10) {
                break;
            }
        }

        return $this->renderTemplate('@HubSpotDashboard/dashboard', [
            'connection' => $connection,
            'mqlCount' => $mqlCount,
            'sqlCount' => $sqlCount,
            'opportunityCount' => $opportunityCount,
            'revenue' => $revenue,
            'exampleRows' => $exampleRows,
        ]);
    }
}
