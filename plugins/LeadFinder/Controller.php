<?php

namespace Piwik\Plugins\LeadFinder;

use Piwik\Plugin\Controller;

class Controller extends Controller
{
    public function index()
    {
        return $this->renderTemplate('index');
    }

    public function getLeads()
    {
        $company = $_GET['company'] ?? 'example.com';
        $city = $_GET['city'] ?? 'Pune';

        $apiKey = "YOUR_APOLLO_API_KEY";

        $url = "https://api.apollo.io/v1/mixed_people/search";

        $data = [
            "api_key" => $apiKey,
            "q_organization_domains" => [$company],
            "person_locations" => [$city],
            "person_titles" => ["Director"]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json"
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        echo $response;
        exit;
    }
}