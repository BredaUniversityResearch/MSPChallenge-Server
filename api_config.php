<?php
$codeBranch = '/';

$GLOBALS['feature_flags'] = array(
    "geoserver_json_importer" => true);

$GLOBALS['api_config'] = array(
    "code_branch" => $codeBranch,
    "msp_auth_with_proxy" => "",
    "game_autosave_interval" => 120,
    "long_request_timeout" => 420,
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
    "database" => array(
        "host" => "127.0.0.1",
        "user" => "root",
        "password" => "",
        "database" => "msp_server_manager",
        "multisession_database_prefix" => "msp_session_",
        "multisession_create_user" => "root",
        "multisession_create_password" => ""
    ),
    "wiki" => array(
        "game_base_url" => "https://knowledge.mspchallenge.info/wiki/",
        "dbUser" => "",
        "dbPass" => "",
        "dbName" => ""
    )
);
