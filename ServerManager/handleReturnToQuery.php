<?php

use App\Domain\Helper\Config;
use App\Domain\Services\SymfonyToLegacyHelper;
use ServerManager\Redirect;
use ServerManager\ServerManager;
use ServerManager\Session;
use ServerManager\User;

function handleReturnToQuery(ServerManager $serverManager, array &$errors, array &$link): void
{
    $request = SymfonyToLegacyHelper::getInstance()->getRequest();
    if (null === $request->get('token')) {
        return;
    }

    $user = new User();
    $userId = $user->importTokenFields($request->query->all());

    // find user in the local database, so we can finalise
    if ($userId === null || !$user->find($userId)) {
        //something went wrong
        $msg = lang("SIGNIN_FAIL");
        $msg2 = "Something went wrong. Please try again later.";
        $errors[] = '<strong>'.$msg.'</strong>'.$msg2;
        $link["href"] = $serverManager->GetFullSelfAddress().'logout.php';
        $link["text"] = "You are being redirected. Click here if this doesn't work and nothing happens.";
        Redirect::to($serverManager->GetFullSelfAddress().'logout.php');
        return;
    }

    // set up local php session
    Session::put(Config::get('session/session_name'), $userId);
    Session::put("currentToken", $request->get('token')); // this is still necessary in case of page refreshes

    // now check if the user is actually allowed to run this MSP Challenge Server Manager
    if ($user->isAuthorised() || $serverManager->freshinstall()) {
        $_SESSION['last_confirm'] = date("Y-m-d H:i:s");
        if ($serverManager->freshinstall()) {
            Redirect::to('install/install.php');
        } else {
            Redirect::to('index.php');
        }
        return;
    }

    $user->logout();
    $msg = lang("SIGNIN_FAIL");
    $msg2 = 'You don\'t have access to this particular Server Manager. Please contact this  ' .
    'Server Manager\'s primary user if you would like to obtain access.';
    $errors[] = '<strong>' . $msg . '</strong>' . $msg2;
    $link["href"] = "https://community.mspchallenge.info";
    $link["text"] = "Return to the MSP Challenge Community wiki.";
}
