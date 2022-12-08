<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols
use ServerManager\Redirect;
use ServerManager\ServerManager;

if (isset($_SESSION)) {
    session_destroy();
}
require 'init.php';
require 'handleReturnToQuery.php';
$serverManager = ServerManager::getInstance();
$errors = [];
$link = [];
handleReturnToQuery($serverManager, $errors, $link);
// if you have no errors to display, then immediately redirect to Authoriser's sso.php page with the proper redirect
//   link
if (empty($errors)) {
    Redirect::to(
        ServerManager::getInstance()->GetMSPAuthURL(). '/users/sso.php?redirect='.
        urlencode($serverManager->GetFullSelfAddress().'login.php')
    );
}

require_once 'templates/header.php';
?>
<div id="page-wrapper">
  <div class="container">
    <?php resultBlock($errors, []);?>
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

require_once 'templates/footer.php'; // the final html footer copyright row + the external js calls

function resultBlock($errors, $successes): void
{
    //Error block
    if (count($errors) > 0) {
        echo "<div class='alert alert-danger alert-dismissible' role='alert'> " .
            "<button type='button' class='close' data-dismiss='alert' aria-label='Close'>".
            "<span aria-hidden='true'>&times;</span></button><ul style='padding-left:1.25rem !important'>";
        foreach ($errors as $error) {
            echo "<li>".$error."</li>";
        }
        echo "</ul>";
        echo "</div>";
    }

    //Success block
    if (count($successes) > 0) {
        echo "<div class='alert alert-success alert-dismissible' role='alert'> " .
            "<button type='button' class='close' data-dismiss='alert' aria-label='Close'>" .
            "<span aria-hidden='true'>&times;</span></button><ul style='padding-left:1.25rem !important'>";
        foreach ($successes as $success) {
            echo "<li>" . $success . "</li>";
        }
        echo "</ul>";
        echo "</div>";
    }
}

