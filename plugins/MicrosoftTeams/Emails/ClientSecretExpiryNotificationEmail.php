<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

declare(strict_types=1);

namespace Piwik\Plugins\MicrosoftTeams\Emails;

use Piwik\Mail;
use Piwik\Piwik;
use Piwik\View;

class ClientSecretExpiryNotificationEmail extends Mail
{
    private const NOTICE_KEYS = [
        28 => 'FourWeeks',
        21 => 'ThreeWeeks',
        14 => 'TwoWeeks',
        7 => 'OneWeek',
        0 => 'Expired',
    ];

    /**
     * @var string
     */
    private $recipientType;

    /**
     * @var int
     */
    private $daysUntilExpiry;

    /**
     * @var string
     */
    private $expiryDate;

    public function __construct(string $recipientEmail, string $recipientType, int $daysUntilExpiry, string $expiryDate)
    {
        parent::__construct();

        $this->recipientType = $recipientType;
        $this->daysUntilExpiry = $daysUntilExpiry;
        $this->expiryDate = $expiryDate;

        $this->setUpEmail($recipientEmail);
    }

    private function setUpEmail(string $recipientEmail): void
    {
        $this->setDefaultFromPiwik();
        $this->addTo($recipientEmail);
        $this->addReplyTo($this->getFrom(), $this->getFromName());
        $this->setSubject($this->getEmailSubject());
        $this->setBodyText($this->getEmailBodyText());
        $this->setWrappedHtmlBody($this->getEmailBodyView());
    }

    private function getEmailSubject(): string
    {
        return Piwik::translate($this->getTranslationKey('Subject'));
    }

    private function getEmailBodyText(): string
    {
        return Piwik::translate($this->getTranslationKey('Body'), [$this->expiryDate]);
    }

    private function getEmailBodyView(): View
    {
        $view = new View('@MicrosoftTeams/_clientSecretExpiryNotificationHtmlEmail');
        $view->bodyText = $this->getEmailBodyText();

        return $view;
    }

    private function getTranslationKey(string $templatePart): string
    {
        return 'MicrosoftTeams_ClientSecretExpiryEmail' . $this->recipientType . self::NOTICE_KEYS[$this->daysUntilExpiry] . $templatePart;
    }
}
