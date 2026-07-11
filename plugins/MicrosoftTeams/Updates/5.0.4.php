<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Plugins\MicrosoftTeams;

use Exception;
use Piwik\Common;
use Piwik\Db;
use Piwik\Mail;
use Piwik\Updater;
use Piwik\Updates;

/**
 * Update for version 5.0.4.
 */
class Updates_5_0_4 extends Updates
{
    /**
     * @throws Exception
     */
    public function doUpdate(Updater $updater): void
    {
        $userTable = Common::prefixTable('user');
        $reportTable = Common::prefixTable('report');
        $alertTable = Common::prefixTable('alert');
        $reportData = DB::fetchAll("Select login,email FROM `$userTable` where login in (SELECT distinct login FROM `$reportTable` where type='teams' AND parameters NOT LIKE '%powerautomate%' and deleted=0)");
        $alertData = DB::fetchAll("Select login,email FROM `$userTable` where login in (Select distinct login FROM `$alertTable` where ms_teams_webhook_url NOT LIKE '%powerautomate%' AND report_mediums like '%teams%')");
        $reportUsers = $this->normalizeDbData($reportData);
        $alertUsers = $this->normalizeDbData($alertData);
        $usersToAlert = array_merge($reportUsers, $alertUsers);
        $senderEmail = 'support@matomo.cloud';
        // Since this will be executed once, no need for translation
        $subject = 'Action required: Update MS Teams webhook in Matomo';
        $emailBody = <<<HTML
<p>Hi,</p>
<p>Microsoft is retiring O365 connectors, including MS Teams webhooks. Your Matomo instance currently uses webhooks for scheduled reports and/or custom alerts.</p>
<p>To make sure these reports and alerts keep working, you will need to replace the current webhooks with Power Automate workflow webhooks. Follow <a href="https://matomo.org/faq/reports/how-to-get-microsoft-teams-webhook-url/" target="_blank" rel="noreferrer noopener">this guide</a> to learn how to create the appropriate webhooks.</p>
<p>We recommend replacing these webhooks as soon as possible to ensure continued operations. The current webhooks should continue to work until <strong>30 April 2026.</strong></p>
<p>Happy Analytics!</p>
HTML;
        foreach ($usersToAlert as $userEmail) {
            $mail = new Mail();
            $mail->setFrom($senderEmail, 'Matthieu from Matomo');
            $mail->setReplyTo($senderEmail);
            $mail->addTo($userEmail);
            $mail->setSubject($subject);
            $mail->setBodyHtml($emailBody);
            $mail->send();
        }
    }

    private function normalizeDbData($data)
    {
        $return = [];
        foreach ($data as $row) {
            $return[$row['login']] = $row['email'];
        }

        return $return;
    }
}
