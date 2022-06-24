#!/usr/bin/php
<?php
///////////////////////////////////////////////////////////////////////////////////
//	Name: get-id-withhost.php
//	Description: Get ID for host, item and trigger in JSON format.
///////////////////////////////////////////////////////////////////////////////////

//Require
require_once 'conf/config.php';
require_once 'common_function.php';

//Setting timezone
date_default_timezone_set("$timezone");

//Log file settings
$date = date("Ymd");
$time = date("His");

$log_file = rtrim($log_dir, '/') . "/get-id-withhost_{$date}.log";
if (!file_exists($log_dir)) {
	mkdir($log_dir, 0755);
}

//Datafile get
$data_dir = rtrim($data_dir, '/') . "/get-id-withhost";

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
$temp_dir = rtrim($output_dir, '/') . "/get-id-withhost_{$date}_{$time}";

if (!file_exists($temp_dir)) {
	mkdir($temp_dir, 0755, TRUE);	
}

//API login
$ch = curl_setup($api_url);
$auth = api_login($api_user, $api_pass);

//Array declaration
$export_array = array();

//Display adjustment
echo "\n";

//Get ID and sort by hostid.
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
	
	//ID get
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
			$method = 'item.get';
			$params = array(
				'groupids' => $groupid,
				'output' => array(
					'itemid',
					'name',
					'status'
				),
				'selectHosts' => array(
					'hostid',
					'host',
					'name',
					'status'
				),
				'selectTriggers' => array(
					'triggerid',
					'description',
					'status'
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

			//Store array
			$count_items = count($response['result']);
			for ($k = 0; $k < $count_items; ++$k) {
				//hostid
				$hostid = $response['result'][$k]['hosts'][0]['hostid'];

				//host
				$host = $response['result'][$k]['hosts'][0]['host'];

				//displayname
				$displayname = $response['result'][$k]['hosts'][0]['name'];

				//hoststatus
				if ($response['result'][$k]['hosts'][0]['status'] === '0') {
					$hoststatus = 'enable';
				}
				elseif ($response['result'][$k]['hosts'][0]['status'] === '1') {
					$hoststatus = 'disable';
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

				//itemid
				$itemid = $response['result'][$k]['itemid'];

				//itemname
				$itemname = $response['result'][$k]['name'];

				//itemstatus
				if ($response['result'][$k]['status'] === '0') {
					$itemstatus = 'enable';
				}
				elseif ($response['result'][$k]['status'] === '1') {
					$itemstatus = 'disable';
				}
				else {
					$itemstatus = $response['result'][$k]['status'];
				}

				//triggers
				$link_triggerid = array();
				$count_triggers = count($response['result'][$k]['triggers']);
				for ($l = 0; $l < $count_triggers; ++$l) {

					//triggerid
					$triggerid = $response['result'][$k]['triggers'][$l]['triggerid'];

					//description
					$description = $response['result'][$k]['triggers'][$l]['description'];

					//triggerstatus
					if ($response['result'][$k]['triggers'][$l]['status'] === '0') {
						$triggerstatus = 'enable';
					}
					elseif ($response['result'][$k]['triggers'][$l]['status'] === '1') {
						$triggerstatus = 'disable';
					}
					else {
						$triggerstatus = $response['result'][$k]['triggers'][$l]['status'];
					}

					//Store triggerid in temporary array
					$link_triggerid[] = $triggerid;
					
					//Store triggerdata in export_array
					$export_array[$hostid]['triggers'][] = array(
						'triggerid' => $triggerid,
						'description' => $description,
						'status' => $triggerstatus
					);
				}

				//Store itemdata in export_array
				$export_array[$hostid]['items'][] = array(
					'itemid' => $itemid,
					'name' => $itemname,
					'status' => $itemstatus,
					'link_triggerid' => $link_triggerid
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
			. "message:\"" . "groups is not set." . "\", "
			. "file:\"" . $file_list[$i] . "\"";
		log_output($log_file, $error_message);
		
		//Processing skip
		continue;
	}
}

//File output
foreach ($export_array as $key => $value) {
	$output_host = $value['hosts']['host'];
	$output_file = $temp_dir . "/" . "get-id-withhost_{$output_host}_{$date}_{$time}.json";
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
	$z_file = "{$output_dir}/get-id-withhost_{$date}_{$time}.zip";

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
