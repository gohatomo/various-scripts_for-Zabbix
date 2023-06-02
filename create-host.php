#!/usr/bin/php
<?php
///////////////////////////////////////////////////////////////////////////////////
//	Name: create-host.php
//	Description: Create a new host.
///////////////////////////////////////////////////////////////////////////////////

//Require
require_once 'conf/config.php';
require_once 'common_function.php';
require_once 'zbx_define.php';

//Setting timezone
date_default_timezone_set("$timezone");

//Log file settings
$date = date("Ymd");

$log_file = rtrim($log_dir, '/') . "/create-host_{$date}.log";
if (!file_exists($log_dir)) {
	mkdir($log_dir, 0755);
}

//Datafile get
$data_dir = rtrim($data_dir, '/') . "/create-host";

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

//Host create
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
	
	//hosname
	$host = "";
	
	if (isset($data_array['host'])) {
		$host = $data_array['host'];
	}
	else {
		//Error count
		$error_count = $error_count+1;
		processing_status_display($i+1, $count, $error_count);
		
		//Error message output
		$error_message = "[ERROR] "
			. "message:\"" . "host is not set." . "\", "
			. "file:\"" . $file_list[$i] . "\"";
		log_output($log_file, $error_message);
		
		//Processing skip
		continue;
	}
	
	//displayname
	$displayname = "";
	
	if (isset($data_array['name'])) {
		$displayname = $data_array['name'];
	}
	else {
		//Error count
		$error_count = $error_count+1;
		processing_status_display($i+1, $count, $error_count);
		
		//Error message output
		$error_message = "[ERROR] "
			. "message:\"" . "name is not set." . "\", "
			. "file:\"" . $file_list[$i] . "\"";
		log_output($log_file, $error_message);
		
		//Processing skip
		continue;
	}
	
	//status
	$status = "";
	
	if (isset($data_array['status'])) {
		$status = array_search($data_array['status'], C_STATUS);
		if (is_null($status)) {
			$error_count = $error_count+1;
			$error_message = "[ERROR] "
				. "message:\"" . "status is missmatched." . "\", "
				. "file:\"" . $file_list[$i] . "\", "
				. "data:\"" . $data_array['status'] . "\"";
			log_output($log_file, $error_message);
			continue;
		}
	}
	else {
		//Error count
		$error_count = $error_count+1;
		processing_status_display($i+1, $count, $error_count);
		
		//Error message output
		$error_message = "[ERROR] "
			. "message:\"" . "status is not set." . "\", "
			. "file:\"" . $file_list[$i] . "\"";
		log_output($log_file, $error_message);
		
		//Processing skip
		continue;
	}
	
	//proxy_hostid
	$proxy_hostid = "";
	
	if (isset($data_array['proxy'])) {
		if (!empty($data_array['proxy'])) {
			if (in_array($data_array['proxy'], $array_proxy)) {
				$proxy_hostid = array_search($data_array['proxy'], $array_proxy);
			}
			else {
				$proxy_hostid = get_proxyid($data_array['proxy']);
				if (!is_null($proxy_hostid)) {
					$array_proxy[$proxy_hostid] = $data_array['proxy'];
				}
				else {
					//Error count
					$error_count = $error_count+1;
					processing_status_display($i+1, $count, $error_count);
					
					//Error message output
					$error_message = "[ERROR] "
						. "message:\"" . "proxy is missmatched." . "\", "
						. "file:\"" . $file_list[$i] . "\", "
						. "data:\"" . $data_array['proxy'] . "\"";
					log_output($log_file, $error_message);
					
					//Processing skip
					continue;
				}
			}
		}
		else {
			$proxy_hostid = '0';
		}
	}
	else {
		$proxy_hostid = '0';
	}
	
	//description
	$description = "";
	
	if (isset($data_array['description'])) {
		$description = $data_array['description'];
	}
	else {
		$description = "";
	}
	
	//templates
	$templates = array();
	
	if (isset($data_array['templates'][0])) {
		$count_templates = count($data_array['templates']);
		for ($j = 0; $j < $count_templates; ++$j) {
			if (in_array($data_array['templates'][$j], $array_templates)) {
				$templateid = array_search($data_array['templates'][$j], $array_templates);
				$templates[] = array(
					'templateid' => $templateid
				);
			}
			else {
				$templateid = get_templateid($data_array['templates'][$j]);
				if (!is_null($templateid)) {
					$array_templates[$templateid] = $data_array['templates'][$j];
					$templates[] = array(
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
						. "data:\"" . $data_array['templates'][$j] . "\"";
					log_output($log_file, $error_message);
					
					//Processing skip
					continue 2;
				}
			}
		}
	}
	
	//groups
	$groups = array();
	
	if (isset($data_array['groups'][0])) {
		$count_groups = count($data_array['groups']);
		for ($j = 0; $j < $count_groups; ++$j) {
			if (in_array($data_array['groups'][$j], $array_groups)) {
				$groupid = array_search($data_array['groups'][$j], $array_groups);
				$groups[] = array(
					'groupid' => $groupid
				);
			}
			else {
				$groupid = get_groupid($data_array['groups'][$j]);
				
				if (!is_null($groupid)) {
					$array_groups[$groupid] = $data_array['groups'][$j];
					$groups[] = array(
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
						. "data:\"" . $data_array['groups'][$j] . "\"";
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
			. "message:\"" . "groups is not set." . "\", "
			. "file:\"" . $file_list[$i] . "\"";
		log_output($log_file, $error_message);
		
		//Processing skip
		continue;
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
	
	if (isset($data_array['interfaces'][0])) {
		$count_interfaces = count($data_array['interfaces']);
		for ($j = 0; $j < $count_interfaces; ++$j) {
			//type
			if (isset($data_array['interfaces'][$j]['type'])) {
				$type = array_search($data_array['interfaces'][$j]['type'], INTERFACE_TYPE);
				if (is_null($type)) {
					//Error count
					$error_count = $error_count+1;
					processing_status_display($i+1, $count, $error_count);
					
					//Error message output
					$error_message = "[ERROR] "
						. "message:\"" . "interfaces type is missmatched." . "\", "
						. "file:\"" . $file_list[$i] . "\", "
						. "data:\"" . $data_array['interfaces'][$j]['type'] . "\"";
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
			if (isset($data_array['interfaces'][$j]['main'])) {
				$main = array_search($data_array['interfaces'][$j]['main'], INTERFACE_MAIN);
				if (is_null($main)) {
					//Error count
					$error_count = $error_count+1;
					processing_status_display($i+1, $count, $error_count);
					
					//Error message output
					$error_message = "[ERROR] "
						. "message:\"" . "interfaces main is missmatched." . "\", "
						. "file:\"" . $file_list[$i] . "\", "
						. "data:\"" . $data_array['interfaces'][$j]['main'] . "\"";
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
			if (isset($data_array['interfaces'][$j]['useip'])) {
				$useip = array_search($data_array['interfaces'][$j]['useip'], INTERFACE_USEIP);
				if (is_null($useip)) {
					//Error count
					$error_count = $error_count+1;
					processing_status_display($i+1, $count, $error_count);
					
					//Error message output
					$error_message = "[ERROR] "
						. "message:\"" . "interfaces useip is missmatched." . "\", "
						. "file:\"" . $file_list[$i] . "\", "
						. "data:\"" . $data_array['interfaces'][$j]['useip'] . "\"";
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
			if (isset($data_array['interfaces'][$j]['ip'])) {
				if (is_null($data_array['interfaces'][$j]['ip'])) {
					if ($useip == '0') {
						$ip = $data_array['interfaces'][$j]['ip'];
					}
					else {
						//Error count
						$error_count = $error_count+1;
						processing_status_display($i+1, $count, $error_count);
						
						//Error message output
						$error_message = "[ERROR] interfaces ip is missmatched."
							. "file:\"" . $file_list[$i] . "\", "
							. "data:\"" . $data_array['interfaces'][$j]['ip'] . "\"";
						log_output($log_file, $error_message);
						
						//Processing skip
						continue 2;
					}
				}
				else {
					$ip = $data_array['interfaces'][$j]['ip'];
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
			if (isset($data_array['interfaces'][$j]['dns'])) {
				if (is_null($data_array['interfaces'][$j]['dns'])) {
					if ($useip == '1') {
						$dns = $data_array['interfaces'][$j]['dns'];
					}
					else {
						//Error count
						$error_count = $error_count+1;
						processing_status_display($i+1, $count, $error_count);
						
						//Error message output
						$error_message = "[ERROR] "
							. "message:\"" . "interfaces dns is missmatched." . "\", "
							. "file:\"" . $file_list[$i] . "\", "
							. "data:\"" . $data_array['interfaces'][$j]['dns'] . "\"";
						log_output($log_file, $error_message);
						
						//Processing skip
						continue 2;
					}
				}
				else {
					$dns = $data_array['interfaces'][$j]['dns'];
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
			if (isset($data_array['interfaces'][$j]['port'])) {
				$port = $data_array['interfaces'][$j]['port'];
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
				if (isset($data_array['interfaces'][$j]['details'])) {
					//version
					if (isset($data_array['interfaces'][$j]['details']['version'])) {
						if ($data_array['interfaces'][$j]['details']['version'] == '1' ||
							$data_array['interfaces'][$j]['details']['version'] == '2' ||
							$data_array['interfaces'][$j]['details']['version'] == '3') {
							
							$version = $data_array['interfaces'][$j]['details']['version'];
						}
						else {
							//Error count
							$error_count = $error_count+1;
							processing_status_display($i+1, $count, $error_count);
							
							//Error message output
							$error_message = "[ERROR] "
								. "message:\"" . "interfaces snmp version is missmatched." . "\", "
								. "file:\"" . $file_list[$i] . "\", "
								. "data:\"" . $data_array['interfaces'][$j]['details']['version'] . "\"";
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
					if (isset($data_array['interfaces'][$j]['details']['bulk'])) {
						$bulk = array_search($data_array['interfaces'][$j]['details']['bulk'], INTERFACE_BULK);
						if (is_null($bulk)) {
							//Error count
							$error_count = $error_count+1;
							processing_status_display($i+1, $count, $error_count);
							
							//Error message output
							$error_message = "[ERROR] "
								. "message:\"" . "interfaces snmp bulk is missmatched." . "\", "
								. "file:\"" . $file_list[$i] . "\", "
								. "data:\"" . $data_array['interfaces'][$j]['details']['bulk'] . "\"";
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
						if (isset($data_array['interfaces'][$j]['details']['community'])) {
							if (!empty($data_array['interfaces'][$j]['details']['community'])) {
								$community = $data_array['interfaces'][$j]['details']['community'];
							}
							else {
								//Error count
								$error_count = $error_count+1;
								processing_status_display($i+1, $count, $error_count);
								
								//Error message output
								$error_message = "[ERROR] "
									. "message:\"" . "interfaces snmp community is missmatched." . "\", "
									. "file:\"" . $file_list[$i] . "\", "
									. "data:\"" . $data_array['interfaces'][$j]['details']['community'] . "\"";
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
						if (isset($data_array['interfaces'][$j]['details']['contextname'])) {
							$contextname = $data_array['interfaces'][$j]['details']['contextname'];
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
						if (isset($data_array['interfaces'][$j]['details']['securityname'])) {
							$securityname = $data_array['interfaces'][$j]['details']['securityname'];
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
						if (isset($data_array['interfaces'][$j]['details']['securitylevel'])) {
							$securitylevel = array_search($data_array['interfaces'][$j]['details']['securitylevel'], C_SECURITY_LEVEL);
							
							if (is_null($securitylevel)) {
								//Error count
								$error_count = $error_count+1;
								processing_status_display($i+1, $count, $error_count);
								
								//Error message output
								$error_message = "[ERROR] "
									. "message:\"" . "interfaces snmp securitylevel is missmatched." . "\", "
									. "file:\"" . $file_list[$i] . "\", "
									. "data:\"" . $data_array['interfaces'][$j]['details']['securitylevel'] . "\"";
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
						
						
						if ($securitylevel == '1' || $securitylevel == '2') {
							//authprotocol
							if (isset($data_array['interfaces'][$j]['details']['authprotocol'])) {
								$authprotocol = array_search($data_array['interfaces'][$j]['details']['authprotocol'], C_AUTHPROTOCOL);

								if (is_null($authprotocol)) {
									//Error count
									$error_count = $error_count+1;
									processing_status_display($i+1, $count, $error_count);
									
									//Error message output
									$error_message = "[ERROR] "
										. "message:\"" . "interfaces snmp authprotocol is missmatched." . "\", "
										. "file:\"" . $file_list[$i] . "\", "
										. "data:\"" . $data_array['interfaces'][$j]['details']['authprotocol'] . "\"";
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
							if (isset($data_array['interfaces'][$j]['details']['authpassphrase'])) {
								$authpassphrase = $data_array['interfaces'][$j]['details']['authpassphrase'];
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
						if ($securitylevel == '2') {
							//privprotocol
							if (isset($data_array['interfaces'][$j]['details']['privprotocol'])) {
								$privprotocol = array_search($data_array['interfaces'][$j]['details']['privprotocol'], C_PRIVPROTOCOL);
								
								if (is_null($privprotocol)) {
									//Error count
									$error_count = $error_count+1;
									processing_status_display($i+1, $count, $error_count);
									
									//Error message output
									$error_message = "[ERROR] "
										. "message:\"" . "interfaces snmp privprotocol is missmatched." . "\", "
										. "file:\"" . $file_list[$i] . "\", "
										. "data:\"" . $data_array['interfaces'][$j]['details']['privprotocol'] . "\"";
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
							if (isset($data_array['interfaces'][$j]['details']['privpassphrase'])) {
								$privpassphrase = $data_array['interfaces'][$j]['details']['privpassphrase'];
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
	
	//tags
	$tags = array();
	
	if (isset($data_array['tags'][0])) {
		$count_tags = count($data_array['tags']);
		for ($j = 0; $j < $count_tags; ++$j) {
			if (isset($data_array['tags'][$j]['tag']) && isset($data_array['tags'][$j]['value'])) {
				$tags[] = array(
					'tag' => $data_array['tags'][$j]['tag'],
					'value' => $data_array['tags'][$j]['value']
				);
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
	$macros = array();
	
	if (isset($data_array['macros'][0])) {
		$count_macros = count($data_array['macros']);
		for ($j = 0; $j < $count_macros; ++$j) {
			if (isset($data_array['macros'][$j]['macro']) && isset($data_array['macros'][$j]['value']) 
				&& isset($data_array['macros'][$j]['type']) && isset($data_array['macros'][$j]['description'])) {
				$macro_type = array_search($data_array['macros'][$j]['type'], MACRO_TYPE);

				if (is_null($macro_type)) { 
					//Error count
					$error_count = $error_count+1;
					processing_status_display($i+1, $count, $error_count);
					
					//Error message output
					$error_message = "[ERROR] "
						. "message:\"" . "macros type is missmatched." . "\", "
						. "file:\"" . $file_list[$i] . "\", "
						. "data:\"" . $data_array['macros'][$j]['type'] . "\"";
					log_output($log_file, $error_message);
					
					//Processing skip
					continue 2;
				}
				$macros[] = array(
					'macro' => $data_array['macros'][$j]['macro'],
					'value' => $data_array['macros'][$j]['value'],
					'type' => $macro_type,
					'description' => $data_array['macros'][$j]['description']
				);
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
	$inventory_mode = "";
	
	if (isset($data_array['inventory']['mode'])) {
		$inventory_mode = array_search($data_array['inventory']['mode'], INVENTORY_MODE);

		if (is_null($inventory_mode)) {
			//Error count
			$error_count = $error_count+1;
			processing_status_display($i+1, $count, $error_count);
			
			//Error message output
			$error_message = "[ERROR] "
				. "message:\"" . "inbentory_mode is missmatched." . "\", "
				. "file:\"" . $file_list[$i] . "\"";
			log_output($log_file, $error_message);
			
			//Processing skip
			continue;
		}
	}
	
	//inventory
	$inventory = array();
	
	if ($inventory_mode == '0' || $inventory_mode == '1') {
		if (isset($data_array['inventory']['inventory'][0])) {
			$count_inventory = count($data_array['inventory']['inventory']);
			for ($j = 0; $j < $count_inventory; ++$j) {
				if (isset($data_array['inventory']['inventory'][$j]['type']) &&
					isset($data_array['inventory']['inventory'][$j]['value'])) {
					
					$inventory[$data_array['inventory']['inventory'][$j]['type']] = $data_array['inventory']['inventory'][$j]['value'];
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
	}
	
	//encryption
	$tls_connect = "";
	$tls_accept = "";
	$tls_issuer = "";
	$tls_subject = "";
	$tls_psk_identity = "";
	$tls_psk = "";

	if (isset($data_array['encryption'])) {
		if (isset($data_array['encryption']['tls_connect'])) {
			$tls_connect = array_search($data_array['encryption']['tls_connect'], C_TLC_CONNECT);

			if (is_null($tls_connect)) {
				//Error count
				$error_count = $error_count+1;
				processing_status_display($i+1, $count, $error_count);
				
				//Error message output
				$error_message = "[ERROR] "
					. "message:\"" . "encryption tls_connect is missmatched." . "\", "
					. "file:\"" . $file_list[$i] . "\", "
					. "data:\"" . $data_array['encryption']['tls_connect'] . "\"";
				log_output($log_file, $error_message);
				
				//Processing skip
				continue;
			}
		}
		if (isset($data_array['encryption']['tls_accept'])) {
			$tls_accept = array_search($data_array['encryption']['tls_accept'], C_TLC_ACCEPT);

			if (is_null($tls_accept)) {
				//Error count
				$error_count = $error_count+1;
				processing_status_display($i+1, $count, $error_count);
				
				//Error message output
				$error_message = "[ERROR] "
					. "message:\"" . "encryption tls_accept is missmatched." . "\", "
					. "file:\"" . $file_list[$i] . "\", "
					. "data:\"" . $data_array['encryption']['tls_accept'] . "\"";
				log_output($log_file, $error_message);
				
				//Processing skip
				continue;
			}
		}
		if ($tls_connect == '2' || $tls_accept == '2' || $tls_accept == '3' || $tls_accept == '6' || $tls_accept == '7') {
			if (!isset($data_array['encryption']['tls_psk_identity']) || !isset($data_array['encryption']['tls_psk'])) {
				//Error count
				$error_count = $error_count+1;
				processing_status_display($i+1, $count, $error_count);
				
				//Error message output
				$error_message = "[ERROR] "
					. "message:\"" . "enctription tls_psk is not set." . "\", "
					. "file:\"" . $file_list[$i] . "\"";
				log_output($log_file, $error_message);
				
				//Processing skip
				continue;
			}
			else {
				$tls_psk_identity = $data_array['encryption']['tls_psk_identity'];
				$tls_psk = $data_array['encryption']['tls_psk'];
			}
		}
		if ($tls_connect == '4' || $tls_accept == '4' || $tls_accept == '5' || $tls_accept == '6' || $tls_accept == '7') {
			if (!isset($data_array['encryption']['tls_issuer']) || !isset($data_array['encryption']['tls_subject'])) {
				//Error count
				$error_count = $error_count+1;
				processing_status_display($i+1, $count, $error_count);
				
				//Error message output
				$error_message = "[ERROR] "
					. "message:\"" . "enctription tls_issuer or tls_subject is not set." . "\", "
					. "file:\"" . $file_list[$i] . "\"";
				log_output($log_file, $error_message);
				
				//Processing skip
				continue;
			}
			else {
				$tls_issuer = $data_array['encryption']['tls_issuer'];
				$tls_subject = $data_array['encryption']['tls_subject'];
			}
		}
	}

	//ipmi
	$ipmi_authtype = "-1";
	$ipmi_password = "";
	$ipmi_privilege = "2";
	$ipmi_username = "";
	
	if (isset($data_array['ipmi'])) {	
		if (isset($data_array['ipmi']['authtype'])) {
			$ipmi_authtype = array_search($data_array['ipmi']['authtype'], IPMI_AUTHTYPE);

			if (is_null($ipmi_authtype)) {
				//Error count
				$error_count = $error_count+1;
				processing_status_display($i+1, $count, $error_count);
				
				//Error message output
				$error_message = "[ERROR] "
					. "message:\"" . "ipmi authtype is missmatched." . "\", "
					. "file:\"" . $file_list[$i] . "\", "
					. "data:\"" . $data_array['ipmi']['authtype'] . "\"";
				log_output($log_file, $error_message);
				
				//Processing skip
				continue;
			}
		}
		if (isset($data_array['ipmi']['privilege'])) {
			$ipmi_privilege = array_search($data_array['ipmi']['privilege'], IPMI_PRIVILEGE);

			if (is_null($ipmi_privilege)) {
				//Error count
				$error_count = $error_count+1;
				processing_status_display($i+1, $count, $error_count);
				
				//Error message output
				$error_message = "[ERROR] "
					. "message:\"" . "ipmi privilege is missmatched." . "\", "
					. "file:\"" . $file_list[$i] . "\", "
					. "data:\"" . $data_array['ipmi']['privilege'] . "\"";
				log_output($log_file, $error_message);
				
				//Processing skip
				continue;
			}
		}
		if (isset($data_array['ipmi']['password'])) {
			$ipmi_password = $data_array['ipmi']['password'];
		}
		if (isset($data_array['ipmi']['username'])) {
			$ipmi_username = $data_array['ipmi']['username'];
		} 
	}

	//valuemaps
	$valuemaps = array();

	if (isset($data_array['valuemaps'][0])) {
		$count_valuemaps = count($data_array['valuemaps']);
		for ($j = 0; $j < $count_valuemaps; ++$j) {
			$valuemaps_name = "";
			
			$valuemaps_name = $data_array['valuemaps'][$j]['name'];
			$count_mappings = count($data_array['valuemaps'][$j]['mappings']);
			for ($k = 0; $k < $count_mappings; ++$k) {
				$mappings = array();
				$mappings_type = "";
				$mappings_value = "";
				$mappings_newvalue = "";
				
				$mappings_type = array_search($data_array['valuemaps'][$j]['mappings'][$k]['type'], VALUEMAPS_TYPE);

				if (is_null($mappings_type)) {
					//Error count
					$error_count = $error_count+1;
					processing_status_display($i+1, $count, $error_count);
					
					//Error message output
					$error_message = "[ERROR] "
						. "message:\"" . "valuemaps mappings type is missmatched." . "\", "
						. "file:\"" . $file_list[$i] . "\", "
						. "data:\"" . $data_array['valuemaps'][$j]['mappings'][$k]['type'] . "\"";
					log_output($log_file, $error_message);
					
					//Processing skip
					continue 3;
				}

				$mappings_value = $data_array['valuemaps'][$j]['mappings'][$k]['value'];
				$mappings_newvalue = $data_array['valuemaps'][$j]['mappings'][$k]['newvalue'];

				$mappings[] = array(
					'type' => $mappings_type,
					'value' => $mappings_value,
					'newvalue' => $mappings_newvalue
				);
			}
			$valuemaps[] = array(
				'name' => $valuemaps_name,
				'mappings' => $mappings
			);
		}
	}

	//API Request
	$method = 'host.create';
	$params = array(
		'host' => $host,
		'name' => $displayname,
		'status' => $status,
		'proxy_hostid' => $proxy_hostid,
		'description' => $description,
		'inventory_mode' => $inventory_mode,
		'groups' => $groups,
		'templates' => $templates,
		'macros' => $macros,
		'tags' => $tags,
		'inventory' => $inventory,
		'tls_connect' => $tls_connect,
		'tls_accept' => $tls_accept,
		'tls_issuer' => $tls_issuer,
		'tls_subject' => $tls_subject,
		'tls_psk_identity' => $tls_psk_identity,
		'tls_psk' => $tls_psk,
		'ipmi_authtype' => $ipmi_authtype,
		'ipmi_privilege' => $ipmi_privilege,
		'ipmi_username' => $ipmi_username,
		'ipmi_password' => $ipmi_password
	);
	if (!empty($interfaces)) {
		$params_interfaces = array(
			'interfaces' => $interfaces
		);
		$params = array_merge($params, $params_interfaces);
	}
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
	else {
		if (isset($valuemaps[0])) {
			$create_hostid = $response['result']['hostids'][0];

			$count_valuemaps = count($valuemaps);
			for ($j = 0; $j < $count_valuemaps; ++$j) {
				//API Request
				$method = 'valuemap.create';
				$params = array(
					'hostid' => $create_hostid,
					'name' => $valuemaps[$j]['name'],
					'mappings' => $valuemaps[$j]['mappings']
				);
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
