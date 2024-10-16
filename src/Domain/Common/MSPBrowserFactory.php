<?php

namespace App\Domain\Common;

use App\Domain\API\v1\Base;
use App\Domain\API\v1\Config;
use App\Domain\API\v1\GameSession;
use Clue\React\HttpProxy\ProxyConnector;
use Exception;
use React\Http\Browser;

class MSPBrowserFactory
{
    /**
     * @throws Exception
     */
    public static function create(string $targetUrl, string $proxy = null): Browser
    {
        // any proxy required for the external calls of any kind
        //  (MSP Authoriser, BUas GeoServer, or any other GeoServer)
        $connector = null;
        $proxy = Config::GetInstance()->GetAuthWithProxy();
        if (!empty($proxy) && !str_contains($targetUrl, GameSession::GetRequestApiRoot()) &&
            !str_contains($targetUrl, "localhost") && Base::PHPCanProxy()
        ) {
            $connector = new ProxyConnector($proxy);
        }

        $browser = new Browser($connector);
        return $browser
            ->withTimeout(60)
            ->withHeader('User-Agent', 'MSP Challenge Server API');
    }
}
