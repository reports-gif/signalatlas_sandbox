<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\CompanyFromIP\Lookup;

use Piwik\Http;

class IpInfoLookup
{
    private string $apiToken;
    private int $timeoutSeconds;

    public function __construct(string $apiToken, int $timeoutSeconds)
    {
        $this->apiToken       = $apiToken;
        $this->timeoutSeconds = $timeoutSeconds;
    }

    /**
     * Resolve the organization/company name for the given IP address.
     * Returns null if the lookup fails or no org data is available.
     */
    public function lookup(string $ip): ?string
    {
        $url     = 'https://ipinfo.io/' . urlencode($ip) . '/json';
        $headers = [];

        // Pass the token as an Authorization header — keeps it out of access logs
        if (!empty($this->apiToken)) {
            $headers[] = 'Authorization: Bearer ' . $this->apiToken;
        }

        try {
            $response = Http::sendHttpRequest(
                $url,
                $this->timeoutSeconds,
                null,   // userAgent
                null,   // destinationPath
                0,      // followDepth
                false,  // acceptLanguage
                false,  // byteRange
                false,  // getExtendedInfo
                'GET',  // httpMethod
                '',     // httpUsername
                '',     // httpPassword
                null,   // requestBody
                $headers
            );
        } catch (\Exception $e) {
            return null;
        }

        if (empty($response)) {
            return null;
        }

        $data = json_decode($response, true);

        if (!is_array($data)) {
            return null;
        }

        // ipinfo.io sets "bogon": true for private/reserved IP ranges
        if (!empty($data['bogon'])) {
            return null;
        }

        // Paid plans expose 'company.name' directly
        if (is_array($data['company'] ?? null) && !empty($data['company']['name'])) {
            return (string) $data['company']['name'];
        }

        // Free/basic plans expose 'org' as "AS12345 Company Name"
        if (!empty($data['org'])) {
            return $this->cleanOrgName((string) $data['org']);
        }

        return null;
    }

    /**
     * Strip the ASN prefix from an org string.
     * "AS12345 Capgemini France" → "Capgemini France"
     */
    private function cleanOrgName(string $org): string
    {
        $cleaned = preg_replace('/^AS\d+\s+/', '', $org);

        return trim((string) $cleaned);
    }
}
