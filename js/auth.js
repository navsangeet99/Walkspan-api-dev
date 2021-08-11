
var authApiUrl = '../php/auth.php';

$(document).ready(function () {
	
	$('#txtUsername, #txtPassword').keyup(function (e) {
		if (e.keyCode == 13) loginUser();
	});
	
	window.addEventListener('message', function(event) { 

		// Only process messages if they come from trusted sources
		if (/senseofwalk.com$/.test(event.origin)) { 
		
			if (event.data.location == 'main-view') {
				gotoMainView();
			} else if (event.data.location == 'request-key') {
				gotoRequestKey();
			}
		}
	}); 
});

function loginUser () {
	
	var loginData = {
		'Action': 'LoginUser',
		'EmailAddress': $('#txtUsername').val(),
		'Password': $('#txtPassword').val(),
	};
	
	$('#btnLogin').addClass('in-progress').attr('disabled', '');
	
	var deferred = $.ajax({
		url: authApiUrl,
		type: 'POST',
		data: loginData,
		success: function (results) { 
			
			if (Storage)
				localStorage.setItem('WalkspanAPILoggedIn', 'true');
			
			window.location.href = 'dashboard.html';
		},
		error: function (e) {
			$('#btnLogin').removeClass('in-progress').removeAttr('disabled');
			$('.auth-error').html(e.responseText);
		}
	});
	
	return deferred;
}

function openUserDetails () {
	
	var deferred = $.ajax({
		url: authApiUrl + '?Action=GetUserDetails',
		type: 'GET',
		success: function (results) { 
			
			results = JSON.parse(results);
			
			var activeStatusDisplay = results.IsActive ? 'Yes' : 'No';
			
			$('#txtUserEmailAddress').val(results.Email);
			$('#txtUserAccountType').val(results.AccountType);
			$('#txtUserActiveStatus').val(activeStatusDisplay);
			$('#txtUserExpireDate').val(results.ExpirationDate);
		},
		error: function (e) {
			
			console.log(e.responseText);
		}
	});
	
	return deferred;
}

function logoutUser() {
	
	var logoutData = { 
		Action: 'LogoutUser' 
	};

	var deferred = $.ajax({
		url: authApiUrl,
		type: 'POST',
		data: logoutData,
		success: function (results) {
			
			if (Storage)
				localStorage.removeItem('WalkspanAPILoggedIn');
			
			window.location.href = 'login.html';
		},
		error: function (e) {
			
			console.log(e.responseText);
		}
	});
	
	return deferred;
}

function openInviteUser () {
	
	$(document).ready(function () {
		
		$('#txtExpirationTime').timepicker({
			'showDuration': true,
			'timeFormat': 'g:ia'
		});

		$('#txtExpirationDate').datepicker({
			'format': 'yyyy-m-d',
			'autoclose': true
		});
	});
}

function createUser () {
	
	var registrationData = {
		Action: 'CreateUser',
		FirstName: $('#txtFirstName').val(),
		LastName: $('#txtLastName').val(),
		Organization: $('#txtOrganization').val(),
		EmailAddress: $('#txtCreateUserEmail').val(),
		Password: $('#txtCreateUserPassword').val(),
		RepeatPassword: $('#txtCreateUserConfirmPassword').val(),
		AccountType: 'user'
	};
	
	$('#btnRegisterUser').addClass('in-progress').attr('disabled', '');
	
	console.log('createUser() #2'); // Debug
	
	var deferred = $.ajax({
		url: authApiUrl + '?action=createuser',
		type: 'POST',
		contentType : 'application/json',
		data: JSON.stringify(registrationData),
		success: function (results) {
		
			alert('User with email address ' + registrationData.CreateEmailAddress + 
				' has been successfully created. An email has been sent to the ' + 
				'email address to confirm this new account.');

			setTimeout(function () {
				window.location.href = 'login.html';
			}, 300);
		},
		error: function (e) {
			
			$('#btnRegisterUser').addClass('in-progress').attr('disabled', '');
			console.log(e.responseText);
			
			setTimeout(function () {
				window.location.href='login.html';
			}, 1000);
		}
	});
	
	return deferred;
}

function requestResetPassword () {
	
	requestResetData = {
		Action: 'RequestResetPassword',
		ResetEmailAddress: $('#txtForgotPasswordEmailAddress').val()
	};

	$('#btnRequestResetPassword').addClass('in-progress').attr('disabled', '');
	
	var deferred = $.ajax({
		url: authApiUrl,
		type: 'POST',
		data: requestResetData,
		success: function (results) { 
		
			window.location.href = '.';
		},
		error: function (e) {
			
			$('#btnRequestResetPassword').removeClass('in-progress').removeAttr('disabled');
			$('.auth-error').html(e.responseText);
		}
	});
	
	return deferred;
}

function confirmResetPassword () {
	
	var confirmResetData = {
		Action: 'ConfirmResetPassword',
		ResetKey: $('#txtResetKey').val(),
		Password: $('#txtResetPassword').val(),
		PasswordConfirm: $('#txtResetConfirmPassword').val()
	};
	
	$('#btnConfirmResetPassword').addClass('in-progress').attr('disabled', '');

	var deferred = $.ajax({
		url: authApiUrl,
		type: 'POST',
		data: confirmResetData,
		success: function (results) { 
		
			window.location.href = '.';
		},
		error: function (e) {
			
			$('#btnConfirmResetPassword').removeClass('in-progress').removeAttr('disabled');
			$('.auth-error').html(e.responseText);
		}
	});
	
	return deferred;
}

function activateUser () {
	
	var activationData = {
		Action: 'ActivateUser',
		ActivationKey: $('#txtActivationKey').val()
	};
	
	$('#btnActivateUser').addClass('in-progress').attr('disabled', '');
	
	var deferred = $.ajax({
		url: authApiUrl,
		type: 'POST',
		data: activationData,
		success: function (results) { 
		
			setTimeout(function () {
				window.location.href = 'login.html';
			}, 300);
		},
		error: function (e) {
			
			$('#btnActivateUser').addClass('in-progress').attr('disabled', '');
			$('.auth-error').html(e.responseText);
		}
	});
	
	return deferred;
}

function returnToHome() {
	
	var loggedIn = localStorage.getItem('WalkspanAPILoggedIn');
	
	window.location.href = (loggedIn) ? 'dashboard.html' : 'login.html';
}

function gotoMainView () {
	window.location.href = 'https://www.senseofwalk.com/walkspan-api/index.html';
}

function gotoRequestKey () {
	window.location.href = 'https://www.senseofwalk.com/walkspan-api/admin/login.html';
}

