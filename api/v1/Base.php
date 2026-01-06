<?php

namespace App\Domain\API\v1;

use App\Domain\Common\CommonBase;
use Exception;
use function App\await;
use function App\isJsonObject;

abstract class Base extends CommonBase
{
    public static bool $debug = false;

    public static bool $more = false;

    public static string $public = "dbfc9c465c3ed8394049f848344f4ab8";

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
     * @param array $data
     * @param bool $encode
     * @return array
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function MergeGeometry(array $data, bool $encode = false): array
    {
        if (self::$more) {
            self::Debug($data);
            return [];
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
            if (array_key_exists('implementation_time', $d)) {
                $geom['implementation_time'] = $d['implementation_time'];
            }

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
                $arr[sizeof($arr) - 1]['data'] = json_decode($d['data'] ?? '');
            }
        }

        if ($encode) {
            $arr = json_encode($arr) ?: [];
        }
        return $arr;
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
    ): false|string {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERAGENT, "MSP Challenge Server API");

        if ($asjson) {
            $post = json_encode($postarray);
            $headers[] = 'Content-Type: application/json';
        } else {
            $post = $postarray;
        }
        if (!empty($post)) {
            curl_setopt($ch, CURLOPT_POST, true);
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
        if ($async === false && ($return === false || $info === false || in_array($info["http_code"], [401, 502]))) {
            if ($info["http_code"] == 401) {
                throw new Exception("Authentication failed. Please check the details you provided.");
            }
            throw new Exception("Request failed to url " . $url . PHP_EOL . "CURL Error: " . curl_error($ch) . PHP_EOL .
                "Response Http code: " . $info["http_code"] . PHP_EOL . "Response Page output: " .
                ($return ?: "Nothing"));
        }
        curl_close($ch);

        return $return;
    }

    public static function isNewPasswordFormat(?string $string): bool
    {
        if (base64_encode(base64_decode($string, true)) === $string) {
            if (isJsonObject(base64_decode($string))) {
                return true;
            }
        }
        return false;
    }
}
