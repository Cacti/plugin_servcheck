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

// we are not talking to the browser
$dir = __DIR__;
chdir($dir);

if (strpos($dir, 'plugins') !== false) {
	chdir('../../');
}

require_once('./include/cli_check.php');
require_once($config['base_path'] . '/plugins/servcheck/includes/functions.php');
require($config['base_path'] . '/plugins/servcheck/includes/arrays.php');

// process calling arguments
$parms = $_SERVER['argv'];
array_shift($parms);

global $debug, $poller_id, $force;

$debug   = false;
$force   = false;
$test_id = 0;
$process = 0;
$start   = microtime(true);
$max_mem = 0;

$poller_interval   = read_config_option('poller_interval');
$poller_id         = $config['poller_id'];

if (cacti_sizeof($parms)) {
	foreach ($parms as $parameter) {
		if (strpos($parameter, '=')) {
			[$arg, $value] = explode('=', $parameter);
		} else {
			$arg   = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--id':
				$test_id = intval($value);

				break;
			case '--process':
				$process = intval($value);

				break;
			case '-d':
			case '--debug':
				$debug = true;

				break;
			case '--version':
			case '-V':
			case '-v':
				display_version();

				unregister_process('servcheck', "child:$poller_id");

				exit;
			case '--help':
			case '-H':
			case '-h':
				display_help();

				unregister_process('servcheck', "child:$poller_id");

				exit;
			case '--force':
			case '-F':
			case '-f':
				$force = true;

				break;
			default:
				print 'ERROR: Invalid Parameter ' . $parameter . PHP_EOL . PHP_EOL;
				display_help();

				unregister_process('servcheck', "child:$poller_id");

				exit;
		}
	}
}

if ($test_id > 0 && $process > 0) {
	print 'ERROR: Specify a test id or process number, not both' . PHP_EOL;

	unregister_process('servcheck', "child:$poller_id");

	exit(1);
}

if ($test_id <= 0 && $process <= 0) {
	print 'ERROR: You must specify a test id or process number' . PHP_EOL;

	unregister_process('servcheck', "child:$poller_id");

	exit(1);
}

servcheck_check_debug();

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

$tests = db_fetch_cell_prepared('SELECT count(*) 
	FROM plugin_servcheck_test 
	WHERE ' . $sql_where,
	$params);

$use_processes = min($tests, $max_processes);

if ($process > 0) {
	if ($process > $use_processes) {
		print 'ERROR: Process number is bigger than max. servcheck process' . PHP_EOL;

		unregister_process('servcheck', "child:$poller_id", $process);

		exit(1);
	} else {
		$x = $process - 1;

		$baseSize  = intdiv($tests, $use_processes);
		$remainder = $tests % $use_processes;

		$extraBefore = min($x, $remainder);

		$length = $baseSize + ($x < $remainder ? 1 : 0);
		$offset = $x * $baseSize + $extraBefore;

		array_unshift($params, ($poller_interval - 10));

		$tests = db_fetch_assoc_prepared("SELECT *, UNIX_TIMESTAMP(DATE_ADD(last_check,
			INTERVAL (? * how_often) SECOND)) AS next_run
			FROM plugin_servcheck_test
			WHERE $sql_where
			LIMIT $offset,  $length",
			$params);

		print "Process: $process, offset: $offset, length: $length" . PHP_EOL;
	}
}

if ($test_id > 0) {
	$sql_where .= 'AND id = ?';
	$params[] = $test_id;
	array_unshift($params, ($poller_interval - 10));

	$tests = db_fetch_assoc_prepared("SELECT *, UNIX_TIMESTAMP(DATE_ADD(last_check,
		INTERVAL (? * how_often) SECOND)) AS next_run
		FROM plugin_servcheck_test
		WHERE $sql_where",
		$params);
}

if (!cacti_sizeof($tests)) {
	print 'ERROR: Test/tests not Found!' . PHP_EOL;

	unregister_process('servcheck', "child:$poller_id", $process);

	exit(1);
}

foreach ($tests as $test) {
	$max_mem = memory_get_peak_usage(true);

	if (function_exists('memory_reset_peak_usage')) {
		memory_reset_peak_usage();
	}

	servcheck_debug(str_pad('Starting test ' . $test['name'], 10));
	servcheck_run_test($test, $force);
}

unregister_process('servcheck', "child:$poller_id", $process);

$rusage = getrusage();

$end    = microtime(true);
$stats  = sprintf('Time:%.2f, Stats:%s/%s, Down triggered:%s, Duration triggered:%s, Memory:%s MB, CPUuser:%.2f CPUsystem:%.2f',
	$end - $start,
	$test['stats_ok'],
	$test['stats_bad'],
	($test['triggered'] == 0 ? 'No' : 'Yes'),
	($test['triggered_duration'] == 0 ? 'No' : 'Yes'),
	$max_mem / 1024 / 1024,
	$rusage['ru_utime.tv_sec'] + $rusage['ru_utime.tv_usec'] / 1E6,
	$rusage['ru_stime.tv_sec'] + $rusage['ru_stime.tv_usec'] / 1E6);

servcheck_debug($stats);

function servcheck_run_test($test, $force) {
	global $config;

	$usage_start = getrusage();

	$cert_expiry_days          = read_config_option('servcheck_certificate_expiry_days');
	$test['new_notify_expire'] = false;

	$poller = db_fetch_cell_prepared('SELECT * FROM poller WHERE id = ?',
		[$test['poller_id']]);

	if ($poller == false) {
		print 'Selected poller not found, changing to poller 1' . PHP_EOL;

		db_execute_prepared('UPDATE plugin_servcheck_test
			SET poller_id = 1
			WHERE id = ?',
			[$test['poller_id']]);
	}

	$logs = db_fetch_cell_prepared('SELECT COUNT(*)
		FROM plugin_servcheck_log
		WHERE test_id = ?',
		[$test['id']]);

	if ($logs > 0 && $test['next_run'] > time() && !$force) {
		servcheck_debug('INFO: Test "' . $test['name'] . '" skipped. Not the right time to run the test.');

		return false;
	}

	if (api_plugin_is_enabled('maint')) {
		require_once($config['base_path'] . '/plugins/maint/functions.php');
	}

	if (function_exists('plugin_maint_check_servcheck_test')) {
		if (plugin_maint_check_servcheck_test($test['id'])) {
			servcheck_debug('Maintenance schedule active, skipped.');

			return false;
		}
	}

	$test['days_left'] = 0;
	$test['duration']  = false;

	// try it three times. First valid result skips next attempts
	$x       = 0;
	$results = [];

	[$category, $service] = explode('_', $test['type']);

	servcheck_debug('Category: ' . $category);
	servcheck_debug('Service: ' . $service);

	while ($x < $test['attempt']) {
		$x++;

		servcheck_debug('Service Check attempt ' . $x);

		if (!function_exists('curl_init') && ($category == 'web' || $category == 'smb' || $category == 'ldap' ||
			$category == 'ftp' || $category == 'mqtt' || $category == 'rest' || $service == 'doh')) {
			print 'FATAL: You must install php-curl to use this test' . PHP_EOL;

			servcheck_debug('Test ' . $test['id'] . ' requires php-curl library');

			$results['result']        = 'error';
			$results['curl']          = false;
			$results['error']         = 'missing php-curl library';
			$results['result_search'] = 'not tested';
			$results['start']         = microtime(true);
			$results['duration']      = 0;
			$results['data']          = '';

			continue;
		}

		switch ($category) {
			case 'web':
			case 'ldap':
			case 'smb':
				require_once($config['base_path'] . '/plugins/servcheck/includes/test_curl.php');
				$results = curl_try($test);

				break;
			case 'mail':
				require_once($config['base_path'] . '/plugins/servcheck/includes/test_mail.php');
				$results = mail_try($test);

				break;
			case 'mqtt':
				require_once($config['base_path'] . '/plugins/servcheck/includes/test_mqtt.php');
				$results = mqtt_try($test);

				break;
			case 'dns':
				require_once($config['base_path'] . '/plugins/servcheck/includes/test_dns.php');

				if ($service == 'doh') {
					$results = doh_try($test);
				} else {
					$results = dns_try($test);
				}

				break;
			case 'rest':
				require_once($config['base_path'] . '/plugins/servcheck/includes/test_restapi.php');
				$results = restapi_try($test);

				break;
			case 'snmp':
				require_once($config['base_path'] . '/plugins/servcheck/includes/test_snmp.php');
				$results = snmp_try($test);

				break;
			case 'ssh':
				require_once($config['base_path'] . '/plugins/servcheck/includes/test_ssh.php');
				$results = ssh_try($test);

				break;
			case 'ftp':
				require_once($config['base_path'] . '/plugins/servcheck/includes/test_ftp.php');
				$results = ftp_try($test);

				break;
		}

		$results['duration'] = round(microtime(true) - $results['start'], 4);

		if ($results['result'] == 'ok') {
			break;
		} else {
			servcheck_debug('Attempt ' . $x . ' was unsuccessful');
			servcheck_debug('Result: ' . clean_up_lines(var_export($results, true)));
		}

		servcheck_debug('Sleeping 1 second');

		sleep(1);
	}

	$results['x'] = $x;

	if (cacti_sizeof($results) == 0) {
		servcheck_debug('Unknown error for test ' . $test['id']);

		return false;
	}

	$results['time']     = time();
	$test['expiry_date'] = null;

	if ($results['result'] == 'ok' && $test['certexpirenotify']) {
		if (isset($results['options']['certinfo'][0])) { // curl
			servcheck_debug('Returned certificate info: ' . clean_up_lines(var_export($results['options']['certinfo'], true)));
			$parsed = date_parse_from_format('M j H:i:s Y e', $results['options']['certinfo'][0]['Expire date']);
			// Prepare to retrieve the local expiry date of certificate instead of UTC date
			$dt = new DateTime("{$parsed['year']}-{$parsed['month']}-{$parsed['day']} {$parsed['hour']}:{$parsed['minute']}:{$parsed['second']}",
				new DateTimeZone('UTC')
			);

			$local_tz = date_default_timezone_get();

			if (empty($local_tz)) {
				$local_tz = 'UTC';
			}

			$dt->setTimezone(new DateTimeZone($local_tz));
			$exp                 = $dt->getTimestamp();
			$test['days_left']   = round(($exp - time()) / 86400,1);
			$test['expiry_date'] = date(date_time_format(), $exp);
		} elseif (isset($results['cert_valid_to'])) {
			// only for log
			$test['days_left']   = round(($results['cert_valid_to'] - time()) / 86400,1);
			$test['expiry_date'] = date(date_time_format(), $results['cert_valid_to']);
		}
	}

	$last_log = db_fetch_row_prepared('SELECT *
		FROM plugin_servcheck_log
		WHERE test_id = ? ORDER BY id DESC LIMIT 1',
		[$test['id']]);

	if (!$last_log) {
		$last_log['result']        = 'not yet';
		$last_log['result_search'] = 'not yet';
	}

	if ($results['result'] == 'ok') {
		$test['stats_ok'] += 1;
	} else {
		$test['stats_bad'] += 1;
	}

	$test['notify_result']      = false;
	$test['notify_search']      = false;
	$test['notify_duration']    = false;
	$test['notify_certificate'] = false;

	servcheck_debug('Checking for triggerers');

	if ($last_log['result'] != $results['result'] || $results['result'] != 'ok') {
		servcheck_debug('Result changed, notification will be send');

		if ($results['result'] != 'ok') {
			$test['failures']++;

			if ($test['failures'] >= $test['downtrigger'] && $test['triggered'] == 0) {
				$test['notify_result'] = true;
				$test['triggered']     = 1;
			}
		}

		if ($results['result'] == 'ok') {
			if ($test['triggered'] == 1) {
				$test['notify_result'] = true;
			}

			$test['triggered'] = 0;
			$test['failures']  = 0;
		}
	} else { // only for stats, without notification
		if ($results['result'] != 'ok') {
			$test['failures']++;

			if ($test['failures'] >= $test['downtrigger']) {
				$test['triggered'] = 1;
			}
		} else {
			$test['triggered'] = 0;
			$test['failures']  = 0;
		}
	}

	// checks only if test passed or some search string exists
	if ($results['result_search'] != 'not tested' && $results['result'] == 'ok') {
		if ($last_log['result_search'] != $results['result_search']) {
			servcheck_debug('Search result changed, notification will be send');
			$test['notify_search'] = true;
		}
	}

	// check certificate expiry
	if ($test['certexpirenotify'] && $cert_expiry_days > 0 && $test['days_left'] < $cert_expiry_days && $results['result'] == 'ok') {
		// notify once per day
		$new_notify = db_fetch_cell_prepared('SELECT UNIX_TIMESTAMP(DATE_ADD(last_exp_notify, INTERVAL 1 DAY))
			FROM plugin_servcheck_test
			WHERE id = ?',
			[$test['id']]);

		if ($new_notify < time()) {
			servcheck_debug('Certificate will expire soon (or is expired), will notify about expiration');
			$test['notify_certificate'] = true;
			$test['certificate_state']  = 'ko';
			$test['new_notify_expire']  = true;
		}
	}

	// check renewed cert
	if ($test['certexpirenotify'] && $results['result'] == 'ok') {
		if (isset($last_log['cert_expire']) &&
			$last_log['cert_expire'] != '0000-00-00 00:00:00' && !is_null($last_log['cert_expire'])) {
			$days_before = round((strtotime($last_log['cert_expire']) - strtotime($last_log['last_check'])) / 86400,1);

			if ($test['days_left'] > 0 && $test['days_left'] > $days_before) {
				if (!servcheck_summer_time_changed()) {
					servcheck_debug('Renewed or changed certificate, notification will be send');

					$test['notify_certificate'] = true;
					$test['certificate_state']  = 'new';
				} else {
					servcheck_debug('Renewed or changed certificate, but summer/winter time change detected. Skipping notification.');
				}
			}
		}
	}

	// long duration
	if ($test['duration_trigger'] > 0 && $test['duration_count'] > 0 && $results['result'] == 'ok') {
		$test['durs'] = [];

		if ($results['duration'] > $test['duration_trigger']) {
			$test['triggered_duration']++;
		}

		$test['durs'][] = $results['duration'] . ' (' . date('Y-m-d H:i:s', $results['time']) . ')';

		if ($test['duration_count'] > 1) {
			$durations = db_fetch_assoc_prepared('SELECT duration, last_check, result
				FROM plugin_servcheck_log
				WHERE test_id = ?
				ORDER BY id DESC LIMIT ' . ($test['duration_count'] - 1),
				[$test['id']]);

			foreach ($durations as $d) {
				$test['durs'][] = $d['duration'] . ' (' . $d['last_check'] . ')';
			}
		}

		if ($test['triggered_duration'] == $test['duration_count']) {
			servcheck_debug('Long duration detected, sending notification');

			$test['notify_duration'] = true;
			$test['duration_state']  = 'ko';
			$test['triggered_duration']++;
		} elseif ($test['triggered_duration'] > $test['duration_count']) {
			servcheck_debug('Long duration issue continue');

			$test['triggered_duration']++;
		}

		if ($results['duration'] < $test['duration_trigger'] && $test['triggered_duration'] >= $test['duration_count']) {
			servcheck_debug('Normal duration detected, sending notification');

			$test['notify_duration']    = true;
			$test['duration_state']     = 'ok';
			$test['triggered_duration'] = 0;
		}
	}

	if ($test['notify_result'] || $test['notify_search'] || $test['notify_duration'] || $test['notify_certificate']) {
		if (read_config_option('servcheck_disable_notification') == 'on') {
			cacti_log('Notifications are disabled, notification will not send for test ' . $test['name'], false, 'SERVCHECK');
			servcheck_debug('Notification disabled globally');
		} else {
			if ($test['notify'] != '') {
				servcheck_debug('Time to send email');
				plugin_servcheck_send_notification($results, $test, $last_log);
			} else {
				servcheck_debug('Time to send email, but email notification for this test is disabled');
			}
		}

		$command        = read_config_option('servcheck_change_command');
		$command_enable = read_config_option('servcheck_enable_scripts');

		if ($command_enable && $command != '' && $test['run_script'] == 'on') {
			servcheck_debug('Time to run command');

			putenv('SERVCHECK_TEST_NAME=' . $test['name']);
			putenv('SERVCHECK_EXTERNAL_ID=' . $test['external_id']);
			putenv('SERVCHECK_TEST_TYPE=' . $test['type']);
			putenv('SERVCHECK_POLLER=' . $test['poller_id']);
			putenv('SERVCHECK_RESULT=' . $results['result']);
			putenv('SERVCHECK_RESULT_SEARCH=' . $results['result_search']);
			putenv('SERVCHECK_CERTIFICATE_EXPIRATION=' . (isset($test['expiry_date']) ? $test['expiry_date'] : 'Not tested or unknown '));

			if (file_exists($command) && is_executable($command)) {
				$output = [];
				$return = 0;

				exec($command, $output, $return);

				cacti_log('Servcheck Command for test[' . $test['id'] . '] Command[' . $command . '] ExitStatus[' . $return . '] Output[' . implode(' ', $output) . ']', false, 'SERVCHECK');
			} else {
				cacti_log('WARNING: Servcheck Command for test[' . $test['id'] . '] Command[' . $command . '] Is either Not found or Not executable!', false, 'SERVCHECK');
			}
		}
	} else {
		servcheck_debug('Nothing triggered');
	}

	servcheck_debug('Updating Statistics');

	$usage_end = getrusage();
	$user_cpu  = ($usage_end['ru_utime.tv_sec'] + $usage_end['ru_utime.tv_usec']) - ($usage_start['ru_utime.tv_sec'] + $usage_start['ru_utime.tv_usec']);
	$sys_cpu   = ($usage_end['ru_stime.tv_sec'] + $usage_end['ru_stime.tv_usec']) - ($usage_start['ru_stime.tv_sec'] + $usage_start['ru_stime.tv_usec']);

	if ($results['curl']) {
		if (!isset($results['curl_return'])) {
			$results['curl_return'] = 'N/A';
		}

		$curl  = 'HTTP code: ' . $results['options']['http_code'] . ', DNS time: ' . round($results['options']['namelookup_time'], 3) . ', ';
		$curl .= 'Conn. time: ' . round($results['options']['connect_time'],3) . ', Redir. time: ' . round($results['options']['redirect_time'], 3) . ', ';
		$curl .= 'Redir. count: ' . $results['options']['redirect_time'] . ', ';
		$curl .= 'Speed: ' . $results['options']['speed_download'] . ', CURL code: ' . $results['curl_return'];
	} else {
		$curl = 'N/A';
	}

	if (!isset($test['expiry_date'])) {
		$save_exp = '0000-00-00 00:00:00';
	} else {
		$save_exp = $test['expiry_date'];
	}

	if (!isset($results['data'])) {
		$results['data'] = '';
	}

	db_execute_prepared('INSERT INTO plugin_servcheck_log
		(test_id, duration, last_check, cert_expire, result, error, result_search,
		curl_response, attempt, cpu_user, cpu_system, memory, returned_data_size)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
		[$test['id'], $results['duration'], date('Y-m-d H:i:s', $results['time']), $save_exp,
			$results['result'], $results['error'], $results['result_search'], $curl, $results['x'],
			$user_cpu / 1E6,
			$sys_cpu / 1E6,
			memory_get_peak_usage(true) / 1024 / 1024,
			strlen($results['data'])
		]
	);

	if ($test['new_notify_expire']) {
		$exp_notify = date(date_time_format());
	} else {
		$exp_notify = db_fetch_cell_prepared('SELECT last_exp_notify
			FROM plugin_servcheck_test
			WHERE id = ?',
			[$test['id']]);
	}

	db_execute_prepared('UPDATE plugin_servcheck_test
		SET triggered = ?, triggered_duration = ?, failures = ?, last_check = ?, last_exp_notify = ?,
		stats_ok = ?, stats_bad = ?, last_returned_data = ?, last_duration = ?,
		last_result = ?, last_result_search = ?, last_attempt = ?, last_error = ?,
		cpu_user = ?, cpu_system = ?,
		memory = ?
		WHERE id = ?',
		[$test['triggered'], $test['triggered_duration'], $test['failures'], date('Y-m-d H:i:s', $results['time']), $exp_notify,
			$test['stats_ok'], $test['stats_bad'], $results['data'], $results['duration'],
			$results['result'], $results['result_search'], $results['x'], $results['error'],
			$user_cpu / 1E6, $sys_cpu / 1E6,
			memory_get_peak_usage(true) / 1024 / 1024,
			$test['id']
		]
	);

	$retention = read_config_option('servcheck_data_retention');

	if ($retention > 0) {
		servcheck_debug('Deletion of logs from this test older than ' . $retention . ' days');

		db_execute_prepared('DELETE FROM plugin_servcheck_log
			WHERE test_id = ? AND last_check < now() - INTERVAL ? day',
			[$test['id'], $retention]);
	}
}

function plugin_servcheck_send_notification($results, $test, $last_log) {
	global $httperrors;
	$notify_list    = [];
	$notify_extra   = [];
	$notify_account = [];

	$cert_expiry_days  = read_config_option('servcheck_certificate_expiry_days');
	$local_tz          = date_default_timezone_get();

	if (empty($local_tz)) {
		$local_tz = 'UTC';
	}

	$servcheck_send_email_separately = read_config_option('servcheck_send_email_separately');

	if ($test['notify_accounts'] != '') {
		$tmp = db_fetch_row_prepared('SELECT email_address
			FROM user_auth
			WHERE id IN (' . $test['notify_accounts'] . ')');

		foreach ($tmp as $acc) {
			$notify_account[] = $acc;
		}
	}

	if (api_plugin_installed('thold') && $test['notify_list'] > 0) {
		$tmp = db_fetch_cell_prepared('SELECT emails
			FROM plugin_notification_lists
			WHERE id = ?',
			[$test['notify_list']]);

		$notify_list = explode(',', $tmp);
	}

	if (isset($test['notify_extra']) && $test['notify_extra'] != '') {
		$notify_extra = explode(',', $test['notify_extra']);
	}

	if (cacti_sizeof($notify_account) == 0 && cacti_sizeof($notify_extra) == 0 && cacti_sizeof($notify_list) == 0) {
		cacti_log('ERROR: No users to send SERVCHECK Notification for ' . $test['name'], false, 'SERVCHECK');
		servcheck_debug('No notification email or user');

		return true;
	}

	if ($test['notify_result']) {
		if ($results['result'] == 'ok') {
			$message['0']['subject'] = '[Cacti servcheck - ' . $test['name'] . '] Service Recovered';
		} else {
			$message['0']['subject'] = '[Cacti servcheck - ' . $test['name'] . '] Service Down';
		}

		$message[0]['text']  = '<h3>' . $message[0]['subject'] . '</h3>' . PHP_EOL;
		$message[0]['text'] .= '<table>' . PHP_EOL;
		$message[0]['text'] .= '<tr><td>Hostname:</td><td>' . $test['hostname'] . '</td></tr>' . PHP_EOL;

		if (!is_null($test['path']) && $test['path'] != '') {
			$message[0]['text'] .= '<tr><td>Path:</td><td>' . $test['path'] . '</td></tr>' . PHP_EOL;
		}

		$message[0]['text'] .= '<tr><td>Status:</td><td>' . ($results['result'] == 'ok' ? 'Recovering' : 'Down') . '</td></tr>' . PHP_EOL;
		$message[0]['text'] .= '<tr><td>Date:</td><td>' . date(date_time_format(), $results['time']) . '</td></tr>' . PHP_EOL;
		$message[0]['text'] .= '<tr><td>Attempt:</td><td>' . $results['x'] . '/' . $test['attempt'] . '</td></tr>' . PHP_EOL;
		$message[0]['text'] .= '<tr><td>Duration:</td><td>' . $results['duration'] . '</td></tr>' . PHP_EOL;
		$message[0]['text'] .= '<tr><td>Error/Reason:</td><td> ' . $results['error'] . '</td></tr>' . PHP_EOL;

		if ($test['certexpirenotify'] && $results['result'] == 'ok') {
			if ($test['days_left'] < 0) {
				$message[0]['text'] .= '<tr><td>Certificate expired:</td><td>' . abs($test['days_left']) . ' days ago (' . (isset($test['expiry_date']) ? $test['expiry_date'] . ' ' . $local_tz : 'Invalid Expiry Date') . ')</td></tr>' . PHP_EOL;
			} else {
				$message[0]['text'] .= '<tr><td>Certificate expires in:</td><td>' . $test['days_left'] . ' days (' . (isset($test['expiry_date']) ? $test['expiry_date'] . ' ' . $local_tz : 'Invalid Expiry Date') . ')</td></tr>' . PHP_EOL;
			}
		}

		if (isset($results['options']['http_code']) && $results['options']['http_code'] != 0) {
			$message[0]['text'] .= '<tr><td>HTTP Code:</td><td>' . $httperrors[$results['options']['http_code']] . '</td></tr>' . PHP_EOL;
		}

		if (isset($results['curl_response'])) {
			$message[0]['text'] .= '<tr><td>CURL response:</td><td>' . $results['curl_response'] . '</td></tr>' . PHP_EOL;
		}

		if ($test['notes'] != '') {
			$message[0]['text'] .= '<tr><td>Notes:</td><td>' . $test['notes'] . '</td></tr>' . PHP_EOL;
		}

		$message[0]['text'] .= '</table>' . PHP_EOL;
	}

	if ($test['notify_duration']) {
		if ($test['duration_state'] == 'ok') {
			$message[1]['subject'] = '[Cacti servcheck - ' . $test['name'] . '] Service long duration restored to normal';
		} else {
			$message[1]['subject'] = '[Cacti servcheck - ' . $test['name'] . '] Service long duration detected';
		}

		$message[1]['text']  = '<h3>' . $message[1]['subject'] . '</h3>' . PHP_EOL;
		$message[1]['text'] .= '<table>' . PHP_EOL;
		$message[1]['text'] .= '<tr><td>Attempt:</td><td>' . $results['x'] . '/' . $test['attempt'] . '</td></tr>' . PHP_EOL;
		$message[1]['text'] .= '<tr><td>Last three duration:</td><td>' . implode(', ', $test['durs']) . '</td></tr>' . PHP_EOL;
		$message[1]['text'] .= '<tr><td>Hostname:</td><td>' . $test['hostname'] . '</td></tr>' . PHP_EOL;

		if ($test['notes'] != '') {
			$message[1]['text'] .= '<tr><td>Notes:</td><td>' . $test['notes'] . '</td></tr>' . PHP_EOL;
		}

		$message[1]['text'] .= '</table>' . PHP_EOL;
	}

	// search string notification
	if ($test['notify_search']) {
		$message[2]['subject'] = '[Cacti servcheck - ' . $test['name'] . '] Search result is different than last check';

		$message[2]['text']  = '<h3>' . $message[2]['subject'] . '</h3>' . PHP_EOL;
		$message[2]['text'] .= '<table>' . PHP_EOL;
		$message[2]['text'] .= '<tr><td>Hostname:</td><td>' . $test['hostname'] . '</td></tr>' . PHP_EOL;

		if (!is_null($test['path']) && $test['path'] != '') {
			$message[2]['text'] .= '<tr><td>Path:</td><td>' . $test['path'] . '</td></tr>' . PHP_EOL;
		}
		$message[2]['text'] .= '<tr><td>Date:</td><td>' . date(date_time_format(), $results['time']) . '</td></tr>' . PHP_EOL;
		$message[2]['text'] .= '<tr><td>Attempt:</td><td>' . $results['x'] . '/' . $test['attempt'] . '</td></tr>' . PHP_EOL;
		$message[2]['text'] .= '<tr><td>Duration:</td><td>' . $results['duration'] . '</td></tr>' . PHP_EOL;
		$message[2]['text'] .= '<tr><td>Previous search:</td><td>' . $last_log['result_search'] . '</td></tr>' . PHP_EOL;
		$message[2]['text'] .= '<tr><td>Actual search:</td><td>' . $results['result_search'] . '</td></tr>' . PHP_EOL;

		if ($test['notes'] != '') {
			$message[2]['text'] .= '<tr><td>Notes:</td><td>' . $test['notes'] . '</td></tr>' . PHP_EOL;
		}

		$message[2]['text'] .= '</table>' . PHP_EOL;
	}

	// Skip notifications for 'new' certificate states (e.g., renewals/changes) to avoid unnecessary emails.
	// Only send notifications for expiring and expired certificates.
	if ($test['notify_certificate'] && $test['certificate_state'] != 'new') {
		if ($test['days_left'] < 0) {
			$message[3]['subject'] = '[Cacti servcheck - ' . $test['name'] . '] Certificate expired ' . abs($test['days_left']) . ((abs($test['days_left']) <= 1) ? ' day ago' : ' days ago');
		} elseif ($test['days_left'] <= $cert_expiry_days) {
			$message[3]['subject'] = '[Cacti servcheck - ' . $test['name'] . '] Certificate will expire in less than ' . $cert_expiry_days . ' days, (days left: ' . $test['days_left'] . ')';
		}

		$message[3]['text']  = '<h3>' . $message[3]['subject'] . '</h3>' . PHP_EOL;

		$message[3]['text'] .= '<table>' . PHP_EOL;
		$message[3]['text'] .= '<tr><td>Hostname:</td><td>' . $test['hostname'] . '</td></tr>' . PHP_EOL;

		if (!is_null($test['path']) && $test['path'] != '') {
			$message[3]['text'] .= '<tr><td>Path:</td><td>' . $test['path'] . '</td></tr>' . PHP_EOL;
		}
		$message[3]['text'] .= '<tr><td>Date:</td><td>' . date(date_time_format(), $results['time']) . '</td></tr>' . PHP_EOL;
		$message[3]['text'] .= '<tr><td>Attempt:</td><td>' . $results['x'] . '/' . $test['attempt'] . '</td></tr>' . PHP_EOL;
		$message[3]['text'] .= '<tr><td>Duration:</td><td>' . $results['duration'] . '</td></tr>' . PHP_EOL;

		if ($test['certexpirenotify']) {
			if ($test['days_left'] < 0) {
				$message[3]['text'] .= '<tr><td>Certificate expired:</td><td>' . abs($test['days_left']) . ' days ago (' . (isset($test['expiry_date']) ? $test['expiry_date'] . ' ' . $local_tz : 'Invalid Expiry Date') . ')</td></tr>' . PHP_EOL;
			} else {
				$message[3]['text'] .= '<tr><td>Certificate expires in:</td><td>' . $test['days_left'] . ' days (' . (isset($test['expiry_date']) ? $test['expiry_date'] . ' ' . $local_tz : 'Invalid Expiry Date') . ')</td></tr>' . PHP_EOL;
			}
		}

		if ($test['notes'] != '') {
			$message[3]['text'] .= '<tr><td>Notes:</td><td>' . $test['notes'] . '</td></tr>' . PHP_EOL;
		}

		$message[3]['text'] .= '</table>' . PHP_EOL;
	}

	$to = array_merge($notify_list, $notify_account, $notify_extra);

	if (isset($message) && is_array($message)) {
		if ($servcheck_send_email_separately != 'on') {
			$addresses = implode(',', $to);

			foreach ($message as $m) {
				if ($test['notify_format'] == 'plain') {
					$m['text'] = strip_tags($m['text']);
				}

				plugin_servcheck_send_email($addresses, $m['subject'], $m['text']);
			}
		} else {
			foreach ($message as $m) {
				if ($test['notify_format'] == 'plain') {
					$m['text'] = strip_tags($m['text']);
				}

				foreach ($to as $u) {
					plugin_servcheck_send_email($u, $m['subject'], $m['text']);
				}
			}
		}
	}
}

function plugin_servcheck_send_email($to, $subject, $message) {
	$from_name  = read_config_option('settings_from_name');
	$from_email = read_config_option('settings_from_email');

	if ($from_email == '') {
		$from_email = 'cacti@cacti.net';
	}

	if ($from_name != '') {
		$from[0] = $from_email;
		$from[1] = $from_name;
	} else {
		$from    = $from_email;
	}

	if (defined('CACTI_VERSION')) {
		$v = CACTI_VERSION;
	} else {
		$v = get_cacti_version();
	}

	$headers['User-Agent'] = 'Cacti-servcheck-v' . $v;

	$message_text = strip_tags($message);

	mailer($from, $to, '', '', '', $subject, $message, $message_text, '', $headers);
}

/**
 * sig_handler - provides a generic means to catch exceptions to the Cacti log.
 *
 * @param int $signo The signal that was thrown by the interface.
 *
 * @return void
 */
function sig_handler($signo) {
	global $force, $poller_id, $process, $taskname;

	switch ($signo) {
		case SIGTERM:
		case SIGINT:
			cacti_log("WARNING: Service Check Poller 'master' is shutting down by signal!", false, 'SERVCHECK');

			unregister_process('servcheck', "child:$poller_id", $process, getmypid());

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
		require_once($config['base_path'] . '/plugins/servcheck/setup.php');
	}

	$info = plugin_servcheck_version();

	print 'Cacti Web Service Check Processor, Version ' . $info['version'] . ', ' . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * display_help - displays the usage of the function
 */
function display_help() {
	display_version();

	print PHP_EOL;
	print 'usage: ' . PHP_EOL;
	print 'servcheck_process.php --id=N [--debug] [--force]' . PHP_EOL . PHP_EOL;
	print 'or: ' . PHP_EOL;
	print 'servcheck_process.php --process=N [--debug] [--force]' . PHP_EOL . PHP_EOL;
	print 'This binary will run the Service check for the Servcheck plugin.' . PHP_EOL . PHP_EOL;
	print '--id=N      - The Test ID from the Servcheck database.' . PHP_EOL;
	print '--process=N - This process then processes (total number of tests/max. process) tests.' . PHP_EOL;
	print '--force     - Run even if the job is disabled or set to run less frequently than every poller cycle' . PHP_EOL;
	print '--debug     - Display verbose output during execution' . PHP_EOL . PHP_EOL;
}
