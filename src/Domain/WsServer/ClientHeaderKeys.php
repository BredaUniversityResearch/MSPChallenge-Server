<?php

namespace App\Domain\WsServer;

interface ClientHeaderKeys
{
    const HEADER_KEY_GAME_SESSION_ID = 'Game-Session-Id';
    const HEADER_KEY_MSP_API_TOKEN = 'Msp-Api-Token';

    // deprecated but still supported
    const HEADER_KEYS_DEPRECATED = [
        self::HEADER_KEY_GAME_SESSION_ID => 'GameSessionId',
        self::HEADER_KEY_MSP_API_TOKEN => 'MSPAPIToken'
    ];
}
