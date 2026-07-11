<?php 
/**
 * Plugin Name: Forecast (Matomo Plugin)
 * Plugin URI: http://plugins.matomo.org/Forecast
 * Description: This Matomo plugin adds a future visits forecast based on the prophet time‑series library
 * Author: Menotec
 * Author URI: https://matomo.org
 * Version: 5.7.4
 */
?><?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\Forecast;

use Piwik\Common;
use Piwik\Db;
use Piwik\Plugin;

 
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

class Forecast extends Plugin
{
    /**
     * Returns a map of Matomo events to listener method names.
     *
     * @return array<string, string>
     */
    public function registerEvents(): array
    {
        return [
            'AssetManager.getJavaScriptFiles' => 'getJavaScriptFiles',
        ];
    }

    /**
     * Appends the plugin's JavaScript file to the asset list.
     *
     * @param array $files Reference to the list of JavaScript asset paths.
     * @return void
     */
    public function getJavaScriptFiles(array &$files): void
    {
        $files[] = 'plugins/Forecast/javascripts/customLimits.js';
    }

    /**
     * Called when the plugin is installed.
     *
     * @return void
     * @throws \Exception
     */
    public function install(): void
    {
        $this->createTables();
    }

    /**
     * Called when the plugin is uninstalled.
     *
     * @return void
     * @throws \Exception
     */
    public function uninstall(): void
    {
        $this->removeTables();
    }

    /**
     * Called when the plugin is activated.
     *
     * @return void
     * @throws \Exception
     */
    public function activate(): void
    {
        $this->createTables();
    }

    /**
     * Creates the forecast database table if it does not already exist.
     *
     * @return void
     * @throws \Exception
     */
    private function createTables(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS " . Common::prefixTable('forecast_access_count') . " (
            `access_siteid` INT UNSIGNED NOT NULL,
            `access_data` JSON DEFAULT NULL,
            PRIMARY KEY (`access_siteid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ";

        Db::exec($sql);
    }

    /**
     * Drops the forecast database table.
     *
     * @return void
     * @throws \Exception
     */
    private function removeTables(): void
    {
        $sql = "DROP TABLE IF EXISTS " . Common::prefixTable('forecast_access_count');
        Db::exec($sql);
    }
}
