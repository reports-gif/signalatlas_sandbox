<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

declare(strict_types=1);

namespace Piwik\Plugins\MicrosoftTeams;

use Piwik\Container\StaticContainer;
use Piwik\Http;
use Piwik\Log\LoggerInterface;

class MicrosoftTeamsApi
{
    /**
     * @var string
     */
    private $webhookUrl;

    /**
     * @var string
     */
    private $accessToken;

    /**
     * @var array
     */
    private $requiredFields;

    /**
     * @var LoggerInterface
     */
    private $logger;

    private const ACCESS_TOKEN_URL = 'https://login.microsoftonline.com/{tenantID}/oauth2/v2.0/token';
    private const SITE_ID_URL = 'https://graph.microsoft.com/v1.0/groups/{teamID}/sites/root';
    private const DRIVE_ID_URL = 'https://graph.microsoft.com/v1.0/sites/{siteID}/drives';
    private const UPLOAD_URL = 'https://graph.microsoft.com/v1.0/drives/{driveID}/root:/{$fileName}:/content';

    private const TEAMS_TIMEOUT = 5;

    public function __construct(
        #[\SensitiveParameter]
        string $webhookUrl
    ) {
        $this->webhookUrl = $webhookUrl;
        $this->logger = StaticContainer::get(LoggerInterface::class);
    }

    /**
     *
     * MS Teams file upload is done in
     * 1. Get access token.
     * 2. Get Team siteID
     * 3. Get Team driveID
     * 4. Upload the file to driveID
     * 5. Send the file as a message to team channel via webhook
     *
     * @param string $subject
     * @param string $fileName
     * @param string $fileContents
     * @param array $requiredFields
     * @param string $additionalNote
     * @return bool
     */
    public function uploadFile(
        string $subject,
        string $fileName,
        string $fileContents,
        #[\SensitiveParameter]
        array $requiredFields,
        string $additionalNote = ''
    ): bool {
        $this->requiredFields = $requiredFields;
        $this->accessToken = $this->getAccessToken();
        if (!empty($this->accessToken)) {
            $uploadURL = $this->uploadFileToDriveAndGetLink($fileName, $fileContents);
            if (!empty($uploadURL)) {
                return $this->sendMessageToTeamsChannel($subject . "<br><a href='$uploadURL'>$fileName</a>" . (!empty($additionalNote) ? "<br><br>$additionalNote" : ''));
            }
        }

        $this->logger->info('Unable to send ' . $fileName . ' report to Microsoft Teams');

        return false;
    }

    /**
     * @return string
     */
    public function getAccessToken(): string
    {
        try {
            $response = $this->sendHttpRequest(
                str_replace('{tenantID}', $this->requiredFields['tenantID'], self::ACCESS_TOKEN_URL),
                self::TEAMS_TIMEOUT,
                [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->requiredFields['clientID'],
                    'client_secret' => $this->requiredFields['clientSecret'],
                    'scope' => 'https://graph.microsoft.com/.default',
                ],
                ['Content-Type: application/x-www-form-urlencoded']
            );
        } catch (\Exception $e) {
            $this->logger->error('MicrosoftTeams error getAccessToken: ' . $e->getMessage());
            return '';
        }

        $data = json_decode($response, true);
        if (!empty($data['access_token'])) {
            return $data['access_token'];
        }

        return '';
    }

    /**
     * @param string $fileName
     * @param string $fileContents
     * @return string
     */
    public function uploadFileToDriveAndGetLink(string $fileName, string $fileContents): string
    {
        $siteID = $this->getTeamsSiteId();
        if (empty($siteID)) {
            return '';
        }

        $driveID = $this->getTeamsDriveId($siteID);
        if (empty($driveID)) {
            return '';
        }

        try {
            $response = $this->sendHttpRequest(
                str_replace(['{driveID}', '{$fileName}'], [$driveID, rawurlencode($fileName)], self::UPLOAD_URL),
                self::TEAMS_TIMEOUT,
                [$fileContents],
                [
                    'Content-Length: ' . strlen($fileContents),
                    'Authorization: Bearer ' . $this->accessToken,
                    'Content-Type: application/octet-stream',
                ],
                true,
                'PUT'
            );
        } catch (\Exception $e) {
            $this->logger->error('MicrosoftTeams error uploadFileToDriveAndGetLink: ' . $e->getMessage());
            return '';
        }

        $data = json_decode($response, true);
        if (!empty($data['webUrl'])) {
            return $data['webUrl'];
        }

        return '';
    }

    /**
     * Sends a message to teams channel via webhook URL
     * @param $message
     * @return bool
     */
    public function sendMessageToTeamsChannel($message): bool
    {
        try {
            $response = $this->sendHttpRequest(
                $this->webhookUrl,
                self::TEAMS_TIMEOUT,
                [json_encode(['text' => $message], JSON_UNESCAPED_SLASHES)],
                ["Content-Type: application/json"],
                true
            );
        } catch (\Exception $e) {
            $this->logger->error('MicrosoftTeams error sendMessageToTeamsChannel: ' . $e->getMessage());
            return false;
        }

        $trimmed = trim((string) $response);

        if ($trimmed === '' || $trimmed == '1') {
            return true;
        }

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded) && (isset($decoded['error']) || isset($decoded['errors']))) {
            return false;
        }

        return stripos($trimmed, 'error') === false;
    }

    /**
     * @return string
     */
    public function getTeamsSiteId(): string
    {
        try {
            $response = $this->sendHttpRequest(
                str_replace('{teamID}', $this->requiredFields['teamID'], self::SITE_ID_URL),
                self::TEAMS_TIMEOUT,
                null,
                ['Authorization: Bearer ' . $this->accessToken],
                false,
                'GET'
            );
        } catch (\Exception $e) {
            $this->logger->error('MicrosoftTeams error getTeamsSiteId: ' . $e->getMessage());
            return '';
        }

        $data = json_decode($response, true);
        if (!empty($data['id'])) {
            return $data['id'];
        }

        return '';
    }

    /**
     * @param string $siteID
     * @return string
     */
    public function getTeamsDriveId(string $siteID): string
    {
        try {
            $response = $this->sendHttpRequest(
                str_replace('{siteID}', $siteID, self::DRIVE_ID_URL),
                self::TEAMS_TIMEOUT,
                null,
                ['Authorization: Bearer ' . $this->accessToken],
                false,
                'GET'
            );
        } catch (\Exception $e) {
            $this->logger->error('MicrosoftTeams error getTeamsDriveId: ' . $e->getMessage());
            return '';
        }

        $data = json_decode($response, true);
        $driveID = '';
        if (!empty($data['value'])) {
            foreach ($data['value'] as $drive) {
                if (!empty($drive['id'])) {
                    $driveID = $drive['id'];
                    break;
                }
            }
        }

        return $driveID;
    }

    /**
     * @param string $url
     * @param int $timeout
     * @param array|null $requestBody
     * @param array $additionalHeaders
     * @param $requestBodyAsString
     * @param $httpMethod
     * @return string
     * @throws \Exception
     */
    private function sendHttpRequest(string $url, int $timeout, ?array $requestBody, array $additionalHeaders = [], $requestBodyAsString = false, $httpMethod = 'POST')
    {
        if ($requestBodyAsString && !empty($requestBody[0])) {
            $requestBody = $requestBody[0];
        }

        return Http::sendHttpRequestBy(
            Http::getTransportMethod(),
            $url,
            $timeout,
            $userAgent = null,
            $destinationPath = null,
            $file = null,
            $followDepth = 0,
            $acceptLanguage = false,
            $acceptInvalidSslCertificate = false,
            $byteRange = false,
            $getExtendedInfo = false,
            $httpMethod,
            $httpUsername = null,
            $httpPassword = null,
            $requestBody,
            $additionalHeaders
        );
    }
}
