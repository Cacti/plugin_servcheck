<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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

/**
 * Returns the shared definition of plugin_servcheck_test for installation and upgrade.
 *
 * @return array The table definition.
 */
function servcheck_test_table_data(): array {
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
	$data['primary']   = ['id'];
	$data['keys'][]    = ['name' => 'last_check', 'columns' => ['last_check']];
	$data['keys'][]    = ['name' => 'triggered', 'columns' => ['triggered']];
	$data['keys'][]    = ['name' => 'enabled', 'columns' => ['enabled']];
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Holds servcheck Service Check Definitions';

	return $data;
}

/**
 * Returns the shared definition of plugin_servcheck_log for installation and upgrade.
 *
 * @return array The table definition.
 */
function servcheck_log_table_data(): array {
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
	$data['primary']   = ['id'];
	$data['keys'][]    = ['name' => 'test_id', 'columns' => ['test_id']];
	$data['keys'][]    = ['name' => 'last_check', 'columns' => ['last_check']];
	$data['keys'][]    = ['name' => 'result', 'columns' => ['result']];
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Holds servcheck Service Check Logs';

	return $data;
}

/**
 * Returns the shared definition of plugin_servcheck_proxy for installation and upgrade.
 *
 * @return array The table definition.
 */
function servcheck_proxy_table_data(): array {
	$data              = [];
	$data['columns'][] = ['name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true];
	$data['columns'][] = ['name' => 'name', 'type' => 'varchar(30)', 'NULL' => true, 'default' => ''];
	$data['columns'][] = ['name' => 'hostname', 'type' => 'varchar(64)', 'NULL' => true, 'default' => ''];
	$data['columns'][] = ['name' => 'http_port', 'type' => 'mediumint(8)', 'NULL' => true, 'default' => '80'];
	$data['columns'][] = ['name' => 'https_port', 'type' => 'mediumint(8)', 'NULL' => true, 'default' => '443'];
	$data['columns'][] = ['name' => 'cred_id', 'type' => 'int(11)', 'NULL' => false, 'unsigned' => true, 'default' => '0'];
	$data['primary']   = ['id'];
	$data['keys'][]    = ['name' => 'hostname', 'columns' => ['hostname']];
	$data['keys'][]    = ['name' => 'name', 'columns' => ['name']];
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Holds Proxy Information for Connections';

	return $data;
}

/**
 * Returns the shared definition of plugin_servcheck_ca for installation and upgrade.
 *
 * @return array The table definition.
 */
function servcheck_ca_table_data(): array {
	$data              = [];
	$data['columns'][] = ['name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true];
	$data['columns'][] = ['name' => 'name', 'type' => 'varchar(100)', 'NULL' => false, 'default' => ''];
	$data['columns'][] = ['name' => 'cert', 'type' => 'text'];
	$data['primary']   = ['id'];
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Holds CA certificates';

	return $data;
}

/**
 * Returns the shared definition of plugin_servcheck_credential for installation and upgrade.
 *
 * @return array The table definition.
 */
function servcheck_credential_table_data(): array {
	$data              = [];
	$data['columns'][] = ['name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true];
	$data['columns'][] = ['name' => 'name', 'type' => 'varchar(100)', 'NULL' => true, 'default' => ''];
	$data['columns'][] = ['name' => 'type', 'type' => "enum('userpass','basic','apikey', 'oauth2', 'cookie', 'snmp','snmp3','sshkey')", 'NULL' => false, 'default' => 'userpass'];
	$data['columns'][] = ['name' => 'data', 'type' => 'text', 'NULL' => true, 'default' => ''];
	$data['primary']   = ['id'];
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Holds Credentials';

	return $data;
}

/**
 * Returns every current table definition from one place.
 *
 * @return array Table names mapped to definitions.
 */
function servcheck_database_tables(): array {
	return [
		'plugin_servcheck_test' => servcheck_test_table_data(),
		'plugin_servcheck_log' => servcheck_log_table_data(),
		'plugin_servcheck_proxy' => servcheck_proxy_table_data(),
		'plugin_servcheck_ca' => servcheck_ca_table_data(),
		'plugin_servcheck_credential' => servcheck_credential_table_data(),
	];
}

/**
 * Normalizes the primary key for older Cacti plugin table-creation APIs.
 *
 * @param array $data The shared definition, with an array primary key.
 * @return array The definition with a scalar primary key.
 */
function servcheck_create_definition(array $data): array {
	$data['primary'] = implode('`,`', $data['primary']);

	return $data;
}

/**
 * Creates all plugin tables through Cacti's tracked plugin table API.
 * Called by plugin_servcheck_install().
 *
 * @return void
 */
function servcheck_setup_database(): void {
	foreach (servcheck_database_tables() as $table => $data) {
		api_plugin_db_table_create('servcheck', $table, servcheck_create_definition($data));
	}
}

/**
 * Applies historical migrations before reconciling the current schema.
 * Missing tables are created through Cacti's tracked plugin table API.
 *
 * @param string $old The installed plugin version.
 * @return bool False if any table could not be reconciled.
 */
function servcheck_upgrade_tables(string $old): bool {
	if (cacti_version_compare($old, '0.3', '<')) {
		if (!db_column_exists('plugin_servcheck_test', 'ipaddress')) {
			db_add_column('plugin_servcheck_test', ['name' => 'ipaddress', 'type' => 'varchar(46)', 'NULL' => false, 'default' => '', 'after' => 'hostname']);
		}

		if (!db_column_exists('plugin_servcheck_test', 'external_id')) {
			db_add_column('plugin_servcheck_test', ['name' => 'external_id', 'type' => 'varchar(20)', 'NULL' => false, 'default' => '', 'after' => 'notes']);
		}

		db_execute('DROP TABLE IF EXISTS plugin_servcheck_contacts');

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

		db_remove_column('plugin_servcheck_test', 'username');
		db_remove_column('plugin_servcheck_test', 'password');
		db_remove_column('plugin_servcheck_test', 'restapi_id');
		db_remove_column('plugin_servcheck_proxy', 'username');
		db_remove_column('plugin_servcheck_proxy', 'password');

		db_execute('DROP TABLE IF EXISTS plugin_servcheck_processes');
		db_execute('DROP TABLE IF EXISTS plugin_servcheck_restapi_method');
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


	$success = true;

	foreach (servcheck_database_tables() as $table => $data) {
		if (db_table_exists($table)) {
			$result = db_update_table($table, $data);
		} else {
			$result = api_plugin_db_table_create('servcheck', $table, servcheck_create_definition($data));
		}

		if ($result === false) {
			$success = false;
		}
	}

	return $success;
}
