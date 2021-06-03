<?php

require_once '../init.php'; 

$api = new API;
$gamesession = new GameSession;
$gameconfig = new GameConfig;
$watchdog = new Watchdog;
$user = new User();

$user->hastobeLoggedIn();

// first check if the session_id referred to can even be obtained
$gamesession->id = $_POST["session_id"] ?? "";
$gamesession->get();

// now see if the associated config can be obtained
if (!empty($gamesession->save_id)) // session eminates from a save as save_id is neither null nor 0
{
    $gamesave = new GameSave;
    $gamesave->id = $gamesession->save_id;
    $gamesave->get();
    $gameconfig->description = "";
    $gameconfig->filename = $gamesave->game_config_files_filename;
    $gameconfig->version_message = "";
    $gameconfig->version = " from a save";
}
else // session eminates from a config file, so from scratch
{
    $gameconfig->id = $gamesession->game_config_version_id;
    $gameconfig->get();
}  


// now see if the associated watchdog can be obtained
$watchdog->id = $gamesession->watchdog_server_id;
$watchdog->get();

// ok, return everything
$gamesession_vars = get_object_vars($gamesession);
$gamesession_vars["gamearchive"] = $gamesession->getArchive();
$gamesession_vars["gameupgradable"] = ServerManager::getInstance()->CheckForUpgrade($gamesession->server_version);
$api->setPayload(["gamesession" => $gamesession_vars]);
$api->setPayload(["gamesession_pretty" => $gamesession->getPrettyVars()]);
$api->setPayload(["gamecountries" => $gamesession->getCountries()]);
$api->setPayload(["gameconfig" => get_object_vars($gameconfig)]);
$api->setPayload(["watchdog" => get_object_vars($watchdog)]);
$api->setStatusSuccess();
$api->Return();

?>