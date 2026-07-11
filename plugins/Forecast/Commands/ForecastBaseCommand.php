<?php

declare(strict_types=1);

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\Forecast\Commands;

use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\Forecast\SystemSettings;
use Symfony\Component\Process\Process;

abstract class ForecastBaseCommand extends ConsoleCommand
{
    public const FORECAST_DAYS = 365;

    /**
     * Checks that Python and all required modules (prophet, pandas, joblib) are available.
     *
     * @return bool True when the environment is ready, false otherwise.
     */
    protected function checkEnvironment(): bool
    {
        $success = true;

        $settings = new SystemSettings();
        $pythonBinPath = $settings->pythonBinPath->getValue();

        // Check python installation.
        $versionProcess = new Process(
            [$pythonBinPath, '--version'],
            null, null, null,
            10
        );
        $versionProcess->run();

        if (!$versionProcess->isSuccessful()) {
            $this->getOutput()->writeln('<error>Python not installed. Please install it!</error>');
            $success = false;
        }

        // Check python dependencies.
        $checkPythonImport = static function (string $pythonBinPath, string $module) use (&$success): bool {
            $process = new Process(
                [$pythonBinPath, '-c', "import $module"],
                null, null, null,
                30
            );
            $process->run();

            return $process->isSuccessful();
        };

        foreach (['prophet', 'pandas', 'joblib'] as $module) {
            if (!$checkPythonImport($pythonBinPath, $module)) {
                $this->getOutput()->writeln(
                    sprintf(
                        '<error>%s not installed. Please install it! Also check python bin path in the forecast plugin settings.</error>',
                        ucfirst($module)
                    )
                );
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Transforms raw visit rows into the JSON format expected by Prophet and fills gaps with zero-visit days.
     *
     * @param array  $visits    Visit rows from the database (each row must have 'date' and 'unique_visits').
     * @param string $startDate Inclusive start date in Y-m-d format.
     * @param string $endDate   Inclusive end date in Y-m-d format.
     * @return string JSON-encoded array of Prophet input records (['ds', 'y']).
     * @throws \Exception
     */
    protected function formatVisitsForProphet(array $visits, string $startDate, string $endDate): string
    {
        $visitsTransformed = array_map(function ($item) {
            return [
                'ds' => $item['date'],
                'y'  => $item['unique_visits'],
            ];
        }, $visits);

        // Fill with missing dates so Prophet receives a contiguous series.
        $startDate = new \DateTime($startDate);
        $endDate   = new \DateTime($endDate);
        $endDate->modify('+1 day');
        $period = new \DatePeriod($startDate, new \DateInterval('P1D'), $endDate);

        foreach ($period as $date) {
            $day = $date->format('Y-m-d');

            if (!isset($visitsTransformed[$day])) {
                $visitsTransformed[$day] = [
                    'ds' => $day,
                    'y'  => 0,
                ];
            }
        }

        ksort($visitsTransformed);

        return json_encode(array_values($visitsTransformed));
    }

    /**
     * Converts a Prophet forecast result array into the JSON format stored in the database.
     *
     * Negative yhat values are clamped to zero and rounded half-up to the nearest integer.
     *
     * @param array $prophetResults Array of Prophet output records (each must have 'ds' and 'yhat').
     * @return string JSON-encoded associative array keyed by date string.
     */
    protected function formatProphetResultForDatabase(array $prophetResults): string
    {
        $resultForDatabase = [];

        foreach ($prophetResults as $prophetResult) {
            $uniqueVisitors = $prophetResult['yhat'] < 0
                ? 0
                : round($prophetResult['yhat'], 0, PHP_ROUND_HALF_UP);

            $resultForDatabase[$prophetResult['ds']] = [
                'label'           => $prophetResult['ds'],
                'nb_uniq_visitors' => $uniqueVisitors,
            ];
        }

        return json_encode($resultForDatabase);
    }
}
