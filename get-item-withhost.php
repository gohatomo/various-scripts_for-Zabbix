#!/usr/bin/php
<?php
///////////////////////////////////////////////////////////////////////////////////
//	Name: get-item-withhost.php
//	Description: Get item for host in JSON format.
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

$log_file = rtrim($log_dir, '/') . "/get-item-withhost_{$date}.log";
if (!file_exists($log_dir)) {
	mkdir($log_dir, 0755);
}

//Datafile get
$data_dir = rtrim($data_dir, '/') . "/get-item-withhost";

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
$temp_dir = rtrim($output_dir, '/') . "/get-item-withhost_{$date}_{$time}";

if (!file_exists($temp_dir)) {
	mkdir($temp_dir, 0755, TRUE);	
}

//API login
$ch = curl_setup($api_url);
$auth = api_login($api_user, $api_pass);

//Array declaration
$export_array = array();
$master_item_array = array();
$valuemap_array = array();

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
				),
				'selectInterfaces' => array(
					'interfaceid',
					'ip',
					'dns',
					'useip'
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
			$interfacedata = array();
			$count_hosts = count($response['result']);
			for ($k = 0; $k < $count_hosts; ++$k) {
				$host_listid = $response['result'][$k]['hostid'];
				$host_list[$host_listid] = $host_listid;

				$count_templates = count($response['result'][$k]['parentTemplates']);
				if ($count_templates != 0) {
					for ($l = 0; $l < $count_templates; ++$l) {
						$link_templateid = $response['result'][$k]['parentTemplates'][$l]['templateid'];
						$link_templatename = $response['result'][$k]['parentTemplates'][$l]['name'];

						$link_template[$link_templateid] = $link_templatename;
					}
				}

				$count_interfaces = count($response['result'][$k]['interfaces']);
				if ($count_interfaces != 0) {
					for ($l = 0; $l < $count_interfaces; ++$l) {
						$if_id = $response['result'][$k]['interfaces'][$l]['interfaceid'];
						if ($response['result'][$k]['interfaces'][$l]['useip'] === '0') {
							$if_address = $response['result'][$k]['interfaces'][$l]['dns'];
						}
						elseif ($response['result'][$k]['interfaces'][$l]['useip'] === '1') {
							$if_address = $response['result'][$k]['interfaces'][$l]['ip'];
						}
						$interfacedata[$if_id] = $if_address;
					}
				}
			}

			//Template data get
			if (!empty($link_template)) {
				$templatedata = get_templatedata($link_template);
			}

			//API Request
			$method = 'item.get';
			$params = array(
				'groupids' => $groupid,
				'output' => 'extend',
				'selectHosts' => array(
					'hostid',
					'host',
					'name',
					'status'
				),
				'webitems' => true,
				'selectPreprocessing' => 'extend',
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
			echo "\n*** Start... Get Item data: " . $data_array['groups'][$j] . " ***\n";
			$count_items = count($response['result']);
			for ($k = 0; $k < $count_items; ++$k) {
				//Processing status display
				processing_status_display($k+1, $count_items, $error_count);

				//hostid
				$hostid = $response['result'][$k]['hosts'][0]['hostid'];

				//Exclude template hosts
				if (!isset($host_list[$hostid])) {
					continue;
				}

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

				//itemid
				$itemid = $response['result'][$k]['itemid'];

				//itemname
				$itemname = $response['result'][$k]['name'];

				//itemstatus
				if (isset(C_STATUS[$response['result'][$k]['status']])) {
					$itemstatus = C_STATUS[$response['result'][$k]['status']];
				}
				else {
					$itemstatus = $response['result'][$k]['status'];
				}

				//itemtype
				if (isset(ITEM_TYPE[$response['result'][$k]['type']])) {
					$itemtype = ITEM_TYPE[$response['result'][$k]['type']];
				}
				else {
					$itemtype = $response['result'][$k]['type'];
				}

				//itemkey_
				$itemkey_ = $response['result'][$k]['key_'];

				//delay
				$delay = $response['result'][$k]['delay'];

				//history
				$history = $response['result'][$k]['history'];

				//trend
				$trends = $response['result'][$k]['trends'];

				//value_type
				if (isset(VALUE_TYPE[$response['result'][$k]['value_type']])) {
					$value_type = VALUE_TYPE[$response['result'][$k]['value_type']];
				}
				else {
					$value_type = $response['result'][$k]['value_type'];
				}

				//snmp_oid
				$snmp_oid = $response['result'][$k]['snmp_oid'];

				//units
				$units = $response['result'][$k]['units'];

				//trapper_hosts
				$trapper_hosts = $response['result'][$k]['trapper_hosts'];

				//logtimefmt
				$logtimefmt = $response['result'][$k]['logtimefmt'];

				//templateid
				if ($response['result'][$k]['templateid'] === '0') {
					$link_templatename_item = "";
				}
				else {
					$link_templatename_item = get_link_templatename_item($response['result'][$k]['templateid'], $templatedata);
				}

				//valuemapid
				if ($response['result'][$k]['valuemapid'] === '0') {
					$valuemap = "";
				}
				else {
					if (isset($valuemap_array[$response['result'][$k]['valuemapid']])) {
						$valuemap = $valuemap_array[$response['result'][$k]['valuemapid']];
					}
					else {
						$valuemap = get_valuemapname($response['result'][$k]['valuemapid']);
						$valuemap_array[$response['result'][$k]['valuemapid']] = $valuemap;
					}
				}

				//params
				$params = $response['result'][$k]['params'];

				//ipmi_sensor
				$ipmi_sensor = $response['result'][$k]['ipmi_sensor'];

				//authtype
				if (isset(AUTH_TYPE[$response['result'][$k]['authtype']])) {
					$authtype = AUTH_TYPE[$response['result'][$k]['authtype']];
				}
				else {
					$authtype = $response['result'][$k]['authtype'];
				}

				//username
				$username = $response['result'][$k]['username'];

				//password
				$password = $response['result'][$k]['password'];

				//publickey
				$publickey = $response['result'][$k]['publickey'];

				//privatekey
				$privatekey = $response['result'][$k]['privatekey'];

				//flags
				if (isset(C_FLAGS[$response['result'][$k]['flags']])) {
					$itemflags = C_FLAGS[$response['result'][$k]['flags']];
				}
				else {
					$itemflags = $response['result'][$k]['flags'];
				}

				//interface
				if (isset($interfacedata[$response['result'][$k]['interfaceid']])) {
					$interface = $interfacedata[$response['result'][$k]['interfaceid']];
				}
				else {
					$interface = $response['result'][$k]['interfaceid'];
				}

				//description
				$description = $response['result'][$k]['description'];

				//inventory_link
				if (isset(INVENTORY_LINK[$response['result'][$k]['inventory_link']])) {
					$inventory_link = INVENTORY_LINK[$response['result'][$k]['inventory_link']];
				}
				else {
					$inventory_link = $response['result'][$k]['inventory_link'];
				}

				//jmx_endpoint
				$jmx_endpoint = $response['result'][$k]['jmx_endpoint'];

				//master_item
				if ($response['result'][$k]['master_itemid'] === '0') {
					$master_item = "";
				}
				else {
					if (isset($master_item_array[$response['result'][$k]['master_itemid']])) {
						$master_item = $master_item_array[$response['result'][$k]['master_itemid']];
					}
					else {
						$master_item = get_itemname($response['result'][$k]['master_itemid']);
						$master_item_array[$response['result'][$k]['master_itemid']] = $master_item;
					}
				}

				//timeout
				$timeout = $response['result'][$k]['timeout'];

				//url
				$url = $response['result'][$k]['url'];

				//query_fields
				$query_fields = $response['result'][$k]['query_fields'];

				//posts
				$posts = $response['result'][$k]['posts'];

				//status_codes
				$status_codes = $response['result'][$k]['status_codes'];

				//follow_redirects
				if (isset(FOLLOW_REDIRECTS[$response['result'][$k]['follow_redirects']])) {
					$follow_redirects = FOLLOW_REDIRECTS[$response['result'][$k]['follow_redirects']];
				}
				else {
					$follow_redirects = $response['result'][$k]['follow_redirects'];
				}

				//post_type
				if (isset(POST_TYPE[$response['result'][$k]['post_type']])) {
					$post_type = POST_TYPE[$response['result'][$k]['post_type']];
				}
				else {
					$post_type = $response['result'][$k]['post_type'];
				}

				//http_proxy
				$http_proxy = $response['result'][$k]['http_proxy'];

				//headers
				$headers = $response['result'][$k]['headers'];

				//retrieve_mode
				if (isset(RETRIEVE_MODE[$response['result'][$k]['retrieve_mode']])) {
					$retrieve_mode = RETRIEVE_MODE[$response['result'][$k]['retrieve_mode']];
				}
				else {
					$retrieve_mode = $response['result'][$k]['retrieve_mode'];
				}

				//request_method
				if (isset(REQUEST_METHOD[$response['result'][$k]['request_method']])) {
					$request_method = REQUEST_METHOD[$response['result'][$k]['request_method']];
				}
				else {
					$request_method = $response['result'][$k]['request_method'];
				}

				//output_format
				if (isset(OUTPUT_FORMAT[$response['result'][$k]['output_format']])) {
					$output_format = OUTPUT_FORMAT[$response['result'][$k]['output_format']];
				}
				else {
					$output_format = $response['result'][$k]['output_format'];
				}

				//ssl_cert_file
				$ssl_cert_file = $response['result'][$k]['ssl_cert_file'];

				//ssl_key_file
				$ssl_key_file = $response['result'][$k]['ssl_key_file'];

				//ssl_key_password
				$ssl_key_password = $response['result'][$k]['ssl_key_password'];

				//verify_peer
				if (isset(VERIFY_PEER[$response['result'][$k]['verify_peer']])) {
					$verify_peer = VERIFY_PEER[$response['result'][$k]['verify_peer']];
				}
				else {
					$verify_peer = $response['result'][$k]['verify_peer'];
				}

				//verify_host
				if (isset(VERIFY_HOST[$response['result'][$k]['verify_host']])) {
					$verify_host = VERIFY_HOST[$response['result'][$k]['verify_host']];
				}
				else {
					$verify_host = $response['result'][$k]['verify_host'];
				}

				//allow_traps
				if (isset(ALLOW_TRAPS[$response['result'][$k]['allow_traps']])) {
					$allow_traps = ALLOW_TRAPS[$response['result'][$k]['allow_traps']];
				}
				else {
					$allow_traps = $response['result'][$k]['allow_traps'];
				}

				//parameters
				$parameters = $response['result'][$k]['parameters'];

				//Tag
				$itemtags = $response['result'][$k]['tags'];

				//Preprocessing
				$pre_array = array();
				if (isset($response['result'][$k]['preprocessing'][0]['type'])) {

					$count_pre = count($response['result'][$k]['preprocessing']);
					for ($l = 0; $l < $count_pre; ++$l) {
						//pre_type
						if (isset(PRE_TYPE[$response['result'][$k]['preprocessing'][$l]['type']])) {
							$pre_type = PRE_TYPE[$response['result'][$k]['preprocessing'][$l]['type']];
						}
						else {
							$pre_type = $response['result'][$k]['preprocessing'][$l]['type'];
						}

						//pre_params
						$pre_params = $response['result'][$k]['preprocessing'][$l]['params'];

						//pre_error_handler
						if (isset(PRE_ERROR_HANDLER[$response['result'][$k]['preprocessing'][$l]['error_handler']])) {
							$pre_error_handler = PRE_ERROR_HANDLER[$response['result'][$k]['preprocessing'][$l]['error_handler']];
						}
						else {
							$pre_error_handler = $response['result'][$k]['preprocessing'][$l]['error_handler'];
						}

						//pre_error_handler_params
						$pre_error_handler_params = $response['result'][$k]['preprocessing'][$l]['error_handler_params'];

						//Store preprocessor data in pre_array
						$pre_array[] = array(
							'type' => $pre_type,
							'params' => $pre_params,
							'error_handler' => $pre_error_handler,
							'error_handler_params' => $pre_error_handler_params
						);
					}
				}

				//Store itemdata in export_array
				//Zabbix agent
				if ($response['result'][$k]['type'] === '0') {
					$export_array[$hostid]['items'][] = array(
						'itemid' => $itemid,
						'link_templatename' => $link_templatename_item,
						'flags' => $itemflags,
						'name' => $itemname,
						'type' => $itemtype,
						'key_' => $itemkey_,
						'value_type' => $value_type,
						'interface' => $interface,
						'units' => $units,
						'delay' => $delay,
						'history' => $history,
						'trends' => $trends,
						'valuemap' => $valuemap,
						'inventory_link' => $inventory_link,
						'description' => $description,
						'status' => $itemstatus,
						'tag' => $itemtags,
						'preprocessing' => $pre_array
					);
				}

				//Zabbix trapper
				if ($response['result'][$k]['type'] === '2') {
					$export_array[$hostid]['items'][] = array(
						'itemid' => $itemid,
						'link_templatename' => $link_templatename_item,
						'flags' => $itemflags,
						'name' => $itemname,
						'type' => $itemtype,
						'key_' => $itemkey_,
						'value_type' => $value_type,
						'units' => $units,
						'history' => $history,
						'trends' => $trends,
						'valuemap' => $valuemap,
						'trapper_hosts' => $trapper_hosts,
						'inventory_link' => $inventory_link,
						'description' => $description,
						'status' => $itemstatus,
						'tag' => $itemtags,
						'preprocessing' => $pre_array
					);
				}

				//Simple check
				if ($response['result'][$k]['type'] === '3') {
					$export_array[$hostid]['items'][] = array(
						'itemid' => $itemid,
						'link_templatename' => $link_templatename_item,
						'flags' => $itemflags,
						'name' => $itemname,
						'type' => $itemtype,
						'key_' => $itemkey_,
						'value_type' => $value_type,
						'interface' => $interface,
						'username' => $username,
						'password' => $password,
						'units' => $units,
						'delay' => $delay,
						'history' => $history,
						'trends' => $trends,
						'valuemap' => $valuemap,
						'inventory_link' => $inventory_link,
						'logtimefmt' => $logtimefmt,
						'description' => $description,
						'status' => $itemstatus,
						'tag' => $itemtags,
						'preprocessing' => $pre_array
					);
				}

				//Zabbix internal
				if ($response['result'][$k]['type'] === '5') {
					$export_array[$hostid]['items'][] = array(
						'itemid' => $itemid,
						'link_templatename' => $link_templatename_item,
						'flags' => $itemflags,
						'name' => $itemname,
						'type' => $itemtype,
						'key_' => $itemkey_,
						'value_type' => $value_type,
						'units' => $units,
						'delay' => $delay,
						'history' => $history,
						'trends' => $trends,
						'valuemap' => $valuemap,
						'inventory_link' => $inventory_link,
						'logtimefmt' => $logtimefmt,
						'description' => $description,
						'status' => $itemstatus,
						'tag' => $itemtags,
						'preprocessing' => $pre_array
					);
				}

				//Zabbix agent (active)
				if ($response['result'][$k]['type'] === '7') {
					$export_array[$hostid]['items'][] = array(
						'itemid' => $itemid,
						'link_templatename' => $link_templatename_item,
						'flags' => $itemflags,
						'name' => $itemname,
						'type' => $itemtype,
						'key_' => $itemkey_,
						'value_type' => $value_type,
						'units' => $units,
						'delay' => $delay,
						'history' => $history,
						'trends' => $trends,
						'valuemap' => $valuemap,
						'inventory_link' => $inventory_link,
						'logtimefmt' => $logtimefmt,
						'description' => $description,
						'status' => $itemstatus,
						'tag' => $itemtags,
						'preprocessing' => $pre_array
					);
				}

				//Web item
				if ($response['result'][$k]['type'] === '9') {
					$export_array[$hostid]['items'][] = array(
						'itemid' => $itemid,
						'link_templatename' => $link_templatename_item,
						'flags' => $itemflags,
						'name' => $itemname,
						'type' => $itemtype,
						'key_' => $itemkey_,
						'value_type' => $value_type,
						'units' => $units,
						'delay' => $delay,
						'history' => $history,
						'trends' => $trends,
						'status' => $itemstatus,
						'tag' => $itemtags
					);
				}

				//External check
				if ($response['result'][$k]['type'] === '10') {
					$export_array[$hostid]['items'][] = array(
						'itemid' => $itemid,
						'link_templatename' => $link_templatename_item,
						'flags' => $itemflags,
						'name' => $itemname,
						'type' => $itemtype,
						'key_' => $itemkey_,
						'value_type' => $value_type,
						'interface' => $interface,
						'units' => $units,
						'delay' => $delay,
						'history' => $history,
						'trends' => $trends,
						'valuemap' => $valuemap,
						'inventory_link' => $inventory_link,
						'logtimefmt' => $logtimefmt,
						'description' => $description,
						'status' => $itemstatus,
						'tag' => $itemtags,
						'preprocessing' => $pre_array
					);
				}

				//Database monitor
				if ($response['result'][$k]['type'] === '11') {
					$export_array[$hostid]['items'][] = array(
						'itemid' => $itemid,
						'link_templatename' => $link_templatename_item,
						'flags' => $itemflags,
						'name' => $itemname,
						'type' => $itemtype,
						'key_' => $itemkey_,
						'value_type' => $value_type,
						'username' => $username,
						'password' => $password,
						'params' => $params,
						'units' => $units,
						'delay' => $delay,
						'history' => $history,
						'trends' => $trends,
						'valuemap' => $valuemap,
						'inventory_link' => $inventory_link,
						'logtimefmt' => $logtimefmt,
						'description' => $description,
						'status' => $itemstatus,
						'tag' => $itemtags,
						'preprocessing' => $pre_array
					);
				}

				//IPMI agent
				if ($response['result'][$k]['type'] === '12') {
					$export_array[$hostid]['items'][] = array(
						'itemid' => $itemid,
						'link_templatename' => $link_templatename_item,
						'flags' => $itemflags,
						'name' => $itemname,
						'type' => $itemtype,
						'key_' => $itemkey_,
						'value_type' => $value_type,
						'interface' => $interface,
						'ipmi_sensor' => $ipmi_sensor,
						'units' => $units,
						'delay' => $delay,
						'history' => $history,
						'trends' => $trends,
						'valuemap' => $valuemap,
						'inventory_link' => $inventory_link,
						'logtimefmt' => $logtimefmt,
						'description' => $description,
						'status' => $itemstatus,
						'tag' => $itemtags,
						'preprocessing' => $pre_array
					);
				}

				//SSH agent
				if ($response['result'][$k]['type'] === '13') {
					$export_array[$hostid]['items'][] = array(
						'itemid' => $itemid,
						'link_templatename' => $link_templatename_item,
						'flags' => $itemflags,
						'name' => $itemname,
						'type' => $itemtype,
						'key_' => $itemkey_,
						'value_type' => $value_type,
						'interface' => $interface,
						'authtype' => $authtype,
						'username' => $username,
						'publickey' => $publickey,
						'privatekey' => $privatekey,
						'password' => $password,
						'params' => $params,
						'units' => $units,
						'delay' => $delay,
						'history' => $history,
						'trends' => $trends,
						'valuemap' => $valuemap,
						'inventory_link' => $inventory_link,
						'logtimefmt' => $logtimefmt,
						'description' => $description,
						'status' => $itemstatus,
						'tag' => $itemtags,
						'preprocessing' => $pre_array
					);
				}

				//Telnet agent
				if ($response['result'][$k]['type'] === '14') {
					$export_array[$hostid]['items'][] = array(
						'itemid' => $itemid,
						'link_templatename' => $link_templatename_item,
						'flags' => $itemflags,
						'name' => $itemname,
						'type' => $itemtype,
						'key_' => $itemkey_,
						'value_type' => $value_type,
						'interface' => $interface,
						'username' => $username,
						'password' => $password,
						'params' => $params,
						'units' => $units,
						'delay' => $delay,
						'history' => $history,
						'trends' => $trends,
						'valuemap' => $valuemap,
						'inventory_link' => $inventory_link,
						'logtimefmt' => $logtimefmt,
						'description' => $description,
						'status' => $itemstatus,
						'tag' => $itemtags,
						'preprocessing' => $pre_array
					);
				}

				//Calculated
				if ($response['result'][$k]['type'] === '15') {
					$export_array[$hostid]['items'][] = array(
						'itemid' => $itemid,
						'link_templatename' => $link_templatename_item,
						'flags' => $itemflags,
						'name' => $itemname,
						'type' => $itemtype,
						'key_' => $itemkey_,
						'value_type' => $value_type,
						'params' => $params,
						'units' => $units,
						'delay' => $delay,
						'history' => $history,
						'trends' => $trends,
						'valuemap' => $valuemap,
						'inventory_link' => $inventory_link,
						'logtimefmt' => $logtimefmt,
						'description' => $description,
						'status' => $itemstatus,
						'tag' => $itemtags,
						'preprocessing' => $pre_array
					);
				}

				//JMX agent
				if ($response['result'][$k]['type'] === '16') {
					$export_array[$hostid]['items'][] = array(
						'itemid' => $itemid,
						'link_templatename' => $link_templatename_item,
						'flags' => $itemflags,
						'name' => $itemname,
						'type' => $itemtype,
						'key_' => $itemkey_,
						'value_type' => $value_type,
						'interface' => $interface,
						'jmx_endpoint' => $jmx_endpoint,
						'username' => $username,
						'password' => $password,
						'units' => $units,
						'delay' => $delay,
						'history' => $history,
						'trends' => $trends,
						'valuemap' => $valuemap,
						'inventory_link' => $inventory_link,
						'logtimefmt' => $logtimefmt,
						'description' => $description,
						'status' => $itemstatus,
						'tag' => $itemtags,
						'preprocessing' => $pre_array
					);
				}

				//SNMP trap
				if ($response['result'][$k]['type'] === '17') {
					$export_array[$hostid]['items'][] = array(
						'itemid' => $itemid,
						'link_templatename' => $link_templatename_item,
						'flags' => $itemflags,
						'name' => $itemname,
						'type' => $itemtype,
						'key_' => $itemkey_,
						'value_type' => $value_type,
						'interface' => $interface,
						'units' => $units,
						'history' => $history,
						'trends' => $trends,
						'valuemap' => $valuemap,
						'inventory_link' => $inventory_link,
						'logtimefmt' => $logtimefmt,
						'description' => $description,
						'status' => $itemstatus,
						'tag' => $itemtags,
						'preprocessing' => $pre_array
					);
				}

				//Dependent item
				if ($response['result'][$k]['type'] === '18') {
					$export_array[$hostid]['items'][] = array(
						'itemid' => $itemid,
						'link_templatename' => $link_templatename_item,
						'flags' => $itemflags,
						'name' => $itemname,
						'type' => $itemtype,
						'key_' => $itemkey_,
						'value_type' => $value_type,
						'master_item' => $master_item,
						'units' => $units,
						'history' => $history,
						'trends' => $trends,
						'valuemap' => $valuemap,
						'inventory_link' => $inventory_link,
						'logtimefmt' => $logtimefmt,
						'description' => $description,
						'status' => $itemstatus,
						'tag' => $itemtags,
						'preprocessing' => $pre_array
					);
				}

				//HTTP agent
				if ($response['result'][$k]['type'] === '19') {
					$export_array[$hostid]['items'][] = array(
						'itemid' => $itemid,
						'link_templatename' => $link_templatename_item,
						'flags' => $itemflags,
						'name' => $itemname,
						'type' => $itemtype,
						'key_' => $itemkey_,
						'value_type' => $value_type,
						'url' => $url,
						'query_fields' => $query_fields,
						'request_method' => $request_method,
						'timeout' => $timeout,
						'post_type' => $post_type,
						'posts' => $posts,
						'headers' => $headers,
						'status_codes' => $status_codes,
						'follow_redirects' => $follow_redirects,
						'retrieve_mode' => $retrieve_mode,
						'output_format' => $output_format,
						'http_proxy' => $http_proxy,
						'authtype' => $authtype,
						'username' => $username,
						'password' => $password,
						'verify_peer' => $verify_peer,
						'verify_host' => $verify_host,
						'ssl_cert_file' => $ssl_cert_file,
						'ssl_key_file' => $ssl_key_file,
						'ssl_key_password' => $ssl_key_password,
						'interface' => $interface,
						'units' => $units,
						'history' => $history,
						'trends' => $trends,
						'valuemap' => $valuemap,
						'allow_traps' => $allow_traps,
						'trapper_hosts' => $trapper_hosts,
						'inventory_link' => $inventory_link,
						'logtimefmt' => $logtimefmt,
						'description' => $description,
						'status' => $itemstatus,
						'tag' => $itemtags,
						'preprocessing' => $pre_array
					);
				}

				//SNMP agent
				if ($response['result'][$k]['type'] === '20') {
					$export_array[$hostid]['items'][] = array(
						'itemid' => $itemid,
						'link_templatename' => $link_templatename_item,
						'flags' => $itemflags,
						'name' => $itemname,
						'type' => $itemtype,
						'key_' => $itemkey_,
						'value_type' => $value_type,
						'interface' => $interface,
						'snmp_oid' => $snmp_oid,
						'units' => $units,
						'delay' => $delay,
						'history' => $history,
						'trends' => $trends,
						'valuemap' => $valuemap,
						'inventory_link' => $inventory_link,
						'logtimefmt' => $logtimefmt,
						'description' => $description,
						'status' => $itemstatus,
						'tag' => $itemtags,
						'preprocessing' => $pre_array
					);
				}

				//Script
				if ($response['result'][$k]['type'] === '21') {
					$export_array[$hostid]['items'][] = array(
						'itemid' => $itemid,
						'link_templatename' => $link_templatename_item,
						'flags' => $itemflags,
						'name' => $itemname,
						'type' => $itemtype,
						'key_' => $itemkey_,
						'value_type' => $value_type,
						'parameters' => $parameters,
						'params' => $params,
						'timout' => $timeout,
						'units' => $units,
						'delay' => $delay,
						'history' => $history,
						'trends' => $trends,
						'valuemap' => $valuemap,
						'inventory_link' => $inventory_link,
						'logtimefmt' => $logtimefmt,
						'description' => $description,
						'status' => $itemstatus,
						'tag' => $itemtags,
						'preprocessing' => $pre_array
					);
				}
			}
			//Processing status display
			echo "\n*** End... Get Item data: " . $data_array['groups'][$j] . " ***\n";
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
	$output_file = $temp_dir . "/" . "get-item-withhost_{$output_host}_{$date}_{$time}.json";
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
	$z_file = "{$output_dir}/get-item-withhost_{$date}_{$time}.zip";

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
//Valuemap name
function get_valuemapname($valuemapid) {
	global $auth;
	global $f;
	global $log_file;

	$method = 'valuemap.get';
	$params = array(
		'valuemapids' => $valuemapid,
		'output' => array(
			'name'
		)
	);
	$response = api_request($method, $params, $auth, '');

	if (isset($response['result'][0]['name'])) {
		return $response['result'][0]['name'];
	}
	elseif (isset($response['error'])) {
		$error_message = error_message($response);
		log_output($log_file, $error_message);
		return $valuemapid;
	}
	else {
		$error_message = error_message_unexpected($response);
		log_output($log_file, $error_message);
		return $valuemapid;
	}
}

//Item name
function get_itemname($itemid) {
	global $auth;
	global $f;
	global $log_file;

	$method = 'item.get';
	$params = array(
		'itemids' => $itemid,
		'output' => array(
			'name'
		),
		'webitems' => true
	);
	$response = api_request($method, $params, $auth, '');

	if (isset($response['result'][0]['name'])) {
		return $response['result'][0]['name'];
	}
	elseif (isset($response['error'])) {
		$error_message = error_message($response);
		log_output($log_file, $error_message);
		return $itemid;
	}
	else {
		$error_message = error_message_unexpected($response);
		log_output($log_file, $error_message);
		return $itemid;
	}
}

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
					'selectItems' => array(
						'itemid',
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

				//Template item data
				$count_items = count($response['result'][0]['items']);
				if ($count_items != 0) {
					for ($i = 0; $i < $count_items; ++$i) {
						$itemid = $response['result'][0]['items'][$i]['itemid'];

						$templatedata['items'][$itemid]['name'] = $templatename;
						$templatedata['items'][$itemid]['templateid'] = $response['result'][0]['items'][$i]['templateid'];
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

//Link Template name of item
function get_link_templatename_item($itemid, $templatedata) {
	while (true) {
		if ($templatedata['items'][$itemid]['templateid'] === '0') {
			$link_templatename = $templatedata['items'][$itemid]['name'];
			break;
		}
		else {
			$itemid = $templatedata['items'][$itemid]['templateid'];
		}
	}
	return $link_templatename;
}

?>
