<?php

namespace Piwik\Plugins\VipDetector;

use Exception;
use Piwik\Common;
use Piwik\View;
use Piwik\Plugins\Live\VisitorDetailsAbstract;
use Piwik\Plugins\VipDetector\Dao\DatabaseMethods;

class VisitorDetails extends VisitorDetailsAbstract
{
    /**
     * Extend the visitor details instead of the renderer
     * @throws Exception
     * @param array &$visitor The visitor detail array
     */
    public function extendVisitorDetails(&$visitor): void
    {
        try {
            $name = DatabaseMethods::getNameFromIp($visitor['visitIp']);
        } catch (Exception $ex) {
            $visitor['vip_name'] = '';
            return;
        }

        $visitor['vip_name'] = Common::sanitizeInputValues($name);
    }

    /**
     * Render the template with the details
     * @param $visitorDetails
     * @return array<int, array<int, int|string>> The rendered view
     */
    public function renderVisitorDetails($visitorDetails): array
    {
        // Render the template
        $view = new View('@VipDetector/vip');
        $view->vipName = $visitorDetails['vip_name'];
        $view->vipUrl = $this->getVipUrl($visitorDetails['vip_name']);

        return [[30, $view->render()]];
    }

    /**
     * Generates an address the frontend can link to.
     * At the moment this is just a link to DuckDuckGo
     *
     * @param string $vip The name of the VIP
     * @return string The url to link to
     */
    private function getVipUrl(string $vip): string
    {
        return sprintf("https://duckduckgo.com/?q=%s", urlencode($vip));
    }
}
