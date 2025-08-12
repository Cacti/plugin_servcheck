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

include_once(__DIR__ . '/includes/arrays.php');

function plugin_servcheck_install () {
	api_plugin_register_hook('servcheck', 'draw_navigation_text', 'plugin_servcheck_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('servcheck', 'config_arrays',        'plugin_servcheck_config_arrays',        'setup.php');
	api_plugin_register_hook('servcheck', 'poller_bottom',        'plugin_servcheck_poller_bottom',        'setup.php');
	api_plugin_register_hook('servcheck', 'replicate_out',        'servcheck_replicate_out',               'setup.php');
	api_plugin_register_hook('servcheck', 'config_settings',      'servcheck_config_settings',             'setup.php');
//!! zrusit restapi_php
	api_plugin_register_realm('servcheck', 'servcheck_test.php,servcheck_restapi.php,servcheck_credential.php,servcheck_curl_code.php,servcheck_proxy.php,servcheck_ca.php', __('Service Check Admin', 'servcheck'), 1);

	plugin_servcheck_setup_table();
}

function plugin_servcheck_uninstall () {
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

function plugin_servcheck_check_config () {
	// Here we will check to ensure everything is configured
	plugin_servcheck_upgrade();
	return true;
}

function plugin_servcheck_upgrade() {
	global $config;

	include_once(__DIR__ . '/includes/functions.php');

	$info = plugin_servcheck_version();
	$new  = $info['version'];
	$old  = db_fetch_cell('SELECT version FROM plugin_config WHERE directory="servcheck"');

	db_execute_prepared('UPDATE plugin_realms
		SET file = ?
		WHERE file LIKE "%servcheck_test.php%"',
		array('servcheck_test.php,servcheck_restapi.php,servcheck_credential.php,servcheck_curl_code.php,servcheck_proxy.php,servcheck_ca.php'));
//!! zrusit restapi,php
		api_plugin_register_hook('servcheck', 'replicate_out', 'servcheck_replicate_out', 'setup.php', '1');
		api_plugin_register_hook('servcheck', 'config_settings', 'servcheck_config_settings', 'setup.php', '1');

	if (db_column_exists('plugin_servcheck_test', 'display_name')) {
		db_execute('ALTER TABLE plugin_servcheck_test RENAME COLUMN display_name TO name');
	}

	if (db_column_exists('plugin_servcheck_test', 'proxy_server')) {
		db_execute('ALTER TABLE plugin_servcheck_test RENAME COLUMN proxy_server TO proxy_id');
	}

	$exist = db_fetch_cell("SELECT COUNT(*)
		FROM information_schema.tables
		WHERE table_schema = SCHEMA()
		AND table_name = 'plugin_servcheck_proxies'");

	if ($exist) {
		db_execute('ALTER TABLE plugin_servcheck_proxies RENAME TO plugin_servcheck_proxy');
	}


	$exist = db_fetch_cell("SELECT COUNT(*)
		FROM information_schema.tables
		WHERE table_schema = SCHEMA()
		AND table_name = 'plugin_servcheck_credential'");

	if (!$exist) {
		$data              = array();
		$data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true);
		$data['columns'][] = array('name' => 'name', 'type' => 'varchar(100)', 'NULL' => true, 'default' => '');
		$data['columns'][] = array('name' => 'type', 'type' => "enum('userpass','basic','apikey', 'oauth2', 'cookie', 'snmp','snmp3','sshkey')", 'NULL' => false, 'default' => 'userpass');
		$data['columns'][] = array('name' => 'data', 'type' => 'text', 'NULL' => true, 'default' => '');
		$data['primary']   = 'id';
		$data['type']      = 'InnoDB';
		$data['comment']   = 'Holds Credentials';

		api_plugin_db_table_create('servcheck', 'plugin_servcheck_credential', $data);

		if (!db_column_exists('plugin_servcheck_test', 'snmp_method')) {
			api_plugin_db_add_column('servcheck', 'plugin_servcheck_test', array('name' => 'snmp_oid', 'type' => "varchar(255)", 'NULL' => false, 'default' => ''));
			api_plugin_db_add_column('servcheck', 'plugin_servcheck_test', array('name' => 'ssh_command', 'type' => "varchar(255)", 'NULL' => false, 'default' => ''));
		}

		if (!db_column_exists('plugin_servcheck_test', 'cred_id')) {
			api_plugin_db_add_column('servcheck', 'plugin_servcheck_test', array('name' => 'cred_id', 'type' => 'int(11)', 'NULL' => false, 'unsigned' => true, 'default' => '0'));
		}

		if (!db_column_exists('plugin_servcheck_proxy', 'cred_id')) {
			api_plugin_db_add_column('servcheck', 'plugin_servcheck_proxy', array('name' => 'cred_id', 'type' => 'int(11)', 'NULL' => false, 'unsigned' => true, 'default' => '0'));
		}

		// convert credentials to separated tab

		if (!db_column_exists('plugin_servcheck_test', 'login_url')) {
			db_add_column('plugin_servcheck_test', array('name' => 'format', 'type' => "enum('urlencoded','xml','json')", 'NULL' => false, 'default' => 'urlencoded', 'after' => 'ldapsearch'));
		}

		$records = db_fetch_assoc("SELECT * FROM plugin_servcheck_test WHERE username != '' OR password !='' AND type != 'restapi'");
		if (cacti_sizeof($records)) {
			foreach ($records as $record) {
				$cred = array();
				$cred['type'] = 'userpass';
				$cred['username'] = servcheck_show_text($record['username']);
				$cred['password'] = servcheck_show_text($record['password']);

				$enc = servcheck_encrypt_credential($cred);

				db_execute_prepared('INSERT INTO plugin_servcheck_credential
					(name, type, data) VALUES (?, ?, ?)',
					array('upgrade/convert_test_' . $record['id'], $cred['type'], $enc));

				db_execute_prepared('UPDATE plugin_servcheck_test
					SET cred_id = ? WHERE id = ?',
					array(db_fetch_insert_id(), $record['id']));
			}
		}

		$records = db_fetch_assoc("SELECT * FROM plugin_servcheck_proxy WHERE username != '' OR password !=''");
		if (cacti_sizeof($records)) {
			foreach ($records as $record) {
				$cred = array();
				$cred['type'] = 'userpass';
				$cred['username'] = $record['username'];
				$cred['password'] = $record['password'];

				$enc = servcheck_encrypt_credential($cred);

				db_execute_prepared('INSERT INTO plugin_servcheck_credential
					(name, type, data) VALUES (?, ?, ?)',
					array('upgrade/convert_proxy_' . $record['id'], $cred['type'], $enc));

				db_execute_prepared('UPDATE plugin_servcheck_proxy
					SET cred_id = ? WHERE id = ?',
					array(db_fetch_insert_id(), $record['id']));
			}
		}

		$records = db_fetch_assoc("SELECT * FROM plugin_servcheck_restapi_method");
		if (cacti_sizeof($records)) {
			foreach ($records as $record) {
				if ($record['type'] == 'no') {
					continue;
				}

				$cred = array();

				if ($record['type'] == 'basic') {
					$cred['type'] = 'basic';
					$cred['username'] = servcheck_show_text($record['username']);
					$cred['password'] = servcheck_show_text($record['password']);
					$cred['data_url'] = $record['data_url'];
				} elseif ($record['type'] == 'apikey') {
					$cred['type'] = 'apikey';
					$cred['username'] = $record['cred_name'];
					$cred['data_url'] = $record['data_url'];
					$cred['cred_value'] = servcheck_show_text($record['cred_value']);
				} elseif ($record['type'] == 'oauth2') {
					$cred['type'] = 'oauth2';
					$cred['username'] = servcheck_show_text($record['username']);
					$cred['password'] = servcheck_show_text($record['password']);
					$cred['cred_value'] = servcheck_show_text($record['cred_value']);
					$cred['cred_name'] = $record['cred_name'];
					$cred['cred_valiedity'] = $record['cred_validity'];
					$cred['data_url'] = $record['data_url'];
					$cred['login_url'] = $record['login_url'];
				} elseif ($record['type'] == 'cookie') {
					$cred['type'] = 'cookie';
					$cred['username'] = servcheck_show_text($record['username']);
					$cred['password'] = servcheck_show_text($record['password']);
					$cred['data_url'] = $record['data_url'];
					$cred['login_url'] = $record['login_url'];
				}

				$enc = servcheck_encrypt_credential($cred);

				$test_id = db_fetch_cell_prepared ('SELECT id FROM plugin_servcheck_test
					WHERE restapi_id = ? LIMIT 1',
					array($record['id']));

				db_execute_prepared('INSERT INTO plugin_servcheck_credential
					(name, type, data) VALUES (?, ?, ?)',
					array('upgrade/convert_restapi_' . $record['id'], $cred['type'], $enc));

				db_execute_prepared('UPDATE plugin_servcheck_test
					set type = ?, format = ?, cred_id = ?
					WHERE id = ?',
					array('rest_' . $cred['type'], $record['format'], db_fetch_insert_id(), $test_id));
			}
		}

		db_remove_column('plugin_servcheck_test', 'username');
		db_remove_column('plugin_servcheck_test', 'password');
		db_remove_column('plugin_servcheck_test', 'restapi_id');
		db_remove_column('plugin_servcheck_proxy', 'username');
		db_remove_column('plugin_servcheck_proxy', 'password');
	}

	if (cacti_version_compare($old, '0.4', '<')) {
		if (!db_column_exists('plugin_servcheck_test', 'ipaddress')) {
			db_add_column('plugin_servcheck_test', array('name' => 'ipaddress', 'type' => 'varchar(46)', 'NULL' => false, 'default' => '', 'after' => 'hostname'));
		}

	}

	return true;
}

function plugin_servcheck_version() {
	global $config;
	$info = parse_ini_file($config['base_path'] . '/plugins/servcheck/INFO', true);
	return $info['info'];
}

function plugin_servcheck_setup_table() {

	$data              = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'type', 'type' => 'varchar(30)', 'NULL' => false, 'default' => 'web_http');
	$data['columns'][] = array('name' => 'name', 'type' => 'varchar(64)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'poller_id', 'type' => 'int(11)', 'NULL' => false, 'unsigned' => true, 'default' => '1');
	$data['columns'][] = array('name' => 'enabled', 'type' => 'varchar(2)', 'NULL' => false, 'default' => 'on');
	$data['columns'][] = array('name' => 'hostname', 'type' => 'varchar(120)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'ipaddress', 'type' => 'varchar(46)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'path', 'type' => 'varchar(256)', 'NULL' => false);
	$data['columns'][] = array('name' => 'dns_query', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'ldapsearch', 'type' => 'varchar(200)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'snmp_oid', 'type' => "varchar(255)", 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'ssh_command', 'type' => "varchar(255)", 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'format', 'type' => "enum('urlencoded','xml','json')", 'NULL' => false, 'default' => 'urlencoded');
	$data['columns'][] = array('name' => 'search', 'type' => 'varchar(1024)', 'NULL' => false);
	$data['columns'][] = array('name' => 'search_maint', 'type' => 'varchar(1024)', 'NULL' => false);
	$data['columns'][] = array('name' => 'search_failed', 'type' => 'varchar(1024)', 'NULL' => false);
	$data['columns'][] = array('name' => 'requiresauth', 'type' => 'varchar(2)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'proxy_id', 'type' => 'int(11)', 'NULL' => false, 'unsigned' => true, 'default' => '0');
	$data['columns'][] = array('name' => 'ca_id', 'type' => 'int(11)', 'NULL' => false, 'unsigned' => true, 'default' => '0');
	$data['columns'][] = array('name' => 'checkcert', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on');
	$data['columns'][] = array('name' => 'certexpirenotify', 'type' => 'char(2)', 'NULL' => false, 'default' => 'on');
	$data['columns'][] = array('name' => 'notify_list', 'type' => 'int(10)', 'NULL' => false, 'unsigned' => true, 'default' => '0');
	$data['columns'][] = array('name' => 'notify_accounts', 'type' => 'varchar(256)', 'NULL' => false);
	$data['columns'][] = array('name' => 'notify_extra', 'type' => 'varchar(256)', 'NULL' => false);
	$data['columns'][] = array('name' => 'notify_format', 'type' => 'int(3)', 'NULL' => false, 'unsigned' => true, 'default' => '0');
	$data['columns'][] = array('name' => 'notes', 'type' => 'text', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'external_id', 'type' => 'varchar(20)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'how_often', 'type' => 'int(11)', 'NULL' => false, 'unsigned' => true, 'default' => '1');
	$data['columns'][] = array('name' => 'downtrigger', 'type' => 'int(11)', 'NULL' => false, 'unsigned' => true, 'default' => '3');
	$data['columns'][] = array('name' => 'timeout_trigger', 'type' => 'int(11)', 'NULL' => false, 'unsigned' => true, 'default' => '4');
	$data['columns'][] = array('name' => 'stats_ok', 'type' => 'int(11)', 'NULL' => false, 'unsigned' => true, 'default' => '0');
	$data['columns'][] = array('name' => 'stats_bad', 'type' => 'int(11)', 'NULL' => false, 'unsigned' => true, 'default' => '0');
	$data['columns'][] = array('name' => 'failures', 'type' => 'int(11)', 'NULL' => false, 'unsigned' => true, 'default' => '0');
	$data['columns'][] = array('name' => 'triggered', 'type' => 'int(11)', 'NULL' => false, 'unsigned' => true, 'default' => '0');
	$data['columns'][] = array('name' => 'lastcheck', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00');
	$data['columns'][] = array('name' => 'last_exp_notify', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00');
	$data['columns'][] = array('name' => 'last_returned_data', 'type' => 'blob', 'NULL' => true, 'default' => '');
	$data['columns'][] = array('name' => 'cred_id', 'type' => 'int(11)', 'NULL' => false, 'unsigned' => true, 'default' => '0');

	$data['primary']   = 'id';
	$data['keys'][] = array('name' => 'lastcheck', 'columns' => 'lastcheck');
	$data['keys'][] = array('name' => 'triggered', 'columns' => 'triggered');
	$data['keys'][] = array('name' => 'enabled', 'columns' => 'enabled');
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Holds servcheck Service Check Definitions';

	api_plugin_db_table_create('servcheck', 'plugin_servcheck_test', $data);



	$data              = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'test_id', 'type' => 'int(11)', 'NULL' => false, 'unsigned' => true, 'default' => '0');
	$data['columns'][] = array('name' => 'lastcheck', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00');
	$data['columns'][] = array('name' => 'curl_return_code', 'type' => 'int(3)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'result', 'type' => "enum('ok','not yet','error')", 'NULL' => false, 'default' => 'not yet');
	$data['columns'][] = array('name' => 'result_search', 'type' => "enum('ok','not ok','failed ok','failed not ok', 'maint ok','not yet', 'not tested')", 'NULL' => false, 'default' => 'not yet');
	$data['columns'][] = array('name' => 'http_code', 'type' => 'int(11)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'cert_expire', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00');
	$data['columns'][] = array('name' => 'error', 'type' => 'varchar(256)', 'NULL' => true, 'default' => 'NULL');
	$data['columns'][] = array('name' => 'total_time', 'type' => 'double', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'namelookup_time', 'type' => 'double', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'connect_time', 'type' => 'double', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'redirect_time', 'type' => 'double', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'redirect_count', 'type' => 'int(11)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'size_download', 'type' => 'int(11)', 'NULL' => true, 'unsigned' => true);
	$data['columns'][] = array('name' => 'speed_download', 'type' => 'int(11)', 'NULL' => true, 'unsigned' => true);
	$data['primary']   = 'id';
	$data['keys'][] = array('name' => 'test_id', 'columns' => 'test_id');
	$data['keys'][] = array('name' => 'lastcheck', 'columns' => 'lastcheck');
	$data['keys'][] = array('name' => 'result', 'columns' => 'result');
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Holds servcheck Service Check Logs';

	api_plugin_db_table_create('servcheck', 'plugin_servcheck_log', $data);


	$data              = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'bigint', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'poller_id', 'type' => 'int(11)', 'NULL' => false, 'unsigned' => true, 'default' => '1');
	$data['columns'][] = array('name' => 'test_id', 'type' => 'int(11)', 'NULL' => false, 'unsigned' => true);
	$data['columns'][] = array('name' => 'pid', 'type' => 'int(11)', 'NULL' => false);
	$data['columns'][] = array('name' => 'time', 'type' => 'timestamp', 'default' => 'CURRENT_TIMESTAMP', 'NULL' => false);
	$data['primary']   = 'id';
	$data['keys'][] = array('name' => 'pid', 'columns' => 'pid');
	$data['keys'][] = array('name' => 'test_id', 'columns' => 'test_id');
	$data['keys'][] = array('name' => 'time', 'columns' => 'time');
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Holds running process information';

	api_plugin_db_table_create('servcheck', 'plugin_servcheck_processes', $data);


	$data              = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'bigint', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'user_id', 'type' => 'int(12)', 'NULL' => false, 'unsigned' => true);
	$data['columns'][] = array('name' => 'type', 'type' => 'varchar(32)', 'NULL' => false);
	$data['columns'][] = array('name' => 'data', 'type' => 'text', 'NULL' => false);
	$data['primary']   = 'id';
	$data['unique_keys'][] = array('name' => 'user_id_type', 'columns' => 'user_id`, `type');
	$data['keys'][] = array('name' => 'type', 'columns' => 'type');
	$data['keys'][] = array('name' => 'user_id', 'columns' => 'user_id');
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Holds Plugin servcheck contacts';

	api_plugin_db_table_create('servcheck', 'plugin_servcheck_contacts', $data);



	$data              = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'name', 'type' => 'varchar(30)', 'NULL' => true, 'default' => '');
	$data['columns'][] = array('name' => 'hostname', 'type' => 'varchar(64)', 'NULL' => true, 'default' => '');
	$data['columns'][] = array('name' => 'http_port', 'type' => 'mediumint(8)', 'NULL' => true, 'default' => '80');
	$data['columns'][] = array('name' => 'https_port', 'type' => 'mediumint(8)', 'NULL' => true, 'default' => '443');
	$data['columns'][] = array('name' => 'username', 'type' => 'varchar(40)', 'NULL' => true, 'default' => '');
	$data['columns'][] = array('name' => 'password', 'type' => 'varchar(60)', 'NULL' => true, 'default' => '');
	$data['columns'][] = array('name' => 'cred_id', 'type' => 'int(11)', 'NULL' => false, 'unsigned' => true, 'default' => '0');
	$data['primary']   = 'id';
	$data['keys'][] = array('name' => 'hostname', 'columns' => 'hostname');
	$data['keys'][] = array('name' => 'name', 'columns' => 'name');
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Holds Proxy Information for Connections';

	api_plugin_db_table_create('servcheck', 'plugin_servcheck_proxy', $data);

	$data              = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'name', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'cert', 'type' => 'text');
	$data['primary']   = 'id';
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Holds CA certificates';

	api_plugin_db_table_create('servcheck', 'plugin_servcheck_ca', $data);

/*
	$data              = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'name', 'type' => 'varchar(100)', 'NULL' => true, 'default' => '');
	$data['columns'][] = array('name' => 'type', 'type' => "enum('no','basic','apikey','oauth2','cookie')", 'NULL' => false, 'default' => 'basic');
	$data['columns'][] = array('name' => 'format', 'type' => "enum('urlencoded','xml','json')", 'NULL' => false, 'default' => 'urlencoded');
	$data['columns'][] = array('name' => 'cred_name', 'type' => 'varchar(100)', 'NULL' => true, 'default' => '');
	$data['columns'][] = array('name' => 'cred_value', 'type' => 'text', 'NULL' => true, 'default' => '');
	$data['columns'][] = array('name' => 'cred_validity', 'type' => 'timestamp', 'NULL' => false, 'default' => '0000-00-00 00:00:00');
	$data['columns'][] = array('name' => 'username', 'type' => 'varchar(100)', 'NULL' => true, 'default' => '');
	$data['columns'][] = array('name' => 'password', 'type' => 'varchar(100)', 'NULL' => true, 'default' => '');
	$data['columns'][] = array('name' => 'login_url', 'type' => 'varchar(200)', 'NULL' => true, 'default' => '');
	$data['columns'][] = array('name' => 'data_url', 'type' => 'varchar(200)', 'NULL' => true, 'default' => '');
	$data['primary']   = 'id';
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Holds Rest API auth';

	api_plugin_db_table_create('servcheck', 'plugin_servcheck_restapi_method', $data);
*/

	$data              = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'name', 'type' => 'varchar(100)', 'NULL' => true, 'default' => '');
	$data['columns'][] = array('name' => 'type', 'type' => "enum('userpass','basic','apikey', 'oauth2', 'cookie', 'snmp','snmp3','sshkey')", 'NULL' => false, 'default' => 'userpass');
	$data['columns'][] = array('name' => 'data', 'type' => 'text', 'NULL' => true, 'default' => '');
	$data['primary']   = 'id';
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Holds Credentials';

	api_plugin_db_table_create('servcheck', 'plugin_servcheck_credential', $data);
}



function plugin_servcheck_poller_bottom() {
	global $config;

	include_once($config['library_path'] . '/database.php');

	$command_string = trim(read_config_option('path_php_binary'));

	// If its not set, just assume its in the path
	if (trim($command_string) == '')
		$command_string = 'php';
	$extra_args = ' -q ' . $config['base_path'] . '/plugins/servcheck/poller_servcheck.php';

	exec_background($command_string, $extra_args);
}

function plugin_servcheck_config_arrays() {
	global $menu, $user_auth_realms, $user_auth_realm_filenames;

	$menu[__('Management')]['plugins/servcheck/servcheck_test.php'] = __('Service Checker', 'servcheck');

	$files = array('index.php', 'plugins.php', 'servcheck_test.php');
	if (in_array(get_current_page(), $files)) {
		plugin_servcheck_check_config();
	}
}

function plugin_servcheck_draw_navigation_text($nav) {
	$nav['servcheck_test.php:'] = array(
		'title' => __('Service Checks', 'servcheck'),
		'mapping' => 'index.php:',
		'url' => 'servcheck_test.php',
		'level' => '1'
	);

	$nav['servcheck_test.php:edit'] = array(
		'title' => __('Service Check Edit', 'servcheck'),
		'mapping' => 'index.php:',
		'url' => 'servcheck_test.php',
		'level' => '1'
	);

	$nav['servcheck_test.php:save'] = array(
		'title' => __('Service Check Save', 'servcheck'),
		'mapping' => 'index.php:',
		'url' => 'servcheck_test.php',
		'level' => '1'
	);

	$nav['servcheck_restapi.php:'] = array(
		'title' => __('Rest API', 'servcheck'),
		'mapping' => 'index.php:',
		'url' => 'servcheck_restapi.php',
		'level' => '1'
	);

	$nav['servcheck_restapi.php:edit'] = array(
		'title' => __('Rest API Edit', 'servcheck'),
		'mapping' => 'index.php:',
		'url' => 'servcheck_restapi.php',
		'level' => '1'
	);

	$nav['servcheck_restapi.php:save'] = array(
		'title' => __('Rest API Save', 'servcheck'),
		'mapping' => 'index.php:',
		'url' => 'servcheck_restapi.php',
		'level' => '1'
	);

	$nav['servcheck_credential.php:'] = array(
		'title' => __('Credential', 'servcheck'),
		'mapping' => 'index.php:',
		'url' => 'servcheck_credential.php',
		'level' => '1'
	);

	$nav['servcheck_credential.php:edit'] = array(
		'title' => __('Credential Edit', 'servcheck'),
		'mapping' => 'index.php:',
		'url' => 'servcheck_credential.php',
		'level' => '1'
	);

	$nav['servcheck_credential.php:save'] = array(
		'title' => __('Credential Save', 'servcheck'),
		'mapping' => 'index.php:',
		'url' => 'servcheck_credential.php',
		'level' => '1'
	);

	$nav['servcheck_proxy.php:'] = array(
		'title' => __('Web Proxy', 'servcheck'),
		'mapping' => 'index.php:',
		'url' => 'servcheck_proxy.php',
		'level' => '1'
	);

	$nav['servcheck_proxy.php:edit'] = array(
		'title' => __('Web Proxy Edit', 'servcheck'),
		'mapping' => 'index.php:',
		'url' => 'servcheck_proxy.php',
		'level' => '1'
	);

	$nav['servcheck_proxy.php:save'] = array(
		'title' => __('Save Web Proxy', 'servcheck'),
		'mapping' => 'index.php:',
		'url' => 'servcheck_proxy.php',
		'level' => '1'
	);

	$nav['servcheck_ca.php:'] = array(
		'title' => __('CA', 'servcheck'),
		'mapping' => 'index.php:',
		'url' => 'servcheck_ca.php',
		'level' => '1'
	);

	$nav['servcheck_ca.php:edit'] = array(
		'title' => __('CA Edit', 'servcheck'),
		'mapping' => 'index.php:',
		'url' => 'servcheck_ca.php',
		'level' => '1'
	);

	$nav['servcheck_ca.php:save'] = array(
		'title' => __('Save CA', 'servcheck'),
		'mapping' => 'index.php:',
		'url' => 'servcheck_ca.php',
		'level' => '1'
	);

	return $nav;
}

function servcheck_replicate_out($data) {
	$remote_poller_id = $data['remote_poller_id'];
	$rcnn_id          = $data['rcnn_id'];
	$class            = $data['class'];

	cacti_log('INFO: Replicating for the servcheck Plugin', false, 'REPLICATE');

	$tables = array(
		'plugin_servcheck_contacts',
		'plugin_servcheck_proxy',
		'plugin_servcheck_test',
		'plugin_servcheck_credential',
		'plugin_servcheck_ca',
		'plugin_servcheck_rest_method'
	);

	if ($class == 'all') {
		foreach($tables as $table) {
			$tdata = db_fetch_assoc('SELECT * FROM ' . $table);
			replicate_out_table($rcnn_id, $tdata, $table, $remote_poller_id);
		}
	}

	return $data;
}

function servcheck_config_settings() {
	global $tabs, $settings;

	$tabs['servcheck'] = __('Servcheck', 'servcheck');

	$settings['servcheck'] = array(
		'servcheck_display_header' => array(
			'friendly_name' => __('Notification Preferences', 'servcheck'),
			'method'        => 'spacer',
		),
		'servcheck_send_email_separately' => array(
			'friendly_name' => __('Send Email separately for each address', 'servcheck'),
			'description'   => __('If checked, this will cause all Emails to be sent separately for each address.', 'servcheck'),
			'method'        => 'checkbox',
			'default'       => '',
		),
		'servcheck_disable_notification' => array(
			'friendly_name' => __('Stop sending all notification', 'servcheck'),
			'description'   => __('If checked, servcheck will not send any emails.', 'servcheck'),
			'method'        => 'checkbox',
			'default'       => '',
		),
		'servcheck_enable_scripts' => array(
			'friendly_name' => __('Enable Command Execution', 'servcheck'),
			'description' => __('Checking this box will enable the ability to run commands on Servcheck events.', 'servcheck'),
			'method' => 'checkbox',
			'default' => ''
		),
		'servcheck_change_command' => array(
			'friendly_name' => __('Status Change Command', 'servcheck'),
			'description' => __('When a basic or search or certificate expiration test returns different result, run the following command... This command must NOT include command line arguments... However, the following variables can be pulled from the environment of the script:<br>&#060SERVCHECK_TEST_NAME&#062 &#060SERVCHECK_EXTERNAL_ID&#062 &#060SERVCHECK_TEST_TYPE&#062 &#060SERVCHECK_POLLER_ID&#062 &#060SERVCHECK_RESULT&#062 &#060SERVCHECK_RESULT_SEARCH&#062 &#060SERVCHECK_CURL_RETURN_CODE&#062 &#060SERVCHECK_CERTIFICATE_EXPIRATION&#062', 'servcheck'),
			'method' => 'filepath',
			'file_type' => 'binary',
			'size' => '100',
			'max_length' => '100',
			'default' => ''
		),
		'servcheck_certificate_expiry_days' => array(
			'friendly_name' => __('Check certificate expiration', 'servcheck'),
			'description' => __('If SSL/TLS service certificate expiration is enabled, notify about soon expiration ', 'sercheck'),
			'method' => 'drop_array',
			'array' => array(
				'-1' => __('Disabled', 'servcheck'),
				'3'  => __('3 days before', 'servcheck'),
				'7'  => __('1 week before', 'servcheck'),
				'21' => __('3 weeks before', 'servcheck'),
				'30' => __('30 days before', 'servcheck'),
			),
			'default' => 7
		),
	);
}
