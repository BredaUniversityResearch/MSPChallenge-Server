<?php

require_once 'init.php';

$user->logout();

require_once 'templates/header.php';
?>
<div id="page-wrapper">
  <div class="container">
    <div id="infobox"></div>
    <h1>Logging out...</h1>
    <p>You have logged out and are being redirected. <a href="https://auth.mspchallenge.info/users/logout.php">Click here</a> if nothing happens.</p>
  </div>
</div>

<?php require_once 'templates/footer.php'; // the final html footer copyright row + the external js calls 


Redirect::to('https://auth.mspchallenge.info/users/logout.php');

?>
