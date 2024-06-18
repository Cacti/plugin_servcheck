<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
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

include_once(__DIR__ . '/includes/constants.php');
include_once(__DIR__ . '/includes/arrays.php');

function plugin_servcheck_install () {
	api_plugin_register_hook('servcheck', 'draw_navigation_text', 'plugin_servcheck_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('servcheck', 'config_arrays',        'plugin_servcheck_config_arrays',        'setup.php');
	api_plugin_register_hook('servcheck', 'poller_bottom',        'plugin_servcheck_poller_bottom',        'setup.php');
	api_plugin_register_hook('servcheck', 'replicate_out',        'servcheck_replicate_out',               'setup.php');
	api_plugin_register_hook('servcheck', 'config_settings',      'servcheck_config_settings',             'setup.php');

	api_plugin_register_realm('servcheck', 'servcheck_test.php,servcheck_curl_code.php,servcheck_proxies.php,servcheck_ca.php', __('Service Check Admin', 'servcheck'), 1);

	plugin_servcheck_setup_table();
}

function plugin_servcheck_uninstall () {
	db_execute('DROP TABLE IF EXISTS plugin_servcheck_test');
	db_execute('DROP TABLE IF EXISTS plugin_servcheck_log');
	db_execute('DROP TABLE IF EXISTS plugin_servcheck_proxies');
	db_execute('DROP TABLE IF EXISTS plugin_servcheck_processes');
	db_execute('DROP TABLE IF EXISTS plugin_servcheck_contacts');
	db_execute('DROP TABLE IF EXISTS plugin_servcheck_ca');
}

function plugin_servcheck_check_config () {
	// Here we will check to ensure everything is configured
	plugin_servcheck_upgrade();
	return true;
}

function plugin_servcheck_upgrade() {
	// Here we will upgrade to the newest version
	global $config;

	$info = plugin_servcheck_version();
	$new  = $info['version'];
	$old  = db_fetch_cell('SELECT version FROM plugin_config WHERE directory="servcheck"');

	db_execute_prepared('UPDATE plugin_realms
		SET file = ?
		WHERE file LIKE "%servcheck_test.php%"',
		array('servcheck_test.php,servcheck_curl_code.php,servcheck_proxies.php,servcheck_ca.php'));

		api_plugin_register_hook('servcheck', 'replicate_out', 'servcheck_replicate_out', 'setup.php', '1');
		api_plugin_register_hook('servcheck', 'config_settings', 'servcheck_config_settings', 'setup.php', '1');

	return true;
}

function plugin_servcheck_version() {
	global $config;
	$info = parse_ini_file($config['base_path'] . '/plugins/servcheck/INFO', true);
	return $info['info'];
}


function plugin_servcheck_setup_table() {

	db_execute("CREATE TABLE IF NOT EXISTS `plugin_servcheck_test` (
		`id` int(11) unsigned NOT NULL auto_increment,
		`type` varchar(30) NOT NULL default 'web_http',
		`display_name` varchar(64) NOT NULL default '',
		`poller_id` int(11) unsigned NOT NULL default '1',
		`enabled` char(2) NOT NULL default 'on',
		`hostname` varchar(120) NOT NULL default '',
		`path` varchar(256) NOT NULL,
		`dns_query` varchar(100) NOT NULL default '',
		`ldapsearch` varchar(200) NOT NULL default '',
		`search` varchar(1024) NOT NULL,
		`search_maint` varchar(1024) NOT NULL,
		`search_failed` varchar(1024) NOT NULL,
		`requiresauth` char(2) NOT NULL default '',
		`proxy_server` int(11) unsigned NOT NULL default '0',
		`ca` int(11) unsigned NOT NULL default '0',
		`checkcert` char(2) NOT NULL default 'on',
		`certexpirenotify` char(2) NOT NULL default 'on',
		`username` varchar(200) NOT NULL default '',
		`password` varchar(100) NOT NULL default '',
		`notify_list` int(10) unsigned NOT NULL default '0',
		`notify_accounts` varchar(256) NOT NULL,
		`notify_extra` varchar(256) NOT NULL,
		`notify_format` int(3) unsigned NOT NULL default '0',
		`notes` text NOT NULL default '',
		`external_id` varchar(20) NOT NULL default '',
		`how_often` int(11) unsigned NOT NULL default '1',
		`downtrigger` int(11) unsigned NOT NULL default '3',
		`timeout_trigger` int(11) unsigned NOT NULL default '4',
		`stats_ok` int(11) unsigned NOT NULL default '0',
		`stats_bad` int(11) unsigned NOT NULL default '0',
		`failures` int(11) unsigned NOT NULL default '0',
		`triggered` int(11) unsigned NOT NULL default '0',
		`lastcheck` timestamp NOT NULL default '0000-00-00 00:00:00',
		`last_exp_notify` timestamp NOT NULL default '0000-00-00 00:00:00',
		`last_returned_data` blob default '',
		PRIMARY KEY  (`id`),
		KEY `lastcheck` (`lastcheck`),
		KEY `triggered` (`triggered`),
		KEY `enabled` (`enabled`))
		ENGINE=InnoDB
		COMMENT='Holds servcheck Service Check Definitions'");

	db_execute("CREATE TABLE IF NOT EXISTS `plugin_servcheck_log` (
		`id` int(11) unsigned NOT NULL auto_increment,
		`test_id` int(11) unsigned NOT NULL default '0',
		`lastcheck` timestamp NOT NULL default '0000-00-00 00:00:00',
		`curl_return_code` int(3) NOT NULL default '0',
		`result` enum('ok','not yet','error') NOT NULL default 'not yet',
		`result_search` enum('ok','not ok','failed ok','failed not ok', 'maint ok','not yet', 'not tested') NOT NULL default 'not yet',
		`http_code` int(11) unsigned default NULL,
		`cert_expire` timestamp NOT NULL default '0000-00-00 00:00:00',
		`error` varchar(256) default NULL,
		`total_time` double default NULL,
		`namelookup_time` double default NULL,
		`connect_time` double default NULL,
		`redirect_time` double unsigned default NULL,
		`redirect_count` int(11) unsigned default NULL,
		`size_download` int(11) unsigned default NULL,
		`speed_download` int(11) unsigned default NULL,
		PRIMARY KEY  (`id`),
		KEY `test_id` (`test_id`),
		KEY `lastcheck` (`lastcheck`),
		KEY `result` (`result`))
		ENGINE=InnoDB
		COMMENT='Holds servcheck Service Check Logs'");

	db_execute("CREATE TABLE IF NOT EXISTS `plugin_servcheck_processes` (
		`id` bigint unsigned NOT NULL auto_increment,
		`poller_id` int(11) unsigned NOT NULL default '1',
		`test_id` int(11) unsigned NOT NULL,
		`pid` int(11) unsigned NOT NULL,
		`time` timestamp default CURRENT_TIMESTAMP,
		PRIMARY KEY  (`id`),
		KEY `pid` (`pid`),
		KEY `test_id` (`test_id`),
		KEY `time` (`time`))
		ENGINE=MEMORY
		COMMENT='Holds running process information'");

	db_execute("CREATE TABLE IF NOT EXISTS `plugin_servcheck_contacts` (
		`id` int(12) NOT NULL auto_increment,
		`user_id` int(12) NOT NULL,
		`type` varchar(32) NOT NULL,
		`data` text NOT NULL,
		PRIMARY KEY (`id`),
		UNIQUE KEY `user_id_type` (`user_id`,`type`),
		KEY `type` (`type`),
		KEY `user_id` (`user_id`))
		ENGINE=InnoDB
		COMMENT='Table of servcheck contacts'");

	db_execute("CREATE TABLE `plugin_servcheck_proxies` (
		`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
		`name` varchar(30) DEFAULT '',
		`hostname` varchar(64) DEFAULT '',
		`http_port` mediumint(8) unsigned DEFAULT '80',
		`https_port` mediumint(8) unsigned DEFAULT '443',
		`username` varchar(40) DEFAULT '',
		`password` varchar(60) DEFAULT '',
		PRIMARY KEY (`id`),
		KEY `hostname` (`hostname`),
		KEY `name` (`name`))
		ENGINE=InnoDB
		COMMENT='Holds Proxy Information for Connections'");

	db_execute("CREATE TABLE `plugin_servcheck_ca` (
		`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
		`name` varchar(100) NOT NULL DEFAULT '',
		`cert` text,
		PRIMARY KEY (`id`))
		ENGINE=InnoDB
		COMMENT='Holds CA certificates'");
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

	$nav['servcheck_proxies.php:'] = array(
		'title' => __('Web Proxies', 'servcheck'),
		'mapping' => 'index.php:',
		'url' => 'servcheck_proxies.php',
		'level' => '1'
	);

	$nav['servcheck_proxies.php:edit'] = array(
		'title' => __('Web Proxie Edit', 'servcheck'),
		'mapping' => 'index.php:',
		'url' => 'servcheck_proxies.php',
		'level' => '1'
	);

	$nav['servcheck_proxies.php:save'] = array(
		'title' => __('Save Web Proxy', 'servcheck'),
		'mapping' => 'index.php:',
		'url' => 'servcheck_proxies.php',
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

	cacti_log('INFO: Replacting for the servcheck Plugin', false, 'REPLICATE');

	$tables = array(
		'plugin_servcheck_contacts',
		'plugin_servcheck_proxies',
		'plugin_servcheck_test',
		'plugin_servcheck_ca'
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
			'friendly_name' => __('Email Settings', 'servcheck'),
			'method'        => 'spacer',
		),
		'servcheck_send_email_separately' => array(
			'friendly_name' => __('Send Email separately for each address', 'servcheck'),
			'description'   => __('If checked, this will cause all Emails to be sent separately for each address.', 'servcheck'),
			'method'        => 'checkbox',
			'default'       => '',
		)
	);
}
