<?php 
/**
 * Plugin Name: Vip Detector (Matomo Plugin)
 * Plugin URI: http://plugins.matomo.org/VipDetector
 * Description: Detect visits from special predefined IP ranges and display the name
 * Author: Sebastian Elisa Pfeifer
 * Author URI: https://github.com/deadda7a/Matomo-VIP-Detector
 * Version: 3.0.3
 */
?><?php

namespace Piwik\Plugins\VipDetector;

use Piwik\Plugin;
use Piwik\Plugins\VipDetector\Dao\DatabaseMethods;

 
if (defined( 'ABSPATH')
&& function_exists('add_action')) {
    $path = '/matomo/app/core/Plugin.php';
    if (defined('WP_PLUGIN_DIR') && WP_PLUGIN_DIR && file_exists(WP_PLUGIN_DIR . $path)) {
        require_once WP_PLUGIN_DIR . $path;
    } elseif (defined('WPMU_PLUGIN_DIR') && WPMU_PLUGIN_DIR && file_exists(WPMU_PLUGIN_DIR . $path)) {
        require_once WPMU_PLUGIN_DIR . $path;
    } else {
        return;
    }
    add_action('plugins_loaded', function () {
        if (function_exists('matomo_add_plugin')) {
            matomo_add_plugin(__DIR__, __FILE__, true);
        }
    });
}

class VipDetector extends Plugin
{
    public function registerEvents(): array
    {
        return [
            'CronArchive.getArchivingAPIMethodForPlugin' => 'getArchivingAPIMethodForPlugin',
        ];
    }

    // support archiving just this plugin via core:archive
    public function getArchivingAPIMethodForPlugin(&$method, $plugin): void
    {
        if ($plugin == 'VipDetector') {
            $method = 'VipDetector.getExampleArchivedMetric';
        }
    }

    public function activate(): void
    {
        DatabaseMethods::createTables();
    }

    public function uninstall(): void
    {
        DatabaseMethods::removeTables();
    }
}
