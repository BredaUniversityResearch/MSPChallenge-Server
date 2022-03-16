<?php

namespace App\Domain\Common;

use App\Domain\API\v1\Base;
use App\Domain\API\v1\Config;
use App\Domain\API\v1\GameSession;
use React\Http\Browser;
use React\Promise\PromiseInterface;

class MSPBrowser extends Browser
{
    public function __construct(string $targetUrl)
    {
        // any proxy required for the external calls of any kind
        //  (MSP Authoriser, BUas GeoServer, or any other GeoServer)
        $connector = null;
        $proxy = Config::GetInstance()->GetAuthWithProxy();
        if (!empty($proxy) && strstr($targetUrl, GameSession::GetRequestApiRoot()) === false &&
            strstr($targetUrl, "localhost") === false && Base::PHPCanProxy()
        ) {
            $connector = $proxy;
        }

        parent::__construct($connector);

        $this->withTimeout(60);
    }

    public function get($url, array $headers = array()): PromiseInterface
    {
        $this->addDefaultHeaders($headers);
        return parent::get($url, $headers);
    }

    public function post($url, array $headers = array(), $contents = ''): PromiseInterface
    {
        $this->addDefaultHeaders($headers);
        return parent::post($url, $headers, $contents);
    }

    public function request($method, $url, array $headers = array(), $body = ''): PromiseInterface
    {
        $this->addDefaultHeaders($headers);
        return parent::request($method, $url, $headers, $body);
    }

    private function addDefaultHeaders(array &$headers)
    {
        $headers[] = 'User-Agent: MSP Challenge Server API';
    }
}
