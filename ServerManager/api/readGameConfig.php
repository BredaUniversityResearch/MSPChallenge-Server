<?php

require_once '../init.php'; 

$api = new API;
$gameconfig = new GameConfig;
$user = new User();

$user->hastobeLoggedIn();

// first check if the config_version_id referred to can even be obtained
$gameconfig->id = $_POST["config_version_id"] ?? "";
$gameconfig->game_config_files_id = $_POST["config_file_id"] ?? "";
$gameconfig->get();

// ok, return everything
$api->setPayload(["gameconfig" => get_object_vars($gameconfig)]);
$api->setPayload(["gameconfig_pretty" => $gameconfig->getPrettyVars()]);
$api->setStatusSuccess();
$api->Return();

?>