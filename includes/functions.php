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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

include_once(__DIR__ . '/constants.php');
include_once(__DIR__ . '/arrays.php');

function servcheck_show_tab($current_tab) {
	global $config;

	$tabs = array(
		'servcheck_test.php'      => __('Tests', 'servcheck'),
		'servcheck_ca.php'        => __('CA certificates', 'servcheck'),
		'servcheck_proxies.php'   => __('Proxies', 'servcheck'),
		'servcheck_curl_code.php' => __('Curl return codes', 'servcheck'),
	);

	if (get_request_var('action') == 'history') {
		if ($current_tab == 'servcheck_test.php') {
			$current_tab = 'servcheck_test.php?action=history&id=' . get_filter_request_var('id');
			$tabs[$current_tab] = __('Log History', 'servcheck');
		}
	}

	if (get_request_var('action') == 'graph') {
		if ($current_tab == 'servcheck_test.php') {
			$current_tab = 'servcheck_web.php?action=graph&id=' . get_filter_request_var('id');
			$tabs[$current_tab] = __('Graphs', 'servcheck');
		}
	}

	print "<div class='tabs'><nav><ul>";

	if (cacti_sizeof($tabs)) {
		foreach ($tabs as $url => $name) {
			print "<li><a class='" . (($url == $current_tab) ? 'pic selected' : 'pic') .  "' href='" . $config['url_path'] .
				"plugins/servcheck/$url'>$name</a></li>";
		}
	}

	print '</ul></nav></div>';
}


function plugin_servcheck_remove_old_users () {
	$users = db_fetch_assoc('SELECT id FROM user_auth');

	$u = array();

	foreach ($users as $user) {
		$u[] = $user['id'];
	}

	$contacts = db_fetch_assoc('SELECT DISTINCT user_id FROM plugin_servcheck_contacts');

	foreach ($contacts as $c) {
		if (!in_array($c['user_id'], $u)) {
			db_execute_prepared('DELETE FROM plugin_servcheck_contacts WHERE user_id = ?', array($c['user_id']));
		}
	}
}


function plugin_servcheck_update_contacts() {
	$users = db_fetch_assoc("SELECT id, 'email' AS type, email_address FROM user_auth WHERE email_address!=''");

	if (cacti_sizeof($users)) {
		foreach($users as $u) {
			$cid = db_fetch_cell('SELECT id FROM plugin_servcheck_contacts WHERE type="email" AND user_id=' . $u['id']);

			if ($cid) {
				db_execute("REPLACE INTO plugin_servcheck_contacts (id, user_id, type, data) VALUES ($cid, " . $u['id'] . ", 'email', '" . $u['email_address'] . "')");
			} else {
				db_execute("REPLACE INTO plugin_servcheck_contacts (user_id, type, data) VALUES (" . $u['id'] . ", 'email', '" . $u['email_address'] . "')");
			}
		}
	}
}

function plugin_servcheck_check_debug() {
	global $debug;

	if (!$debug) {
			$plugin_debug = read_config_option('selective_plugin_debug');
		if (preg_match('/(^|[, ]+)(servcheck)($|[, ]+)/', $plugin_debug, $matches)) {
			$debug = (cacti_sizeof($matches) == 4 && $matches[2] == 'servcheck');
		}
	}
}

function plugin_servcheck_debug($message='') {
	global $debug;

	if ($debug) {
		cacti_log('DEBUG: ' . trim($message), true, 'SERVCHECK');
	}
}

function plugin_servcheck_graph ($id, $interval) {
	global $config, $graph_interval;

	$result = db_fetch_assoc_prepared("SELECT
		lastcheck, total_time, namelookup_time, connect_time
		FROM plugin_servcheck_log
		WHERE test_id = ? AND
		lastcheck > DATE_SUB(NOW(), INTERVAL ? HOUR)
		ORDER BY id", array($id, $interval));

	if (cacti_sizeof($result) < 5) {
		print __('No data', 'servcheck');
		return;
	}

	$xid = 'xx' . substr(md5($graph_interval[$interval]), 0, 7);


	foreach ($result as $row) {
		$lastcheck[]       = $row['lastcheck'];
		$total_time[]      = round($row['total_time'], 5);
		$namelookup_time[] = round($row['namelookup_time'], 5);
		$connect_time[]    = round($row['connect_time'], 5);
	}

	// Start chart attributes
	$chart = array(
		'bindto' => "#line_$xid",
		'size' => array(
			'height' => 300,
			'width'=> 600
		),
		'point' => array (
			'r' => 1.5
		),
		'data' => array(
			'type' => 'area',
			'x' => 'x',
			'xFormat' => '%Y-%m-%d %H:%M:%S' // rikam mu, jaky je format te timeserie
		)
	);

	$columns = array();
	$axis = array();
	$axes = array();

	// Add the X Axis first
	$columns[] = array_merge(array('x'), $lastcheck);
	$columns[] = array_merge(array('Total'), $total_time);
	$columns[] = array_merge(array('Connect'), $connect_time);
	$columns[] = array_merge(array('DNS '), $namelookup_time);

	// Setup the Axis
	$axis['x'] = array(
		'type' => 'timeseries',
		'tick' => array(
			'format'=> '%m-%d %H:%M',
			'culling' => array('max' => 6),
		)
	);

	$axis['y'] = array(
		'tick' => array(
			'label' => array(
				'text' => 'Response in ms',
			),
			'show' => true
		)
	);

	$chart['data']['axes']= $axes;
	$chart['axis']= $axis;
	$chart['data']['columns'] = $columns;

	$chart_data = json_encode($chart);

	$content  = '<div id="line_' . $xid. '"></div>';
	$content .= '<script type="text/javascript">';
	$content .= 'line_' . $xid . ' = bb.generate(' . $chart_data . ');';
	$content .= '</script>';

	print $content;
}

// I know, it not secure, it is better than plaintext

function servcheck_hide_text ($string) {
	$output = '';

	for ($f = 0; $f < strlen($string); $f++) {
		$output .= dechex(ord($string[$f]));
	}

	return $output;
}

function servcheck_show_text ($string) {
	$output = '';

	for ($f = 0; $f < strlen($string); $f = $f + 2) {
		$output .= chr(hexdec($string[$f] . $string[($f+1)]));
	}

	return $output;
}

