<?php

use App\Domain\Services\SymfonyToLegacyHelper;

require __DIR__ . '/../init.php';
$api = new API;
$user = new User();

$user->hastobeLoggedIn();

$request = SymfonyToLegacyHelper::getInstance()->getRequest();
if (null === $request->get('token')) {
    $api->setMessage("Cannot do anything without a token.");
    $api->Return();
}

// check the old token and get the new token in one go
$response = Base::postCallAuthoriser('token/refresh', [
    'refresh_token' => $user->data()->refresh_token
]);

// if old accepted and new returned
if (!empty($response['token'])) {
    Session::put('currentToken', $response['token']);
    $user->importTokenFields($response);
    $api->setStatusSuccess();
    $api->setPayload(['jwt' => $response["token"]]);
    $api->Return();
}

$api->setMessage('Did not obtain new token.');
$api->Return();
