<?php

use App\Domain\Helper\Config;
use ServerManager\Base;
use ServerManager\DB;
use ServerManager\Redirect;
use ServerManager\ServerManager;
use ServerManager\Session;
use ServerManager\User;
use function ServerManager\lang;

function handleReturnToQuery(ServerManager $serverManager, array &$errors, array &$link): void
{
    if (empty($_GET['returntoquery'])) {
        return;
    }

    $db = DB::getInstance();
    $user = new User();

    $arraySend = array (
      "jwt" => $_GET['returntoquery'],
      "audience" => $serverManager->GetBareHost()
    );

    $resultDecoded = Base::callAuthoriser("checkjwt.php", $arraySend);

    if (!$resultDecoded["success"] || empty($resultDecoded["userdata"])) {
        //something went wrong
        $msg = lang("SIGNIN_FAIL");
        $msg2 = "Something went wrong. Please try again later.";
        $errors[] = '<strong>'.$msg.'</strong>'.$msg2;
        $link["href"] = $serverManager->GetFullSelfAddress().'logout.php';
        $link["text"] = "You are being redirected. Click here if this doesn't work and nothing happens.";
        Redirect::to($serverManager->GetFullSelfAddress().'logout.php');
        return;
    }

    $fields = array(
        'id' => $resultDecoded["userdata"]["id"],
        'username' => $resultDecoded["userdata"]["username"],
        'account_owner' => $resultDecoded["userdata"]["account_owner"],
    );
    $data = $db->get('users', array("username", '=', $resultDecoded["userdata"]["username"]));
    if ($data->count()) {
        $db->update('users', $data->first()->id, $fields);
    } else {
        $db->insert('users', $fields);
    }

    // user was found through the local database, so we are ready to finalise
    if ($user->find($resultDecoded["userdata"]["id"])) {
        // set up local php session
        Session::put(Config::get('session/session_name'), $user->data()->id);
        // this is still necessary in case of page refreshes
        Session::put("currentToken", $resultDecoded["jwt"]);
        // now check if the user is actually allowed to run this MSP Challenge Server Manager
        if ($user->isAuthorised() || $serverManager->freshInstall()) {
            $_SESSION['last_confirm'] = date("Y-m-d H:i:s");
            if ($serverManager->freshInstall()) {
                Redirect::to('install/install.php');
            } else {
                Redirect::to('index.php');
            }
        } else {
            $user->logout();
            $msg = lang("SIGNIN_FAIL");
            $msg2 = 'You don\'t have access to this particular Server Manager. Please contact this  ' .
                'Server Manager\'s primary user if you would like to obtain access.';
            $errors[] = '<strong>' . $msg . '</strong>' . $msg2;
            $link["href"] = "https://community.mspchallenge.info";
            $link["text"] = "Return to the MSP Challenge Community wiki.";
        }
    }
}
