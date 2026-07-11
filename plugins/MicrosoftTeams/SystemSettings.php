<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\MicrosoftTeams;

use Piwik\Piwik;
use Piwik\Plugins\Marketplace\Api\Exception;
use Piwik\Settings\Setting;
use Piwik\Settings\FieldConfig;

class SystemSettings extends \Piwik\Settings\Plugin\SystemSettings
{
    /** @var Setting */
    public $clientID;

    /** @var Setting */
    public $clientSecret;

    /** @var Setting */
    public $clientSecretExpiryDate;

    /** @var Setting */
    public $tenantID;
    /** @var Setting */

    public $teamID;

    protected function init()
    {
        // System setting --> allows selection of a single value
        $this->clientID = $this->createClientIdSetting();
        $this->clientSecret = $this->createClientSecretSetting();
        $this->clientSecretExpiryDate = $this->createClientSecretExpiryDateSetting();
        $this->tenantID = $this->createTenantIdSetting();
        $this->teamID = $this->createTeamIdSetting();
    }

    private function createClientIdSetting()
    {
        return $this->makeSetting('teamsClientID', $default = '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = Piwik::translate('MicrosoftTeams_ClientIdTitle');
            $field->uiControl = FieldConfig::UI_CONTROL_PASSWORD;
            $field->inlineHelp = Piwik::translate('MicrosoftTeams_ClientIdDescription', $this->getRequiredFieldsLearnMoreTranslation());
            $field->transform = function ($value) {
                return trim($value);
            };
        });
    }

    private function createClientSecretSetting()
    {
        return $this->makeSetting('teamsClientSecret', $default = '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = Piwik::translate('MicrosoftTeams_ClientSecretTitle');
            $field->uiControl = FieldConfig::UI_CONTROL_PASSWORD;
            $field->inlineHelp = Piwik::translate('MicrosoftTeams_ClientSecretDescription', $this->getRequiredFieldsLearnMoreTranslation());
            $field->transform = function ($value) {
                return trim($value);
            };
        });
    }

    private function createClientSecretExpiryDateSetting()
    {
        return $this->makeSetting('teamsClientSecretExpiryDate', $default = '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = Piwik::translate('MicrosoftTeams_ClientSecretExpiryDateTitle');
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
            $field->uiControlAttributes = ['placeholder' => 'YYYY-MM-DD'];
            $field->description = Piwik::translate('MicrosoftTeams_ClientSecretExpiryDateDescription');
            $field->validate = function ($value) {
                if (!empty(trim($value))) {
                    $date = trim($value);
                    $dateFormat = \DateTime::createFromFormat('Y-m-d', $date);
                    if (!$dateFormat || $dateFormat->format('Y-m-d') !== $date) {
                        throw new Exception('Invalid date format, allowed date format is YYYY-MM-DD');
                    }
                }
            };
            $field->transform = function ($value) {
                return trim($value);
            };
        });
    }

    private function createTenantIdSetting()
    {
        return $this->makeSetting('teamsTenantID', $default = '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = Piwik::translate('MicrosoftTeams_TenantIdTitle');
            $field->uiControl = FieldConfig::UI_CONTROL_PASSWORD;
            $field->inlineHelp = Piwik::translate('MicrosoftTeams_TenantIdDescription', $this->getRequiredFieldsLearnMoreTranslation());
            $field->transform = function ($value) {
                return trim($value);
            };
        });
    }

    private function createTeamIdSetting()
    {
        return $this->makeSetting('teamsTeamID', $default = '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = Piwik::translate('MicrosoftTeams_TeamIdTitle');
            $field->uiControl = FieldConfig::UI_CONTROL_PASSWORD;
            $field->inlineHelp = Piwik::translate('MicrosoftTeams_TeamIdDescription', $this->getRequiredFieldsLearnMoreTranslation());
            $field->transform = function ($value) {
                return trim($value);
            };
        });
    }

    public function isRequiredFieldsSet(): bool
    {
        return !empty($this->clientID->getValue())
            && !empty($this->clientSecret->getValue())
            && !empty($this->tenantID->getValue())
            && !empty($this->teamID->getValue());
    }

    public function getRequiredFieldsWithValue()
    {
        return [
            'clientID' => $this->clientID->getValue(),
            'clientSecret' => $this->clientSecret->getValue(),
            'tenantID' => $this->tenantID->getValue(),
            'teamID' => $this->teamID->getValue(),
        ];
    }

    private function getRequiredFieldsLearnMoreTranslation(): array
    {
        return [
            '<a href="https://matomo.org/faq/reports/how-to-get-microsoft-teams-client-id-client-secret-tenant-id-and-teams-id/" target="_blank" rel="noopener noreferrer">',
            '</a>',
        ];
    }
}
