<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\CompanyFromIP\Reports;

use Piwik\Piwik;
use Piwik\Plugin\Report;
use Piwik\Plugin\ViewDataTable;
use Piwik\Report\ReportWidgetFactory;
use Piwik\Widget\WidgetsList;

class GetCompanyVisits extends Report
{
    protected function init(): void
    {
        parent::init();

        $this->categoryId    = 'General_Visitors';
        $this->subcategoryId = 'CompanyFromIP_Companies';
        $this->name          = Piwik::translate('CompanyFromIP_ReportName');
        $this->documentation = Piwik::translate('CompanyFromIP_ReportDocumentation');

        // Required for Matomo report rendering.
        $this->dimension = new \Piwik\Plugins\CompanyFromIP\Columns\CompanyName();

        $this->metrics = [
            'nb_visits',
            'nb_uniq_visitors',
            'nb_actions'
        ];

        $this->processedMetrics = [];
        $this->hasGoalMetrics = false;
        $this->order = 10;
    }

    public function configureWidgets(WidgetsList $widgetsList, ReportWidgetFactory $factory): void
    {
        $widgetsList->addWidgetConfig(
            $factory->createWidget()
                ->setName('CompanyFromIP_WidgetTitle')
                ->setOrder(10)
        );
    }

    public function configureView(ViewDataTable $view): void
    {
        $view->config->show_search = true;
        $view->config->show_limit_control = true;

        // Disable row evolution because this report reads directly from log_visit.
        $view->config->disable_row_evolution = true;

        $view->config->columns_to_display = [
                'label',
                'nb_visits',
                'nb_uniq_visitors',
                'nb_actions'
            ];

        $view->config->title = Piwik::translate('CompanyFromIP_ReportName');
    }
}