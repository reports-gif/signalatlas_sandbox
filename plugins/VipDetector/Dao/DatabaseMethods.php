<?php

namespace Piwik\Plugins\VipDetector\Dao;

use Exception;
use Piwik\Db;
use Piwik\DbHelper;
use Piwik\Common;
use Piwik\Plugins\VipDetector\libs\Helpers;

class DatabaseMethods
{
    /**
     * Get the name associated with an IP. If there is no match, throw an Exception.
     *
     * @throws Exception
     * @param string $ip The IP to check
     * @return string The name found
     */
    public static function getNameFromIp(string $ip): string
    {
        // We want the name that is associated with this IPs range.
        // So we find the name of the range between the start and the end address and then join it on the names table.
        $query = sprintf(
            'SELECT names.`name`
                FROM `%s` ranges
            LEFT JOIN `%s` names
            ON ranges.`name_id` = names.`id`
                WHERE `type` = ?
                AND  INET6_ATON(?)
                BETWEEN ranges.`range_from` AND ranges.`range_to`',
            Common::prefixTable('vip_detector_ranges'),
            Common::prefixTable('vip_detector_names')
        );

        // We can only have one result, so it is enough to fetch one
        $name = Db::fetchOne(
            $query,
            array(
                Helpers::getAddressType($ip),
                $ip
            )
        );

        if (empty($name)) {
            throw new Exception('No name found');
        }

        return $name;
    }

    /**
     * Check if the name is in the database. If not, throw an Exception. If yes, return the id.
     *
     * @param string $searchValue
     * @return string
     * @throws Exception
     */
    public static function getNameId(string $searchValue): string
    {
        $query = sprintf(
            'SELECT `id` FROM `%s` WHERE `name` = ?',
            Common::prefixTable('vip_detector_names')
        );

        // Names are unique, so we only need the first result
        $result = Db::fetchOne(
            $query,
            array($searchValue) // fetchOne expects the parameters to be an array
        );

        if (empty($result)) {
            throw new Exception('No name found');
        }

        return $result;
    }

    /**
     * Check if the database contains the range in question
     * @param array<int, string, string> $rangeInfo An array containing the first and last IP address of the range
     * @return bool
     * @throws Exception
     */
    public static function checkRangeInDb(array $rangeInfo): bool
    {
        $query = sprintf(
            'SELECT `id` FROM `%s` WHERE `range_from` = INET6_ATON(?) AND `range_to` = INET6_ATON(?)',
            Common::prefixTable('vip_detector_ranges')
        );

        // Same idea as with the names
        $result = Db::fetchOne(
            $query,
            array(
                $rangeInfo['range_from'],
                $rangeInfo['range_to']
            )
        );

        if (empty($result)) {
            return false;
        }

        return true;
    }

    /**
     * Add a name to the database.
     * @param string $name The name to be inserted
     * @return bool
     */
    public static function insertName(string $name): bool
    {
        $query = sprintf(
            'INSERT INTO `%s` (name)
                VALUES
            (?)',
            Common::prefixTable('vip_detector_names')
        );

        try {
            Db::query(
                $query,
                array($name)
            );
        } catch (Exception $ex) {
            return false;
        }

        return true;
    }

    /**
     * Add a range in the database
     * @param array<string, string> $rangeInfo An array containing the first and last IP address to be inserted
     */
    public static function insertRange(array $rangeInfo): bool
    {
        // Store the addresses as INET6_ATON representation for more efficiency
        $query = sprintf(
            'INSERT INTO `%s` (type, range_from, range_to, name_id)
                VALUES
            (?, INET6_ATON(?), INET6_ATON(?), ?)',
            Common::prefixTable('vip_detector_ranges')
        );

        try {
            Db::query(
                $query,
                $rangeInfo
            );
        } catch (Exception $ex) {
            return false;
        }

        return true;
    }

    /**
     * Counts how many names are present in the database
     * @throws Exception
     * @return int The number of names
     */
    public static function countNames(): int
    {
        return self::countValues('id', 'vip_detector_names');
    }

    /**
     * Counts how many ranges are present in the database
     * @throws Exception
     * @return int The number of ranges
     */
    public static function countRanges(): int
    {
        return self::countValues('name_id', 'vip_detector_ranges');
    }

    /**
     * Count how many entries are present in a specific table
     * @throws Exception
     * @param string $to_select The field to count
     * @param string $table The table that contains the field
     * @return int The number of values in the database
     */
    private static function countValues(string $to_select, string $table): int
    {
        try {
            $result = Db::fetchOne(
                sprintf(
                    'SELECT COUNT("%s") FROM `%s`',
                    $to_select,
                    Common::prefixTable($table)
                )
            );
        } catch (Exception $ex) {
            return 0;
        }

        if (empty($result)) {
            return 0;
        }

        return intval($result);
    }

    /**
     * Creates the needed database tables
     * @return void
     */
    public static function createTables(): void
    {
        DbHelper::createTable(
            'vip_detector_names',
            'id INT NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
                PRIMARY KEY (id)'
        );

        DbHelper::createTable(
            'vip_detector_ranges',
            'id INT NOT NULL AUTO_INCREMENT,
                type TINYINT NOT NULL,
                range_from VARBINARY(16) NOT NULL,
                range_to VARBINARY(16) NOT NULL,
                name_id INT NOT NULL,
                PRIMARY KEY (id)'
        );
    }

    /**
     * Deletes the database tables
     * @return void
     */
    public static function removeTables(): void
    {
        Db::dropTables(
            array(
                Common::prefixTable('vip_detector_names'),
                Common::prefixTable('vip_detector_ranges')
            )
        );
    }
}
