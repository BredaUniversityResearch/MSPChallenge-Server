<?php

use ServerManager\MSPAuthException;
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
    throw new MSPAuthException(401); // this will force a redirect to the login page
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

require_once 'templates/footer.php'; // the final html footer copyright row + the external js calls ?>
