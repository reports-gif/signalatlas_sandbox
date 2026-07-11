<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\CompanyFromIP;

use Piwik\Plugins\Live\VisitorDetailsAbstract;
use Piwik\View;

class VisitorDetails extends VisitorDetailsAbstract
{
    /**
     * Expose company_name in the visitor details array (used by Live API / export).
     */
    public function extendVisitorDetails(&$visitor)
    {
        $visitor['company'] = isset($this->details['company_name'])
            ? $this->details['company_name']
            : null;
    }

    /**
     * Render a company badge in the left column of each Visit Log row.
     * Returns [[order, html]] — order 25 places it just below the IP/location block.
     */
    public function renderVisitorDetails($visitorDetails)
    {
        $company = isset($visitorDetails['company']) ? $visitorDetails['company'] : null;

        if (empty($company)) {
            return [];
        }

        $view          = new View('@CompanyFromIP/_visitorDetails.twig');
        $view->company = $company;

        return [[25, $view->render()]];
    }
}
