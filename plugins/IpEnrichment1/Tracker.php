<?php
namespace Piwik\Plugins\IpEnrichment;

use Piwik\Tracker\Request;

class Tracker
{
    public function processRequest(Request $request)
    {
        $ip = $request->getIpString();

        // API call
        $data = file_get_contents("https://ipinfo.io/{$ip}/json?token=YOUR_TOKEN");
        $json = json_decode($data, true);

        if (!empty($json['org'])) {
            $request->setCustomTrackingParameter('dimension1', $json['org']);
        }

        if (!empty($json['city'])) {
            $request->setCustomTrackingParameter('dimension2', $json['city']);
        }
    }
}