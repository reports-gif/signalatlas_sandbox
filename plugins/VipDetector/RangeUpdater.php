<?php

namespace Piwik\Plugins\VipDetector;

use Exception;
use Piwik\Common;
use Piwik\Container\StaticContainer;
use Piwik\Http;
use Piwik\Log\LoggerInterface;
use Piwik\Plugins\VipDetector\Dao\DatabaseMethods;
use Piwik\Plugins\VipDetector\libs\Helpers;
use Piwik\SettingsPiwik;

class RangeUpdater
{
    private LoggerInterface $logger;
    /**
     * @var string $source_type How we are acquiring the data
     */
    private string $source_type;
    /**
     * @var string $source Where the data is
     */
    private string $source;

    /**
     * @param string $source Where the data is
     * @param string $source_type How we are acquiring the data
     * @throws Exception
     */
    public function __construct(string $source, string $source_type)
    {
        // @phan-suppress-next-line PhanAccessMethodInternal
        $this->logger = StaticContainer::get(LoggerInterface::class);
        $this->source = $source; // Path or Url to the source
        $this->source_type = $source_type; // url or file
    }

    /**
     * Try to fetch the data file and seed the database
     * @return void The status if the import
     * @throws Exception
     */
    public function import(): void
    {
        // Load the json source
        $sourceData = $this->loadJson($this->source_type, $this->source);
        $this->insertData($sourceData);

        $this->logger->info("Done loading.");
    }

    /**
     * Load the JSON Data. This can be done from a local file via the command line, or via HTTP from the UI.
     * @param string $source_type
     * @param string $source
     * @return \stdClass[] $rangeInfo
     * @throws Exception
     */
    private function loadJson(string $source_type, string $source): array
    {
        // At the moment this can be "file" or "url".
        switch ($source_type) {
            case 'file':
                $this->logger->info("Source is file. Start loading");
                $source_string = @file_get_contents($source);

                break;

            case 'url':
                if (!SettingsPiwik::isInternetEnabled()) {
                    throw new Exception("To load from a remote URL internet access needs to be enabled.");
                }

                $this->logger->info("Source is remote URL. Start loading.");

                // download the file.
                $source_string = Http::sendHttpRequest($source, 30);

                break;

            default:
                throw new Exception("Invalid source type.");
        }

        // request was ok, but response was empty, file not found, etc
        if (!$source_string) {
            throw new Exception("File could not be loaded.");
        }

        // input is not valid json.
        if (!$json = json_decode($source_string)) {
            throw new Exception("File is not JSON.");
        }

        return $json;
    }

    /**
     * Insert the data to the respective tables
     * @throws Exception
     * @param \stdClass[] $data
     */
    private function insertData(array $data): void
    {
        // loop through all elements in the source
        foreach ($data as $entry) {
            $name = Common::sanitizeInputValues($entry->name);

            // only insert the name if it is not already there
            try {
                $nameId = DatabaseMethods::getNameId($name);
            } catch (Exception $ex1) {
                if (!DatabaseMethods::insertName($name)) {
                    $this->logger->critical("Name {$name} could not be inserted.");
                }

                $nameId = DatabaseMethods::getNameId($name);
            }

            foreach ($entry->ranges as $range) {
                $this->logger->debug("Currently at {$name}: {$range}");

                try {
                    $rangeInfo = Helpers::getRangeInfo($range);
                } catch (Exception $ex) {
                    $this->logger->critical("Invalid range {$range}");
                    continue;
                }

                // same as for name, don't insert duplicates
                if (!DatabaseMethods::checkRangeInDb($rangeInfo)) {
                    $rangeInfoToInsert = array_merge(
                        $rangeInfo,
                        ['name_id' => $nameId]
                    );

                    DatabaseMethods::insertRange(
                        $rangeInfoToInsert
                    );
                }
            }
        }
    }
}
