<?php

declare(strict_types=1);

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\Forecast\Widgets;

use Piwik\Piwik;
use Piwik\Plugins\Forecast\Controller;
use Piwik\Widget\Widget;
use Piwik\Widget\WidgetConfig;

class ForecastWidget extends Widget
{
    /**
     * Configures the widget's category and display name.
     *
     * @param WidgetConfig $config Widget configuration object.
     * @return void
     */
    public static function configure(WidgetConfig $config): void
    {
        $config->setCategoryId('General_Visitors');
        $config->setName(Piwik::translate('Forecast_WidgetName'));
    }

    /**
     * Renders the forecast widget HTML.
     *
     * @return string Rendered HTML output.
     * @throws \Exception
     */
    public function render(): string
    {
        return (new Controller())->getRawData();
    }
}
