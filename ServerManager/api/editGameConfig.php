<?php
require_once '../init.php'; 

$api = new API;
$gameconfig = new GameConfig;
$user = new User();

$user->hastobeLoggedIn();

// first check if the config_version_id referred to can even be obtained
$gameconfig->id = $_POST["config_version_id"] ?? "";
$gameconfig->get();

// now optionally change all the object vars
$gameconfig->filename = $_POST["filename"] ?? $gameconfig->filename;
$gameconfig->description = $_POST["description"] ?? $gameconfig->description;
$gameconfig->version = $_POST["version"] ?? $gameconfig->version;
$gameconfig->version_message = $_POST["version_message"] ?? $gameconfig->version_message;
$gameconfig->visibility = $_POST["visibility"] ?? $gameconfig->visibility;
$gameconfig->upload_time = $_POST["upload_time"] ?? $gameconfig->upload_time;
$gameconfig->upload_user = $_POST["upload_user"] ?? $gameconfig->upload_user;
$gameconfig->last_played_time = $_POST["last_played_time"] ?? $gameconfig->last_played_time;
$gameconfig->file_path = $_POST["file_path"] ?? $gameconfig->file_path;
$gameconfig->region = $_POST["region"] ?? $gameconfig->region;
$gameconfig->client_versions = $_POST["client_versions"] ?? $gameconfig->client_versions;
$gameconfig->game_config_files_id = $_POST["game_config_files_id"] ?? $gameconfig->game_config_files_id;

// ready to do final actual update
$gameconfig->edit();
$api->setPayLoad(["gameconfig" => get_object_vars($gameconfig)]);
$api->setStatusSuccess();
$api->Return();

?>