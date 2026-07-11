<?php

namespace Piwik\Plugins\VipDetector;

use Piwik\Settings\FieldConfig;
use Piwik\Validators\NotEmpty;
use Piwik\Validators\UrlLike;
use Piwik\Settings\Plugin\SystemSetting;

class SystemSettings extends \Piwik\Settings\Plugin\SystemSettings
{
    public SystemSetting $importUrl;
    public SystemSetting $importViaScheduler;

    protected function init(): void
    {
        $this->title = "VIP Ranges Detector";
        $this->importUrl = $this->createImportUrlSetting();
        $this->importViaScheduler = $this->importViaSchedulerSetting();
    }

    // Source URL Setting
    private function createImportUrlSetting(): SystemSetting
    {
        return $this->makeSetting(
            'importUrl',
            'https://ranges.vikoe.eu/all.json',
            FieldConfig::TYPE_STRING,
            function (FieldConfig $field) {
                $field->title = 'Json Source File Download URL';
                $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
                $field->description = 'The URL where the range file is located';
                $field->validators[] = new NotEmpty();
                $field->validators[] = new UrlLike();
            }
        );
    }

    // Import via CLI or Scheduler?
    private function importViaSchedulerSetting(): SystemSetting
    {
        return $this->makeSetting(
            'importViaScheduler',
            false,
            FieldConfig::TYPE_BOOL,
            function (FieldConfig $field) {
                $field->title = 'Use Scheduler';
                $field->uiControl = FieldConfig::UI_CONTROL_CHECKBOX;
                $field->description = 'If enabled, this URL will be used. If disabled, use the CLI importer.';
            }
        );
    }
}
