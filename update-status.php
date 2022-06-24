#!/usr/bin/php
<?php
///////////////////////////////////////////////////////////////////////////////////
//	Name: update-status.php
//	Description: Update status for host,item,trigger with id.
///////////////////////////////////////////////////////////////////////////////////

//Require
require_once 'conf/config.php';
require_once 'common_function.php';

//Setting timezone
date_default_timezone_set("$timezone");

//Log file settings
$date = date("Ymd");

$log_file = rtrim($log_dir, '/') . "/update-status_{$date}.log";
if (!file_exists($log_dir)) {
	mkdir($log_dir, 0755);
}

//Datafile get
$data_dir = rtrim($data_dir, '/') . "/update-status";

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

//Update status
$count = count($file_list);
$error_count = 0;

for ($i = 0; $i < $count; ++$i) {
	//Processing status display
	processing_status_display($i+1, $count, $error_count);

	//Display adjustment
	echo "\n---------------------------------------";

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
	
	//Update host
	if (isset($data_array['hosts'])) {
		//Processing status display
		echo "\n*** Start Update hosts ***";
		
		//enable host
		if (isset($data_array['hosts']['enable'][0])) {
			//Array declaration
			$enable_hostid = array();

			//Get enable hostid
			$count_enable_hostid = count($data_array['hosts']['enable']);
			for ($j = 0; $j < $count_enable_hostid; ++$j) {
				$enable_hostid[] = array(
					"hostid" => $data_array['hosts']['enable'][$j]
				);
			}

			//Update host status to enable
			$method = 'host.massupdate';
			$params = array(
				"hosts" => $enable_hostid,
				"status" => 0
			);
			$response = api_request($method, $params, $auth, '');
			
			if (isset($response['error'])) {
				//Error count
				$error_count = $error_count+1;

				//Error message output
				$error_message = error_message($response);
				log_output($log_file, $error_message);
			}

			//Processing status display
			echo "\n- Enable hosts is done";
		}
		else {
			//Processing status display
			echo "\n- Enable hosts is nothing";
		}

		//disable host
		if (isset($data_array['hosts']['disable'][0])) {
			//Array declaration
			$disable_hostid = array();

			//Get disable hostid
			$count_disable_hostid = count($data_array['hosts']['disable']);
			for ($j = 0; $j < $count_disable_hostid; ++$j) {
				$disable_hostid[] = array(
					"hostid" => $data_array['hosts']['disable'][$j]
				);
			}

			//Update host status to disable
			$method = 'host.massupdate';
			$params = array(
				"hosts" => $disable_hostid,
				"status" => 1
			);
			$response = api_request($method, $params, $auth, '');
			
			if (isset($response['error'])) {
				//Error count
				$error_count = $error_count+1;

				//Error message output
				$error_message = error_message($response);
				log_output($log_file, $error_message);
			}

			//Processing status display
			echo "\n- Disable hosts is done";
		}
		else {
			//Processing status display
			echo "\n- Disable hosts is nothing";
		}

		//Processing status display
		echo "\n*** End Update hosts ***\n";
	}

	//Update item
	if (isset($data_array['items'])) {
		//Processing status display
		echo "\n*** Start Update items ***\n";

		//enable item
		if (isset($data_array['items']['enable'][0])) {
			//Array declaration
			$enable_itemid = array();

			//Get enable itemid
			$count_enable_itemid = count($data_array['items']['enable']);
			for ($j = 0; $j < $count_enable_itemid; ++$j) {
				
				//Processing status display
				echo "\r- Enable items..." . ($j+1) . "/" . $count_enable_itemid . " (now/max)";

				//Update item status to enable
				$method = 'item.update';
				$params = array(
					"itemid" => $data_array['items']['enable'][$j],
					"status" => 0
				);
				$response = api_request($method, $params, $auth, '');
				
				if (isset($response['error'])) {
					//Error count
					$error_count = $error_count+1;

					//Error message output
					$error_message = error_message($response);
					log_output($log_file, $error_message);

					//Processing skip
					continue;
				}
			}

			//Display adjustment
			echo "\n";
		}
		else {
			//Processing status display
			echo "- Enable items is nothing\n";
		}

		//disable item
		if (isset($data_array['items']['disable'][0])) {
			//Array declaration
			$disable_itemid = array();

			//Get disable itemid
			$count_disable_itemid = count($data_array['items']['disable']);
			for ($j = 0; $j < $count_disable_itemid; ++$j) {
				
				//Processing status display
				echo "\r- Disable items..." . ($j+1) . "/" . $count_disable_itemid . " (now/max)";

				//Update item status to disable
				$method = 'item.update';
				$params = array(
					"itemid" => $data_array['items']['disable'][$j],
					"status" => 1
				);
				$response = api_request($method, $params, $auth, '');
				
				if (isset($response['error'])) {
					//Error count
					$error_count = $error_count+1;

					//Error message output
					$error_message = error_message($response);
					log_output($log_file, $error_message);

					//Processing skip
					continue;
				}
			}
		}
		else {
			//Processing status display
			echo "- Disable items is nothing";
		}

		//Processing status display
		echo "\n*** End Update items ***\n";
	}

	//Update trigger
	if (isset($data_array['triggers'])) {
		//Processing status display
		echo "\n*** Start Update triggers ***\n";

		//enable trigger
		if (isset($data_array['triggers']['enable'][0])) {
			//Array declaration
			$enable_triggerid = array();

			//Get enable triggerid
			$count_enable_triggerid = count($data_array['triggers']['enable']);
			for ($j = 0; $j < $count_enable_triggerid; ++$j) {
				
				//Processing status display
				echo "\r- Enable triggers..." . ($j+1) . "/" . $count_enable_triggerid . " (now/max)";

				//Update trigger status to enable
				$method = 'trigger.update';
				$params = array(
					"triggerid" => $data_array['triggers']['enable'][$j],
					"status" => 0
				);
				$response = api_request($method, $params, $auth, '');
				
				if (isset($response['error'])) {
					//Error count
					$error_count = $error_count+1;

					//Error message output
					$error_message = error_message($response);
					log_output($log_file, $error_message);

					//Processing skip
					continue;
				}
			}
			//Display adjustment
			echo "\n";
		}
		else {
			//Processing status display
			echo "- Enable triggers is nothing\n";
		}

		//disable trigger
		if (isset($data_array['triggers']['disable'][0])) {
			//Array declaration
			$disable_triggerid = array();

			//Get disable triggerid
			$count_disable_triggerid = count($data_array['triggers']['disable']);
			for ($j = 0; $j < $count_disable_triggerid; ++$j) {
				
				//Processing status display
				echo "\r- Disable triggers..." . ($j+1) . "/" . $count_disable_triggerid . " (now/max)";

				//Update trigger status to disable
				$method = 'trigger.update';
				$params = array(
					"triggerid" => $data_array['triggers']['disable'][$j],
					"status" => 1
				);
				$response = api_request($method, $params, $auth, '');
				
				if (isset($response['error'])) {
					//Error count
					$error_count = $error_count+1;

					//Error message output
					$error_message = error_message($response);
					log_output($log_file, $error_message);

					//Processing skip
					continue;
				}
			}
		}
		else {
			//Processing status display
			echo "- Disable triggers is nothing";
		}

		//Processing status display
		echo "\n*** End Update triggers ***\n";
	}

	//Display adjustment
	echo "---------------------------------------\n";
	echo "Finish..." . ($i+1) . "/" . $count .  "/" . $error_count ." (now/max/error)\n\n";
}

//API logout
api_logout($auth);
curl_close($ch);

?>
