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
require_once 'init.php'; 

$servermanager = ServerManager::getInstance();

if (!$user->isAuthorised()) {
	if ($user->isLoggedIn()) {
		Redirect::to($url_app_root.'logout.php');
	}
	else {
		Redirect::to($url_app_root.'index.php');
	}
} 

require_once $abs_app_root.$url_app_root.'templates/header.php'; ?>

<div id="page-wrapper">
	<div class="container">
		<div class="row">
			<div class="col-sm-12">
				<div id="infobox"></div>
				<h2>Settings</h2>

				<div class="card">
					<h5 class="card-header">Managing Access - <?php echo $servermanager->GetServerName(); ?></h5>
					<div class="card-body">
						<p class="card-text">
							By default only the user who completed the installation of the MSP Challenge Server has access to this Server Manager. That person becomes the Server Manager's administrator. Any such administrator can grant access to other MSP Challenge users as well. For this to work two conditions need to be met:
							<ol><li>The other user will need to have an account with the MSP Challenge Authoriser too.</li>
								<li>This Server Manager application needs to be accessible from the other user's computer through the IP address stated at the top of your screen (Current Address).</li></ol>
								Administrators of this Server Manager can arrange for access by other users through the MSP Challenge Authoriser. If you are an administrator of this Server Manager, you can click on the link below, log in again, and continue from there.</p>
							<p class="card-text">
							<a href="https://auth.mspchallenge.info/usersc/server_manager.php" class="btn btn-primary" role="button">Go to the MSP Challenge Authoriser</a>
						</p>
					</div>
				</div>
				<div>&nbsp;</div>
				
				<div class="card">
					<h5 class="card-header">Simulation Servers</h5>
					<div class="card-body">
						<p class="card-text">Here you can define additional simulation servers, useful if you would rather have the simulations run on a different computer than this one. To get this to work, make sure you've installed the MSP Challenge server software on that computer as well.</p>
						<form class="form-horizontal" role="form" data-toggle="validator" id="formNewWatchdogServer" enctype="multipart/form-data">
							<div class="row">
								<div class="col">
									<label for="newWatchdogServerName">Simulation Server Name</label>
									<input type="text" class="form-control" id="newWatchdogServerName" required="required" placeholder="required">
								</div>
								<div class="col">
									<label for="newWatchdogServerAddress">IP Address</label>
									<input type="text" class="form-control" id="newWatchdogServerAddress" required="required" placeholder="required">
								</div>
								<div class="col" style="padding-top:28px;">
									<button type="button" class="btn btn-primary" onClick="submitNewWatchdogServer();">Add</button>
								</div>
							</div>
						</form>
						<p>
							<table id="watchdogServerTable" class="table table-sm table-hover">
								<thead>
									<tr>
										<th>Simulation Server Name</th>
										<th>IP Address</th>
										<th></th>
									</tr>
								</thead>
								<tbody id="watchdogServerBody">
								</tbody>
							</table>
						</p>
					</div>
					</br/>
				</div>
				<div>&nbsp;</div>	
							
				<div class="card">
					<h5 class="card-header">MSP Challenge Server machine's address</h5>
					<div class="card-body">
						<form class="form-horizontal" role="form" data-toggle="validator" id="formNewWatchdogServer" enctype="multipart/form-data">
							<p class="card-text">The default value is 'localhost'. This value makes MSP Challenge determine your current IP address automatically. In some cases that automatically determined IP address listed above ('Current Address') turns out to be erroneous or inaccessible, causing connection problems for MSP Challenge clients and any externally hosted background simulations. In those cases you can specify the public IP address or fully-qualified domain name of this machine below. Note that local IP addresses tend to change, so as soon as that happens you will have to specify the new exact IP address here again. If everything works well, then leave this setting alone.</p>
							<div class="row">
								<div class="col">
									<label for="GameServerAddress">MSP Challenge Server machine's address</label>
									<input type="text" class="form-control" id="ServerAddress" required="required"/>
									<input type="hidden" id="ServerID" value="1"/>
									<input type="hidden" id="ServerName" value="Default: the server machine"/>
								</div>
								<div class="col" style="padding-top:28px;">
									<button type="button" class="btn btn-primary" onClick="submitNewServer();">Save</button>
								</div>
							</div>
						</form>
					</div>
					</br/>
				</div>
				
			</div>
		</div>
		<br/>

	</div>
</div>

<!-- Place any per-page javascript here -->
<script type="text/javascript">
	var currentToken = '<?php echo Session::get("currentToken"); ?>';
	regularupdateToken = setInterval(function() {
		updatecurrentToken();
	}, 60000);
	$(document).ready(function() {
		WatchdogServerList();
		GetServerAddr();
		$('[data-toggle="tooltip"]').tooltip(); 
	});
</script>
	
<!-- footers -->
<?php require_once $abs_app_root.$url_app_root.'templates/footer.php'; // the final html footer copyright row + the external js calls ?>
