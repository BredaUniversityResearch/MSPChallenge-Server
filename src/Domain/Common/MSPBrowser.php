<?php

namespace App\Domain\Common;

use App\Domain\API\v1\Base;
use App\Domain\API\v1\Config;
use App\Domain\API\v1\GameSession;
use React\Http\Browser;
use React\Promise\PromiseInterface;

class MSPBrowser
{
    private Browser $browser;

    public function __construct(string $targetUrl)
    {
        // any proxy required for the external calls of any kind
        //  (MSP Authoriser, BUas GeoServer, or any other GeoServer)
        $connector = null;
        $proxy = Config::GetInstance()->GetAuthWithProxy();
        if (!empty($proxy) && !str_contains($targetUrl, GameSession::GetRequestApiRoot()) &&
            !str_contains($targetUrl, "localhost") && Base::PHPCanProxy()
        ) {
            $connector = $proxy;
        }

        $browser = new Browser($connector);
        $browser->withTimeout(60);
    }

    public function get($url, array $headers = array()): PromiseInterface
    {
        $this->addDefaultHeaders($headers);
        return $this->browser->get($url, $headers);
    }

    public function post($url, array $headers = array(), $contents = ''): PromiseInterface
    {
        $this->addDefaultHeaders($headers);
        return $this->browser->post($url, $headers, $contents);
    }

    public function request($method, $url, array $headers = array(), $body = ''): PromiseInterface
    {
        $this->addDefaultHeaders($headers);
        return $this->browser->request($method, $url, $headers, $body);
    }

    private function addDefaultHeaders(array &$headers): void
    {
        $headers[] = 'User-Agent: MSP Challenge Server API';
    }

    public function __call(string $name, array $arguments)
    {
        return call_user_func_array([$this->browser, $name], $arguments);
    }
}
