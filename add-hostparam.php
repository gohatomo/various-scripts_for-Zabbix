#!/usr/bin/php
<?php
///////////////////////////////////////////////////////////////////////////////////
//	Name: add-hostparam.php
//	Description: Add hostgroup or template or macro or interface to host.
///////////////////////////////////////////////////////////////////////////////////

//Require
require_once 'conf/config.php';
require_once 'common_function.php';

//Setting timezone
date_default_timezone_set("$timezone");

//Log file settings
$date = date("Ymd");

$log_file = rtrim($log_dir, '/') . "/add-hostparam_{$date}.log";
if (!file_exists($log_dir)) {
	mkdir($log_dir, 0755);
}

//Datafile get
$data_dir = rtrim($data_dir, '/') . "/add-hostparam";

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

//Display adjustment
echo "\n";

//Add hostparam
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
		//hosts
		$hosts = array();

		if (isset($data_array[$j]['hosts'][0])) {
			$hosts = get_hostid($data_array[$j]['hosts']);

			if (is_null($hosts)) {
				//Error count
				$error_count = $error_count+1;
				processing_status_display($i+1, $count, $error_count);
				
				//Error message output
				$error_message = "[ERROR] "
					. "message:\"" . "hosts is missmatched." . "\", "
					. "file:\"" . $file_list[$i] . "\", "
					. "index:\"" . $j . "\"";
				log_output($log_file, $error_message);
				
				//Processing skip
				continue;
			}
		}
		else {
			//Error count
			$error_count = $error_count+1;
			processing_status_display($i+1, $count, $error_count);
			
			//Error message output
			$error_message = "[ERROR] "
				. "message:\"" . "hosts is not set." . "\", "
				. "file:\"" . $file_list[$i] . "\", "
				. "index:\"" . $j . "\"";
			log_output($log_file, $error_message);
			
			//Processing skip
			continue;
		}

		//hostgroups
		$hostgroups = array();

		if (isset($data_array[$j]['groups'][0])) {
			$hostgroups = get_hostgroupid($data_array[$j]['groups']);
			
			if (is_null($hostgroups)) {
				//Error count
				$error_count = $error_count+1;
				processing_status_display($i+1, $count, $error_count);
				
				//Error message output
				$error_message = "[ERROR] "
					. "message:\"" . "groups is missmatched." . "\", "
					. "file:\"" . $file_list[$i] . "\", "
					. "index:\"" . $j . "\"";
				log_output($log_file, $error_message);
				
				//Processing skip
				continue;
			}
		}

		//templates
		$templates = array();

		if (isset($data_array[$j]['templates'][0])) {
			$templates = get_templateid($data_array[$j]['templates']);
			
			if (is_null($templates)) {
				//Error count
				$error_count = $error_count+1;
				processing_status_display($i+1, $count, $error_count);
				
				//Error message output
				$error_message = "[ERROR] "
					. "message:\"" . "templates is missmatched." . "\", "
					. "file:\"" . $file_list[$i] . "\", "
					. "index:\"" . $j . "\"";
				log_output($log_file, $error_message);
				
				//Processing skip
				continue;
			}
		}

		//macros
		$macros = array();

		if (isset($data_array[$j]['macros'][0])) {
			$count_macro = count($data_array[$j]['macros']);

			for ($k = 0; $k < $count_macro; ++$k) {
				if (isset($data_array[$j]['macros'][$k]['macro']) && isset($data_array[$j]['macros'][$k]['value']) 
					&& isset($data_array[$j]['macros'][$k]['type']) && isset($data_array[$j]['macros'][$k]['description'])) {
					
					//macro type
					$macro_type = "";

					if ($data_array[$j]['macros'][$k]['type'] === 'text') {
						$macro_type = '0';
					}
					elseif ($data_array[$j]['macros'][$k]['type'] === 'secret') {
						$macro_type = '1';
					}
					elseif ($data_array[$j]['macros'][$k]['type'] === 'vault') {
						$macro_type = '2';
					}
					else {
						//Error count
						$error_count = $error_count+1;
						processing_status_display($i+1, $count, $error_count);
						
						//Error message output
						$error_message = "[ERROR] "
							. "message:\"" . "macro type is missmatched." . "\", "
							. "file:\"" . $file_list[$i] . "\", "
							. "index:\"" . $data_array[$j]['macros'][$k]['type'] . "\"";
						log_output($log_file, $error_message);
						
						//Processing skip
						continue 2;
					}

					$macros[] = array(
						'macro' => $data_array[$j]['macros'][$k]['macro'],
						'value' => $data_array[$j]['macros'][$k]['value'],
						'type' => $macro_type,
						'description' => $data_array[$j]['macros'][$k]['description']
					);
				}
				else {
					//Error count
					$error_count = $error_count+1;
					processing_status_display($i+1, $count, $error_count);
					
					//Error message output
					$error_message = "[ERROR] "
						. "message:\"" . "macro is missmatched." . "\", "
						. "file:\"" . $file_list[$i] . "\", "
						. "index:\"" . $data_array[$j]['macros'][$k]['type'] . "\"";
					log_output($log_file, $error_message);
					
					//Processing skip
					continue 2;	
				}
			}
		}

		//interfaces
		$interfaces = array();
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

		if (isset($data_array[$j]['interfaces'][0])) {
			$count_interfaces = count($data_array[$j]['interfaces']);
			for ($k = 0; $k < $count_interfaces; ++$k) {
				//type
				if (isset($data_array[$j]['interfaces'][$k]['type'])) {
					if ($data_array[$j]['interfaces'][$k]['type'] === 'agent') {
						$type = '1';
					}
					elseif ($data_array[$j]['interfaces'][$k]['type'] === 'snmp') {
						$type = '2';
					}
					elseif ($data_array[$j]['interfaces'][$k]['type'] === 'ipmi') {
						$type = '3';
					}
					elseif ($data_array[$j]['interfaces'][$k]['type'] === 'jmx') {
						$type = '4';
					}
					else {
						//Error count
						$error_count = $error_count+1;
						processing_status_display($i+1, $count, $error_count);
						
						//Error message output
						$error_message = "[ERROR] "
							. "message:\"" . "interfaces type is missmatched." . "\", "
							. "file:\"" . $file_list[$i] . "\", "
							. "data:\"" . $data_array[$j]['interfaces'][$k]['type'] . "\"";
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
				if (isset($data_array[$j]['interfaces'][$k]['main'])) {
					if ($data_array[$j]['interfaces'][$k]['main'] === 'default') {
						$main = '1';
					}
					else {
						$main = '0';
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
				if (isset($data_array[$j]['interfaces'][$k]['useip'])) {
					if ($data_array[$j]['interfaces'][$k]['useip'] === 'ip') {
						$useip = '1';
					}
					elseif ($data_array[$j]['interfaces'][$k]['useip'] === 'dns') {
						$useip = '0';
					}
					else {
						//Error count
						$error_count = $error_count+1;
						processing_status_display($i+1, $count, $error_count);
						
						//Error message output
						$error_message = "[ERROR] "
							. "message:\"" . "interfaces useip is missmatched." . "\", "
							. "file:\"" . $file_list[$i] . "\", "
							. "data:\"" . $data_array[$j]['interfaces'][$k]['useip'] . "\"";
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
				if (isset($data_array[$j]['interfaces'][$k]['ip'])) {
					if (empty($data_array[$j]['interfaces'][$k]['ip'])) {
						if ($useip === '0') {
							$ip = $data_array[$j]['interfaces'][$k]['ip'];
						}
						else {
							//Error count
							$error_count = $error_count+1;
							processing_status_display($i+1, $count, $error_count);
							
							//Error message output
							$error_message = "[ERROR] interfaces ip is missmatched."
								. "file:\"" . $file_list[$i] . "\", "
								. "data:\"" . $data_array[$j]['interfaces'][$k]['ip'] . "\"";
							log_output($log_file, $error_message);
							
							//Processing skip
							continue 2;
						}
					}
					else {
						$ip = $data_array[$j]['interfaces'][$k]['ip'];
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
				if (isset($data_array[$j]['interfaces'][$k]['dns'])) {
					if (empty($data_array[$j]['interfaces'][$k]['dns'])) {
						if ($useip === '1') {
							$dns = $data_array[$j]['interfaces'][$k]['dns'];
						}
						else {
							//Error count
							$error_count = $error_count+1;
							processing_status_display($i+1, $count, $error_count);
							
							//Error message output
							$error_message = "[ERROR] "
								. "message:\"" . "interfaces dns is missmatched." . "\", "
								. "file:\"" . $file_list[$i] . "\", "
								. "data:\"" . $data_array[$j]['interfaces'][$k]['dns'] . "\"";
							log_output($log_file, $error_message);
							
							//Processing skip
							continue 2;
						}
					}
					else {
						$dns = $data_array[$j]['interfaces'][$k]['dns'];
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
				if (isset($data_array[$j]['interfaces'][$k]['port'])) {
					$port = $data_array[$j]['interfaces'][$k]['port'];
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
				if ($type === '2') {
					if (isset($data_array[$j]['interfaces'][$k]['details'])) {
						//version
						if (isset($data_array[$j]['interfaces'][$k]['details']['version'])) {
							if ($data_array[$j]['interfaces'][$k]['details']['version'] === '1' ||
								$data_array[$j]['interfaces'][$k]['details']['version'] === '2' ||
								$data_array[$j]['interfaces'][$k]['details']['version'] === '3') {
								
								$version = $data_array[$j]['interfaces'][$k]['details']['version'];
							}
							else {
								//Error count
								$error_count = $error_count+1;
								processing_status_display($i+1, $count, $error_count);
								
								//Error message output
								$error_message = "[ERROR] "
									. "message:\"" . "interfaces snmp version is missmatched." . "\", "
									. "file:\"" . $file_list[$i] . "\", "
									. "data:\"" . $data_array[$j]['interfaces'][$k]['details']['version'] . "\"";
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
						if (isset($data_array[$j]['interfaces'][$k]['details']['bulk'])) {
							if ($data_array[$j]['interfaces'][$k]['details']['bulk'] === 'on') {
								$bulk = '1';
							}
							elseif ($data_array[$j]['interfaces'][$k]['details']['bulk'] === 'off') {
								$bulk = '0';
							}
							else {
								//Error count
								$error_count = $error_count+1;
								processing_status_display($i+1, $count, $error_count);
								
								//Error message output
								$error_message = "[ERROR] "
									. "message:\"" . "interfaces snmp bulk is missmatched." . "\", "
									. "file:\"" . $file_list[$i] . "\", "
									. "data:\"" . $data_array[$j]['interfaces'][$k]['details']['bulk'] . "\"";
								log_output($log_file, $error_message);
								
								//Processing skip
								continue 2;
							}
						}
						else {
							$bulk = '1';
						}
						
						//Settings for each version
						if ($version === '1' || $version === '2') {
							//community
							if (isset($data_array[$j]['interfaces'][$k]['details']['community'])) {
								if (!empty($data_array[$j]['interfaces'][$k]['details']['community'])) {
									$community = $data_array[$j]['interfaces'][$k]['details']['community'];
								}
								else {
									//Error count
									$error_count = $error_count+1;
									processing_status_display($i+1, $count, $error_count);
									
									//Error message output
									$error_message = "[ERROR] "
										. "message:\"" . "interfaces snmp community is missmatched." . "\", "
										. "file:\"" . $file_list[$i] . "\", "
										. "data:\"" . $data_array[$j]['interfaces'][$k]['details']['community'] . "\"";
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
						if ($version === '3') {
							//contextname
							if (isset($data_array[$j]['interfaces'][$k]['details']['contextname'])) {
								$contextname = $data_array[$j]['interfaces'][$k]['details']['contextname'];
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
							if (isset($data_array[$j]['interfaces'][$k]['details']['securityname'])) {
								$securityname = $data_array[$j]['interfaces'][$k]['details']['securityname'];
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
							if (isset($data_array[$j]['interfaces'][$k]['details']['securitylevel'])) {
								if ($data_array[$j]['interfaces'][$k]['details']['securitylevel'] === 'noAuthNoPriv') {
									$securitylevel = '0';
								}
								elseif ($data_array[$j]['interfaces'][$k]['details']['securitylevel'] === 'authNoPriv') {
									$securitylevel = '1';
								}
								elseif ($data_array[$j]['interfaces'][$k]['details']['securitylevel'] === 'authPriv') {
									$securitylevel = '2';
								}
								else {
									//Error count
									$error_count = $error_count+1;
									processing_status_display($i+1, $count, $error_count);
									
									//Error message output
									$error_message = "[ERROR] "
										. "message:\"" . "interfaces snmp securitylevel is missmatched." . "\", "
										. "file:\"" . $file_list[$i] . "\", "
										. "data:\"" . $data_array[$j]['interfaces'][$k]['details']['securitylevel'] . "\"";
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
								if (isset($data_array[$j]['interfaces'][$k]['details']['authprotocol'])) {
									if ($data_array[$j]['interfaces'][$k]['details']['authprotocol'] === 'MD5') {
										$authprotocol = '0';
									}
									elseif ($data_array[$j]['interfaces'][$k]['details']['authprotocol'] === 'SHA1') {
										$authprotocol = '1';
									}
									elseif ($data_array[$j]['interfaces'][$k]['details']['authprotocol'] === 'SHA224') {
										$authprotocol = '2';
									}
									elseif ($data_array[$j]['interfaces'][$k]['details']['authprotocol'] === 'SHA256') {
										$authprotocol = '3';
									}
									elseif ($data_array[$j]['interfaces'][$k]['details']['authprotocol'] === 'SHA384') {
										$authprotocol = '4';
									}
									elseif ($data_array[$j]['interfaces'][$k]['details']['authprotocol'] === 'SHA512') {
										$authprotocol = '5';
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
								if (isset($data_array[$j]['interfaces'][$k]['details']['authpassphrase'])) {
									$authpassphrase = $data_array[$j]['interfaces'][$k]['details']['authpassphrase'];
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
								if (isset($data_array[$j]['interfaces'][$k]['details']['privprotocol'])) {
									if ($data_array[$j]['interfaces'][$k]['details']['privprotocol'] === 'DES') {
										$privprotocol = '0';
									}
									elseif ($data_array[$j]['interfaces'][$k]['details']['privprotocol'] === 'AES128') {
										$privprotocol = '1';
									}
									elseif ($data_array[$j]['interfaces'][$k]['details']['privprotocol'] === 'AES192') {
										$privprotocol = '2';
									}
									elseif ($data_array[$j]['interfaces'][$k]['details']['privprotocol'] === 'AES256') {
										$privprotocol = '3';
									}
									elseif ($data_array[$j]['interfaces'][$k]['details']['privprotocol'] === 'AES192C') {
										$privprotocol = '4';
									}
									elseif ($data_array[$j]['interfaces'][$k]['details']['privprotocol'] === 'AES256C') {
										$privprotocol = '5';
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
								if (isset($data_array[$j]['interfaces'][$k]['details']['privpassphrase'])) {
									$privpassphrase = $data_array[$j]['interfaces'][$k]['details']['privpassphrase'];
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
				$interfaces[] = array(
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

		//API Request
		$method = 'host.massadd';
		$params = array(
			"hosts" => $hosts,
			"groups" => $hostgroups,
			"templates" => $templates,
			"macros" => $macros,
			"interfaces" => $interfaces
		);
		$response = api_request($method, $params, $auth, '');

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
			return $response['result'];
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
function get_hostgroupid($hostgroup) {
	global $auth;
	global $f;
	
	$method = 'hostgroup.get';
	$params = array(
		'output' => array(
			'groupid'
		),
		'filter' => array(
			'name' => $hostgroup
		)
	);
	$response = api_request($method, $params, $auth, '');
	
	if (isset($response['result'])) {
		if (isset($response['result'][0]['groupid'])) {
			return $response['result'];
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
function get_templateid($template) {
	global $auth;
	global $f;
	
	$method = 'template.get';
	$params = array(
		'output' => array(
			'templateid'
		),
		'filter' => array(
			'name' => $template
		)
	);
	$response = api_request($method, $params, $auth, '');
	
	if (isset($response['result'])) {
		if (isset($response['result'][0]['templateid'])) {
			return $response['result'];
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
