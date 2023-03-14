# various-scripts for Zabbix

## Description
Various scripts using Zabbix API. Please use Zabbix for convenient use.

## Require
* Zabbix: 6.0
* OS: RHEL 8
* PHP: 7.2
* Package: php-cli, php-pdo, php-zip, php-json, php-mbstring

## Config Settings
Modify the configuration file (check_related_ac-tr.conf) according to the operating environment.
1. API
* $api_url: Describe the URL of Zabbix frontend URL that executes API. 
* $api_user: Zabbix user that executes API.
* $api_pass: Zabbix user password.

2. Encoding
* $encoding: Character code of output file.(SJIS-win, UTF-8, etc.)

3. Timezone
* $timezone: Execution environment time zone.

4. Directory
* $base_dir: Script expansion directory.
* $log_dir: Output directory of execute log file.
* $data_dir: Data directory for data file for registering and executing data.
* $output_dir: Output directory of export file.

## Usage
1. Placement the data file in data directory for scripts.
2. Modify the config file according to the environment.See Config Settings.
3. Run the php script.
```
./script name
 or
/full_path/script name
```
