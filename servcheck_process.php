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

/* we are not talking to the browser */
$dir = dirname(__FILE__);
chdir($dir);

if (strpos($dir, 'plugins') !== false) {
	chdir('../../');
}

require('./include/cli_check.php');
include_once($config['base_path'] . '/plugins/servcheck/includes/functions.php');
include($config['base_path'] . '/plugins/servcheck/includes/arrays.php');

ini_set('max_execution_time', '21');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

global $debug;

$debug   = false;
$force   = false;
$test_id = 0;

$poller_interval   = read_config_option('poller_interval');
$cert_expiry_days  = read_config_option('servcheck_certificate_expiry_days');
$new_notify_expire = false;

if (cacti_sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
		case '--id':
			$test_id = intval($value);
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
		case '--force':
		case '-F':
		case '-f':
			$force = true;
			break;
		default:
			print 'ERROR: Invalid Parameter ' . $parameter . PHP_EOL . PHP_EOL;
			display_help();
			exit;
		}
	}
}


if (empty($test_id) || !is_int($test_id)) {
	print 'ERROR: You must specify a test id' . PHP_EOL;
	exit(1);
}

plugin_servcheck_check_debug();

$enabled = '';

if (!$force) {
	$enabled = 'AND enabled = "on"';
}

$test = db_fetch_row_prepared('SELECT *, UNIX_TIMESTAMP(DATE_ADD(lastcheck, INTERVAL (? * how_often) SECOND)) AS next_run
	FROM plugin_servcheck_test
	WHERE id = ? ' . $enabled,
	array(($poller_interval-10), $test_id));

if (!cacti_sizeof($test)) {
	print 'ERROR: Test not Found' . PHP_EOL;
	exit(1);
}

$poller = db_fetch_cell_prepared('SELECT * FROM poller WHERE id = ?',
	array($test['poller_id']));

if ($poller == false) {
	print 'Selected poller not found, changing to poller 1' . PHP_EOL;
	db_execute_prepared('UPDATE plugin_servcheck_test
		SET poller_id = 1
		WHERE id = ?',
		array($test['poller_id']));
}

$logs = db_fetch_cell_prepared('SELECT count(*) FROM plugin_servcheck_log WHERE test_id = ?',
	array($test['id']));

if ($logs > 0 && $test['next_run'] > time() && !$force) {
	plugin_servcheck_debug('INFO: Test "' . $test['name'] . '" skipped. Not the right time to run the test.', $test);
	exit(0);
}

if (api_plugin_is_enabled('maint')) {
	include_once($config['base_path'] . '/plugins/maint/functions.php');
}

if (function_exists('plugin_maint_check_servcheck_test')) {
	if (plugin_maint_check_servcheck_test($test_id)) {
		plugin_servcheck_debug('Maintenance schedule active, skipped ' , $test);
		exit(0);
	}
}


$test['days'] = 0;
$test['duration'] = false;
register_startup($test_id);

/* attempt to get results 3 times before exiting */
$x = 1;
$results = array();

while ($x <= $test['attempt']) {
	plugin_servcheck_debug('Service Check attempt ' . $x, $test);

	list($category, $service) = explode('_', $test['type']);

	plugin_servcheck_debug('Category: ' . $category , $test);
	plugin_servcheck_debug('Service: ' . $service , $test);

	switch ($category) {

		case 'web':
		case 'ldap':
		case 'smb':

			include_once($config['base_path'] . '/plugins/servcheck/includes/test_curl.php');
			$results = curl_try($test);
			break;

		case 'mail':
			include_once($config['base_path'] . '/plugins/servcheck/includes/test_mail.php');
			$results = mail_try($test);
			break;

		case 'mqtt':
			include_once($config['base_path'] . '/plugins/servcheck/includes/test_mqtt.php');
			$results = mqtt_try($test);
			break;

		case 'dns':
			include_once($config['base_path'] . '/plugins/servcheck/includes/test_dns.php');
			if ($service == 'doh') {
				$results = doh_try($test);
			} else {
				$results = dns_try($test);
			}
			break;
		case 'rest':
			include_once($config['base_path'] . '/plugins/servcheck/includes/test_restapi.php');
			$results = restapi_try($test);
			break;

		case 'snmp':
			include_once($config['base_path'] . '/plugins/servcheck/includes/test_snmp.php');
			$results = snmp_try($test);
			break;

		case 'ssh':
			include_once($config['base_path'] . '/plugins/servcheck/includes/test_ssh.php');
			$results = ssh_try($test);
			break;

		case 'ftp':
			include_once($config['base_path'] . '/plugins/servcheck/includes/test_ftp.php');
			$results = ftp_try($test);
	}

	if (isset($results['result'])) {
		$results['duration'] = round(microtime(true) - $results['start'], 4);
		$results['x'] = $x;
		break;
	}
//!!pm - tady musi byt slozitejsi logika, protoze i kdyz se mi vrati chyba nebo se to vubec nepovede, tak musim zkouset znovu
//!!pm - budu pocitat i pokusy

	$x++;
	usleep(3000);
}

if (cacti_sizeof($results) == 0) {
	plugin_servcheck_debug('Unknown error for test ' . $test['id'], $test);
	exit('Unknown error for test ' . $test['id']);
}

plugin_servcheck_debug('failures:'. $test['stats_bad'] . ', triggered:' . $test['triggered'], $test);

$results['time'] = time();
$test['expiry_date'] = null;

if ($test['certexpirenotify']) {

	if (isset($results['options']['certinfo'][0])) { // curl
		plugin_servcheck_debug('Returned certificate info: ' .  clean_up_lines(var_export($results['options']['certinfo'], true))  , $test);
		$parsed = date_parse_from_format('M j H:i:s Y e', $results['options']['certinfo'][0]['Expire date']);
		$exp = mktime($parsed['hour'], $parsed['minute'], $parsed['second'], $parsed['month'], $parsed['day'], $parsed['year']);
		$test['days'] = round(($exp - time()) / 86400);
		$test['expiry_date'] = date(date_time_format(), $exp);
	} elseif (isset($results['cert_valid_to'])) {
		$test['days'] = floor(($results['cert_valid_to'] - time())/86400);
		$test['expiry_date'] = date(date_time_format(), $results['cert_valid_to']);
	}
}

$test['status_change'] = false;

$last_log = db_fetch_row_prepared('SELECT *
		FROM plugin_servcheck_log
		WHERE test_id = ? ORDER BY id DESC LIMIT 1',
		array ($test['id']));

if (!$last_log) {
	$last_log['result'] = 'not yet';
	$last_log['result_search'] = 'not yet';
}

if ($results['result'] == 'ok') {
	$test['stats_ok'] += 1;
} else {
	$test['stats_bad'] += 1;
}

if ($last_log['result'] != $results['result'] || $last_log['result_search'] != $results['result_search'] ||
	($test['certexpirenotify'] && $cert_expiry_days > 0 && $test['days'] < $cert_expiry_days)) {

	plugin_servcheck_debug('Checking for trigger', $test);

	$sendemail = false;

	if ($results['result'] != 'ok') {
		$test['failures']++;

		if ($test['failures'] >= $test['downtrigger'] && $test['triggered'] == 0) {
			$sendemail = true;
			$test['triggered'] = 1;
			$test['status_change'] = true;
		}
	}

	if ($results['result'] == 'ok') {
		if ($test['triggered'] == 1) {
			$sendemail = true;
			$test['status_change'] = true;
		}
			$test['triggered'] = 0;
			$test['failures'] = 0;
	}

	if ($last_log['result_search'] != $results['result_search']) {
		$sendemail = true;
	}

	if ($test['certexpirenotify'] && $cert_expiry_days > 0 && $test['days'] < $cert_expiry_days) {

		// notify once per day
		$new_notify = db_fetch_cell_prepared('SELECT UNIX_TIMESTAMP(DATE_ADD(last_exp_notify, INTERVAL 1 DAY))
			FROM plugin_servcheck_test
			WHERE id = ?',
			array($test['id']));

		if ($new_notify < time()) {
			plugin_servcheck_debug('Certificate will expire soon (or is expired), will notify about expiration', $test);

			$sendemail = true;
			$new_notify_expire = true;
		}
	}

	// long duration
	if ($test['duration_trigger'] > 0) {

		plugin_servcheck_debug('Checking test for log duration', $test);

		$trig = 0;
		$test['durs'] = array();

		$test['durs'][] = $results['duration'] . ' (' . date('Y-m-d H:i:s', $results['time']) . ')';

		$durations = db_fetch_assoc_prepared('SELECT duration, lastcheck FROM plugin_servcheck_log
			WHERE test_id = ? ORDER BY id DESC LIMIT 2',
			array($test['id']));

		if (cacti_sizeof($durations) == 2) {
			foreach ($durations as $d) {
				if ($d['duration'] > $test['duration_trigger']) {
					$trig++;
				}
				$test['durs'][] = $d['duration'] . ' (' . $d['lastcheck'] . ')';
			}
		}

		if ($trig == 2) {
			if ($results['duration'] > $test['duration_trigger']) {
				plugin_servcheck_debug('Long duration detected, sending notification', $test);
				$test['duration'] = true;
				$test['duration_state'] = 'ko';
				$sendemail = true;
			} elseif ($results['duration'] < $test['duration_trigger']) {
				plugin_servcheck_debug('Normal duration detected, sending notification', $test);
				$test['duration'] = true;
				$test['duration_state'] = 'ok';
				$sendemail = true;
			}
		}
	}

	if ($sendemail) {
		plugin_servcheck_debug('Time to send email', $test);

		if ($test['notify_format'] == 'plain') {
			plugin_servcheck_send_notification($results, $test, 'text', $last_log);
		} else {
			plugin_servcheck_send_notification($results, $test, '', $last_log);
		}
	}

	$command = read_config_option('servcheck_change_command');
	$command_enable = read_config_option('servcheck_enable_scripts');
	if ($command_enable && $command != '') {
		plugin_servcheck_debug('Time to run command', $test);

		putenv('SERVCHECK_TEST_NAME='              . $test['name']);
		putenv('SERVCHECK_EXTERNAL_ID='            . $test['external_id']);
		putenv('SERVCHECK_TEST_TYPE='              . $test['type']);
		putenv('SERVCHECK_POLLER='                 . $test['poller_id']);
		putenv('SERVCHECK_RESULT='                 . $results['result']);
		putenv('SERVCHECK_RESULT_SEARCH='          . $results['result_search']);
		putenv('SERVCHECK_CERTIFICATE_EXPIRATION=' . (isset($test['expiry_date']) ? $test['expiry_date'] : 'Not tested or unknown '));

		if (file_exists($command) && is_executable($command)) {
			$output = array();
			$return = 0;

			exec($command, $output, $return);

			cacti_log('Servcheck Command for test[' . $test['id'] . '] Command[' . $command . '] ExitStatus[' . $return . '] Output[' . implode(' ', $output) . ']', false, 'SERVCHECK');
		} else {
			cacti_log('WARNING: Servcheck Command for test[' . $test['id'] . '] Command[' . $command . '] Is either Not found or Not executable!', false, 'SERVCHECK');
		}
	}
} else {
	plugin_servcheck_debug('Not checking for trigger', $test);

	if ($results['result'] != 'ok') {
		$test['failures']++;
		$test['triggered'] = 1;
	} else {
		$test['triggered'] = 0;
		$test['failures'] = 0;
	}
}


plugin_servcheck_debug('Updating Statistics', $test);

if ($results['curl']) {
	if (!isset($results['curl_return'])) {
		$results['curl_return'] = 'N/A';
	}

	$curl  = 'HTTP code: ' . $results['options']['http_code'] . ', DNS time: ' . round($results['options']['namelookup_time'], 3) . ', ';
	$curl .= 'Conn. time: ' . round($results['options']['connect_time'],3) . ', Redir. time: ' . round($results['options']['redirect_time'], 3) . ', ';
	$curl .= 'Redir. count: ' . $results['options']['redirect_time'] . ', Download: ' . round($results['options']['size_download'], 3) . ', ';
	$curl .= 'Speed: ' . $results['options']['speed_download'] . ', CURL code: ' . $results['curl_return'];
} else {
	$curl = 'N/A';
}

if (!isset($test['expiry_date'])) {
	$save_exp = '0000-00-00 00:00:00';
} else {
	$save_exp = $test['expiry_date'];
}

db_execute_prepared('INSERT INTO plugin_servcheck_log
	(test_id, duration, lastcheck, cert_expire, result, error, result_search, curl_response, attempt)
	VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
	array($test['id'], $results['duration'], date('Y-m-d H:i:s', $results['time']), $save_exp,
		$results['result'], $results['error'], $results['result_search'], $curl, $results['x'])
);

if ($new_notify_expire) {
	db_execute_prepared('UPDATE plugin_servcheck_test
		SET triggered = ?, failures = ?, lastcheck = ?, last_exp_notify = now(),
		stats_ok = ?, stats_bad = ?, last_returned_data = ?
		WHERE id = ?',
		array($test['triggered'], $test['failures'],
			date('Y-m-d H:i:s', $results['time']),
			$test['stats_ok'], $test['stats_bad'],
			$results['data'], $test['id']
		)
	);
} else {
	db_execute_prepared('UPDATE plugin_servcheck_test
		SET triggered = ?, failures = ?, lastcheck = ?,
		stats_ok = ?, stats_bad = ?, last_returned_data = ?
		WHERE id = ?',
		array($test['triggered'], $test['failures'],
			date('Y-m-d H:i:s', $results['time']),
			$test['stats_ok'], $test['stats_bad'],
			$results['data'], $test['id']
		)
	);
}

/* register process end */
register_shutdown($test_id);

/* purge old entries from the log */

db_execute_prepared('DELETE FROM plugin_servcheck_log
	WHERE lastcheck < FROM_UNIXTIME(?)',
	array(time() - (86400 * 90)));

/* exit */

function register_startup($test_id) {
	db_execute_prepared('INSERT INTO plugin_servcheck_processes
		(test_id, pid, time)
		VALUES(?, ?, NOW())',
		array($test_id, getmypid()));
}

function register_shutdown($test_id) {
	db_execute_prepared('DELETE FROM plugin_servcheck_processes
		WHERE test_id = ?
		AND pid = ?',
		array($test_id, getmypid()), false);
}

function plugin_servcheck_send_notification($results, $test, $type, $last_log) {
	global $httperrors, $cert_expiry_days;

	if (read_config_option('servcheck_disable_notification') == 'on') {
		cacti_log('Notifications are disabled, notification will not send for test ' . $test['name'], false, 'SERVCHECK');
		plugin_servcheck_debug('Notification disabled globally', $test);

		return true;
	}

	$servcheck_send_email_separately = read_config_option('servcheck_send_email_separately');

	$users = '';
	if ($test['notify_accounts'] != '') {
		$users = db_fetch_cell("SELECT GROUP_CONCAT(DISTINCT data) AS emails
			FROM plugin_servcheck_contacts
			WHERE id IN (" . $test['notify_accounts'] . ")");
	}

	if ($users == '' && (isset($test['notify_extra']) && $test['notify_extra'] == '') && (api_plugin_installed('thold') && $test['notify_list'] <= 0)) {
		cacti_log('ERROR: No users to send SERVCHECK Notification for ' . $test['name'], false, 'SERVCHECK');
		return;
	}

	$to = $users;

	if (read_config_option('servcheck_disable_notification') == 'on' && ($to != '' || $test['notify_extra'] != '')) {
		cacti_log(sprintf('WARNING: Service Check %s has individual Emails specified and Disable Legacy Notification is Enabled.', $test['name']), false, 'SERVCHECK');
	}

	if ($test['notify_extra'] != '') {
		$to .= ($to != '' ? ', ':'') . $test['notify_extra'];
	}

	if (api_plugin_installed('thold') && $test['notify_list'] > 0) {
		$emails = db_fetch_cell_prepared('SELECT emails
			FROM plugin_notification_lists
			WHERE id = ?',
			array($test['notify_list']));

		if ($emails != '') {
			$to .= ($to != '' ? ', ':'') . $emails;
		}
	}

	if ($type == 'text') {
		if ($test['status_change']) {
			if ($results['result'] == 'ok') {
				$message[0]['subject'] = '[Cacti servcheck] Service recovered: ' . $test['name'];
			} else {
				$message[0]['subject'] = '[Cacti servcheck] Service down: ' . $test['name'];
			}

			$message[0]['text']  = 'Service state: ' . ($results['result'] == 'ok' ? 'Recovering' : 'Down') . PHP_EOL;

			if (!is_null($test['path']) && $test['path'] != '') {
				$message[0]['text'] .= 'Path: ' . $test['path'] . PHP_EOL;
			}
			$message[0]['text'] .= 'Error: ' . $results['error'] . PHP_EOL;
			$message[0]['text'] .= 'Date: ' . date(date_time_format(), $results['time']) . PHP_EOL;
			$message[0]['text'] .= 'Duration: ' . $results['duration'] . PHP_EOL;

			if ($test['certexpirenotify']) {
				if ($test['days'] < 0) {
					$message[0]['text'] .= 'Certificate expired ' . ($test['days'] * -1) . ' days ago' . PHP_EOL;
				} else {
					$message[0]['text'] .= 'Certificate expires in ' . $test['days'] . ' days (' . (isset($test['expiry_date']) ? $test['expiry_date'] : 'Invalid Expiry Date') . ')' . PHP_EOL;
				}
			}

			if ($test['notes'] != '') {
				$message[0]['text'] .= PHP_EOL . 'Notes: ' . $test['notes'] . PHP_EOL;
			}
		}

		if ($test['duration']) {

			if ($test['duration_state'] == 'ok') {
				$message[0]['subject'] = '[Cacti servcheck] Service long duration restored to normal: ' . $test['name'];
			} else {
				$message[0]['subject'] = '[Cacti servcheck] Service long duration detected: ' . $test['name'];
			}

			$message[0]['text'] .= 'Last three durations: ' . implode(', ', $test['durs']) . PHP_EOL;

			if ($test['notes'] != '') {
				$message[0]['text'] .= PHP_EOL . 'Notes: ' . $test['notes'] . PHP_EOL;
			}
		}

		// search string notification
		if ($last_log['result_search'] != $results['result_search']) {
			$message[1]['subject'] = '[Cacti servcheck] Service: ' . $test['name'] . ' search result is different than last check';

			$message[1]['text'] = 'Hostname: ' . $test['hostname'] . PHP_EOL;

			if (!is_null($test['path']) && $test['path'] != '') {
				$message[1]['text'] .= 'Path: ' . $test['path'] . PHP_EOL;
			}

			$message[1]['text'] .= 'Date: ' . date(date_time_format(), $results['time']) . PHP_EOL;
			$message[1]['text'] .= 'Duration: ' . $results['duration'] . PHP_EOL;

			if ($test['certexpirenotify']) {
				if ($test['days'] < 0) {
					$message[1]['text'] .= 'Certificate expired ' . ($test['days'] * -1) . ' days ago' . PHP_EOL;
				} else {
					$message[1]['text'] .= 'Certificate expires in ' . $test['days'] . ' days (' . (isset($test['expiry_date']) ? $test['expiry_date'] : 'Invalid Expiry Date') . ')' . PHP_EOL;
				}
			}

			if (isset($results['options']['http_code'])) {
				$message[1]['text'] .= 'HTTP Code: ' . $httperrors[$results['options']['http_code']] . PHP_EOL;
			}

			$message[1]['text'] .= 'Previous search: ' . $last_log['result_search'] . PHP_EOL;
			$message[1]['text'] .= 'Actual search: ' . $results['result_search'] . PHP_EOL;

			if ($test['notes'] != '') {
				$message[1]['text'] .= PHP_EOL . 'Notes: ' . $test['notes'] . PHP_EOL;
			}
		}

		if ($test['certexpirenotify'] && $cert_expiry_days > 0 && $test['days'] < $cert_expiry_days) {
			if ($test['days'] < 0) {
				$message[2]['subject'] = '[Cacti servcheck] Certificate will expire in less than ' . $cert_expiry_days . ' days: ' . $test['days'];
			} else {
				$message[2]['subject'] = '[Cacti servcheck] Certificate expired ' . ($test['days'] * -1) . ' ago';
			}
			$message[2]['text'] = 'Test ' . $test['name'] . PHP_EOL;

			$message[2]['text'] .= 'Hostname: ' . $test['hostname'] . PHP_EOL;

			if (!is_null($test['path']) && $test['path'] != '') {
				$message[2]['text'] .= 'Path: ' . $test['path'] . PHP_EOL;
			}
			$message[2]['text'] .= 'Certificate expiry date:' . (isset($test['expiry_date']) ? $test['expiry_date'] : 'Invalid Expiry Date') . PHP_EOL;
			$message[2]['text'] .= 'Date: ' . date(date_time_format(), $results['time']) . PHP_EOL;
			$message[2]['text'] .= 'Duration: ' . $results['duration'] . PHP_EOL;

			if ($test['notes'] != '') {
				$message[2]['text'] .= PHP_EOL . 'Notes: ' . $test['notes'] . PHP_EOL;
			}
		}
	} else {	// html output
		if ($test['status_change']) {

			if ($results['result'] == 'ok') {
				$message['0']['subject'] = '[Cacti servcheck] Service Recovered: ' . $test['name'];
			} else {
				$message['0']['subject'] = '[Cacti servcheck] Service Down: ' . $test['name'];
			}

			$message[0]['text']  = '<h3>' . $message[0]['subject'] . '</h3>' . PHP_EOL;
			$message[0]['text'] .= '<hr>';

			$message[0]['text'] .= '<table>' . PHP_EOL;
			$message[0]['text'] .= '<tr><td>Hostname:</td><td>' . $test['hostname'] . '</td></tr>' . PHP_EOL;

			if (!is_null($test['path']) && $test['path'] != '') {
				$message[0]['text'] .= '<tr><td>Path:</td><td>' . $test['path'] . '</td></tr>' . PHP_EOL;
			}

			$message[0]['text'] .= '<tr><td>Status:</td><td>' . ($results['result'] == 'ok' ? 'Recovering' : 'Down') . '</td></tr>' . PHP_EOL;
			$message[0]['text'] .= '<tr><td>Date:</td><td>' . date(date_time_format(), $results['time']) . '</td></tr>' . PHP_EOL;
			$message[0]['text'] .= '<tr><td>Duration:</td><td>' . $results['duration'] . '</td></tr>' . PHP_EOL;

			if ($test['certexpirenotify']) {
				if ($test['days'] < 0) {
					$message[0]['text'] .= '<tr><td>Certificate expired:</td><td>' . ($test['days'] * -1) . ' days ago</td></tr>' . PHP_EOL;
				} else {
					$message[0]['text'] .= '<tr><td>Certificate expires in:</td><td>' . $test['days'] . ' days (' . (isset($test['expiry_date']) ? $test['expiry_date'] : 'Invalid Expiry Date') . ')</td></tr>' . PHP_EOL;
				}
			}

			if (isset($results['options']['http_code'])) {
				$message[0]['text'] .= '<tr><td>HTTP Code:</td><td>' . $httperrors[$results['options']['http_code']] . '</td></tr>' . PHP_EOL;
			}

			if ($results['error'] != '') {
				$message[0]['text'] .= '<tr><td>Error:</td><td>' . $results['error'] . '</td></tr>' . PHP_EOL;
			}

			if ($test['notes'] != '') {
				$message[0]['text'] .= '<tr><td>Notes:</td><td>' . $test['notes'] . '</td></tr>' . PHP_EOL;
			}

			$message[0]['text'] .= '</table>' . PHP_EOL;
			$message[0]['text'] .= '<hr>';

			if ($results['curl'] && $results['error'] == 'ok') {
				$message[0]['text'] .= '<table>' . PHP_EOL;
				$message[0]['text'] .= '<tr><td>Total Time:</td><td> '     . round($results['options']['total_time'],4)      . '</td></tr>' . PHP_EOL;
				$message[0]['text'] .= '<tr><td>Connect Time:</td><td> '   . round($results['options']['connect_time'],4)    . '</td></tr>' . PHP_EOL;
				$message[0]['text'] .= '<tr><td>DNS Time:</td><td> '       . round($results['options']['namelookup_time'],4) . '</td></tr>' . PHP_EOL;
				$message[0]['text'] .= '<tr><td>Redirect Time:</td><td> '  . round($results['options']['redirect_time'],4)   . '</td></tr>' . PHP_EOL;
				$message[0]['text'] .= '<tr><td>Redirect Count:</td><td> ' . round($results['options']['redirect_count'],4)  . '</td></tr>' . PHP_EOL;
				$message[0]['text'] .= '<tr><td>Download Size:</td><td> '  . round($results['options']['size_download'],4)   . ' Bytes' . '</td></tr>' . PHP_EOL;
				$message[0]['text'] .= '<tr><td>Download Speed:</td><td> ' . round($results['options']['speed_download'],4)  . ' Bps' . '</td></tr>' . PHP_EOL;
				$message[0]['text'] .= '</table>' . PHP_EOL;
				$message[0]['text'] .= '<hr>';
			}
		}

		if ($test['duration']) {

			if ($test['duration_state'] == 'ok') {
				$message[0]['subject'] = '[Cacti servcheck] Service long duration restored to normal: ' . $test['name'];
			} else {
				$message[0]['subject'] = '[Cacti servcheck] Service long duration detected: ' . $test['name'];
			}

			$message[0]['text']  = '<h3>' . $message[0]['subject'] . '</h3>' . PHP_EOL;
			$message[0]['text'] .= '<hr>';

			$message[0]['text'] .= '<table>' . PHP_EOL;
			$message[0]['text'] .= '<tr><td>Hostname:</td><td>' . $test['hostname'] . '</td></tr>' . PHP_EOL;

			$message[0]['text'] .= '<tr><td>Last three duration:</td><td>' . implode(', ', $test['durs']) . '</td></tr>' . PHP_EOL;

			if ($test['notes'] != '') {
				$message[0]['text'] .= '<tr><td>Notes:</td><td>' . $test['notes'] . '</td></tr>' . PHP_EOL;
			}

			$message[0]['text'] .= '</table>' . PHP_EOL;
		}

		// search string notification
		if ($last_log['result_search'] != $results['result_search']) {
			$message[1]['subject'] = '[Cacti servcheck] Service ' . $test['name'] . ' search result is different than last check';

			$message[1]['text']  = '<h3>' . $message[1]['subject'] . '</h3>' . PHP_EOL;
			$message[1]['text'] .= '<hr>';

			$message[1]['text'] .= '<table>' . PHP_EOL;

			$message[1]['text'] .= '<tr><td>Hostname:</td><td>' . $test['hostname'] . '</td></tr>' . PHP_EOL;

			if (!is_null($test['path']) && $test['path'] != '') {
				$message[1]['text'] .= '<tr><td>Path:</td><td>' . $test['path'] . '</td></tr>' . PHP_EOL;
			}
			$message[1]['text'] .= '<tr><td>Date:</td><td>' . date(date_time_format(), $results['time']) . '</td></tr>' . PHP_EOL;
			$message[1]['text'] .= '<tr><td>Duration:</td><td>' . $results['duration'] . '</td></tr>' . PHP_EOL;

			if ($test['certexpirenotify']) {
				if ($test['days'] < 0) {
					$message[1]['text'] .= '<tr><td>Certificate expired:</td><td>' . ($test['days'] * -1) . ' days ago</td></tr>' . PHP_EOL;
				} else {
					$message[1]['text'] .= '<tr><td>Certificate expires in:</td><td>' . $test['days'] . ' days (' . (isset($test['expiry_date']) ? $test['expiry_date'] : 'Invalid Expiry Date') . ')</td></tr>' . PHP_EOL;
				}
			}

			$message[1]['text'] .= '<tr><td>Previous search:</td><td>' . $last_log['result_search'] . '</td></tr>' . PHP_EOL;
			$message[1]['text'] .= '<tr><td>Actual search:</td><td>' . $results['result_search'] . '</td></tr>' . PHP_EOL;

			if ($test['notes'] != '') {
				$message[1]['text'] .= '<tr><td>Notes:</td><td>' . $test['notes'] . '</td></tr>' . PHP_EOL;
			}

			$message[1]['text'] .= '</table>' . PHP_EOL;
		}

		if ($test['certexpirenotify'] && $cert_expiry_days > 0 && $test['days'] < $cert_expiry_days) {
			$message[2]['subject'] = '[Cacti servcheck] Certificate will expire in less than ' . $cert_expiry_days . ' days: ' . $test['name'];
			$message[2]['text']  = '<h3>' . $message[2]['subject'] . '</h3>' . PHP_EOL;
			$message[2]['text'] .= '<hr>';

			$message[2]['text'] .= '<table>' . PHP_EOL;

			$message[2]['text'] .= '<tr><td>Hostname:</td><td>' . $test['hostname'] . '</td></tr>' . PHP_EOL;

			if (!is_null($test['path']) && $test['path'] != '') {
				$message[2]['text'] .= '<tr><td>Path:</td><td>' . $test['path'] . '</td></tr>' . PHP_EOL;
			}
			$message[2]['text'] .= '<tr><td>Date:</td><td>' . date(date_time_format(), $results['time']) . '</td></tr>' . PHP_EOL;
			$message[2]['text'] .= '<tr><td>Duration:</td><td>' . $results['duration'] . '</td></tr>' . PHP_EOL;

			if ($test['certexpirenotify']) {
				if ($test['days'] < 0) {
					$message[2]['text'] .= '<tr><td>Certificate expired:</td><td>' . ($test['days'] * -1) . ' days ago</td></tr>' . PHP_EOL;
				} else {
					$message[2]['text'] .= '<tr><td>Certificate expires in:</td><td>' . $test['days'] . ' days (' . (isset($test['expiry_date']) ? $test['expiry_date'] : 'Invalid Expiry Date') . ')</td></tr>' . PHP_EOL;
				}
			}

			if ($test['notes'] != '') {
				$message[2]['text'] .= '<tr><td>Notes:</td><td>' . $test['notes'] . '</td></tr>' . PHP_EOL;
			}

			$message[2]['text'] .= '</table>' . PHP_EOL;

		}
	}

	if ($servcheck_send_email_separately != 'on') {
		foreach ($message as $m) {
			plugin_servcheck_send_email($to, $m['subject'], $m['text']);
		}
	} else {

		$users = explode(',', $to);

		foreach ($message as $m) {
			foreach ($users as $u) {
				plugin_servcheck_send_email($u, $m['subject'], $m['text']);
			}
		}
	}
}


function plugin_servcheck_send_email($to, $subject, $message) {
	$from_name  = read_config_option('settings_from_name');
	$from_email = read_config_option('settings_from_email');

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

/*  display_version - displays version information */
function display_version() {
	global $config;

	if (!function_exists('plugin_servcheck_version')) {
		include_once($config['base_path'] . '/plugins/servcheck/setup.php');
	}

	$info = plugin_servcheck_version();

    print 'Cacti Web Service Check Processor, Version ' . $info['version'] . ', ' . COPYRIGHT_YEARS . PHP_EOL;
}

/*  display_help - displays the usage of the function */
function display_help() {
	display_version();

	print PHP_EOL;
	print 'usage: servcheck_process.php --id=N [--debug]' . PHP_EOL . PHP_EOL;
	print 'This binary will run the Service check for the Servcheck plugin.' . PHP_EOL . PHP_EOL;
	print '--id=N     - The Test ID from the Servcheck database.' . PHP_EOL;
	print '--force    - Run even if the job is disabled or set to run less frequently than every poller cycle' . PHP_EOL;
	print '--debug    - Display verbose output during execution' . PHP_EOL . PHP_EOL;
}
