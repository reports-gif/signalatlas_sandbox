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
use Piwik\Exception\DI\DependencyException;
use Piwik\Exception\DI\NotFoundException;
use Piwik\Plugins\Forecast\Commands\Services\ForecastApiCommandService;
use Piwik\Plugins\Forecast\Repositories\ForecastRepository;
use Piwik\Plugins\Forecast\SystemSettings;

class ForecastRemoteFetchCommand extends ForecastBaseCommand
{
    /** @var ForecastApiCommandService */
    private $forecastApiCommandService;

    /** @var ForecastRepository */
    private $forecastRepository;

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function __construct()
    {
        parent::__construct('forecast:remoteFetch');
        $this->forecastApiCommandService = StaticContainer::get(ForecastApiCommandService::class);
        $this->forecastRepository        = StaticContainer::get(ForecastRepository::class);
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setDescription('Fetch the forecast remote.');
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * Retrieves the forecast from the remote API and persists it for each site.
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

        foreach ($siteIds as $siteId) {
            $response = $this->forecastApiCommandService->fetch($siteId);

            $responseDecoded = json_decode($response, true);
            if ($responseDecoded) {
                if (isset($responseDecoded['error'])) {
                    $this->getOutput()->writeln($responseDecoded['error']);
                    return self::FAILURE;
                }

                $resultForDatabase = $this->formatProphetResultForDatabase($responseDecoded);
                if ($resultForDatabase) {
                    $this->forecastRepository->persist($resultForDatabase, $siteId);
                }
            }
        }

        return self::SUCCESS;
    }
}
