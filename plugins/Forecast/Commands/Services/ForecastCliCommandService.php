<?php

declare(strict_types=1);

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\Forecast\Commands\Services;

use Piwik\Plugins\Forecast\Commands\ForecastBaseCommand;
use Piwik\Plugins\Forecast\Commands\Services\Validator\JsonValidator;
use Piwik\Plugins\Forecast\SystemSettings;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class ForecastCliCommandService
{
    private const PYTHON_MAIN_CLI_FILE = __DIR__ . '/../../Prophet/main_cli.py';

    /**
     * Runs the Prophet model in inference mode (using an existing saved model).
     *
     * @param string $visitsJson JSON-encoded array of visit records passed via stdin.
     * @param int    $siteId     Matomo site ID used to locate the model file.
     * @return string JSON-encoded array of forecast records.
     * @throws \RuntimeException When the Python process fails or returns empty output.
     */
    public function inference(string $visitsJson, int $siteId): string
    {
        $settings      = new SystemSettings();
        $pythonBinPath = $settings->pythonBinPath->getValue();

        $process = new Process(
            [
                $pythonBinPath,
                self::PYTHON_MAIN_CLI_FILE,
                '--days', (string)ForecastBaseCommand::FORECAST_DAYS,
                '--model-path', $this->getModelDir() . DIRECTORY_SEPARATOR . $siteId . '.tpl',
            ],
            null, null, null,
            600
        );

        // Pass JSON safely via stdin — no interpolation into the command line.
        $process->setInput($visitsJson);

        return $this->runProcess($process);
    }

    /**
     * Trains (or retrains) the Prophet model and returns the resulting forecast.
     *
     * @param string $visitsJson JSON-encoded array of visit records passed via stdin.
     * @param int    $siteId     Matomo site ID used to locate / write the model file.
     * @return string JSON-encoded array of forecast records.
     * @throws \InvalidArgumentException When JSON or siteId are invalid.
     * @throws \RuntimeException         When the Python binary is missing, the model directory cannot be created,
     *                                   or the process fails.
     */
    public function retrain(string $visitsJson, int $siteId): string
    {
        $settings      = new SystemSettings();
        $pythonBinPath = $settings->pythonBinPath->getValue();

        $modelDir = $this->getModelDir();

        $this->validate($pythonBinPath, $modelDir, $visitsJson, $siteId);

        $modelPath = $modelDir . DIRECTORY_SEPARATOR . $siteId . '.tpl';

        $process = new Process(
            [
                $pythonBinPath,
                self::PYTHON_MAIN_CLI_FILE,
                '--days', (string)ForecastBaseCommand::FORECAST_DAYS,
                '--model-path', $modelPath,
                '--retrain', 'true',
            ],
            null, null, null,
            600
        );

        // Pass JSON safely via stdin — no interpolation into the command line.
        $process->setInput($visitsJson);

        return $this->runProcess($process);
    }

    /**
     * Runs the given process, captures stdout, and converts failures to RuntimeExceptions.
     *
     * @param Process $process Configured but not yet started process.
     * @return string Trimmed stdout output of the process.
     * @throws \RuntimeException On timeout, non-zero exit code, or empty output.
     */
    private function runProcess(Process $process): string
    {
        try {
            $process->mustRun();
        } catch (ProcessTimedOutException $e) {
            throw new \RuntimeException(
                sprintf('Prophet forecast timed out after %d seconds.', $process->getTimeout())
            );
        } catch (ProcessFailedException $e) {
            throw new \RuntimeException(
                sprintf(
                    'Prophet forecast failed (exit code %d). stderr: %s',
                    $process->getExitCode(),
                    trim($process->getErrorOutput()) ?: '(no output)'
                )
            );
        }

        $result = trim($process->getOutput());

        if ($result === '') {
            $stderr = $process->getErrorOutput();
            throw new \RuntimeException(
                'Prophet forecast returned empty output.'
                . ($stderr !== '' ? ' stderr: ' . trim($stderr) : '')
            );
        }

        return $result;
    }

    /**
     * Validates all prerequisites before starting the Prophet process.
     *
     * @param string $pythonBinPath Path to the Python executable.
     * @param string $modelDir      Directory where the model file is stored.
     * @param string $visitsJson    JSON payload to validate.
     * @param int    $siteId        Site ID to validate (must be positive).
     * @return void
     * @throws \InvalidArgumentException When JSON or siteId are invalid.
     * @throws \RuntimeException         When the binary or directory is inaccessible.
     */
    private function validate(string $pythonBinPath, string $modelDir, string $visitsJson, int $siteId): void
    {
        if (!JsonValidator::validate($visitsJson)) {
            throw new \InvalidArgumentException('Invalid visits JSON provided.');
        }

        if ($siteId <= 0) {
            throw new \InvalidArgumentException('Invalid siteId: must be a positive integer.');
        }

        if (empty($pythonBinPath) || !is_executable($pythonBinPath)) {
            throw new \RuntimeException(
                'Python binary is not configured or not executable: ' . $pythonBinPath
            );
        }

        if (!is_dir($modelDir)) {
            if (!mkdir($modelDir, 0700, true) && !is_dir($modelDir)) {
                throw new \RuntimeException(
                    'Model dir directory could not be created: ' . $modelDir
                );
            }
        }
    }

    /**
     * Returns the absolute path to the directory where Prophet model files are stored.
     *
     * @return string Absolute directory path (without trailing separator).
     */
    private function getModelDir(): string
    {
        return realpath(dirname(__DIR__, 4)) . DIRECTORY_SEPARATOR . 'tmp/forecast';
    }
}
