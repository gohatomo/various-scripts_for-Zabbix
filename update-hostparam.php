#!/usr/bin/php
<?php
///////////////////////////////////////////////////////////////////////////////////
//	Name: update-hostparam.php
//	Description: Update standard property and related object(host group,template,tag,macro,interface,inventory) to host.
///////////////////////////////////////////////////////////////////////////////////

//Require
require_once 'conf/config.php';
require_once 'common_function.php';
require_once 'zbx_define.php';

//Setting timezone
date_default_timezone_set("$timezone");

//Log file settings
$date = date("Ymd");

$log_file = rtrim($log_dir, '/') . "/update-hostparam_{$date}.log";
if (!file_exists($log_dir)) {
	mkdir($log_dir, 0755);
}

//Datafile get
$data_dir = rtrim($data_dir, '/') . "/update-hostparam";

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
$array_templates = array();
$array_groups = array();

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
					$status = array_search($data_array[$j]['property']['status'], C_STATUS);

					if (is_null($status)) {
						$error_count = $error_count+1;
						$error_message = "[ERROR] "
							. "message:\"" . "status is missmatched." . "\", "
							. "file:\"" . $file_list[$i] . "\", "
							. "data:\"" . $data_array[$j]['property']['status'] . "\"";
						log_output($log_file, $error_message);
						continue;
					}
					else {
						$params['status'] = $status;
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

			//groups
			if (isset($data_array[$j]['property']['groups'][0])) {
				$count_groups = count($data_array[$j]['property']['groups']);
				for ($k = 0; $k < $count_groups; ++$k) {
					if (in_array($data_array[$j]['property']['groups'][$k], $array_groups)) {
						$groupid = array_search($data_array[$j]['property']['groups'][$k], $array_groups);
						$params['groups'][] = array(
							'groupid' => $groupid
						);
					}
					else {
						$groupid = get_groupid($data_array[$j]['property']['groups'][$k]);

						if (!is_null($groupid)) {
							$array_groups[$groupid] = $data_array[$j]['property']['groups'][$k];
							$params['groups'][] = array(
								'groupid' => $groupid
							);
						}
						else {
							//Error count
							$error_count = $error_count+1;
							processing_status_display($i+1, $count, $error_count);

							//Error message output
							$error_message = "[ERROR] "
								. "message:\"" . "groups is missmatched." . "\", "
								. "file:\"" . $file_list[$i] . "\", "
								. "data:\"" . $data_array[$j]['property']['groups'][$k] . "\"";
							log_output($log_file, $error_message);

							//Processing skip
							continue 2;
						}
					}
				}
			}

			//templates
			if (isset($data_array[$j]['property']['templates'][0])) {
				$count_templates = count($data_array[$j]['property']['templates']);
				for ($k = 0; $k < $count_templates; ++$k) {
					if (in_array($data_array[$j]['property']['templates'][$k], $array_templates)) {
						$templateid = array_search($data_array[$j]['property']['templates'][$k], $array_templates);
						$params['templates'][] = array(
							'templateid' => $templateid
						);
					}
					else {
						$templateid = get_templateid($data_array[$j]['property']['templates'][$k]);
						if (!is_null($templateid)) {
							$array_templates[$templateid] = $data_array[$j]['property']['templates'][$k];
							$params['templates'][] = array(
								'templateid' => $templateid
							);
						}
						else {
							//Error count
							$error_count = $error_count+1;
							processing_status_display($i+1, $count, $error_count);

							//Error message output
							$error_message = "[ERROR] "
								. "message:\"" . "templates is missmatched." . "\", "
								. "file:\"" . $file_list[$i] . "\", "
								. "data:\"" . $data_array[$j]['property']['templates'][$k] . "\"";
							log_output($log_file, $error_message);

							//Processing skip
							continue 2;
						}
					}
				}
			}

			//tags
			if (isset($data_array[$j]['property']['tags'][0])) {
				$count_tags = count($data_array[$j]['property']['tags']);
				for ($k = 0; $k < $count_tags; ++$k) {
					if (isset($data_array[$j]['property']['tags'][$k]['tag']) 
						&& isset($data_array[$j]['property']['tags'][$k]['value'])) {
						
						if (!empty($data_array[$j]['property']['tags'][$k]['tag'])) {
							$params['tags'][] = array(
								'tag' => $data_array[$j]['property']['tags'][$k]['tag'],
								'value' => $data_array[$j]['property']['tags'][$k]['value']
							);
						}
					}
					else {
						//Error count
						$error_count = $error_count+1;
						processing_status_display($i+1, $count, $error_count);

						//Error message output
						$error_message = "[ERROR] "
							. "message:\"" . "tags is not set." . "\", "
							. "file:\"" . $file_list[$i] . "\"";
						log_output($log_file, $error_message);

						//Processing skip
						continue 2;
					}
				}
			}

			//macros
			if (isset($data_array[$j]['property']['macros'][0])) {
				$count_macros = count($data_array[$j]['property']['macros']);
				for ($k = 0; $k < $count_macros; ++$k) {
					if (isset($data_array[$j]['property']['macros'][$k]['macro']) && isset($data_array[$j]['property']['macros'][$k]['value']) 
						&& isset($data_array[$j]['property']['macros'][$k]['type']) && isset($data_array[$j]['property']['macros'][$k]['description'])) {
						
						if (!empty($data_array[$j]['property']['macros'][$k]['macro'])) {
							$macro_type = array_search($data_array[$j]['property']['macros'][$k]['type'], MACRO_TYPE);

							if (is_null($macro_type)) {
								//Error count
								$error_count = $error_count+1;
								processing_status_display($i+1, $count, $error_count);
								
								//Error message output
								$error_message = "[ERROR] "
									. "message:\"" . "macros type is missmatched." . "\", "
									. "file:\"" . $file_list[$i] . "\", "
									. "data:\"" . $data_array[$j]['property']['macros'][$k]['type'] . "\"";
								log_output($log_file, $error_message);
								
								//Processing skip
								continue 2;
							}
							$params['macros'][] = array(
								'macro' => $data_array[$j]['property']['macros'][$k]['macro'],
								'value' => $data_array[$j]['property']['macros'][$k]['value'],
								'type' => $macro_type,
								'description' => $data_array[$j]['property']['macros'][$k]['description']
							);
						}
					}
					else {
						//Error count
						$error_count = $error_count+1;
						processing_status_display($i+1, $count, $error_count);

						//Error message output
						$error_message = "[ERROR] "
							. "message:\"" . "macros is not set." . "\", "
							. "file:\"" . $file_list[$i] . "\"";
						log_output($log_file, $error_message);

						//Processing skip
						continue 2;
					}
				}
			}

			//inventory_mode
			if (isset($data_array[$j]['property']['inventory']['mode'])) {
				if (!empty($data_array[$j]['property']['inventory']['mode'])) {
					$inventory_mode = array_search($data_array[$j]['property']['inventory']['mode'], INVENTORY_MODE);
					if (is_null($inventory_mode)) {
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
					else {
						$params['inventory_mode'] = $inventory_mode;
					}
				}
			}

			//inventory
			if (isset($data_array[$j]['property']['inventory']['inventory'][0])) {
				$count_inventory = count($data_array[$j]['property']['inventory']['inventory']);
				for ($k = 0; $k < $count_inventory; ++$k) {
					if (isset($data_array[$j]['property']['inventory']['inventory'][$k]['type']) && 
						isset($data_array[$j]['property']['inventory']['inventory'][$k]['value'])) {
						if (!empty($data_array[$j]['property']['inventory']['inventory'][$k]['type'])) {
							$params['inventory'][$data_array[$j]['property']['inventory']['inventory'][$k]['type']] = $data_array[$j]['property']['inventory']['inventory'][$k]['value'];
						}
					}
					else {
						//Error count
						$error_count = $error_count+1;
						processing_status_display($i+1, $count, $error_count);
						
						//Error message output
						$error_message = "[ERROR] "
							. "message:\"" . "inventory is not set." . "\", "
							. "file:\"" . $file_list[$i] . "\"";
						log_output($log_file, $error_message);
						
						//Processing skip
						continue 2;
					}
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
						$tls_connect = array_search($data_array[$j]['property']['encryption']['tls_connect'], C_TLC_CONNECT);
						if (is_null($tls_connect)) {
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
						else {
							$params['tls_connect'] = $tls_connect;
						}
					}
				}
				//tls_accept
				if (isset($data_array[$j]['property']['encryption']['tls_accept'])) {
					if (!empty($data_array[$j]['property']['encryption']['tls_accept'])) {
						$tls_accept = array_search($data_array[$j]['property']['encryption']['tls_accept'], C_TLC_ACCEPT);

						if (is_null($tls_accept)) {
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
						else {
							$params['tls_accept'] = $tls_accept;
						}
					}
				}

				//psk
				if ($tls_connect == '2' || $tls_accept == '2' || $tls_accept == '3' || $tls_accept == '6' || $tls_accept == '7') {
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
				if ($tls_connect == '4' || $tls_accept == '4' || $tls_accept == '5' || $tls_accept == '6' || $tls_accept == '7') {
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
						$ipmi_authtype = array_search($data_array[$j]['property']['ipmi']['authtype'], IPMI_AUTHTYPE);

						if (is_null($ipmi_authtype)) {
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
						else {
							$params['ipmi_authtype'] = $ipmi_authtype;
						}
					}
				}
				//ipmi_privilege
				if (isset($data_array[$j]['property']['ipmi']['privilege'])) {
					if (!empty($data_array[$j]['property']['ipmi']['privilege'])) {
						$ipmi_privilege = array_search($data_array[$j]['property']['ipmi']['privilege'], IPMI_PRIVILEGE);

						if (is_null($ipmi_privilege)) {
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
						else {
							$params['ipmi_privilege'] = $ipmi_privilege;
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

			//interfaces
			if (isset($data_array[$j]['property']['interfaces'][0])) {
				$count_interfaces = count($data_array[$j]['property']['interfaces']);
				for ($k = 0; $k < $count_interfaces; ++$k) {
					//Variable initialization
					$type = "";
					$main = "";
					$useip = "";
					$ip = "";
					$dns = "";
					$port = "";
					$version = "";
					$bulk = "";
					$community = "";
					$contextname = "";
					$securityname = "";
					$securitylevel = "";
					$authprotocol = "0";
					$authpassphrase = "";
					$privprotocol = "0";
					$privpassphrase = "";

					//type
					if (isset($data_array[$j]['property']['interfaces'][$k]['type'])) {
						$type = array_search($data_array[$j]['property']['interfaces'][$k]['type'], INTERFACE_TYPE);
						
						if (is_null($type)) {
							//Error count
							$error_count = $error_count+1;
							processing_status_display($i+1, $count, $error_count);

							//Error message output
							$error_message = "[ERROR] "
								. "message:\"" . "interfaces type is missmatched." . "\", "
								. "file:\"" . $file_list[$i] . "\", "
								. "data:\"" . $data_array[$j]['property']['interfaces'][$k]['type'] . "\"";
							log_output($log_file, $error_message);

							//Processing skip
							continue 2;
						}
					}
					else {
						//Error count
						$error_count = $error_count+1;
						processing_status_display($i+1, $count, $error_count);

						//Error message output
						$error_message = "[ERROR] "
							. "message:\"" . "interfaces type is not set." . "\", "
							. "file:\"" . $file_list[$i] . "\"";
						log_output($log_file, $error_message);

						//Processing skip
						continue 2;
					}

					//main
					if (isset($data_array[$j]['property']['interfaces'][$k]['main'])) {
						$main = array_search($data_array[$j]['property']['interfaces'][$k]['main'], INTERFACE_MAIN);
						
						if (is_null($main)) {
							//Error count
							$error_count = $error_count+1;
							processing_status_display($i+1, $count, $error_count);

							//Error message output
							$error_message = "[ERROR] "
								. "message:\"" . "interfaces main is missmatched." . "\", "
								. "file:\"" . $file_list[$i] . "\", "
								. "data:\"" . $data_array[$j]['property']['interfaces'][$k]['main'] . "\"";
							log_output($log_file, $error_message);

							//Processing skip
							continue 2;
						}
					}
					else {
						//Error count
						$error_count = $error_count+1;
						processing_status_display($i+1, $count, $error_count);

						//Error message output
						$error_message = "[ERROR] "
							. "message:\"" . "interfaces main is not set." . "\", "
							. "file:\"" . $file_list[$i] . "\"";
						log_output($log_file, $error_message);

						//Processing skip
						continue 2;
					}

					//useip
					if (isset($data_array[$j]['property']['interfaces'][$k]['useip'])) {
						$useip = array_search($data_array[$j]['property']['interfaces'][$k]['useip'], INTERFACE_USEIP);

						if (is_null($useip)) {
							//Error count
							$error_count = $error_count+1;
							processing_status_display($i+1, $count, $error_count);

							//Error message output
							$error_message = "[ERROR] "
								. "message:\"" . "interfaces useip is missmatched." . "\", "
								. "file:\"" . $file_list[$i] . "\", "
								. "data:\"" . $data_array[$j]['property']['interfaces'][$k]['useip'] . "\"";
							log_output($log_file, $error_message);

							//Processing skip
							continue 2;
						}
					}
					else {
						//Error count
						$error_count = $error_count+1;
						processing_status_display($i+1, $count, $error_count);

						//Error message output
						$error_message = "[ERROR] "
							. "message:\"" . "interfaces useip is not set." . "\", "
							. "file:\"" . $file_list[$i] . "\"";
						log_output($log_file, $error_message);

						//Processing skip
						continue 2;
					}

					//ip
					if (isset($data_array[$j]['property']['interfaces'][$k]['ip'])) {
						if (empty($data_array[$j]['property']['interfaces'][$k]['ip'])) {
							if ($useip == '0') {
								$ip = $data_array[$j]['property']['interfaces'][$k]['ip'];
							}
							else {
								//Error count
								$error_count = $error_count+1;
								processing_status_display($i+1, $count, $error_count);

								//Error message output
								$error_message = "[ERROR] interfaces ip is missmatched."
									. "file:\"" . $file_list[$i] . "\", "
									. "data:\"" . $data_array[$j]['property']['interfaces'][$k]['ip'] . "\"";
								log_output($log_file, $error_message);

								//Processing skip
								continue 2;
							}
						}
						else {
							$ip = $data_array[$j]['property']['interfaces'][$k]['ip'];
						}
					}
					else {
						//Error count
						$error_count = $error_count+1;
						processing_status_display($i+1, $count, $error_count);

						//Error message output
						$error_message = "[ERROR] "
							. "message:\"" . "interfaces ip is not set." . "\", "
							. "file:\"" . $file_list[$i] . "\"";
						log_output($log_file, $error_message);

						//Processing skip
						continue 2;
					}

					//dns
					if (isset($data_array[$j]['property']['interfaces'][$k]['dns'])) {
						if (empty($data_array[$j]['property']['interfaces'][$k]['dns'])) {
							if ($useip == '1') {
								$dns = $data_array[$j]['property']['interfaces'][$k]['dns'];
							}
							else {
								//Error count
								$error_count = $error_count+1;
								processing_status_display($i+1, $count, $error_count);

								//Error message output
								$error_message = "[ERROR] "
									. "message:\"" . "interfaces dns is missmatched." . "\", "
									. "file:\"" . $file_list[$i] . "\", "
									. "data:\"" . $data_array[$j]['property']['interfaces'][$k]['dns'] . "\"";
								log_output($log_file, $error_message);

								//Processing skip
								continue 2;
							}
						}
						else {
							$dns = $data_array[$j]['property']['interfaces'][$k]['dns'];
						}
					}
					else {
						//Error count
						$error_count = $error_count+1;
						processing_status_display($i+1, $count, $error_count);

						//Error message output
						$error_message = "[ERROR] "
							. "message:\"" . "interfaces dns is not set." . "\", "
							. "file:\"" . $file_list[$i] . "\"";
						log_output($log_file, $error_message);

						//Processing skip
						continue 2;
					}

					//port
					if (isset($data_array[$j]['property']['interfaces'][$k]['port'])) {
						$port = $data_array[$j]['property']['interfaces'][$k]['port'];
					}
					else {
						//Error count
						$error_count = $error_count+1;
						processing_status_display($i+1, $count, $error_count);

						//Error message output
						$error_message = "[ERROR] "
							. "message:\"" . "interfaces port is not set." . "\", "
							. "file:\"" . $file_list[$i] . "\"";
						log_output($log_file, $error_message);

						//Processing skip
						continue 2;
					}

					//details
					if ($type == '2') {
						if (isset($data_array[$j]['property']['interfaces'][$k]['details'])) {
							//version
							if (isset($data_array[$j]['property']['interfaces'][$k]['details']['version'])) {
								if ($data_array[$j]['property']['interfaces'][$k]['details']['version'] == '1' ||
									$data_array[$j]['property']['interfaces'][$k]['details']['version'] == '2' ||
									$data_array[$j]['property']['interfaces'][$k]['details']['version'] == '3') {
									
									$version = $data_array[$j]['property']['interfaces'][$k]['details']['version'];
								}
								else {
									//Error count
									$error_count = $error_count+1;
									processing_status_display($i+1, $count, $error_count);

									//Error message output
									$error_message = "[ERROR] "
										. "message:\"" . "interfaces snmp version is missmatched." . "\", "
										. "file:\"" . $file_list[$i] . "\", "
										. "data:\"" . $data_array[$j]['property']['interfaces'][$k]['details']['version'] . "\"";
									log_output($log_file, $error_message);

									//Processing skip
									continue 2;
								}
							}
							else {
								//Error count
								$error_count = $error_count+1;
								processing_status_display($i+1, $count, $error_count);

								//Error message output
								$error_message = "[ERROR] "
									. "message:\"" . "interfaces snmp version is not set." . "\", "
									. "file:\"" . $file_list[$i] . "\"";
								log_output($log_file, $error_message);

								//Processing skip
								continue 2;
							}

							//bulk
							if (isset($data_array[$j]['property']['interfaces'][$k]['details']['bulk'])) {
								$bulk = array_search($data_array[$j]['property']['interfaces'][$k]['details']['bulk'], INTERFACE_BULK);

								if (is_null($bulk)) {
									//Error count
									$error_count = $error_count+1;
									processing_status_display($i+1, $count, $error_count);

									//Error message output
									$error_message = "[ERROR] "
										. "message:\"" . "interfaces snmp bulk is missmatched." . "\", "
										. "file:\"" . $file_list[$i] . "\", "
										. "data:\"" . $data_array[$j]['property']['interfaces'][$k]['details']['bulk'] . "\"";
									log_output($log_file, $error_message);

									//Processing skip
									continue 2;
								}
							}
							else {
								$bulk = '1';
							}

							//Settings for each version
							if ($version == '1' || $version == '2') {
								//community
								if (isset($data_array[$j]['property']['interfaces'][$k]['details']['community'])) {
									if (!empty($data_array[$j]['property']['interfaces'][$k]['details']['community'])) {
										$community = $data_array[$j]['property']['interfaces'][$k]['details']['community'];
									}
									else {
										//Error count
										$error_count = $error_count+1;
										processing_status_display($i+1, $count, $error_count);

										//Error message output
										$error_message = "[ERROR] "
											. "message:\"" . "interfaces snmp community is missmatched." . "\", "
											. "file:\"" . $file_list[$i] . "\", "
											. "data:\"" . $data_array[$j]['property']['interfaces'][$k]['details']['community'] . "\"";
										log_output($log_file, $error_message);

										//Processing skip
										continue 2;
									}
								}
								else {
									//Error count
									$error_count = $error_count+1;
									processing_status_display($i+1, $count, $error_count);

									//Error message output
									$error_message = "[ERROR] "
										. "message:\"" . "interfaces snmp community is not set." . "\", "
										. "file:\"" . $file_list[$i] . "\"";
									log_output($log_file, $error_message);

									//Processing skip
									continue 2;
								}
							}
							if ($version == '3') {
								//contextname
								if (isset($data_array[$j]['property']['interfaces'][$k]['details']['contextname'])) {
									$contextname = $data_array[$j]['property']['interfaces'][$k]['details']['contextname'];
								}
								else {
									//Error count
									$error_count = $error_count+1;
									processing_status_display($i+1, $count, $error_count);

									//Error message output
									$error_message = "[ERROR] "
										. "message:\"" . "interfaces snmp contextname is not set." . "\", "
										. "file:\"" . $file_list[$i] . "\"";
									log_output($log_file, $error_message);

									//Processing skip
									continue 2;
								}

								//securityname
								if (isset($data_array[$j]['property']['interfaces'][$k]['details']['securityname'])) {
									$securityname = $data_array[$j]['property']['interfaces'][$k]['details']['securityname'];
								}
								else {
									//Error count
									$error_count = $error_count+1;
									processing_status_display($i+1, $count, $error_count);

									//Error message output
									$error_message = "[ERROR] "
										. "message:\"" . "interfaces snmp securityname is not set." . "\", "
										. "file:\"" . $file_list[$i] . "\"";
									log_output($log_file, $error_message);

									//Processing skip
									continue 2;
								}

								//securitylevel
								if (isset($data_array[$j]['property']['interfaces'][$k]['details']['securitylevel'])) {
									$securitylevel = array_search($data_array[$j]['property']['interfaces'][$k]['details']['securitylevel'], C_SECURITY_LEVEL);
									
									if (is_null($securitylevel)) {
										//Error count
										$error_count = $error_count+1;
										processing_status_display($i+1, $count, $error_count);

										//Error message output
										$error_message = "[ERROR] "
											. "message:\"" . "interfaces snmp securitylevel is missmatched." . "\", "
											. "file:\"" . $file_list[$i] . "\", "
											. "data:\"" . $data_array[$j]['property']['interfaces'][$k]['details']['securitylevel'] . "\"";
										log_output($log_file, $error_message);

										//Processing skip
										continue 2;
									}
								}
								else {
									//Error count
									$error_count = $error_count+1;
									processing_status_display($i+1, $count, $error_count);

									//Error message output
									$error_message = "[ERROR] "
										. "message:\"" . "interfaces snmp securitylevel is not set." . "\", "
										. "file:\"" . $file_list[$i] . "\"";
									log_output($log_file, $error_message);

									//Processing skip
									continue 2;
								}

								if ($securitylevel === '1' || $securitylevel === '2') {
									//authprotocol
									if (isset($data_array[$j]['property']['interfaces'][$k]['details']['authprotocol'])) {
										$authprotocol = array_search($data_array[$j]['property']['interfaces'][$k]['details']['authprotocol'], C_AUTHPROTOCOL);
										
										if (is_null($authprotocol)) {
											//Error count
											$error_count = $error_count+1;
											processing_status_display($i+1, $count, $error_count);

											//Error message output
											$error_message = "[ERROR] "
												. "message:\"" . "interfaces snmp authprotocol is missmatched." . "\", "
												. "file:\"" . $file_list[$i] . "\", "
												. "data:\"" . $data_array[$j]['property']['interfaces'][$k]['details']['authprotocol'] . "\"";
											log_output($log_file, $error_message);

											//Processing skip
											continue 2;
										}
									}
									else {
										//Error count
										$error_count = $error_count+1;
										processing_status_display($i+1, $count, $error_count);

										//Error message output
										$error_message = "[ERROR] "
											. "message:\"" . "interfaces snmp authprotocol is not set." . "\", "
											. "file:\"" . $file_list[$i] . "\"";
										log_output($log_file, $error_message);

										//Processing skip
										continue 2;
									}

									//authpassphrase
									if (isset($data_array[$j]['property']['interfaces'][$k]['details']['authpassphrase'])) {
										$authpassphrase = $data_array[$j]['property']['interfaces'][$k]['details']['authpassphrase'];
									}
									else {
										//Error count
										$error_count = $error_count+1;
										processing_status_display($i+1, $count, $error_count);

										//Error message output
										$error_message = "[ERROR] "
											. "message:\"" . "interfaces snmp authpassphrase is not set." . "\", "
											. "file:\"" . $file_list[$i] . "\"";
										log_output($log_file, $error_message);

										//Processing skip
										continue 2;
									}
								}
								if ($securitylevel === '2') {
									//privprotocol
									if (isset($data_array[$j]['property']['interfaces'][$k]['details']['privprotocol'])) {
										$privprotocol = array_search($data_array[$j]['property']['interfaces'][$k]['details']['privprotocol'], C_PRIVPROTOCOL);

										if (is_null($privprotocol)) {
											//Error count
											$error_count = $error_count+1;
											processing_status_display($i+1, $count, $error_count);

											//Error message output
											$error_message = "[ERROR] "
												. "message:\"" . "interfaces snmp privprotocol is missmatched." . "\", "
												. "file:\"" . $file_list[$i] . "\", "
												. "data:\"" . $data_array[$j]['property']['interfaces'][$k]['details']['privprotocol'] . "\"";
											log_output($log_file, $error_message);

											//Processing skip
											continue 2;
										}
									}
									else {
										//Error count
										$error_count = $error_count+1;
										processing_status_display($i+1, $count, $error_count);

										//Error message output
										$error_message = "[ERROR] "
											. "message:\"" . "interfaces snmp privprotocol is not set." . "\", "
											. "file:\"" . $file_list[$i] . "\"";
										log_output($log_file, $error_message);

										//Processing skip
										continue 2;
									}

									//privpassphrase
									if (isset($data_array[$j]['property']['interfaces'][$k]['details']['privpassphrase'])) {
										$privpassphrase = $data_array[$j]['property']['interfaces'][$k]['details']['privpassphrase'];
									}
									else {
										//Error count
										$error_count = $error_count+1;
										processing_status_display($i+1, $count, $error_count);

										//Error message output
										$error_message = "[ERROR] "
											. "message:\"" . "interfaces snmp privpassphrase is not set." . "\", "
											. "file:\"" . $file_list[$i] . "\"";
										log_output($log_file, $error_message);

										//Processing skip
										continue 2;
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
								. "message:\"" . "interfaces detail is not set." . "\", "
								. "file:\"" . $file_list[$i] . "\"";
							log_output($log_file, $error_message);

							//Processing skip
							continue 2 ;
						}
					}
					$params['interfaces'][] = array(
						'type' => $type,
						'main' => $main,
						'useip' => $useip,
						'ip' => $ip,
						'dns' => $dns,
						'port' => $port,
						'details' => array(
							'version' => $version,
							'bulk' => $bulk,
							'community' => $community,
							'contextname' => $contextname,
							'securityname' => $securityname,
							'securitylevel' => $securitylevel,
							'authprotocol' => $authprotocol,
							'authpassphrase' => $authpassphrase,
							'privprotocol' => $privprotocol,
							'privpassphrase' => $privpassphrase
						)
					);
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

//Template id
function get_templateid($host) {
	global $auth;
	global $f;
	
	$method = 'template.get';
	$params = array(
		'output' => array(
			'templateid',
		),
		'filter' => array(
			'host' => $host
		)
	);
	$response = api_request($method, $params, $auth, '');
	
	if (isset($response['result'])) {
		if (isset($response['result'][0]['templateid'])) {
			return $response['result'][0]['templateid'];
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

//Hostgroup id
function get_groupid($name) {
	global $auth;
	global $f;
	
	$method = 'hostgroup.get';
	$params = array(
		'output' => array(
			'groupid',
		),
		'filter' => array(
			'name' => $name
		)
	);
	$response = api_request($method, $params, $auth, '');
	
	if (isset($response['result'])) {
		if (isset($response['result'][0]['groupid'])) {
			return $response['result'][0]['groupid'];
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
