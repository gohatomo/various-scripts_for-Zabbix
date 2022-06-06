<?php

////////////////////////////////////////////////////////////////////////////////
//	Common Function
////////////////////////////////////////////////////////////////////////////////
//Processing status display
function processing_status_display($now, $max, $error) {
	echo "\rWorking..." . ($now) . "/" . $max .  "/" . $error ." (now/max/error)";
}

//Output log
function log_output ($log_file, $message) {
	//File open
	$f = fopen($log_file, "a");

	//Data get
	$date = date("Y/m/d H:i:s");
	
	//Output log
	fwrite($f,"$date :" . $message . PHP_EOL);

	//File close
	fclose($f);
}

////////////////////////////////////////////////////////////////////////////////
//	API Function
////////////////////////////////////////////////////////////////////////////////
//Curl setup
function curl_setup($api_url) {
	$ch = curl_init();
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($ch, CURLOPT_URL,$api_url);
	return $ch;
}

//API login
function api_login($api_user, $api_pass) {
	$params = array(
		'user' => $api_user,
		'password' => $api_pass
	);
	$auth = null;
	$response = api_request('user.login', $params, $auth, '');
	if (isset($response['result'])) {
		return $response['result'];
	}
	elseif (isset($response['error'])) {
		$error_message = error_message($response);
		echo $error_message;
		exit(1);
	}
	else {
		$error_message = error_message_unexpected($response);
		echo $error_message;
		exit(1);
	}
}

//API logout
function api_logout($auth) {
	$response = api_request('user.logout', [], $auth, '');
	if (isset($response['result'])) {
		return $response['result'];
	}
	elseif (isset($response['error'])) {
		$error_message = error_message($response);
		echo $error_message;
		exit(1);
	}
	else {
		$error_message = error_message_unexpected($response);
		echo $error_message;
		exit(1);
	}
}

//API request
function api_request($method, $params, $auth, $flag) {
	global $ch;
	
	$id = date('YmdHis');
	
	//Request Data
	$request = array(
		'jsonrpc' => '2.0',
		'method' => $method,
		'params' => $params,
		'id' => $id,
		'auth' => $auth
	);
	//JSON Encode
	$request_json = json_encode($request);
	if ($flag === 'replace'){
		$request_json = str_replace("\\\\r\\\\n", "\\r\\n", $request_json);
	}
	
	//Curl Setup
	curl_setopt($ch, CURLOPT_POSTFIELDS, $request_json);
	
	//Curl Execute
	$response_json = curl_exec($ch);
	
	//JSON Decode
	$response = json_decode($response_json, true);
	
	return $response;
}

//Handling error messages
function error_message($response) {
	$error_message = "[ERROR] "
		. "code:\"" . $response['error']['code'] . "\", "
		. "message:\"" . $response['error']['message'] . "\", "
		. "data:\"" . $response['error']['data'] . "\"";
	return $error_message;
}

//Handling error messages unexpected
function error_message_unexpected($response) {
	$error_message = "[ERROR] Unexpected error has occuerd."
		. "data:\"" . $response . "\"";
	return $error_message;
}

?>
