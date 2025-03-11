<?php

use App\Domain\Common\DatabaseDefaults;
use App\Domain\Services\ConnectionManager;

$connectionConfig = ConnectionManager::getInstance()->getConnectionConfig();
$codeBranch = '/';

$GLOBALS['feature_flags'] = array(
    "geoserver_json_importer" => true);

$GLOBALS['api_config'] = array(
    "code_branch" => $codeBranch,
    "game_autosave_interval" => 120,
    "long_request_timeout" => 840, // 420 originally, was doubled for Eastern Med Sea edition
    "unit_test_logger" => array(
        "enabled" => false,
        "intermediate_folder" => "export/",
        "request_filter" => array (
            "ignore" => array(
                "game::getcurrentmonth",
                "mel::shouldupdate",
                "security::checkaccess"
            )
        )
    ),
    "database" => array_merge($connectionConfig, [
        "database" => $_ENV['DBNAME_SERVER_MANAGER'] ?? DatabaseDefaults::DEFAULT_DBNAME_SERVER_MANAGER,
        "multisession_database_prefix" =>
            $_ENV['DBNAME_SESSION_PREFIX'] ?? DatabaseDefaults::DEFAULT_DBNAME_SESSION_PREFIX,
        "multisession_create_user" => $_ENV['DATABASE_CREATOR_USER'] ?? DatabaseDefaults::DEFAULT_DATABASE_CREATOR_USER,
        "multisession_create_password" =>
            $_ENV['DATABASE_CREATOR_PASSWORD'] ?? DatabaseDefaults::DEFAULT_DATABASE_CREATOR_PASSWORD
    ]),
    "wiki" => array(
        "game_base_url" => "https://knowledge.mspchallenge.info/wiki/",
        "dbUser" => "",
        "dbPass" => "",
        "dbName" => ""
    )
);
