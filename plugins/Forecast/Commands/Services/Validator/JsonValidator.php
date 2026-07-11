<?php

declare(strict_types=1);

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\Forecast\Commands\Services\Validator;

class JsonValidator
{
    /**
     * Validates whether the given string is valid JSON.
     *
     * @param string      $json         The string to validate.
     * @param string|null $errorMessage When provided and validation fails, set to the JSON error message.
     * @return bool True when the string is valid JSON, false otherwise.
     */
    public static function validate(string $json, ?string &$errorMessage = null): bool
    {
        json_decode($json);
        $error = json_last_error();

        if ($error !== JSON_ERROR_NONE) {
            $errorMessage = json_last_error_msg();
            return false;
        }

        return true;
    }
}
