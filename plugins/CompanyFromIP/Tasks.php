<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\CompanyFromIP;

use Piwik\Container\StaticContainer;
use Piwik\Plugin\Tasks as PluginTasks;

class Tasks extends PluginTasks
{
    public function schedule(): void
    {
        $this->daily('purgeExpiredCache');
    }

    /**
     * Removes cache entries older than the configured TTL.
     * Runs daily to keep the cache table lean.
     */
    public function purgeExpiredCache(): void
    {
        $settings = StaticContainer::get(SystemSettings::class);
        $ttlDays  = (int) $settings->cacheTtlDays->getValue();

        $dao = StaticContainer::get(Dao\CompanyCacheDao::class);
        $dao->deleteExpired($ttlDays);
    }
}
