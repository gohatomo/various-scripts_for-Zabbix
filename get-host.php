#!/usr/bin/php
<?php
///////////////////////////////////////////////////////////////////////////////////
//	Name: get-host.php
//	Description: Get the host config in JSON format.
///////////////////////////////////////////////////////////////////////////////////

//Require
require_once 'conf/config.php';
require_once 'common_function.php';
require_once 'zbx_define.php';

//Setting timezone
date_default_timezone_set("$timezone");

//Log file settings
$date = date("Ymd");
$time = date("His");

$log_file = rtrim($log_dir, '/') . "/get-host_{$date}.log";
if (!file_exists($log_dir)) {
	mkdir($log_dir, 0755);
}

//Datafile get
$data_dir = rtrim($data_dir, '/') . "/get-host";

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

//Output directory settings
if (!file_exists($output_dir)) {
	mkdir($output_dir, 0755);	
}

//Temp directory settings
$temp_dir = rtrim($output_dir, '/') . "/get-host_{$date}_{$time}";

if (!file_exists($temp_dir)) {
	mkdir($temp_dir, 0755, TRUE);	
}

//API login
$ch = curl_setup($api_url);
$auth = api_login($api_user, $api_pass);

//Display adjustment
echo "\n";

//Get host
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

	//Host get
	if (isset($data_array['groups'][0])) {
		$count_groups = count($data_array['groups']);
		for ($j = 0; $j < $count_groups; ++$j) {

			$interfaces = array();

			//API Request
			$method = 'hostgroup.get';
			$params = array(
				'output' => array(
					'groupid'
				),
				'filter' => array(
					'name' => $data_array['groups'][$j]
				)
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

			//Hostgroupid get
			if (isset($response['result'][0]['groupid'])) {
				$groupid = $response['result'][0]['groupid'];
			}
			else {
				//Error count
				$error_count = $error_count+1;
				processing_status_display($i+1, $count, $error_count);

				//Error message output
				$error_message = "[ERROR] "
					. "message:\"" . "hostgroup name is missmached." . "\", "
					. "file:\"" . $file_list[$i] . "\", "
					. "data:\"" . $data_array['groups'][$j] . "\"";
				log_output($log_file, $error_message);

				//Processing skip
				continue;
			}

			//API Request
			$method = 'host.get';
			$params = array(
				'groupids' => $groupid,
				'output' => array(
					'hostid',
					'host',
					'name',
					'status',
					'proxy_hostid',
					'description',
					'inventory_mode',
					'tls_connect',
					'tls_accept',
					'tls_issuer',
					'tls_subject',
					'ipmi_authtype',
					'ipmi_password',
					'ipmi_privilege',
					'ipmi_username'
				),
				'selectGroups' => array(
					'name'
				),
				'selectInterfaces' => array(
					'type',
					'main',
					'useip',
					'ip',
					'dns',
					'port',
					'details'
				),
				'selectParentTemplates' => array(
					'host'
				),
				'selectMacros' => array(
					'macro',
					'value',
					'type',
					'description'
				),
				'selectTags' => array(
					'tag',
					'value'
				),
				'selectInventory' => 'extend',
				'selectValueMaps' => array(
					'name',
					'mappings'
				)
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

			//Host get
			$count_hosts = count($response['result']);
			for ($k = 0; $k < $count_hosts; ++$k) {
				//hostid
				$hostid = $response['result'][$k]['hostid'];

				//host
				$host = $response['result'][$k]['host'];

				//displayname
				$displayname = $response['result'][$k]['name'];

				//status
				if (isset(C_STATUS[$response['result'][$k]['status']])) {
					$status = C_STATUS[$response['result'][$k]['status']];
				}
				else {
					$status = $response['result'][$k]['status'];
				}

				//proxy_hostid
				if ($response['result'][$k]['proxy_hostid'] === '0') {
					$proxy_name = "";
				}
				else {
					$proxy_name = get_proxyname($response['result'][$k]['proxy_hostid']);
					
					if (is_null($proxy_name)) {
						$proxy_name = $response['result'][$k]['proxy_hostid'];

						//Error count
						$error_count = $error_count+1;
						processing_status_display($i+1, $count, $error_count);

						//Error message output
						$error_message = "[ERROR] "
							. "message:\"" . "proxy name is missmatched." . "\", "
							. "file:\"" . $file_list[$i] . "\", "
							. "data:\"" . $data_array['groups'][$j] . "\"";
						log_output($log_file, $error_message);
					}
				}

				//description
				$description = $response['result'][$k]['description'];

				//templates
				$templates = array();
				$count_templates = count($response['result'][$k]['parentTemplates']);
				for ($l = 0; $l < $count_templates; ++$l) {
					$templates[] = $response['result'][$k]['parentTemplates'][$l]['host'];
				}

				//groups
				$link_groups = array();
				$count_link_groups = count($response['result'][$k]['groups']);
				for ($l = 0; $l < $count_link_groups; ++$l) {
					$link_groups[] = $response['result'][$k]['groups'][$l]['name'];
				}

				//interfaces
				$interfaces = array();
				$count_interfaces = count($response['result'][$k]['interfaces']);
				for ($l = 0; $l < $count_interfaces; ++$l) {
					//type
					if (isset(INTERFACE_TYPE[$response['result'][$k]['interfaces'][$l]['type']])) {
						$type = INTERFACE_TYPE[$response['result'][$k]['interfaces'][$l]['type']];
					}
					else {
						$type = $response['result'][$k]['interfaces'][$l]['type'];
					}
					
					//main
					if (isset(INTERFACE_MAIN[$response['result'][$k]['interfaces'][$l]['main']])) {
						$main = INTERFACE_MAIN[$response['result'][$k]['interfaces'][$l]['main']];
					}
					else {
						$main = $response['result'][$k]['interfaces'][$l]['main'];
					}

					//useip
					if (isset(INTERFACE_USEIP[$response['result'][$k]['interfaces'][$l]['main']])) {
						$useip = INTERFACE_USEIP[$response['result'][$k]['interfaces'][$l]['main']];
					}
					else {
						$useip = $response['result'][$k]['interfaces'][$l]['useip'];
					}

					//ip
					$ip = $response['result'][$k]['interfaces'][$l]['ip'];

					//dns
					$dns = $response['result'][$k]['interfaces'][$l]['dns'];

					//port
					$port = $response['result'][$k]['interfaces'][$l]['port'];

					//version
					if ($response['result'][$k]['interfaces'][$l]['type'] === '2') {
						$version = $response['result'][$k]['interfaces'][$l]['details']['version'];
					}
					else {
						$version = "";
					}

					//bulk
					if (isset($response['result'][$k]['interfaces'][$l]['details']['bulk'])) {
						if (isset(INTERFACE_BULK[$response['result'][$k]['interfaces'][$l]['details']['bulk']])) {
							$bulk = INTERFACE_BULK[$response['result'][$k]['interfaces'][$l]['details']['bulk']];
						}
						else {
							$bulk = $response['result'][$k]['interfaces'][$l]['details']['bulk'];
						}
					}
					else {
						$bulk = "";
					}

					//community
					if (isset($response['result'][$k]['interfaces'][$l]['details']['community'])) {
						$community = $response['result'][$k]['interfaces'][$l]['details']['community'];
					}
					else {
						$community = "";
					}

					//contextname
					if (isset($response['result'][$k]['interfaces'][$l]['details']['contextname'])) {
						$contextname = $response['result'][$k]['interfaces'][$l]['details']['contextname'];
					}
					else {
						$contextname = "";
					}

					//securityname
					if (isset($response['result'][$k]['interfaces'][$l]['details']['securityname'])) {
						$securityname = $response['result'][$k]['interfaces'][$l]['details']['securityname'];
					}
					else {
						$securityname = "";
					}

					//securitylevel
					if (isset($response['result'][$k]['interfaces'][$l]['details']['securitylevel'])) {
						if (isset(C_SECURITY_LEVEL[$response['result'][$k]['interfaces'][$l]['details']['securitylevel']])) {
							$securitylevel = C_SECURITY_LEVEL[$response['result'][$k]['interfaces'][$l]['details']['securitylevel']];
						}
						else {
							$securitylevel = $response['result'][$k]['interfaces'][$l]['details']['securitylevel'];
						}
					}
					else {
						$securitylevel = "";
					}

					//authprotocol
					if (isset($response['result'][$k]['interfaces'][$l]['details']['authprotocol'])) {
						if (isset(C_AUTHPROTOCOL[$response['result'][$k]['interfaces'][$l]['details']['authprotocol']])) {
							$authprotocol = C_AUTHPROTOCOL[$response['result'][$k]['interfaces'][$l]['details']['authprotocol']];
						}
						else {
							$authprotocol = $response['result'][$k]['interfaces'][$l]['details']['authprotocol'];
						}
					}
					else {
						$authprotocol = "";
					}

					//authpassphrase
					if (isset($response['result'][$k]['interfaces'][$l]['details']['authpassphrase'])) {
						$authpassphrase = $response['result'][$k]['interfaces'][$l]['details']['authpassphrase'];
					}
					else {
						$authpassphrase = "";
					}

					//privprotocol
					if (isset($response['result'][$k]['interfaces'][$l]['details']['privprotocol'])) {
						if (isset(C_PRIVPROTOCOL[$response['result'][$k]['interfaces'][$l]['details']['privprotocol']])) {
							$privprotocol = C_PRIVPROTOCOL[$response['result'][$k]['interfaces'][$l]['details']['privprotocol']];
						}
						else {
							$privprotocol = $response['result'][$k]['interfaces'][$l]['details']['privprotocol'];
						}
					}
					else {
						$privprotocol = "";
					}

					//privpassphrase
					if (isset($response['result'][$k]['interfaces'][$l]['details']['privpassphrase'])) {
						$privpassphrase = $response['result'][$k]['interfaces'][$l]['details']['privpassphrase'];
					}
					else {
						$privpassphrase = "";
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

				//tags
				$tags = array();
				$count_tags = count($response['result'][$k]['tags']);
				for ($l = 0; $l < $count_tags; ++$l) {
					$tags[] = array(
						'tag' => $response['result'][$k]['tags'][$l]['tag'],
						'value' => $response['result'][$k]['tags'][$l]['value']
					);
				}

				//macros
				$macros = array();
				$count_macros = count($response['result'][$k]['macros']);
				for ($l = 0; $l < $count_macros; ++$l) {
					if ($response['result'][$k]['macros'][$l]['type'] === '0') {
						$macros[] = array(
							'macro' => $response['result'][$k]['macros'][$l]['macro'],
							'value' => $response['result'][$k]['macros'][$l]['value'],
							'type' => 'text',
							'description' => $response['result'][$k]['macros'][$l]['description']
						);
					}
					elseif ($response['result'][$k]['macros'][$l]['type'] === '1') {
						$macros[] = array(
							'macro' => $response['result'][$k]['macros'][$l]['macro'],
							'value' => '********',
							'type' => 'secret',
							'description' => $response['result'][$k]['macros'][$l]['description']
						);
					}
					elseif ($response['result'][$k]['macros'][$l]['type'] === '2') {
						$macros[] = array(
							'macro' => $response['result'][$k]['macros'][$l]['macro'],
							'value' => $response['result'][$k]['macros'][$l]['value'],
							'type' => 'vault',
							'description' => $response['result'][$k]['macros'][$l]['description']
						);
					}
					else {
						$macros[] = array(
							'macro' => $response['result'][$k]['macros'][$l]['macro'],
							'value' => $response['result'][$k]['macros'][$l]['value'],
							'type' => $response['result'][$k]['macros'][$l]['type'],
							'description' => $response['result'][$k]['macros'][$l]['description']
						);
					}
				}

				//inventory_mode
				if (isset(INVENTORY_MODE[$response['result'][$k]['inventory_mode']])) {
					$inventory_mode = INVENTORY_MODE[$response['result'][$k]['inventory_mode']];
				}
				else {
					$inventory_mode = $response['result'][$k]['inventory_mode'];
				}

				//inventory
				$inventory = array();
				if (!empty($response['result'][$k]['inventory'])) {
					foreach($response['result'][$k]['inventory'] as $key => $value) {
						if (!empty($value)) {
							$inventory[] = array(
								'type' => $key,
								'value' => $value
							);
						}
					}
				}

				//tls_connect
				$tls_psk_identity = "";
				$tls_psk = "";

				if (isset(C_TLC_CONNECT[$response['result'][$k]['tls_connect']])) {
					$tls_connect = C_TLC_CONNECT[$response['result'][$k]['tls_connect']];
				}
				else {
					$tls_connect = $response['result'][$k]['tls_connect'];
				}

				//tls_accept
				if (isset(C_TLC_ACCEPT[$response['result'][$k]['tls_accept']])) {
					$tls_accept = C_TLC_ACCEPT[$response['result'][$k]['tls_accept']];
				}
				else {
					$tls_accept = $response['result'][$k]['tls_accept'];
				}

				if ($response['result'][$k]['tls_accept'] === '2'
				 || $response['result'][$k]['tls_accept'] === '3'
				 || $response['result'][$k]['tls_accept'] === '6'
				 || $response['result'][$k]['tls_accept'] === '7') {
					$tls_psk_identity = 'write only';
					$tls_psk = 'write only';
				 }
				
				//tls_issuer
				$tls_issuer = $response['result'][$k]['tls_issuer'];

				//tls_subject
				$tls_subject = $response['result'][$k]['tls_subject'];

				//ipmi_authtype
				if (isset(IPMI_AUTHTYPE[$response['result'][$k]['ipmi_authtype']])) {
					$ipmi_authtype = IPMI_AUTHTYPE[$response['result'][$k]['ipmi_authtype']];
				}
				else {
					$ipmi_authtype = $response['result'][$k]['ipmi_authtype'];
				}

				//ipmi_privilege
				if (isset(IPMI_PRIVILEGE[$response['result'][$k]['ipmi_privilege']])) {
					$ipmi_privilege = IPMI_PRIVILEGE[$response['result'][$k]['ipmi_privilege']];
				}
				else {
					$ipmi_privilege = $response['result'][$k]['ipmi_privilege'];
				}

				//ipmi_username
				$ipmi_username = $response['result'][$k]['ipmi_username'];

				//ipmi_password
				$ipmi_password = $response['result'][$k]['ipmi_password'];

				//valuemaps
				$valuemaps = array();
				$count_valuemaps = count($response['result'][$k]['valuemaps']);
				for ($l = 0; $l < $count_valuemaps; ++$l) {
					$mappings = array();
					$valuemaps_name = $response['result'][$k]['valuemaps'][$l]['name'];
					$count_mappings = count($response['result'][$k]['valuemaps'][$l]['mappings']);
					for ($m = 0; $m < $count_mappings; ++$m) {
						if (isset(VALUEMAPS_TYPE[$response['result'][$k]['valuemaps'][$l]['mappings'][$m]['type']])) {
							$valuemaps_type = VALUEMAPS_TYPE[$response['result'][$k]['valuemaps'][$l]['mappings'][$m]['type']];
						}
						else {
							$valuemaps_type = $response['result'][$k]['valuemaps'][$l]['mappings'][$m]['type'];
						}
						$mappings[] = array(
							'type' => $valuemaps_type,
							'value' => $response['result'][$k]['valuemaps'][$l]['mappings'][$m]['value'],
							'newvalue' => $response['result'][$k]['valuemaps'][$l]['mappings'][$m]['newvalue']
						);
					}
					$valuemaps[] = array(
						'name' => $valuemaps_name,
						'mappings' => $mappings
					);
				}

				//File output
				$output_file = $temp_dir . "/" . "get-host_{$host}_{$date}_{$time}.json";
				$output_array[] = $output_file;
				$output_data = array(
					'host' => $host,
					'name' => $displayname,
					'status' => $status,
					'proxy' => $proxy_name,
					'description' => $description,
					'templates' => $templates,
					'groups' => $link_groups,
					'interfaces' => $interfaces,
					'tags' => $tags,
					'macros' => $macros,
					'inventory' => array(
						'mode' => $inventory_mode,
						'inventory' => $inventory
					),
					'encryption' => array(
						'tls_connect' => $tls_connect,
						'tls_accept' => $tls_accept,
						'tls_issuer' => $tls_issuer,
						'tls_subject' => $tls_subject,
						'tls_psk_identity' => $tls_psk_identity,
						'tls_psk' => $tls_psk
					),
					'ipmi' => array(
						'authtype' => $ipmi_authtype,
						'privilege' => $ipmi_privilege,
						'username' => $ipmi_username,
						'password' => $ipmi_password
					),
					'valuemaps' => $valuemaps
				);
				$output_json = json_encode($output_data, JSON_PRETTY_PRINT);
				
				file_put_contents($output_file, $output_json);
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
}

//Display adjustment
echo "\n";

//API logout
api_logout($auth);
curl_close($ch);

//Create zip file
if (isset($output_array[0])) {
	$z_file = "{$output_dir}/get-host_{$date}_{$time}.zip";

	$zip = new ZipArchive();
	$zip->open("{$z_file}", ZipArchive::CREATE);

	$count_output = count($output_array);
	for ($i = 0; $i < $count_output; ++$i) {
		$zip->addFile($output_array[$i], mb_substr(mb_strrchr("/$output_array[$i]", '/', false, "$encoding"), 1, NULL, "$encoding"));
	}
	
	$zip->close();

	//Show full path of ZIP file on standard output
	echo "[Output File Path]" . "\n";
	echo realpath($z_file) . "\n";
}
else {
	//Error count
	$error_count = $error_count+1;
	processing_status_display($i+1, $count, $error_count);

	//Error message output
	$error_message = "[ERROR] "
		. "message:\"" . "No output file. Check data file and Zabbix." . "\"";
	log_output($log_file, $error_message);	
}

//Remove temp directory
rm_dir($temp_dir);


////////////////////////////////////////////////////////////////////////////////
//	Get Function
////////////////////////////////////////////////////////////////////////////////
//Proxy id
function get_proxyname($proxy_hostid) {
	global $auth;
	global $f;

	$method = 'proxy.get';
	$params = array(
		'proxyids' => $proxy_hostid,
		'output' => array(
			'host'
		)
	);
	$response = api_request($method, $params, $auth, '');

	if (isset($response['result'])) {
		if (isset($response['result'][0]['host'])) {
			return $response['result'][0]['host'];
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
