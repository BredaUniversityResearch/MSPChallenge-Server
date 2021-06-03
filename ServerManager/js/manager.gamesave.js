function newSessionChoice() {
	if ($('#NewSessionModalDefault').is(':visible')) {
		submitNewSession();
	}
	else if ($('#NewSessionModalLoadSave').is(':visible')) {
		submitLoadSave();
	}
}

function submitLoadSave() {
    if (isFormValid($('#formLoadSave')))
	{
        var url = 'api/readGameSave.php';
        var data = {
            save_id: $("#SaveFileSelector").val(),
            name: $("#newServerName").val(),
            watchdog_server_id: $("#newWatchdogLoadSave").val(),
            action: 'load'
        }
        
        $.when(CallAPI(url, data)).done(function(results) {
            if (results.success) {
                new_game_session = results.gamesessions[results.gamesessions.length - 1];
                updateInfobox(MessageType.SUCCESS, "Session being reloaded with the ID "+new_game_session.id+". Please be patient while the process finalises.");
                ShowLogToast(new_game_session.id);
            }
            else {
                updateInfobox(MessageType.ERROR, 'submitLoadSave (API): '+results.message);
                console.log('submitLoadSave (API)', results.message);
            }
        });

        $('#modalNewSession').modal('hide');
		$('#modalNewSession').find("form").trigger("reset");
    }
    else 
    {
        alert("Please check the form. Make sure all required fields have been filled in.");
    }
}

function UploadSave() {
	var uploadedSaveFile = $("#uploadedSaveFile").val();
	if (uploadedSaveFile) {
		showToast(MessageType.INFO, 'Please wait...');
        var url = 'api/addGameSave.php';
        var data = new FormData();
		data.append("uploadedSaveFile", $("#uploadedSaveFile")[0].files[0]);        
        $.when(CallAPIWithFileUpload(url, data)).done(function(results) {
			if (results.success) {
				updateInfobox(MessageType.SUCCESS, "Save file successfully uploaded. Now available for use.");
			} else {
                updateInfobox(MessageType.ERROR, 'UploadSave (API): '+results.message);
			}
		});

		$('#modalUploadSave').modal('hide');
		$('#modalUploadSave').find("form").trigger("reset");
	}
}

function saveSession(sessionId, saveType) {
	showToast(MessageType.INFO, 'Please wait...');
    var url = 'api/addGameSave.php';
    var data = {
        session_id: sessionId,
        save_type: saveType
    }
    $.when(CallAPI(url, data)).done(function(results) {
        if (results.success) {
            updateInfobox(MessageType.SUCCESS, "Session successful saved.");
            $('#sessionInfo').modal('hide');
        } 
        else {
            updateInfobox(MessageType.ERROR, results.message);
            console.log('saveSession' , results.message);
        }
	});
}

function SaveNotes() {
    var url = 'api/editGameSave.php';
    var data = {
        save_id: $("#SaveInfoId").val(),
        save_notes: $("#SaveInfoNotes").val()
    }
    $.when(CallAPI(url, data)).done(function(results) {
        if (results.success) {
            updateInfobox(MessageType.SUCCESS, "Notes successfully saved.");
        } else {
            updateInfobox(MessageType.ERROR, 'SaveNotes (API): '+results.message);
        }
	});
}

function setNewServerNameInForm(saveId, append=" ") {
	if (saveId > 0) {
        var url = 'api/readGameSave.php';
        var data = {
            save_id: saveId
        }
        $.when(CallAPI(url, data)).done(function(results) {
            if (results.success) {
                $('#newServerName').val(results.gamesave.name + append);
            } else {
                updateInfobox(MessageType.ERROR, 'setNewServerName (API): '+results.message);
            }
		});
	}
}

function showLoadSaveModal(SaveId) {
	$('#btnCloseSaveInfo').click();
	SavesListToOptions(SaveId);
	setNewServerNameInForm(SaveId, " (reloaded)");
	$('#btnCreateServer').click();
	$('#NewSessionModalLoadSave-tab').click();
}

function downloadSave(id) {
    window.location = "api/downloader.php?id="+id+"&request=gamesave/getFullZipPath";
}

function getSaveInfo(saveId) {
	var url = 'api/readGameSave.php';
    var data = {
        save_id: saveId
    }
    $.when(CallAPI(url, data)).done(function(results) {
        if (results.success) {
            updateSaveInfoList(results);
        } else {
            updateInfobox(MessageType.ERROR, results.message);
            console.log('getSaveInfo (API)', results.message);
        }
	});
}

function updateSaveInfoList(data) {
	if(data.gamesave) {
		archiveButtonLabel = (data.gamesave.save_visibility == "active") ? "Archive" : "Un-archive";
		$('#buttonSaveDownload').attr('onClick', 'downloadSave('+data.gamesave.id+');');
		$('#buttonSaveArchive').attr('onClick', 'setSaveArchived('+data.gamesave.id+');');
        $('#buttonSaveArchive').html('<i class="fa fa-archive" title="Set archived state of config file"></i> '+archiveButtonLabel);
		if (data.gamesave.save_type == "full") {
			$('#buttonLoadSave').attr('onClick', 'showLoadSaveModal('+data.gamesave.id+');');
            $('#buttonLoadSave').show();
		}
		else {
			$('#buttonLoadSave').hide();
		}

        $('#SaveInfoName').html(data.gamesave.name);
        $('#SaveInfoTimestamp').html(data.gamesave.save_timestamp);
        $('#SaveInfoMonth').html(data.gamesave_pretty.game_current_month);
        $('#SaveInfoConfig').html(data.gamesave.game_config_files_filename);
        $('#SaveInfoType').html(data.gamesave.save_type);
        $('#SaveInfoVisibility').html(data.gamesave.save_visibility);
        $('#SaveInfoId').val(data.gamesave.id);
        $('#SaveInfoNotes').val(data.gamesave.save_notes);        
	}
}

function updateSavesTable(visibility) {
	$("#buttonRefreshSavesListIcon").addClass("fa-spin");
    var url = 'api/browseGameSave.php';
    var data = {
        visibility: visibility
    }
    $.when(CallAPI(url, data)).done(function(results) {
        $('#buttonRefreshSavesListIcon').removeClass('fa-spin');
        savesListToTable(results.saveslist);
	});
}

function SavesListToOptions(SaveId=0) {
	var url = 'api/browseGameSave.php';
    var data = {
        save_type: 'full'
    }
    $.when(CallAPI(url, data)).done(function(results) {
        updateSelectServerSaves(results.saveslist, SaveId);		
	});
}

function updateSelectServerSaves(saveslist, SaveId=0) {
	$('#SaveFileSelector').empty();
	var html_options = '';
	$.each(saveslist, function(i, v) {
		html_options += "<option value=\"" + v.id + "\" ";
		if (SaveId > 0) {
			if (SaveId == v.id) {
				html_options += "selected";
			}
		}
		html_options += ">" + v.save_timestamp + " - " + v.name + " (" + v.game_current_month + ")</option>";
	});
	if (html_options == "") {
		html_options = "<option value=\"0\">No full server saves yet...</options>";
	}
	else {
		html_options = "<option value=\"0\"></option>" + html_options;
	}
	$('#SaveFileSelector').html(html_options);
}

function savesListToTable(saveslist) {
	$('#savesListtbody').empty();
	if(saveslist == '') { $('<tr><td colspan="8">No saves yet. Create your first one by viewing a session\'s details and selecting one of the save options.</td></tr>').appendTo('#savesListtbody') }
	$.each(saveslist, function(i, v) {
		// check to see if file is available for download
		if (v.save_path != false) {
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

		info_icon = '<button class="btn btn-secondary btn-sm" data-toggle="modal" data-target="#saveInfo" onClick="getSaveInfo('+v.id+');"><i class="fa fa-info-circle" title="Info" ></i></button>';
		
		$('<tr><td>'+v.save_timestamp+'</td>'+
			'<td>'+v.name+'</td>'+
			'<td>'+v.game_current_month+'</td>'+
			'<td>'+v.game_config_files_filename+'</td>'+
			'<td>'+v.save_type+'</td>'+
			'<td class="text-center">'+download_icon+' '+load_icon+' '+info_icon+'</i></td>'+
		'</tr>'
		).appendTo('#savesListtbody')
	})
}


function setSaveArchived(saveId) {
    var url = 'api/deleteGameSave.php';
    var data = {
        save_id: saveId
    }
    $.when(CallAPI(url, data)).done(function(results) {
        if (results.success) {
            getSaveInfo(saveId);
        } else {
            updateInfobox(MessageType.ERROR, 'setSaveArchived (API): '+results.message);
        }
	});
}