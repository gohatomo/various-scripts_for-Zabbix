#!/usr/bin/php
<?php
///////////////////////////////////////////////////////////////////////////////////
//	Name: get-trigger-withhost.php
//	Description: Get trigger for host in JSON format.
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

$log_file = rtrim($log_dir, '/') . "/get-trigger-withhost_{$date}.log";
if (!file_exists($log_dir)) {
	mkdir($log_dir, 0755);
}

//Datafile get
$data_dir = rtrim($data_dir, '/') . "/get-trigger-withhost";

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
$temp_dir = rtrim($output_dir, '/') . "/get-trigger-withhost_{$date}_{$time}";

if (!file_exists($temp_dir)) {
	mkdir($temp_dir, 0755, TRUE);	
}

//API login
$ch = curl_setup($api_url);
$auth = api_login($api_user, $api_pass);

//Array declaration
$export_array = array();
$function_item_array = array();
$dependenciesdata = array();

//Display adjustment
echo "\n";

//Get ID and sort by hostid.
$count = count($file_list);
$error_count = 0;
for ($i = 0; $i < $count; ++$i) {
	//Processing status display
	processing_status_display($i+1, $count, $error_count);

	//Display adjustment
	echo "\n---------------------------------------\n";

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

	//Get ID
	if (isset($data_array['groups'][0])) {
		$count_groups = count($data_array['groups']);
		for ($j = 0; $j < $count_groups; ++$j) {
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

			//Host List get
			$host_list = array();

			//API Request
			$method = 'host.get';
			$params = array(
				'groupids' => $groupid,
				'output' => array(
					'hostid',
				),
				'selectParentTemplates' => array(
					'templateid',
					'name'
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

			//Get Host property
			$link_template = array();
			$templatedata = array();
			$count_hosts = count($response['result']);
			for ($k = 0; $k < $count_hosts; ++$k) {
				$host_listid = $response['result'][$k]['hostid'];
				$host_list[] = $host_listid;

				$count_templates = count($response['result'][$k]['parentTemplates']);
				if ($count_templates != 0) {
					for ($l = 0; $l < $count_templates; ++$l) {
						$link_templateid = $response['result'][$k]['parentTemplates'][$l]['templateid'];
						$link_templatename = $response['result'][$k]['parentTemplates'][$l]['name'];

						$link_template[$link_templateid] = $link_templatename;
					}
				}
			}

			//Template data get
			if (!empty($link_template)) {
				$templatedata = get_templatedata($link_template);
			}

			//API Request
			$method = 'trigger.get';
			$params = array(
				'hostids' => $host_list,
				'output' => 'extend',
				'selectHosts' => array(
					'hostid',
					'host',
					'name',
					'status'
				),
				'selectFunctions' => 'extend',
				'selectDependencies' => 'extend',
				'selectTags' => 'extend'
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

			//Store array
			echo "*** Start... Get Trigger data: " . $data_array['groups'][$j] . " ***\n";
			$count_triggers = count($response['result']);
			for ($k = 0; $k < $count_triggers; ++$k) {
				//Processing status display
				processing_status_display($k+1, $count_triggers, $error_count);

				//hostid
				$hostid = $response['result'][$k]['hosts'][0]['hostid'];

				//host
				$host = $response['result'][$k]['hosts'][0]['host'];

				//displayname
				$displayname = $response['result'][$k]['hosts'][0]['name'];

				//hoststatus
				if (isset(C_STATUS[$response['result'][$k]['hosts'][0]['status']])) {
					$hoststatus = C_STATUS[$response['result'][$k]['hosts'][0]['status']];
				}
				else {
					$hoststatus = $response['result'][$k]['hosts'][0]['status'];
				}

				//Store hostdata in export_array
				if (!isset($export_array[$hostid])) {
					$export_array[$hostid]['hosts'] = array(
						'hostid' => $hostid,
						'host' => $host,
						'name' => $displayname,
						'status' => $hoststatus
					);
				}
				
				//triggerid
				$triggerid = $response['result'][$k]['triggerid'];
				
				//triggername
				$triggername = $response['result'][$k]['description'];
				
				//eventname
				$eventname = $response['result'][$k]['event_name'];
				
				//opdata
				$opdata = $response['result'][$k]['opdata'];
				
				//severity
				if (isset(SEVERITY[$response['result'][$k]['priority']])) {
					$severity = SEVERITY[$response['result'][$k]['priority']];
				}
				else {
					$severity = $response['result'][$k]['priority'];
				}
				
				//expression
				$expression = $response['result'][$k]['expression'];
				
				//recovery_mode
				if (isset(RECOVERY_MODE[$response['result'][$k]['recovery_mode']])) {
					$recovery_mode = RECOVERY_MODE[$response['result'][$k]['recovery_mode']];
				}
				else {
					$recovery_mode = $response['result'][$k]['recovery_mode'];
				}
				
				//recovery_expression
				$recovery_expression = $response['result'][$k]['recovery_expression'];
				
				//trigger_type
				if (isset(TRIGGER_TYPE[$response['result'][$k]['type']])) {
					$trigger_type = TRIGGER_TYPE[$response['result'][$k]['type']];
				}
				else {
					$trigger_type = $response['result'][$k]['type'];
				}
				
				//correlation_mode
				if (isset(CORRELATION_MODE[$response['result'][$k]['correlation_mode']])) {
					$correlation_mode = CORRELATION_MODE[$response['result'][$k]['correlation_mode']];
				}
				else {
					$correlation_mode = $response['result'][$k]['correlation_mode'];
				}
				
				//correlation_tag
				$correlation_tag = $response['result'][$k]['correlation_tag'];
				
				//manual_close
				if (isset(MANUAL_CLOSE[$response['result'][$k]['manual_close']])) {
					$manual_close = MANUAL_CLOSE[$response['result'][$k]['manual_close']];
				}
				else {
					$manual_close = $response['result'][$k]['manual_close'];
				}
				
				//url
				$url = $response['result'][$k]['url'];
				
				//comments
				$comments = $response['result'][$k]['comments'];
				
				//triggerstatus
				if (isset(C_STATUS[$response['result'][$k]['status']])) {
					$triggerstatus = C_STATUS[$response['result'][$k]['status']];
				}
				else {
					$triggerstatus = $response['result'][$k]['status'];
				}
				
				//flags
				if (isset(C_FLAGS[$response['result'][$k]['flags']])) {
					$triggerflags = C_FLAGS[$response['result'][$k]['flags']];
				}
				else {
					$triggerflags = $response['result'][$k]['flags'];
				}
				
				//tag
				$triggertags = $response['result'][$k]['tags'];

				//templateid
				if ($response['result'][$k]['templateid'] === '0') {
					$link_templatename_trigger = "";
				}
				else {
					$link_templatename_trigger = get_link_templatename_trigger($response['result'][$k]['templateid'], $templatedata);
				}
				
				//dependencies
				$dependencies_array = array();
				$count_dependencies = count($response['result'][$k]['dependencies']);
				for ($l = 0; $l < $count_dependencies; ++$l) {
					$dependencies_triggerid = $response['result'][$k]['dependencies'][$l]['triggerid'];
					if (isset($dependenciesdata[$dependencies_triggerid])) {
						$dependencies_description = $dependenciesdata[$dependencies_triggerid]['triggername'];
						$dependencies_displayname = $dependenciesdata[$dependencies_triggerid]['displayname'];
					}
					else {
						$dependencies_triggerdata = get_dependencies_triggerdata($dependencies_triggerid);
						$dependencies_description = $dependencies_triggerdata[0]['description'];
						$dependencies_displayname = $dependencies_triggerdata[0]['hosts'][0]['name'];
						
						$dependenciesdata[$dependencies_triggerid] = array(
							'triggername' => $dependencies_description,
							'displayname' => $dependencies_displayname
						);
					}
					
					$dependencies_array[] = array(
						'triggerid' => $dependencies_triggerid,
						'displayname' => $dependencies_displayname,
						'triggername' => $dependencies_description
					);
				}
				
				//Get function data and replace expression and recovery_expression
				$count_function = count($response['result'][$k]['functions']);
				for ($l = 0; $l < $count_function; ++$l) {
					$function_itemdata = array();
					$functionid = '{' . $response['result'][$k]['functions'][$l]['functionid'] . '}';
					$functionparam = substr($response['result'][$k]['functions'][$l]['parameter'], 1);
					$function = $response['result'][$k]['functions'][$l]['function'];
					$function_itemid = $response['result'][$k]['functions'][$l]['itemid'];
					if (isset($function_item_array[$function_itemid])) {
						$function_key_ = $function_item_array[$function_itemid]['key_'];
						$function_host = $function_item_array[$function_itemid]['host'];
					}
					else {
						$function_itemdata = get_function_itemdata($function_itemid);
						$function_key_ = $function_itemdata[0]['key_'];
						$function_host = $function_itemdata[0]['hosts'][0]['host'];
						$function_item_array[$function_itemid] = array(
							'host' => "$function_host",
							'key_' => "$function_key_"
						);
					}
					$replace_expression = $function .'(/' . "$function_host" . '/' . "$function_key_" . "$functionparam" . ')';
					$expression = str_replace("$functionid", "$replace_expression", "$expression");
					$recovery_expression = str_replace("$functionid", "$replace_expression", "$recovery_expression");
				}
				
				//Store triggerdata in export_array
				$export_array[$hostid]['triggers'][] = array(
					'triggerid' => $triggerid,
					'link_templatename' => $link_templatename_trigger,
					'flags' => $triggerflags,
					'name' => $triggername,
					'eventname' => $eventname,
					'opdata' => $opdata,
					'severity' => $severity,
					'expression' => $expression,
					'recovery_mode' => $recovery_mode,
					'recovery_expression' => $recovery_expression,
					'event_type' => $trigger_type,
					'correlation_mode' => $correlation_mode,
					'correlation_tag' => $correlation_tag,
					'manual_close' => $manual_close,
					'url' => $url,
					'comments' => $comments,
					'status' => $triggerstatus,
					'tag' => $triggertags,
					'dependencies' => $dependencies_array
				);
			}
			//Processing status display
			echo "\n*** End... Get Trigger data: " . $data_array['groups'][$j] . " ***\n";
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
	//Display adjustment
	echo "---------------------------------------\n";
	echo "Finish..." . ($i+1) . "/" . $count .  "/" . $error_count ." (now/max/error)\n\n";
}

//File output
foreach ($export_array as $key => $value) {
	$output_host = $value['hosts']['host'];
	$output_file = $temp_dir . "/" . "get-trigger-withhost_{$output_host}_{$date}_{$time}.json";
	$output_array[] = $output_file;
	$output_json = json_encode($value, JSON_PRETTY_PRINT);

	file_put_contents($output_file, $output_json);
}

//Display adjustment
echo "\n";

//API logout
api_logout($auth);
curl_close($ch);

//Create zip file
if (isset($output_array[0])) {
	$z_file = "{$output_dir}/get-trigger-withhost_{$date}_{$time}.zip";

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
//Template data
function get_templatedata($link_template) {
	global $auth;
	global $f;
	global $log_file;

	$templatedata = array();

	while (true) {
		$link_template_p = array();
		
		foreach ($link_template as $templateid => $templatename) {

			if (!isset($templatedata['templates'][$templateid])) {

				$templatedata['templates'][$templateid] = $templatename;

				$method = 'template.get';
				$params = array(
					'templateids' => $templateid,
					'output' => array(
						'name',
						'templateid'
					),
					'selectParentTemplates' => array(
						'name',
						'templateid'
					),
					'selectTriggers' => array(
						'triggerid',
						'templateid'
					),
					'sortfield' => 'hostid'
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

				//Template Trigger data
				$count_triggers = count($response['result'][0]['triggers']);
				if ($count_triggers != 0) {
					for ($i = 0; $i < $count_triggers; ++$i) {
						$triggerid = $response['result'][0]['triggers'][$i]['triggerid'];

						$templatedata['triggers'][$triggerid]['name'] = $templatename;
						$templatedata['triggers'][$triggerid]['templateid'] = $response['result'][0]['triggers'][$i]['templateid'];
					}
				}	

				//Template data
				$count_templates = count($response['result'][0]['parentTemplates']);
				if ($count_templates != 0) {
					for ($i = 0; $i < $count_templates; ++$i) {
						$link_templateid = $response['result'][0]['parentTemplates'][$i]['templateid'];
						$link_templatename = $response['result'][0]['parentTemplates'][$i]['name'];

						$link_template_p[$link_templateid] = $link_templatename;
					}
				}
			}
		}
		$count_templates_p = count($link_template_p);
		if ($count_templates_p != 0) {
			$link_template = $link_template_p;
		}
		else {
			break;
		}
	}
	return $templatedata;
}

//Link Template name of trigger
function get_link_templatename_trigger($triggerid, $templatedata) {
	while (true) {
		if ($templatedata['triggers'][$triggerid]['templateid'] === '0') {
			$link_templatename = $templatedata['triggers'][$triggerid]['name'];
			break;
		}
		else {
			$triggerid = $templatedata['triggers'][$triggerid]['templateid'];
		}
	}
	return $link_templatename;
}

//Function item data
function get_function_itemdata($itemid) {
	global $auth;
	global $f;
	global $log_file;

	$method = 'item.get';
	$params = array(
		'itemids' => $itemid,
		'output' => array(
			'key_'
		),
		'webitems' => true,
		'selectHosts' => array(
			'host'
		)
	);
	$response = api_request($method, $params, $auth, '');

	if (isset($response['result'][0]['key_'])) {
		return $response['result'];
	}
	elseif (isset($response['error'])) {
		$error_message = error_message($response);
		log_output($log_file, $error_message);
		exit(1);
	}
	else {
		$error_message = error_message_unexpected($response);
		log_output($log_file, $error_message);
		exit(1);
	}
}

//Dependencies trigger data
function get_dependencies_triggerdata($triggerid) {
	global $auth;
	global $f;
	global $log_file;

	$method = 'trigger.get';
	$params = array(
		'triggerids' => $triggerid,
		'output' => array(
			'description'
		),
		'selectHosts' => array(
			'name'
		)
	);
	$response = api_request($method, $params, $auth, '');

	if (isset($response['result'][0]['description'])) {
		return $response['result'];
	}
	elseif (isset($response['error'])) {
		$error_message = error_message($response);
		log_output($log_file, $error_message);
		exit(1);
	}
	else {
		$error_message = error_message_unexpected($response);
		log_output($log_file, $error_message);
		exit(1);
	}
}

?>
