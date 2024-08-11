function CallAuthoriser(method, endpoint, data = {})
{
    let url = $('#auth2BaseURL').attr('data-url') + endpoint;
    return  $.ajax({
        url: url,
        type: method,
        beforeSend: function (xhr) {
            xhr.setRequestHeader('Authorization', 'Bearer ' + currentToken);
        },
        headers: { 'Accept': "application/ld+json" },
        contentType: 'application/json',
        data: JSON.stringify(data),
        dataType: 'json',
        dataFilter : function (returnData) {
            if (returnData) {
                var json = JSON.parse(returnData);
                if (json["hydra:member"]) {
                    return JSON.stringify(json["hydra:member"]);
                }
                return JSON.stringify(json);
            }
            return true;
        }
    });
}

function CallAPI(url, data = {})
{
    return  $.post(
        url,
        data,
        function (result) {
            //console.log(result); // re-activate for debugging
        },
        'json'
    );
}

function CallAPIWithFileUpload(url, data)
{
    return $.ajax({
        url: url,
        method: "POST",
        data: data,
        contentType: false,
        processData: false,
        dataType: 'json',
        success: function (result) {
            //console.log(result); // re-activate for debugging
        }
    });
}

function CallServerAPI(url, data = {}, session_id = 0, token = '')
{
    if (session_id > 0 && token) {
        url = "../" + session_id + "/" + url;
        return  $.ajax({
            url: url,
            method: "POST",
            headers: { 'Msp-Api-Token': token },
            data: data,
            dataType: "json",
            success: function (result) {
                //console.log(result); // re-activate for debugging
            }
        });
    } else {
        url = "../" + url;
        return  $.post(
            url,
            data,
            function (result) {
                //console.log(result); // re-activate for debugging
            },
            'json'
        );
    }
}

function updatecurrentToken()
{
    var url = "api/updateToken_php";
    var data = {
        token: currentToken
    }
    $.when(CallAPI(url, data)).done(function (results) {
        if (results.success) {
            resetToken(results.jwt);
        }
    });
}

function resetToken(newtoken)
{
    if (newtoken && newtoken.length > 0) {
        currentToken = newtoken;
    }
}

function updateInfobox(type, message)
{
    showToast(type, message);
    // updating all non-modal lists that might have changed, except the sessions and saves lists, because those update every X secs anyway (in turn because the server also updates them in the background)
    updateConfigVersionsTable($('input[name=radioOptionConfig]:checked').val());
}

const MessageType = {
    ERROR: 0,
    INFO: 1,
    SUCCESS: 2,
}

function showToast(type, message)
{
    switch (type) {
        case MessageType.ERROR:
            shortMessage = message
            if (message.length > 150) {
                shortMessage =  'An error occurred. '
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

function handleAPIError(results, customError = 'An unknown error occurred.')
{
    if (results.responseJSON && results.responseJSON['hydra:description']) {
        updateInfobox(MessageType.ERROR, results.responseJSON['hydra:description']);
    } else {
        updateInfobox(MessageType.ERROR, customError);
    }
}

function copyToClipboard(text)
{
    window.prompt("Copy to clipboard: Ctrl+C, Enter", text);
}

var log_concise_old, regularLogToastAutoCloseCheck, regularLogToastBodyUpdate,
    logUpdateIntervalSec = 3, logCleanupIntervalSec = 120, logElapsedSec = 0;
function ShowLogToast(session_id)
{
    // first cancel any previous logtoast updates that might still be running
    clearInterval(regularLogToastBodyUpdate);

    $('#LogToastHeader').html("Session Activity Log ("+session_id+")");
    log_concise_old = '';

    UpdateLogToastContents(session_id);
    regularLogToastBodyUpdate = setInterval(function () {
        UpdateLogToastContents(session_id);
    }, logUpdateIntervalSec * 1000);

    $('#LogToast').prop('data-autoclose', false);
    $('#LogToast').toast('show');
}

function UpdateLogToastContents(session_id)
{
    var url = "api/readGameSession_php";
    var data = {
        session_id: session_id
    }
    logElapsedSec += logUpdateIntervalSec;
    $.when(CallAPI(url, data)).done(function (results) {
        log_array = [];
        if (null != results.gamesession.log) {
            log_array = results.gamesession.log.slice(-5);
        }
        log_array.forEach(LogToastContentItemCleanUp);
        var log_concise_new = log_array.join("");
        if (logElapsedSec > logCleanupIntervalSec) {
            if (log_concise_new == log_concise_old) {
                if ($('#LogToast').prop('data-autoclose')) {
                    // so only if the old log was deemed unchanged *twice*, should the window be closed
                    clearInterval(regularLogToastBodyUpdate);
                    $('#LogToast').toast('hide');
                }
                $('#LogToast').prop('data-autoclose', true);
            } else {
                logElapsedSec = 0; // reset
            }
        }
        log_concise_old = log_concise_new;
        $("#LogToastBody").html(log_concise_new);
    });
}

function LogToastContentItemCleanUp(item, index, arr)
{
    item = item.replace(/\[[0-9\-\s:]+\]/, "");
    item = item.replace("[ INFO ]", '<div style="color: green;">');
    item = item.replace("[ ERROR ]", '<div style="color: red;">');
    item = item.replace("[ WARN ]", '<div style="color: navy;">');
    item = item.replace("[ DEBUG ]", '<div style="color: grey;">');

    item = item.replace("INFO:", '<div style="color: green;">');
    item = item.replace("NOTICE:", '<div style="color: green;">');
    item = item.replace("ERROR:", '<div style="color: red;">');
    item = item.replace("WARNING:", '<div style="color: navy;">');
    item = item.replace("DEBUG:", '<div style="color: grey;">');
    item = item.replace(/\[[0-9\-\s:+.T]+\]/, "");
    item = item.replace("game_session.", "");
    item = item.replace(/\{["\w:,]+\} \[\]/, "");
    item += "</div>";
    arr[index] = item;
}

function isFormValid(currentForm)
{
    var fail = false;
    var fail_log = '';
    var name;
    $(currentForm).find('select, textarea, input').each(function () {

        if ($(this).prop('required') && !$(this).val() ) {
            fail = true;
            name = $(this).attr('name');
            fail_log += name + " is required \n";
            $(this).addClass('is-invalid');
        } else {
            $(this).removeClass('is-invalid');
            $(this).removeClass('is-valid');
        }
    });

    //submit if fail never got set to true
    if (fail) {
        console.log(fail_log);
        return false
    } else {
        return true;
    }
}

function isEmail(email)
{
    var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
    return regex.test(email);
}