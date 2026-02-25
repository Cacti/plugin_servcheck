#!/usr/bin/env php
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

pcntl_async_signals(true);

ini_set('output_buffering', 'Off');
ini_set('max_runtime', '-1');
ini_set('memory_limit', '-1');

ini_set('max_execution_time', '-1');

set_time_limit(0);
ob_implicit_flush();

// install signal handlers for UNIX only
if (function_exists('pcntl_signal')) {
	pcntl_signal(SIGTERM, 'sig_handler');
	pcntl_signal(SIGINT, 'sig_handler');
	pcntl_signal(SIGUSR1, 'sig_handler');
}

$dir = __DIR__;
chdir($dir);

if (strpos($dir, 'plugins') !== false) {
	chdir('../../');
}

require('./include/cli_check.php');
require_once($config['base_path'] . '/plugins/servcheck/includes/functions.php');
require_once($config['base_path'] . '/lib/poller.php');
require($config['base_path'] . '/plugins/servcheck/includes/arrays.php');

// process calling arguments
$parms = $_SERVER['argv'];
array_shift($parms);

$debug      = false;
$force      = false;
$start      = microtime(true);
$test_cond  = '';
$poller_id  = $config['poller_id'];
$poller_int = read_config_option('poller_interval');

if (cacti_sizeof($parms)) {
	foreach ($parms as $parameter) {
		if (strpos($parameter, '=')) {
			[$arg, $value] = explode('=', $parameter);
		} else {
			$arg   = $parameter;
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
				print 'ERROR: Invalid Parameter ' . $parameter . PHP_EOL . PHP_EOL;
				display_help();
				exit;
		}
	}
}

servcheck_check_debug();

print 'Running Service Checks' . PHP_EOL;

$taskname = "child:$poller_id";

/**
 * On a force action, just clear out all the results, otherwise
 * clear out results that have timed out.
 */
if ($force) {
	db_execute_prepared('DELETE FROM processes
		WHERE tasktype = "servcheck"
		AND (taskname = ? OR (taskname = "master" AND taskid = ?))',
		[$taskname, $poller_id]);

	print "The taskname is $taskname" . PHP_EOL;
} else {
	$processes = db_fetch_assoc_prepared('SELECT *
		FROM processes
		WHERE UNIX_TIMESTAMP() - UNIX_TIMESTAMP(`started`) > `timeout`
		AND tasktype = "servcheck"
		AND taskname = ?', [$taskname]);

	if (cacti_sizeof($processes)) {
		foreach ($processes as $p) {
			posix_kill($p['pid'], SIGINT);

			db_execute_prepared('DELETE FROM processes
				WHERE pid = ?
				AND tasktype = "servcheck"
				AND taskname = ?',
				[$p['pid'], "child:$poller_id"]);
		}
	}
}

/**
 *  Make sure we create an interlock to prevent race conditions
 */
if (!$force) {
	print 'Checking on Registered Process' . PHP_EOL;

	if (!register_process_start('servcheck', 'master', $poller_id, $poller_int * 2)) {
		print 'Found Registered Process.  Shutting Down!' . PHP_EOL;

		exit(0);
	}
}

$max_processes = read_config_option('servcheck_processes');

if (empty($max_processes)) {
	$max_processes = 8;
}

$params    = [];
$params[]  = $poller_id;
$sql_where = 'poller_id = ? ';

if (!$force) {
	$sql_where .= 'AND enabled = "on" ';
}

$tests = db_fetch_cell_prepared("SELECT COUNT(id)
	FROM plugin_servcheck_test
	WHERE
	$sql_where",
	$params);

$timeout = db_fetch_cell_prepared("SELECT MAX(duration_trigger*attempt)
	FROM plugin_servcheck_test
	WHERE
	$sql_where",
	$params);

if ($timeout > 0) {
	$timeout += 2;
} else {
	read_config_option('servcheck_test_max_duration');
}

$use_processes = min($tests, $max_processes);

if ($tests > 0) {
	for ($f = 1; $f <= $use_processes; $f++) {
		if (!register_process_start('servcheck', $taskname, $f, $timeout)) {
			cacti_log(sprintf('WARNING: Not Running Service Check %s it is still running', $test['name']), false, 'SERVCHECK');
		} else {
			servcheck_debug('Launching Servceck process ' . $f);

			$command = read_config_option('path_php_binary');
			$args    = '-q "' . $config['base_path'] . '/plugins/servcheck/servcheck_process.php" --process=' . $f . ($debug ? ' --debug' : '') . ($force ? ' --force' : '');

			exec_background($command, $args);
		}
	}
} else {
	servcheck_debug('No enabled tests, nothing to do.');
}

/**
 * Waiting for all processes to end
 */
while (true) {
	$running = db_fetch_cell_prepared('SELECT COUNT(id)
		FROM processes
		WHERE tasktype = ?
		AND taskname = ?',
		['servcheck', $taskname]);

	if ($running == 0) {
		break;
	} else {
		sleep(1);
	}
}

// stats
$stat_ok        = 0;
$stat_ko        = 0;
$stat_search_ok = 0;
$stat_search_ko = 0;

if ($tests > 0) {
	$tests = db_fetch_assoc_prepared("SELECT *
		FROM plugin_servcheck_test
		WHERE
		$sql_where",
		$params);

	foreach ($tests as $test) {
		$test_last = db_fetch_row_prepared('SELECT result, result_search
			FROM plugin_servcheck_log
			WHERE test_id = ?
			ORDER BY id DESC LIMIT 1',
			[$test['id']]);

		if (isset($test_last['result']) && ($test_last['result'] == 'ok' || $test_last['result'] == 'not yet')) {
			$stat_ok++;
		} else {
			$stat_ko++;
		}

		if (isset($test_last['result_search']) && ($test_last['result_search'] == 'ok' || $test_last['result_search'] == 'not yet' || $test_last['result_search'] == 'not tested')) {
			$stat_search_ok++;
		} else {
			$stat_search_ko++;
		}
	}
}

$end   = microtime(true);
$ttime = round($end - $start, 2);

$stats = 'Time:' . $ttime . ' Checks:' . cacti_sizeof($tests) .
	' Results (ok/problem):' . $stat_ok . '/' . $stat_ko .
	' Search results (ok/problem):' . $stat_search_ok . '/' . $stat_search_ko;

cacti_log("SERVCHECK STATS: $stats", false, 'SYSTEM');

if ($poller_id == 1) {
	set_config_option('stats_servcheck', $stats);

	// Remove old logs
	$t = time() - (86400 * 30);

	db_execute_prepared('DELETE FROM plugin_servcheck_log
		WHERE last_check < FROM_UNIXTIME(?)',
		[$t]);
}

set_config_option('stats_servcheck_' . $poller_id, $stats);

unregister_process('servcheck', 'master', $poller_id);

/**
 * sig_handler - provides a generic means to catch exceptions to the Cacti log.
 *
 * @param int $signo The signal that was thrown by the interface.
 *
 * @return void
 */
function sig_handler($signo) {
	global $force, $poller_id, $taskname;

	switch ($signo) {
		case SIGTERM:
		case SIGINT:
		case SIGUSR1:
			cacti_log("WARNING: Service Check Poller 'master' is shutting down by signal!", false, 'SERVCHECK');

			if (!$force) {
				unregister_process('servcheck', 'master', $poller_id, getmypid());
			}

			$processes = db_fetch_assoc_prepared('SELECT *
				FROM processes
				WHERE tasktype = "servcheck"
				AND taskname = ?',
				[$taskname]);

			cacti_log('Signaling ' . cacti_sizeof($processes), false, 'SERVCHECK');

			if (cacti_sizeof($processes)) {
				foreach ($processes as $p) {
					posix_kill($p['pid'], SIGINT);

					db_execute_prepared('DELETE FROM processes
						WHERE pid = ?
						AND tasktype = "servcheck"
						AND taskname = ?',
						[$p['pid'], "child:$poller_id"]);
				}
			}

			exit(1);
		default:
			// ignore all other signals
	}
}

/**
 * display_version - displays version information
 */
function display_version() {
	global $config;

	if (!function_exists('plugin_servcheck_version')) {
		include_once($config['base_path'] . '/plugins/servcheck/setup.php');
	}

	$info = plugin_servcheck_version();
	print 'Cacti Service Check Master Process, Version ' . $info['version'] . ', ' . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * display_help - displays the usage of the function
 */
function display_help() {
	display_version();

	print PHP_EOL . 'usage: poller_servcheck.php [--debug] [--force]' . PHP_EOL . PHP_EOL;
	print 'This binary will exec all the Service check child processes.' . PHP_EOL . PHP_EOL;
	print '--force    - Force all the service checks to run now. Run even if the job is disabled or set to run less frequently than every poller cycle' . PHP_EOL . PHP_EOL;
	print '--debug    - Display verbose output during execution' . PHP_EOL . PHP_EOL;
}
