function GetUserAccessList()
{
    $.when(CallAuthoriser('GET', '/api/servers/' + serverUUID +'/server_users')).done(function (results) {
        $('#UserAccessBody').empty();
        $.each(results, function (row, serverUser) {
            setUserAccessList(serverUser);
        });
    });
}

function setUserAccessList(serverUser)
{
    var html = '<tr>';
    html += '<td>' + serverUser.user.username + '<br/>(' + serverUser.user.email + ')</td><td>' + serverUser.isAdmin + '</td>';
    html += '<td>';
    html += '<button type="button" class="btn btn-primary btn-sm" onclick="deleteUserAccess(\'' + serverUser['@id'] + '\');">';
    html += '<i class="fa fa-trash"></i></button>';
    html += '</td>';
    html += '</tr>';
    $('#UserAccessBody').append(html);
}

function submitNewUserAccess()
{
    let endpoint;
    let username = $('#newUserAccess').val();
    let isAdmin = $("#newUserAccessAdmin").is(':checked');
    if (isEmail(username)) {
        endpoint = '/api/users?email=';
    } else {
        endpoint = '/api/users?username=';
    }
    $.when(CallAuthoriser('GET', endpoint + encodeURI(username)))
        .done(function (results) {
            if (results[0]) {
                var data = {
                    server: '/api/servers/' + serverUUID,
                    user: results[0]['@id'],
                    isAdmin: isAdmin
                }
                submitNewUserAccessFinal(data);
            } else {
                handleAPIError(results, 'No user found with that e-mail address.');
            }
        })
        .fail(function (results) {
            handleAPIError(results);
        });
}

function submitNewUserAccessFinal(data)
{
    $.when(CallAuthoriser('POST', '/api/server_users', data))
        .done(function (results) {
            updateInfobox(MessageType.SUCCESS, 'User ' + results.user.username + ' was successfully added.');
            setUserAccessList(results);
            $('#newUserAccess').val('');
            $('#newUserAccessAdmin').prop('checked', false);
        })
        .fail(function (results) {
            handleAPIError(results);
        });
}

function deleteUserAccess(endpoint)
{
    $.when(CallAuthoriser('DELETE', endpoint))
        .done(function (results) {
            updateInfobox(MessageType.SUCCESS, 'The user was successfully deleted.');
            GetUserAccessList();
        })
        .fail(function (results) {
            handleAPIError(results);
        });
}

function submitNewGeoServer()
{
    var name = $('#newGeoServerName').val();
    var address = $('#newGeoServerAddress').val();
    var username = $('#newGeoServerUsername').val();
    var password = $('#newGeoServerPassword').val();

    if ([name, address, username, password].every(Boolean)) {
        var url = 'api/addGeoServer_php';
        var data =  {
            name: name,
            address: address,
            username: username,
            password: password
        }
        $.when(CallAPI(url, data)).done(function (results) {
            if (results.success) {
                updateInfobox(MessageType.SUCCESS, "GeoServer successfully added.");
                $('#modalNewGeoServers').modal('hide');
                $('#modalNewGeoServers').find("form").trigger("reset");
            } else {
                updateInfobox(MessageType.ERROR, 'submitNewGeoServer (API): '+results.message);
            }
        });
    } else {
        updateInfobox(MessageType.ERROR, 'Please fill in all the required fields');
    }
}

function GeoServerList()
{
    var url = "api/browseGeoServer_php";
    $.when(CallAPI(url)).done(function (results) {
        $('#GeoServerBody').empty();
        $.each(results.geoserverslist, function (row, geoserver) {
            setGeoServerList(geoserver);
        });
    });
}

function setGeoServerList(geoserver)
{
    var html = '<tr>';
    html += '<td>' + geoserver.name + '</td><td>' + geoserver.address + '</td>';
    html += '<td>';
    if (geoserver.id != 1) {
        html += '<button type="button" class="btn btn-primary btn-sm" onclick="GeoServerEdit('+geoserver.id+');"><i class="fa fa-pencil"></i></button>';
    }
    if (geoserver.available == 1) {
        html += '<button type="button" id="GeoServerAvailability'+geoserver.id+'" class="btn btn-warning btn-sm" title="Available. Click to make unavailable." onclick="GeoServerToggleAvailability('+geoserver.id+');"><i class="fa fa-eye"></i></button>';
    } else {
        html += '<button type="button" id="GeoServerAvailability'+geoserver.id+'" class="btn btn-warning btn-sm" title="Unavailable. Click to make available." onclick="GeoServerToggleAvailability('+geoserver.id+');"><i class="fa fa-eye-slash"></i></button>';
    }
    html += '</td>';
    html += '</tr>';
    $('#GeoServerBody').append(html);
}

function GeoServerToggleAvailability(geoserver_id)
{
    var url = "api/deleteGeoServer_php";
    var data = {
        geoserver_id: geoserver_id
    }
    $.when(CallAPI(url, data)).done(function (results) {
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

function GeoServerEdit(geoserver_id)
{
    var url = "api/readGeoServer_php";
    var data = {
        geoserver_id: geoserver_id
    }
    $.when(CallAPI(url, data)).done(function (results) {
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

function submitGeoServerEdit()
{
    var url = "api/editGeoServer_php";
    var data = {
        geoserver_id: $('#editGeoserverID').val(),
        name: $('#newGeoServerName').val(),
        address: $('#newGeoServerAddress').val()
    }
    if ($('#newGeoServerUsername').val()) {
        data['username'] = $('#newGeoServerUsername').val();
    }
    if ($('#newGeoServerPassword').val()) {
        data['password'] = $('#newGeoServerPassword').val();
    }
    $.when(CallAPI(url, data)).done(function (results) {
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

function watchdogListToOptions()
{
    $('#newWatchdog').empty();
    $('#newWatchdogLoadSave').empty();
    var url = "api/browseWatchdog_php";
    $.when(CallAPI(url)).done(function (results) {
        $.each(results.watchdogslist, function (i, watchdog) {
            if (watchdog.available == 1) {
                $('<option value="'+watchdog.id+'" title="'+watchdog.address+'">'+watchdog.name+'</option>').appendTo('#newWatchdog');
                $('<option value="'+watchdog.id+'" title="'+watchdog.address+'">'+watchdog.name+'</option>').appendTo('#newWatchdogLoadSave');
            }
        })
    });
}

function WatchdogServerList()
{
    var url = "api/browseWatchdog_php";
    $.when(CallAPI(url)).done(function (results) {
        $('#watchdogServerBody').empty();
        $.each(results.watchdogslist, function (i, watchdog) {
            setWatchdogServerList(watchdog);
        });
    });
}

function setWatchdogServerList(watchdog)
{
    var html = '<tr>';
    html += '<td>' + watchdog.name + '</td><td>' + watchdog.address + '</td>';
    html += '<td>';
    if (watchdog.id != 1) {
        html += '<button type="button" class="btn btn-primary btn-sm" onclick="WatchdogEdit('+watchdog.id+');"><i class="fa fa-pencil"></i></button>';
    }
    if (watchdog.available == 1) {
        html += '<button type="button" id="WatchdogAvailability'+watchdog.id+'" class="btn btn-warning btn-sm" title="Available. Click to make unavailable." onclick="WatchdogToggleAvailability('+watchdog.id+');"><i class="fa fa-eye"></i></button>';
    } else {
        html += '<button type="button" id="WatchdogAvailability'+watchdog.id+'" class="btn btn-warning btn-sm" title="Unavailable. Click to make available." onclick="WatchdogToggleAvailability('+watchdog.id+');"><i class="fa fa-eye-slash"></i></button>';
    }
    html += '</td>';
    html += '</tr>';
    $('#watchdogServerBody').append(html);
}

function submitNewWatchdogServer()
{
    var name = $('#newWatchdogServerName').val();
    var address = $('#newWatchdogServerAddress').val();

    if ([name, address].every(Boolean)) {
        var url = "api/addWatchdog_php";
        var data = {
            name: name,
            address: address,
        }
        $.when(CallAPI(url, data)).done(function (results) {
            if (results.success) {
                updateInfobox(MessageType.SUCCESS, "Watchdog successfully added.");
                $('#modalNewSimServers').modal('hide');
                $('#modalNewSimServers').find("form").trigger("reset");
            } else {
                updateInfobox(MessageType.ERROR, 'submitNewWatchdogServer (API): '+results.message);
            }
        });
    } else {
        updateInfobox(MessageType.ERROR, 'Please fill in all the required fields');
    }
}

function WatchdogEdit(watchdog_id)
{
    var url = "api/readWatchdog_php";
    var data = {
        watchdog_id: watchdog_id
    }
    $.when(CallAPI(url, data)).done(function (results) {
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

function submitWatchdogEdit()
{
    var url = "api/editWatchdog_php";
    var data = {
        watchdog_id: $('#editWatchdogID').val(),
        name: $('#newWatchdogServerName').val(),
        address: $('#newWatchdogServerAddress').val()
    }
    $.when(CallAPI(url, data)).done(function (results) {
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

function WatchdogToggleAvailability(watchdog_id)
{
    var url = "api/deleteWatchdog_php";
    var data = {
        watchdog_id: watchdog_id
    }
    $.when(CallAPI(url, data)).done(function (results) {
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

function GetServerAddr()
{
    var url = "api/readServerManager_php";
    $.when(CallAPI(url)).done(function (results) {
        $('#ServerName').val(results.servermanager.serverName);
        $('#ServerDescription').val(results.servermanager.serverDescription);
        $('#ServerAddress').val(results.servermanager.serverAddress);
    });
}

function editServerManager()
{
    var name = $('#ServerName').val();
    var address = $('#ServerAddress').val();
    var description = $('#ServerDescription').val();
    if ([name, address, description].every(Boolean)) {
        var url = "api/editServerManager_php";
        var data = {
            serverName: name,
            serverAddress: address,
            serverDescription: description,
            jwt: currentToken
        }
        $.when(CallAPI(url, data)).done(function (results) {
            if (results.success) {
                updateInfobox(MessageType.SUCCESS, "Server settings successfully updated.");
                $('#modalServerDetails').modal('hide');
                $('#modalServerDetails').find("form").trigger("reset");
            } else {
                updateInfobox(MessageType.ERROR, 'editServerManager (API): '+results.message);
            }
        });
    } else {
        updateInfobox(MessageType.ERROR, 'Please fill in all the required fields');
    }
}