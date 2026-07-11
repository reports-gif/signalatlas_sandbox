<?php

namespace Piwik\Plugins\LeadFinder;

use Piwik\Menu\MenuMain;

class Menu extends \Piwik\Plugin\Menu
{
    public function configureMenu(MenuMain $menu)
    {
        $menu->addItem(
            'Visitors',          // left sidebar section
            'Lead Finder',       // name
            ['module' => 'LeadFinder', 'action' => 'index'],
            true,
            50
        );
    }
}