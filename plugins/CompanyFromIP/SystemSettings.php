<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\CompanyFromIP;

use Piwik\Piwik;
use Piwik\Settings\FieldConfig;
use Piwik\Settings\Plugin\SystemSetting;
use Piwik\Validators\NumberRange;

class SystemSettings extends \Piwik\Settings\Plugin\SystemSettings
{
    public SystemSetting $apiToken;
    public SystemSetting $cacheTtlDays;
    public SystemSetting $requestTimeoutSeconds;
    public SystemSetting $enableLookup;

    protected function init(): void
    {
        $this->enableLookup = $this->makeSetting(
            'enableLookup',
            true,
            FieldConfig::TYPE_BOOL,
            function (FieldConfig $field) {
                $field->title       = Piwik::translate('CompanyFromIP_SettingEnable');
                $field->uiControl   = FieldConfig::UI_CONTROL_CHECKBOX;
                $field->description = Piwik::translate('CompanyFromIP_SettingEnableDesc');
            }
        );

        $this->apiToken = $this->makeSetting(
            'apiToken',
            '',
            FieldConfig::TYPE_STRING,
            function (FieldConfig $field) {
                $field->title       = Piwik::translate('CompanyFromIP_SettingApiToken');
                $field->uiControl   = FieldConfig::UI_CONTROL_TEXT;
                $field->description = Piwik::translate('CompanyFromIP_SettingApiTokenDesc');
            }
        );

        $this->cacheTtlDays = $this->makeSetting(
            'cacheTtlDays',
            30,
            FieldConfig::TYPE_INT,
            function (FieldConfig $field) {
                $field->title       = Piwik::translate('CompanyFromIP_SettingCacheTtl');
                $field->uiControl   = FieldConfig::UI_CONTROL_TEXT;
                $field->description = Piwik::translate('CompanyFromIP_SettingCacheTtlDesc');
                $field->validators[] = new NumberRange(1, 365);
            }
        );

        $this->requestTimeoutSeconds = $this->makeSetting(
            'requestTimeoutSeconds',
            2,
            FieldConfig::TYPE_INT,
            function (FieldConfig $field) {
                $field->title       = Piwik::translate('CompanyFromIP_SettingTimeout');
                $field->uiControl   = FieldConfig::UI_CONTROL_TEXT;
                $field->description = Piwik::translate('CompanyFromIP_SettingTimeoutDesc');
                $field->validators[] = new NumberRange(1, 30);
            }
        );
    }
}
