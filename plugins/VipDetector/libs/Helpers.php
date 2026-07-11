<?php

namespace Piwik\Plugins\VipDetector\libs;

use Exception;
use Matomo\Network\IP;
use Matomo\Network\IPUtils;
use Matomo\Network\IPv6;

class Helpers
{
    /**
     * Get the range bounds and IP version of a range in CIDR format
     * @throws Exception
     * @param string $range The range to check
     * @return array <int, string> The range bounds and the IP version
     */
    public static function getRangeInfo(string $range): array
    {
        // Get the type (Ipv4/IPv6) and the first and last address of the subnet
        $rangeBounds = IPUtils::getIPRangeBounds($range);

        if (!$rangeBounds) {
            throw new Exception("Range could not be parsed!");
        }

        // TODO: array_walk
        $from = IPUtils::binaryToStringIP($rangeBounds[0]);
        $to = IPUtils::binaryToStringIP($rangeBounds[1]);

        return [
            'type' => self::getAddressType($from), // We could also do this with the last IP
            'range_from' => $from,
            'range_to' => $to
        ];
    }

    /**
     * Returns the IP Version for a given IP
     * @param string $ip The IP to check
     * @return int IP Version
     */
    public static function getAddressType(string $ip): int
    {
        $ipObj = IP::fromStringIP($ip);

        if ($ipObj instanceof IPv6) {
            return 6;
        }

        return 4;
    }
}
