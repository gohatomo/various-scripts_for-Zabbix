#!/usr/bin/php
<?php
///////////////////////////////////////////////////////////////////////////////////
//	Name: create-template.php
//	Description: Create a new template.
///////////////////////////////////////////////////////////////////////////////////

//Require
require_once 'conf/config.php';
require_once 'common_function.php';
require_once 'zbx_define.php';

//Setting timezone
date_default_timezone_set("$timezone");

//Log file settings
$date = date("Ymd");

$log_file = rtrim($log_dir, '/') . "/create-template_{$date}.log";
if (!file_exists($log_dir)) {
	mkdir($log_dir, 0755);
}

//Datafile get
$data_dir = rtrim($data_dir, '/') . "/create-template";

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
$array_templates = array();
$array_groups = array();

//Display adjustment
echo "\n";

//Template create
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
		if (!empty($data_array['name'])) {
			$displayname = $data_array['name'];
		}
		else {
			$displayname = $data_array['host'];
		}
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
	$method = 'template.create';
	$params = array(
		'host' => $host,
		'name' => $displayname,
		'description' => $description,
		'groups' => $groups,
		'templates' => $templates,
		'macros' => $macros,
		'tags' => $tags
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
	else {
		if (isset($valuemaps[0])) {
			$create_hostid = $response['result']['templateids'][0];

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
