<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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

/* let PHP run just as long as it has to */
ini_set('max_execution_time', '55');

$dir = dirname(__FILE__);
chdir($dir);

if (strpos($dir, 'plugins') !== false) {
	chdir('../../');
}

include('./include/cli_check.php');
include_once($config['base_path'] . '/plugins/servcheck/includes/functions.php');
include_once($config['base_path'] . '/lib/poller.php');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$debug = false;
$force = false;
$start = microtime(true);

$poller_id = $config['poller_id'];

if (cacti_sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '-f':
			case '--force':
				$force = true;
				break;
			case '-d':
			case '--debug':
				$debug = true;
				break;
			case '--version':
			case '-V':
			case '-v':
				display_version();
				exit;
			case '--help':
			case '-H':
			case '-h':
				display_help();
				exit;
			default:
				print "ERROR: Invalid Parameter " . $parameter . "\n\n";
				display_help();
				exit;
		}
	}
}

if (!function_exists('curl_init')) {
	print "FATAL: You must install php-curl to use this Plugin" . PHP_EOL;
}

plugin_servcheck_check_debug();

print "Running Service Checks\n";

// Remove old logs)
$t = time() - (86400 * 30);

if ($poller_id == 1) {
	db_execute_prepared('DELETE FROM plugin_servcheck_log
		WHERE lastcheck < FROM_UNIXTIME(?)',
		array($t));

	db_execute_prepared('DELETE FROM plugin_servcheck_processes
		WHERE time < FROM_UNIXTIME(?)',
		array(time() - 15));
}

$tests = db_fetch_assoc_prepared('SELECT *
	FROM plugin_servcheck_test
	WHERE enabled = "on"
	AND poller_id = ?',
	array($poller_id));

$max = 12;

if (cacti_sizeof($tests)) {
	foreach($tests as $test) {
		$total = db_fetch_cell_prepared('SELECT COUNT(id)
			FROM plugin_servcheck_processes
			WHERE poller_id = ?',
			array($poller_id));

		if ($max - $total > 0) {

			plugin_servcheck_debug('Launching Service Check ' . $test['display_name'], $test);

			$command_string = read_config_option('path_php_binary');
			$extra_args     = '-q "' . $config['base_path'] . '/plugins/servcheck/servcheck_process.php" --id=' . $test['id'] . ($debug ? ' --debug':'');
			exec_background($command_string, $extra_args);

			usleep(10000);
		} else {
			usleep(10000);

			db_execute_prepared('DELETE FROM plugin_servcheck_processes
				WHERE time < FROM_UNIXTIME(?)
				AND poller_id = ?',
				array(time() - 15, $poller_id));
		}
	}
}

while(true) {
	db_execute_prepared('DELETE FROM plugin_servcheck_processes
		WHERE time < FROM_UNIXTIME(?)
		AND poller_id = ?',
		array(time() - 15, $poller_id));

	$running = db_fetch_cell_prepared('SELECT COUNT(*)
		FROM plugin_servcheck_processes
		WHERE poller_id = ?',
		array($poller_id));

	if ($running == 0) {
		break;
	} else {
		sleep(1);
	}
}

$end   = microtime(true);
$ttime = round($end - $start, 2);

$stats = 'Time:' . $ttime . ' Checks:' . sizeof($tests);

cacti_log("SERVCHECK STATS: $stats", false, 'SYSTEM');

if ($poller_id == 1) {
	set_config_option('stats_servcheck', $stats);
}

set_config_option('stats_servcheck_' . $poller_id, $stats);


/**
 * display_version - displays version information
 */
function display_version() {
	global $config;

	if (!function_exists('plugin_servcheck_version')) {
		include_once($config['base_path'] . '/plugins/servcheck/setup.php');
	}

    $info = plugin_servcheck_version();

    print "Cacti Service Check Master Process, Version " . $info['version'] . ", " . COPYRIGHT_YEARS . "\n";
}

/**
 * display_help - displays the usage of the function
 */
function display_help () {
    display_version();

    print "\nusage: poller_servcheck.php [--debug] [--force]\n\n";
	print "This binary will exec all the Service check child processes.\n\n";
    print "--force    - Force all the service checks to run now\n";
    print "--debug    - Display verbose output during execution\n\n";
}

