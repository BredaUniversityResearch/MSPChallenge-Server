<?php

use App\Domain\API\v1\Config;
use ServerManager\Redirect;
use ServerManager\User;

require 'init.php';
$user = new User();
$user->logout();

require_once 'templates/header.php';
// @codingStandardsIgnoreStart
?>
<div id="page-wrapper">
  <div class="container">
    <div id="infobox"></div>
    <h1>Logging out...</h1>
    <p>You have logged out and are being redirected. <a href="<?php echo Config::GetInstance()->getMSPAuthBaseURL(); ?>/users/logout_php">Click here</a> if nothing happens.</p>
  </div>
</div>
<?php
// @codingStandardsIgnoreEnd
require_once 'templates/footer.php'; // the final html footer copyright row + the external js calls
Redirect::to(Config::GetInstance()->getMSPAuthBaseURL() . '/logout');
