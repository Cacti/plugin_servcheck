<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2025 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

function plugin_servcheck_install() {
	api_plugin_register_hook('servcheck', 'draw_navigation_text', 'plugin_servcheck_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('servcheck', 'config_arrays',        'plugin_servcheck_config_arrays',        'setup.php');
	api_plugin_register_hook('servcheck', 'poller_bottom',        'plugin_servcheck_poller_bottom',        'setup.php');
	api_plugin_register_hook('servcheck', 'replicate_out',        'servcheck_replicate_out',               'setup.php');
	api_plugin_register_hook('servcheck', 'config_settings',      'servcheck_config_settings',             'setup.php');

	api_plugin_register_realm('servcheck', 'servcheck_test.php,servcheck_restapi.php,servcheck_credential.php,servcheck_curl_code.php,servcheck_proxy.php,servcheck_ca.php', __('Service Check Admin', 'servcheck'), 1);

	plugin_servcheck_setup_table();
}

function plugin_servcheck_uninstall() {
	db_execute('DROP TABLE IF EXISTS plugin_servcheck_test');
	db_execute('DROP TABLE IF EXISTS plugin_servcheck_log');
	db_execute('DROP TABLE IF EXISTS plugin_servcheck_proxies');
	db_execute('DROP TABLE IF EXISTS plugin_servcheck_proxy');
	db_execute('DROP TABLE IF EXISTS plugin_servcheck_processes');
	db_execute('DROP TABLE IF EXISTS plugin_servcheck_contacts');
	db_execute('DROP TABLE IF EXISTS plugin_servcheck_ca');
	db_execute('DROP TABLE IF EXISTS plugin_servcheck_restapi_method');
	db_execute('DROP TABLE IF EXISTS plugin_servcheck_credential');
}

function plugin_servcheck_check_config() {
	// Here we will check to ensure everything is configured
	plugin_servcheck_upgrade();

	return true;
}

function plugin_servcheck_upgrade() {
	global $config;

	require_once(__DIR__ . '/includes/functions.php');

	$info = plugin_servcheck_version();
	$new  = $info['version'];
	$old  = db_fetch_cell('SELECT version FROM plugin_config WHERE directory="servcheck"');

	db_execute_prepared('UPDATE plugin_realms
		SET file = ?
		WHERE file LIKE "%servcheck_test.php%"',
		['servcheck_test.php,servcheck_restapi.php,servcheck_credential.php,servcheck_curl_code.php,servcheck_proxy.php,servcheck_ca.php']);
	api_plugin_register_hook('servcheck', 'replicate_out', 'servcheck_replicate_out', 'setup.php', '1');
	api_plugin_register_hook('servcheck', 'config_settings', 'servcheck_config_settings', 'setup.php', '1');

	if (cacti_version_compare($old, '0.3', '<')) {
		if (!db_column_exists('plugin_servcheck_test', 'ipaddress')) {
			db_add_column('plugin_servcheck_test', ['name' => 'ipaddress', 'type' => 'varchar(46)', 'NULL' => false, 'default' => '', 'after' => 'hostname']);
		}

		if (!db_column_exists('plugin_servcheck_test', 'external_id')) {
			db_add_column('plugin_servcheck_test', ['name' => 'external_id', 'type' => 'varchar(20)', 'NULL' => false, 'default' => '', 'after' => 'notes']);
		}

		db_execute('DROP TABLE IF EXISTS plugin_servcheck_contacts');

		// 0.3 contains a lot of changes. I tried to convert old data but for sure make a backup

		db_execute('CREATE TABLE plugin_servcheck_test_backup AS SELECT * FROM plugin_servcheck_test');
		db_execute('CREATE TABLE plugin_servcheck_log_backup AS SELECT * FROM plugin_servcheck_log');
		db_execute('CREATE TABLE plugin_servcheck_proxies_backup AS SELECT * FROM plugin_servcheck_proxies');

		if (db_table_exists('plugin_servcheck_restapi_method')) {
			db_execute('CREATE TABLE plugin_servcheck_restapi_method_backup AS SELECT * FROM plugin_servcheck_restapi_method');
		}

		if (db_column_exists('plugin_servcheck_test', 'display_name')) {
			db_execute('ALTER TABLE plugin_servcheck_test RENAME COLUMN display_name TO name');
		}

		if (db_column_exists('plugin_servcheck_test', 'ca')) {
			db_execute('ALTER TABLE plugin_servcheck_test RENAME COLUMN ca TO ca_id');
		}

		if (db_column_exists('plugin_servcheck_test', 'proxy_server')) {
			db_execute('ALTER TABLE plugin_servcheck_test RENAME COLUMN proxy_server TO proxy_id');
		}

		if (db_column_exists('plugin_servcheck_test', 'timeout_trigger')) {
			db_execute('ALTER TABLE plugin_servcheck_test RENAME COLUMN timeout_trigger TO duration_trigger');
			db_execute('ALTER TABLE plugin_servcheck_test MODIFY duration_trigger decimal(4,2) default "0"');
		}

		api_plugin_db_add_column('servcheck', 'plugin_servcheck_test', ['name' => 'duration_count', 'type' => 'int(3)', 'NULL' => false, 'unsigned' => true, 'default' => '3', 'after' => 'duration_trigger']);
		api_plugin_db_add_column('servcheck', 'plugin_servcheck_test', ['name' => 'snmp_oid', 'type' => 'varchar(255)', 'NULL' => false, 'default' => '']);
		api_plugin_db_add_column('servcheck', 'plugin_servcheck_test', ['name' => 'ssh_command', 'type' => 'varchar(255)', 'NULL' => false, 'default' => '']);
		api_plugin_db_add_column('servcheck', 'plugin_servcheck_test', ['name' => 'cred_id', 'type' => 'int(11)', 'NULL' => false, 'unsigned' => true, 'default' => '0']);
		api_plugin_db_add_column('servcheck', 'plugin_servcheck_test', ['name' => 'triggered_duration', 'type' => 'int(11)', 'NULL' => false, 'unsigned' => true, 'default' => '0']);
		db_add_column('plugin_servcheck_test', ['name' => 'attempt', 'type' => 'int(2)', 'NULL' => false, 'default' => '3', 'after' => 'type']);
		db_add_column('plugin_servcheck_test', ['name' => 'notify', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on']);

		db_execute('ALTER TABLE plugin_servcheck_log MODIFY curl_return_code int(3) default NULL');
		db_execute('ALTER TABLE plugin_servcheck_log MODIFY cert_expire timestamp default "0000-00-00 00:00:00"');
		db_add_column('plugin_servcheck_log', ['name' => 'attempt', 'type' => 'int(2)', 'NULL' => false, 'default' => '0', 'after' => 'enabled']);

		$exist = db_fetch_cell("SELECT COUNT(*)
			FROM information_schema.tables
			WHERE table_schema = SCHEMA()
			AND table_name = 'plugin_servcheck_proxies'");

		if ($exist) {
			db_execute('ALTER TABLE plugin_servcheck_proxies RENAME TO plugin_servcheck_proxy');
		}

		api_plugin_db_add_column('servcheck', 'plugin_servcheck_proxy', ['name' => 'cred_id', 'type' => 'int(11)', 'NULL' => false, 'unsigned' => true, 'default' => '0']);

		$data              = [];
		$data['columns'][] = ['name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true];
		$data['columns'][] = ['name' => 'name', 'type' => 'varchar(100)', 'NULL' => true, 'default' => ''];
		$data['columns'][] = ['name' => 'type', 'type' => "enum('userpass','basic','apikey', 'oauth2', 'cookie', 'snmp','snmp3','sshkey')", 'NULL' => false, 'default' => 'userpass'];
		$data['columns'][] = ['name' => 'data', 'type' => 'text', 'NULL' => true, 'default' => ''];
		$data['primary']   = 'id';
		$data['type']      = 'InnoDB';
		$data['comment']   = 'Holds Credentials';
		api_plugin_db_table_create('servcheck', 'plugin_servcheck_credential', $data);

		// convert log data from 0.2 version

		if (!db_column_exists('plugin_servcheck_log', 'duration')) {
			api_plugin_db_add_column('servcheck', 'plugin_servcheck_log', ['name' => 'curl_response', 'type' => 'text', 'NULL' => true, 'default' => null]);
			api_plugin_db_add_column('servcheck', 'plugin_servcheck_log', ['name' => 'duration', 'type' => 'float', 'NULL' => false, 'default' => 0]);

			db_execute('UPDATE plugin_servcheck_log SET total_time = 0  WHERE total_time IS NULL');
			db_execute('UPDATE plugin_servcheck_log SET duration = total_time');
			db_execute("UPDATE plugin_servcheck_log SET curl_response = CONCAT(
				'HTTP code: ', http_code, ', ',
				'DNS time: ', ROUND(namelookup_time,3), ', ',
				'Conn. time: ', ROUND(connect_time,3), ', ',
				'Redir. time: ', ROUND(redirect_time,3), ', ',
				'Redir. count: ', redirect_count, ', ',
				'Download: ', ROUND(size_download,3), ', ',
				'Speed: ', ROUND(speed_download,3), ', ',
				'CURL code: ', curl_return_code
				)");

			db_remove_column('plugin_servcheck_log', 'http_code');
			db_remove_column('plugin_servcheck_log', 'total_time');
			db_remove_column('plugin_servcheck_log', 'namelookup_time');
			db_remove_column('plugin_servcheck_log', 'connect_time');
			db_remove_column('plugin_servcheck_log', 'redirect_time');
			db_remove_column('plugin_servcheck_log', 'redirect_count');
			db_remove_column('plugin_servcheck_log', 'size_download');
			db_remove_column('plugin_servcheck_log', 'speed_download');
			db_remove_column('plugin_servcheck_log', 'curl_return_code');
		}

		// convert credentials to separated tab

		$records = db_fetch_assoc("SELECT * FROM plugin_servcheck_test
			WHERE username != '' OR password !='' AND type != 'restapi'");

		if (cacti_sizeof($records)) {
			foreach ($records as $record) {
				$cred             = [];
				$cred['type']     = 'userpass';
				$cred['username'] = servcheck_show_text($record['username']);
				$cred['password'] = servcheck_show_text($record['password']);

				$enc = servcheck_encrypt_credential($cred);

				db_execute_prepared('INSERT INTO plugin_servcheck_credential
					(name, type, data) VALUES (?, ?, ?)',
					['upgrade/convert_test_' . $record['id'], $cred['type'], $enc]);

				db_execute_prepared('UPDATE plugin_servcheck_test
					SET cred_id = ? WHERE id = ?',
					[db_fetch_insert_id(), $record['id']]);
			}
		}

		$records = db_fetch_assoc("SELECT * FROM plugin_servcheck_proxy WHERE username != '' OR password !=''");

		if (cacti_sizeof($records)) {
			foreach ($records as $record) {
				$cred             = [];
				$cred['type']     = 'userpass';
				$cred['username'] = $record['username'];
				$cred['password'] = $record['password'];

				$enc = servcheck_encrypt_credential($cred);

				db_execute_prepared('INSERT INTO plugin_servcheck_credential
					(name, type, data) VALUES (?, ?, ?)',
					['upgrade/convert_proxy_' . $record['id'], $cred['type'], $enc]);

				db_execute_prepared('UPDATE plugin_servcheck_proxy
					SET cred_id = ? WHERE id = ?',
					[db_fetch_insert_id(), $record['id']]);
			}
		}

		if (db_table_exists('plugin_servcheck_restapi_method')) {
			$records = db_fetch_assoc('SELECT * FROM plugin_servcheck_restapi_method');

			if (cacti_sizeof($records)) {
				foreach ($records as $record) {
					if ($record['type'] == 'no') {
						continue;
					}

					$cred = [];

					if ($record['type'] == 'basic') {
						$cred['type']     = 'basic';
						$cred['username'] = servcheck_show_text($record['username']);
						$cred['password'] = servcheck_show_text($record['password']);
						$cred['data_url'] = $record['data_url'];
					} elseif ($record['type'] == 'apikey') {
						$cred['type']          = 'apikey';
						$cred['option_apikey'] = 'post';
						$cred['token_name']    = $record['username'];
						$cred['data_url']      = $record['data_url'];
						$cred['token_value']   = servcheck_show_text($record['cred_value']);
					} elseif ($record['type'] == 'oauth2') {
						$cred['type']                = 'oauth2';
						$cred['oauth_client_id']     = servcheck_show_text($record['username']);
						$cred['oauth_client_secret'] = servcheck_show_text($record['password']);
						$cred['token_value']         = servcheck_show_text($record['cred_value']);
						$cred['token_name']          = $record['cred_name'];
						$cred['cred_valiedity']      = $record['cred_validity'];
						$cred['data_url']            = $record['data_url'];
						$cred['login_url']           = $record['login_url'];
					} elseif ($record['type'] == 'cookie') {
						$cred['type']          = 'cookie';
						$cred['option_cookie'] = 'json';
						$cred['username']      = servcheck_show_text($record['username']);
						$cred['password']      = servcheck_show_text($record['password']);
						$cred['data_url']      = $record['data_url'];
						$cred['login_url']     = $record['login_url'];
					}

					$enc = servcheck_encrypt_credential($cred);

					$test_id = db_fetch_cell_prepared('SELECT id FROM plugin_servcheck_test
						WHERE restapi_id = ? LIMIT 1',
						[$record['id']]);

					db_execute_prepared('INSERT INTO plugin_servcheck_credential
						(name, type, data) VALUES (?, ?, ?)',
						['upgrade/convert_restapi_' . $record['id'], $cred['type'], $enc]);

					db_execute_prepared('UPDATE plugin_servcheck_test
						set type = ?, cred_id = ?
						WHERE id = ?',
						['rest_' . $cred['type'], db_fetch_insert_id(), $test_id]);
				}
			}
		}

		db_remove_column('plugin_servcheck_test', 'username');
		db_remove_column('plugin_servcheck_test', 'password');
		db_remove_column('plugin_servcheck_test', 'restapi_id');
		db_remove_column('plugin_servcheck_proxy', 'username');
		db_remove_column('plugin_servcheck_proxy', 'password');

		db_execute('DROP TABLE IF EXISTS plugin_servcheck_processes');
	}

	if (cacti_version_compare($old, '0.4', '<')) {
		if (db_column_exists('plugin_servcheck_test', 'lastcheck')) {
			db_execute('ALTER TABLE plugin_servcheck_test RENAME COLUMN lastcheck TO last_check');
		}

		if (db_column_exists('plugin_servcheck_log', 'lastcheck')) {
			db_execute('ALTER TABLE plugin_servcheck_log RENAME COLUMN lastcheck TO last_check');
		}

		if (!db_column_exists('plugin_servcheck_test', 'cpu_user')) {
			api_plugin_db_add_column('servcheck', 'plugin_servcheck_test', ['name' => 'cpu_user', 'type' => 'double', 'NULL' => false, 'default' => '0', 'after' => 'triggered_duration']);
			api_plugin_db_add_column('servcheck', 'plugin_servcheck_test', ['name' => 'cpu_system', 'type' => 'double', 'NULL' => false, 'default' => '0', 'after' => 'cpu_user']);
			api_plugin_db_add_column('servcheck', 'plugin_servcheck_test', ['name' => 'memory', 'type' => 'int(11)', 'NULL' => false, 'default' => '0', 'after' => 'cpu_system']);
			api_plugin_db_add_column('servcheck', 'plugin_servcheck_test', ['name' => 'last_result', 'type' => 'varchar(20)', 'NULL' => false, 'default' => 'not yet', 'after' => 'last_exp_notify']);
			api_plugin_db_add_column('servcheck', 'plugin_servcheck_test', ['name' => 'last_result_search', 'type' => 'varchar(20)', 'NULL' => false, 'default' => 'not yet', 'after' => 'last_result']);
			api_plugin_db_add_column('servcheck', 'plugin_servcheck_test', ['name' => 'last_attempt', 'type' => 'int(2)', 'NULL' => false, 'default' => '0', 'after' => 'last_result_search']);
			api_plugin_db_add_column('servcheck', 'plugin_servcheck_test', ['name' => 'last_error', 'type' => 'varchar(256)', 'NULL' => true, 'default' => 'NULL', 'after' => 'last_attempt']);
			api_plugin_db_add_column('servcheck', 'plugin_servcheck_test', ['name' => 'run_script', 'type' => 'varchar(2)', 'NULL' => false, 'default' => 'on', 'after' => 'attempt']);
		}

		if (!db_column_exists('plugin_servcheck_test', 'last_duration')) {
			api_plugin_db_add_column('servcheck', 'plugin_servcheck_test', ['name' => 'last_duration', 'type' => 'float', 'NULL' => false, 'default' => '0', 'after' => 'memory']);
		}

		if (!db_column_exists('plugin_servcheck_log', 'cpu_user')) {
			api_plugin_db_add_column('servcheck', 'plugin_servcheck_log', ['name' => 'cpu_user', 'type' => 'double', 'NULL' => false, 'default' => '0', 'after' => 'duration']);
			api_plugin_db_add_column('servcheck', 'plugin_servcheck_log', ['name' => 'cpu_system', 'type' => 'double', 'NULL' => false, 'default' => '0', 'after' => 'cpu_user']);
			api_plugin_db_add_column('servcheck', 'plugin_servcheck_log', ['name' => 'memory', 'type' => 'int(11)', 'NULL' => false, 'default' => '0', 'after' => 'cpu_system']);
			api_plugin_db_add_column('servcheck', 'plugin_servcheck_log', ['name' => 'returned_data_size', 'type' => 'int(11)', 'NULL' => false, 'default' => '0', 'after' => 'memory']);
		}
	}

	// Set the new version
	db_execute_prepared("UPDATE plugin_config
		SET version = ?, author = ?, webpage = ?
		WHERE directory = 'servcheck'",
		[$info['version'], $info['author'], $info['homepage']]
	);

	return true;
}

function plugin_servcheck_version() {
	global $config;
	$info = parse_ini_file($config['base_path'] . '/plugins/servcheck/INFO', true);

	return $info['info'];
}

function plugin_servcheck_setup_table() {
	$data              = [];
	$data['columns'][] = ['name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true];
	$data['columns'][] = ['name' => 'type', 'type' => 'varchar(30)', 'NULL' => false, 'default' => 'web_http'];
	$data['columns'][] = ['name' => 'notify', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'];
	$data['columns'][] = ['name' => 'name', 'type' => 'varchar(64)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'poller_id', 'type' => 'int(11)', 'NULL' => false, 'unsigned' => true, 'default' => '1'];
	$data['columns'][] = ['name' => 'enabled', 'type' => 'varchar(2)', 'NULL' => false, 'default' => 'on'];
	$data['columns'][] = ['name' => 'attempt', 'type' => 'int(2)', 'NULL' => false, 'default' => '3'];
	$data['columns'][] = ['name' => 'run_script', 'type' => 'varchar(2)', 'NULL' => false, 'default' => 'on'];
	$data['columns'][] = ['name' => 'hostname', 'type' => 'varchar(120)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'ipaddress', 'type' => 'varchar(46)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'path', 'type' => 'varchar(256)', 'NULL' => false];
	$data['columns'][] = ['name' => 'dns_query', 'type' => 'varchar(100)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'ldapsearch', 'type' => 'varchar(200)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'snmp_oid', 'type' => 'varchar(255)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'ssh_command', 'type' => 'varchar(255)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'search', 'type' => 'varchar(1024)', 'NULL' => false];
	$data['columns'][] = ['name' => 'search_maint', 'type' => 'varchar(1024)', 'NULL' => false];
	$data['columns'][] = ['name' => 'search_failed', 'type' => 'varchar(1024)', 'NULL' => false];
	$data['columns'][] = ['name' => 'requiresauth', 'type' => 'varchar(2)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'proxy_id', 'type' => 'int(11)', 'NULL' => false, 'unsigned' => true, 'default' => '0'];
	$data['columns'][] = ['name' => 'ca_id', 'type' => 'int(11)', 'NULL' => false, 'unsigned' => true, 'default' => '0'];
	$data['columns'][] = ['name' => 'cred_id', 'type' => 'int(11)', 'NULL' => false, 'unsigned' => true, 'default' => '0'];
	$data['columns'][] = ['name' => 'checkcert', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'];
	$data['columns'][] = ['name' => 'certexpirenotify', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on'];
	$data['columns'][] = ['name' => 'notify_list', 'type' => 'int(10)', 'NULL' => false, 'unsigned' => true, 'default' => '0'];
	$data['columns'][] = ['name' => 'notify_accounts', 'type' => 'varchar(256)', 'NULL' => false];
	$data['columns'][] = ['name' => 'notify_extra', 'type' => 'varchar(256)', 'NULL' => false];
	$data['columns'][] = ['name' => 'notify_format', 'type' => 'int(3)', 'NULL' => false, 'unsigned' => true, 'default' => '0'];
	$data['columns'][] = ['name' => 'notes', 'type' => 'text', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'external_id', 'type' => 'varchar(20)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'how_often', 'type' => 'int(11)', 'NULL' => false, 'unsigned' => true, 'default' => '1'];
	$data['columns'][] = ['name' => 'downtrigger', 'type' => 'int(11)', 'NULL' => false, 'unsigned' => true, 'default' => '3'];
	$data['columns'][] = ['name' => 'duration_trigger', 'type' => 'decimal(4,2)', 'NULL' => false, 'unsigned' => true, 'default' => '0'];
	$data['columns'][] = ['name' => 'duration_count', 'type' => 'int(3)', 'NULL' => false, 'unsigned' => true, 'default' => '3'];
	$data['columns'][] = ['name' => 'stats_ok', 'type' => 'int(11)', 'NULL' => false, 'unsigned' => true, 'default' => '0'];
	$data['columns'][] = ['name' => 'stats_bad', 'type' => 'int(11)', 'NULL' => false, 'unsigned' => true, 'default' => '0'];
	$data['columns'][] = ['name' => 'failures', 'type' => 'int(11)', 'NULL' => false, 'unsigned' => true, 'default' => '0'];
	$data['columns'][] = ['name' => 'triggered', 'type' => 'int(11)', 'NULL' => false, 'unsigned' => true, 'default' => '0'];
	$data['columns'][] = ['name' => 'triggered_duration', 'type' => 'int(11)', 'NULL' => false, 'unsigned' => true, 'default' => '0'];
	$data['columns'][] = ['name' => 'cpu_user', 'type' => 'double', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'cpu_system', 'type' => 'double', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'memory', 'type' => 'int(11)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'last_check', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00'];
	$data['columns'][] = ['name' => 'last_exp_notify', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00'];
	$data['columns'][] = ['name' => 'last_result', 'type' => 'varchar(20)', 'NULL' => false, 'default' => 'not yet'];
	$data['columns'][] = ['name' => 'last_result_search', 'type' => 'varchar(20)', 'NULL' => false, 'default' => 'not yet'];
	$data['columns'][] = ['name' => 'last_attempt', 'type' => 'int(2)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'last_error', 'type' => 'varchar(256)', 'NULL' => true, 'default' => 'NULL'];
	$data['columns'][] = ['name' => 'last_returned_data', 'type' => 'blob', 'NULL' => true, 'default' => ''];
	$data['columns'][] = ['name' => 'last_duration', 'type' => 'float', 'NULL' => false, 'default' => '0'];
	$data['primary']   = 'id';
	$data['keys'][]    = ['name' => 'last_check', 'columns' => 'last_check'];
	$data['keys'][]    = ['name' => 'triggered', 'columns' => 'triggered'];
	$data['keys'][]    = ['name' => 'enabled', 'columns' => 'enabled'];
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Holds servcheck Service Check Definitions';

	api_plugin_db_table_create('servcheck', 'plugin_servcheck_test', $data);

	$data              = [];
	$data['columns'][] = ['name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true];
	$data['columns'][] = ['name' => 'test_id', 'type' => 'int(11)', 'NULL' => false, 'unsigned' => true, 'default' => '0'];
	$data['columns'][] = ['name' => 'attempt', 'type' => 'int(2)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'result', 'type' => "enum('ok','not yet','error')", 'NULL' => false, 'default' => 'not yet'];
	$data['columns'][] = ['name' => 'result_search', 'type' => "enum('ok','not ok','failed ok','failed not ok', 'maint ok','not yet', 'not tested')", 'NULL' => false, 'default' => 'not yet'];
	$data['columns'][] = ['name' => 'curl_response', 'type' => 'text', 'NULL' => true, 'default' => null];
	$data['columns'][] = ['name' => 'cert_expire', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00'];
	$data['columns'][] = ['name' => 'error', 'type' => 'varchar(256)', 'NULL' => true, 'default' => 'NULL'];
	$data['columns'][] = ['name' => 'duration', 'type' => 'float', 'NULL' => false, 'default' => 0];
	$data['columns'][] = ['name' => 'cpu_user', 'type' => 'double', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'cpu_system', 'type' => 'double', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'memory', 'type' => 'int(11)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'returned_data_size', 'type' => 'int(11)', 'NULL' => false, 'default' => '0'];
	$data['columns'][] = ['name' => 'last_check', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00'];
	$data['primary']   = 'id';
	$data['keys'][]    = ['name' => 'test_id', 'columns' => 'test_id'];
	$data['keys'][]    = ['name' => 'last_check', 'columns' => 'last_check'];
	$data['keys'][]    = ['name' => 'result', 'columns' => 'result'];
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Holds servcheck Service Check Logs';

	api_plugin_db_table_create('servcheck', 'plugin_servcheck_log', $data);

	$data              = [];
	$data['columns'][] = ['name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true];
	$data['columns'][] = ['name' => 'name', 'type' => 'varchar(30)', 'NULL' => true, 'default' => ''];
	$data['columns'][] = ['name' => 'hostname', 'type' => 'varchar(64)', 'NULL' => true, 'default' => ''];
	$data['columns'][] = ['name' => 'http_port', 'type' => 'mediumint(8)', 'NULL' => true, 'default' => '80'];
	$data['columns'][] = ['name' => 'https_port', 'type' => 'mediumint(8)', 'NULL' => true, 'default' => '443'];
	$data['columns'][] = ['name' => 'cred_id', 'type' => 'int(11)', 'NULL' => false, 'unsigned' => true, 'default' => '0'];
	$data['primary']   = 'id';
	$data['keys'][]    = ['name' => 'hostname', 'columns' => 'hostname'];
	$data['keys'][]    = ['name' => 'name', 'columns' => 'name'];
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Holds Proxy Information for Connections';

	api_plugin_db_table_create('servcheck', 'plugin_servcheck_proxy', $data);

	$data              = [];
	$data['columns'][] = ['name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true];
	$data['columns'][] = ['name' => 'name', 'type' => 'varchar(100)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'cert', 'type' => 'text'];
	$data['primary']   = 'id';
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Holds CA certificates';

	api_plugin_db_table_create('servcheck', 'plugin_servcheck_ca', $data);

	$data              = [];
	$data['columns'][] = ['name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true];
	$data['columns'][] = ['name' => 'name', 'type' => 'varchar(100)', 'NULL' => true, 'default' => ''];
	$data['columns'][] = ['name' => 'type', 'type' => "enum('userpass','basic','apikey', 'oauth2', 'cookie', 'snmp','snmp3','sshkey')", 'NULL' => false, 'default' => 'userpass'];
	$data['columns'][] = ['name' => 'data', 'type' => 'text', 'NULL' => true, 'default' => ''];
	$data['primary']   = 'id';
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Holds Credentials';

	api_plugin_db_table_create('servcheck', 'plugin_servcheck_credential', $data);
}

function plugin_servcheck_poller_bottom() {
	global $config;

	require_once($config['library_path'] . '/database.php');

	$command_string = trim(read_config_option('path_php_binary'));

	// If its not set, just assume its in the path
	if (trim($command_string) == '') {
		$command_string = 'php';
	}
	$extra_args = ' -q ' . $config['base_path'] . '/plugins/servcheck/poller_servcheck.php';

	exec_background($command_string, $extra_args);
}

function plugin_servcheck_config_arrays() {
	global $menu, $user_auth_realms, $user_auth_realm_filenames;

	$menu[__('Management')]['plugins/servcheck/servcheck_test.php'] = __('Service Checker', 'servcheck');

	$files = ['index.php', 'plugins.php', 'servcheck_test.php'];

	if (in_array(get_current_page(), $files, true)) {
		plugin_servcheck_check_config();
	}
}

function plugin_servcheck_draw_navigation_text($nav) {
	$nav['servcheck_test.php:'] = [
		'title'   => __('Service Checks', 'servcheck'),
		'mapping' => 'index.php:',
		'url'     => 'servcheck_test.php',
		'level'   => '1'
	];

	$nav['servcheck_test.php:edit'] = [
		'title'   => __('Service Check Edit', 'servcheck'),
		'mapping' => 'index.php:',
		'url'     => 'servcheck_test.php',
		'level'   => '1'
	];

	$nav['servcheck_test.php:save'] = [
		'title'   => __('Service Check Save', 'servcheck'),
		'mapping' => 'index.php:',
		'url'     => 'servcheck_test.php',
		'level'   => '1'
	];

	$nav['servcheck_restapi.php:'] = [
		'title'   => __('Rest API', 'servcheck'),
		'mapping' => 'index.php:',
		'url'     => 'servcheck_restapi.php',
		'level'   => '1'
	];

	$nav['servcheck_restapi.php:edit'] = [
		'title'   => __('Rest API Edit', 'servcheck'),
		'mapping' => 'index.php:',
		'url'     => 'servcheck_restapi.php',
		'level'   => '1'
	];

	$nav['servcheck_restapi.php:save'] = [
		'title'   => __('Rest API Save', 'servcheck'),
		'mapping' => 'index.php:',
		'url'     => 'servcheck_restapi.php',
		'level'   => '1'
	];

	$nav['servcheck_credential.php:'] = [
		'title'   => __('Credential', 'servcheck'),
		'mapping' => 'index.php:',
		'url'     => 'servcheck_credential.php',
		'level'   => '1'
	];

	$nav['servcheck_credential.php:edit'] = [
		'title'   => __('Credential Edit', 'servcheck'),
		'mapping' => 'index.php:',
		'url'     => 'servcheck_credential.php',
		'level'   => '1'
	];

	$nav['servcheck_credential.php:save'] = [
		'title'   => __('Credential Save', 'servcheck'),
		'mapping' => 'index.php:',
		'url'     => 'servcheck_credential.php',
		'level'   => '1'
	];

	$nav['servcheck_proxy.php:'] = [
		'title'   => __('Web Proxy', 'servcheck'),
		'mapping' => 'index.php:',
		'url'     => 'servcheck_proxy.php',
		'level'   => '1'
	];

	$nav['servcheck_proxy.php:edit'] = [
		'title'   => __('Web Proxy Edit', 'servcheck'),
		'mapping' => 'index.php:',
		'url'     => 'servcheck_proxy.php',
		'level'   => '1'
	];

	$nav['servcheck_proxy.php:save'] = [
		'title'   => __('Save Web Proxy', 'servcheck'),
		'mapping' => 'index.php:',
		'url'     => 'servcheck_proxy.php',
		'level'   => '1'
	];

	$nav['servcheck_ca.php:'] = [
		'title'   => __('CA', 'servcheck'),
		'mapping' => 'index.php:',
		'url'     => 'servcheck_ca.php',
		'level'   => '1'
	];

	$nav['servcheck_ca.php:edit'] = [
		'title'   => __('CA Edit', 'servcheck'),
		'mapping' => 'index.php:',
		'url'     => 'servcheck_ca.php',
		'level'   => '1'
	];

	$nav['servcheck_ca.php:save'] = [
		'title'   => __('Save CA', 'servcheck'),
		'mapping' => 'index.php:',
		'url'     => 'servcheck_ca.php',
		'level'   => '1'
	];

	return $nav;
}

function servcheck_replicate_out($data) {
	$remote_poller_id = $data['remote_poller_id'];
	$rcnn_id          = $data['rcnn_id'];
	$class            = $data['class'];

	cacti_log('INFO: Replicating for the servcheck Plugin', false, 'REPLICATE');

	$tables = [
		'plugin_servcheck_proxy',
		'plugin_servcheck_test',
		'plugin_servcheck_credential',
		'plugin_servcheck_ca'
	];

	if ($class == 'all') {
		foreach ($tables as $table) {
			$tdata = db_fetch_assoc('SELECT * FROM ' . $table);
			replicate_out_table($rcnn_id, $tdata, $table, $remote_poller_id);
		}
	}

	return $data;
}

function servcheck_config_settings() {
	global $tabs, $settings;

	$tabs['servcheck'] = __('Servcheck', 'servcheck');

	$settings['servcheck'] = [
		'servcheck_display_header' => [
			'friendly_name' => __('Notification Preferences', 'servcheck'),
			'method'        => 'spacer',
		],
		'servcheck_send_email_separately' => [
			'friendly_name' => __('Send Email separately for each address', 'servcheck'),
			'description'   => __('If checked, this will cause all Emails to be sent separately for each address.', 'servcheck'),
			'method'        => 'checkbox',
			'default'       => '',
		],
		'servcheck_disable_notification' => [
			'friendly_name' => __('Stop sending all notification', 'servcheck'),
			'description'   => __('If checked, servcheck will not send any emails. You can also disable notification only for specific tests', 'servcheck'),
			'method'        => 'checkbox',
			'default'       => '',
		],
		'servcheck_enable_scripts' => [
			'friendly_name' => __('Enable Command Execution', 'servcheck'),
			'description'   => __('Checking this box will enable the ability to run commands on Servcheck events. You can enable or disable it for each test.', 'servcheck'),
			'method'        => 'checkbox',
			'default'       => ''
		],
		'servcheck_change_command' => [
			'friendly_name' => __('Status Change Command', 'servcheck'),
			'description'   => __('When a basic or search or certificate expiration test returns different result, run the following command... This command must NOT include command line arguments... However, the following variables can be pulled from the environment of the script:<br>&#060SERVCHECK_TEST_NAME&#062 &#060SERVCHECK_EXTERNAL_ID&#062 &#060SERVCHECK_TEST_TYPE&#062 &#060SERVCHECK_POLLER_ID&#062 &#060SERVCHECK_RESULT&#062 &#060SERVCHECK_RESULT_SEARCH&#062 &#060SERVCHECK_CERTIFICATE_EXPIRATION&#062', 'servcheck'),
			'method'        => 'filepath',
			'file_type'     => 'binary',
			'size'          => '100',
			'max_length'    => '100',
			'default'       => ''
		],
		'servcheck_processes' => [
			'friendly_name' => __('Concurrent Check Processes', 'servcheck'),
			'description'   => __('The number of service check processes to run concurrently.  Increasing this number to 2 times the number of cores is not advised.', 'servcheck'),
			'method'        => 'drop_array',
			'default'       => 8,
			'array'         => [
				'1'  => __('1 Process', 'servcheck'),
				'2'  => __('%d Processes', 2, 'servcheck'),
				'3'  => __('%d Processes', 3, 'servcheck'),
				'4'  => __('%d Processes', 4, 'servcheck'),
				'5'  => __('%d Processes', 5, 'servcheck'),
				'6'  => __('%d Processes', 6, 'servcheck'),
				'7'  => __('%d Processes', 7, 'servcheck'),
				'8'  => __('%d Processes', 8, 'servcheck'),
				'9'  => __('%d Processes', 9, 'servcheck'),
				'10' => __('%d Processes', 10, 'servcheck'),
				'15' => __('%d Processes', 15, 'servcheck'),
				'20' => __('%d Processes', 20, 'servcheck'),
				'25' => __('%d Processes', 25, 'servcheck'),
				'30' => __('%d Processes', 30, 'servcheck')
			]
		],
		'servcheck_certificate_expiry_days' => [
			'friendly_name' => __('Certificate expiry date advanced notification email', 'servcheck'),
			'description'   => __('If SSL/TLS service certificate expiration is enabled, set how many days advanced notice period before certificate expiry date the system will send notification', 'servcheck'),
			'method'        => 'drop_array',
			'default'       => 7,
			'array'         => [
				'-1' => __('Disabled', 'servcheck'),
				'3'  => __('3 days in advance', 'servcheck'),
				'7'  => __('1 week in advance', 'servcheck'),
				'21' => __('3 weeks in advance', 'servcheck'),
				'30' => __('30 days in advance', 'servcheck'),
				'60' => __('60 days in advance', 'servcheck'),
				'90' => __('90 days in advance', 'servcheck'),
			]
		],
		'servcheck_data_retention' => [
			'friendly_name' => __('How long to keep logs', 'servcheck'),
			'description'   => __('Enter the period after which older logs will be automatically deleted.', 'servcheck'),
			'method'        => 'drop_array',
			'default'       => 8,
			'array'         => [
				'0'   => __('Never', 'servcheck'),
				'1'   => __('%d hours', 24, 'servcheck'),
				'7'   => __('%d days', 7, 'servcheck'),
				'90'  => __('%d days', 90, 'servcheck'),
				'180' => __('%d days', 180, 'servcheck'),
				'365' => __('%d year', 1, 'servcheck'),
			]
		],
		'servcheck_test_max_duration' => [
			'friendly_name' => __('Maximum test duration in seconds', 'intropage'),
			'description'   => __('The default value for tests where runtime testing is not enabled. If enabled, the max. duration is calculated as the duration threshold + 2 seconds.'),
			'method'        => 'textbox',
			'max_length'    => 2,
			'default'       => '3',
		]
	];
}
