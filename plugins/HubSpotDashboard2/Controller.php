<?php

namespace Piwik\Plugins\HubSpotDashboard;

use Piwik\Plugin\Controller as BaseController;
use Piwik\Piwik;

class Controller extends BaseController
{
    public function journey()
    {
        Piwik::checkUserIsNotAnonymous();

        $email = trim($_GET['email'] ?? '');
        $client = new HubSpotClient();

        $contact = null;
        $deals = ['total' => 0, 'results' => []];
        $error = '';

        if ($email === '') {
            $error = 'Missing email parameter.';
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
                }
            }
        }

        return $this->renderTemplate('journey', [
            'email' => $email,
            'contact' => $contact,
            'deals' => $deals,
            'error' => $error
        ]);
    }
}
