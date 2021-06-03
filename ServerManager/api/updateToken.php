<?php 
require_once '../init.php';
$api = new API;
$user = new User();

$user->hastobeLoggedIn();

if (empty($_POST['token'])) {
    $api->setMessage("Cannot do anything without a token.");
    $api->Return();
}

// check the old token and get the new token in one go
$checkoldgetnew = Base::callAuthoriser(
    'checkjwt.php', 
    array(
        "jwt" => $_POST['token'],
        "audience" => ServerManager::getInstance()->GetBareHost()
    ) 
);

// if old accepted and new returned
if ($checkoldgetnew["success"] && !empty($checkoldgetnew["jwt"])) {
    Session::put("currentToken", $checkoldgetnew["jwt"]);
    $api->setStatusSuccess();
    $api->setPayload(['jwt' => $checkoldgetnew["jwt"]]);
    $api->Return();
}

$api->setMessage('Did not obtain new token.');
$api->Return();




?>