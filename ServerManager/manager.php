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

if (!$user->isAuthorised()) {
	if ($user->isLoggedIn()) {
		Redirect::to($url_app_root.'logout.php');
	}
	else {
		Redirect::to($url_app_root.'index.php');
	}
} 

require_once $abs_app_root . $url_app_root . 'templates/header.php'; ?>

<div id="page-wrapper">
	<div role="main" class="container">
		<div id="infobox"></div>
		<ul class="nav nav-tabs" id="myTab" role="tablist">
			<li class="nav-item">
				<a href="#tabSessionsList" class="nav-link active" role="tab" data-toggle="tab" aria-controls="tabSessionsList" aria-selected="true"><i class="fa fa-list-alt"></i> Sessions</a>
			</li>
			<li class="nav-item">
				<a href="#tabSavesList" class="nav-link" role="tab" data-toggle="tab" aria-controls="tabSavesList" aria-selected="false"><i class="fa fa-save"></i> Saves</a>
			</li>
			<li class="nav-item">
				<a href="#tabConfigVersions" class="nav-link" role="tab" data-toggle="tab" aria-controls="tabConfigVersions" aria-selected="false"><i class="fa fa-file-text"></i> Configurations</a>
			</li>
			<li class="nav-item">
				<a href="#tabSettings" class="nav-link" role="tab" data-toggle="tab" aria-controls="tabSettings" aria-selected="false"><i class="fa fa-wrench"></i> Settings</a>
			</li>	

		</ul>

		<div class="tab-content" id="myTabContent">
			<div class="tab-pane fade show active" id="tabSessionsList" role="tabpanel" aria-labelledby="tabSessionsList-tab">
				<div class="row">
					<div class="col-md-12 flex-row">
						<button type="button" id="btnCreateServer" class="btn btn-primary  pull-left" data-toggle="modal" data-target="#modalNewSession"><i class="fa fa-plus-circle" title="Create new session"></i> New Session</button>
						<button id="buttonRefreshSessionsList" class="btn btn-primary"><i class="fa fa-refresh" title="Refresh" id="buttonRefreshSessionsListIcon"></i> Refresh</button>
					</div>
					<div class="col-md-12 flex-right" id="sessionsList">
						<div class="well well-sm" >
							Filter:
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
						<p>Here you can create a new MSP Challenge session, and administer existing ones.</p>
						<div class="table-responsive">
							<table id="sessionsTable" class="table table-hover table-striped tablesorter-default">
								<thead>
									<tr>
										<th>ID</th>
										<th>Session Name</th>
										<th>Configuration</th>
										<th>Players</th>
										<th>State</th>
										<th>Current Month</th>
										<th>Ending Month</th>
										<th class="text-center sorter-false">Quick Actions</th>
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
					<div class="col-md-12 flex-right" id="savesList">
						<div class="well well-sm" >
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
						<p>Here you can review and reuse saves of MSP Challenge sessions.</p>
						<div class="table-responsive">
							<table id="savesTable" class="table table-hover table-striped tablesorter-default">
								<thead>
									<tr>
										<th>Created</th>
										<th>Session Name</th>
										<th>Month / Year</th>
										<th>Configuration</th>
										<th>Type</th>
										<th class="text-center sorter-false">Quick Actions</th>
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
				<div class="col-md-12 flex-right" id="configVersionsList">
					<div class="well well-sm" style="text-align: end;">

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
						<p>Here you can upload a new MSP Challenge session configuration file, and administer existing ones.</p>
						<div class="table-responsive">
							<table id="configVersionsTable" class="table table-hover tablesorter-default">
								<thead>
									<tr>
										<th></th>
										<th>Name</th>
										<th>Version</th>
										<th>Date uploaded</th>
										<th>Uploaded by</th>
										<th>Last used</th>
										<th class="text-center sorter-false">Quick Actions</th>
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
				</div>
			</div>
		</div>

		<!-- Modal Session info -->
		<button type="button" id="btnSessionInfo" class="btn btn-primary" data-toggle="modal" data-target="#sessionInfo" style="display: none;"></button>
		<div class="modal fade" id="sessionInfo" tabindex="-1" role="dialog" aria-labelledby="sessionModalCenterTitle" aria-hidden="true">
			<div class="modal-dialog modal-wide" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="sessionModalCenterTitle">Session Details</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body">
						<div class="table-responsive">
							<table class="table table-hover table-border-left" id="sessionInfoTable">
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
									<label for="newWatchdog">Simulation Server</label>
									<select class="form-control" id="newWatchdog" required="required">
									</select>
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
									<label for="newAdminPassword">Admin Password</label>
									<input type="text" class="form-control" id="newAdminPassword" required="required" title="This feature offers minimal security only. The set password will be retrievable here in the ServerManager for all its users. So do not enter one of your personal, commonly-used passwords.">
								</div>
								<div class="form-group">
									<label for="newPlayerPassword">Player Password</label>
									<input type="text" class="form-control" id="newPlayerPassword" title="This feature offers minimal security only. The set password will be retrievable here in the ServerManager for all its users. So do not enter one of your personal, commonly-used passwords.">
								</div>
								<div class="form-group">
									<input type="hidden"  id="newGameServer" value="1" />
								</div>
								<div class="form-group">
									<input type="hidden"  id="newVisibility" value="public" />
									<?php /* 
									<label for="newVisibility">Visibility</label>
									<select class="form-control" id="newVisibility" required="required">
										<option value="public" selected>public</option>
										<option value="private">private</option>
									</select>
									*/?>
								</div>
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

		<!-- Modal Save info -->
		<button type="button" id="btnSaveInfo" class="btn btn-primary" data-toggle="modal" data-target="#saveInfo" style="display: none;"></button>
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
							</table>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Modal: New Config info -->
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
								<input type="text" class="form-control" id="newConfigFileName" disabled=true value="Change if uploading a completely new configuration file" required="required">
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
						<h4 class="modal-title" id="ServerDetailsModalLabel">Server Address, Name & Description</h4>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body">
						<p>The default value is 'localhost'. This value makes MSP Challenge determine your current IP address automatically. In some cases that automatically determined IP address turns out to be erroneous or inaccessible, causing connection problems for MSP Challenge clients and any externally hosted background simulations. In those cases you can specify the public IP address or fully-qualified domain name of this machine below. Note that local IP addresses tend to change, so as soon as that happens you will have to specify the new exact IP address here again. If everything works well, then leave this setting alone.</p>
						<form class="form-horizontal" role="form" data-toggle="validator" id="formNewWatchdogServer" enctype="multipart/form-data">
							<div class="row">
								<div class="col">
									<label for="GameServerAddress">MSP Challenge Server machine's address</label>
									<input type="text" class="form-control" id="ServerAddress" required="required"/>
									<input type="hidden" id="ServerID" value="1"/>
									<input type="hidden" id="ServerName" value="Default: the server machine"/>
								</div>
							</div>
						</form>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
						<button type="button" class="btn btn-primary" onClick="submitNewServer();">Update</button>
					</div>
				</div>
			</div>
		</div>
		
		<!-- Modal: New Simulation Servers -->
		<div class="modal fade" id="modalNewSimServers" tabindex="-1" role="dialog" aria-labelledby="NewSimServersModalLabel">
			<div class="modal-dialog" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<h4 class="modal-title" id="NewSimServersModalLabel">Add Additional Simulation Servers</h4>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body">
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
							</div>
						</form>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
						<button type="button" class="btn btn-primary" onClick="submitNewWatchdogServer();">Add</button>
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

</div>

<!-- Place any per-page javascript here -->
<script type="text/javascript">
	var currentToken = '<?php echo Session::get("currentToken"); ?>';
	regularupdateToken = setInterval(function() {
		updatecurrentToken();
	}, 30000);
	
	$(document).ready(function() {
		updateSessionsTable('public');
		$("#sessionsTable").tablesorter();
		$("#buttonRefreshSessionsList").click(function() {
			updateSessionsTable($('input[name=inlineRadioOptions]:checked').val());
		});
		$('input[name=inlineRadioOptions]').change(function() {
			updateSessionsTable(this.value);
		});

		$('#newConfigFile').change(function() {
			updateSelectNewConfigVersion(this.value);
		});
		updateConfigVersionsTable('active');
		$("#buttonRefreshConfigVersionsList").click(function() {
			updateConfigVersionsTable($('input[name=radioOptionConfig]:checked').val());
		});
		$("#radioFilterConfigVersionsList input[type=radio]").change(function() {
			updateConfigVersionsTable(this.value);
		});
		$("#newConfigFileOriginal").change(function() {
			onUploadConfigFileOriginalSelected(this.value);
		});
		configListToOptions();
		watchdogListToOptions();

		updateSavesTable('active');
		$("#buttonRefreshSavesList").click(function() {
			updateSavesTable($('input[name=inlineRadioOptionSaves]:checked').val());
		});
		$('input[name=inlineRadioOptionSaves]').change(function() {
			updateSavesTable(this.value);
		});
		$("#SaveFileSelector").change(function() {
			setNewServerName(this.value);
		});
		SavesListToOptions();

		WatchdogServerList();
		GetServerAddr();
	});

	regularupdateTablesManager = setInterval(function() {
		updateSessionsTable($('input[name=inlineRadioOptions]:checked').val());
		updateSavesTable($('input[name=inlineRadioOptionSaves]:checked').val());
	}, 20000);
	
</script>
<!-- footers -->
<?php require_once $abs_app_root . $url_app_root . 'templates/footer.php'; // the final html footer copyright row + the external js calls
?>
