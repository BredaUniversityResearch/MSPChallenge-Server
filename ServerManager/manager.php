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
$user = new User();
if (!$user->isAuthorised()) {
	if ($user->isLoggedIn()) {
		Redirect::to(ServerManager::getInstance()->GetServerManagerFolder().'logout.php');
	}
	else {
		Redirect::to(ServerManager::getInstance()->GetServerManagerFolder().'index.php');
	}
} 

require_once ServerManager::getInstance()->GetServerManagerRoot() . 'templates/header.php'; ?>

<div id="page-wrapper">
	<div role="main" class="container">
		<div id="infobox"></div>
		<ul class="nav nav-tabs" id="myTab" role="tablist">
			<li class="nav-item">
				<a href="#tabSessionsList" class="nav-link active" role="tab" data-toggle="tab" aria-controls="tabSessionsList" aria-selected="true">
					<i class="fa fa-list-alt"></i> <span class="tabSessionsListText">Sessions</span>
				</a>
			</li>
			<li class="nav-item">
				<a href="#tabSavesList" class="nav-link" role="tab" data-toggle="tab" aria-controls="tabSavesList" aria-selected="false">
					<i class="fa fa-save"></i> <span class="tabSavesListText">Saves</span>
				</a>
			</li>
			<li class="nav-item">
				<a href="#tabConfigVersions" class="nav-link" role="tab" data-toggle="tab" aria-controls="tabConfigVersions" aria-selected="false">
					<i class="fa fa-file-text"></i> <span class="tabConfigVersionsText">Configurations</span>
				</a>
			</li>
			<li class="nav-item">
				<a href="#tabSettings" class="nav-link" role="tab" data-toggle="tab" aria-controls="tabSettings" aria-selected="false">
					<i class="fa fa-wrench"></i> <span class="tabSettingsText">Settings</span>
				</a>
			</li>	

		</ul>

		<div class="tab-content" id="myTabContent">
			<div class="tab-pane fade show active" id="tabSessionsList" role="tabpanel" aria-labelledby="tabSessionsList-tab">
				<div class="row">
					<div class="col-md-12 flex-row">
						<button type="button" id="btnCreateServer" class="btn btn-primary  pull-left" data-toggle="modal" data-target="#modalNewSession"><i class="fa fa-plus-circle" title="Create new session"></i> New Session</button>
						<button id="buttonRefreshSessionsList" class="btn btn-primary"><i class="fa fa-refresh" title="Refresh" id="buttonRefreshSessionsListIcon"></i> Refresh</button>
					</div>
					<div class="col-md-12" id="sessionsList">
						<div class="float-left">
							Here you can create a new MSP Challenge session, and administer existing ones.
						</div>
						<div class="well well-sm float-right" >Filter:
							<span id="radioFilterSessionsList">
								<div class="form-check form-check-inline">
									<input class="form-check-input" type="radio" name="inlineRadioOptions" id="inlineRadio1" value="public" checked>
									<label class="form-check-label" for="inlineRadio1">Active</label>
								</div>
								<!-- div class="form-check form-check-inline">
									<input class="form-check-input" type="radio" name="inlineRadioOptions" id="inlineRadio2" value="private">
									<label class="form-check-label" for="inlineRadio2">Private</label>
								</div -->
								<div class="form-check form-check-inline">
									<input class="form-check-input" type="radio" name="inlineRadioOptions" id="inlineRadio3" value="archived">
									<label class="form-check-label" for="inlineRadio3">Archived</label>
								</div>
							</span>
						</div>
					</div>

					<div class="col-md-12">
						
						<div class="table-responsive">
							<table id="sessionsTable" class="table table-hover table-striped">
								<thead>
									<tr>
										<th>ID</th>
										<th>Session Name</th>
										<th>Configuration</th>
										<th>Players</th>
										<th>State</th>
										<th>Current Month</th>
										<th>Ending Month</th>
										<th class="text-center">Quick Actions</th>
									</tr>
								</thead>
								<tbody id="sessionsListtbody">
									<tr>
										<td id="sessionsListtd" colspan="8" class="text-center"><span><i class="fa fa-exclamation-triangle" aria-hidden="true"></i></span> waiting for data...</td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>
			<div class="tab-pane fade" id="tabSavesList" role="tabpanel" aria-labelledby="tabSavesList-tab">
				<div class="row">
					<div class="col-md-12 flex-row">
						<button type="button" id="btnLoadSave" class="btn btn-primary pull-left" data-toggle="modal" data-target="#modalUploadSave"><i class="fa fa-plus-circle" title=""></i> Upload Session Save</button>
						<button id="buttonRefreshSavesList" class="btn btn-primary"><i class="fa fa-refresh" title="Refresh" id="buttonRefreshSavesListIcon"></i> Refresh</button>
					</div>
					<div class="col-md-12" id="savesList">
						<div class="float-left">
							Here you can review and reuse saves of MSP Challenge sessions.
						</div>
						<div class="well well-sm float-right" >
							Filter:
							<span id="radioFilterSavesList">
								<div class="form-check form-check-inline">
									<input class="form-check-input" type="radio" name="inlineRadioOptionSaves" id="inlineRadio1" value="active" checked>
									<label class="form-check-label" for="inlineRadio1">Active</label>
								</div>
								<div class="form-check form-check-inline">
									<input class="form-check-input" type="radio" name="inlineRadioOptionSaves" id="inlineRadio3" value="archived">
									<label class="form-check-label" for="inlineRadio3">Archived</label>
								</div>
							</span>
						</div>
					</div>

					<div class="col-md-12">
						<div class="table-responsive">
							<table id="savesTable" class="table table-hover table-striped">
								<thead>
									<tr>
										<th>Created</th>
										<th>Session Name</th>
										<th>Month / Year</th>
										<th>Configuration</th>
										<th>Type</th>
										<th class="text-center">Quick Actions</th>
									</tr>
								</thead>
								<tbody id="savesListtbody">
									<tr>
										<td id="savesListtd" colspan="8" class="text-center"><span><i class="fa fa-exclamation-triangle" aria-hidden="true"></i></span> waiting for data...</td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>
			<div class="tab-pane fade" id="tabConfigVersions" role="tabConfigVersions" aria-labelledby="tabConfigVersions-tab">
				<div class="row">
				<div class="col-md-12 flex-row">
					<button type="button" class="btn btn-primary pull-left" data-toggle="modal" data-target="#modalNewConfigFile"><i class="fa fa-plus-circle" title="Create a new Configuration"></i> New Configuration</button>
					<button id="buttonRefreshConfigVersionsList" class="btn btn-primary"><i class="fa fa-refresh" title="Refresh" id="buttonRefreshConfigVersionsListIcon"></i> Refresh</button>
				</div>
				<div class="col-md-12" id="configVersionsList">
					<div class="float-left">
						Here you can upload a new MSP Challenge session configuration file, and administer existing ones.
					</div>
					<div class="well well-sm float-right" style="text-align: end;">
						Filter:
						<span id="radioFilterConfigVersionsList">
							<div class="form-check form-check-inline">
								<input class="form-check-input" type="radio" name="radioOptionConfig" id="radioOptionConfig1" value="active" checked>
								<label class="form-check-label" for="radioOptionConfig1">Active</label>
							</div>
							<div class="form-check form-check-inline">
								<input class="form-check-input" type="radio" name="radioOptionConfig" id="radioOptionConfig2" value="archived">
								<label class="form-check-label" for="radioOptionConfig2">Archived</label>
							</div>

						</span>

					</div>
				</div>
				<div class="col-md-12">
						<div class="table-responsive">
							<table id="configVersionsTable" class="table table-hover">
								<thead>
									<tr>
										<th></th>
										<th>Name</th>
										<th>Version</th>
										<th>Date uploaded</th>
										<th>Uploaded by</th>
										<th>Last used</th>
										<th class="text-center">Quick Actions</th>
									</tr>
								</thead>
								<tbody id="configVersionsListtbody">
									<tr>
										<td id="configVersionsListtd" colspan="7" class="text-center"><span><i class="fa fa-exclamation-triangle" aria-hidden="true"></i></span> waiting for data...</td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>
				</div> <!-- /tabConfigVersions -->
			</div>
			<div class="tab-pane fade" id="tabSettings" role="tabpanel" aria-labelledby="tabSettings-tab">
			<p>Here you can change various settings of this MSP Challenge server.</p>
				<div class="card-deck">	
					
					<div class="card">
						<div class="card-header">
							User Access
						</div>
						<div class="card-body">
							<p class="card-text">The other users that should have access to this MSP Challenge server. Note that only this server's administrator(s) can change this.</p>
						</div>
						<div class="card-footer">
							<a href="https://auth.mspchallenge.info/usersc/server_manager.php" class="btn btn-primary" role="button">Change</a>
						</div>
					</div>
					<div class="card">
						<div class="card-header">
							Simulation Servers
						</div>
						<div class="card-body">
							<p class="card-text">Any simulation servers additional to this one, if you would rather have the background simulations run on a different computer than this one.</p>
						</div>
						<div class="card-footer">
							<button type="button" id="btnSimServer" class="btn btn-primary  pull-left" data-toggle="modal" data-target="#modalNewSimServers">Change</button>
						</div>
					</div>
					<div class="card">
						<div class="card-header">
							GeoServers
						</div>
						<div class="card-body">
							<p class="card-text">Any GeoServers to which you have access, and which have been set up to work with the MSP Challenge Simulation Platform, in addition to the default public MSP Challenge GeoServer used by default.</p>
						</div>
						<div class="card-footer">
							<button type="button" id="btnGeoServer" class="btn btn-primary  pull-left" data-toggle="modal" data-target="#modalNewGeoServers">Change</button>
						</div>
					</div>
				<?php /* </div>
				<div class="card-deck">*/ ?>
					<div class="card">
						<div class="card-header">
							Server Address
						</div>
						<div class="card-body">
							<p class="card-text">The address of this MSP Challenge server. Only change this if you are experiencing connection problems and expect setting an IP address or fully-qualified domain name here would help.<?php //The address of this MSP Challenge server, its name, and its description text users see when they connect to it using the MSP Challenge client.?></p>
						</div>
						<div class="card-footer">
							<button type="button" id="btnServerDetails" class="btn btn-primary  pull-left" data-toggle="modal" data-target="#modalServerDetails">Change</button>
						</div>
					</div>
					<?php /*
					<div class="card">
						<div class="card-header">
							News feed
						</div>
						<div class="card-body">
							<p class="card-text">The link to an Atom encoded news feed users see when they connect to this MSP Challenge server using the MSP Challenge client and click on News.</p>
						</div>
						<div class="card-footer">
							<a href="#" class="btn btn-primary">Change</a>
						</div>
					</div>
					<div class="card">
						<div class="card-header">
							Introduction Videos
						</div>
						<div class="card-body">
							<p class="card-text">The list of links of online videos that users should be able to see when they connect to this MSP Challenge server using the MSP Challenge client and click on Introduction.</p>
						</div>
						<div class="card-footer">
							<a href="#" class="btn btn-primary">Change</a>
						</div>
					</div>
					*/ ?>
				</div>
			</div>
		</div>

		<!-- Modal Session info -->
		<div class="modal fade" id="sessionInfo" tabindex="-1" role="dialog" aria-labelledby="sessionModalCenterTitle" aria-hidden="true">
			<div class="modal-dialog modal-wide" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<input type="text" id="sessionModalCenterTitle" class="form-control modal-title" style="font-size: 1.25rem;" onchange="" />
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="table-responsive">
							<table class="table table-hover table-border-left" id="sessionInfoTable">
								<tr>
									<th scope="col">ID</th>
									<td class="text-right" id="sessionInfoID"></td>
									<th scope="col">Visibility</th>
									<td class="text-right" id="sessionInfoVisibility"></td>
								</tr>
								<tr>
									<th scope="col">Simulation state</th>
									<td class="text-right" id="sessionInfoGameState"></td>
									<th scope="col">Session state</th>
									<td class="text-right" id="sessionInfoSessionState"></td>
								</tr>
								<tr>
									<th scope="col">Current Month</th>
									<td class="text-right" id="sessionInfoCurrentMonth"></td>
									<th scope="col">Ending Month</th>
									<td class="text-right" id="sessionInfoEndMonth"></td>
								</tr>
								<tr>
									<th scope="col">Setup Time</th>
									<td class="text-right" id="sessionInfoGameCreationTime"></td>
									<th scope="col" >Time Until End</th>
									<td class="text-right" id="sessionInfoGameRunningTilTime"></td>
								</tr>
								<tr>
									<th scope="col">Config File</th>
									<td class="text-right">
										<div id="sessionInfoConfigFilename"></div>
										<div id="sessionInfoConfigVersion"></div>
									</td>
									<th scope="col">Simulation Server</th>
									<td class="text-right">
										<div id="sessionInfoWatchdogName"></div>
										<div id="sessionInfoWatchdogAddress"></div>
									</td>
								</tr>
								<tr>
									<th scop="col">Active Players</th>
									<td class="text-right" id="sessionInfoActivePlayers"></td>
									<th scope="col">Demo Session?</th>
									<td class="text-right" id="sessionInfoDemoStatus"></td>
								</tr>
								<tr class="table-info">
									<td colspan="2">
										<button id="sessionInfoButtonStartPause" class="btn btn-success btn-sm" onclick=""></button> 
										<button id="sessionInfoButtonDemoToggle" class="btn btn-info btn-sm" onclick=""></button>
									</td>
									<td colspan="2" class="text-right">
										<button id="sessionInfoButtonRecreateSession" class="btn btn-warning btn-sm" onclick=""></button>
										<button id="sessionInfoButtonArchiveDownload" class="btn btn-warning btn-sm" onclick=""></button>
										<button id="sessionInfoButtonUpgrade" class="btn btn-warning btn-sm" onclick=""></button>
									</td>
								</tr>
								<tr class="table-info">
									<td colspan="2">
										<button id="sessionInfoButtonSaveFull" class="btn btn-secondary btn-sm" onclick=""></button>
										<button id="sessionInfoButtonSaveLayers" class="btn btn-secondary btn-sm" onclick=""></button>
									</td>
									<td colspan="2" class="text-right">
										<button id="sessionInfoButtonUserAccess" data-toggle="modal" data-target="#sessionUsers" class="btn btn-secondary btn-sm" onclick=""></button>
										<button id="sessionInfoButtonExportPlans" class="btn btn-secondary btn-sm" onclick=""></button>
									</td>
								</tr>
								<tr class="table-info">
									<td colspan="4" class="text-left">
										<button id="sessionInfoButtonServerLog" onclick="toggleSessionInfoLog()" class="btn btn-secondary btn-sm"><i class="fa fa-bars" aria-hidden="true"></i></i> Show/Hide Session Creation Log</button>
									</td>
								</tr>
								<tr>
									<td colspan="4" class="text-center p-0">
										<div id="sessionInfoLog" style="width: 100%; height: 115px; overflow-y:auto; font-size:14px; background-color: #e9ecef; text-align: left; resize: both; display: none;">
										</div>
									</td>
								</tr>
							</table>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Modal: new Session -->
		<div class="modal fade" id="modalNewSession" tabindex="-1" role="dialog" aria-labelledby="newSessionModalLabel" aria-hidden="true">
			<div class="modal-dialog modal-wide" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title" id="newSessionModalLabel">Create New Session</h4>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body">
					
						<ul class="nav nav-tabs" id="myTab" role="tablist">
							<li class="nav-item" role="presentation">
								<a class="nav-link active" id="NewSessionModalDefault-tab" data-toggle="tab" href="#NewSessionModalDefault" role="tab" aria-controls="NewSessionModalDefault" aria-selected="true">By Selecting a Configuration File</a>
							</li>
							<li class="nav-item" role="presentation">
								<a class="nav-link" id="NewSessionModalLoadSave-tab" data-toggle="tab" href="#NewSessionModalLoadSave" role="tab" aria-controls="NewSessionModalLoadSave" aria-selected="false">Or By Loading a Saved Session</a>
							</li>
						</ul>
						<div class="tab-content" id="myNewSessionModalTabContent">
							<div class="tab-pane fade show active" id="NewSessionModalDefault" role="tabpanel" aria-labelledby="NewSessionModalDefault-tab">
								<form class="form-horizontal" role="form" data-toggle="validator" id="formNewSession" enctype="multipart/form-data">
									<div class="form-group">
										<label for="newSessionName">Session Name</label>
										<input type="text" class="form-control" id="newSessionName" name="session name" required="true">
									</div>
									<div class="form-group">
										<label for="newConfigFile">Configuration File</label>
										<select class="form-control" id="newConfigFile" required="required">
										</select>
									</div>
									<div class="form-group">
										<label for="newConfigVersion">Configuration Version</label>
										<select class="form-control" id="newConfigVersion" required="required"></select>
									</div>
									<div class="form-group">
										<label for="newGeoServer">GeoServer</label>
										<select class="form-control" id="newGeoServer" required="required">
										</select>
									</div>
									<div class="form-group">
										<label for="newWatchdog">Simulation Server</label>
										<select class="form-control" id="newWatchdog" required="required">
										</select>
									</div>
									<div class="form-group">
										<label for="newAdminPassword">Admin Password</label>
										<input type="text" class="form-control" id="newAdminPassword" required="required">
										<small id="adminPasswordHelp" class="form-text text-muted">This and more sophisticated user access settings can always be changed after the session has been successfully created.</small>
									</div>
									<div class="form-group">
										<label for="newPlayerPassword">Player Password</label>
										<input type="text" class="form-control" id="newPlayerPassword" title="This feature offers minimal security only. The set password will be retrievable here in the ServerManager for all its users. So do not enter one of your personal, commonly-used passwords.">
										<small id="userPasswordHelp" class="form-text text-muted">This and more sophisticated user access settings can always be changed after the session has been successfully created.</small>
									</div>
									<?php /* 
									<div class="form-group">
										<label for="newVisibility">Visibility</label>
										<select class="form-control" id="newVisibility" required="required">
											<option value="public" selected>public</option>
											<option value="private">private</option>
										</select>
									</div>
									*/?>
								</form>
							</div>
							<div class="tab-pane fade show" id="NewSessionModalLoadSave" role="tabpanel" aria-labelledby="NewSessionModalLoadSave-tab">
							<form class="form-horizontal" role="form" data-toggle="validator" id="formLoadSave" enctype="multipart/form-data">
								<div class="form-group">
									<label for="newSessionName2">Session Name</label>
									<input type="text" class="form-control" id="newServerName" name="session name" required="true">
								</div>
								<div class="form-group">
									<label for="newWatchdogLoadSave">Simulation Server</label>
									<select class="form-control" id="newWatchdogLoadSave" required="required">
									</select>
								</div>
								<div class="form-group">
									<label for="SaveFileSelector">Select the Session Save you wish to load</label>
									<select class="form-control" id="SaveFileSelector" required="required">		
									</select>
								</div>
							</form>
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
						<button type="button" class="btn btn-primary" onClick="newSessionChoice();">Create session</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Modal Session user management -->
		<div class="modal fade" id="sessionUsers" tabindex="-1" role="dialog" aria-labelledby="sessionUsersCenterTitle" aria-hidden="true">
			<div class="modal-dialog modal-wide" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="sessionUsersCenterTitle">Session User Management</h5>
						<button type="button" id="btnCloseSessionUsers" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body">
						<p>When setting a password, anyone who has that password will be able to log on to your session as that user type. 
						When setting specific users, those authentication provider's users will be able to log on to your session as that user type (assuming they entered the correct username and password). 
						</p>
						<form class="form-horizontal" role="form" data-toggle="validator" id="formSessionUsers">
							<div class="row">
								<div class="col">
									<div id="adminUserAccess">
										<h6>Administrators</h6>
										<div class="form-check">
											<input class="form-check-input" type="radio" name="provider_admin" value="local" onChange="limitUserAccessView('#adminPasswordFields');">
											<label class="form-check-label" for="provider_admin">
												Set a password
											</label>
										</div>
										<div id="adminPasswordFields">
											<div class="input-group mb-3">
												<input type="text" class="form-control" placeholder="Enter a password. Administrators require a password." id="password_admin" name="password_admin">
											</div>	
										</div>
										<div id="adminProviders">
											<div class="form-check">
									 			<input class="form-check-input" type="radio" name="provider_admin" value="external" onChange="limitUserAccessView('#adminUserFields');">
												<label class="form-check-label" for="provider_admin">
													Set users from
												</label>
												<select class="form-control-sm d-inline-block p-0 h-25" id="provider_admin_external"></select>
											</div>
										</div>
										<div id="adminUserFields">
											<div class="input-group mb-3">
												<div contenteditable="true" class="form-control" style="height: auto !important;" id="users_admin"></div>
												<div class="input-group-append">
													<button class="btn btn-outline-secondary" type="button" id="button-find-users_admin" onclick="findUsersAtProvider('#users_admin', $('#provider_admin_external').val());">Find</button>
												</div>
											</div>
										</div>							
									</div>
								</div>
								<div class="col">
									<div id="regionUserAccess">
										<h6>Region Managers</h6>
										<div class="form-check">
											<input class="form-check-input" type="radio" name="provider_region" id="provider_region" value="local" onChange="limitUserAccessView('#regionPasswordFields');">
											<label class="form-check-label" for="provider_region">
												Set a password
											</label>
										</div>
										<div id="regionPasswordFields">
											<div class="input-group mb-3">
												<input type="text" class="form-control" placeholder="Enter a password. Region managers require a password." id="password_region" name="password_region">
											</div>
										</div>
										<div id="regionProviders">
											<div class="form-check">
									 			<input class="form-check-input" type="radio" name="provider_region" value="external" onChange="limitUserAccessView('#regionUserFields');">
												<label class="form-check-label" for="provider_region">
													Set users from
												</label>
												<select class="form-control-sm d-inline-block p-0 h-25" id="provider_region_external"></select>
											</div>
										</div>
										<div id="regionUserFields">
											<div class="input-group mb-3">
												<div contenteditable="true" class="form-control" style="height: auto !important;" id="users_region"></div>
												<div class="input-group-append">
													<button class="btn btn-outline-secondary" type="button" id="button-find-users_region" onclick="findUsersAtProvider('#users_region', $('#provider_region_external').val());">Find</button>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col">
									<div id="playerUserAccess" style="margin-top: 25px;">
										<h6>Players</h6>
										<div class="form-check">
											<input class="form-check-input" type="radio" name="provider_player" id="provider_player" value="local" onChange="limitUserAccessView('#playerPasswordFields');">
											<label class="form-check-label" for="provider_player">
												Set a password
											</label>
										</div>
										<div id="playerPasswordFields">
											<div class="input-group mb-3">
												<input type="text" class="form-control" placeholder="Leave empty for immediate access." id="password_playerall" name="password_playerall" oninput="toggleFields();">
												<div class="input-group-append">
													<span class="input-group-text">All countries</span>
												</div>
											</div>
											<div id="playerPasswordExtraFields">
											</div>
										</div>
										<div id="playerProviders">
											<div class="form-check">
									 			<input class="form-check-input" type="radio" name="provider_player" value="external" onChange="limitUserAccessView('#playerUserFields');">
												<label class="form-check-label" for="provider_player">
													Set users from 
												</label>
												<select class="form-control-sm d-inline-block p-0 h-25" id="provider_player_external"></select>
											</div>
										</div>
										<div id="playerUserFields">
											<div class="input-group mb-3">
												<div contenteditable="true" class="form-control" style="height: auto !important;" id="users_playerall"></div>
												<script language="javascript">$("#users_playerall").on("change keydown paste input", function() {
													toggleDivs();
												});</script>
												<div class="input-group-append">
													<span class="input-group-text">All countries</span>
												</div>
												<div class="input-group-append">
													<button class="btn btn-outline-secondary" type="button" id="button-find-users_playerall" onclick="findUsersAtProvider('#users_playerall', $('#provider_player_external').val());">Find</button>
												</div>
											</div>
											<div id="playerUserExtraFields">
											</div>
										</div>
									</div>
								</div>
							</div>
						</form>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
						<button type="button" class="btn btn-primary" onClick="saveUserAccess();">Save</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Modal Save info -->
		<div class="modal fade" id="saveInfo" tabindex="-1" role="dialog" aria-labelledby="saveModalCenterTitle" aria-hidden="true">
			<div class="modal-dialog" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="saveModalCenterTitle">Save Details</h5>
						<button type="button" id="btnCloseSaveInfo" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="table-responsive">
							<table class="table table-hover table-border-left" id="saveInfoTable">
								<tr class="bg-primary">
									<td class="text-center" colspan="2" style="color: #FFFFFF" id="SaveInfoName"></td>
								</tr>
								<tr>
									<th>Saved on</th>
									<td class="text-right" id="SaveInfoTimestamp"></td>
								</tr>
								<tr>
									<th>Month & Year of the Simulation</th>
									<td class="text-right" id="SaveInfoMonth"></td>
								</tr>
								<tr>
									<th>Configuration</th>
									<td class="text-right" id="SaveInfoConfig"></td>
								</tr>
								<tr>
									<th>Type</th>
									<td class="text-right" id="SaveInfoType"></td>
								</tr>
								<tr>
									<th>Visibility</th>
									<td class="text-right" id="SaveInfoVisibility"></td>
								</tr>
								<tr>
									<td colspan="2" class="text-left">
										<p><strong>Notes</strong></p>
										<form class="form-horizontal" role="form" data-toggle="validator" id="formSaveNotes" enctype="multipart/form-data">
											<input type="hidden" id="SaveInfoId" />
											<textarea class="form-control" id="SaveInfoNotes" rows="3" onchange="SaveNotes();"></textarea>
										</form>
									</td>
								</tr>
								<tr class="table-info">
									<td class="text-center" colspan="2">
										<button id="buttonLoadSave" class="btn btn-info btn-sm" onClick=""><i class="fa fa-plus-circle" title=""></i> Load</button>
										<button id="buttonSaveDownload" class="btn btn-secondary btn-sm" onClick=""><i class="fa fa-download" title="Download save file"></i> Download</button>
										<button id="buttonSaveArchive" class="btn btn-info btn-sm" onClick=""></button>
									</td>
								</tr>
							</table>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Modal: Upload Save  -->
		<div class="modal fade" id="modalUploadSave" tabindex="-1" role="dialog" aria-labelledby="uploadsaveModalLabel" aria-hidden="true">
			<div class="modal-dialog" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title" id="uploadsaveModalLabel">Upload a Session Save File</h4>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body">
						<form class="form-horizontal" role="form" data-toggle="validator" id="formUploadSave" enctype="multipart/form-data">
							<div class="form-group">
								<label for="uploadedSaveFile">Choose a save file you stored on your computer somewhere for uploading</label>
								<input type="file" class="form-control-file" id="uploadedSaveFile" accept=".zip">
							</div>
						</form>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
						<button type="button" class="btn btn-primary" onClick="UploadSave();">Upload</button>
					</div>
				</div>
			</div>
		</div>
		
		<!-- Modal Config info -->
		<button type="button" id="btnConfigInfo" class="btn btn-primary" data-toggle="modal" data-target="#configInfo" style="display: none;"></button>
		<div class="modal fade" id="configInfo" tabindex="-1" role="dialog" aria-labelledby="configModalCenterTitle" aria-hidden="true">
			<div class="modal-dialog" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="configModalCenterTitle">Configuration Details</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="table-responsive">
							<table class="table table-hover table-border-left" id="configInfoTable">
								<tr class="bg-primary">
									<td class="text-center" colspan="2" style="color: #FFFFFF" id="ConfigInfoFilenameVersion"></td> <!--'+data.gameconfig.filename+' version '+data.gameconfig.version+'-->
								</tr>
								<tr>
									<th>Date Uploaded</th>
									<td class="text-right" id="ConfigInfoUploadTime"></td> <!-- '+data.gameconfig.upload_time+' -->
								</tr>
								<tr>
									<th>Uploaded By</th>
										<td class="text-right" id="ConfigInfoUploadUser"></td> <!-- '+data.gameconfig.upload_user+' -->
									</tr>
									<tr>
										<th>Last Used</th>
										<td class="text-right" id="ConfigInfoLastPlayedTime"></td> <!--'+data.gameconfig.last_played_time+'-->
									</tr>
									<tr>
										<th>Filename</th>
										<td class="text-right" id="ConfigInfoFilename"></td> <!--'+data.gameconfig.filename+'-->
									</tr>
									<tr>
										<th>Description</th>
										<td class="text-right" id="ConfigInfoDescription"></td> <!--'+data.gameconfig.description+'-->
									</tr>
									<tr>
										<th>Version</th>
										<td class="text-right" id="ConfigInfoVersion"></td> <!--'+data.gameconfig.version+'-->
									</tr>
									<tr>
										<th>Change Message</th>
										<td class="text-right" id="ConfigInfoVersionMessage"></td> <!-- '+data.gameconfig.version_message+'-->
									</tr>
									<tr class="table-info">
										<td class="text-center" colspan="2">
											<button id="buttonConfigDownload" class="btn btn-secondary btn-sm" onClick=""><i class="fa fa-download" title="Download config file"></i> Download</button>
											<button id="buttonConfigArchive" class="btn btn-info btn-sm" onClick=""></button>
										</td>
									</tr>
							</table>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Modal: New Config -->
		<div class="modal fade" id="modalNewConfigFile" tabindex="-1" role="dialog" aria-labelledby="newconfigfileModalLabel">
			<div class="modal-dialog" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title" id="newconfigfileModalLabel">Upload New Configuration File</h4>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body">
						<form class="form-horizontal" role="form" data-toggle="validator" id="formNewConfig" enctype="multipart/form-data">
							<div class="form-group">
								<label for="newConfigFileUpload">Select a Configuration file to upload</label>
								<input type="file" class="form-control-file" id="newConfigFileContent" accept=".json" required="required">
							</div>
							<div class="form-group">
								<label for="newConfigFileOriginal" >New version of existing configuration, or completely new configuration?</label>
								<select class="form-control" id="newConfigFileOriginal" required="required">
									
								</select>
							</div>
							<div class="form-group">
								<label for="newConfigFileName" >New configuration file name</label>
								<input type="text" class="form-control" id="newConfigFileName" disabled=true placeholder="Change if uploading a completely new configuration file">
							</div>
							<div class="form-group">
								<label for="newConfigDescription">Description</label>
								<textarea class="form-control" rows="5" id="newConfigDescription" required="required"></textarea>
							</div>
							<div class="form-group">
								<label for="newConfigChangeMessage">Description of the changes in the new or first version</label>
								<textarea class="form-control" rows="5" id="newConfigChangeMessage" required="required"></textarea>
							</div>
						</form>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
						<button type="button" class="btn btn-primary" onClick="submitNewConfigFile();">Upload Configuration File</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Modal: Server Address, Name, Description -->
		<div class="modal fade" id="modalServerDetails" tabindex="-1" role="dialog" aria-labelledby="ServerDetailsModalLabel">
			<div class="modal-dialog" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title" id="ServerDetailsModalLabel">Server Name, Description & Address</h4>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body">
						<form class="form-horizontal" role="form" data-toggle="validator" id="formServerDetails" enctype="multipart/form-data">
							<div class="form-group">
								<label for="GameServerAddress">MSP Challenge Server name</label>
								<input type="text" class="form-control" id="ServerName" required="required"/>
							</div>
							<div class="form-group">
								<label for="GameServerAddress">MSP Challenge Server description</label>
								<textarea class="form-control" id="ServerDescription" rows="3" required="required"/></textarea>
							</div>
							<div class="form-group">
								<label for="GameServerAddress">MSP Challenge Server machine's address</label>
								<input type="text" class="form-control" id="ServerAddress" required="required"/>
							</div>
							<p>The default value is 'localhost'. This value makes MSP Challenge determine your current IP address automatically. In some cases that automatically determined IP address turns out to be erroneous or inaccessible, causing connection problems for MSP Challenge clients and any externally hosted background simulations. In those cases you can specify the public IP address or fully-qualified domain name of this machine below. Note that local IP addresses tend to change, so as soon as that happens you will have to specify the new exact IP address here again. If everything works well, then leave this setting alone.</p>
						</form>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
						<button type="button" class="btn btn-primary" onClick="editServerManager();">Update</button>
					</div>
				</div>
			</div>
		</div>
		
		<!-- Modal: New Simulation Servers -->
		<div class="modal fade" id="modalNewSimServers" tabindex="-1" role="dialog" aria-labelledby="NewSimServersModalLabel">
			<div class="modal-dialog" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title" id="NewSimServersModalLabel">Simulation Servers</h4>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body">
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
						<form class="form-horizontal" role="form" data-toggle="validator" id="formNewWatchdogServer" enctype="multipart/form-data">
							<input type="hidden" id="editWatchdogID">
							<h5 id="WatchdogFormTitle">Add additional simulation server</h5>
							<div class="row">
								<div class="col">
									<label for="newWatchdogServerName">Simulation Server Name</label>
									<input type="text" class="form-control" id="newWatchdogServerName" required="required" placeholder="required">
								</div>
								<div class="col">
									<label for="newWatchdogServerAddress">IP Address</label>
									<input type="text" class="form-control" id="newWatchdogServerAddress" required="required" placeholder="required">
								</div>
							</div>
						</form>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
						<button type="button" class="btn btn-primary" id="WatchdogFormButton" onClick="submitNewWatchdogServer();">Add</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Modal: New Geo Servers -->
		<div class="modal fade" id="modalNewGeoServers" tabindex="-1" role="dialog" aria-labelledby="NewGeoServersModalLabel">
			<div class="modal-dialog" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title" id="NewGeoServersModalLabel">GeoServers</h4>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body">
						<table id="GeoServerTable" class="table table-sm table-hover">
							<thead>
								<tr>
									<th>GeoServer Name</th>
									<th>Fully-qualified URL</th>
									<th style="width: 75px;">Actions</th>
								</tr>
							</thead>
							<tbody id="GeoServerBody">
							</tbody>
						</table>
						<form class="form-horizontal" role="form" data-toggle="validator" id="formNewGeoServer">
							<input type="hidden" id="editGeoserverID">
							<h5 id="GeoServerFormTitle">Add a new GeoServer</h5>
							<div class="form-group">
									<label for="newGeoServerName">GeoServer Name</label>
									<input type="text" class="form-control" id="newGeoServerName" required="required" placeholder="required">
							</div>
							<div class="form-group">
									<label for="newGeoServerAddress">Fully-qualified URL</label>
									<input type="text" class="form-control" id="newGeoServerAddress" required="required" placeholder="required">
							</div>
							<div class="form-group">
									<label for="newGeoServerUsername">Username</label>
									<input type="text" class="form-control" id="newGeoServerUsername" required="required" placeholder="required">
							</div>
							<div class="form-group">
									<label for="newGeoServerPassword">Password</label>
									<input type="text" class="form-control" id="newGeoServerPassword" required="required" placeholder="required">
							</div>
						</form>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
						<button type="button" class="btn btn-primary" id="GeoServerFormButton" onClick="submitNewGeoServer();">Add</button>
					</div>
				</div>
			</div>
		</div>
		
	</div>

	<!-- Modal Error Details -->

	<div class="modal fade" id="errorDetail" tabindex="-1" role="dialog" aria-labelledby="errorModalCenterTitle" aria-hidden="true">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="errorModalCenterTitle">Error Details</h5>
					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>
				<div class="modal-body">
					<div id="divErrorDetail">

					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
				</div>
			</div>
		</div>
	</div>


	<div role="status" aria-live="polite" id="LogToast" aria-atomic="true" class="toast hide" data-autohide="false" data-autoclose="false" style="max-width: 650px !important; width: 650px; position: fixed; bottom: 0; right: 0;">
		<div class="toast-header">
			<strong class="mr-auto" id="LogToastHeader"></strong>
			<button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close">
			<span aria-hidden="true">&times;</span>
			</button>
		</div>
		<div class="toast-body" id="LogToastBody">
			
		</div>
	</div>

</div>

<!-- Place any per-page javascript here -->
<script type="text/javascript">
	var currentToken = '<?php echo Session::get("currentToken"); ?>';
	regularupdateToken = setInterval(function() {
		updatecurrentToken();
	}, 30000);
	
	$(document).ready(function() {
		// all about refreshing the sessions table (browse)
		updateSessionsTable('public');
		$("#buttonRefreshSessionsList").click(function() {
			updateSessionsTable($('input[name=inlineRadioOptions]:checked').val());
		});
		$('input[name=inlineRadioOptions]').change(function() {
			updateSessionsTable(this.value);
		});

		// all  about refreshing the config table (browse)
		updateConfigVersionsTable('active');
		$("#buttonRefreshConfigVersionsList").click(function() {
			updateConfigVersionsTable($('input[name=radioOptionConfig]:checked').val());
		});
		$("#radioFilterConfigVersionsList input[type=radio]").change(function() {
			updateConfigVersionsTable(this.value);
		});

		// all about refreshing the saves table (browse)
		updateSavesTable('active');
		$("#buttonRefreshSavesList").click(function() {
			updateSavesTable($('input[name=inlineRadioOptionSaves]:checked').val());
		});
		$('input[name=inlineRadioOptionSaves]').change(function() {
			updateSavesTable(this.value);
		});		
			
		// when showing modalNewSession, update the dropdown lists in the forms of both tabs
		$('#modalNewSession').on('show.bs.modal', function (event) {
			configListToOptions();
			GeoServerListToOptions();
			watchdogListToOptions();
			if ($('#SaveFileSelector').val() == 0) SavesListToOptions();
		});
		$("#newConfigFileOriginal").change(function() {
			onUploadConfigFileOriginalSelected(this.value);
		});
		$('#newConfigFile').change(function() {
			updateSelectNewConfigVersion(this.value);
		});
		$("#SaveFileSelector").change(function() {
			setNewServerNameInForm(this.value);
		});

		// when showing modalNewConfigFile, update the dropdown lists in the form
		$('#modalNewConfigFile').on('show.bs.modal', function (event) {
			configListToOptions();
		});

		// when showing modalNewSimServer, update the list (browse)
		$('#modalNewSimServers').on('show.bs.modal', function (event) {
			WatchdogServerList();
		});

		// when showing modalNewGeoServers, update the list (browse)
		$('#modalNewGeoServers').on('show.bs.modal', function (event) {
			GeoServerList();
		});

		// when showing modalServerDetails, update the values
		$('#modalServerDetails').on('show.bs.modal', function (event) {
			GetServerAddr();
		});
	});

	regularupdateTablesManager = setInterval(function() {
		updateSessionsTable($('input[name=inlineRadioOptions]:checked').val());
		if ($('#sessionInfo').is(':visible')) {
			getSessionInfo($('#sessionInfoID').html());
		}

		updateSavesTable($('input[name=inlineRadioOptionSaves]:checked').val());
	}, 10000);
	
</script>
<!-- footers -->
<?php require_once ServerManager::getInstance()->GetServerManagerRoot() . 'templates/footer.php'; // the final html footer copyright row + the external js calls
?>
