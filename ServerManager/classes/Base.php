<?php

namespace ServerManager;

use App\Domain\Helper\Config;

class Base
{
    public function hasJWT(): bool
    {
        return Session::get("currentToken") !== null;
    }

    public function getJWT(): string
    {
        if (null === Session::get("currentToken")) {
            // this will only happen if this is called without a logged-in user on ServerManager
            // meaning: when the WebSocket server recreates a demo session that reached the end
            try {
                $vars = array(
                    "username" => ServerManager::getInstance()->GetServerID(),
                    "password" => ServerManager::getInstance()->GetServerPassword()
                );
                $authoriserCall = $this->postCallAuthoriser(
                    "login_check",
                    $vars
                );
                if (null !== $authoriserCall["token"]) {
                    Session::put('currentToken', $authoriserCall["token"]);
                }
            } catch (\Exception $e) {
                // nothing to do.
            }
        }
        return Session::get("currentToken") ?? '';
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

    /**
     * @throws HydraErrorException
     */
    public function getCallAuthoriser(string $endpoint, array $data2send = [])
    {
        return $this->callAuthoriser('GET', $endpoint, $data2send);
    }

    // needs a function to call Authoriser API
    /**
     * @throws HydraErrorException
     */
    public function postCallAuthoriser(string $endpoint, array $data2send = [])
    {
        return $this->callAuthoriser('POST', $endpoint, $data2send);
    }

    /**
     * @throws HydraErrorException
     */
    public function putCallAuthoriser(string $endpoint, array $data2send = [])
    {
        return $this->callAuthoriser('PUT', $endpoint, $data2send);
    }

    /**
     * @throws HydraErrorException
     */
    public function callAuthoriser(string $method, string $endpoint, array $data2send = [])
    {
        $headers = [];
        if ($this->hasJWT()) {
            $headers[] = 'Authorization: Bearer ' . $this->getJWT();
        }
        $callReturn = self::callAPI(
            $method,
            ServerManager::getInstance()->GetMSPAuthAPI().$endpoint,
            $data2send,
            $headers
        );
        $callReturnDecoded = json_decode($callReturn, true);
        if ((array_key_exists('code', $callReturnDecoded ?? [])) && $callReturnDecoded['code'] == 401) {
            throw new MSPAuthException(401, $callReturnDecoded['message'] ?? 'Unauthorized');
        }
        if ((array_key_exists('@type', $callReturnDecoded ?? [])) && $callReturnDecoded['@type'] == 'hydra:Error') {
            throw new HydraErrorException($callReturnDecoded);
        }
        return $callReturnDecoded;
    }

    // needs a generic calling something function
    private static function callAPI($method, $url, $data2send = false, $headers = array(), $asJson = true): bool|string
    {
        $curl = curl_init();

        switch ($method) {
            case "POST":
            case "PUT":
                curl_setopt($curl, CURLOPT_POST, 1);
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
                if ($data2send) {
                    if ($asJson) {
                        $data2send = json_encode($data2send);
                        $headers[] = 'Content-Type: application/json';
                    }
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data2send);
                }
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
