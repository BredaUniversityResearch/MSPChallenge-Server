<?php

use App\Domain\Common\EntityEnums\GameSessionStateValue;
use App\Domain\Services\SymfonyToLegacyHelper;
use App\Entity\ServerManager\GameList;
use App\Entity\ServerManager\GameSave as GameSaveNew;
use App\Entity\ServerManager\GameWatchdogServer;
use App\Message\GameSave\GameSaveLoadMessage;
use ServerManager\API;
use ServerManager\GameSave;
use ServerManager\GameSession;
use ServerManager\User;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

require __DIR__ . '/../init.php';

$api = new API;
$gamesave = new GameSave;
$gamesession = new GameSession;
$user = new User();

$user->hasToBeLoggedIn();

// first check if the save_id referred to can even be obtained
$gamesave->id = $_POST["save_id"] ?? "";
$gamesave->get();

//  then perform any allowed and existing object action requested
/*$allowed_actions = array(
    "load", // called in JS function submitLoadSave
);
if (method_exists($gamesave, $action) && in_array($action, $allowed_actions)) {
    $api->setPayload([$action => $gamesave->$action()]);
}*/
$action = $_POST["action"] ?? "";
if ($action == 'load') {
    $normalizers = [new ObjectNormalizer(null, new CamelCaseToSnakeCaseNameConverter())];
    $serializer = new Serializer($normalizers, []);
    $em = SymfonyToLegacyHelper::getInstance()->getEntityManager();
    $gameSave = $em->getRepository(GameSaveNew::class)->find($gamesave->id);
    $normalizedGameSave = $serializer->normalize(
        $gameSave,
        null,
        $em->getRepository(GameSaveNew::class)->defaultNormalizeContext()
    );
    $newGameSessionFromLoad = $serializer->denormalize(
        $normalizedGameSave,
        GameList::class,
        null,
        $em->getRepository(GameList::class)->defaultDenormalizeContext()
    );
    $newGameSessionFromLoad->setGameSave($gameSave);
    $newGameSessionFromLoad->setName($_POST['name']);
    $newGameSessionFromLoad->setGameWatchdogServer(
        $em->getRepository(GameWatchdogServer::class)->find($_POST['watchdog_server_id'] ?? 1)
    );
    $newGameSessionFromLoad->setSessionState(new GameSessionStateValue('request'));
    $em->persist($newGameSessionFromLoad);
    $em->flush();
    SymfonyToLegacyHelper::getInstance()->getMessageBus()->dispatch(
        new GameSaveLoadMessage($newGameSessionFromLoad->getId(), $gameSave->getId())
    );
}

// now see which associated GameSessions can be obtained
$gamesessions = $gamesession->getList(array("save_id", "=", $gamesave->id));

// ok, return everything
$api->setStatusSuccess();
$api->setPayload(["gamesave" => get_object_vars($gamesave)]);
$api->setPayload(["gamesave_pretty" => $gamesave->getPrettyVars()]);
$api->setPayload(["gamesessions" => $gamesessions]);
$api->Return();
