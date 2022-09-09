#!/usr/bin/php
<?php
///////////////////////////////////////////////////////////////////////////////////
//	Name: get-template.php
//	Description: Get the template config in JSON format.
///////////////////////////////////////////////////////////////////////////////////

//Require
require_once 'conf/config.php';
require_once 'common_function.php';

//Setting timezone
date_default_timezone_set("$timezone");

//Log file settings
$date = date("Ymd");
$time = date("His");

$log_file = rtrim($log_dir, '/') . "/get-template_{$date}.log";
if (!file_exists($log_dir)) {
	mkdir($log_dir, 0755);
}

//Datafile get
$data_dir = rtrim($data_dir, '/') . "/get-template";

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
$temp_dir = rtrim($output_dir, '/') . "/get-template_{$date}_{$time}";

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
			$method = 'template.get';
			$params = array(
				'groupids' => $groupid,
				'output' => array(
					'templateid',
					'host',
					'name',
					'description'
				),
				'selectGroups' => array(
					'name'
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
				'selectValueMaps' => array(
					'name',
					'mappings'
				)
			);
			$response = api_request($method, $params, $auth, '');
			//print_r($response);
			
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
				//templateid
				$templateid = $response['result'][$k]['templateid'];

				//host
				$host = $response['result'][$k]['host'];

				//displayname
				$displayname = $response['result'][$k]['name'];

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
					$groups[] = $response['result'][$k]['groups'][$l]['name'];
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

				//valuemaps
				$valuemaps = array();
				$count_valuemaps = count($response['result'][$k]['valuemaps']);
				for ($l = 0; $l < $count_valuemaps; ++$l) {
					$mappings = array();
					$valuemaps_name = $response['result'][$k]['valuemaps'][$l]['name'];
					$count_mappings = count($response['result'][$k]['valuemaps'][$l]['mappings']);
					for ($m = 0; $m < $count_mappings; ++$m) {
						if ($response['result'][$k]['valuemaps'][$l]['mappings'][$m]['type'] === '0') {
							$valuemaps_type = '=';
						}
						elseif ($response['result'][$k]['valuemaps'][$l]['mappings'][$m]['type'] === '1') {
							$valuemaps_type = '>=';
						}
						elseif ($response['result'][$k]['valuemaps'][$l]['mappings'][$m]['type'] === '2') {
							$valuemaps_type = '<=';
						}
						elseif ($response['result'][$k]['valuemaps'][$l]['mappings'][$m]['type'] === '3') {
							$valuemaps_type = 'range';
						}
						elseif ($response['result'][$k]['valuemaps'][$l]['mappings'][$m]['type'] === '4') {
							$valuemaps_type = 'regexp';
						}
						elseif ($response['result'][$k]['valuemaps'][$l]['mappings'][$m]['type'] === '5') {
							$valuemaps_type = 'default';
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
				$output_file = $temp_dir . "/" . "get-template_{$host}_{$date}_{$time}.json";
				$output_array[] = $output_file;
				$output_data = array(
					'host' => $host,
					'name' => $displayname,
					'description' => $description,
					'templates' => $templates,
					'groups' => $link_groups,
					'tags' => $tags,
					'macros' => $macros,
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
	$z_file = "{$output_dir}/get-template_{$date}_{$time}.zip";

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

?>
