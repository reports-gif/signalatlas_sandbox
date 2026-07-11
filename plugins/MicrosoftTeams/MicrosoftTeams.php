<?php 
/**
 * Plugin Name: Microsoft Teams (Matomo Plugin)
 * Plugin URI: http://plugins.matomo.org/MicrosoftTeams
 * Description: Send Matomo reports and alerts to Microsoft Team channels, keeping your team informed and ready to act in real time.
 * Author: Matomo
 * Author URI: https://github.com/matomo-org/plugin-MicrosoftTeams
 * Version: 5.1.0
 */
?><?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\MicrosoftTeams;

use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Db;
use Piwik\Log\LoggerInterface;
use Piwik\Option;
use Piwik\Period;
use Piwik\Piwik;
use Piwik\Plugins\ScheduledReports\ScheduledReports;
use Piwik\ReportRenderer;
use Piwik\SettingsPiwik;
use Piwik\UrlHelper;
use Piwik\View;

 
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

class MicrosoftTeams extends \Piwik\Plugin
{
    public const MS_TEAMS_INCOMING_WEBHOOK_URL_PARAMETER = 'msTeamsWebhookUrl';
    public const MS_TEAMS_TYPE = 'teams';
    private static $availableParameters = [
        self::MS_TEAMS_INCOMING_WEBHOOK_URL_PARAMETER => true,
        ScheduledReports::EVOLUTION_GRAPH_PARAMETER => false,
        ScheduledReports::DISPLAY_FORMAT_PARAMETER => true,
    ];

    private static $managedReportTypes = [
        self::MS_TEAMS_TYPE => 'plugins/MicrosoftTeams/images/teams.png',
    ];

    private static $managedReportFormats = array(
        ReportRenderer::PDF_FORMAT => 'plugins/Morpheus/icons/dist/plugins/pdf.png',
        ReportRenderer::CSV_FORMAT => 'plugins/Morpheus/images/export.png',
        ReportRenderer::TSV_FORMAT => 'plugins/Morpheus/images/export.png',
    );

    public function registerEvents()
    {
        return [
            'ScheduledReports.getReportParameters' => 'getReportParameters',
            'ScheduledReports.validateReportParameters' => 'validateReportParameters',
            'ScheduledReports.getReportMetadata' => 'getReportMetadata',
            'ScheduledReports.getReportTypes' => 'getReportTypes',
            'ScheduledReports.getReportFormats' => 'getReportFormats',
            'ScheduledReports.getRendererInstance' => 'getRendererInstance',
            'ScheduledReports.getReportRecipients' => 'getReportRecipients',
            'ScheduledReports.processReports' => 'processReports',
            'ScheduledReports.allowMultipleReports' => 'allowMultipleReports',
            'ScheduledReports.sendReport' => 'sendReport',
            'Template.reportParametersScheduledReports' => 'templateReportParametersScheduledReports',
            'Translate.getClientSideTranslationKeys' => 'getClientSideTranslationKeys',
            'CustomAlerts.validateReportParameters' => 'validateCustomAlertReportParameters',
            'CustomAlerts.sendNewAlerts' => 'sendNewAlerts',
            'AssetManager.getJavaScriptFiles' => 'getJsFiles',
            'Template.jsGlobalVariables' => 'addJsGlobalVariables',
        ];
    }


    public function requiresInternetConnection()
    {
        return true;
    }

    public function getJsFiles(&$jsFiles)
    {
        $jsFiles[] = "plugins/MicrosoftTeams/javascripts/alertNotification.js";
    }

    public function addJsGlobalVariables(&$out)
    {
        $request = \Piwik\Request::fromRequest();
        $module = $request->getParameter('module', '');
        $action  = $request->getParameter('action', '');
        $shouldShowNotification = false;
        $login = Piwik::getCurrentUserLogin();
        $idSite = $request->getParameter('idSite', '');
        if ($module === 'ScheduledReports' && $action === 'index' && $idSite) {
            $table = Common::prefixTable('report');
            $sql = "SELECT count(type) FROM `$table` WHERE type = ? AND idsite = ? AND deleted = 0 AND login = ?"
                . " AND parameters NOT LIKE ?";
            $bind = [self::MS_TEAMS_TYPE, $idSite, $login, '%powerautomate%'];
            $result = Db::fetchOne($sql, $bind);
            $shouldShowNotification = !empty($result);
        } elseif ($module === 'CustomAlerts' && $action === 'index') {
            $table = Common::prefixTable('alert');
            $sql = "SELECT count(idalert) FROM `$table` WHERE login = ? AND ms_teams_webhook_url NOT LIKE ?"
                . " AND report_mediums LIKE ?";
            $bind = [$login, '%powerautomate%', '%teams%'];
            $result = Db::fetchOne($sql, $bind);
            $shouldShowNotification = !empty($result);
        }

        $out .= 'var msTeamsShouldShowWebhookNotification = ' . json_encode($shouldShowNotification) . ';';
        $out .= 'var msTeamsAlertModule = ' . json_encode($module) . ';';
    }

    public function getClientSideTranslationKeys(&$translationKeys)
    {
        $translationKeys[] = 'MicrosoftTeams_RequiredFieldsNotSet';
        $translationKeys[] = 'MicrosoftTeams_IncomingWebhookRequiredErrorMessage';
        $translationKeys[] = 'MicrosoftTeams_TeamsWebhookUrl';
        $translationKeys[] = 'MicrosoftTeams_ClientIdTitle';
        $translationKeys[] = 'MicrosoftTeams_ClientIdDescription';
        $translationKeys[] = 'MicrosoftTeams_ClientSecretTitle';
        $translationKeys[] = 'MicrosoftTeams_ClientSecretDescription';
        $translationKeys[] = 'MicrosoftTeams_TenantIdTitle';
        $translationKeys[] = 'MicrosoftTeams_TenantIdDescription';
        $translationKeys[] = 'MicrosoftTeams_TeamsEnterYourWebhookUrlText';
        $translationKeys[] = 'MicrosoftTeams_MicrosoftTeamsWebhookUrlDeprecatedNoticeText';
        $translationKeys[] = 'MicrosoftTeams_MicrosoftTeamsWebhookUrlDeprecatedNoticeTextCustomAlerts';
    }

    /**
     *
     * Adds report parameter for MicrosoftTeams, e.g. teamWebhookURL
     *
     * @param $availableParameters
     * @param $reportType
     * @return void
     */
    public function getReportParameters(&$availableParameters, $reportType)
    {
        if (self::isMSTeamsEvent($reportType)) {
            $availableParameters = self::$availableParameters;
        }
    }

    /**
     *
     * Validates the Schedule Report for MicrosoftTeams reportType
     *
     * @param $parameters
     * @param $reportType
     * @return void
     * @throws \Piwik\Exception\DI\DependencyException
     * @throws \Piwik\Exception\DI\NotFoundException
     */
    public function validateReportParameters(&$parameters, $reportType)
    {
        if (!self::isMSTeamsEvent($reportType)) {
            return;
        }

        $reportFormat = $parameters[ScheduledReports::DISPLAY_FORMAT_PARAMETER];
        $availableDisplayFormats = array_keys(ScheduledReports::getDisplayFormats());
        if (!in_array($reportFormat, $availableDisplayFormats)) {
            throw new \Exception(
                Piwik::translate(
                    'General_ExceptionInvalidAggregateReportsFormat',
                    array($reportFormat, implode(', ', $availableDisplayFormats))
                )
            );
        }

        // evolutionGraph is an optional parameter
        if (!isset($parameters[ScheduledReports::EVOLUTION_GRAPH_PARAMETER])) {
            $parameters[ScheduledReports::EVOLUTION_GRAPH_PARAMETER] = ScheduledReports::EVOLUTION_GRAPH_PARAMETER_DEFAULT_VALUE;
        } else {
            $parameters[ScheduledReports::EVOLUTION_GRAPH_PARAMETER] = self::valueIsTrue($parameters[ScheduledReports::EVOLUTION_GRAPH_PARAMETER]);
        }

        $settings = StaticContainer::get(SystemSettings::class);
        if (!$settings->isRequiredFieldsSet()) {
            throw new \Exception(Piwik::translate('MicrosoftTeams_RequiredFieldsNotSet'));
        } elseif (empty($parameters[self::MS_TEAMS_INCOMING_WEBHOOK_URL_PARAMETER])) {
            throw new \Exception(Piwik::translate('MicrosoftTeams_IncomingWebhookRequiredErrorMessage'));
        } elseif (!UrlHelper::isLookLikeUrl($parameters[self::MS_TEAMS_INCOMING_WEBHOOK_URL_PARAMETER])) {
            throw new \Exception(Piwik::translate('MicrosoftTeams_IncomingWebhookInvalidErrorMessage'));
        } elseif ($this->isIpHost(parse_url($parameters[self::MS_TEAMS_INCOMING_WEBHOOK_URL_PARAMETER], PHP_URL_HOST))) {
            throw new \Exception(Piwik::translate('MicrosoftTeams_IncomingWebhookInvalidErrorMessage'));
        }

        $parameters[self::MS_TEAMS_INCOMING_WEBHOOK_URL_PARAMETER] = htmlspecialchars_decode($parameters[self::MS_TEAMS_INCOMING_WEBHOOK_URL_PARAMETER]);
    }

    /**
     *
     * Adds MicrosoftTeams as a reportType in Schedule Reports
     *
     * @param $reportTypes
     * @return void
     */
    public function getReportTypes(&$reportTypes)
    {
        $reportTypes = array_merge($reportTypes, self::$managedReportTypes);
    }

    /**
     *
     * Adds allowed reportTypes for MicrosoftTeams, e.g. PDF, CSV and TSV
     *
     * @param $reportFormats
     * @param $reportType
     * @return void
     */
    public function getReportFormats(&$reportFormats, $reportType)
    {
        if (self::isMSTeamsEvent($reportType)) {
            $reportFormats = array_merge($reportFormats, self::$managedReportFormats);
        }
    }

    /**
     *
     * To allow multiple reports in a single file
     *
     * @param $allowMultipleReports
     * @param $reportType
     * @return void
     */
    public function allowMultipleReports(&$allowMultipleReports, $reportType)
    {
        if (self::isMSTeamsEvent($reportType)) {
            $allowMultipleReports = true;
        }
    }

    /**
     *
     * Get report metadata for MicrosoftTeams scheduled report
     *
     * @param $availableReportMetadata
     * @param $reportType
     * @param $idSite
     * @return void
     */
    public function getReportMetadata(&$availableReportMetadata, $reportType, $idSite)
    {
        if (! self::isMSTeamsEvent($reportType)) {
            return;
        }

        // Use same metadata as E-mail report from ScheduledReports plugin
        Piwik::postEvent(
            'ScheduledReports.getReportMetadata',
            [&$availableReportMetadata, ScheduledReports::EMAIL_TYPE, $idSite]
        );
    }

    /**
     *
     * Displays the recipients in the list of Schedule Reports
     *
     * @param $recipients
     * @param $reportType
     * @param $report
     * @return void
     */
    public function getReportRecipients(&$recipients, $reportType, $report)
    {
        if (!self::isMSTeamsEvent($reportType) || empty($report['parameters'][self::MS_TEAMS_INCOMING_WEBHOOK_URL_PARAMETER])) {
            return;
        }

        $recipients = [Piwik::translate('MicrosoftTeams_TeamsChannel')];
    }

    /**
     *
     * Process the Schedule report for reportType MicrosoftTeams
     *
     * @param $processedReports
     * @param $reportType
     * @param $outputType
     * @param $report
     * @return void
     */
    public function processReports(&$processedReports, $reportType, $outputType, $report)
    {
        if (! self::isMSTeamsEvent($reportType)) {
            return;
        }

        // Use same metadata as E-mail report from ScheduledReports plugin
        Piwik::postEvent(
            'ScheduledReports.processReports',
            [&$processedReports, ScheduledReports::EMAIL_TYPE, $outputType, $report]
        );
    }

    /**
     *
     * Sets the rendered instance based on reportFormat for MicrosoftTeams
     *
     * @param $reportRenderer
     * @param $reportType
     * @param $outputType
     * @param $report
     * @return void
     * @throws \Exception
     */
    public function getRendererInstance(&$reportRenderer, $reportType, $outputType, $report)
    {
        if (! self::isMSTeamsEvent($reportType)) {
            return;
        }

        $reportFormat = $report['format'];

        $reportRenderer = ReportRenderer::factory($reportFormat);
    }

    /**
     *
     * Add the view template for MicrosoftTeams report parameters
     *
     * @param $out
     * @param $context
     * @return void
     * @throws \Piwik\Exception\DI\DependencyException
     * @throws \Piwik\Exception\DI\NotFoundException
     */
    public function templateReportParametersScheduledReports(&$out, $context = '')
    {
        if (Piwik::isUserIsAnonymous()) {
            return;
        }

        $view = new View('@MicrosoftTeams/reportParametersScheduledReports');
        $view->reportType = self::MS_TEAMS_TYPE;
        $view->context = $context;

        $settings = StaticContainer::get(SystemSettings::class);
        $view->isRequiredFieldsSet = !empty($settings->isRequiredFieldsSet());
        $view->defaultDisplayFormat = ScheduledReports::DEFAULT_DISPLAY_FORMAT;
        $view->defaultFormat = ReportRenderer::PDF_FORMAT;
        $view->defaultEvolutionGraph = ScheduledReports::EVOLUTION_GRAPH_PARAMETER_DEFAULT_VALUE;
        $out .= $view->render();
    }

    /**
     *
     * Code to send a Schedule Report via MicrosoftTeams
     * @param $reportType
     * @param $report
     * @param $contents
     * @param $filename
     * @param $prettyDate
     * @param $reportSubject
     * @param $reportTitle
     * @param $additionalFiles
     * @param Period|null $period
     * @param $force
     * @return void
     * @throws \Piwik\Exception\DI\DependencyException
     * @throws \Piwik\Exception\DI\NotFoundException
     */
    public function sendReport(
        $reportType,
        $report,
        $contents,
        $filename,
        $prettyDate,
        $reportSubject,
        $reportTitle,
        $additionalFiles,
        $period,
        $force
    ) {
        if (! self::isMSTeamsEvent($reportType)) {
            return;
        }
        $logger = StaticContainer::get(LoggerInterface::class);
        // Safeguard against sending the same report twice to the same Teams channel (unless $force is true)
        if (!$force && $this->reportAlreadySent($report, $period)) {
            $logger->warning(
                sprintf('Preventing the same scheduled report from being sent again (report #%s for period "%s")', $report['idreport'], $report['period'])
            );
            return;
        }

        $settings = StaticContainer::get(SystemSettings::class);
        if (!$settings->isRequiredFieldsSet()) {
            $logger->error('Microsoft Teams required fields not set.');
            return;
        }

        $periods = ScheduledReports::getPeriodToFrequency();
        $subject = Piwik::translate('MicrosoftTeams_PleaseFindYourReport', [$periods[$report['period']], $reportSubject]);
        $webhookUrl = $report['parameters'][self::MS_TEAMS_INCOMING_WEBHOOK_URL_PARAMETER];
        $requiredFields = $settings->getRequiredFieldsWithValue();
        $scheduleReportMsTeams = new ScheduleReportMicrosoftTeams($subject, $filename, $contents, $webhookUrl, $requiredFields);
        if ($scheduleReportMsTeams->send() && !$force) {
            $this->markReportAsSent($report, $period);
        }
    }

    /**
     *
     * Validation check for CustomAlert report parameters
     *
     * @param $parameters
     * @param $alertMedium
     * @return void
     * @throws \Exception
     */
    public function validateCustomAlertReportParameters($parameters, $alertMedium)
    {
        if ($alertMedium === self::MS_TEAMS_TYPE) {
            if (empty($parameters[self::MS_TEAMS_INCOMING_WEBHOOK_URL_PARAMETER])) {
                throw new \Exception(Piwik::translate('MicrosoftTeams_IncomingWebhookRequiredErrorMessage'));
            } elseif (!UrlHelper::isLookLikeUrl($parameters[self::MS_TEAMS_INCOMING_WEBHOOK_URL_PARAMETER])) {
                throw new \Exception(Piwik::translate('MicrosoftTeams_IncomingWebhookInvalidErrorMessage'));
            } elseif ($this->isIpHost(parse_url($parameters[self::MS_TEAMS_INCOMING_WEBHOOK_URL_PARAMETER], PHP_URL_HOST))) {
                throw new \Exception(Piwik::translate('MicrosoftTeams_IncomingWebhookInvalidErrorMessage'));
            }

            $parameters[self::MS_TEAMS_INCOMING_WEBHOOK_URL_PARAMETER] = htmlspecialchars_decode($parameters[self::MS_TEAMS_INCOMING_WEBHOOK_URL_PARAMETER]);
        }
    }

    private function isIpHost(?string $host): bool
    {
        if (empty($host)) {
            return false;
        }

        $host = trim($host, '[]');

        return filter_var($host, FILTER_VALIDATE_IP) !== false || ctype_digit($host);
    }

    /**
     *
     * Code to send CustomAlerts via MicrosoftTeams
     *
     * @param $triggeredAlerts
     * @return void
     * @throws \Piwik\Exception\DI\DependencyException
     * @throws \Piwik\Exception\DI\NotFoundException
     */
    public function sendNewAlerts($triggeredAlerts): void
    {
        if (!empty($triggeredAlerts)) {
            $enrichTriggerAlerts = StaticContainer::get(EnrichTriggeredAlerts::class);
            $triggeredAlerts = $enrichTriggerAlerts->enrichTriggeredAlerts($triggeredAlerts);
            $groupedAlerts = $this->groupAlertsByChannelId($triggeredAlerts);
            foreach ($groupedAlerts as $msTeamsWebhookUrl => $alert) {
                $msTeamsApi = new MicrosoftTeamsApi($msTeamsWebhookUrl);
                if (!$msTeamsApi->sendMessageToTeamsChannel(implode("<br>", $alert['message']))) {
                    $logger = StaticContainer::get(LoggerInterface::class);
                    $logger->info('MicrosoftTeams alert failed for following alerts: ' . implode("\n", $alert['name']));
                }
            }
        }
    }

    /**
     *
     * Group alerts by msTeamsWebhookUrl to reduce number of network calls for multiple alerts
     *
     * @param array $alerts
     * @return array
     */
    private function groupAlertsByChannelId(array $alerts): array
    {
        $groupedAlerts = [];
        foreach ($alerts as $alert) {
            if (!in_array(self::MS_TEAMS_TYPE, $alert['report_mediums']) || empty($alert['ms_teams_webhook_url'])) {
                continue;
            }
            $metric = !empty($alert['reportMetric']) ? $alert['reportMetric'] : $alert['metric'];
            $reportName = !empty($alert['reportName']) ? $alert['reportName'] : $alert['report'];
            $groupedAlerts[$alert['ms_teams_webhook_url']]['message'][] = $this->getAlertMessage($alert, $metric, $reportName);
            $groupedAlerts[$alert['ms_teams_webhook_url']]['name'][] = $alert['name'];
        }

        return $groupedAlerts;
    }


    /**
     *
     * Returns the alert message to send via MicrosoftTeams
     *
     * @param array $alert
     * @param string $metric
     * @param string $reportName
     * @return string
     */
    public function getAlertMessage(array $alert, string $metric, string $reportName): string
    {
        $settingURL = SettingsPiwik::getPiwikUrl();
        if (stripos($settingURL, 'index.php') === false) {
            $settingURL .= 'index.php';
        }
        $settingURL .= '?idSite=' . $alert['idsite'];
        $siteName = htmlspecialchars($alert['siteName'], ENT_QUOTES);
        $siteWithLink = "<a href='$settingURL'>$siteName</a>";
        return Piwik::translate('MicrosoftTeams_MicrosoftTeamsAlertContent', [$alert['name'], $siteWithLink, $metric, $reportName, $this->transformAlertCondition($alert)]);
    }

    /**
     *
     * Transform the alert condition to text
     *
     * @param array $alert
     * @return string
     */
    private function transformAlertCondition(array $alert): string
    {
        switch ($alert['metric_condition']) {
            case 'less_than':
                return Piwik::translate('CustomAlerts_ValueIsLessThan', [$alert['metric_matched'], $alert['value_new']]);
            case 'greater_than':
                return Piwik::translate('CustomAlerts_ValueIsGreaterThan', [$alert['metric_matched'], $alert['value_new']]);
            case 'decrease_more_than':
                return Piwik::translate('CustomAlerts_ValueDecreasedMoreThan', [$alert['metric_matched'], $alert['value_old'] ?? '-', $alert['value_new']]);
            case 'increase_more_than':
                return Piwik::translate('CustomAlerts_ValueIncreasedMoreThan', [$alert['metric_matched'], $alert['value_old'] ?? '-', $alert['value_new']]);
            case 'percentage_decrease_more_than':
                return Piwik::translate('CustomAlerts_ValuePercentageDecreasedMoreThan', [$alert['metric_matched'], $alert['value_old'] ?? '-', $alert['value_new']]);
            case 'percentage_increase_more_than':
                return Piwik::translate('CustomAlerts_ValuePercentageIncreasedMoreThan', [$alert['metric_matched'], $alert['value_old'] ?? '-', $alert['value_new']]);
        }

        return '';
    }

    private static function isMSTeamsEvent($reportType): bool
    {
        return in_array($reportType, array_keys(self::$managedReportTypes));
    }

    private function reportAlreadySent($report, Period $period)
    {
        $key = ScheduledReports::OPTION_KEY_LAST_SENT_DATERANGE . $report['idreport'];

        $previousDate = Option::get($key);

        return $previousDate === $period->getRangeString();
    }

    private static function valueIsTrue($value)
    {
        return $value == 'true' || $value == 1 || $value == '1' || $value === true;
    }

    private function markReportAsSent($report, Period $period)
    {
        $key = ScheduledReports::OPTION_KEY_LAST_SENT_DATERANGE . $report['idreport'];

        Option::set($key, $period->getRangeString());
    }
}
