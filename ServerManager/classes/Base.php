<?php

namespace ServerManager;

use App\Domain\Helper\Config;

class Base
{
    protected ?string $jwt = null;

    public function setJWT($jwt): void
    {
        $this->jwt = $jwt;
    }

    public function getJWT(): string
    {
        if (null === $this->jwt) {
            $vars = array(
                "server_id" => ServerManager::getInstance()->GetServerID(),
                "audience" => ServerManager::getInstance()->GetBareHost()
            );
            $authoriserCall = self::callAuthoriser(
                "getjwt.php",
                $vars
            );
            if ($authoriserCall["success"]) {
                $this->jwt = $authoriserCall["jwt"] ?? "";
            }
        }
        return $this->jwt;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function HasSpecialChars($string): bool|int
    {
        return (preg_match('/[\\\\\/"`\'^€£$%*}{@#~!?><.,|=+¬]/', $string));
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function EmptyOrHasSpaces($string): bool
    {
        return (str_contains($string, " ") || empty($string));
    }

    public static function isNewPasswordFormat($string): bool
    {
        if (base64_encode(base64_decode($string, true)) === $string) {
            if (isJsonObject(base64_decode($string))) {
                return true;
            }
        }
        return false;
    }

    public function processPostedVars(): void
    {
        $args = getPublicObjectVars($this);
        foreach ($args as $key => $value) {
            if (isset($_POST[$key])) {
                $this->$key = $_POST[$key];
            }
        }
    }

    public function ignorePostedVars($array): void
    {
        if (is_array($array)) {
            foreach ($array as $value) {
                if (isset($_POST[$value])) {
                    unset($_POST[$value]);
                }
            }
        }
    }

    // needs a function to call server API
    public static function callServer($endpoint, $data2send = false, $session_id = "", $api_access_token = "")
    {
        $call_return = self::callAPI(
            "POST",
            ServerManager::getInstance()->GetServerURLBySessionId($session_id)."/api/".$endpoint,
            $data2send,
            array("MSPAPIToken: ".$api_access_token),
            false
        );
        return json_decode($call_return, true);
    }

    // needs a function to call Authoriser API
    public static function callAuthoriser($endpoint, $data2send)
    {
        $call_return = self::callAPI("POST", ServerManager::getInstance()->GetMSPAuthAPI().$endpoint, $data2send);
        return json_decode($call_return, true);
    }

    // needs a generic calling something function
    /** @noinspection PhpSameParameterValueInspection */
    private static function callAPI($method, $url, $data2send = false, $headers = array(), $asJson = true): bool|string
    {
        $curl = curl_init();
        switch ($method) {
            case "POST":
                curl_setopt($curl, CURLOPT_POST, 1);
                if ($data2send) {
                    if ($asJson) {
                        $data2send = json_encode($data2send);
                    }
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data2send);
                }
                break;
            case "PUT":
                curl_setopt($curl, CURLOPT_PUT, 1);
                break;
            default:
                if ($data2send) {
                    $url = sprintf("%s?%s", $url, http_build_query($data2send));
                }
        }
        if (!empty($headers) && is_array($headers)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }
  
        // any proxy required for the external calls to the MSP Authoriser? (which are the only kind of external calls
        //   done by ServerManager)
        $proxy = Config::get('msp_auth/with_proxy');
        if (!empty($proxy) && str_contains($url, ServerManager::getInstance()->GetMSPAuthAPI()) && PHPCanProxy()) {
            curl_setopt($curl, CURLOPT_PROXY, $proxy);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($curl, CURLOPT_MAXREDIRS, 1);
        }
        curl_setopt($curl, CURLOPT_USERAGENT, "MSP Challenge Server Manager API");
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        $result = curl_exec($curl);
        curl_close($curl);
        return $result;
    }
}
