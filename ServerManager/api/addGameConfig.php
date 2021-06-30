<?php
require_once '../init.php'; 

$api = new API;
$gameconfig = new GameConfig;
$user = new User();

$user->hastobeLoggedIn();

// first check if we're just uploading a next version
$gameconfig->game_config_files_id = $_POST["game_config_files_id"] ?? -1;
if ($gameconfig->game_config_files_id > -1) {
    $gameconfig->get();
    $gameconfig->version++;
}
else {
    $gameconfig->filename = $_POST["filename"] ?? "";
    $gameconfig->version = 1;
}

// now go through the form vars
$gameconfig->uploadTemp($_FILES['config_file']['tmp_name']);
$gameconfig->description = $_POST["description"] ?? $gameconfig->description;
$gameconfig->version_message = $_POST["version_message"] ?? $gameconfig->version_message;
$gameconfig->visibility = "active";
$gameconfig->upload_time = time();
$gameconfig->upload_user = $user->data()->id;
$gameconfig->last_played_time = 0;

// ready to add
$gameconfig->add();
$api->setPayload(["gameconfig" => get_object_vars($gameconfig)]);
$api->setStatusSuccess();
$api->Return();

?>