<?php

use App\Domain\Helper\Config;
use App\Domain\Services\SymfonyToLegacyHelper;

if (isset($_SESSION)) {
    session_destroy();
}
require 'init.php';
$db = DB::getInstance();
$servermanager = ServerManager::getInstance();
$user = new User();
$errors = [];
$successes = [];

$request = SymfonyToLegacyHelper::getInstance()->getRequest();
if (null !== $request->get('token')) {
    $userId = $user->importTokenFields($request->query->all());
    // user was found through the local database, so we are ready to finalise
    if ($user->find($userId)) {
        // set up local php session
        Session::put(Config::get('session/session_name'), $userId);
        Session::put("currentToken", $request->get('token')); // this is still necessary in case of page refreshes

        // now check if the user is actually allowed to run this MSP Challenge Server Manager
        if ($user->isAuthorised() || $servermanager->freshinstall()) {
            $_SESSION['last_confirm'] = date("Y-m-d H:i:s");
            if ($servermanager->freshinstall()) {
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
    } else {
      //something went wrong
        $msg = lang("SIGNIN_FAIL");
        $msg2 = "Something went wrong. Please try again later.";
        $errors[] = '<strong>'.$msg.'</strong>'.$msg2;
        $link["href"] = $servermanager->GetFullSelfAddress().'logout.php';
        $link["text"] = "You are being redirected. Click here if this doesn't work and nothing happens.";
        Redirect::to($servermanager->GetFullSelfAddress().'logout.php');
    }
}

// if you have no errors to display, then immediately redirect to Authoriser's sso.php page with the proper redirect
//   link
if (empty($errors)) {
    throw new MSPAuthException(401); // this will force a redirect to the login page
}

require_once 'templates/header.php';
?>
<div id="page-wrapper">
  <div class="container">
    <?=resultBlock($errors, $successes);?>
    <div class="row">
      <div class="col-sm-12">

      </div>
    </div>
    <div class="row">
      <div class="col-md-1"></div>
      <div class="col-sm-6 col-md-9"><br>
        <a href='<?php echo $link['href']; ?>'><?php echo $link['text']; ?></a>
        <br><br>
      </div>
    </div>
  </div>
</div>

<?php

require_once 'templates/footer.php'; // the final html footer copyright row + the external js calls ?>
