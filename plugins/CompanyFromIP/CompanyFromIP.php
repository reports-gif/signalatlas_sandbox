<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\CompanyFromIP;

use Piwik\Plugin;

class CompanyFromIP extends Plugin
{
    public function registerEvents(): array
    {
        return [];
    }

    public function install(): void
    {
        $dao = new Dao\CompanyCacheDao();
        $dao->install();
    }

    public function uninstall(): void
    {
        $dao = new Dao\CompanyCacheDao();
        $dao->uninstall();
    }
}
