function submitNewGeoServer() {
	var name = $('#newGeoServerName').val();
	var address = $('#newGeoServerAddress').val();
	var username = $('#newGeoServerUsername').val();
	var password = $('#newGeoServerPassword').val();

    if ([name, address, username, password].every(Boolean)) {
        var url = 'api/addGeoServer.php';
        var data =  {
            name: name,
            address: address,
            username: username,
            password: password
        }
        $.when(CallAPI(url, data)).done(function(results) {
            if (results.success) {
                updateInfobox(MessageType.SUCCESS, "GeoServer successfully added.");
                $('#modalNewGeoServers').modal('hide');
	            $('#modalNewGeoServers').find("form").trigger("reset");
            } else {
                updateInfobox(MessageType.ERROR, 'submitNewGeoServer (API): '+results.message);
            }
        });
    }
    else {
		updateInfobox(MessageType.ERROR, 'Please fill in all the required fields');
	}	
}

function GeoServerList() {
	var url = "api/browseGeoServer.php";
    $.when(CallAPI(url)).done(function(results) {
        $('#GeoServerBody').empty();
        $.each(results.geoserverslist, function(row, geoserver) {
           setGeoServerList(geoserver);
        });
	});
}

function setGeoServerList(geoserver) {
    var html = '<tr>';
    html += '<td>' + geoserver.name + '</td><td>' + geoserver.address + '</td>';
    html += '<td>';
    if (geoserver.id != 1)
        html += '<button type="button" class="btn btn-primary btn-sm" onclick="GeoServerEdit('+geoserver.id+');"><i class="fa fa-pencil"></i></button>';
    if (geoserver.available == 1) {
        html += '<button type="button" id="GeoServerAvailability'+geoserver.id+'" class="btn btn-warning btn-sm" title="Available. Click to make unavailable." onclick="GeoServerToggleAvailability('+geoserver.id+');"><i class="fa fa-eye"></i></button>';
    } else {
        html += '<button type="button" id="GeoServerAvailability'+geoserver.id+'" class="btn btn-warning btn-sm" title="Unavailable. Click to make available." onclick="GeoServerToggleAvailability('+geoserver.id+');"><i class="fa fa-eye-slash"></i></button>';
    }
    html += '</td>';
    html += '</tr>';
    $('#GeoServerBody').append(html);
}

function GeoServerToggleAvailability(geoserver_id) {
    var url = "api/deleteGeoServer.php";
    var data = {
        geoserver_id: geoserver_id
    }
    $.when(CallAPI(url, data)).done(function(results) {
        if (results.success) {
            if (results.geoserver.available == 1) {
                $('#GeoServerAvailability'+results.geoserver.id).prop('title', 'Available. Click to make unavailable.');
                $('#GeoServerAvailability'+results.geoserver.id).html('<i class="fa fa-eye"></i>');
            } else {
                $('#GeoServerAvailability'+results.geoserver.id).prop('title', 'Unavailable. Click to make available.');
                $('#GeoServerAvailability'+results.geoserver.id).html('<i class="fa fa-eye-slash"></i>');
            }
        } else {
            updateInfobox(MessageType.ERROR, results.message);
        }
    });
}

function GeoServerEdit(geoserver_id) {
    var url = "api/readGeoServer.php";
    var data = {
        geoserver_id: geoserver_id
    }
    $.when(CallAPI(url, data)).done(function(results) {
        if (results.success) {
            $('#GeoServerFormTitle').html("Edit an existing GeoServer");
            $('#newGeoServerName').val(results.geoserver.name);
            $('#newGeoServerAddress').val(results.geoserver.address);
            $('#editGeoserverID').val(results.geoserver.id);
            $('#GeoServerFormButton').html("Update");
            $('#GeoServerFormButton').attr("onclick", "submitGeoServerEdit();");
        } else {
            updateInfobox(MessageType.ERROR, results.message);
        }
    });
}

function submitGeoServerEdit() {
    var url = "api/editGeoServer.php";
    var data = {
        geoserver_id: $('#editGeoserverID').val(),
        name: $('#newGeoServerName').val(),
        address: $('#newGeoServerAddress').val()
    }
    if ($('#newGeoServerUsername').val()) data['username'] = $('#newGeoServerUsername').val();
    if ($('#newGeoServerPassword').val()) data['password'] = $('#newGeoServerPassword').val();
    $.when(CallAPI(url, data)).done(function(results) {
        if (results.success) {
            updateInfobox(MessageType.SUCCESS, 'GeoServer update successfully.');
            $('#GeoServerFormTitle').html("Add a new GeoServer");
            $('#modalNewGeoServers').find("form").trigger("reset");
            $('#GeoServerFormButton').html("Add");
            $('#GeoServerFormButton').attr("onclick", "submitNewGeoserver();");
            GeoServerList();
        } else {
            updateInfobox(MessageType.ERROR, results.message);
        }
    });
}

function watchdogListToOptions() {
	$('#newWatchdog').empty();
	$('#newWatchdogLoadSave').empty();
    var url = "api/browseWatchdog.php";
    $.when(CallAPI(url)).done(function(results) {
	    $.each(results.watchdogslist, function(i, watchdog) {
            if (watchdog.available == 1) {
                $('<option value="'+watchdog.id+'" title="'+watchdog.address+'">'+watchdog.name+'</option>').appendTo('#newWatchdog');
                $('<option value="'+watchdog.id+'" title="'+watchdog.address+'">'+watchdog.name+'</option>').appendTo('#newWatchdogLoadSave');
            }
        })
	});
}

function WatchdogServerList() {
    var url = "api/browseWatchdog.php";
    $.when(CallAPI(url)).done(function(results) {
        $('#watchdogServerBody').empty();
        $.each(results.watchdogslist, function(i, watchdog) {
            setWatchdogServerList(watchdog);
        });
	});
}

function setWatchdogServerList(watchdog) {
    var html = '<tr>';
    html += '<td>' + watchdog.name + '</td><td>' + watchdog.address + '</td>';
    html += '<td>';
    if (watchdog.id != 1)
        html += '<button type="button" class="btn btn-primary btn-sm" onclick="WatchdogEdit('+watchdog.id+');"><i class="fa fa-pencil"></i></button>';
    if (watchdog.available == 1) {
        html += '<button type="button" id="WatchdogAvailability'+watchdog.id+'" class="btn btn-warning btn-sm" title="Available. Click to make unavailable." onclick="WatchdogToggleAvailability('+watchdog.id+');"><i class="fa fa-eye"></i></button>';
    } else {
        html += '<button type="button" id="WatchdogAvailability'+watchdog.id+'" class="btn btn-warning btn-sm" title="Unavailable. Click to make available." onclick="WatchdogToggleAvailability('+watchdog.id+');"><i class="fa fa-eye-slash"></i></button>';
    }
    html += '</td>';
    html += '</tr>';
    $('#watchdogServerBody').append(html);
}

function submitNewWatchdogServer() {
	var name = $('#newWatchdogServerName').val();
	var address = $('#newWatchdogServerAddress').val();

	if ([name, address].every(Boolean)) {
        var url = "api/addWatchdog.php";
        var data = {
            name: name,
			address: address,
        }
        $.when(CallAPI(url, data)).done(function(results) {
		    if (results.success) {
                updateInfobox(MessageType.SUCCESS, "Watchdog successfully added.");                
                $('#modalNewSimServers').modal('hide');
	            $('#modalNewSimServers').find("form").trigger("reset");
            } else {
                updateInfobox(MessageType.ERROR, 'submitNewWatchdogServer (API): '+results.message);
            }
		});
	}
	else {
		updateInfobox(MessageType.ERROR, 'Please fill in all the required fields');
	}
}

function WatchdogEdit(watchdog_id) {
    var url = "api/readWatchdog.php";
    var data = {
        watchdog_id: watchdog_id
    }
    $.when(CallAPI(url, data)).done(function(results) {
        if (results.success) {
            $('#WatchdogFormTitle').html("Edit an existing Watchdog");
            $('#newWatchdogServerName').val(results.watchdog.name);
            $('#newWatchdogServerAddress').val(results.watchdog.address);
            $('#editWatchdogID').val(results.watchdog.id);
            $('#WatchdogFormButton').html("Update");
            $('#WatchdogFormButton').attr("onclick", "submitWatchdogEdit();");
        } else {
            updateInfobox(MessageType.ERROR, results.message);
        }
    });
}

function submitWatchdogEdit() {
    var url = "api/editWatchdog.php";
    var data = {
        watchdog_id: $('#editWatchdogID').val(),
        name: $('#newWatchdogServerName').val(),
        address: $('#newWatchdogServerAddress').val()
    }
    $.when(CallAPI(url, data)).done(function(results) {
        if (results.success) {
            updateInfobox(MessageType.SUCCESS, 'Watchdog update successfully.');
            $('#WatchdogFormTitle').html("Add a new GeoServer");
            $('#modalNewSimServers').find("form").trigger("reset");
            $('#WatchdogFormButton').html("Add");
            $('#WatchdogFormButton').attr("onclick", "submitNewWatchdogServer();");
            WatchdogServerList();
        } else {
            updateInfobox(MessageType.ERROR, results.message);
        }
    });
}

function WatchdogToggleAvailability(watchdog_id) {
    var url = "api/deleteWatchdog.php";
    var data = {
        watchdog_id: watchdog_id
    }
    $.when(CallAPI(url, data)).done(function(results) {
        if (results.success) {
            if (results.watchdog.available == 1) {
                $('#WatchdogAvailability'+results.watchdog.id).prop('title', 'Available. Click to make unavailable.');
                $('#WatchdogAvailability'+results.watchdog.id).html('<i class="fa fa-eye"></i>');
            } else {
                $('#WatchdogAvailability'+results.watchdog.id).prop('title', 'Unavailable. Click to make available.');
                $('#WatchdogAvailability'+results.watchdog.id).html('<i class="fa fa-eye-slash"></i>');
            }
        } else {
            updateInfobox(MessageType.ERROR, results.message);
        }
    });
}

function GetServerAddr() {
    var url = "api/readServerManager.php";
    $.when(CallAPI(url)).done(function(results) {
	    $('#ServerName').val(results.servermanager.server_name);
        $('#ServerDescription').val(results.servermanager.server_description);
        $('#ServerAddress').val(results.servermanager.server_address);
	});
}

function editServerManager() {
	var name = $('#ServerName').val();
	var address = $('#ServerAddress').val();
	var description = $('#ServerDescription').val();
	if ([name, address, description].every(Boolean)) {
		var url = "api/editServerManager.php";
        var data = {
            server_name: name,
            server_address: address,
            server_description: description,
            jwt: currentToken
        }
        $.when(CallAPI(url, data)).done(function(results) {
            if (results.success) {
                updateInfobox(MessageType.SUCCESS, "Server settings successfully updated.");
                $('#modalServerDetails').modal('hide');
	            $('#modalServerDetails').find("form").trigger("reset");
            } else {
                updateInfobox(MessageType.ERROR, 'editServerManager (API): '+results.message);
            }
		});
	}
	else {
		updateInfobox(MessageType.ERROR, 'Please fill in all the required fields');
	}
}