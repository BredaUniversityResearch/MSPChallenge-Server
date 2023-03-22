<?php

use App\Domain\Common\DatabaseDefaults;
use App\Domain\Services\ConnectionManager;

$connectionConfig = ConnectionManager::getInstance()->getConnectionConfig();
$codeBranch = '/';

/** @noinspection HttpUrlsUsage */
$GLOBALS['config'] = array(
    'ws_server' => [
        'scheme' => $_ENV['WS_SERVER_SCHEME'] ?? 'ws://',
        // if "host" is not set, ServerManager::getInstance()->GetTranslatedServerURL() is used,
        //   which is the same host as the api server.
        'host' => $_ENV['WS_SERVER_HOST'] ?? null,
        'port' => $_ENV['WS_SERVER_PORT'] ?? 45001,
        'port_external' => ($_ENV['WS_SERVER_PORT_EXTERNAL'] ?? null) ?: 45001,
        'uri' =>  $_ENV['WS_SERVER_URI'] ?? '',
        // none, add_game_session_id_to_port, add_game_session_id_to_uri
        'address_modification' => $_ENV['WS_SERVER_ADDRESS_MODIFICATION'] ?? 'none'
    ],
    'code_branch' => $codeBranch,
    'mysql' => array_merge($connectionConfig, [
        'db' => $_ENV['DBNAME_SERVER_MANAGER'] ?? DatabaseDefaults::DEFAULT_DBNAME_SERVER_MANAGER,
        'username' => $connectionConfig['user'],
        'multisession_database_prefix' =>
            $_ENV['DBNAME_SESSION_PREFIX'] ?? DatabaseDefaults::DEFAULT_DBNAME_SESSION_PREFIX
    ]),
    'remember' => array(
      'cookie_name' => 'pmqesoxiw318374csb',
      'cookie_expiry' => 604800
    ),
    'session' => array(
      'session_name' => 'user',
      'token_name' => 'token'
    ),
    'msp_auth' => array(
        'with_proxy' => ''
    ),
    // change to https to go secure with all background connections to your MSP Challenge servers
    'msp_server_protocol'   => 'http://',
    // change to https to go secure with all background connections to your MSP Challenge Server Manager
    'msp_servermanager_protocol' => 'http://'
);
