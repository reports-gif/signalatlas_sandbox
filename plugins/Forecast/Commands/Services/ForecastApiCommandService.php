<?php

declare(strict_types=1);

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\Forecast\Commands\Services;

use Piwik\Log\LoggerInterface;
use Piwik\Plugins\Forecast\Commands\ForecastBaseCommand;
use Piwik\Plugins\Forecast\Commands\Services\Validator\JsonValidator;
use Piwik\Plugins\Forecast\SystemSettings;
use RuntimeException;

class ForecastApiCommandService
{
    private const CURL_TIMEOUT_SECONDS     = 50;
    private const CURL_CONNECT_TIMEOUT_SEC = 20;
    private const HTTP_OK_MIN              = 200;
    private const HTTP_OK_MAX              = 299;

    /** @var string */
    private $apiHostname;

    /** @var string */
    private $apiHostnameHost;

    /** @var string */
    private $apiKey;

    /** @var LoggerInterface */
    private $logger;

    /**
     * @param SystemSettings  $settings Plugin system settings providing API credentials and hostname.
     * @param LoggerInterface $logger   PSR-3 compatible logger.
     */
    public function __construct(
        SystemSettings  $settings,
        LoggerInterface $logger
    ) {
        $this->apiHostname     = rtrim($settings->apiHostname->getValue(), '/');
        $this->apiHostnameHost = (string)parse_url($this->apiHostname, PHP_URL_HOST);
        $this->apiKey          = $settings->apiKey->getValue();
        $this->logger          = $logger;
    }

    /**
     * Fetches forecast data for the given site from the remote API.
     *
     * @param int $siteId Matomo site ID.
     * @return string JSON-encoded forecast response body.
     * @throws RuntimeException On cURL error or non-2xx HTTP status.
     */
    public function fetch(int $siteId): string
    {
        $url = "{$this->apiHostname}/index.php/fetch/{$siteId}";

        $this->logger->debug('Fetching forecast for site {siteId} from {url}', [
            'siteId' => $siteId,
            'url'    => $url,
        ]);

        return $this->executeRequest('GET', $url, [
            'Accept' => 'application/json',
        ]);
    }

    /**
     * Sends historical visit data for the given site to the remote API for persistence.
     *
     * @param string $visitsJson JSON-encoded array of visit records.
     * @param int    $siteId     Matomo site ID.
     * @return string JSON-encoded API response body.
     * @throws RuntimeException On invalid payload, invalid hostname, cURL error, or non-2xx HTTP status.
     */
    public function persist(string $visitsJson, int $siteId): string
    {
        if (empty($visitsJson) || !JsonValidator::validate($visitsJson)) {
            throw new RuntimeException('Invalid JSON payload provided for persist request.');
        }

        if (!filter_var($this->apiHostname, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Invalid hostname.');
        }

        $days = ForecastBaseCommand::FORECAST_DAYS;
        $url  = "{$this->apiHostname}/index.php/persist/{$siteId}/{$days}";

        $this->logger->debug('Persisting forecast data for site {siteId} to {url}', [
            'siteId' => $siteId,
            'url'    => $url,
        ]);

        return $this->executeRequest('POST', $url, [
            'Content-Type' => 'application/json',
        ], $visitsJson);
    }

    /**
     * Executes a cURL request and returns the response body.
     *
     * @param string               $method       HTTP method ('GET' or 'POST').
     * @param string               $url          Full request URL.
     * @param array<string,string> $extraHeaders Additional HTTP headers to include.
     * @param string|null          $body         Optional request body (used for POST).
     * @return string Response body.
     * @throws RuntimeException On cURL error or non-2xx HTTP status.
     */
    private function executeRequest(
        string  $method,
        string  $url,
        array   $extraHeaders = [],
        string  $body = null
    ): string {
        $ch = curl_init();

        if ($ch === false) {
            throw new RuntimeException('Failed to initialise cURL handle.');
        }

        $headers = array_merge([
            'Host'      => $this->apiHostnameHost,
            'X-API-KEY' => $this->apiKey,
        ], $extraHeaders);

        $formattedHeaders = array_map(
            static function (string $k, string $v): string {
                return "{$k}: {$v}";
            },
            array_keys($headers),
            $headers
        );

        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $formattedHeaders,
            CURLOPT_TIMEOUT        => self::CURL_TIMEOUT_SECONDS,
            CURLOPT_CONNECTTIMEOUT => self::CURL_CONNECT_TIMEOUT_SEC,
            CURLOPT_FAILONERROR    => false,
        ];

        if (strtoupper($method) === 'POST') {
            $options[CURLOPT_POST]       = true;
            $options[CURLOPT_POSTFIELDS] = $body !== null ? $body : '';
        } else {
            $options[CURLOPT_HTTPGET] = true;
        }

        curl_setopt_array($ch, $options);

        $response   = curl_exec($ch);
        $curlErrNo  = curl_errno($ch);
        $curlErrMsg = curl_error($ch);
        $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($curlErrNo !== CURLE_OK) {
            $this->logger->error('cURL error [{code}] for {url}: {message}', [
                'code'    => $curlErrNo,
                'url'     => $url,
                'message' => $curlErrMsg,
            ]);

            throw new RuntimeException(
                "cURL request failed [{$curlErrNo}]: {$curlErrMsg}"
            );
        }

        if ($httpStatus < self::HTTP_OK_MIN || $httpStatus > self::HTTP_OK_MAX) {
            $this->logger->error('Unexpected HTTP {status} from {url}: {body}', [
                'status' => $httpStatus,
                'url'    => $url,
                'body'   => $response,
            ]);

            throw new RuntimeException(
                "API request to {$url} returned unexpected HTTP status {$httpStatus}."
            );
        }

        return (string)$response;
    }
}
