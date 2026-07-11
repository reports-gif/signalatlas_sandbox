<?php

declare(strict_types=1);

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\Forecast;

use Piwik\Container\StaticContainer;
use Piwik\DataTable;
use Piwik\DataTable\Row;
use Piwik\Exception\DI\DependencyException;
use Piwik\Exception\DI\NotFoundException;
use Piwik\Piwik;
use Piwik\Plugins\Forecast\Repositories\ForecastRepository;
use Piwik\Request;
use Piwik\ViewDataTable\Factory;

class Controller extends \Piwik\Plugin\Controller
{
    /** @var ForecastRepository */
    private $forecastRepository;

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function __construct()
    {
        parent::__construct();
        $this->forecastRepository = StaticContainer::get(ForecastRepository::class);
    }

    /**
     * Renders the forecast evolution graph widget.
     *
     * @return string
     * @throws \Exception
     */
    public function getRawData(): string
    {
        $request = Request::fromRequest();
        $idSite = (int)$request->getParameter('idSite', 1);

        Piwik::checkUserHasViewAccess($idSite);

        $period = $request->getParameter('period', 'day');
        $dateTill = $this->calculateDateTill($period, $request);

        $view = Factory::build('graphEvolution', 'Forecast.getRawData');
        $view->config->columns_to_display = ['nb_uniq_visitors'];
        $view->config->translations['nb_uniq_visitors'] = Piwik::translate('Forecast_ColumnUniqueVisitors');
        $view->config->enable_sort = false;
        $view->config->hide_annotations_view = true;

        $view->setDataTable($this->getData($dateTill->format('Y-m-d'), $idSite));

        return $view->render();
    }

    /**
     * Builds a DataTable from stored forecast data filtered up to the given date.
     *
     * @param string $dateTill Upper-bound date in Y-m-d format.
     * @param int    $siteId   Matomo site ID.
     * @return DataTable
     * @throws \Exception
     */
    public function getData(string $dateTill, int $siteId): DataTable
    {
        $dataTable = new DataTable();

        $result = $this->forecastRepository->fetchBySiteId($siteId);
        if (empty($result)) {
            return $dataTable;
        }

        $filteredData = array_filter(
            json_decode($result, true),
            static function (string $date) use ($dateTill): bool {
                return $date <= $dateTill;
            },
            ARRAY_FILTER_USE_KEY
        );

        foreach ($filteredData as $filteredDataRow) {
            $dataTable->addRow(new Row([
                Row::COLUMNS => $filteredDataRow
            ]));
        }

        return $dataTable;
    }

    /**
     * Calculates the upper-bound forecast date based on period and request params.
     *
     * @param string  $period  Supported: 'day', 'month'
     * @param Request $request Current HTTP request.
     * @return \DateTime
     * @throws \InvalidArgumentException When an unsupported period is given.
     * @throws \Exception
     */
    private function calculateDateTill(string $period, Request $request): \DateTime
    {
        $now = new \DateTime();

        switch ($period) {
            case 'day':
                return (clone $now)->modify(
                    sprintf('+%d days', (int)$request->getParameter('evolution_day_last_n', 8))
                );
            case 'month':
                return (clone $now)->modify(
                    sprintf('+%d months', (int)$request->getParameter('evolution_month_last_n', 3))
                );
            default:
                throw new \InvalidArgumentException(
                    sprintf('Unsupported period "%s". Allowed: day, month.', $period)
                );
        }
    }
}
