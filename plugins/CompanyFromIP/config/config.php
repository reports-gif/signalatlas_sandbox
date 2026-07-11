<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

use Piwik\DI;

return [
    \Piwik\Plugins\CompanyFromIP\Lookup\IpInfoLookup::class => DI::factory(
        function (\Piwik\Container\Container $c) {
            $settings = $c->get(\Piwik\Plugins\CompanyFromIP\SystemSettings::class);

            return new \Piwik\Plugins\CompanyFromIP\Lookup\IpInfoLookup(
                (string) $settings->apiToken->getValue(),
                (int)    $settings->requestTimeoutSeconds->getValue()
            );
        }
    ),

    \Piwik\Plugins\CompanyFromIP\CompanyResolver::class => DI::factory(
        function (\Piwik\Container\Container $c) {
            $settings = $c->get(\Piwik\Plugins\CompanyFromIP\SystemSettings::class);

            return new \Piwik\Plugins\CompanyFromIP\CompanyResolver(
                $c->get(\Piwik\Plugins\CompanyFromIP\Dao\CompanyCacheDao::class),
                $c->get(\Piwik\Plugins\CompanyFromIP\Lookup\IpInfoLookup::class),
                (int) $settings->cacheTtlDays->getValue()
            );
        }
    ),
];
