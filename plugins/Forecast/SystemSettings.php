<?php

declare(strict_types=1);

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\Forecast;

use Piwik\Piwik;
use Piwik\Settings\FieldConfig;

class SystemSettings extends \Piwik\Settings\Plugin\SystemSettings
{
    /** @var \Piwik\Settings\Plugin\SystemSetting */
    public $pythonBinPath;

    /** @var \Piwik\Settings\Plugin\SystemSetting */
    public $apiKey;

    /** @var \Piwik\Settings\Plugin\SystemSetting */
    public $apiHostname;

    /**
     * Initialises all plugin settings.
     *
     * @return void
     */
    protected function init(): void
    {
        $this->pythonBinPath = $this->createPythonBinPathSetting();
        $this->apiKey        = $this->createApiKeySettings();
        $this->apiHostname   = $this->createApiHostnameSettings();
    }

    /**
     * Creates the setting for the Python executable path.
     *
     * @return \Piwik\Settings\Plugin\SystemSetting
     */
    private function createPythonBinPathSetting(): \Piwik\Settings\Plugin\SystemSetting
    {
        return $this->makeSetting('pythonBinPath', 'python3', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title       = Piwik::translate('Forecast_SettingPythonBinPathTitle');
            $field->uiControl   = FieldConfig::UI_CONTROL_TEXT;
            $field->description = Piwik::translate('Forecast_SettingPythonBinPathDescription');
        });
    }

    /**
     * Creates the setting for the remote API key.
     *
     * @return \Piwik\Settings\Plugin\SystemSetting
     */
    private function createApiKeySettings(): \Piwik\Settings\Plugin\SystemSetting
    {
        return $this->makeSetting('apiKey', '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title       = Piwik::translate('Forecast_SettingApiKeyTitle');
            $field->uiControl   = FieldConfig::UI_CONTROL_PASSWORD;
            $field->description = Piwik::translate('Forecast_SettingApiKeyDescription');
        });
    }

    /**
     * Creates the setting for the remote API hostname.
     *
     * @return \Piwik\Settings\Plugin\SystemSetting
     */
    private function createApiHostnameSettings(): \Piwik\Settings\Plugin\SystemSetting
    {
        return $this->makeSetting('apiHostname', '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title       = Piwik::translate('Forecast_SettingApiHostnameTitle');
            $field->uiControl   = FieldConfig::UI_CONTROL_TEXT;
            $field->description = Piwik::translate('Forecast_SettingApiHostnameDescription');
        });
    }
}
