<?php

use ServerManager\API;
use ServerManager\DB;
use ServerManager\GameConfig;
use ServerManager\GameSave;
use ServerManager\GameSession;
use ServerManager\ServerManagerAPIException;
use ServerManager\User;
use App\Domain\Services\SymfonyToLegacyHelper;
use App\Message\GameSave\GameSaveCreationMessage;

ini_set('upload_max_filesize', '200M');
ini_set('post_max_size', '200M');

require __DIR__ . '/../init.php';

$api = new API;
$gamesession = new GameSession;
$gamesave = new GameSave;
$gameconfig = new GameConfig;
$user = new User();

$user->hasToBeLoggedIn();

$gamesession->id = $_POST["session_id"] ?? 0;
if ($gamesession->id == 0 && isset($_FILES['uploadedSaveFile']['tmp_name'])) {
    $gamesave->addFromUpload($_FILES['uploadedSaveFile']['tmp_name'] ?? '');
} elseif ($gamesession->id > 0) {
    $gamesession->get();

    if (!is_null($gamesession->game_config_version_id)) {
        $gameconfig->id = $gamesession->game_config_version_id;
        $gameconfig->get();
    } elseif (!is_null($gamesession->save_id)) {
        $gamesave->id = $gamesession->save_id;
        $gamesave->get();
        $gameconfig->filename = $gamesave->game_config_files_filename;
        $gameconfig->region = $gamesave->game_config_versions_region;
    } else {
        throw new ServerManagerAPIException("Cannot determine key config variables, so not continuing.");
    }

    $gamesave->name = DB::getInstance()->ensure_unique_name($gamesession->name, "name", "game_saves");
    $gamesave->game_config_version_id = $gamesession->game_config_version_id;
    $gamesave->game_config_files_filename = $gameconfig->filename;
    $gamesave->game_config_versions_region = $gameconfig->region;
    $gamesave->game_server_id = $gamesession->game_server_id;
    $gamesave->watchdog_server_id = $gamesession->watchdog_server_id;
    $gamesave->game_creation_time = $gamesession->game_creation_time;
    $gamesave->game_start_year = $gamesession->game_start_year;
    $gamesave->game_end_month = $gamesession->game_end_month;
    $gamesave->game_current_month = $gamesession->game_current_month;
    $gamesave->game_running_til_time = $gamesession->game_running_til_time;
    $gamesave->password_admin = base64_encode($gamesession->password_admin);
    $gamesave->password_player = base64_encode($gamesession->password_player);
    $gamesave->session_state = $gamesession->session_state;
    $gamesave->game_state = $gamesession->game_state;
    $gamesave->game_visibility = $gamesession->game_visibility;
    $gamesave->players_active = $gamesession->players_active;
    $gamesave->players_past_hour = $gamesession->players_past_hour;
    $gamesave->demo_session = $gamesession->demo_session;
    $gamesave->api_access_token = $gamesession->api_access_token;
    $gamesave->server_version = $gamesession->server_version;
    $gamesave->save_type = $_POST['save_type'] ?? 'full';
    $gamesave->save_visibility = "active";
    $gamesave->save_notes = " ";
    $gamesave->id = -1;
    $gamesave->add();
    
    // then perform request for save zip
    //$gamesave->createZip($gamesession);
    SymfonyToLegacyHelper::getInstance()->getMessageBus()->dispatch(
        new GameSaveCreationMessage($gamesession->id, $gamesave->id)
    );
} else {
    throw new Exception("Confusing request, cannot continue.");
}
$api->setStatusSuccess();
$api->setPayload(["gamesave" => get_object_vars($gamesave)]);
$api->Return();
