<?php

namespace Piwik\Plugins\HubSpotDashboard;

use Piwik\Plugin\Controller as BaseController;
use Piwik\Piwik;

class Controller extends BaseController
{
    public function journey()
    {
        Piwik::checkUserIsNotAnonymous();

        $email = trim((string)($_GET['email'] ?? ''));
        $client = new HubSpotClient();

        $contact = null;
        $deals = ['total' => 0, 'results' => []];
        $companies = ['total' => 0, 'results' => []];
        $activities = ['total' => 0, 'results' => [], 'errors' => []];
        $error = '';

        if ($email === '') {
            $error = 'Missing email parameter.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email parameter.';
        } else {
            $contact = $client->searchContactByEmail($email);

            if (!empty($contact['error'])) {
                $error = $contact['message'] ?? 'Unable to load contact journey.';
                $contact = null;
            } else {
                $contactId = $contact['id'] ?? '';
                if ($contactId !== '') {
                    $deals = $client->getDealsForContact($contactId);
                    if (!empty($deals['error'])) {
                        $deals = [
                            'total' => 0,
                            'results' => [],
                            'error' => $deals['message'] ?? 'Unable to load associated deals.'
                        ];
                    }

                    $companies = $client->getCompaniesForContact($contactId);
                    $activities = $client->getContactEngagementTimeline($contactId);
                }
            }
        }

        return $this->renderTemplate('journey', [
            'email' => $email,
            'contact' => $contact,
            'deals' => $deals,
            'companies' => $companies,
            'activities' => $activities,
            'error' => $error
        ]);
    }
}
