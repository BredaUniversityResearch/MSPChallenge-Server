<?php
/*
based on...

UserSpice 5
An Open Source PHP User Management System
by the UserSpice Team at http://UserSpice.com

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
ob_start();
?>

<?php require_once '../init.php'; 
$servermanager = ServerManager::getInstance();

//install the database tables and content

if (!$user->isLoggedIn()) { die(); } 
if (!$servermanager->freshinstall()) { die(); }

$db = DB::getInstance();
require_once '../templates/header.php';

if ($servermanager->install($user)) {
  //send it to the authoriser to store with successfully logged in user_id
  $params = array("jwt" => Session::get("currentToken"), "server_id" => $servermanager->GetServerID(), "server_name" => $servermanager->GetServerName(), "audience" => $servermanager->GetBareHost());
  $url_freshinstall = Config::get('msp_auth/api_endpoint').'freshinstalljwt.php';
  $response = CallAPI("POST", $url_freshinstall, $params);
  $freshinstall = json_decode($response);
  if ($freshinstall->success) {
      //echo 'settings sent <br/>'; ?>
      <div id="page-wrapper">
      	<div class="container">
      		<div id="infobox"></div>
          <h1>New installation</h1>
          <p>This is a new installation of the Server Manager application.</p>
          <p>You, <strong><?=$user->data()->username;?></strong>, are now the primary user of this Server Manager. This means that you can not only use this application,
           but you can also add other users to it through the <a href="https://auth.mspchallenge.info">MSP Challenge Authoriser</a> application. You don't have to do this
           right now of course, or at all for that matter.</p>
          <p>You can go ahead and <a href="<?php echo $url_app_root;?>manager.php">set up your first MSP Challenge server</a>.</p>
          <p>We also recommend you enter your computer's proper IP address or full-qualified domain name <a href="<?php echo $url_app_root;?>server_manager.php">under Settings</a>.</p>
        </div>
      </div>
      <?php
  }
  else {
    ?>
    <div id="page-wrapper">
      <div class="container">
        <div id="infobox"></div>
        <h1>New installation</h1>
        <p>Unfortunately, something went wrong. Please try again later or get in touch with us through <a href="http://community.mspchallenge.info">community.mspchallenge.info</a> to get support.</p>
      </div>
    </div>
    <?php
  }
}
?>
<!-- footers -->
<?php require_once $abs_app_root.$url_app_root.'templates/footer.php'; // the final html footer copyright row + the external js calls ?>
