<?php

namespace App\Domain\API\v1;

use Exception;

class GeoServer extends Base
{
    public string $baseurl;
    public string $username;
    public string $password;

    public function __construct(string $baseurl = "", string $username = "", string $password = "")
    {
        if (!empty($baseurl)) {
            $this->baseurl = $baseurl;
        }
        if (!empty($username)) {
            $this->username = $username;
        }
        if (!empty($password)) {
            $this->password = $password;
        }

        parent::__construct();
    }

    //generic GET request

    /**
     * @throws Exception
     */
    public function curlGet(string $url, string $returntype = "json"): ?string
    {
        $customOpt = array(CURLOPT_USERPWD => $this->username . ":" . $this->password);
        $return = $this->CallBack(
            $this->baseurl."rest/".$url,
            array(),
            array("Accept: application/" . $returntype),
            false,
            false,
            $customOpt
        );
        return json_decode($return, true);
    }

    /**
     * @param string $url
     * @param string $method
     * @param string $data
     * @param string $contentType
     * @return false|string
     * @throws Exception
     */
    public function request(
        string $url,
        string $method = "GET",
        string $data = "",
        string $contentType = "text/xml"
    ): false|string {
        $headers = array("Content-Type: " . $contentType, "Content-Length: " . strlen($data));
        $customOpt = array(
            CURLOPT_USERPWD => $this->username . ":" . $this->password,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HEADER => false
        );
            
        if ($method == "DELETE" || $method == "PUT") {
            $customOpt[CURLOPT_CUSTOMREQUEST] = $method;
        }

        return $this->CallBack(
            $this->baseurl."rest/".$url,
            $data,                  // content to send, see above
            $headers,               // headers to send, see above
            false,                  // sync request, so wait for it
            false,                  // don't json encode the $data supplied
            $customOpt
        );            // see above additional curl opts for this request
    }

    public function ows(string $url): ?string
    {
        Log::LogInfo("Calling geoserver: ".$this->baseurl.$url);

        return $this->CallBack(
            $this->baseurl . $url,
            array(),                // no content to send
            array(),                // no headers to send
            false,                  // sync request, so wait for it
            false,                  // no content, so no json encoding of it required either
            array(
                CURLOPT_USERPWD => $this->username . ":" . $this->password,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HEADER => false
            )
        );
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function GetAllRemoteLayers(string $region): array
    {
        $layers = array();
        $requestResult = json_decode($this->request("workspaces/".$region."/layers?format=json"), true);
        if (!isset($requestResult["layers"]) || !isset($requestResult["layers"]["layer"])) {
            return $layers;
        }

        foreach ($requestResult["layers"]["layer"] as $layer) {
            $layers[] = $layer["name"];
        }
        return $layers;
    }
}
