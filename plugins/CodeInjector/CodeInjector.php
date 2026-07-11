<?php 
/**
 * Plugin Name: Code Injector (Matomo Plugin)
 * Plugin URI: http://plugins.matomo.org/CodeInjector
 * Description: Inject CSS and JS code to your Matomo instance
 * Author: Openmost
 * Author URI: https://openmost.io/products/code-injector/
 * Version: 5.0.10
 */
?><?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\CodeInjector;

 
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

class CodeInjector extends \Piwik\Plugin
{

    public function registerEvents()
    {
        return array(
            'Template.bodyTop' => 'addCodeToBodyTop',
            'Template.bodyBottom' => 'addCodeToBodyBottom'
        );
    }

    public function addCodeToBodyTop()
    {
        $settings = new SystemSettings();
        $bodyTop = $settings->bodyTop->getValue();
        echo $bodyTop;
    }

    public function addCodeToBodyBottom()
    {
        $settings = new SystemSettings();
        $bodyBottom = $settings->bodyBottom->getValue();
        echo $bodyBottom;
    }

}
