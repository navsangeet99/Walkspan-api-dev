
var app = {
	APIUrl: '../php/auth.php',
	Me: null//,
	//SelectedUserRow: null
};

$(document).ready(function () {

	initializeUsers();
});

function initializeUsers () {

	$('#divAdminDashboardLoader').show();

	var url = app.APIUrl + '?Action=GetUserDetails';
	
	var deferred = $.ajax({
		url: url,
		type: 'GET',
		beforeSend: function (xhr) { },
		success: function (data, textStatus, xhr) { 
		
			data = JSON.parse(data);
			
			for (var i = 0; i < data.length; i++) {
				if (data[i].IsMe) {
					app.Me = data[i];
					break;
				}
			}
			
			if (app.Me) {
				$('.nav-username').show().children('span')
					.html(app.Me.FirstName + ' ' + app.Me.LastName);
			}
			
			fillUsersGrid(data);
			
		}, error (error) {
			
			// Logout user on 401 Unauthorized responses
			if (error && error.status == 401) {
				
				if (Storage)
					localStorage.removeItem('WalkspanAPILoggedIn');
			
				window.location.href = 'login.html';
			}
		}
	});
}

function fillUsersGrid (userData) {
	
	$('#divAdminDashboardLoader').hide();
	
	var userRowTemplateHTML = $('#divAdminDashboard').find('.row-template')[0].outerHTML;
	
	$('#divAdminDashboard').find('.user-row').remove();
	
	for (var i = 0; i < userData.length; i++) {
		var newUserRow = $(userRowTemplateHTML);
		newUserRow.toggleClass('disabled', (userData.IsEnabled === false));
		newUserRow.addClass('user-row').removeClass('hidden');
		newUserRow.find('div').eq(0).html(i + 1);
		newUserRow.find('div').eq(1).html(userData[i].Organization);
		newUserRow.find('div').eq(2).html(userData[i].FirstName + ' ' + userData[i].LastName);
		newUserRow.find('div').eq(3).html(userData[i].Email);
		newUserRow.find('div').eq(4).html('Yes');
		newUserRow.find('div').eq(5).html((userData[i].IsApproved) ? 'Yes' : 'No');
		newUserRow.find('div').eq(6).html((userData[i].IsEnabled) ? 'Yes' : 'No');
		newUserRow.find('div').eq(7).html(userData[i].APICalls || '0');
		newUserRow.data('user', userData[i]);
		$('#divAdminDashboard').append(newUserRow);
		
		var buyUserKeyButton = newUserRow.find('.buy-user-key-button');
		var generateUserKeyButton = newUserRow.find('.generate-user-key-button');
		var showUserKeysButton = newUserRow.find('.show-user-keys-button');
		generateUserKeyButton.toggleClass('hidden', (userData[i].KeyCount > 0));
		showUserKeysButton.toggleClass('hidden', (! userData[i].KeyCount));

		buyUserKeyButton.unbind('click').bind('click', function () {
			buyUserKey(this);
		});
		
		generateUserKeyButton.unbind('click').bind('click', function () {
			generateUserKey(this);
		});
		
		showUserKeysButton.unbind('click').bind('click', function () {
			refreshUserKeys(this);
		});
		
		newUserRow.find('.disable-user-button').unbind('click').bind('click', function () {
			enableDisableUser(this);
		});
	}
}

function buyUserKey (element) {

	if ($(element).length == 0) return;
	
}

function generateUserKey (element) {
	
	if ($(element).length == 0) return;
	
	var userRow = $(element).closest('.user-row');
	if (userRow.length == 0) userRow = $(element).closest('.user-key-row').prevAll('.user-row').eq(0);
	var userData = userRow.data('user');
	
	var url = app.APIUrl + '?Action=GenerateUserKey';
	url += '&UserId=' + userData.Id;
	
	var deferred = $.ajax({
		url: url,
		type: 'GET',
		beforeSend: function (xhr) { },
		success: function (data, textStatus, xhr) {

			if ($(element).closest('.user-row').length == 0) {
				element = $(element).closest('.user-key-row').prevAll('.user-row')
					.eq(0).find('.show-user-keys-button');
			}
			setTimeout(function () {
				refreshUserKeys(element);
			}, 500);
		},
		error: function (e) { }
	});
}

function refreshUserKeys (element) {
	
	if ($(element).length == 0) return;
	
	var userRow = $(element).closest('.user-row');
	if (userRow.length == 0) userRow = $(element).closest('.user-key-row').prevAll('.user-row').eq(0);
	var userData = userRow.data('user');
	
	$('.show-user-keys-button').css({ 'visibility' : 'visible' });
	$(element).css({ 'visibility' : 'hidden' });
	
	var url = app.APIUrl + '?Action=GetUserKeys';
	url += '&UserId=' + userData.Id;
	
	var deferred = $.ajax({
		url: url,
		type: 'GET',
		beforeSend: function (xhr) { },
		success: function (data, textStatus, xhr) { 
			
			data = JSON.parse(data);
			if (! data) return;
			
			$('#divAdminDashboard').find('.user-key-row').remove();
			
			var userKeysHtml = '';
			for (var i = 0; i < data.length; i++) {
				userKeysHtml += '<tr class="user-key-row">';
				userKeysHtml += '<td><i class="fa fa-key"></i></td>';
				userKeysHtml += '<td colspan="10">' + 
					'<span>' + data[i].Key + '</span>' +
					'<div class="delete-key-button" onclick="deleteUserKey(this, ' + data[i].Id + ')">' +
						'<i class="fa fa-trash"></i>' + 
					'</div>' +
				'</td>';
				userKeysHtml += '</tr>';
			}
			userKeysHtml += '<tr class="user-key-row">' + 
				'<td></td>' +
				'<td colspan="9">' +
					'<button type="button" class="btn btn-light generate-user-key-button"' +
					' onclick="generateUserKey(this)">' +
						'<i class="fa fa-plus-circle"></i>' +
						'<span> Generate New Key </span>' +
					'</button>' +
					/*
					'<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top" class="button-form">' +
						'<input type="hidden" name="cmd" value="_s-xclick">' +
						'<input type="hidden" name="hosted_button_id" value="BT93DETU7URRU">' +
						'<input type="image" src="https://www.senseofwalk.com/walkspan-api/content/images/buy-key-button.png" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">' +
						'<img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">' +
					'</form>' +
					*/
				'</td>' +
			'</tr>';
			$(element).closest('.user-row').after(userKeysHtml);
			
		},
		error: function (e) { }
	});
}

function deleteUserKey (element, userKeyId) {
	
	if ($(element).length == 0) return;
	element = $(element).closest('.user-key-row').prevAll('.user-row').eq(0).find('.show-user-keys-button');
	
	var deleteUserKeyData = {
		'Action': 'DeleteUserKey',
		'UserKeyId': userKeyId
	};
	
	var deferred = $.ajax({
		url: app.APIUrl,
		type: 'POST',
		data: deleteUserKeyData,
		success: function (results) { 
			
			refreshUserKeys(element);
		},
		error: function (e) { }
	});
	
	return deferred;
}

function enableDisableUser (element, userName, value) {
	
	if ($(element).length == 0) return;
	
	var userRow = $(element).closest('.user-row');
	var userData = userRow.data('user');
	
	var enableDisableUserData = {
		'Action': 'EnableDisableUser',
		'UserId': userData.Id,
		'Enable': (!userData.IsEnabled)
	};
	
	var deferred = $.ajax({
		url: app.APIUrl,
		type: 'POST',
		data: enableDisableUserData,
		success: function (results) { 
			
			userData.IsEnabled = (! userData.IsEnabled);
			userRow.data('user', userData);
			refreshUserRow(element);
		},
		error: function (e) { }
	});
	
	return deferred;
}

function refreshUserRow (element) {
	
	if ($(element).length == 0) return;
	
	var userRow = $(element).closest('.user-row');
	var userData = userRow.data('user');
	
	userRow.toggleClass('disabled', (userData.IsEnabled === false));
	
	if (! userData.IsEnabled) {
		$(element).removeClass('btn-danger').addClass('btn-success');
		$(element).find('span').html('Enable');
		$(element).find('i').removeClass('fa-remove').addClass('fa-check');
	} else {
		$(element).removeClass('btn-success').addClass('btn-danger');
		$(element).find('span').html('Disable');
		$(element).find('i').removeClass('fa-check').addClass('fa-remove');
	}
	
	userRow.find('div').eq(6).html((userData.IsApproved) ? 'Yes' : 'No');
	userRow.find('div').eq(7).html((userData.IsEnabled) ? 'Yes' : 'No');
}

function isDefined (obj) {
	return ((typeof obj !== 'undefined') && (obj !== null));
}

function gotoMainView () {
	
	window.location.href = 'https://www.senseofwalk.com/walkspan-api/index.html';
}
