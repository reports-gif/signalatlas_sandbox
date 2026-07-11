<?php

declare(strict_types=1);

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\Forecast\Commands;

use Piwik\Container\StaticContainer;
use Piwik\Date;
use Piwik\Exception\DI\DependencyException;
use Piwik\Exception\DI\NotFoundException;
use Piwik\Plugins\Forecast\Commands\Repositories\VisitRepository;
use Piwik\Plugins\Forecast\Commands\Services\ForecastCliCommandService;
use Piwik\Plugins\Forecast\Repositories\ForecastRepository;

class ForecastLocalCommand extends ForecastBaseCommand
{
    /** @var ForecastCliCommandService */
    private $forecastCliCommandService;

    /** @var VisitRepository */
    private $visitRepository;

    /** @var ForecastRepository */
    private $forecastRepository;

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function __construct()
    {
        parent::__construct('forecast:local');
        $this->forecastCliCommandService = StaticContainer::get(ForecastCliCommandService::class);
        $this->visitRepository           = StaticContainer::get(VisitRepository::class);
        $this->forecastRepository        = StaticContainer::get(ForecastRepository::class);
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setDescription('Creates the forecast local.');
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * Fetches visit data for each site, runs Prophet locally, and persists the forecast.
     *
     * @return int Command exit code.
     * @throws \Exception
     */
    protected function doExecute(): int
    {
        if (!$this->checkEnvironment()) {
            return self::FAILURE;
        }

        $siteIds = \Piwik\Plugins\SitesManager\API::getInstance()->getAllSitesId();

        $startDate = Date::lastYear()->subDay(1)->toString();
        $endDate   = Date::yesterday()->toString();

        foreach ($siteIds as $siteId) {
            $visitsJson = $this->formatVisitsForProphet(
                $this->visitRepository->fetchDays($startDate, $endDate, $siteId),
                $startDate,
                $endDate
            );

            $prophetResult = $this->forecastCliCommandService->retrain($visitsJson, $siteId);

            $prophetResultDecoded = json_decode($prophetResult, true);
            if ($prophetResultDecoded) {
                $resultForDatabase = $this->formatProphetResultForDatabase($prophetResultDecoded);
                if ($resultForDatabase) {
                    $this->forecastRepository->persist($resultForDatabase, $siteId);
                }
            }
        }

        return self::SUCCESS;
    }
}
