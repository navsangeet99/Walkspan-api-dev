<?php

	header('Access-Control-Allow-Headers: Content-Type, SameSite');
	header('Access-Control-Allow-Credentials: true');
	header('Access-Control-Allow-Methods: DELETE, GET, OPTIONS, POST');
	header('Access-Control-Allow-Origin: ' . 
		(isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : 
		(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 
		(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : ''))));

	// Ignore options
	if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {    
		return 0;
	}
	
	require_once('conn.php');
	
	require_once(dirname(__FILE__)."/PHPAuth/Config.php");
	require_once(dirname(__FILE__)."/PHPAuth/Auth.php");

	if (! isset($dbh)) $dbh = new PDO("pgsql:" .
		"dbname=" . $auth_conn->db_name . 
		";host=" . $auth_conn->host, 
		$auth_conn->user, 
		$auth_conn->password
	);
	
	$config = new PHPAuth\Config($dbh);
	$auth   = new PHPAuth\Auth($dbh, $config);
	
	$post = json_decode(file_get_contents('php://input'), true);
	if (empty($post)) $post = $_REQUEST;

	// Get action requested
	$_REQUEST_lower = array_change_key_case($_REQUEST, CASE_LOWER);
	$action = isset($_REQUEST_lower['action']) ? $_REQUEST_lower['action'] :
		(isset($post['Action']) ? $post['Action'] :
		(isset($post['action']) ? $post['action'] : null));
	if (! isset($action)) {
		http_response_code(400);
		die('Error: Invalid action request');
	}

	switch (strtolower($action))
	{
		// GET methods
		case 'getuserdetails': get_user_details(); break;
		case 'getuserkeys': get_user_keys(); break;
		case 'generateuserkey': generate_user_key(); break;
		case 'getsavedroutes': get_saved_routes(); break;
		// POST methods
		case 'activateuser': activate_user(); break;
		case 'buyuserkey': buy_user_key(); break;
		case 'confirmresetpassword': confirm_reset_password(); break;
		case 'createuser': create_user(); break;
		case 'deleteuserkey': delete_user_key(); break;
		case 'enabledisableuser': enable_disable_user(); break;
		case 'loginuser': login_user(); break;
		case 'logoutuser': logout_user(); break;
		case 'requestresetpassword': request_reset_password(); break;
		case 'saveroute': save_route(); break;
		case 'saverouterating': save_route_rating(); break;
		case 'savesteps': save_steps(); break;
	}
	
	// create_user - Registers a new user after the required fields have been provided
	function create_user () {
	
		//require_once(dirname(__FILE__)."/PHPAuth/Config.php");
		//require_once(dirname(__FILE__)."/PHPAuth/Auth.php");

		//global $dbh;
		global $auth;
		global $post;
	
		//$config = new PHPAuth\Config($dbh);
		//$auth   = new PHPAuth\Auth($dbh, $config);
		
		$auth_data = (object) [
			'first_name' => (isset($post['FirstName']) ? 
				pg_escape_string($post['FirstName']) : null),
			'last_name' => (isset($post['LastName']) ? 
				pg_escape_string($post['LastName']) : null),
			'organization' => (isset($post['Organization']) ? 
				pg_escape_string($post['Organization']) : null),
			'email_address' => (isset($post['EmailAddress']) ? 
				pg_escape_string($post['EmailAddress']) : null),
			'password' => (isset($post['Password']) ? 
				pg_escape_string($post['Password']) : null),
			'repeat_password' => (isset($post['RepeatPassword']) ? 
				pg_escape_string($post['RepeatPassword']) : null),
			'account_type' => (isset($post['AccountType']) ? 
				pg_escape_string($post['AccountType']) : 'user'),
			'age' => (isset($post['Age']) ? 
				pg_escape_string($post['Age']) : null),
			'height' => (isset($post['Height']) ? 
				pg_escape_string($post['Height']) : null),
			'step_goal' => (isset($post['StepGoal']) ? 
				pg_escape_string($post['StepGoal']) : null),
			'weight' => (isset($post['Weight']) ? 
				pg_escape_string($post['Weight']) : null),
			'weight_goal' => (isset($post['WeightGoal']) ? 
				pg_escape_string($post['WeightGoal']) : null),
		];
		
		echo print_r($auth_data);
		
		// Set additional create user parameters
		$params = array(
			'age' => $auth_data->age,
			'first_name' => $auth_data->first_name,
			'height' => $auth_data->height,
			'last_name' => $auth_data->last_name,
			'organization' => $auth_data->organization,
			'step_goal' => $auth_data->step_goal,
			'weight' => $auth_data->weight,
			'weight_goal' => $auth_data->weight_goal
		);
		
		sleep(1); // Wait to prevent too many requests
	
		$register = $auth->register(
			$auth_data->email_address, 
			$auth_data->password, 
			$auth_data->repeat_password, 
			$auth_data->account_type, 
			$params, null, true);
			
		$message = '';
		if (isset($register['error']) && ($register['error'] == 1)) {
			
			$message = $register['message'];
		} else {
			
			// Registration was successful
			$message = $register['message'];
			
			// Test AWS email
			// $register['email_data']['header']
			send_email_aws (
				$register['email_data']['email'], 
				'Walkspan', 
				'aradules@gmail.com', 
				'Walkspan',
				$register['email_data']['subject'], 
				$register['email_data']['message']);
		}

		echo $message;
	}
	
	function request_reset_password () {
		
		//require_once(dirname(__FILE__)."/PHPAuth/Config.php");
		//require_once(dirname(__FILE__)."/PHPAuth/Auth.php");

		global $auth;
		global $post;
		
		//$config = new PHPAuth\Config($dbh);
		//$auth   = new PHPAuth\Auth($dbh, $config);
		
		$reset_email_address = isset($post['EmailAddress']) ? 
			pg_escape_string($post['EmailAddress']) : null;
	
		sleep(1); // Wait to prevent too many requests
	
		$reset = $auth->requestReset($reset_email_address, true);
	
		$message = '';
		if (isset($reset['error']) && ($reset['error'] == 1)) {
			$message = $reset['message'];
			
		} else {
			// Registration was successful
			$message = $reset['message'];
			
			// Send email using relay
			global $email_relay;
			
			// Post to relay server
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $email_relay->url);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, array(
				'key' => $email_relay->key,
				'email' => $reset['email_data']['email'],
				'header' => $reset['email_data']['header'],
				'message' => $reset['email_data']['message'],
				'subject' => $reset['email_data']['subject']
			));
			curl_exec($ch);
			curl_close($ch);
		}

		echo $message;
	}
	
	function confirm_reset_password () {
		
		//require_once(dirname(__FILE__)."/PHPAuth/Config.php");
		//require_once(dirname(__FILE__)."/PHPAuth/Auth.php");

		global $auth;
		global $post;
		
		//$config = new PHPAuth\Config($dbh);
		//$auth   = new PHPAuth\Auth($dbh, $config);
		
		$reset_key = isset($post['ResetKey']) ? 
			pg_escape_string($post['ResetKey']) : null;
		$reset_password = isset($post['Password']) ? 
			pg_escape_string($post['Password']) : null;
		$reset_password_confirm = isset($post['PasswordConfirm']) ? 
			pg_escape_string($post['PasswordConfirm']) : null;

		$auth->resetPass(
			$reset_key, 
			$reset_password, 
			$reset_password_confirm, 
			true);
	}

	// activate_user - Activates a user that has been emailed an activation key after registering
	function activate_user () {
	
		//require_once(dirname(__FILE__)."/PHPAuth/Config.php");
		//require_once(dirname(__FILE__)."/PHPAuth/Auth.php");
	
		global $auth;
		global $post;
		
		//$config = new PHPAuth\Config($dbh);
		//$auth   = new PHPAuth\Auth($dbh, $config);

		$auth_data = (object) [
			'activation_key' => (isset($post['ActivationKey']) ? 
				pg_escape_string($post['ActivationKey']) : null)
		];
		
		sleep(2); // Wait to prevent too many requests

		$activation = $auth->activate($auth_data->activation_key);
		
		$message = '';
		if (isset($activation['error']) && ($activation['error'] == 1)) {
			
			$message = $activation['message'];
			http_response_code(400);
			die('Bad Request');
		} else {
			
			$message = 'Activation Successful';
			http_response_code(200);
		}
		
		echo $message;
	}
	
	// login_user - Authenticates a user that provides a username and password
	function login_user () {
		
		global $auth;
		global $post;
		
		$auth_data = (object) [
			'emailaddress' => (isset($post['EmailAddress']) ? 
				pg_escape_string($post['EmailAddress']) : null),
			'password' => (isset($post['Password']) ? 
				pg_escape_string($post['Password']) : null),
			'remember_me' => (isset($post['RememberMe']) ? 
				pg_escape_string($post['RememberMe']) : null)
		];
		
		$login = $auth->login(
			$auth_data->emailaddress, 
			$auth_data->password, 
			$auth_data->remember_me);
		
		$message = '';
		if (isset($login['error']) && ($login['error'] == 1)) {
			
			http_response_code(400);
			$message = 'Error: ' . $login['message'];
		} else {
			// Login was successful
			// Get user's account type
			$query = "
				SELECT 	account_type 
				FROM 	public.users
				WHERE 	(email ILIKE '" . $auth_data->emailaddress . "')
			";
			
			$conn = get_postgresql_db_connection('walkspan_auth');
			
			$result = pg_query($conn, $query) 
				or die ('Error: ' + pg_last_error($conn) + '\n');

			$row = pg_fetch_row($result);
			
			$message = 'Login Successful';
		}
		
		echo $message;
	}
	
	// logout_user - Logs out the current user
	function logout_user () {
		global $auth;
		$auth->logout($auth->getSessionHash());
	}
	
	function enable_disable_user () {
		
		check_authorization();
		
		global $post;
		
		$user_id = isset($post['UserId']) ? pg_escape_string($post['UserId']) : null;
		$enable = isset($post['Enable']) ? pg_escape_string($post['Enable']) : null;
		
		if (! $user_id) {
			echo '0';
			exit;
		}
		
		$query = "
			UPDATE 	users 
			SET 	Enabled = " . (($enable == 'true') ? "1" : "0") . "::BIT
			WHERE 	(id = $user_id)
		";
			
		$conn = get_postgresql_db_connection('walkspan_auth');
		
		$result = pg_query($conn, $query) 
			or die ('Error: ' + pg_last_error($conn) + '\n');

		pg_close($conn);
		
		echo '1';
	}
	
	// get_user_details - Gets all relevant info about the currently logged in user
	function get_user_details () {

		check_authorization();

		global $auth;
		
		$user_id = @$auth->getSessionUID($auth->getSessionHash());
		$user_id = is_numeric($user_id) ? $user_id : 59; // NOTE: Temporary for Beta testing
		$_REQUEST_lower = array_change_key_case($_REQUEST, CASE_LOWER);
		$user_date = isset($_REQUEST_lower['userdate']) ? pg_escape_string($_REQUEST_lower['userdate']) : null;
		
		$query = "
			SELECT 
					  id AS Id
					, first_name AS FirstName
					, last_name AS LastName
					, email AS Email
					, organization AS Organization
					, approved AS IsApproved
					, enabled AS IsEnabled
					, api_calls AS APICalls
					, (SELECT COUNT(id) FROM user_keys UK WHERE UK.user_id = U.id) AS KeyCount
					, isactive AS IsActive
					, account_type AS AccountType
					, age AS Age
					, height AS Height
					, step_goal AS StepGoal
					, weight AS Weight
					, weight_goal AS WeightGoal
					, CASE WHEN (id = $user_id) THEN 1 ELSE 0 END AS IsMe
					, (SELECT json_agg(US) FROM user_steps US WHERE (US.user_id = $user_id) 
						AND (date_part('month', created_date) = date_part('month', timestamp '$user_date'))
						AND (date_part('year', created_date) = date_part('year', timestamp '$user_date')) LIMIT 1) AS Steps
			FROM 	public.users U
			LEFT OUTER JOIN (SELECT CASE WHEN account_type = 'admin' THEN 1 ELSE 0 END AS IsAdmin 
				FROM public.users 
				WHERE (id = $user_id)) AS T ON (1 = 1)
			WHERE 	(1 = 1)
				AND ((id = $user_id) OR (T.IsAdmin = 1));
		";
		
		$conn = get_postgresql_db_connection('walkspan_auth');
		
		$results = pg_query($conn, $query) 
			or die ('Error: ' + pg_last_error($conn) + '\n');

		$userDetails = array();
		while ($row = pg_fetch_row($results)) {
			$userDetails = array(
				  'Id' => $row[0]
				, 'FirstName' => $row[1]
				, 'LastName' => $row[2]
				, 'Email' => $row[3]
				, 'Organization' => $row[4]
				, 'IsApproved' => ($row[5] == '1' ? true : false)
				, 'IsEnabled' => ($row[6] == '1' ? true : false)
				, 'APICalls' => $row[7]
				, 'KeyCount' => intval($row[8])
				, 'IsActive' => ($row[9] == '1' ? true : false)
				, 'AccountType' => $row[10]
				, 'Age' => $row[11]
				, 'Height' => $row[12]
				, 'StepGoal' => $row[13]
				, 'Weight' => $row[14]
				, 'WeightGoal' => $row[15]
				, 'IsMe' => ($row[16] == '1' ? true : false)
				, 'Steps' => (isset($row[17]) ? $row[17] : '[]')
			);
		}
		
		pg_close($conn);
		
		echo json_encode($userDetails);
	}
	
	function get_user_keys () {
		
		check_authorization();
		
		$user_id = isset($_REQUEST['UserId']) ? pg_escape_string($_REQUEST['UserId']) : null;
		
		$query = "
			SELECT 	  id
  					, user_id
					, \"key\"
					, isactive
			FROM	user_keys UK
			WHERE	UK.user_id = (SELECT id 
					FROM public.users
					WHERE (id = " . $user_id . "));
		";
		
		$conn = get_postgresql_db_connection('walkspan_auth');
		
		$results = pg_query($conn, $query) 
			or die ('Error: ' + pg_last_error($conn) + '\n');

		$user_keys = array();
		while ($row = pg_fetch_row($results)) {
			
			$user_key = array(
				  'Id' => $row[0]
				, 'UserId' => $row[1]
				, 'Key' => $row[2]
				, 'IsActive' => $row[3]
			);
			array_push($user_keys, $user_key);
		}
		
		echo json_encode($user_keys);
	}
	
	function buy_user_key () {
		check_authorization();
		http_response_code(501);
		echo 'Not Implemented';
	}
	
	function generate_user_key () {
		
		check_authorization();
		
		$user_id = isset($_REQUEST['UserId']) ? pg_escape_string($_REQUEST['UserId']) : null;
		
		$ip = (isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? ($_SERVER['HTTP_X_FORWARDED_FOR'] . ", ") : "") . $_SERVER['REMOTE_ADDR'];
		$key = strtoupper(bin2hex(openssl_random_pseudo_bytes(16)));
		
		$query = "
			INSERT INTO user_keys (user_id, \"key\", isactive, created_date, ip_address) VALUES
			(
				(SELECT id 
					FROM public.users
					WHERE (id = " . $user_id . ")) 
				, '$key'
				, 1
				, now()
				, '$ip'
			);
		";
			
		$conn = get_postgresql_db_connection('walkspan_auth');
		
		$result = pg_query($conn, $query) 
			or die ('Error: ' + pg_last_error($conn) + '\n');

		echo $key;		
	}
	
	function delete_user_key () {
		
		check_authorization();
		
		global $post;
		
		$user_key_id = isset($post['UserKeyId']) ? pg_escape_string($post['UserKeyId']) : null;
		
		if (! $user_key_id) {
			echo '0';
			exit;
		}
		
		$query = "
			DELETE 	FROM user_keys
			WHERE 	(id = $user_key_id)
		";
			
		$conn = get_postgresql_db_connection('walkspan_auth');
		
		$result = pg_query($conn, $query) 
			or die ('Error: ' + pg_last_error($conn) + '\n');

		pg_close($conn);
		
		echo '1';
	}
	
	function get_saved_routes() {
	
		check_authorization();
		
		global $auth;
		$user_id = @$auth->getSessionUID($auth->getSessionHash());
		$user_id = is_numeric($user_id) ? $user_id : 59; // NOTE: Temporary for Beta testing
			
		$query = "
			SELECT 	  id
  					, waypoints
					, transportation_mode
					, walkspan_value
					, to_char(created_date, 'MM/DD/YYYY hh:mm:ss')
					, rating
			FROM	user_saved_routes USR
			WHERE	(USR.user_id = $user_id)
			ORDER BY USR.created_date DESC;
		";
		
		$conn = get_postgresql_db_connection('walkspan_auth');
		
		$results = pg_query($conn, $query) 
			or die ('Error: ' + pg_last_error($conn) + '\n');

		$user_saved_routes = array();
		while ($row = pg_fetch_row($results)) {
			
			$user_saved_route = array(
				  'Id' => $row[0]
				, 'Waypoints' => $row[1]
				, 'TransportationMode' => $row[2]
				, 'WalkspanValue' => $row[3]
				, 'CreatedDate' => $row[4]
				, 'Rating' => intval($row[5])
			);
			array_push($user_saved_routes, $user_saved_route);
		}
		
		echo json_encode($user_saved_routes);
	}
	
	function save_route() {
		
		check_authorization();
		
		global $auth;
		global $post;
		
		$user_id = @$auth->getSessionUID($auth->getSessionHash());
		$user_id = is_numeric($user_id) ? $user_id : 59; // NOTE: Temporary for Beta testing
		
		$is_saved = isset($post['IsSaved']) ? pg_escape_string($post['IsSaved']) : null;
		$rating = isset($post['Rating']) ? pg_escape_string($post['Rating']) : null;
		$transportation_mode = isset($post['TransportationMode']) ? pg_escape_string($post['TransportationMode']) : null;
		$walkspan_value = isset($post['WalkspanValue']) ? pg_escape_string($post['WalkspanValue']) : null;
		$waypoints = array();
		if (isset($post['Waypoints'])) {
			foreach($post['Waypoints'] as $w) {
				$waypoint = array(
					'Lat' => pg_escape_string($w['Lat']),
					'Lng' => pg_escape_string($w['Lng']),
					'Value' => pg_escape_string($w['Value'])
				);
				array_push($waypoints, $waypoint);
			}
		}
		
		// Check if this route already exists, if so either 
		// do nothing or remove it if is_saved is false
		$query = "
			SELECT 1 FROM user_saved_routes USR
			WHERE	(USR.user_id = $user_id) 
				AND (USR.waypoints = '" . json_encode($waypoints) . "')
				AND (USR.transportation_mode = '$transportation_mode')
				AND (USR.walkspan_value = '$walkspan_value');
		";
		
		$conn = get_postgresql_db_connection('walkspan_auth');
		
		$results = pg_query($conn, $query) 
			or die ('Error: ' + pg_last_error($conn) + '\n');
		$row = pg_fetch_row($results);

		// Remove existing route if it exists and is toggled off
		if (($row[0] == '1') && !$is_saved) {
			$query = "
				DELETE FROM user_saved_routes
				WHERE	(user_id = $user_id) 
					AND (waypoints = '" . json_encode($waypoints) . "')
					AND (transportation_mode = '$transportation_mode')
					AND (walkspan_value = '$walkspan_value');
			";
			$result = pg_query($conn, $query) 
				or die ('Error: ' + pg_last_error($conn) + '\n');
		}
		// Insert new route if it does not exist and is toggled on
		else if (($row[0] != '1') && $is_saved) {
			$query = "
				INSERT INTO user_saved_routes 
				(user_id, waypoints, transportation_mode, walkspan_value, created_date) VALUES
				(	  $user_id
					, '" . json_encode($waypoints) . "'
					, '$transportation_mode'
					, '$walkspan_value'
					, TIMEZONE('utc', NOW())
				);
			";
			$result = pg_query($conn, $query) 
				or die ('Error: ' + pg_last_error($conn) + '\n');
		}
		
		pg_close($conn);
		
		echo '1';
	}
	
	function save_route_rating() {
		
		check_authorization();
		
		global $auth;
		global $post;
		
		$user_id = @$auth->getSessionUID($auth->getSessionHash());
		$user_id = is_numeric($user_id) ? $user_id : 59; // NOTE: Temporary for Beta testing
		$route_id = isset($post['RouteId']) ? pg_escape_string($post['RouteId']) : null;
		$rating = isset($post['Rating']) ? pg_escape_string($post['Rating']) : null;
		
		$conn = get_postgresql_db_connection('walkspan_auth');
		$query = "
			UPDATE user_saved_routes 
			SET rating = $rating 
			WHERE (id = $route_id) AND (user_id = $user_id);
		";
		$result = pg_query($conn, $query) 
			or die ('Error: ' + pg_last_error($conn) + '\n');
		pg_close($conn);
		
		echo '1';
	}
	
	function save_steps() {
		
		check_authorization();
		
		global $auth;
		global $post;
		
		$user_id = @$auth->getSessionUID($auth->getSessionHash());
		$user_id = is_numeric($user_id) ? $user_id : 59; // NOTE: Temporary for Beta testing
		$distance = isset($post['Distance']) ? pg_escape_string($post['Distance']) : null;
		$user_date = isset($post['UserDate']) ? pg_escape_string($post['UserDate']) : null;
		
		$conn = get_postgresql_db_connection('walkspan_auth');
		$query = "
			INSERT INTO user_steps (user_id, distance, created_date)
			VALUES($user_id, $distance, TO_DATE('$user_date', 'YYYY-MM-DD')::timestamp) 
			ON CONFLICT (user_id, created_date) 
			DO UPDATE SET distance = user_steps.distance + EXCLUDED.distance;
		";
		$result = pg_query($conn, $query) 
			or die ('Error: ' + pg_last_error($conn) + '\n');
		pg_close($conn);
		
		echo '1';
	}
	
	function check_authorization () {
		
		global $auth;
		// Note: Temporary disabled for test purposes
		/*
		if (!$auth->isLogged()) {
			header('HTTP/1.0 403 Forbidden');
			echo "Forbidden";
			exit();
		}
		*/
	}
	
	function get_postgresql_db_connection ($db_name='walkspan_auth') {
		
		global $auth_conn;
		
		$pgdbconn = pg_connect(
			" host=" . $auth_conn->host . 
			" port=" . $auth_conn->port .
			" dbname=" . $db_name . 
			" user=" . $auth_conn->user . 
			" password=" . $auth_conn->password
		) or die ('An error occurred.\n');
		
		return $pgdbconn;
	}
?>
	