function getSessionInfo(session_id, show_modal = false)
{
    const url = 'api/readGameSession_php';
    const data = {
        session_id: session_id
    };
    $.when(CallAPI(url, data)).done(function (results) {
        if (results.success) {
            updateSessionInfoList(results);
            if (show_modal) {
                $('#sessionInfo').modal('show');
            }
        } else {
            updateInfobox(MessageType.ERROR, results.message);
            console.log('getSessionInfo (API)', results.message);
        }
    });
}

function handleAllSessionInfoButtons(gamesession)
{
    handleUserAccessButton(gamesession);
    handleUpgradeButton(gamesession);
    handleDemoToggle(gamesession);
    handleExportPlansButton(gamesession);
    handlePlayPauseButton(gamesession);
    handleFastForwardButton(gamesession);
    handleArchiveButton(gamesession);
    handleSaveFullButton(gamesession);
    handleSaveLayersButton(gamesession);
    handleSessionRecreateButton(gamesession);
}

function deciderPlayPauseButtons(gamesession)
{
    if (gamesession.session_state === 'healthy') {
        if (gamesession.game_state === 'pause') {
            return 'play';
        } else if (gamesession.game_state === 'setup' || gamesession.game_state === 'play' || gamesession.game_state === 'fastforward') {
            return 'pause';
        } else {
            return 'disable';
        }
    } else {
        return 'hide';
    }
}

function handlePlayPauseButton(gamesession)
{
    var decider = deciderPlayPauseButtons(gamesession);
    if (decider === 'play') {
        $('#sessionInfoButtonStartPause').html('<i class="fa fa-play" title="Start"></i> Play');
        $('#sessionInfoButtonStartPause').attr('onclick', 'changeGameState(\'play\', '+gamesession.id+')');
        $('#sessionInfoButtonStartPause').removeAttr('disabled');
        $('#sessionInfoButtonStartPause').show();
    } else if (decider === 'pause') {
        $('#sessionInfoButtonStartPause').html('<i class="fa fa-pause" title="Pause"></i> Pause');
        $('#sessionInfoButtonStartPause').attr('onclick', 'changeGameState(\'pause\', '+gamesession.id+')');
        $('#sessionInfoButtonStartPause').removeAttr('disabled');
        $('#sessionInfoButtonStartPause').show();
    } else if (decider === 'disable') {
        $('#sessionInfoButtonStartPause').html('<i class="fa fa-ban" title="State change unavailable"></i> Play/pause unavailable');
        $('#sessionInfoButtonStartPause').attr('onclick', '');
        $('#sessionInfoButtonStartPause').attr('disabled','disabled');
        $('#sessionInfoButtonStartPause').show();
    } else if (decider === 'hide') {
        $('#sessionInfoButtonStartPause').hide();
    }
}

function handleFastForwardButton(gamesession)
{
    if (gamesession.session_state === 'healthy') {
        if (gamesession.game_state === 'fastforward') {
            $('#sessionInfoButtonFastForward').html('<i class="fa fa-fast-forward" title="Fast-forwarding"></i> Fast-forwarding');
            $('#sessionInfoButtonFastForward').attr('onclick', '');
            $('#sessionInfoButtonFastForward').attr('disabled', 'disabled');
            $('#sessionInfoButtonFastForward').show();
        } else if (gamesession.game_state === 'setup' || gamesession.game_state === 'end' || gamesession.game_state === 'simulation') {
            $('#sessionInfoButtonFastForward').hide();
        } else {
            $('#sessionInfoButtonFastForward').html('<i class="fa fa-fast-forward" title="Fast-forward"></i> Fast-forward');
            $('#sessionInfoButtonFastForward').attr('onclick', 'changeGameState(\'fastforward\', ' + gamesession.id + ')');
            $('#sessionInfoButtonFastForward').removeAttr('disabled');
            $('#sessionInfoButtonFastForward').show();
        }
    } else {
        $('#sessionInfoButtonFastForward').hide();
    }
}

function handleDemoToggle(gamesession)
{
    let demoToggle;
    let demoSessionDescription;
    if (gamesession.session_state === 'healthy') {
        if (gamesession.demo_session === 0) {
            demoSessionDescription = " Enable Demo Mode";
            demoToggle = 1;
        } else {
            demoSessionDescription = " Disable Demo Mode";
            demoToggle = 0;
        }
        $('#sessionInfoButtonDemoToggle').html('<i class="fa fa-bookmark" title="'+demoSessionDescription+'"></i>'+demoSessionDescription);
        $('#sessionInfoButtonDemoToggle').attr('onclick', 'toggleDemoSession('+demoToggle+', '+gamesession.id+')');
        $('#sessionInfoButtonDemoToggle').show();
    } else {
        $('#sessionInfoButtonDemoToggle').hide();
    }
}

function handleArchiveButton(gamesession)
{
    if (gamesession.session_state === 'failed' || gamesession.session_state === 'request' ||
        (gamesession.session_state === 'healthy' &&
            (gamesession.game_state === 'pause' || gamesession.game_state === 'setup' ||
             gamesession.game_state === 'end' || gamesession.game_state === 'simulation')
        )
    ) {
        $('#sessionInfoButtonArchiveDownload').html('<i class="fa fa-archive" title="Archive Session"></i> Archive Session');
        $('#sessionInfoButtonArchiveDownload').attr('onclick', 'archiveSession('+gamesession.id+')');
        $('#sessionInfoButtonArchiveDownload').show();
    } else {
        $('#sessionInfoButtonArchiveDownload').hide();
    }
}

function handleUpgradeButton(gamesession)
{
    if (gamesession.session_state === 'healthy' && gamesession.gameupgradable !== false) {
        $('#sessionInfoButtonUpgrade').html('<i class="fa fa-wrench" title="Upgrade Session"></i> Upgrade Session');
        $('#sessionInfoButtonUpgrade').attr('onclick', 'callUpgrade('+gamesession.id+')');
        $('#sessionInfoButtonUpgrade').show();
    } else {
        $('#sessionInfoButtonUpgrade').hide();
    }
}

function deciderSaveFullButton(gamesession)
{
    if (gamesession.session_state === 'healthy' && (gamesession.game_state === 'pause' || gamesession.game_state === 'setup' || gamesession.game_state === 'end')) {
        return 'show';
    } else if (gamesession.session_state === 'healthy') {
        return 'disable';
    } else {
        return 'hide';
    }
}

function handleSaveFullButton(gamesession)
{
    const decider = deciderSaveFullButton(gamesession);
    if (decider === 'show') {
        $('#sessionInfoButtonSaveFull').html('<i class="fa fa-save" title="Save Session"></i> Save Session as File');
        $('#sessionInfoButtonSaveFull').attr('onclick', 'saveSession('+gamesession.id+')');
        $('#sessionInfoButtonSaveFull').removeAttr('disabled');
        $('#sessionInfoButtonSaveFull').show();
    } else if (decider === 'disable') {
        $('#sessionInfoButtonSaveFull').html('<i class="fa fa-save" title="Save unavailable, make sure the simulations are not running."></i> Save Session as File');
        $('#sessionInfoButtonSaveFull').attr('onclick', '');
        $('#sessionInfoButtonSaveFull').attr('disabled','disabled');
        $('#sessionInfoButtonSaveFull').show();
    } else {
        $('#sessionInfoButtonSaveFull').hide();
    }
}

function handleSaveLayersButton(gamesession)
{
    if (gamesession.session_state === 'healthy' &&
        (gamesession.game_state === 'pause' || gamesession.game_state === 'setup' || gamesession.game_state === 'end')
    ) {
        $('#sessionInfoButtonSaveLayers').html('<i class="fa fa-save" title="Save All Layers"></i> Save All Layers');
        $('#sessionInfoButtonSaveLayers').attr('onclick', 'saveSession('+gamesession.id+', \'layers\')');
        $('#sessionInfoButtonSaveLayers').removeAttr('disabled');
        $('#sessionInfoButtonSaveLayers').show();
    } else if (gamesession.session_state == 'healthy') {
        $('#sessionInfoButtonSaveLayers').html('<i class="fa fa-save" title="Save unavailable, make sure the simulations are not running."></i> Save All Layers');
        $('#sessionInfoButtonSaveLayers').attr('onclick', '');
        $('#sessionInfoButtonSaveLayers').attr('disabled','disabled');
        $('#sessionInfoButtonSaveLayers').show();
    } else {
        $('#sessionInfoButtonSaveLayers').hide();
    }
}

function handleUserAccessButton(gamesession)
{
    if (gamesession.session_state === 'healthy' && gamesession.server_version !== "4.0-beta7") { // was introduced in beta8, all previous versions are designated beta7, even if older
        $('#sessionInfoButtonUserAccess').attr('onclick', 'showUserAccessManagement('+gamesession.id+')');
        $('#sessionInfoButtonUserAccess').html('<i class="fa fa-user" title="Set User Access"></i> Set User Access</button>');
        $('#sessionInfoButtonUserAccess').show();
    } else {
        $('#sessionInfoButtonUserAccess').hide();
    }
}

function handleExportPlansButton(gamesession)
{
    if (gamesession.session_state === 'healthy') {
        $('#sessionInfoButtonExportPlans').html('<i class="fa fa-file-code-o" title="Export with Current Plans"></i> Export with Current Plans');
        $('#sessionInfoButtonExportPlans').attr('onclick', 'downloadExportedPlansWithConfig('+gamesession.id+')');
        $('#sessionInfoButtonExportPlans').show();
    } else {
        $('#sessionInfoButtonExportPlans').hide();
    }
}

function handleSessionRecreateButton(gamesession)
{
    if (gamesession.session_state === 'request' || gamesession.session_state === 'failed' || gamesession.game_state === 'end' ||
        (gamesession.session_state === 'healthy' && (gamesession.game_state === 'pause' || gamesession.game_state === 'setup' || gamesession.game_state === 'simulation'))
    ) {
        $('#sessionInfoButtonRecreateSession').attr('onclick', 'RecreateSession('+gamesession.id+')');
        $('#sessionInfoButtonRecreateSession').html('<i class="fa fa-repeat" title="Recreate Session"></i> Recreate Session</button>');
        $('#sessionInfoButtonRecreateSession').show();
    } else {
        $('#sessionInfoButtonRecreateSession').hide();
    }
}

function updateSessionInfoList(data)
{
    $('#sessionModalCenterTitle').val(data.gamesession.name);
    $('#sessionModalCenterTitle').attr("onchange", "editSessionName("+data.gamesession.id+");");
    $('#sessionInfoID').html(data.gamesession.id);
    if (data.gamesession.game_visibility.toLowerCase() === 'public') {
        $('#sessionInfoVisibility').html('<i class="fa fa-globe" aria-hidden="true"></i> ' + data.gamesession.game_visibility);
    } else {
        $('#sessionInfoVisibility').html('<i class="fa fa-lock" aria-hidden="true"></i> ' + data.gamesession.game_visibility);
    }
    $('#sessionInfoGameState').html('<h5><span class="badge badge-warning"><i class="fa fa-info" aria-hidden="true"></i> ' + data.gamesession.game_state + '</span></h5>');
    if (data.gamesession.session_state.toLowerCase() === 'healthy') {
        $('#sessionInfoSessionState').html('<h5><span class="badge badge-success"><i class="fa fa-heartbeat" aria-hidden="true"></i> ' + data.gamesession.session_state + '</span></h5>');
    } else {
        $('#sessionInfoSessionState').html('<h5><span class="badge badge-success"><i class="fa fa-clock-o" aria-hidden="true"></i> ' + data.gamesession.session_state + '</span></h5>');
    }
    $('#sessionInfoCurrentMonth').html(data.gamesession_pretty.game_current_month);
    $('#sessionInfoEndMonth').html(data.gamesession_pretty.game_end_month);
    $('#sessionInfoGameCreationTime').html(data.gamesession_pretty.game_creation_time);
    $('#sessionInfoGameRunningTilTime').html(data.gamesession_pretty.game_running_til_time);
    $('#sessionInfoConfigFilename').html('<a class="hoverinfo" title="'+data.gameconfig.description+'">'+data.gameconfig.filename+'</a>');
    $('#sessionInfoConfigVersion').html('<a class="hoverinfo" title="'+data.gameconfig.version_message+'">Version '+data.gameconfig.version+'</a>');
    $('#sessionInfoWatchdogName').html(data.watchdog.name);
    $('#sessionInfoWatchdogAddress').html(data.watchdog.address);
    $('#sessionInfoActivePlayers').html(data.gamesession.players_past_hour);
    $('#sessionInfoGeoServer').html(data.geoserver.name);
    if (data.gamesession.log) {
        $('#sessionInfoLog').html(data.gamesession.log.join("<br />"));
    }
    
    handleAllSessionInfoButtons(data.gamesession);
}

function toggleSessionInfoLog()
{
    if ($('#sessionInfoLog').is(":hidden")) {
        $('#sessionInfoLog').show();
    } else {
        $('#sessionInfoLog').hide();
    }
}

function callUpgrade(session_id)
{
    var url = 'api/editGameSession_php';
    var data = {
        session_id: session_id,
        action: 'upgrade'
    }
    $.when(CallAPI(url, data)).done(function (results) {
        if (results.success) {
            updateInfobox(MessageType.SUCCESS, "Session upgrade was successful.");
            getSessionInfo(session_id);
        } else {
            updateInfobox(MessageType.ERROR, results.message);
            console.log('editGameSession (API)', results.message);
        }
    });
}

function editSessionName(session_id)
{
    const url = 'api/editGameSession_php';
    const data = {
        session_id: session_id,
        name: $('#sessionModalCenterTitle').val()
    };
    $.when(CallAPI(url, data)).done(function (results) {
        if (results.success) {
            updateInfobox(MessageType.SUCCESS, "Session name successfully altered.");
            getSessionInfo(session_id);
        } else {
            updateInfobox(MessageType.ERROR, results.message);
            console.log('editGameSession (API)', results.message);
        }
    });
}

function submitNewSession()
{
    if (isFormValid($('#formNewSession'))) {
        showToast(MessageType.INFO, 'Please wait...');
        var url = 'api/addGameSession_php';
        var data = {
            name: $('#newSessionName').val(),
            game_config_version_id: $('#newConfigVersion').val(),
            password_admin: $('#newAdminPassword').val(),
            password_player: $('#newPlayerPassword').val(),
            game_geoserver_id: $('#newGeoServer').val(),
            watchdog_server_id: $('#newWatchdog').val(),
            jwt: currentToken
        }
        $.when(CallAPI(url, data)).done(function (results) {
            if (results.success) {
                updateInfobox(MessageType.SUCCESS, "New session requested with the ID "+results.gamesession.id+". Please be patient while the process finalises.");
                ShowLogToast(results.gamesession.id);
            } else {
                updateInfobox(MessageType.ERROR, 'submitNewSession (API): '+results.message);
            }
        });

        $('#modalNewSession').modal('hide');
        $('#modalNewSession').find("form").trigger("reset");
    } else {
        alert("Please check the form. Make sure all required fields have been filled in.");
    }
}

function RecreateSession(sessionId)
{
    if (confirm('This will delete and recreate the session. All existing data will be lost. Are you sure?')) {
        showToast(MessageType.INFO, 'Please wait...');
        const url = 'api/editGameSession_php';
        const data = {
            jwt: currentToken,
            session_id: sessionId,
            action: 'recreate'
        };
        $.when(CallAPI(url, data)).done(function (results) {
            if (results.success) {
                updateInfobox(MessageType.SUCCESS, "Recreating session... please be patient.");
                ShowLogToast(results.gamesession.id);
            } else {
                updateInfobox(MessageType.ERROR, results.message);
                console.log('RecreateSession', results.message);
            }
        });
        $('#sessionInfo').modal('hide');
    }
}

function showUserAccessManagement(sessionId)
{
    $('#sessionUsers').find("form").trigger("reset");
    $('#provider_admin_external').empty();
    $('#users_admin').html('');
    $('#provider_region_external').empty();
    $('#users_region').html('');
    $('#provider_player_external').empty();
    $('#playerPasswordExtraFields').empty();
    $('#playerUserExtraFields').empty();

    const url = 'api/readGameSession_php';
    const data = {
        session_id: sessionId
    };
    $.when(CallAPI(url, data)).done(function (sessiondetails) {
        let countryList = "";
        $.each(sessiondetails['gamecountries'], function (count, country) {
            countryList += country["country_id"] + " ";
            $('#playerPasswordExtraFields').append(addPasswordFields("player", country));
        })
        countryList = countryList.trim();
        $('#adminProviders').append('<input type="hidden" id="countries" name="countries" value="'+countryList+'">');
        
        setServerAuthProviders(sessionId);
        
        $.each(sessiondetails['gamecountries'], function (count2, country) {
            $('#playerUserExtraFields').append(addUserFields("player", country));
        });

        setAllUserAccessFieldValues(sessiondetails);
        $('#sessionUsers').modal('show');
    });
}

function setAllUserAccessFieldValues(data)
{
    const password_admin = JSON.parse(data.gamesession.password_admin);
    const password_player = JSON.parse(data.gamesession.password_player);
    let adminDivToShow;
    if (password_admin.admin.provider === "local") {
        adminDivToShow = "#adminPasswordFields";
        $("input[name=provider_admin][value=local]").prop('checked', true);
        $("#password_admin").val(password_admin.admin.value);
    } else {
        adminDivToShow = "#adminUserFields";
        $("input[name=provider_admin][value=external]").prop('checked', true);
        $('#provider_admin_external').val(password_admin.admin.provider);
        $('#users_admin').html(wrapWords(password_admin.admin.value));
    }
    limitUserAccessView(adminDivToShow);

    let regionDivToShow;
    if (password_admin.region.provider === "local") {
        regionDivToShow = "#regionPasswordFields";
        $("input[name=provider_region][value=local]").prop('checked', true);
        $("#password_region").val(password_admin.region.value);
    } else {
        regionDivToShow = "#regionUserFields";
        $("input[name=provider_region][value=external]").prop('checked', true);
        $('#provider_region_external').val(password_admin.region.provider);
        $('#users_region').html(wrapWords(password_admin.region.value));
    }
    limitUserAccessView(regionDivToShow);

    let stored;
    let storage;
    let equalValues;
    let playerDivToShow;
    if (password_player.provider === "local") {
        playerDivToShow = "#playerPasswordFields";
        $("input[name=provider_player][value=local]").prop('checked', true);
        stored = false;
        storage = '';
        equalValues = true;
        $('input[name^="password_player"]').each(function () {
            let varname = $(this).attr('name');
            let temp = varname.replace("password_player[", "");
            let country_id = temp.replace("]", "");
            let value = password_player.value[parseInt(country_id)];
            if (value) {
                $(this).val(value);
                if (stored) {
                    equalValues = storage === value && equalValues;
                }
                storage = value;
                stored = true;
            }
        });
        if (equalValues) {
            $('#password_playerall').val(storage);
            $('input[name^="password_player"]').each(function () {
                if ($(this).attr('name') !== "password_playerall") {
                    $(this).val('');
                }
            });
            toggleFields();
        }
    } else {
        playerDivToShow = "#playerUserFields";
        $("input[name=provider_player][value=external]").prop('checked', true);
        $('#provider_player_external').val(password_player.provider);
        stored = false;
        storage = '';
        equalValues = true;
        $.each(password_player.value, function (team, users) {
            $('#users_player\\[' + team + '\\]').html(wrapWords(users));
            if (stored) {
                equalValues = storage === users && equalValues;
            }
            storage = users;
            stored = true;
        });
        if (equalValues) {
            $('#users_playerall').html(wrapWords(storage));
            $.each(password_player.value, function (team, users) {
                $('#users_player\\[' + team + '\\]').html('');
            });
            toggleDivs();
        }
    }
    limitUserAccessView(playerDivToShow);
}

function limitUserAccessView(divToShow)
{
    let divToHide1;
    if (~divToShow.indexOf("PasswordFields")) {
        divToHide1 = divToShow.replace("PasswordFields", "UserFields");
    } else {
        divToHide1 = divToShow.replace("UserFields", "PasswordFields");
    }
    $(divToShow).show();
    $(divToHide1).hide();
}

function addAuthProvider(provider)
{
    return '<option value="'+provider['id']+'">'+provider['name']+'</option>';
}

function toggleFields()
{
    let fieldId = '#password_playerall';
    let varToCheck;
    if ($(fieldId).val().length > 0) {
        for (let i = 0; i < 30; i++) {
            varToCheck = fieldId.replace("all", "") + '\\[' + i + '\\]';
            if ($(varToCheck).length) {
                $(varToCheck).prop("disabled", true);
            }
        }
    } else {
        for (i = 0; i < 30; i++) {
            varToCheck = fieldId.replace("all", "") + '\\[' + i + '\\]';
            if ($(varToCheck).length) {
                $(varToCheck).prop("disabled", false);
            }
        }
    }
}

function toggleDivs()
{
    if ($('#users_playerall').text().trim().length > 0) {
        for (i = 0; i < 30; i++) {
            $('#users_player\\['+i+'\\]').css("background-color", "#e9ecef");
            $('#users_player\\['+i+'\\]').attr("contenteditable","false");
        }
    } else {
        for (i = 0; i < 30; i++) {
            $('#users_player\\['+i+'\\]').css("background-color", "#ffffff");
            $('#users_player\\['+i+'\\]').attr("contenteditable","true");
        }
    }
}

function addPasswordFields(type, country)
{
    let id = 'password_' + type + '[' + country['country_id'] + ']';
    let html = '<div class="input-group mb-3">';
    html += '	<input type="text" class="form-control" placeholder="Leave empty for immediate access." id="'+id+'" name="'+id+'">';
    html += '	<div class="input-group-append">';
    html += '		<span class="input-group-text" style="color: black; font-weight: bolder; opacity: 0.5; background-color: '+country['country_colour']+';">'+country['country_name']+'</span>';
    html += '	</div>';
    html += '</div>';
    return html;
}

function addUserFields(type, country)
{
    let id = 'users_' + type + '[' + country['country_id'] + ']';
    let jid = '#' + id;
    let html = '<div class="input-group mb-3">';
    html += '	<div contenteditable="true" class="form-control" style="height: auto !important;" id="'+id+'"></div>';
    html += '	<div class="input-group-append">';
    html += '		<span class="input-group-text" style="color: black; font-weight: bolder; opacity: 0.5; background-color: '+country['country_colour']+';">'+country['country_name']+'</span>';
    html += '	</div>';
    html += '	<div class="input-group-append">';
    html += '		<button class="btn btn-outline-secondary" type="button" id="button-find-'+id+'" onclick="findUsersAtProvider(\''+jid+'\', $(\'#provider_player_external\').val());">Find</button>';
    html += '	</div>';
    html += '</div>';
    return html;
}

function saveUserAccess()
{
    const url = 'api/editGameSession_php';
    const data = {
        session_id: $('#sessionInfoID').html(),
        password_admin: JSON.stringify(getPasswordAdmin()),
        password_player: JSON.stringify(getPasswordPlayer()),
        action: 'setUserAccess'
    };
    $.when(CallAPI(url, data)).done(function (results) {
        if (results.success) {
            updateInfobox(MessageType.SUCCESS, "User access settings successfully saved.");
        } else {
            updateInfobox(MessageType.ERROR, results.message);
            console.log('editGameSession (API)', results.message);
        }
    });
    $('#sessionUsers').modal('hide');
}

function getPasswordAdmin()
{
    let provider_admin = $('input[name=provider_admin]:checked').val();
    let provider_region = $('input[name=provider_region]:checked').val();
    let value_admin;
    if (provider_admin === "external") {
        provider_admin = $('#provider_admin_external').val();
        value_admin = unWrapWords($('#users_admin').html());
    } else {
        value_admin = $('#password_admin').val().trim();
    }
    let value_region;
    if (provider_region === "external") {
        provider_region = $('#provider_region_external').val();
        value_region = unWrapWords($('#users_region').html());
    } else {
        value_region = $('#password_region').val().trim();
    }

    return {
        admin: {
            provider: provider_admin,
            value: value_admin
        },
        region: {
            provider: provider_region,
            value: value_region
        }
    };
}

function getPasswordPlayer()
{
    let countries = $('#countries').val().split(" ");
    let value_player = {};
    let provider_player = $('input[name=provider_player]:checked').val();
    if (provider_player === "external") {
        provider_player = $('#provider_player_external').val();
    }
    if (provider_player === 'local') {
        if ($('#password_playerall').val()) {
            $.each(countries, function (count, country) {
                value_player[country] = $('#password_playerall').val().trim();
            });
        } else {
            $.each(countries, function (count, country) {
                value_player[country] = $('#password_player\\['+country+'\\]').val().trim();
            });
        }
    } else {
        if ($('#users_playerall').text()) {
            $.each(countries, function (count, country) {
                value_player[country] = unWrapWords($('#users_playerall').html());
            });
        } else {
            $.each(countries, function (count, country) {
                value_player[country] = unWrapWords($('#users_player\\['+country+'\\]').html());
            });
        }
    }

    return {
        provider: provider_player,
        value: value_player
    };
}

function wrapWords(str, tmpl)
{
    if (!str) {
        return '';
    }
    let strArray;
    let returnStr = '';
    strArray = str.split('|');
    strArray.forEach(function (value) {
        returnStr += "<button type=\"button\" class=\"btn btn-primary\" style=\"margin: 5px;\" onClick=\"$(this).remove(); toggleDivs();\">" + value + "<i style=\"padding-left: 7px;\" class=\"fa fa-times-circle\"></i></button>";
    })
    return "<div> &nbsp; </div>" + returnStr;
}

function unWrapWords(str, tmpl1, tmpl2)
{
    let returnArr = [];
    const regexp1 = /<button.*?>([^<>\/]+)(?:<.*?>)?<\/button>/ig;
    const regexp2 = /<div>([^<>\/]+)<\/div>/gi;

    str = str.replaceAll('&nbsp;', '');
    str = str.replaceAll('<br>', '');
    for (const match of str.matchAll(regexp1)) {
        if (match[1].replace(/\s/g, '').length) {
            returnArr.push(match[1].trim());
        }
    }
    for (const match of str.matchAll(regexp2)) {
        if (match[1].replace(/\s/g, '').length) {
            returnArr.push(match[1].trim());
        }
    }
    if (!returnArr.length) {
        return str; // presume text was not wrapped by <button> or <div>, and not multiline, so return as is
    }
    return returnArr.join('|');
}

function setServerAuthProviders(sessionId)
{
    const url = 'api/User/getProviders';
    $.when(CallServerAPI(url, {}, sessionId, '0')).done(function (results) {
        if (results.success && results.payload) {
            $.each(results.payload, function (count, provider) {
                var newprovideroption = addAuthProvider(provider);
                $('#provider_admin_external').append(newprovideroption);
                $('#provider_region_external').append(newprovideroption);
                $('#provider_player_external').append(newprovideroption);
            });
        }
    });
}

function findUsersAtProvider(div, provider)
{
    div = div.replace("[", "\\[");
    div = div.replace("]", "\\]");
    const userTextInput = unWrapWords($(div).html());
    const url = 'api/readGameSession_php';
    const session_id = $('#sessionInfoID').html();
    const data = {
        session_id: session_id
    };
    $.when(CallAPI(url, data)).done(function (results) {
        if (results.success) {
            const url2 = "api/User/checkExists";
            const data2 = {
                provider: provider,
                users: userTextInput
            };
            $.when(CallServerAPI(url2, data2, session_id, results.gamesession.api_access_token)).done(function (results2) {
                if (results2.success) {
                    if (results2.payload.notfound) {
                        showToast(MessageType.ERROR, 'Could not find these users: '+results2.payload.notfound.replaceAll("|", "<br/>"));
                    }
                    $(div).html(wrapWords(results2.payload.found));
                } else {
                    console.log('findusersatprovider', results2.payload.message);
                }
            });
        } else {
            console.log('findusersatprovider', results.message);
        }
    });
}

function GeoServerListToOptions()
{
    $('#newGeoServer').empty();
    const url = 'api/browseGeoServer_php';
    const data = {
        jwt: currentToken
    };
    $.when(CallAPI(url, data)).done(function (results) {
        $.each(results.geoserverslist, function (row, geoserver) {
            if (geoserver.available === 1) {
                $('<option value="'+geoserver.id+'" title="'+geoserver.address+'">'+geoserver.name+'</option>').appendTo('#newGeoServer');
            }
        })
    });
}

function changeGameState(newState, sessionId)
{
    $('#sessionInfoButtonStartPause').prop('disabled', 1);
    showToast(MessageType.INFO, 'Please wait...');
    const url = 'api/editGameSession_php';
    const data = {
        session_id: sessionId,
        game_state: newState,
        action: 'changeGameState'
    };
    $.when(CallAPI(url, data)).done(function (results) {
        if (results.success) {
            updateInfobox(MessageType.SUCCESS, "State successfully set to "+results.gamesession.game_state);
            getSessionInfo(sessionId);
        } else {
            updateInfobox(MessageType.ERROR, results.message);
            console.log('startSession' , results.message);
        }
    });
}

function toggleDemoSession(toggle, sessionId)
{
    let text;
    if (toggle === 1) {
        text = 'By enabling demo mode, the simulations will start, they will continue until the end (even if you select pause along the way), and subsequently the session will be recreated. After recreation, the whole process continues until you disable demo mode again. Are you sure you want to do that?';
    } else {
        text = 'By disabling demo mode, the simulation will remain running (until you change the simulation state again), but it will not be recreated automatically anymore. Are you sure you want to disable demo mode?';
    }
    if (confirm(text)) {
        const url = 'api/editGameSession_php';
        const data = {
            session_id: sessionId,
            demo_session: toggle,
            action: "demoCheck"
        };
        $.when(CallAPI(url, data)).done(function (results) {
            if (results.success) {
                updateInfobox(MessageType.SUCCESS, "Successfully altered demo status.");
                getSessionInfo(sessionId);
            } else {
                updateInfobox(MessageType.ERROR, results.message);
                console.log('toggleDemoSession', results.message)
            }
        });
    }
}

function downloadArchive(sessionId)
{
    window.location = "api/downloader_php?id="+sessionId+"&request=GameSession/getArchive";
}

function downloadExportedPlansWithConfig(sessionId)
{
    window.location = "api/downloader_php?id="+sessionId+"&request=GameSession/getConfigWithPlans";
}

function archiveSession(sessionId)
{
    if (confirm('This will permanently archive the session. It will subsequently no longer be usable by end users. If you want a backup, then *first* save this session, *before* continuing! Are you sure you want to archive this session?')) {
        const url = 'api/deleteGameSession_php';
        const data = {
            session_id: sessionId
        };
        $.when(CallAPI(url, data)).done(function (results) {
            if (results.success) {
                updateInfobox(MessageType.SUCCESS, "Session being archived. Archive file will be available shortly.");
                $('#sessionInfo').modal('hide');
            } else {
                updateInfobox(MessageType.ERROR, results.message);
                console.log('archiveSession' , results.message)
            }
        });
    }
}



function updateSessionsTable(visibility)
{
    $("#buttonRefreshSessionsListIcon").addClass("fa-spin");
    const url = 'api/browseGameSession_php';
    const data = {
        session_state: visibility
    };
    $.when(CallAPI(url, data)).done(function (results) {
        $('#buttonRefreshSessionsListIcon').removeClass('fa-spin');
        sessionsListToTable(results.sessionslist);
    });
}

function sessionsListToTable(sessionsList)
{
    $('#sessionsListtbody').empty();
    if (sessionsList === '') {
        $('<tr><td colspan="8">No sessions yet. Create your first one through the New Session button above.</td></tr>').appendTo('#sessionsListtbody') }
    $.each(sessionsList, function (i, v) {
        v.game_start_year = undefined;
        let visibility = '';
        v.show_state = v.game_state;
        if (v.session_state !== 'healthy') {
            visibility = ' hidden_icon';
            v.show_state = v.session_state;
        }

        const deciderPlayPause = deciderPlayPauseButtons(v);
        const deciderSave = deciderSaveFullButton(v);

        let running_icon;
        if (deciderPlayPause === 'play') {
            running_icon = '<button class="btn btn-secondary btn-sm" onClick="changeGameState(\'play\', ' + v.id + ')"><i class="fa fa-play" title="Start Simulation" ></i></button>';
        } else if (deciderPlayPause === 'pause') {
            running_icon = '<button class="btn btn-secondary btn-sm" onClick="changeGameState(\'pause\', ' + v.id + ')"><i class="fa fa-pause" title="Pause Simulation" ></i></button>';
        } else if (deciderPlayPause === 'disable' || deciderPlayPause === 'hide') {
            running_icon = '<button class="btn btn-secondary btn-sm" disabled><i class="fa fa-ban" title="Start/pause unavailable"></i></button>';
        }

        let save_icon;
        if (deciderSave === 'show') {
            save_icon = '<button class="btn btn-secondary btn-sm" onClick="saveSession(' + v.id + ')"><i class="fa fa-save" title="Save Session"></i></button>';
        } else {
            save_icon = '<button class="btn btn-secondary btn-sm" disabled><i class="fa fa-save" title="Save Session unavailable"></i></button>';
        }

        let info_icon = '<button class="btn btn-secondary btn-sm" onClick="getSessionInfo(' + v.id + ', true);"><i class="fa fa-info-circle" title="Info" ></i></button>';
        if (v.game_start_year === '0') {
            v.current_month_formatted = ''; }
        if (v.game_start_year === '0') {
            v.end_month_formatted = ''; }

        let tableHTML = '<tr><td>' + v.id + '</td><td>' + v.name + '</td>';
        tableHTML += '<td>'+v.config_file_name+'</td>';
        tableHTML +=
            '<td>'+v.players_past_hour+'</td>'+
            '<td class="state_'+v.show_state+'">'+ShowState(v)+'</td>'+
            '<td>'+v.current_month_formatted+'</td>'+
            '<td>'+v.end_month_formatted+'</td>'+
            '<td class="text-center">'+running_icon+' '+save_icon+' '+info_icon+'</i></td>'+
        '</tr>';
        $(tableHTML).appendTo('#sessionsListtbody')
    })
}

function ShowState(v)
{
    if (v.show_state === "request") {
        return v.show_state+' <i class="fa fa-spinner fa-pulse" title="Your session is being created."></i>';
    } else if (v.show_state === "setup") {
        return v.show_state+' <i class="fa fa-check" title="This session is ready."></i>';
    } else {
        return v.show_state;
    }
}