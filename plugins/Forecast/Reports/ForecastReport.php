<?php

declare(strict_types=1);

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\Forecast\Reports;

use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;

class ForecastReport extends Report
{
    /**
     * Initialises report metadata (name, category, module, action, order).
     *
     * @return void
     */
    protected function init(): void
    {
        parent::init();

        $this->name          = Piwik::translate('Forecast_ReportName');
        $this->categoryId    = 'Forecast';
        $this->subcategoryId = 'General_Overview';

        $this->module = 'Forecast';
        $this->action = 'getForecastReport';

        $this->order = 10;
    }

    /**
     * Configures the view for the forecast report.
     *
     * @param ViewDataTable $view The view instance to configure.
     * @return void
     */
    public function configureView(ViewDataTable $view): void
    {
        $view->config->show_search               = false;
        $view->config->show_limit_control        = false;
        $view->config->show_all_views_icons      = false;
        $view->config->show_table_all_columns    = false;
        $view->config->show_exclude_low_population = false;
        $view->config->columns_to_display        = ['label', 'nb_visits'];
        $view->config->addTranslation('nb_visits', Piwik::translate('Forecast_ColumnVisits'));
    }
}
