<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

declare(strict_types=1);

namespace Piwik\Plugins\MicrosoftTeams;

use Piwik\Date;
use Piwik\Container\StaticContainer;
use Piwik\Log\LoggerInterface;
use Piwik\Plugins\MicrosoftTeams\Emails\ClientSecretExpiryNotificationEmail;
use Piwik\Plugins\ScheduledReports\API as ScheduledReportsApi;
use Piwik\Plugins\UsersManager\API as UsersManagerApi;
use Piwik\Scheduler\Schedule\Schedule;

class ClientSecretExpiryNotifier
{
    private const NOTICE_DAYS = [28, 21, 14, 7, 0];
    /**
     * @var SystemSettings
     */
    private $settings;

    /**
     * @var ScheduledReportsApi
     */
    private $scheduledReportsApi;

    /**
     * @var UsersManagerApi
     */
    private $usersManagerApi;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Builds the notifier with the settings, reports API, and user API it needs.
     */
    public function __construct(
        SystemSettings $settings,
        ScheduledReportsApi $scheduledReportsApi,
        UsersManagerApi $usersManagerApi,
        LoggerInterface $logger
    ) {
        $this->settings = $settings;
        $this->scheduledReportsApi = $scheduledReportsApi;
        $this->usersManagerApi = $usersManagerApi;
        $this->logger = $logger;
    }

    /**
     * Sends expiry notifications when today matches one of the configured notice days.
     */
    public function sendNotificationsIfDue(): void
    {
        $expiryDate = $this->getExpiryDate();
        if ($expiryDate === null) {
            return;
        }

        // The task is stateless: only exact notice days result in email.
        $daysUntilExpiry = $this->getDaysUntilExpiry($expiryDate);
        if (!$this->isNoticeDay($daysUntilExpiry)) {
            return;
        }

        $superUserRecipients = $this->getSuperUserRecipients();
        $this->sendEmails($superUserRecipients, $expiryDate, $daysUntilExpiry, 'SuperUser');

        $ownerRecipients = $this->getReportOwnerRecipients($superUserRecipients);
        $this->sendEmails($ownerRecipients, $expiryDate, $daysUntilExpiry, 'Owner');
    }

    /**
     * Calculates full UTC days remaining until the configured client secret expiry date.
     */
    public function getDaysUntilExpiry(Date $expiryDate): int
    {
        return (int)(($expiryDate->getTimestamp() - Date::today()->getTimestamp()) / Date::NUM_SECONDS_IN_DAY);
    }

    /**
     * Checks whether the number of days remaining should trigger a notification.
     */
    public function isNoticeDay(int $daysUntilExpiry): bool
    {
        return in_array($daysUntilExpiry, self::NOTICE_DAYS, true);
    }

    /**
     * Reads and parses the configured expiry date, returning null when it cannot be used.
     */
    private function getExpiryDate(): ?Date
    {
        $expiryDate = $this->settings->clientSecretExpiryDate->getValue();
        if (empty($expiryDate)) {
            return null;
        }

        try {
            return Date::factory($expiryDate);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Sends the selected template to each email address in the recipient list.
     *
     * @param array<string, string> $emails
     */
    private function sendEmails(array $emails, Date $expiryDate, int $daysUntilExpiry, string $recipientType): void
    {
        foreach ($emails as $email) {
            $this->sendEmail($email, $expiryDate, $daysUntilExpiry, $recipientType);
        }
    }

    /**
     * Collects all superuser email addresses, de-duplicated by email address.
     *
     * @return array<string, string>
     */
    private function getSuperUserRecipients(): array
    {
        $recipients = [];

        foreach ($this->usersManagerApi->getUsersHavingSuperUserAccess() as $user) {
            $this->addRecipient($recipients, $user);
        }

        return $recipients;
    }

    /**
     * Collects non-superuser owners of active Microsoft Teams scheduled reports.
     *
     * @param array<string, string> $excludedEmails
     * @return array<string, string>
     */
    private function getReportOwnerRecipients(array $excludedEmails): array
    {
        $recipients = [];
        $superUserLogins = $this->getSuperUserLogins();

        foreach ($this->getMicrosoftTeamsReports() as $report) {
            $this->addReportOwnerRecipient($recipients, $report, $superUserLogins, $excludedEmails);
        }

        return $recipients;
    }

    /**
     * Builds a lookup table for logins that already receive the superuser email.
     *
     * @return array<string, bool>
     */
    private function getSuperUserLogins(): array
    {
        $logins = [];

        foreach ($this->usersManagerApi->getUsersHavingSuperUserAccess() as $user) {
            if (!empty($user['login'])) {
                $logins[$user['login']] = true;
            }
        }

        return $logins;
    }

    /**
     * Returns active scheduled reports that use Microsoft Teams delivery.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getMicrosoftTeamsReports(): array
    {
        $reports = [];

        foreach ($this->getScheduledReports() as $report) {
            if ($report['type'] === MicrosoftTeams::MS_TEAMS_TYPE && $report['period'] !== Schedule::PERIOD_NEVER) {
                $reports[] = $report;
            }
        }

        return $reports;
    }

    /**
     * Returns scheduled reports, logging and skipping owner emails if reports cannot be loaded.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getScheduledReports(): array
    {
        try {
            return $this->scheduledReportsApi->getReports();
        } catch (\Exception $e) {
            $this->logger->warning('Could not load scheduled reports for Microsoft Teams expiry emails: {exception}', [
                'exception' => $e,
            ]);

            return [];
        }
    }

    /**
     * Adds a report owner email unless the owner is a superuser or already notified.
     *
     * @param array<string, string> $recipients
     * @param array<string, mixed> $report
     * @param array<string, bool> $superUserLogins
     * @param array<string, string> $excludedEmails
     */
    private function addReportOwnerRecipient(
        array &$recipients,
        array $report,
        array $superUserLogins,
        array $excludedEmails
    ): void {
        $login = $report['login'] ?? '';
        if (empty($login) || isset($superUserLogins[$login])) {
            return;
        }

        try {
            $this->addRecipient($recipients, $this->usersManagerApi->getUser($login), $excludedEmails);
        } catch (\Exception $e) {
            return;
        }
    }

    /**
     * Adds one user email to the recipient list unless it is empty or excluded.
     *
     * @param array<string, string> $recipients
     * @param array<string, mixed> $user
     * @param array<string, string> $excludedEmails
     */
    private function addRecipient(array &$recipients, array $user, array $excludedEmails = []): void
    {
        if (empty($user['email']) || isset($excludedEmails[$user['email']])) {
            return;
        }

        $recipients[$user['email']] = $user['email'];
    }

    /**
     * Creates and sends a single expiry notification email.
     */
    private function sendEmail(string $email, Date $expiryDate, int $daysUntilExpiry, string $recipientType): void
    {
        try {
            /** @var ClientSecretExpiryNotificationEmail $mail */
            $mail = StaticContainer::getContainer()->make(ClientSecretExpiryNotificationEmail::class, [
                'recipientEmail' => $email,
                'recipientType' => $recipientType,
                'daysUntilExpiry' => $daysUntilExpiry,
                'expiryDate' => $expiryDate->toString(),
            ]);
            $mail->safeSend();
        } catch (\Exception $e) {
            $this->logger->warning('Could not send Microsoft Teams client secret expiry email: {exception}', [
                'exception' => $e,
            ]);
        }
    }
}
