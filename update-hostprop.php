#!/usr/bin/php
<?php
///////////////////////////////////////////////////////////////////////////////////
//	Name: update-hostprop.php
//	Description: Update standard property and inventory to host.
///////////////////////////////////////////////////////////////////////////////////

//Require
require_once 'conf/config.php';
require_once 'common_function.php';

//Setting timezone
date_default_timezone_set("$timezone");

//Log file settings
$date = date("Ymd");

$log_file = rtrim($log_dir, '/') . "/update-hostprop_{$date}.log";
if (!file_exists($log_dir)) {
	mkdir($log_dir, 0755);
}

//Datafile get
$data_dir = rtrim($data_dir, '/') . "/update-hostprop";

foreach (glob("{$data_dir}/*.json") as $fname) {
	if (!is_dir($fname)) {
		$file_list[] = mb_substr(mb_strrchr("/$fname", '/', false, "$encoding"), 1, NULL, "$encoding");
	}
}
if (!isset($file_list[0])) {
	$error_message = "[ERROR] "
		. "message:\"" . "JSON file is not exists. Check directory {$data_dir}.";
	log_output($log_file, $error_message);
	exit(1);
}

//API login
$ch = curl_setup($api_url);
$auth = api_login($api_user, $api_pass);

//Array declaration
$array_proxy = array();

//Display adjustment
echo "\n";

//Update host property
$count = count($file_list);
$error_count = 0;

for ($i = 0; $i < $count; ++$i) {
	//Processing status display
	processing_status_display($i+1, $count, $error_count);
	
	//File data get
	$data_json = file_get_contents("{$data_dir}/$file_list[$i]");
	$data_array = json_decode($data_json, true);
	if (empty($data_array)) {
		//Error count
		$error_count = $error_count+1;
		processing_status_display($i+1, $count, $error_count);
		
		//Error message output
		$error_message = "[ERROR] "
			. "message:\"" . "File is illegal json format." . "\", "
			. "file:\"" . $file_list[$i] . "\"";
		log_output($log_file, $error_message);
		
		//Processing skip
		continue;
	}
	
	$count_host = count($data_array);
	for ($j = 0; $j < $count_host; ++$j) {
		//Array declaration
		$params = array();

		//Taget hostid
		$target_hostid = "";
		
		if (isset($data_array[$j]['host'])) {
			$target_hostid = get_hostid($data_array[$j]['host']);

			if (is_null($target_hostid)) {
				//Error count
				$error_count = $error_count+1;
				processing_status_display($i+1, $count, $error_count);

				//Error message output
				$error_message = "[ERROR] "
					. "message:\"" . "host is missmatched." . "\", "
					. "file:\"" . $file_list[$i] . "\", "
					. "data:\"" . $data_array[$j]['host'] . "\"";
				log_output($log_file, $error_message);

				//Processing skip
				continue;
			}
			else {
				$params['hostid'] = $target_hostid;
			}
		}
		else {
			//Error count
			$error_count = $error_count+1;
			processing_status_display($i+1, $count, $error_count);

			//Error message output
			$error_message = "[ERROR] "
				. "message:\"" . "host is not set." . "\", "
				. "file:\"" . $file_list[$i] . "\",";
			log_output($log_file, $error_message);

			//Processing skip
			continue;
		}

		if (isset($data_array[$j]['property'])) {
			//hostname
			if (isset($data_array[$j]['property']['host'])) {
				if (!empty($data_array[$j]['property']['host'])) {
					$params['host'] = $data_array[$j]['property']['host'];
				}
			}

			//displayname
			if (isset($data_array[$j]['property']['name'])) {
				if (!empty($data_array[$j]['property']['name'])) {
					$params['name'] = $data_array[$j]['property']['name'];
				}
			}

			//status
			if (isset($data_array[$j]['property']['status'])) {
				if (!empty($data_array[$j]['property']['status'])) {
					if ($data_array[$j]['property']['status'] === 'enable') {
						$params['status'] = '0';
					}
					elseif ($data_array[$j]['property']['status'] === 'disable') {
						$params['status'] = '1';
					}
					else {
						$error_count = $error_count+1;
						$error_message = "[ERROR] "
							. "message:\"" . "status is missmatched." . "\", "
							. "file:\"" . $file_list[$i] . "\", "
							. "data:\"" . $data_array[$j]['property']['status'] . "\"";
						log_output($log_file, $error_message);
						continue;
					}
				}
			}

			//proxy_hostid
			$proxy_hostid = "";

			if (isset($data_array[$j]['property']['proxy'])) {
				if (!empty($data_array[$j]['property']['proxy'])) {
					if ($data_array[$j]['property']['proxy'] === 'none') {
						$params['proxy_hostid'] = '0';
					}
					else {
						if (in_array($data_array[$j]['property']['proxy'], $array_proxy)) {
							$proxy_hostid = array_search($data_array[$j]['property']['proxy'], $array_proxy);
							$params['proxy_hostid'] = $proxy_hostid;
						}
						else {
							$proxy_hostid = get_proxyid($data_array[$j]['property']['proxy']);

							if (!is_null($proxy_hostid)) {
								$params['proxy_hostid'] = $proxy_hostid;
								$array_proxy[$proxy_hostid] = $data_array[$j]['property']['proxy'];
							}
							else {
								//Error count
								$error_count = $error_count+1;
								processing_status_display($i+1, $count, $error_count);

								//Error message output
								$error_message = "[ERROR] "
									. "message:\"" . "proxy is missmatched." . "\", "
									. "file:\"" . $file_list[$i] . "\", "
									. "data:\"" . $data_array[$j]['property']['proxy'] . "\"";
								log_output($log_file, $error_message);

								//Processing skip
								continue;
							}
						}
					}
				}
			}

			//description
			if (isset($data_array[$j]['property']['description'])) {
				if (!empty($data_array[$j]['property']['description'])) {
					$params['description'] = $data_array[$j]['property']['description'];
				}
			}

			//inventory_mode
			if (isset($data_array[$j]['property']['inventory']['mode'])) {
				if (!empty($data_array[$j]['property']['inventory']['mode'])) {
					if ($data_array[$j]['property']['inventory']['mode'] === 'disable') {
						$params['inventory_mode'] = '-1';
					}
					elseif ($data_array[$j]['property']['inventory']['mode'] === 'manual') {
						$params['inventory_mode'] = '0';
					}
					elseif ($data_array[$j]['property']['inventory']['mode'] === 'auto') {
						$params['inventory_mode'] = '1';
					}
					else {
						//Error count
						$error_count = $error_count+1;
						processing_status_display($i+1, $count, $error_count);

						//Error message output
						$error_message = "[ERROR] "
							. "message:\"" . "inbentory_mode is missmatched." . "\", "
							. "file:\"" . $file_list[$i] . "\", "
							. "data:\"" . $data_array[$j]['property']['inventory']['mode'] . "\"";
						log_output($log_file, $error_message);

						//Processing skip
						continue;
					}
				}
			}

			//inventory
			if (isset($data_array[$j]['property']['inventory']['inventory'][0])) {
				$count_inventory = count($data_array[$j]['property']['inventory']['inventory']);
				for ($k = 0; $k < $count_inventory; ++$k) {
					$params['inventory'][$data_array[$j]['property']['inventory']['inventory'][$k]['type']] = $data_array[$j]['property']['inventory']['inventory'][$k]['value'];
				}
			}

			//encryption
			$tls_connect = "";
			$tls_accept = "";
			$tls_issuer = "";
			$tls_subject = "";
			$tls_psk_identity = "";
			$tls_psk = "";

			if (isset($data_array[$j]['property']['encryption'])) {
				//tls_connect
				if (isset($data_array[$j]['property']['encryption']['tls_connect'])) {
					if (!empty($data_array[$j]['property']['encryption']['tls_connect'])) {
						if ($data_array[$j]['property']['encryption']['tls_connect'] === 'no') {
							$tls_connect = '1';
							$params['tls_connect'] = '1';
						}
						elseif ($data_array[$j]['property']['encryption']['tls_connect'] === 'psk') {
							$tls_connect = '2';
							$params['tls_connect'] = '2';
						}
						elseif ($data_array[$j]['property']['encryption']['tls_connect'] === 'certificate') {
							$tls_connect = '4';
							$params['tls_connect'] = '4';
						}
						else {
							//Error count
							$error_count = $error_count+1;
							processing_status_display($i+1, $count, $error_count);

							//Error message output
							$error_message = "[ERROR] "
								. "message:\"" . "encryption tls_connect is missmatched." . "\", "
								. "file:\"" . $file_list[$i] . "\", "
								. "data:\"" . $data_array[$j]['property']['encryption']['tls_connect'] . "\"";
							log_output($log_file, $error_message);

							//Processing skip
							continue;
						}
					}
				}
				//tls_accept
				if (isset($data_array[$j]['property']['encryption']['tls_accept'])) {
					if (!empty($data_array[$j]['property']['encryption']['tls_accept'])) {
						if ($data_array[$j]['property']['encryption']['tls_accept'] === 'no') {
							$tls_accept = '1';
							$params['tls_accept'] = '1';
						}
						elseif ($data_array[$j]['property']['encryption']['tls_accept'] === 'psk') {
							$tls_accept = '2';
							$params['tls_accept'] = '2';
						}
						elseif ($data_array[$j]['property']['encryption']['tls_accept'] === 'no,psk') {
							$tls_accept = '3';
							$params['tls_accept'] = '3';
						}
						elseif ($data_array[$j]['property']['encryption']['tls_accept'] === 'certificate') {
							$tls_accept = '4';
							$params['tls_accept'] = '4';
						}
						elseif ($data_array[$j]['property']['encryption']['tls_accept'] === 'no,certificate') {
							$tls_accept = '5';
							$params['tls_accept'] = '5';
						}
						elseif ($data_array[$j]['property']['encryption']['tls_accept'] === 'psk,certificate') {
							$tls_accept = '6';
							$params['tls_accept'] = '6';
						}
						elseif ($data_array[$j]['property']['encryption']['tls_accept'] === 'no,psk,certificate') {
							$tls_accept = '7';
							$params['tls_accept'] = '7';
						}
						else {
							//Error count
							$error_count = $error_count+1;
							processing_status_display($i+1, $count, $error_count);

							//Error message output
							$error_message = "[ERROR] "
								. "message:\"" . "encryption tls_accept is missmatched." . "\", "
								. "file:\"" . $file_list[$i] . "\", "
								. "data:\"" . $data_array[$j]['property']['encryption']['tls_accept'] . "\"";
							log_output($log_file, $error_message);

							//Processing skip
							continue;
						}
					}
				}

				//psk
				if ($tls_connect === '2' || $tls_accept === '2' || $tls_accept === '3' || $tls_accept === '6' || $tls_accept === '7') {
					if (!isset($data_array[$j]['property']['encryption']['tls_psk_identity']) || !isset($data_array[$j]['property']['encryption']['tls_psk'])) {
						//Error count
						$error_count = $error_count+1;
						processing_status_display($i+1, $count, $error_count);

						//Error message output
						$error_message = "[ERROR] "
							. "message:\"" . "enctription tls_psk is not set." . "\", "
							. "file:\"" . $file_list[$i] . "\"";
						log_output($log_file, $error_message);

						//Processing skip
						continue ;
					}
					else {
						//tls_psk_identity
						if (!empty($data_array[$j]['property']['encryption']['tls_psk_identity'])) {
							$tls_psk_identity = $data_array[$j]['property']['encryption']['tls_psk_identity'];
							$params['tls_psk_identity'] = $data_array[$j]['property']['encryption']['tls_psk_identity'];
						}
						//tls_psk
						if (!empty($data_array[$j]['property']['encryption']['tls_psk'])) {
							$tls_psk = $data_array[$j]['property']['encryption']['tls_psk'];
							$params['tls_psk'] = $data_array[$j]['property']['encryption']['tls_psk'];
						}
					}
				}
				//cert
				if ($tls_connect === '4' || $tls_accept === '4' || $tls_accept === '5' || $tls_accept === '6' || $tls_accept === '7') {
					if (!isset($data_array[$j]['property']['encryption']['tls_issuer']) || !isset($data_array[$j]['property']['encryption']['tls_subject'])) {
						//Error count
						$error_count = $error_count+1;
						processing_status_display($i+1, $count, $error_count);

						//Error message output
						$error_message = "[ERROR] "
							. "message:\"" . "enctription tls_issuer or tls_subject is not set." . "\", "
							. "file:\"" . $file_list[$i] . "\"";
						log_output($log_file, $error_message);

						//Processing skip
						continue ;
					}
					else {
						//tls_issuer
						if (!empty($data_array[$j]['property']['encryption']['tls_issuer'])) {
							$tls_issuer = $data_array[$j]['property']['encryption']['tls_issuer'];
							$params['tls_issuer'] = $data_array[$j]['property']['encryption']['tls_issuer'];
						}
						//tls_subject
						if (!empty($data_array[$j]['property']['encryption']['tls_subject'])) {
							$tls_subject = $data_array[$j]['property']['encryption']['tls_subject'];
							$params['tls_subject'] = $data_array[$j]['property']['encryption']['tls_subject'];
						}
					}
				}
			}

			//ipmi
			if (isset($data_array[$j]['property']['ipmi'])) {
				//ipmi_authtype
				if (isset($data_array[$j]['property']['ipmi']['authtype'])) {
					if (!empty($data_array[$j]['property']['ipmi']['authtype'])) {
						if ($data_array[$j]['property']['ipmi']['authtype'] === 'default') {
							$params['ipmi_authtype'] = '-1';
						}
						elseif ($data_array[$j]['property']['ipmi']['authtype'] === 'none') {
							$params['ipmi_authtype'] = '0';
						}
						elseif ($data_array[$j]['property']['ipmi']['authtype'] === 'MD2') {
							$params['ipmi_authtype'] = '1';
						}
						elseif ($data_array[$j]['property']['ipmi']['authtype'] === 'MD5') {
							$params['ipmi_authtype'] = '2';
						}
						elseif ($data_array[$j]['property']['ipmi']['authtype'] === 'straight') {
							$params['ipmi_authtype'] = '4';
						}
						elseif ($data_array[$j]['property']['ipmi']['authtype'] === 'OEM') {
							$params['ipmi_authtype'] = '5';
						}
						elseif ($data_array[$j]['property']['ipmi']['authtype'] === 'RMCP') {
							$params['ipmi_authtype'] = '6';
						}
						else {
							//Error count
							$error_count = $error_count+1;
							processing_status_display($i+1, $count, $error_count);

							//Error message output
							$error_message = "[ERROR] "
								. "message:\"" . "ipmi authtype is missmatched." . "\", "
								. "file:\"" . $file_list[$i] . "\", "
								. "data:\"" . $data_array[$j]['property']['ipmi']['authtype'] . "\"";
							log_output($log_file, $error_message);

							//Processing skip
							continue;
						}
					}
				}
				//ipmi_privilege
				if (isset($data_array[$j]['property']['ipmi']['privilege'])) {
					if (!empty($data_array[$j]['property']['ipmi']['privilege'])) {
						if ($data_array[$j]['property']['ipmi']['privilege'] === 'callback') {
							$params['ipmi_privilege'] = '1';
						}
						elseif ($data_array[$j]['property']['ipmi']['privilege'] === 'user') {
							$params['ipmi_privilege'] = '2';
						}
						elseif ($data_array[$j]['property']['ipmi']['privilege'] === 'operator') {
							$params['ipmi_privilege'] = '3';
						}
						elseif ($data_array[$j]['property']['ipmi']['privilege'] === 'admin') {
							$params['ipmi_privilege'] = '4';
						}
						elseif ($data_array[$j]['property']['ipmi']['privilege'] === 'OEM') {
							$params['ipmi_privilege'] = '5';
						}
						else {
							//Error count
							$error_count = $error_count+1;
							processing_status_display($i+1, $count, $error_count);

							//Error message output
							$error_message = "[ERROR] "
								. "message:\"" . "ipmi privilege is missmatched." . "\", "
								. "file:\"" . $file_list[$i] . "\", "
								. "data:\"" . $data_array[$j]['property']['ipmi']['privilege'] . "\"";
							log_output($log_file, $error_message);

							//Processing skip
							continue;
						}
					}
				}
				//ipmi_password
				if (isset($data_array[$j]['property']['ipmi']['password'])) {
					if (!empty($data_array[$j]['property']['ipmi']['password'])) {
						$params['ipmi_password'] = $data_array[$j]['property']['ipmi']['password'];
					}
				}
				//ipmi_username
				if (isset($data_array[$j]['property']['ipmi']['username'])) {
					if (!empty($data_array[$j]['property']['ipmi']['username'])) {
						$params['ipmi_username'] = $data_array[$j]['property']['ipmi']['username'];
					}
				}
			}
		}
		else {
			//Error count
			$error_count = $error_count+1;
			processing_status_display($i+1, $count, $error_count);

			//Error message output
			$error_message = "[ERROR] "
				. "message:\"" . "property is not set." . "\", "
				. "file:\"" . $file_list[$i] . "\",";
			log_output($log_file, $error_message);

			//Processing skip
			continue;
		}

		//API Request
		$method = 'host.update';
		$response = api_request($method, $params, $auth, 'replace');

		if (isset($response['error'])) {
			//Error count
			$error_count = $error_count+1;
			processing_status_display($i+1, $count, $error_count);

			//Error message output
			$error_message = error_message($response);
			log_output($log_file, $error_message);

			//Processing skip
			continue;
		}
	}
}

//Display adjustment
echo "\n";

//API logout
api_logout($auth);
curl_close($ch);

////////////////////////////////////////////////////////////////////////////////
//	Get Function
////////////////////////////////////////////////////////////////////////////////
//Host id
function get_hostid($host) {
	global $auth;
	global $f;
	
	$method = 'host.get';
	$params = array(
		'output' => array(
			'hostid'
		),
		'filter' => array(
			'host' => $host
		)
	);
	$response = api_request($method, $params, $auth, '');
	
	if (isset($response['result'])) {
		if (isset($response['result'][0]['hostid'])) {
			return $response['result'][0]['hostid'];
		}
		else {
			return null;
		}
	}
	elseif (isset($response['error'])) {
		$error_message = error_message($response);
		log_output($log_file, $error_message);
		return null;
	}
	else {
		$error_message = error_message_unexpected($response);
		log_output($log_file, $error_message);
		return null;
	}
}

//Proxy id
function get_proxyid($host) {
	global $auth;
	global $f;
	
	$method = 'proxy.get';
	$params = array(
		'output' => array(
			'proxyid',
		),
		'filter' => array(
			'host' => $host
		)
	);
	$response = api_request($method, $params, $auth, '');
	
	if (isset($response['result'])) {
		if (isset($response['result'][0]['proxyid'])) {
			return $response['result'][0]['proxyid'];
		}
		else {
			return null;
		}
	}
	elseif (isset($response['error'])) {
		$error_message = error_message($response);
		log_output($log_file, $error_message);
		return null;
	}
	else {
		$error_message = error_message_unexpected($response);
		log_output($log_file, $error_message);
		return null;
	}
}

?>
