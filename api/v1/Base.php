<?php

namespace App\Domain\API\v1;

use App\Domain\API\APIHelper;
use App\Domain\Helper\AsyncDatabase;
use Drift\DBAL\Connection;
use Exception;
use React\EventLoop\Loop;
use TypeError;

function IsFeatureFlagEnabled(string $featureName): bool
{
    return (isset($GLOBALS['feature_flags'][$featureName]) && $GLOBALS['feature_flags'][$featureName] == true);
}

class Base
{
    public static bool $debug = false;

    public static bool $more = false;

    public static string $public = "dbfc9c465c3ed8394049f848344f4ab8";

    private bool $isValid = false;
    private array $allowed;
    private ?Connection $asyncDatabase = null;

    /**
     * @throws Exception
     */
    public function __construct(string $method = '', array $allowed = [])
    {
        $this->allowed = $allowed;
        if ($method !== '') {
            $this->isValid = $this->Validate($this->allowed, $method);
        }
    }

    public function isValid(): bool
    {
        return $this->isValid;
    }

    /**
     * @throws Exception
     */
    protected function getAsyncDatabase(): Connection
    {
        if (null === $this->asyncDatabase) {
            // fail-safe: try to create an async database from current request information if there is no instance set.
            if (GameSession::INVALID_SESSION_ID === $gameSessionId = GameSession::GetGameSessionIdForCurrentRequest()) {
                throw new Exception('Missing required async database connection.');
            }
            $this->asyncDatabase = AsyncDatabase::createGameSessionConnection(Loop::get(), $gameSessionId);
        }
        return $this->asyncDatabase;
    }

    public function setAsyncDatabase(Connection $asyncDatabase): void
    {
        $this->asyncDatabase = $asyncDatabase;
    }

    /**
     * @param mixed|null $d
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function Debug($d): void
    {
        echo "<pre>";
        print_r($d);
        echo "</pre>";
    }

    /**
     * @param mixed|null $d
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function Warning($d): void
    {
        echo "<pre style='color:#c67d00; background-color:#000; padding: 10px 5px; margin: 10px 0 0 0;'>";
        print_r("WARNING     ");
        print_r($d);
        echo "</pre>";
    }

    /**
     * @param mixed|null $d
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function Error($d): void
    {
        echo "<pre style='color:#e80000; background-color:#000; padding: 10px 5px; margin-top: 10px;'>";
        print_r("ERROR       ");
        print_r($d);
        echo "</pre>";
    }

    /**
     * @param Exception|TypeError $errorException
     * @return string
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function ErrorString($errorException): string
    {
        return $errorException->getMessage() . PHP_EOL . "Of file " . $errorException->getFile() . " On line " .
            $errorException->getLine() . PHP_EOL . "Stack trace: " . $errorException->getTraceAsString();
    }

    /**
     * @param array $data
     * @return false|string
     */
    public static function JSON(array $data)/*: false|string */ // <-- for php 8
    {
        if (self::$more) {
            self::Debug($data);
            return '';
        }
        return json_encode($data);
    }

    /**
     * @param array $data
     * @param bool $encode
     * @return array|false|string
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function MergeGeometry(array $data, bool $encode = false)/*: false|string|array */ // <-- for php 8
    {
        if (self::$more) {
            self::Debug($data);
            return '';
        }

        $arr = array();
        /** @noinspection PhpParameterByRefIsNotUsedAsReferenceInspection */
        foreach ($data as &$d) {
            $geom = array(
                "id" => $d['id'],
                "geometry" => json_decode($d['geometry'], true),
                "subtractive" => array(),
                "persistent" => $d['persistent'],
                "mspid" => ($d['mspid'] == null) ? 0 : $d['mspid'],
                "type" => $d['type'],
                "country" => ($d['country'] == null) ? -1 : $d['country']
            );

            if (isset($d['active'])) {
                $geom["active"] = $d['active'];
            }

            //if this geometry needs to be handled as a subtractive poly
            if (isset($d['subtractive']) && $d['subtractive'] != 0) {
                foreach ($arr as &$g) {
                    if ($g['id'] == $d['subtractive']) {
                        if (!is_array($g['subtractive'])) {
                            $g['subtractive'] = array();
                        }

                        array_push($g['subtractive'], $geom);
                    }
                }
            } else {
                array_push($arr, $geom);
            }

            if ($d['data'] == "[]") {
                $arr[sizeof($arr) - 1]['data'] = "";
            } else {
                $arr[sizeof($arr) - 1]['data'] = json_decode($d['data']);
            }
        }

        return ($encode) ? json_encode($arr) : $arr;
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    protected function Validate(array $allowed, string $called): bool
    {
        $calledFunctionAllowed = false;
        $accessFlagsRequired = Security::ACCESS_LEVEL_FLAG_FULL;

        $calledLower = strtolower($called);
        foreach ($allowed as $allowedToCheck) {
            if (is_array($allowedToCheck)) {
                if (strtolower($allowedToCheck[0]) == $calledLower) {
                    $calledFunctionAllowed = true;
                    $accessFlagsRequired = $allowedToCheck[1];
                    break;
                }
            } elseif (strtolower($allowedToCheck) == $calledLower) {
                $calledFunctionAllowed = true;
                break;
            }
        }

        $security = new Security();
        return $calledFunctionAllowed && $security->validateAccess($accessFlagsRequired);
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function Dir(): string
    {
        $abs_app_root = $_SERVER['DOCUMENT_ROOT'];
        $url_app_root = '';
        $self_path = explode("/", $_SERVER['PHP_SELF']);
        $self_path_length = count($self_path);
        for ($i = 1; $i < $self_path_length; $i++) {
            array_splice($self_path, $self_path_length - $i, $i);
            $url_app_root = implode("/", $self_path) . "/";
            if (file_exists($abs_app_root . $url_app_root . 'api_config.php')) {
                break;
            }
        }
        return $abs_app_root . $url_app_root;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function PHPCanProxy(): bool
    {
        if (!empty(ini_get('open_basedir')) || ini_get('safe_mode')) {
            return false;
        }
        return true;
    }

    /**
     * @param string $url
     * @param array|string $postarray
     * @param array $headers
     * @param bool $async
     * @param bool $asjson
     * @param array $customopt
     * @return false|string
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function CallBack(
        string $url,
        $postarray,
        array $headers = array(),
        bool $async = false,
        bool $asjson = false,
        array $customopt = array()
    ) {/*: false|string */ // <-- for php 8
        $ch = curl_init($url);

        // any proxy required for the external calls of any kind
        //  (MSP Authoriser, BUas GeoServer, or any other GeoServer)
        $proxy = Config::GetInstance()->GetAuthWithProxy();
        if (!empty($proxy) && strstr($url, GameSession::GetRequestApiRoot()) === false &&
            strstr($url, "localhost") === false && self::PHPCanProxy()
        ) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 1);
        }

        curl_setopt($ch, CURLOPT_USERAGENT, "MSP Challenge Server API");

        if ($asjson) {
            $post = json_encode($postarray);
        } else {
            $post = $postarray;
        }
        if (!empty($post)) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        if (!$async) {
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        } else {
            curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        }

        if (!empty($customopt)) {
            foreach ($customopt as $key => $val) {
                curl_setopt($ch, $key, $val);
            }
        }

        $return = curl_exec($ch);
        $info = curl_getinfo($ch);

        if ($async == false && ($return === false || $info === false || in_array($info["http_code"], [401, 502]))) {
            throw new Exception("Request failed to url " . $url . PHP_EOL . "CURL Error: " . curl_error($ch) . PHP_EOL .
                "Response Http code: " . ($info["http_code"] ?? "Unknown") . PHP_EOL . "Response Page output: " .
                ($return ?? "Nothing"));
        }
        curl_close($ch);

        return $return;
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function AutoloadAllClasses(): void
    {
        $apifolder = APIHelper::getInstance()->GetCurrentSessionServerApiFolder();
        foreach (array_diff(scandir($apifolder), array('..', '.')) as $file) {
            $file = strtolower($file);
            if (substr($file, 0, 6) == "class.") {
                $includeFileName = $apifolder . $file;
                include_once($includeFileName); // won't include the same file twice
            }
        }
    }

    public static function isNewPasswordFormat(string $string): bool
    {
        if (base64_encode(base64_decode($string, true)) === $string) {
            if (isJsonObject(base64_decode($string))) {
                return true;
            }
        }
        return false;
    }
}