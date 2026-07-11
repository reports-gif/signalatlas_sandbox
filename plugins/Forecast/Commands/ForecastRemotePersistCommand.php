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
use Piwik\Plugins\Forecast\Commands\Services\ForecastApiCommandService;
use Piwik\Plugins\Forecast\SystemSettings;

class ForecastRemotePersistCommand extends ForecastBaseCommand
{
    /** @var ForecastApiCommandService */
    private $forecastApiCommandService;

    /** @var VisitRepository */
    private $visitRepository;

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function __construct()
    {
        parent::__construct('forecast:remotePersist');
        $this->forecastApiCommandService = StaticContainer::get(ForecastApiCommandService::class);
        $this->visitRepository           = StaticContainer::get(VisitRepository::class);
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setDescription('Persists the forecast remote.');
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * Sends historical visit data for each site to the remote forecast API.
     *
     * @return int Command exit code.
     * @throws \Exception
     */
    protected function doExecute(): int
    {
        $siteIds = \Piwik\Plugins\SitesManager\API::getInstance()->getAllSitesId();

        $settings = new SystemSettings();
        if (!$settings->apiKey->getValue() || !$settings->apiHostname->getValue()) {
            $this->getOutput()->writeln('Check if API key and API hostname is set!');
            return self::FAILURE;
        }

        $startDate = Date::lastYear()->subDay(1)->toString();
        $endDate   = Date::yesterday()->toString();

        foreach ($siteIds as $siteId) {
            $visitsJson = $this->formatVisitsForProphet(
                $this->visitRepository->fetchDays($startDate, $endDate, $siteId),
                $startDate,
                $endDate
            );

            $response = $this->forecastApiCommandService->persist($visitsJson, $siteId);

            $responseDecoded = json_decode($response, true);
            if (isset($responseDecoded['error'])) {
                $this->getOutput()->writeln($responseDecoded['error']);
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
