<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\CompanyFromIP;

use Piwik\Plugins\CompanyFromIP\Dao\CompanyCacheDao;
use Piwik\Plugins\CompanyFromIP\Lookup\IpInfoLookup;

class CompanyResolver
{
    private CompanyCacheDao $cacheDao;
    private IpInfoLookup $lookup;
    private int $cacheTtlDays;

    public function __construct(CompanyCacheDao $cacheDao, IpInfoLookup $lookup, int $cacheTtlDays)
    {
        $this->cacheDao     = $cacheDao;
        $this->lookup       = $lookup;
        $this->cacheTtlDays = $cacheTtlDays;
    }

    /**
     * Resolve a company name for the given IP address.
     *
     * Private/reserved IPs are skipped immediately.
     * Results are cached in the DB to avoid redundant API calls.
     * A null result (unknown company) is also cached to avoid hammering the API.
     */
    public function resolveCompany(string $ip): ?string
    {
        // Skip private and reserved IP ranges — no point calling the API
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        $hash   = hash('sha256', $ip);
        $cached = $this->cacheDao->findByHash($hash);

        if ($cached !== null) {
            try {
                $lookupDate = new \DateTime($cached['lookup_date']);
                $expiryDate = (new \DateTime())->modify('-' . $this->cacheTtlDays . ' days');

                if ($lookupDate > $expiryDate) {
                    // Cache is still fresh — return stored value (may be null = unknown)
                    return $cached['company_name'];
                }
            } catch (\Exception $e) {
                // Malformed date in cache — treat as expired, proceed to fresh lookup
            }
        }

        // Not cached or expired — perform the API lookup
        $companyName = $this->lookup->lookup($ip);
        $this->cacheDao->upsert($hash, $companyName);

        return $companyName;
    }
}
