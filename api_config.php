<?php
$codeBranch = '/';

$GLOBALS['experimental_feature_flags'] = array(
    "geoserver_json_importer" => true);

$auth_url = "https://auth.mspchallenge.info/usersc/plugins/apibuilder/authmsp/"; 
$GLOBALS['api_config'] = array(
    "code_branch" => $codeBranch,
    "geoserver_url" => "https://geo.mspchallenge.info/geoserver/",
    "geoserver_credentials_endpoint" => $auth_url."geocredjwt.php",
    "authserver_log_session_info_endpoint" => $auth_url."logcreatejwt.php",
    "msp_auth_with_proxy" => "",
    "game_autosave_interval" => 120,
    "long_request_timeout" => 420,
    "wait_for_simulations_in_dev" => true,
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
