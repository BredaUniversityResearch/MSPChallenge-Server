function updatecurrentToken() {
	$.ajax({
		'url': 'api/updateToken.php',
		'data': {
			Token: currentToken,
			format: 'json'
		},
		'dataType': 'json',
		'success': function(data) {
			if (data.status == 'success') {
				resetToken(data.jwt);
			}
		},
		'type': 'POST'
	});
}

function resetToken(newtoken) {
	if (newtoken && newtoken.length > 0) currentToken = newtoken;
}

function updateInfobox(type, message) {
	showToast(type, message);
	//updateSessionsTable($('input[name=inlineRadioOptions]:checked').val());
	//updateSavesTable($('input[name=inlineRadioOptionSaves]:checked').val());
	
	// updating all info that might have changed, except the sessions and saves tables, because those update every X secs anyway
	updateConfigVersionsTable($('input[name=radioOptionConfig]:checked').val());
	configListToOptions();
	watchdogListToOptions();
	SavesListToOptions();
	WatchdogServerList();
	GetServerAddr();
}

const MessageType = {
    ERROR: 0,
    INFO: 1,
    SUCCESS: 2,
}

function showToast(type, message) {
	switch(type) {
		case MessageType.ERROR:

			shortMessage = message
			if(message.length > 100){
				shortMessage =  'An error occurred with the MSP Challenge session. '
				shortMessage += '<button type="button" id="btnErrorDetail" class="btn btn-secondary btn-sm" data-toggle="modal" data-target="#errorDetail">Click here</button> to see more details';
			}

			$('#divErrorDetail').empty();
			$('#divErrorDetail').append(message);

			tata.error('Error', shortMessage, {
				position: 'mm',
				duration: 10000
			});

			break;
		case MessageType.SUCCESS:
			tata.success('Success', message, {
				position: 'mm',
				duration: 10000
			});
			break;
		default:
			tata.info('Info', message, {
				position: 'mm',
				duration: 10000
			});
			break;
	}
}

function copyToClipboard(text) {
	window.prompt("Copy to clipboard: Ctrl+C, Enter", text);
}


/********/
// Sessions related functions
function getSessionInfo(sessionId) {
	$.ajax({
		'url': 'api/getsessioninfo.php',
		'data': {
			Token: currentToken,
			format: 'json',
			session_id: sessionId
		},
		'error': function() {
			updateInfobox('danger', 'getSessionInfo: Error in AJAX call.');
		},
		'dataType': 'json',
		'success': function(data) {
			if (data.status == 'error') {
				updateInfobox('danger', data.message);
				console.log('getSessionInfo (API)', data.message);
			} else {
				updateSessionInfoList(data.sessioninfo);
			}
		},
		'type': 'POST'
	});
}

function startSession(sessionId) {
	$('#buttonStartPause').prop('disabled', 1);
	showToast(MessageType.INFO, 'Please wait...');
	$.ajax({
		'url': 'api/startsession.php',
		'data': {
			Token: currentToken,
			format: 'json',
			session_id: sessionId
			},
		'error': function() {
			updateInfobox(MessageType.ERROR, 'startSession: Error in AJAX call.');
			},
		'dataType': 'json',
		'success': function(data) {
			if (data.status == 'error') {
				updateInfobox(MessageType.ERROR, data.message);
				console.log('startSession' , data.message)
			} else {
				updateInfobox(MessageType.SUCCESS, data.message);
				updatePlayPauseStatus('pause', sessionId);
			}
			},
		'type': 'POST'
	});
}

function pauseSession(sessionId) {
	$('#buttonStartPause').prop('disabled', 1);
	showToast(MessageType.INFO, 'Please wait...');
	$.ajax({
		'url': 'api/pausesession.php',
		'data': {
			Token: currentToken,
			format: 'json',
			session_id: sessionId
			},
		'error': function() {
			updateInfobox(MessageType.ERROR, 'pauseSession: Error in AJAX call.');
			},
		'dataType': 'json',
		'success': function(data) {
			if (data.status == 'error') {
				updateInfobox(MessageType.ERROR, data.message);
				console.log('pauseSession', data.message);
			} else {
				updateInfobox(MessageType.SUCCESS, data.message);
				updatePlayPauseStatus('play', sessionId);
			}
			},
		'type': 'POST'
	});
}

function updatePlayPauseStatus(playorpause, sessionId) {
	$('#game-state').empty();
	if (playorpause == 'pause') {
		btnText = '<i class="fa fa-pause" title="Pause Simulation"></i> Pause Simulation';
		propclass = 'btn btn-secondary btn-sm pull-left';
		proponclick = 'pauseSession('+sessionId+')';
		gamestateText = '<h5><span class="badge badge-warning"><i class="fa fa-info" aria-hidden="true"></i> play</span></h5>';
	}
	else if (playorpause == 'play') {
		btnText = '<i class="fa fa-play" title="Start Simulation"></i> Start Simulation';
		propclass = 'btn btn-success btn-sm pull-left';
		proponclick = 'startSession('+sessionId+')';
		gamestateText = '<h5><span class="badge badge-warning"><i class="fa fa-info" aria-hidden="true"></i> pause</span></h5>';
	}
	$('#buttonStartPause').empty();
	$('#buttonStartPause').append(btnText);
	$('#buttonStartPause').prop('class', propclass);
	$('#buttonStartPause').attr('onclick', proponclick);
	$('#buttonStartPause').prop('disabled', 0);

	$('#game-state').append(gamestateText);
}
function saveSession(sessionId, saveType) {
	showToast(MessageType.INFO, 'Please wait...');
	$.ajax({
		'url': 'api/savesession.php',
		'data': {
			Token: currentToken,
			format: 'json',
			session_id: sessionId,
			save_type: saveType
		},
		'error': function() {
			updateInfobox(MessageType.ERROR, 'saveSession: Error in AJAX call.');
		},
		'dataType': 'json',
		'success': function(data) {
			if (data.status == 'error') {
				updateInfobox(MessageType.ERROR, data.message);
				console.log('saveSession' , data.message)
			} else if (data.status == 'info') {
				updateInfobox(MessageType.INFO, data.message);
				console.log('saveSession' , data.message)
			}
			else {
				updateInfobox(MessageType.SUCCESS, data.message);
				$('#sessionInfo').modal('hide');
			}
		}, 
		'type': 'POST'
	});
}

function newSessionChoice() {
	if ($('#NewSessionModalDefault').is(':visible')) {
		submitNewSession();
	}
	else if ($('#NewSessionModalLoadSave').is(':visible')) {
		submitLoadSave();
	}
}

function archiveSession(sessionId) {
	if (confirm('This will permanently archive the session. It will subsequently no longer be usable by end users. You will be able to download an archive file purely as a backup. Are you sure?')) {
		$.ajax({
			'url': 'api/archivesession.php',
			'data': {
				Token: currentToken,
				format: 'json',
				session_id: sessionId
				},
			'error': function() {
				updateInfobox(MessageType.ERROR, 'archiveSession: Error in AJAX call.');
				},
			'dataType': 'json',
			'success': function(data) {
				if (data.status == 'error') {
					updateInfobox(MessageType.ERROR, data.message);
					console.log('archiveSession' , data.message)
				} else {
					updateInfobox(MessageType.SUCCESS, data.message);
					$('#sessionInfo').modal('hide');
					//updateSessionsTable($('input[name=radioVisibility]:checked', '#radioFilterSessionsList').val());
				}
				},
			'type': 'POST'
		});
	}
}

function toggleDemoSession(sessionId) {
	$.ajax({
		'url': 'api/toggledemosession.php',
		'data': {
			Token: currentToken,
			format: 'json',
			session_id: sessionId
			},
		'error': function() {
			updateInfobox(MessageType.ERROR, 'toggleDemoSession: Error in AJAX call.');
			},
		'dataType': 'json',
		'success': function(data) {
			if (data.status == 'error') {
				updateInfobox(MessageType.ERROR, data.message);
				console.log('toggleDemoSession', data.message)
			} else {
				updateInfobox(MessageType.SUCCESS, data.message);
				updateDemoSessionStatus();
			}
			},
		'type': 'POST'
	});
}

function updateDemoSessionStatus(){
	$('#demo-session-status').empty();
	wasEnabled = $('#demo-session-status').attr("data-value") == "1"  ? true : false;
	btnText = wasEnabled ?  '<i class="fa fa-bookmark" title="toggle"></i> Enable Demo Session' : '<i class="fa fa-bookmark" title="toggle"></i> Disable Demo Session';
	$('#toggleDemoSessionButton').empty();
	$('#toggleDemoSessionButton').append(btnText)

	if(wasEnabled){
		$('#demo-session-status').append('<h5><span class="badge badge-secondary"><i class="fa fa-plug" aria-hidden="true"></i> OFF </span></h5>');
		$('#demo-session-status').data('data-value',0).attr('data-value',0);
	} else {
		$('#demo-session-status').append('<h5><span class="badge badge-success"><i class="fa fa-plug" aria-hidden="true"></i> ON </span></h5>');
		$('#demo-session-status').data('data-value', 1).attr('data-value', 1);
	}
}

function ShowArchiveFile(sessionId) {
	//api call
	$.ajax({
		'url': 'api/checkarchiveavailable.php',
		'data': {
			Token: currentToken,
			format: 'json',
			session_id: sessionId
		},
		'error': function() {
			updateInfobox(MessageType.ERROR, 'Cannot check archive status.');
			archiveavailable = false;
		},
		'dataType': 'json',
		'success': function(data) {
			if (data.status == 'error') {
				updateInfobox(MessageType.ERROR, data.message);
				console.log('checkarchiveavailable', data.message);
			}
			archiveavailable = data.archiveavailable;
		}, 
		'async': false,
		'type': 'POST'
	});
	if (archiveavailable) {
		return '<button id="buttonArchiveDownload" class="btn btn-secondary btn-sm" onClick="downloadArchive('+sessionId+')"><i class="fa fa-download" title="Download archive"></i> Download archive</button>';
	}
	else {
		return '<button id="buttonArchiveDownload" class="btn btn-secondary btn-sm" disabled><i class="fa fa-download" title="Archive being created"></i> Archive being created...</button>';
	}
}

function downloadArchive(sessionId) {
	window.location = "api/downloadarchive.php?session_id="+sessionId+"&Token="+currentToken;
}

function RecreateSession(sessionId) {
	if (confirm('This will delete and recreate the session. All existing data will be lost. Are you sure?')) {
		showToast(MessageType.INFO, 'Please wait...');
		$.ajax({
		'url': 'api/recreatesession.php',
		'data': {
			Token: currentToken,
			format: 'json',
			session_id: sessionId
			},
		'error': function() {
			updateInfobox(MessageType.ERROR, 'RecreateSession: Error in AJAX call.');
			},
		'dataType': 'json',
		'success': function(data) {
			if (data.status == 'error') {
				updateInfobox(MessageType.ERROR, data.message);
				console.log('RecreateSession', data.message);
			} else {
				updateInfobox(MessageType.SUCCESS, data.message);
				//updateSessionsTable($('input[name=radioVisibility]:checked', '#radioFilterSessionsList').val());
			}
			},
		'type': 'POST'
		});
		$('#sessionInfo').modal('hide');
		//$('#sessionsTable').trigger('update', true);
	}
}

function downloadExportedPlansWithConfig(sessionId) {
	window.location = "api/exportplansandreturnconfig.php?session_id="+sessionId+"&Token="+currentToken;
}

function updateSessionsTable(visibility) {
	$("#buttonRefreshSessionsListIcon").addClass("fa-spin");
	//updateSessionInfoList(null);
	$.ajax({
		'url': 'api/getsessionslist.php',
		'dataType': 'json',
		'type': 'POST',
		'data': {
			format: 'json',
			visibility: visibility,
			client_timestamp: 'ServerManager'
		},

		'error': function() {
			$('#buttonRefreshSessionsListIcon').removeClass('fa-spin');
			$('#sessionsListtd').html('<p>An error has occurred</p>');
		},

		'success': function(data) {
			$('#buttonRefreshSessionsListIcon').removeClass('fa-spin');
			sessionsListToTable(data.sessionslist);
		},
	});
}

function sessionsListToTable(sessionsList) {
	$('#sessionsListtbody').empty();
	if(sessionsList == '') { $('<tr><td colspan="8">No sessions yet. Create your first one through the New Session button above.</td></tr>').appendTo('#sessionsListtbody') }
	$.each(sessionsList, function(i, v) {
		visibility = '';
		show_state = v.game_state;
		if(v.session_state != 'healthy') {
			visibility = ' hidden_icon';
			show_state = v.session_state;
		}
		if(v.game_state == 'play' || v.game_state == 'fastforward') {
			save_icon = '<button class="btn btn-secondary btn-sm" disabled><i class="fa fa-save" title="Save Session"></i></button>';
			running_icon = '<button class="btn btn-secondary btn-sm" disabled><i class="fa fa-play" title="Start Simulation"></i></button>';
			paused_icon = '<button class="btn btn-secondary btn-sm" onClick="pauseSession('+v.id+')"><i class="fa fa-pause" title="Pause Simulation" ></i></button>';
		} else if(v.session_state != 'archived' && v.session_state != 'request'  && (v.game_state == 'pause' || v.game_state == "setup")){
			save_icon = '<button class="btn btn-secondary btn-sm" onClick="saveSession('+v.id+')"><i class="fa fa-save" title="Save Session"></i></button>';
			running_icon = '<button class="btn btn-secondary btn-sm" onClick="startSession('+v.id+')"><i class="fa fa-play" title="Start Simulation" ></i></button>';
			paused_icon = '<button class="btn btn-secondary btn-sm" disabled><i class="fa fa-pause" title="Pause Simulation"></i></button>';
		} else {
			save_icon = '<button class="btn btn-secondary btn-sm" disabled><i class="fa fa-save" title="Save Session"></i></button>';
			running_icon = '<button class="btn btn-secondary btn-sm" disabled><i class="fa fa-play" title="Start Simulation"></i></button>';
			paused_icon = '<button class="btn btn-secondary btn-sm" disabled><i class="fa fa-pause" title="Pause Simulation"></i></button>';
		}
		info_icon = '<button class="btn btn-secondary btn-sm" onClick="getSessionInfo('+v.id+')"><i class="fa fa-info-circle" title="Info" ></i></button>';
		if (v.game_start_year == '0') { v.current_month_formatted = ''; }
		if (v.game_start_year == '0') { v.end_month_formatted = ''; }

		var tableHTML = '<tr><td>'+v.id+'</td><td>'+v.name+'</td>';
		tableHTML += '<td>'+v.config_file_name+'</td>';
		tableHTML +=
			'<td>'+v.players_active+'</td>'+
			'<td class="state_'+show_state+'">'+show_state+'</td>'+
			'<td>'+v.current_month_formatted+'</td>'+
			'<td>'+v.end_month_formatted+'</td>'+
			'<td class="text-center">'+running_icon+' '+paused_icon+' '+save_icon+' '+info_icon+'</i></td>'+
		'</tr>';
		$(tableHTML).appendTo('#sessionsListtbody')
	})
	//$('#sessionsTable').trigger('update', true);
}

function updateSessionInfoList(sessionInfo) {
	//console.log('Session Log:' , sessionInfo.log);
	$('#sessionInfoList').empty();
	if(sessionInfo == null) {
		$('<li class="list-group-item list-group-item-warning">No session selected...</li>').appendTo("#sessionInfoList");
	} else {
		var buttonStartPause = '';
		var buttonArchiveDownload = '';
		var buttonSaveDownload = '';

		if(sessionInfo.session_state == 'archived') {
			buttonArchiveDownload = ShowArchiveFile(sessionInfo.id);
		} else if (sessionInfo.session_state == 'healthy') {
			buttonArchiveDownload = '<button id="buttonArchiveDownload" class="btn btn-warning btn-sm" onClick="archiveSession('+sessionInfo.id+')"><i class="fa fa-archive" title="Archive Session"></i> Archive Session</button>';
			buttonSaveDownload = '<button id="" class="btn btn-secondary btn-sm" onClick="saveSession('+sessionInfo.id+')"><i class="fa fa-save" title="Save Session"></i> Save Session as File</button>';
			buttonSaveDownload += ' <button id="" class="btn btn-secondary btn-sm" onClick="saveSession('+sessionInfo.id+', \'layers\')"><i class="fa fa-save" title="Save All Layers"></i> Save All Layers</button>';
			if(sessionInfo.game_state == 'play') {
				buttonStartPause = '<button id="buttonStartPause" class="btn btn-secondary btn-sm pull-left" onclick="pauseSession('+sessionInfo.id+')"><i class="fa fa-pause" title="Pause Simulation"></i> Pause Simulation</button>';
			}
			if(sessionInfo.game_state == 'pause' || sessionInfo.game_state == 'setup') {
				buttonStartPause = '<button id="buttonStartPause" class="btn btn-success btn-sm pull-left" onclick="startSession('+sessionInfo.id+')"><i class="fa fa-play" title="Start Simulation"></i> Start Simulation</button>';
			}
		}

		var demoSessionDescription = (sessionInfo.demo_session == 0)? " Enable Demo Session" : " Disable Demo Session";

		var toggleDemoSessionButton = '<button id="toggleDemoSessionButton" class="btn btn-info btn-sm" onClick="toggleDemoSession('+sessionInfo.id+')"><i class="fa fa-bookmark" title="'+demoSessionDescription+'"></i>'+demoSessionDescription+'</button>';
		var buttonExportCurrentPath = '<button id="exportPlansButton" class="btn btn-secondary btn-sm" onClick="downloadExportedPlansWithConfig('+sessionInfo.id+')"><i class="fa fa-file-code-o" title="Export Configuration with Current Plans"></i> Export Configuration with Current Plans</button>';

		if(sessionInfo.session_state == 'request' || sessionInfo.session_state == 'archived'){
			buttonExportCurrentPath = ''; // just don't show anything
			toggleDemoSessionButton = ''; // just don't show anything
		}

		if(sessionInfo.session_state == 'archived') {
			var buttonRecreateSession = ''; // just don't show anything
		}
		else if (sessionInfo.save_id > 0) {
			var buttonRecreateSession = '<button id="buttonRecreateSession" class="btn btn-warning btn-sm" onClick="RecreateLoadSave('+sessionInfo.id+')"><i class="fa fa-repeat" title="Recreate Session"></i> Recreate Session (from save)</button>';
		}
		else {
			var buttonRecreateSession = '<button id="buttonRecreateSession" class="btn btn-warning btn-sm" onClick="RecreateSession('+sessionInfo.id+')"><i class="fa fa-repeat" title="Recreate Session"></i> Recreate Session</button>';
		}

		$('#sessionInfoTable').empty();

		$('#sessionInfoTable').append(getTableSessionInfo(sessionInfo, buttonStartPause, buttonArchiveDownload, toggleDemoSessionButton, buttonExportCurrentPath, buttonRecreateSession, buttonSaveDownload));

		if($('#logInfo')) {
			$('#logInfo').hide();
		}

		$('#btnSessionInfo').click();

	}
}

function getTableSessionInfo(sessionInfo, buttonStartPause, buttonArchiveDownload, toggleDemoSessionButton, buttonExportCurrentPath, buttonRecreateSession, buttonSaveDownload) {
	var html = 	'<tr class="bg-primary"><td class="text-center" colspan="4" style="color: #FFFFFF">'+sessionInfo.name+'</td></tr>';
	html += 	'<tr><th scope="col">ID</th><td class="text-right">'+sessionInfo.id+'</td>'
	html += 	'<th scope="col">Visibility</th>'
	if(sessionInfo.game_visibility.toLowerCase() == 'public') {
		html += '<td class="text-right"><h5><span class="badge badge-info"><i class="fa fa-globe" aria-hidden="true"></i> ' + sessionInfo.game_visibility + '</span></h5></td></tr>'
	}else {
		html += '<td class="text-right"><h5><span class="badge badge-info"><i class="fa fa-lock" aria-hidden="true"></i> ' + sessionInfo.game_visibility + '</span></h5></td></tr>'
	}

	html +=		'<tr>'
	html += 		'<th scope="col">Simulation state</th>'
	html += 		'<td class="text-right" id="game-state"><h5><span class="badge badge-warning"><i class="fa fa-info" aria-hidden="true"></i> ' + sessionInfo.game_state + '</span></h5></td>'
	html += 		'<th scope="col">Session state</th>'
	if(sessionInfo.session_state.toLowerCase() == 'healthy'){
		html += '<td class="text-right"><h5><span class="badge badge-success"><i class="fa fa-heartbeat" aria-hidden="true"></i> ' + sessionInfo.session_state + '</span></h5></td></tr>'
	} else {
		html += '<td class="text-right"><h5><span class="badge badge-success"><i class="fa fa-clock-o" aria-hidden="true"></i> ' + sessionInfo.session_state + '</span></h5></td></tr>'
	}
	html +=		'<tr>'
	html += 		'<th scope=" col">Current Month</th>'
	html += 		'<td class="text-right">' + sessionInfo.current_month_formatted  + '</td>'
	html +=			'<th scope="col">Ending Month</th>'
	html +=			'<td class="text-right">' + sessionInfo.end_month_formatted + '</h5></td>'
	html += 	'</tr>'
	html +=		'<tr>'
	html +=			'<th scope=" col">Setup Time</th>'
	html +=			'<td class="text-right">' + sessionInfo.game_creation_time + '</td>'
	html +=			'<th scope="col" >Time Until End</th>'
	html +=			'<td class="text-right">' + sessionInfo.game_running_til_time + '</td>'
	html += 	'</tr>'

	html +=		'<tr>'
	html +=			'<th scope=" col">Config File</th>'
	html += 		'<td style="word-wrap: break-word"><p class="sessionInfoValue"><a class="hoverinfo" title="'+sessionInfo.config_file_description+'">'+sessionInfo.config_file_name+'</a>'
	if (sessionInfo.config_version_message && sessionInfo.config_version_version) {
		html +=			'<br><a class="hoverinfo" title="'+sessionInfo.config_version_message+'">Version '+sessionInfo.config_version_version+'</a></p></td>'
	}
	html +=			'<th scope="col">Simulation Server</th>'
	html +=			'<td style="word-wrap: break-word" class="text-right"><p class="sessionInfoValue">'+sessionInfo.watchdog_name+'<br>'+sessionInfo.watchdog_address+' <i class="fa fa-clipboard" aria-hidden="true" onClick="copyToClipboard(\''+sessionInfo.watchdog_address+'\')"></i></p></td></tr>'
	html += 	'</tr>'

	html +=		'<tr>'
	html +=			'<th scop="col">Active Players</th>'
	html +=			'<td class="text-right">'+sessionInfo.players_active+'</td>'
	html +=			'<th scope="col">Demo Session?</th>'
	html +=			'<td class="text-right" id="demo-session-status" data-value="'+sessionInfo.demo_session+'">'
	if(sessionInfo.demo_session == 1){
		html +=			'<h5><span class="badge badge-success"><i class="fa fa-plug" aria-hidden="true"></i> ON </span></h5>'
	} else {
		html +=			'<h5><span class="badge badge-secondary"><i class="fa fa-plug" aria-hidden="true"></i> OFF </span></h5>'
	}
	html +=			'</td>'
	html +=		'</tr>'

	html +=		'<tr>'
	html +=			'<th scope="col">Admin Password</th>'
	html += 		'<td class="text-right">'+sessionInfo.password_admin + ' <i class="fa fa-clipboard" aria-hidden="true" onClick="copyToClipboard(\''+sessionInfo.password_admin+'\')"></i></td>'
	html +=			'<th scope="col" >Player Password</th>'
	html +=			'<td class="text-right">'+sessionInfo.password_player + ' <i class="fa fa-clipboard" aria-hidden="true" onClick="copyToClipboard(\''+sessionInfo.password_player+'\')"></i></td>'
	html +=		'</tr>'

	//Buttons


	html += 	'<tr class="table-info">'
	html +=			'<td colspan="2">' + buttonStartPause + '&nbsp;' + toggleDemoSessionButton + ' ' + '</td>'
	html +=			'<td colspan="2" class="text-right">' + buttonRecreateSession + ' ' + buttonArchiveDownload + ' ' + '</td>'
	html +=		'</tr>'
	html += 	'<tr class="table-info">'
	html +=			'<td colspan="2">' + buttonSaveDownload  + '</td>'
	html +=			'<td colspan="2" class="text-right">' + buttonExportCurrentPath + ' ' + '</td>'
	html +=		'</tr>'

	//Logs
	if(sessionInfo.log && sessionInfo.log.length > 15){
		html += 	'<tr class="table-info">'
		html +=			'<td colspan="4" class="text-left">'
		html +=				'<button id="buttonServerLog" onclick="toggleLogInfo()" class="btn btn-secondary btn-sm"><i class="fa fa-bars" aria-hidden="true"></i></i> Show/Hide Session Creation Log</button>'
		html +=			'</td>'
		html +=		'</tr>'

		html += '<tr id="logInfo">'
		html += 	'<td colspan="4" class="text-center p-0">'
		html +=			'<div style="width: 100%; height: 115px; overflow-y:auto; font-size:14px; background-color: #e9ecef; text-align: left; resize: both;">'
		html +=				sessionInfo.log
		html +=			'</div>'
		html += 	'</td>'
		html += '</tr>'
	}

    return html;
}

function toggleLogInfo() {
	if($('#logInfo').is(":hidden")){
		$('#logInfo').show();
	} else {
		$('#logInfo').hide();
	}
}

function submitNewSession() {
	var name = $('#newSessionName').val();
	var configFile = $('#newConfigFile').val();
	var configVersion = $('#newConfigVersion').val();
	var adminPassword = $('#newAdminPassword').val();
	var playerPassword = $('#newPlayerPassword').val();
	var gameServer = $('#newGameServer').val();
	var watchdog = $('#newWatchdog').val();
	var visibility = $('#newVisibility').val();

	if (isFormValid($('#formNewSession'))) {
		showToast(MessageType.INFO, 'Please wait...');
		$.ajax({
			url: 'api/createnewsession.php',
			data:  {
				Token: currentToken,
				format: 'json',
				name: name,
				configFile: configFile,
				configVersion: configVersion,
				adminPassword: adminPassword,
				playerPassword: playerPassword,
				gameServer: gameServer,
				watchdog: watchdog,
				visibility: visibility
				},
			'error': function() {
				updateInfobox(MessageType.ERROR, 'submitNewSession: Error in AJAX call.');
				},
			'dataType': 'json',
			'success': function(data) {
				if (data.status == 'error') {
					updateInfobox(MessageType.ERROR, 'submitNewSession (API): '+data.message);
				} else {
					if (watchdog == '1') {
						data.message = data.message;
					}
					updateInfobox(MessageType.SUCCESS, data.message);
				}
				},
			'type': 'POST'
		});

		$('#modalNewSession').modal('hide');
		$('#modalNewSession').find("form").trigger("reset");
		//$('#sessionsTable').trigger('update', true);
	}
}

function isFormValid(currentForm) {
	var fail = false;
	var fail_log = '';
	var name;
	$(currentForm).find( 'select, textarea, input' ).each(function(){

		if ($( this ).prop( 'required' ) && !$( this ).val() ) {
			fail = true;
			name = $( this ).attr( 'name' );
			fail_log += name + " is required \n";
			$(this).addClass('is-invalid');
		} else {
			$(this).removeClass('is-invalid');
			$(this).removeClass('is-valid');
		}
	});

	//submit if fail never got set to true
	if (fail) {
		console.log( fail_log );
		return false
	} else {
		return true;
	}
}

/********/
// Saves related functions

function updateSavesTable(visibility) {
	$("#buttonRefreshSavesListIcon").addClass("fa-spin");
	$.ajax({
		'url': 'api/getsaveslist.php',
		'dataType': 'json',
		'type': 'POST',
		'data': {
			Token: currentToken,
			format: 'json',
			visibility: visibility
		},
		'error': function() {
			$('#buttonRefreshSavesListIcon').removeClass('fa-spin');
			$('#savesListtd').html('<p>An error has occurred</p>');
		},
		'success': function(data) {
			$('#buttonRefreshSavesListIcon').removeClass('fa-spin');
			savesListToTable(data.saveslist);
		},
	});
}

function SavesListToOptions(SaveId=0) {
	$.ajax({
		'url': 'api/getsaveslist.php',
		'dataType': 'json',
		'type': 'POST',
		'data': {
			Token: currentToken,
			format: 'json',
			visibility: 'visibility'
		},
		'error': function() {
			//just do nothing
		},
		'success': function(data) {
			$('#buttonRefreshSavesListIcon').removeClass('fa-spin');
			updateSelectServerSaves(data.saveslist, SaveId);
		},
	});
}

function savesListToTable(saveslist) {
	$('#savesListtbody').empty();
	if(saveslist == '') { $('<tr><td colspan="8">No saves yet. Create your first one by viewing a session\'s details and selecting one of the save options.</td></tr>').appendTo('#savesListtbody') }
	$.each(saveslist, function(i, v) {
		// check to see if file is available for download
		if (ShowSaveFile(v.id)) {
			download_icon = '<button class="btn btn-secondary btn-sm" onClick="downloadSave('+v.id+')"><i class="fa fa-download" title="Download file"></i></button>';
		}
		else {
			download_icon = '<button class="btn btn-secondary btn-sm" disabled><i class="fa fa-download" title="Download file"></i></button>';
		}
		if (v.save_type == "full") {
			load_icon = '<button class="btn btn-secondary btn-sm" onClick="showLoadSaveModal('+v.id+')"><i class="fa fa-plus-circle" title="Load Server Save"></i></button>';
		}
		else {
			load_icon = '<button class="btn btn-secondary btn-sm" disabled><i class="fa fa-plus-circle" title="Load Server Save"></i></button>';
		}

		info_icon = '<button class="btn btn-secondary btn-sm" onClick="getSaveInfo('+v.id+')"><i class="fa fa-info-circle" title="Info" ></i></button>';
		
		$('<tr><td>'+v.save_timestamp+'</td>'+
			'<td>'+v.name+'</td>'+
			'<td>'+v.game_current_month+'</td>'+
			'<td>'+v.filename+'</td>'+
			'<td>'+v.save_type+'</td>'+
			'<td class="text-center">'+download_icon+' '+load_icon+' '+info_icon+'</i></td>'+
		'</tr>'
		).appendTo('#savesListtbody')
	})
	//$('#savesTable').trigger('update', true);
}

function ShowSaveFile(saveId) {
	//api call
	var saveavailable = false;
	$.ajax({
		'url': 'api/checkarchiveavailable.php',
		'data': {
			Token: currentToken,
			format: 'json',
			session_id: saveId,
			type: 'full'
		},
		'error': function() {
			updateInfobox(MessageType.ERROR, 'Cannot check archive status.');
			saveavailable = false;
		},
		'dataType': 'json',
		'success': function(data) {
			if (data.status == 'error') {
				updateInfobox(MessageType.ERROR, data.message);
				console.log('checkarchiveavailable', data.message);
			}
			saveavailable = data.archiveavailable;
		}, 
		'async': false,
		'type': 'POST'
	});
	return saveavailable;
}

function downloadSave(id) {
	window.location = "api/downloadsave.php?id="+id+"&Token="+currentToken;
}

function getSaveInfo(saveId) {
	$.ajax({
		'url': 'api/getsaveinfo.php',
		'data': {
			Token: currentToken,
			format: 'json',
			save_id: saveId
		},
		'error': function() {
			updateInfobox('danger', 'getSaveInfo: Error in AJAX call.');
		},
		'dataType': 'json',
		'success': function(data) {
			if (data.status == 'error') {
				updateInfobox('danger', data.message);
				console.log('getSaveInfo (API)', data.message);
			} else {
				updateSaveInfoList(data.saveinfo);
			}
		},
		'type': 'POST'
	});
}

function updateSaveInfoList(SaveInfo) {
	$('#saveInfoTable').empty();
	if(SaveInfo == null) {
		//$('<li class="list-group-item list-group-item-warning">No save selected...</li>').appendTo("#configVersionsInfoList");
	} 
	else {
		archiveButtonShouldArchive = SaveInfo.save_visibility == "active";
		archiveButtonLabel = archiveButtonShouldArchive? "Archive" : "Unarchive";

		buttonDownloadSave = '<button id="buttonConfigDownload" class="btn btn-secondary btn-sm" onClick="downloadSave('+SaveInfo.id+')"><i class="fa fa-download" title="Download config file"></i> Download</button>';
		buttonArchiveSave = '<button id="buttonConfigArchive" class="btn btn-info btn-sm" onClick="setSaveArchived('+SaveInfo.id+', '+archiveButtonShouldArchive+')"><i class="fa fa-archive" title="Set archived state of config file"></i> '+archiveButtonLabel+'</button>';
		if (SaveInfo.save_type == "full") {
			buttonLoadSave = '<button id="buttonLoadSave" class="btn btn-info btn-sm" onClick="showLoadSaveModal('+SaveInfo.id+');"><i class="fa fa-plus-circle" title=""></i> Load</button>';
		}
		else {
			buttonLoadSave = '';
		}

		$('#saveInfoTable').empty();

		$('#saveInfoTable').append(getTableSaveInfo(SaveInfo, buttonDownloadSave, buttonArchiveSave, buttonLoadSave));

		$('#btnSaveInfo').click();
	}
}

function showLoadSaveModal(SaveId) {
	$('#btnCloseSaveInfo').click();
	SavesListToOptions(SaveId);
	setNewServerName(SaveId, " (reloaded)");
	$('#btnCreateServer').click();
	$('#NewSessionModalLoadSave-tab').click();
}

function getTableSaveInfo(SaveInfo, buttonDownloadSave, buttonArchiveSave, buttonLoadSave) {
	html = '';

	html +=		'<tr class="bg-primary">'
	html +=			'<td class="text-center" colspan="2" style="color: #FFFFFF">'+SaveInfo.name+'</td>'
	html +=		'</tr>'
	html +=		'<tr>'
	html +=			'<th>Saved on</th>'
	html +=			'<td class="text-right">'+SaveInfo.save_timestamp+'</td>'
	html +=		'</tr>'
	html +=		'<tr>'
	html +=			'<th>Month & Year of the Simulation</th>'
	html +=			'<td class="text-right">'+SaveInfo.game_current_month+'</td>'
	html +=		'</tr>'
	html +=		'<tr>'
	html +=			'<th>Configuration</th>'
	html +=			'<td class="text-right">'+SaveInfo.filename+'</td>'
	html +=		'</tr>'
	html +=		'<tr>'
	html +=			'<th>Type</th>'
	html +=			'<td class="text-right">'+SaveInfo.save_type+'</td>'
	html +=		'</tr>'
	html +=		'<tr>'
	html +=			'<th>Visibility</th>'
	html +=			'<td class="text-right">'+SaveInfo.save_visibility+'</td>'
	html +=		'</tr>'
	html +=		'<tr>'
	html +=			'<td colspan="2" class="text-left"><p><strong>Notes</strong></p>'
	html +=			'<form class="form-horizontal" role="form" data-toggle="validator" id="formSaveNotes" enctype="multipart/form-data">'
	html +=			'<input type="hidden" id="SaveId" value="'+SaveInfo.id+'"/>'
	html +=				'<textarea class="form-control" id="SaveNotesTextArea" rows="3" onchange="SaveNotes();">'+SaveInfo.save_notes+'</textarea></form>'
	html +=			'</form></td>'
	html +=		'</tr>'
	html +=		'<tr class="table-info">'
	html +=			'<td class="text-center" colspan="2">'
	html +=				buttonLoadSave + ' '
	html +=				buttonDownloadSave + ' '
	html +=				buttonArchiveSave
	html +=			'</td>'
	html +=		'</tr>'

	return html;
}

function setSaveArchived(saveId, archivedState) {
	$.ajax({
		'url': 'api/setsavearchived.php',
		'data': {
			Token: currentToken,
			format: 'json',
			save_id: saveId,
			archived_state: archivedState
		},
		'error': function() {
			updateInfobox(MessageType.ERROR, 'setSaveArchived: Error in AJAX call.');
		},
		'dataType': 'json',
		'success': function(data) {
			if (data.status == 'error') {
				updateInfobox(MessageType.ERROR, 'setSaveArchived (API): '+data.message);
			} else {
				//getConfigFileInfo(configFileVersionId);
				updateInfobox(MessageType.SUCCESS, data.message);
			}
		},
		'type': 'POST'
	});
}

function UploadSave() {
	var uploadedSaveFile = $("#uploadedSaveFile").val();
	if (uploadedSaveFile) {
		showToast(MessageType.INFO, 'Please wait...');
		var formData = new FormData();
		formData.append("Token", currentToken);
		formData.append("uploadedSaveFile", $("#uploadedSaveFile")[0].files[0]);
		$.ajax({
			url: "api/uploadsave.php",
			method: "POST",
			data: formData,
			contentType: false,
			processData: false,
			error: function() {
				updateInfobox(MessageType.ERROR, 'UploadSave: Error in AJAX call.'+data.message);
			},
			dataType: 'json',
			success: function(data) {
				if (data.status == 'error') {
					updateInfobox(MessageType.ERROR, 'UploadSave (API): '+data.message);
				} else {
					updateInfobox(MessageType.SUCCESS, data.message);
				}
			}
		});

		$('#modalUploadSave').modal('hide');
		$('#modalUploadSave').find("form").trigger("reset");
		//$('#savesTable').trigger('update', true);
	}
}

function RecreateLoadSave(ExistingServerId) {
	if (confirm('This will reload the save from which this server was originally recreated. All existing data will be lost. Are you sure?')) {
		$('#sessionInfo').modal('hide');

		var formData = new FormData();
		formData.append("Token", currentToken);
		formData.append("ExistingOrNewServerId", ExistingServerId);
		formData.append("SaveFileSelector", -1);

		callLoadSave(formData);
	}
}

function submitLoadSave() {
	var newServerName = $("#newServerName").val();
	var SaveFileSelector = $("#SaveFileSelector").val();
	var newWatchdogLoadSave = $("#newWatchdogLoadSave").val();
	var SaveFileSelector = parseInt(SaveFileSelector);
	if (isFormValid($('#formLoadSave')))
	{
		$('#modalNewSession').modal('hide');
		$('#modalNewSession').find("form").trigger("reset");

		var formData = new FormData();
		formData.append("Token", currentToken);
		formData.append("ExistingOrNewServerId", -1);
		formData.append("newServerName", newServerName);
		formData.append("SaveFileSelector", SaveFileSelector);
		formData.append("newWatchdogLoadSave", newWatchdogLoadSave);

		callLoadSave(formData);
	}
}

function callLoadSave(formData) {
	$.blockUI({ 
		message: '<h3>Please wait...</h3>This can take anywhere between a couple of seconds to 20 minutes to complete, depending on the server being loaded.',
		css: {  
			border: 'none',
			padding: '15px',
			'text-align': 'left',
			'border-radius': '5px' 
			}
	});
	$.ajax({
		url: "api/loadsave.php",
		method: "POST",
		data: formData,
		contentType: false,
		processData: false,
		error: function() {
			updateInfobox(MessageType.ERROR, 'submitLoadSave: Error in AJAX call.'+data.message);
		},
		dataType: 'json',
		success: function(data) {
			$.unblockUI();
			if (data.status == 'error') {
				updateInfobox(MessageType.ERROR, 'submitLoadSave (API): '+data.message);
			} else {
				updateInfobox(MessageType.SUCCESS, data.message);
			}
		}
	});
}

function SaveNotes() {
	var SaveNotesTextArea = $("#SaveNotesTextArea").val();
	var SaveId = $("#SaveId").val();
	$.ajax({
		'url': 'api/savenotes.php',
		'data': {
			Token: currentToken,
			format: 'json',
			save_id: SaveId,
			notes: SaveNotesTextArea
		},
		'error': function() {
			updateInfobox(MessageType.ERROR, 'SaveNotes: Error in AJAX call.');
		},
		'dataType': 'json',
		'success': function(data) {
			if (data.status == 'error') {
				updateInfobox(MessageType.ERROR, 'SaveNotes (API): '+data.message);
			} else {
				updateInfobox(MessageType.SUCCESS, data.message);
			}
		},
		'type': 'POST'
	});
}

function updateSelectServerSaves(saveslist, SaveId=0) {
	$('#SaveFileSelector').empty();
	var html_options = '';
	$.each(saveslist, function(i, v) {
		if (v.save_type == "full") {
			html_options += "<option value=\"" + v.id + "\" ";
			if (SaveId > 0) {
				if (SaveId == v.id) {
					html_options += "selected";
				}
			}
			html_options += ">" + v.save_timestamp + " - " + v.name + " (" + v.game_current_month + ")</option>";
		}
	});
	if (html_options == "") {
		html_options = "<option value=\"0\">No full server saves yet...</options>";
	}
	else {
		html_options = "<option value=\"0\"></option>" + html_options;
	}
	$('#SaveFileSelector').html(html_options);
}

function setNewServerName(saveId, append=" ") {
	if (saveId > 0) {
		//get the name and put it in newServerName
		$.ajax({
			'url': 'api/getsaveslist.php',
			'data': {
				Token: currentToken,
				format: 'json'
			},
			'error': function() {
				updateInfobox(MessageType.ERROR, 'setNewServerName: Error in AJAX call.');
			},
			'dataType': 'json',
			'success': function(data) {
				if (data.status == 'error') {
					updateInfobox(MessageType.ERROR, 'setNewServerName (API): '+data.message);
				} else {
					var newServerName = '';
					$.each(data.saveslist, function(i, v) {
						if (v.id == saveId) {
							newServerName = v.name + append;
						}
					})
					$('#newServerName').val(newServerName);
				}
			},
			'type': 'POST'
		});
	}
	else {
		$('#newServerName').val("");
	}
}

/********/
// ConfigVersions releated functions


function updateSelectNewConfigVersion(configFileId) {
	$('#newConfigVersion').empty();
	$.ajax({
		'url': 'api/getconfigversionsofconfigfile.php',
		'data': {
			Token: currentToken,
			format: 'json',
			config_file_id: configFileId
		},
		'error': function() {
			updateInfobox(MessageType.ERROR, 'getConfigVersionsOfConfigFile: Error in AJAX call.');
		},
		'dataType': 'json',
		'success': function(data) {
			if (data.status == 'error') {
				updateInfobox(MessageType.ERROR, 'getConfigVersionsOfConfigFile (API): '+data.message);
			} else {
				$('#newConfigVersion').html(data.html_options);
			}
		},
		'type': 'POST'
	});
}

function updateConfigVersionsTable(visibility) {
	$("#buttonRefreshConfigVersionsListIcon").addClass("fa-spin");
	//updateConfigVersionsInfoList(null);
	$.ajax({
		'url': 'api/getconfigversionslist.php',
		'data': {
			Token: currentToken,
			format: 'json',
			config_visibility: visibility
		},
		'error': function() {
			$('#buttonRefreshConfigVersionsListIcon').removeClass('fa-spin');
			$('#configVersionsListtd').html('<p>An error has occurred</p>');
		},
		'dataType': 'json',
		'success': function(data) {
			$('#buttonRefreshConfigVersionsListIcon').removeClass('fa-spin');
			configVersionsListToTable(data.configversionslist, data.numversionslist);
		},
		'type': 'POST'
	});
}

function getConfigFileInfo(configFileVersionId) {
	$.ajax({
		'url': 'api/getconfigfileinfo.php',
		'data': {
			Token: currentToken,
			format: 'json',
			config_file_version_id: configFileVersionId
		},
		'error': function() {
			updateInfobox(MessageType.ERROR, 'getConfigFileInfo: Error in AJAX call.');
		},
		'dataType': 'json',
		'success': function(data) {
			if (data.status == 'error') {
				updateInfobox(MessageType.ERROR, 'getConfigFileInfo (API): '+data.message);
			} else {
				updateConfigFileInfo(data.config_file_info);
				updateConfigVersionsTable($('input[name=radioOptionConfig]:checked').val());
			}
		},
		'type': 'POST'
	});

}

function updateConfigFileInfo(configFileInfo) {
	$('#configVersionsInfoList').empty();
	if(configFileInfo == null) {
		$('<li class="list-group-item list-group-item-warning">No config selected...</li>').appendTo("#configVersionsInfoList");
	} 
	else {
		archiveButtonShouldArchive = configFileInfo.visibility == "active";
		archiveButtonLabel = archiveButtonShouldArchive? "Archive" : "Unarchive";

		buttonDownloadConfig = '<button id="buttonConfigDownload" class="btn btn-secondary btn-sm" onClick="downloadConfigVersion('+configFileInfo.id+')"><i class="fa fa-download" title="Download config file"></i> Download</button>';
		buttonArchiveConfig = '<button id="buttonConfigArchive" class="btn btn-info btn-sm" onClick="setConfigVersionArchived('+configFileInfo.id+', '+archiveButtonShouldArchive+')"><i class="fa fa-archive" title="Set archived state of config file"></i> '+archiveButtonLabel+'</button>';

		$('#configInfoTable').empty();

		$('#configInfoTable').append(getTableConfigInfo(configFileInfo, buttonDownloadConfig, buttonArchiveConfig));

		$('#btnConfigInfo').click();
	}
}


function getTableConfigInfo(configFileInfo, buttonDownloadConfig, buttonArchiveConfig){
	html = '';

	html +=		'<tr class="bg-primary">'
	html +=			'<td class="text-center" colspan="2" style="color: #FFFFFF">'+configFileInfo.filename+' version '+configFileInfo.version+'</td>'
	html +=		'</tr>'
	html +=		'<tr>'
	html +=			'<th>Date Uploaded</th>'
	html +=			'<td class="text-right">'+configFileInfo.upload_time+'</td>'
	html +=		'</tr>'
	html +=		'<tr>'
	html +=			'<th>Uploaded By</th>'
	html +=			'<td class="text-right">'+configFileInfo.upload_user+'</td>'
	html +=		'</tr>'
	html +=		'<tr>'
	html +=			'<th>Last Used</th>'
	html +=			'<td class="text-right">'+configFileInfo.last_played_time+'</td>'
	html +=		'</tr>'
	html +=		'<tr>'
	html +=			'<th>Filename</th>'
	html +=			'<td class="text-right">'+configFileInfo.filename+'</td>'
	html +=		'</tr>'
	html +=		'<tr>'
	html +=			'<th>Description</th>'
	html +=			'<td class="text-right">'+configFileInfo.description+'</td>'
	html +=		'</tr>'
	html +=		'<tr>'
	html +=			'<th>Version</th>'
	html +=			'<td class="text-right">'+configFileInfo.version+'</td>'
	html +=		'</tr>'
	html +=		'<tr>'
	html +=			'<th>Change Message</th>'
	html +=			'<td class="text-right">'+configFileInfo.version_message+'</td>'
	html +=		'</tr>'
	html +=		'<tr class="table-info">'
	html +=			'<td class="text-center" colspan="2">'
	html +=				buttonDownloadConfig + ' '
	html +=				buttonArchiveConfig
	html +=			'</td>'
	html +=		'</tr>'


	return html;
}

function setConfigVersionArchived(configFileVersionId, archivedState)
{
	$.ajax({
		'url': 'api/setconfigfilearchived.php',
		'data': {
			Token: currentToken,
			format: 'json',
			config_file_version_id: configFileVersionId,
			archived_state: archivedState
		},
		'error': function() {
			updateInfobox(MessageType.ERROR, 'setConfigVersionArchived: Error in AJAX call.');
		},
		'dataType': 'json',
		'success': function(data) {
			if (data.status == 'error') {
				updateInfobox(MessageType.ERROR, 'setConfigVersionArchived (API): '+data.message);
			} else {
				getConfigFileInfo(configFileVersionId);
				updateInfobox(MessageType.SUCCESS, data.message);
			}
		},
		'type': 'POST'
	});
}

function downloadConfigVersion(configFileVersionId)
{
	window.location = "api/downloadconfigfile.php?version_id="+configFileVersionId+"&Token="+currentToken;
}

function configVersionsListToTable(configVersionsList, numversionslist = []) {
	$('#configVersionsListtbody').empty();
	var lastConfigName = "";
	var lastclass = "";
	$.each(configVersionsList, function(i, v) {
		info_icon = '<button class="btn btn-secondary btn-sm" onClick="getConfigFileInfo('+v.id+')"><i class="fa fa-info-circle" title="Info" ></i></button>';
		parentDataTarget = "";
		childClass = "";
		foldoutClass = "";
		if (lastConfigName != v.config_file_name) {
			if (numversionslist[v.game_config_files_id] > 1) {
				parentDataTarget = 'data-toggle="collapse" class="collapsed" data-target=".allConfigVersions_'+v.config_file_name+'"';
				foldoutClass ="class=\"foldout_icon\"";
			}
			if (lastclass == "") {
				childClass = "stripe";
				lastclass = "stripe";
			}
			else {
				childClass = "";
				lastclass = "";
			}
		}
		else {
			childClass = lastclass + " removeborder collapse allConfigVersions_"+v.config_file_name;
		}
		lastConfigName = v.config_file_name;

		$('<tr class="'+childClass+'">' +
			'<td style="min-width: 25px;" '+parentDataTarget+'><i class="fa"></i></td>' +
			'<td>'+v.config_file_name+'</td>'+
			'<td>'+v.version+'</td>'+
			'<td>'+v.upload_time+'</td>'+
			'<td>'+v.upload_user+'</td>'+
			'<td>'+v.last_played_time+'</td>'+
			'<td class="text-center">'+info_icon+'</i></td>'+
		'</tr>'
		).appendTo('#configVersionsListtbody');
	})
	$('[data-toggle="collapse"]').on('click', function() {
		$(this).toggleClass('collapsed');
 	});
	//$('#configVersionsTable').trigger('update', true);
}

function configListToOptions() {
	$('#newConfigFile').empty();
	$('#newConfigFileOriginal').empty();
	$.ajax({
		'url': 'api/getconfiglist.php',
		'data': {
			Token: currentToken,
			format: 'json'
			},
		'error': function() {
			$('#newConfigFile').html('<option>An error occurred.</option>');
			},
		'dataType': 'json',
		'success': function(data) {
				$('<option disabled selected value> Select existing configuration or \'New Configuration File\'...</option>').appendTo('#newConfigFileOriginal');
				$('<option disabled selected value> Select a configuration ...</option>').appendTo('#newConfigFile');
				$.each(data.configlist, function(i, v) {
					$('<option value="'+v.id+'" title="'+v.description+'">'+v.filename+'</option>').appendTo('#newConfigFile');
					$('<option value="'+v.id+'" title="'+v.description+'">'+v.filename+'</option>').appendTo('#newConfigFileOriginal');
				});
				$('<option value="NewConfigFile" title="Create a new config file stream">New configuration file</option>').appendTo('#newConfigFileOriginal');
			},
		'type': 'POST'
	});
}

function watchdogListToOptions() {
	$('#newWatchdog').empty();
	$('#newWatchdogLoadSave').empty();
	$.ajax({
		'url': 'api/getwatchdoglist.php',
		'data': {
			Token: currentToken,
			format: 'json'
		},
		'error': function() {
			$('#newWatchdog').html('<option>An error occurred.</option>');
			$('#newWatchdogLoadSave').html('<option>An error occurred.</option>');
		},
		'dataType': 'json',
		'success': function(data) {
				$.each(data.watchdoglist, function(i, v) {
					$('<option value="'+v.id+'" title="'+v.address+'">'+v.name+'</option>').appendTo('#newWatchdog');
					$('<option value="'+v.id+'" title="'+v.address+'">'+v.name+'</option>').appendTo('#newWatchdogLoadSave');
				})
			},
		'type': 'POST'
	});
}



/*
* New config file
*/

function submitNewConfigFile()  {
	var configFileUpload = $("#newConfigFileContent").val();
	var originalConfigInput = $("#newConfigFileOriginal").val();
	var newConfigFileName = $("#newConfigFileName").val();
	var description = $("#newConfigDescription").val();
	var changeMessage = $("#newConfigChangeMessage").val();

	var originalConfigId = parseInt(originalConfigInput);
	if (isFormValid($('#formNewConfig')) && configFileUpload &&
		((!isNaN(originalConfigId) && originalConfigId > 0) || (isNaN(originalConfigId) && newConfigFileName)) &&
		description &&
		changeMessage)
	{
		showToast(MessageType.INFO, 'Please wait...');
		var formData = new FormData();
		formData.append("Token", currentToken);
		formData.append("config_file_id", originalConfigId);
		formData.append("new_config_file_name", newConfigFileName);
		formData.append("description", description);
		formData.append("change_message", changeMessage);
		formData.append("config_file", $("#newConfigFileContent")[0].files[0]);

		$.ajax({
			url: "api/uploadconfigfile.php",
			method: "POST",
			data: formData,
			contentType: false,
			processData: false,
			error: function() {
				updateInfobox(MessageType.ERROR, 'submitNewConfigFile: Error in AJAX call.'+data.message);
			},
			dataType: 'json',
			success: function(data) {
				if (data.status == 'error') {
					updateInfobox(MessageType.ERROR, 'submitNewConfigFile (API): '+data.message);
				} else {
					updateInfobox(MessageType.SUCCESS, data.message);
				}
			}
		});

		$('#modalNewConfigFile').modal('hide');
		$('#modalNewConfigFile').find("form").trigger("reset");
		//$('#configVersionsTable').trigger('update', true);
	}
}

function updateConfigDescription(configFileId) {
	$("#buttonRefreshConfigVersionsListIcon").addClass("fa-spin");
	//updateConfigVersionsInfoList(null);
	$.ajax({
		'url': 'api/getconfigfiledescription.php',
		'data': {
			Token: currentToken,
			format: 'json'
		},
		'error': function() {
			$('#buttonRefreshConfigVersionsListIcon').removeClass('fa-spin');
			$('#configVersionsListtd').html('<p>An error has occurred</p>');
		},
		'dataType': 'json',
		'success': function(data) {
			$('#buttonRefreshConfigVersionsListIcon').removeClass('fa-spin');
			configVersionsListToTable(data.configversionslist);
		},
		'type': 'POST'
	});
}

function onUploadConfigFileOriginalSelected(selectedValue) {
	var isNewVersion = selectedValue == "NewConfigFile";
	$("#newConfigFileName").prop("disabled", !isNewVersion);

	if(isNewVersion)
	{
		$("#newConfigDescription").val("");
	}
	else
	{
		$.ajax({
			'url': 'api/getconfigfiledescription.php',
			'data': {
				Token: currentToken,
				format: 'json',
				config_file_id: parseInt(selectedValue)
			},
			'dataType': 'json',
			'success': function(data) {
				if (data.status == "success") {
					$("#newConfigDescription").val(data.description);
				}
			},
			'type': 'POST'
		});
	}
}

function WatchdogServerList() {
	$('#watchdogServerBody').empty();
	$.ajax({
		'url': 'api/getwatchdoglist.php',
		'data': {
			Token: currentToken,
			format: 'json'
			},
		'error': function() {
			$('#watchdogServerBody').html('An error occurred.');
			},
		'dataType': 'json',
		'success': function(data) {
			$('bla').appendTo('#watchdogServerBody');
				$.each(data.watchdoglist, function(i, v) {
					$('<tr><td>' + v.name + '</td><td>' + v.address + '</td><td></td></tr>').appendTo('#watchdogServerBody');
				});
			},
		'type': 'POST'
	});
}

function GetServerAddr() {
	$.ajax({
		'url': 'api/getserveraddr.php',
		'data': {
			Token: currentToken,
			format: 'json'
			},
		'error': function() {
			$('#ServerAddress').val('An error occurred.');
			},
		'dataType': 'json',
		'success': function(data) {
				$('#ServerAddress').val(data.serveraddr);
			},
		'type': 'POST'
	});
}

function submitNewServer() {
	var name = $('#ServerName').val();
	var address = $('#ServerAddress').val();
	var serverid = $('#ServerID').val();
	if ([name, address, serverid].every(Boolean)) {
		$.ajax({
			url: 'api/addgameserver.php',
			data:  {
				Token: currentToken,
				format: 'json',
				name: name,
				address: address,
				serverid: serverid,
			},
			'error': function() {
				updateInfobox('danger', 'addgameserver: Error in AJAX call.');
			},
			'dataType': 'json',
			'success': function(data) {
				if (data.status == 'error') {
					updateInfobox('danger', 'addgameserver (API): '+data.message);
				} else {
					updateInfobox('success', data.message);
				}
			},
			'async': false,
			'type': 'POST'
		});
	}
	else {
		updateInfobox('danger', 'Please fill in all the required fields');
	}
}

function submitNewWatchdogServer() {
	var name = $('#newWatchdogServerName').val();
	var address = $('#newWatchdogServerAddress').val();

	if ([name, address].every(Boolean)) {
		$.ajax({
			url: 'api/addwatchdogserver.php',
			data:  {
				Token: currentToken,
				format: 'json',
				name: name,
				address: address,
				},
			'error': function() {
				updateInfobox('danger', 'addwatchdogserver: Error in AJAX call.');
				},
			'dataType': 'json',
			'success': function(data) {
				if (data.status == 'error') {
					updateInfobox('danger', 'addwatchdogserver (API): '+data.message);
				} else {
					updateInfobox('success', data.message);
					WatchdogServerList();
					GetServerAddr();
				}
				},
			'type': 'POST'
		});
		$('#sessionsTable').trigger('update', true);
	}
	else {
		updateInfobox('danger', 'Please fill in all the required fields');
	}
}

