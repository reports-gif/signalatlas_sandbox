<?php

namespace Piwik\Plugins\VipDetector\Commands;

use Exception;
use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\VipDetector\RangeUpdater;
use Piwik\Plugins\VipDetector\SystemSettings;

class ImportData extends ConsoleCommand
{
    protected function configure(): void
    {
        $this->setName('vipdetector:import-data');
        $this->setDescription('Import Json File with ranges');
        $this->addRequiredArgument(
            'file',
            'Path to the file'
        );
    }

    /**
     * Start file import. Warn the User in case the Scheduler import is also enabled.
     * @throws Exception
     * @return int The exit code
     */
    protected function doExecute(): int
    {
        $input = $this->getInput();
        $file = $input->getArgument('file');
        $settings = new SystemSettings();

        // Warn the user if the scheduler import is also enabled.
        if ($settings->importViaScheduler->getValue()) {
            $this->getOutput()->writeln('<fg=yellow>========= WARNING ==========</>');
            $this->getOutput()->writeln('<fg=yellow>Scheduler Import is enabled!</>');
            $this->getOutput()->writeln('<fg=yellow>========= WARNING ==========</>');
        }

        $importer = new RangeUpdater($file, 'file');

        // Try to import.
        try {
            $importer->import();
        } catch (Exception $e) {
            $this->getOutput()->writeln("<fg=red>Import failed: {$e->getMessage()}</>");
            return self::FAILURE;
        }

        $this->getOutput()->writeln('<fg=green>Import done.</>');
        return self::SUCCESS;
    }
}
