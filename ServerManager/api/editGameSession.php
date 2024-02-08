<?php

use App\Domain\Services\SymfonyToLegacyHelper;
use App\Message\GameList\GameListCreationMessage;
use ServerManager\API;
use ServerManager\GameSession;
use ServerManager\User;

require __DIR__ . '/../init.php';

$api = new API;
$gamesession = new GameSession;
$user = new User();

$user->hasToBeLoggedIn();

// first check if the session_id referred to can even be obtained
$gamesession->id = $_POST["session_id"] ?? "";
$gamesession->get();

// now optionally change all the object vars
$gamesession->processPostedVars();

// then perform any allowed and existing object action requested
$allowed_actions = array(
    "setUserAccess", // called in JS function saveUserAccess
    "upgrade", // called in JS function callUpgrade
    "changeGameState", // called in JS functions startSession, pauseSession
    "processZip", // called by server API gamesession/ArchiveGameSessionInternal
    "recreate", // called by JS function RecreateSession
    // called by websocket server GameTick >> UpdateGameDetailsAtServerManager and by JS function toggleDemoSession
    "demoCheck"
);
$action = $_POST["action"] ?? "";
if (method_exists($gamesession, $action) && in_array($action, $allowed_actions)) {
    $api->setPayLoad([$action => $gamesession->$action()]);
}

// ready to do final actual update
$gamesession->edit();
// alternative to recreate function in GameSession class
if ($action == 'recreate') {
    SymfonyToLegacyHelper::getInstance()->getMessageBus()->dispatch(new GameListCreationMessage($gamesession->id));
}
$api->setPayLoad(["gamesession" => get_object_vars($gamesession)]);
$api->setStatusSuccess();
$api->Return();
