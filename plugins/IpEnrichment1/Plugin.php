<?php
namespace Piwik\Plugins\IpEnrichment;

use Piwik\Plugin;
use Piwik\Tracker\Request;

class Plugin extends Plugin
{
    public function registerEvents()
    {
        return [
            'Tracker.newRequest' => 'onNewRequest',
        ];
    }

    public function onNewRequest(Request $request)
    {
        $ip = $request->getIpString();

        // 🔑 Replace with your real token
        $url = "https://ipinfo.io/{$ip}/json?token=b390b3484e47e9";

        // CURL request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            curl_close($ch);
            return;
        }

        curl_close($ch);

        if (!$response) {
            return;
        }

        $data = json_decode($response, true);

        if (!$data) {
            return;
        }

        // 🎯 Custom Dimensions set
        if (!empty($data['org'])) {
            $request->setCustomTrackingParameter('dimension1', $data['org']);
        }

        if (!empty($data['city'])) {
            $request->setCustomTrackingParameter('dimension2', $data['city']);
        }
    }
}