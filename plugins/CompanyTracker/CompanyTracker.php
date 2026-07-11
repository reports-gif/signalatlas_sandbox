<?php

namespace Piwik\Plugins\CompanyTracker;

class CompanyTracker extends \Piwik\Plugin
{
    public function registerEvents()
    {
        file_put_contents(
            __DIR__ . '/loaded.txt',
            'PLUGIN LOADED'.PHP_EOL,
            FILE_APPEND
        );

        return [
            'Tracker.setVisitCustomVariables' => 'setCompanyData',
        ];
    }

    public function setCompanyData(&$customVariables, $info)
    {
        file_put_contents(
            __DIR__ . '/hook.txt',
            'HOOK RUNNING'.PHP_EOL,
            FILE_APPEND
        );

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        if (!$ip || $ip == '127.0.0.1') {
            return;
        }

        // API Call
        $response = @file_get_contents(
            "https://api.ipapi.is/?q=".$ip
        );

        if (!$response) {
            return;
        }

        $data = json_decode($response, true);

        if (!empty($data['company']['name'])) {

            $company = $data['company']['name'];

            // Save Company
            $customVariables[1] = [
                'Company',
                $company
            ];

            file_put_contents(
                __DIR__ . '/companytracker.log',
                'COMPANY: '.$company.PHP_EOL,
                FILE_APPEND
            );
        }
    }
}