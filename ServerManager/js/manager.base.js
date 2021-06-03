

function CallAPI(url, data = {}) {
	return  $.post(
		url, 
		data,
		function(result){
            //console.log(result); // re-activate for debugging
        },
		'json'
	);
}

function CallAPIWithFileUpload(url, data) {
	return $.ajax({
		url: url,
		method: "POST",
		data: data,
		contentType: false,
		processData: false,
		dataType: 'json',
		success: function(result) {
			//console.log(result); // re-activate for debugging
		}
	});
}

function CallServerAPI(url, data = {}, session_id = 0, token = '') {
	if (session_id > 0 && token) 
	{
		url = "../" + session_id + "/" + url;
		return  $.ajax({
			url: url,
			method: "POST",
			headers: { MSPAPIToken: token },
			data: data,
			dataType: "json",
			success: function(result){
				//console.log(result); // re-activate for debugging
			}
		});
	}
	else {
		url = "../" + url;
		return  $.post(
			url, 
			data,
			function(result){
				//console.log(result); // re-activate for debugging
			},
			'json'
		);
	}
}

function updatecurrentToken() {
	var url = "api/updateToken.php";
	var data = {
		token: currentToken
	}
	$.when(CallAPI(url, data)).done(function(results) {
        if (results.success) {
			resetToken(results.jwt);
		}
	});
}

function resetToken(newtoken) {
	if (newtoken && newtoken.length > 0) {
		currentToken = newtoken;
	} 
}

function updateInfobox(type, message) {
	showToast(type, message);	
	// updating all non-modal lists that might have changed, except the sessions and saves lists, because those update every X secs anyway (in turn because the server also updates them in the background)
	updateConfigVersionsTable($('input[name=radioOptionConfig]:checked').val());
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

function copyToClipboard(text) {
	window.prompt("Copy to clipboard: Ctrl+C, Enter", text);
}

function ShowLogToast(session_id) {
	// first cancel any previous logtoast updates that might still be running
	if (typeof regularLogToastBodyUpdate !== 'undefined') clearTimeout(regularLogToastBodyUpdate); 
	if (typeof regularLogToastAutoCloseCheck !== 'undefined') clearTimeout(regularLogToastAutoCloseCheck); 

	$('#LogToastHeader').html("Session Activity Log ("+session_id+")");
	log_concise_old = '';
	UpdateLogToastContents(session_id);
	regularLogToastBodyUpdate = setInterval(function() {
		UpdateLogToastContents(session_id);
	}, 3000);
	regularLogToastAutoCloseCheck = setInterval(function() {
		AutoCloseLogToast(session_id);
	}, 20000);
	$('#LogToast').toast('show');
}

var log_concise_old = '';
function UpdateLogToastContents(session_id) {
	// read the log from getsessioninfo.php
	var url = "api/readGameSession.php";
	var data = {
		session_id: session_id
	}
	$.when(CallAPI(url, data)).done(function(results) {
		log_array = results.gamesession.log.slice(-5);			
		log_array.forEach(LogToastContentItemCleanUp);
		var log_concise_new = log_array.join("");
		if (log_concise_new == log_concise_old) {
			$('#LogToast').prop('data-autoclose', true);
		}
		else {
			$('#LogToast').prop('data-autoclose', false);
		}
		log_concise_old = log_concise_new;
		$("#LogToastBody").html(log_concise_new);
	});
}

function LogToastContentItemCleanUp(item, index, arr) {
	item = item.replace(/\[[0-9\-\s:]+\]/, "");
	item = item.replace("[ INFO ]", '<div style="color: green;">');
	item = item.replace("[ ERROR ]", '<div style="color: red;">');
	item = item.replace("[ WARN ]", '<div style="color: navy;">');
	item = item.replace("[ DEBUG ]", '<div style="color: grey;">');
	item += "</div>";
	arr[index] = item;
}

async function AutoCloseLogToast(session_id) {
	if ($('#LogToast').prop('data-autoclose')) {
		// stop the regular UpdateLogToastContents calls, but do another bunch of UpdateLogToastContents calls first
		clearTimeout(regularLogToastBodyUpdate); 
		for (var times = 0; times < 10; times++) { 
			await new Promise(r => setTimeout(r, 3000));
			UpdateLogToastContents(session_id);	
		}
		if ($('#LogToast').prop('data-autoclose')) {
			// stop calling this function and hide LogToast as a wrap-up
			clearTimeout(regularLogToastAutoCloseCheck); 
			$('#LogToast').toast('hide');
		}
		else {
			// otherwise re-initialise UpdateLogToastContents every 3 seconds and this function will be called again later
			regularLogToastBodyUpdate = setInterval(function() {
				UpdateLogToastContents(session_id);
			}, 3000);
		}
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