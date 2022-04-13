
function getConfigFileInfo(configFileVersionId) {
    var url = 'api/readGameConfig.php';
    var data = {
        config_version_id: configFileVersionId
    }
    $.when(CallAPI(url, data)).done(function(results) {
	    if (results.success) {
            updateConfigFileInfo(results);
        } else {
            updateInfobox(MessageType.ERROR, 'getConfigFileInfo (API): '+results.message);
        }
  	});
}


function updateConfigFileInfo(data) {
	$('#configVersionsInfoList').empty();
	if(data.gameconfig == null) {
		$('<li class="list-group-item list-group-item-warning">No config selected...</li>').appendTo("#configVersionsInfoList");
	} 
	else {
		$('#buttonConfigDownload').attr('onClick', 'downloadConfigVersion('+data.gameconfig.id+');');
        $('#buttonConfigArchive').attr('onClick', 'setConfigVersionArchived('+data.gameconfig.id+');');
		archiveButtonLabel = (data.gameconfig.visibility == "active") ? "Archive" : "Un-archive";
        $('#buttonConfigArchive').html('<i class="fa fa-archive" title="Set archived state of config file"></i> '+archiveButtonLabel);
        
        $('#ConfigInfoFilenameVersion').html(data.gameconfig.filename+' version '+data.gameconfig.version);
        $('#ConfigInfoUploadTime').html(data.gameconfig_pretty.upload_time);
        $('#ConfigInfoUploadUser').html(data.gameconfig_pretty.upload_user);
        $('#ConfigInfoLastPlayedTime').html(data.gameconfig_pretty.last_played_time);
        $('#ConfigInfoFilename').html(data.gameconfig.filename);
        $('#ConfigInfoDescription').html(data.gameconfig.description);
        $('#ConfigInfoVersion').html(data.gameconfig.version);
        $('#ConfigInfoVersionMessage').html(data.gameconfig.version_message);
	}
}

function configVersionsListToTable(configVersionsList) {
	$('#configVersionsListtbody').empty();
	var html = "";
	var colouring = "";
	$.each(configVersionsList, function(i, v) {
		colouring = (colouring == "") ? "stripe" : "";
		$.each(v.all_versions, function (n_i, n_v) {
			if (v.all_versions.length > 1 && n_i > 0) childClass = colouring + " removeborder collapse allConfigVersions_"+v.filename;
			else childClass = colouring; 
			html += '<tr class="'+childClass+'">';
			if (v.all_versions.length > 1 && n_i == 0) html += '<td style="min-width: 25px;" data-toggle="collapse" class="collapsed" data-target=".allConfigVersions_'+v.filename+'"><i class="fa"></i></td>';
			else html += '<td style="min-width: 25px;"><i class="fa"></i></td>';
			if (n_i == 0) html += '<td>'+v.filename+'</td>';
			else html += '<td></td>';
			html += '<td>'+n_v.version+'</td>';
			html += '<td>'+n_v.pretty.upload_time+'</td>';
			html += '<td>'+n_v.pretty.upload_user+'</td>';
			html += '<td>'+n_v.pretty.last_played_time+'</td>';
			html += '<td class="text-center"><button class="btn btn-secondary btn-sm" data-toggle="modal" data-target="#configInfo" onClick="getConfigFileInfo('+n_v.id+');"><i class="fa fa-info-circle" title="Info" ></i></button></i></td>';
			html += '</tr>';
		})
	})
	$(html).appendTo('#configVersionsListtbody');
	$('[data-toggle="collapse"]').on('click', function() {
		$(this).toggleClass('collapsed');
 	});
}


function setConfigVersionArchived(configFileVersionId) {
    var url = 'api/deleteGameConfig.php';
    var data = {
        config_version_id: configFileVersionId
    }
    $.when(CallAPI(url, data)).done(function(results) {
        if (results.success) {
            getConfigFileInfo(configFileVersionId);
			updateInfobox(MessageType.SUCCESS, 'Config file successfully set to '+results.gameconfig.visibility+'.');	
        } else {
            updateInfobox(MessageType.ERROR, 'setConfigVersionArchived (API): '+results.message);
        }
	});
}

function downloadConfigVersion(configFileVersionId)
{
	window.location = "api/downloader.php?id="+configFileVersionId+"&request=gameconfig/getFile";
}

function updateConfigVersionsTable(visibility) {
	$("#buttonRefreshConfigVersionsListIcon").addClass("fa-spin");
	var url = 'api/browseGameConfig.php';
    var data = {
        visibility: visibility
    }
    $.when(CallAPI(url, data)).done(function(results) {
		if (results.success) {
			configVersionsListToTable(results.configslist);
		}
		$('#buttonRefreshConfigVersionsListIcon').removeClass('fa-spin');
	});
}

function updateSelectNewConfigVersion(configFileId) {
	$('#newConfigVersion').empty();
	var url = 'api/browseGameConfig.php';
    var data = {
        visibility: 'active'
    }
    $.when(CallAPI(url, data)).done(function(results) {
		if (!results.success) {
			updateInfobox(MessageType.ERROR, 'updateSelectNewConfigVersion (API): '+results.message);
		} else {
			var html = '';
			$.each(results.configslist, function(i, v) {
				if (v.id == configFileId) {
					$.each(v.all_versions, function(n_i, n_v) {
						html += '<option value="'+n_v.id+'">#'+n_v.version+': '+n_v.version_message+'</option>';
					})
				}
			})
			$('#newConfigVersion').html(html);
		}
	})
}

function configListToOptions() {
	$('#newConfigFile').empty();
	$('#newConfigFileOriginal').empty();
	var url = 'api/browseGameConfig.php';
    var data = {
        visibility: 'active'
    }
    $.when(CallAPI(url, data)).done(function(results) {
		if (results.success) {
			$('<option disabled selected value> Select existing configuration or \'New Configuration File\'...</option>').appendTo('#newConfigFileOriginal');
			$('<option disabled selected value> Select a configuration ...</option>').appendTo('#newConfigFile');
			$.each(results.configslist, function(i, v) {
				$('<option value="'+v.id+'" title="'+v.description+'">'+v.filename+'</option>').appendTo('#newConfigFile');
				$('<option value="'+v.id+'" title="'+v.description+'">'+v.filename+'</option>').appendTo('#newConfigFileOriginal');
			});
			$('<option value="-1" title="Create a new config file stream">New configuration file</option>').appendTo('#newConfigFileOriginal');
		}
	});
}

function onUploadConfigFileOriginalSelected(selectedValue) {
	var isNewVersion = selectedValue == "-1";
	$("#newConfigFileName").prop("disabled", !isNewVersion);

	if(isNewVersion)
	{
		$("#newConfigDescription").val("");
	}
	else
	{
		var url = 'api/readGameConfig.php';
		var data = {
			config_file_id: selectedValue
		}
		$.when(CallAPI(url, data)).done(function(results) {
			if (results.success) {
				$("#newConfigDescription").val(results.gameconfig.description);
			}
		});
	}
}



function submitNewConfigFile()  {
	if (isFormValid($('#formNewConfig')) && $("#newConfigFileContent").val())
	{
		var url = 'api/addGameConfig.php';
		var data = new FormData();
		data.append("config_file", $("#newConfigFileContent")[0].files[0]);
		data.append("game_config_files_id", $("#newConfigFileOriginal").val());
		data.append("filename", $("#newConfigFileName").val());
		data.append("description", $("#newConfigDescription").val());
		data.append("version_message", $("#newConfigChangeMessage").val());
		$.when(CallAPIWithFileUpload(url, data)).done(function(results) {
			if (results.success) {
				updateInfobox(MessageType.SUCCESS, "Config file successfully added.");
			} else {
				updateInfobox(MessageType.ERROR, 'submitNewConfigFile (API): '+results.message);
			}
		});
		$('#modalNewConfigFile').modal('hide');
		$('#modalNewConfigFile').find("form").trigger("reset");
	}
}