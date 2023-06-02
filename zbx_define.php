<?php

//common status
define('C_STATUS',
	array(
		'0' => 'enable',
		'1' => 'disable',
		'3' => 'none',
	)
);

//common flags
define('C_FLAGS',
	array(
		'0' => 'plain',
		'4' => 'discoverd'
	)
);

//common security_level
define('C_SECURITY_LEVEL',
	array(
		'0' => 'noAuthNoPriv',
		'1' => 'authNoPriv',
		'2' => 'authPriv'
	)
);

//common authprotocol
define('C_AUTHPROTOCOL',
	array(
		'0' => 'MD5',
		'1' => 'SHA1',
		'2' => 'SHA224',
		'3' => 'SHA256',
		'4' => 'SHA384',
		'5' => 'SHA512'
	)
);

//common privprotocol
define('C_PRIVPROTOCOL',
	array(
		'0' => 'DES',
		'1' => 'AES128',
		'2' => 'AES192',
		'3' => 'AES256',
		'4' => 'AES192C',
		'5' => 'AES256C'
	)
);

//common tls_connect
define('C_TLC_CONNECT',
	array(
		'1' => 'no',
		'2' => 'psk',
		'4' => 'certificate'
	)
);

//common tls_accept
define('C_TLC_ACCEPT',
	array(
		'1' => 'no',
		'2' => 'psk',
		'3' => 'no,psk',
		'4' => 'certificate',
		'5' => 'no,certificate',
		'6' => 'psk,certificate',
		'7' => 'no,psk,certificate'
	)
);

//ipmi_authtype
define('IPMI_AUTHTYPE',
	array(
		'-1' => 'default',
		'0' => 'none',
		'1' => 'MD2',
		'2' => 'MD5',
		'4' => 'straight',
		'5' => 'OEM',
		'6' => 'RMCP'
	)
);

//ipmi_privilege
define('IPMI_PRIVILEGE',
	array(
		'1' => 'callback',
		'2' => 'user',
		'3' => 'operator',
		'4' => 'admin',
		'5' => 'OEM'
	)
);

//valuemaps_type
define('VALUEMAPS_TYPE',
	array(
		'0' => '=',
		'1' => '>=',
		'2' => '<=',
		'3' => 'range',
		'4' => 'regexp',
		'5' => 'default'
	)
);

//inventory_mode
define('INVENTORY_MODE',
	array(
		'-1' => 'disable',
		'0' => 'manual',
		'1' => 'auto'
	)
);

//interface_type
define('INTERFACE_TYPE',
	array(
		'1' => 'agent',
		'2' => 'snmp',
		'3' => 'ipmi',
		'4' => 'jmx'
	)
);

//interface_main
define('INTERFACE_MAIN',
	array(
		'0' => 'not default',
		'1' => 'default'
	)
);

//interface_useip
define('INTERFACE_USEIP',
	array(
		'0' => 'dns',
		'1' => 'ip'
	)
);

//interface_bulk
define('INTERFACE_BULK',
	array(
		'0' => 'off',
		'1' => 'on'
	)
);

//itemtype
define('ITEM_TYPE',
	array(
		'0' => 'Zabbix agent',
		'2' => 'Zabbix trapper',
		'3' => 'Simple check',
		'5' => 'Zabbix internal',
		'7' => 'Zabbix agent (active)',
		'9' => 'Web item',
		'10' => 'External check',
		'11' => 'Database monitor',
		'12' => 'IPMI agent',
		'13' => 'SSH agent',
		'14' => 'Telnet agent',
		'15' => 'Calculated',
		'16' => 'JMX agent',
		'17' => 'SNMP trap',
		'18' => 'Dependent item',
		'19' => 'HTTP agent',
		'20' => 'SNMP agent',
		'21' => 'Script'
	)
);

//value_type
define('VALUE_TYPE',
	array(
		'0' => 'numeric float',
		'1' => 'character',
		'2' => 'log',
		'3' => 'numeric unsigned',
		'4' => 'text',
	)
);

//authtype
define('AUTH_TYPE',
	array(
		'0' => 'none',
		'1' => 'basic',
		'2' => 'NTLM',
		'3' => 'Kerberos',
		'4' => 'Digest',
	)
);

//pre_type
define('PRE_TYPE',
	array(
		'1' => 'Custom multiplier',
		'2' => 'Right trim',
		'3' => 'Left trim',
		'4' => 'Trim',
		'5' => 'Regular expression',
		'6' => 'Boolean to decimal',
		'7' => 'Octal to decimal',
		'8' => 'Hexadecimal to decimal',
		'9' => 'Simple change',
		'10' => 'Change per second',
		'11' => 'XML XPath',
		'12' => 'JSONPath',
		'13' => 'In range',
		'14' => 'Matches regular expression',
		'15' => 'Does not match regular expression',
		'16' => 'Check for error in JSON',
		'17' => 'Check for error in XML',
		'18' => 'Check for error using regular expression',
		'19' => 'Discard unchanged',
		'20' => 'Discard unchanged with heartbeat',
		'21' => 'JavaScript',
		'22' => 'Prometheus pattern',
		'23' => 'Prometheus to JSON',
		'24' => 'CSV to JSON',
		'25' => 'Replace',
		'26' => 'Check unsupported',
		'27' => 'XML to JSON'
	)
);

//error_handler
define('PRE_ERROR_HANDLER',
	array(
		'0' => 'Default',
		'1' => 'Discard value',
		'2' => 'Set custom value',
		'3' => 'Set custom error message'
	)
);

//post_type
define('POST_TYPE',
	array(
		'0' => 'Raw data',
		'2' => 'JSON data',
		'3' => 'XML data'
	)
);

//follow_redirects
define('FOLLOW_REDIRECTS',
	array(
		'0' => 'Do not follow redirects',
		'1' => 'Fllow redirects'
	)
);

//retrieve_mode
define('RETRIEVE_MODE',
	array(
		'0' => 'Body',
		'1' => 'Headers',
		'2' => 'Both body and headers'
	)
);

//request_method
define('REQUEST_METHOD',
	array(
		'0' => 'GET',
		'1' => 'POST',
		'2' => 'PUT',
		'3' => 'HEAD'
	)
);

//output_format
define('OUTPUT_FORMAT',
	array(
		'0' => 'Store raw',
		'1' => 'Convert to JSON'
	)
);

//verify_peer
define('VERIFY_PEER',
	array(
		'0' => 'Do not validate',
		'1' => 'Validate'
	)
);

//verify_host
define('VERIFY_HOST',
	array(
		'0' => 'Do not validate',
		'1' => 'Validate'
	)
);

//allow_traps
define('ALLOW_TRAPS',
	array(
		'0' => 'Do not allow',
		'1' => 'Allow'
	)
);

//inventory link
define('INVENTORY_LINK',
	array(
		'0' => 'none',
		'1' => 'type',
		'2' => 'type_full',
		'3' => 'name',
		'4' => 'alias',
		'5' => 'os',
		'6' => 'os_full',
		'7' => 'os_short',
		'8' => 'serialno_a',
		'9' => 'serialno_b',
		'10' => 'tag',
		'11' => 'asset_tag',
		'12' => 'macaddress_a',
		'13' => 'macaddress_b',
		'14' => 'hardware',
		'15' => 'hardware_full',
		'16' => 'software',
		'17' => 'software_full',
		'18' => 'software_app_a',
		'19' => 'software_app_b',
		'20' => 'software_app_c',
		'21' => 'software_app_d',
		'22' => 'software_app_e',
		'23' => 'contact',
		'24' => 'location',
		'25' => 'location_lat',
		'26' => 'location_lon',
		'27' => 'notes',
		'28' => 'chassis',
		'29' => 'model',
		'30' => 'hw_arch',
		'31' => 'vendor',
		'32' => 'contact_number',
		'33' => 'installer_name',
		'34' => 'deployment_status',
		'35' => 'url_a',
		'36' => 'url_b',
		'37' => 'url_c',
		'38' => 'host_networks',
		'39' => 'host_netmask',
		'40' => 'host_router',
		'41' => 'oob_ip',
		'42' => 'oob_netmask',
		'43' => 'oob_router',
		'44' => 'date_hw_purchase',
		'45' => 'date_hw_install',
		'46' => 'date_hw_expiry',
		'47' => 'date_hw_decomm',
		'48' => 'site_address_a',
		'49' => 'site_address_b',
		'50' => 'site_address_c',
		'51' => 'site_city',
		'52' => 'site_state',
		'53' => 'site_country',
		'54' => 'site_zip',
		'55' => 'site_rack',
		'56' => 'site_notes',
		'57' => 'poc_1_name',
		'58' => 'poc_1_email',
		'59' => 'poc_1_phone_a',
		'60' => 'poc_1_phone_b',
		'61' => 'poc_1_cell',
		'62' => 'poc_1_screen',
		'63' => 'poc_1_notes',
		'64' => 'poc_2_name',
		'65' => 'poc_2_email',
		'66' => 'poc_2_phone_a',
		'67' => 'poc_2_phone_b',
		'68' => 'poc_2_cell',
		'69' => 'poc_2_screen',
		'70' => 'poc_2_notes',
	)
);


//severity
define('SEVERITY',
	array(
		'0' => 'Not classified',
		'1' => 'Information',
		'2' => 'Warning',
		'3' => 'Average',
		'4' => 'High',
		'5' => 'Disaster',
	)
);

//recovery_mode
define('RECOVERY_MODE',
	array(
		'0' => 'Expression',
		'1' => 'Recovery expression',
		'2' => 'None'
	)
);

//trigger_type
define('TRIGGER_TYPE',
	array(
		'0' => 'Single',
		'1' => 'Multiple'
	)
);

//correlation_mode
define('CORRELATION_MODE',
	array(
		'0' => 'All problems',
		'1' => 'All problems if tag values match'
	)
);

//manual_close
define('MANUAL_CLOSE',
	array(
		'0' => 'Do not allow',
		'1' => 'Allow'
	)
);

//macro_type
define('MACRO_TYPE',
	array(
		'0' => 'text',
		'1' => 'secret',
		'2' => 'vault'
	)
);

?>
