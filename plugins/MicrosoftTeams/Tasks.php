<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\MicrosoftTeams;

class Tasks extends \Piwik\Plugin\Tasks
{
    /**
     * @var ClientSecretExpiryNotifier
     */
    private $notifier;

    public function __construct(ClientSecretExpiryNotifier $notifier)
    {
        $this->notifier = $notifier;
    }

    public function schedule()
    {
        $this->daily('sendClientSecretExpiryNotifications');
    }

    public function sendClientSecretExpiryNotifications(): void
    {
        $this->notifier->sendNotificationsIfDue();
    }
}
