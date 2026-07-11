<?php

namespace Piwik\Plugins\VipDetector;

use Exception;
use Piwik\Container\StaticContainer;
use Piwik\Log\LoggerInterface;

class Tasks extends \Piwik\Plugin\Tasks
{
    public function schedule(): void
    {
        $this->hourly('rangeImportTask');
    }

    /**
     * @throws Exception
     */
    public function rangeImportTask(): void
    {
        $logger = StaticContainer::get(LoggerInterface::class); // @phan-suppress-current-line PhanAccessMethodInternal
        $settings = new SystemSettings();
        $importUrl = $settings->importUrl->getValue();
        $importViaScheduler = $settings->importViaScheduler->getValue();

        // Don't run if the scheduler is disabled -> User wants to import using the cli
        if (!$importViaScheduler) {
            $logger->info("Scheduler is disabled.");
            return;
        }

        $importer = new RangeUpdater($importUrl, "url");

        try {
            $importer->import();
        } catch (Exception $e) {
            $logger->critical("Import failed: {$e->getMessage()}");
        }
    }
}
