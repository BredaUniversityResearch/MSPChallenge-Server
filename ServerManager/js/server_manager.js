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
					WatchdogServerList();
					GetServerAddr();
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
