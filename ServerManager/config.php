<?php

$codeBranch = '/';

$GLOBALS['config'] = array(
    'ws_server' => [
        'scheme' => 'ws://',
        // if "host" is not set, ServerManager::getInstance()->GetTranslatedServerURL() is used,
        //   which is the same host as the api server.
        // 'host' => 'localhost',
        'port' => 45001,
        'uri' => '',
        'address_modification' => 'none' // none, add_game_session_id_to_port, add_game_session_id_to_uri
    ],
    'code_branch' => $codeBranch,
    'mysql' => array(
        'host' => 'localhost',
        'username' => 'root',
        'password' => '',
        'db' => 'msp_server_manager',
        'multisession_database_prefix' => 'msp_session_',
        'num_pool_connections' => 10
    ),
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
