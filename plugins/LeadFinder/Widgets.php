<?php

namespace Piwik\Plugins\LeadFinder;

use Piwik\WidgetsList;

class Widgets extends \Piwik\Plugin\Widgets
{
    public function registerWidgets(WidgetsList $widgetsList)
    {
        $widgetsList->addWidget(
            'Lead Finder',
            'Find Directors',
            'LeadFinder',
            'renderWidget'
        );
    }

    public function renderWidget()
    {
        return $this->renderTemplate('widget');
    }
}