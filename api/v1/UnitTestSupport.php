<?php

namespace App\Domain\API\v1;

use Exception;

class UnitTestSupport extends Base
{
    /**
     * @throws Exception
     */
    public function __construct(string $method = '')
    {
        parent::__construct($method, []);

        Config::GetInstance()->GetUnitTestLoggerConfig();
        if (!self::ShouldLogApiCalls()) {
            throw new Exception("This should not be instantiated in non-development environments");
        }
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function GetIntermediateFolder(): ?string
    {
        $dbName = Database::GetInstance()->GetDatabaseName();
        if (empty($dbName)) {
            return null;
        }

        $config = Config::GetInstance()->GetUnitTestLoggerConfig();
        return $config["intermediate_folder"].$dbName."/";
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function ShouldLogApiCalls(): bool
    {
        $config = Config::GetInstance()->GetUnitTestLoggerConfig();
        return isset($config["enabled"]) && $config["enabled"] === true;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function RecordApiCall(string $class, string $method, array $data, array $result): void
    {
        $requestHeaders = apache_request_headers();
        $requestHeaders = array_change_key_case($requestHeaders, CASE_LOWER);
        
        if (isset($requestHeaders["msp_force_no_call_log"])) {
            return;
        }

        $requestKey = strtolower($class)."::".strtolower($method);
        if ($this->IsCallIdentifierOnIgnoreList($requestKey) ||
            $this->CallIdentifierRecordingNoLongerRequired($requestKey)
        ) {
            return;
        }

        $outputData = array ("call_class" => $class,
            "call_method" => $method,
            "call_data" => $data,
            "result" => $result
        );

        $outputFolder = self::GetIntermediateFolder();
        if ($outputFolder != null) {
            if (!is_dir($outputFolder)) {
                mkdir($outputFolder, 0666, true);
            }
        
            $filename = ((string)number_format(microtime(true), 4, '.', ''))."-".$class."-".$method.".json";
            file_put_contents($outputFolder.$filename, json_encode($outputData));

            $data = $this->ReadAndReturnSummaryJSON();
            if (isset($data[$requestKey])) {
                $data[$requestKey] = $data[$requestKey] + 1;
            } else {
                $data[$requestKey] = 1;
            }
            $statFilePath = $outputFolder."summary.json";
            $statFile = fopen($statFilePath, "c+");
            ftruncate($statFile, 0);
            fseek($statFile, 0);
            fwrite($statFile, json_encode($data, JSON_PRETTY_PRINT));
            fflush($statFile);
            flock($statFile, LOCK_UN);
            fclose($statFile);
        }
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function IsCallIdentifierOnIgnoreList(string $callIdentifier): bool
    {
        $config = Config::GetInstance()->GetUnitTestLoggerConfig();
        return (isset($config["request_filter"]["ignore"]) && in_array(
            $callIdentifier,
            $config["request_filter"]["ignore"]
        ));
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function CallIdentifierRecordingNoLongerRequired(string $callIdentifier): bool
    {
        $config = Config::GetInstance()->GetUnitTestLoggerConfig();

        $data = $this->ReadAndReturnSummaryJSON();
        return (
            isset($data[$callIdentifier]) && isset($config["request_filter"]["onlyonce"]) &&
            in_array($callIdentifier, $config["request_filter"]["onlyonce"])
        );
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function ReadAndReturnSummaryJSON()
    {
        $outputFolder = self::GetIntermediateFolder();
        $statFilePath = $outputFolder."summary.json";
        $data = array();

        $statFile = fopen($statFilePath, "c+");
        while (!flock($statFile, LOCK_EX)) {
            continue;
        }
        fseek($statFile, 0, SEEK_END);
        $statFileSize = ftell($statFile);
        if ($statFileSize > 0) {
            fseek($statFile, 0, SEEK_SET);
            $statData = fread($statFile, filesize($statFilePath));
            $data = json_decode($statData, true);
        }
        fclose($statFile);
        return $data;
    }
}
