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
	global $config, $servcheck_tabs;

	if (get_request_var('action') == 'history') {
		if ($current_tab == 'servcheck_test.php') {
			$current_tab = 'servcheck_test.php?action=history&id=' . get_filter_request_var('id');
			$servcheck_tabs[$current_tab] = __('Log History', 'servcheck');
		}
	}

	if (get_request_var('action') == 'graph') {
		if ($current_tab == 'servcheck_test.php') {
			$current_tab = 'servcheck_web.php?action=graph&id=' . get_filter_request_var('id');
			$servcheck_tabs[$current_tab] = __('Graphs', 'servcheck');
		}
	}

	print "<div class='tabs'><nav><ul>";

	if (cacti_sizeof($servcheck_tabs)) {
		foreach ($servcheck_tabs as $url => $name) {
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

	$xid = 'xx' . substr(md5($id . $graph_interval[$interval]), 0, 7);


	foreach ($result as $row) {
		$lastcheck[]       = $row['lastcheck'];
		$total_time[]      = round($row['total_time'], 5);
		$namelookup_time[] = round($row['namelookup_time'], 5);
		$connect_time[]    = round($row['connect_time'], 5);
	}

	// Start chart attributes
	$chart = array(
		'data' => array(
			'x' => 'x',
			'type' => 'area-spline',
			'axes' => array(), // Setup the Axes (keep it empty to use only the left Y-axis)
			'labels' => true,
			'names' => array(
				'Total' => 'Total',
				'Connect' => 'Connect',
				'DNS' => 'DNS'
			)
		),
		'size' => array(
			'height' => 600,
			'width'=> 1200
		),
		'axis' => array(),
		'point' => array(
			'pattern' => array(
				"<circle cx='5' cy='5' r='5'></circle>",
				"<rect x='0' y='0' width='10' height='10'></rect>",
				"<polygon points='8 0 0 16 16 16'></polygon>"
			)
		),
		'legend' => array(
			'show' => true,
			'usePoint' => true,
			'tooltip' => true,
			'contents' => array(
				'bindto' => "#legend_$xid",
				"<div style='position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);background-color: rgba(0, 0, 0, 0.7); color: #fff; padding: 10px; border-radius: 5px;'>{=TITLE}</div>"
			)
		),
		'zoom' => array('enabled' => true),
		'bindto' => "#chart_$xid"
	);

	// Add the data columns
	$columns = array();
	$columns[] = array_merge(array('x'), $lastcheck);
	$columns[] = array_merge(array('Total'), $total_time);
	$columns[] = array_merge(array('Connect'), $connect_time);
	$columns[] = array_merge(array('DNS'), $namelookup_time);
	$chart['data']['columns'] = $columns;

	// Setup the Axis
	$axis = array();
	$axis['x'] = array(
		'type' => 'timeseries',
		'tick' => array(
			'format'=> '%Y%m%d %H:%M',
			'culling' => array('max' => 6),
			'rotate' => -15
		),
		'label' => array(
			'text' => 'Date/Time',
			'position' => 'outer-right'
		)
	);

	$axis['y'] = array(
		'label' => array(
			'text' => 'Response (ms)',
			'position' => 'outer-middle'
		)
	);
	$chart['axis']= $axis;

	$chart_data = json_encode($chart);

// The below block can be used as part of the debugging to display the data retrieved along with the graph.
// Uncomment/Comment as required.
/*
print '<style>
    .json-output {
        max-width: 800px; 
        overflow-wrap: break-word; 
        word-wrap: break-word;
        white-space: pre-wrap;
        border: 1px solid #ccc;
        padding: 10px;
        background-color: #f9f9f9;
    }
</style>';
print '<pre class="json-output">';
var_dump($chart_data);
print '</pre>';
*/

	$content  = '<div id="chart_' . $xid. '"></div>';
	$content  .= '<div id="legend_container_' . $xid. '" style="display: flex; flex-direction: column; align-items: flex-start;">';
	$content  .= '<div id="legend_' . $xid. '" style="margin-top: 10px;"></div>';
	$content  .= '</div>';
	$content .= '<script type="text/javascript">';
	$content .= 'chart_' . $xid . ' = bb.generate(' . $chart_data . ');';
	$content .= "setTimeout(() => {chart_$xid.flush(); }, 500);"; // Forces Billboard.js to redraw, this is a workaround to resolve the issue of styling of axis 'y' which is displayed trimmed at the left of the page. A permanent solution is required to fix the styling issue of both legend overlapping and axis 'y' shift to the left. 
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

