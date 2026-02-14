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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

function servcheck_show_tab($current_tab) {
	global $config, $servcheck_tabs;

	if (get_request_var('action') == 'history') {
		if ($current_tab == 'servcheck_test.php') {
			$current_tab                  = 'servcheck_test.php?action=history&id=' . get_filter_request_var('id');
			$servcheck_tabs[$current_tab] = __('Log History', 'servcheck');
		}
	}

	if (get_request_var('action') == 'graph') {
		if ($current_tab == 'servcheck_test.php') {
			$current_tab                  = 'servcheck_web.php?action=graph&id=' . get_filter_request_var('id');
			$servcheck_tabs[$current_tab] = __('Graphs', 'servcheck');
		}
	}

	print "<div class='tabs'><nav><ul>";

	if (cacti_sizeof($servcheck_tabs)) {
		foreach ($servcheck_tabs as $url => $name) {
			print "<li><a class='" . (($url == $current_tab) ? 'pic selected' : 'pic') . "' href='" . $config['url_path'] .
				"plugins/servcheck/$url'>$name</a></li>";
		}
	}

	print '</ul></nav></div>';
}

function servcheck_check_debug() {
	global $debug;

	if (!$debug) {
		$plugin_debug = read_config_option('selective_plugin_debug');

		if (preg_match('/(^|[, ]+)(servcheck)($|[, ]+)/', $plugin_debug, $matches)) {
			$debug = (cacti_sizeof($matches) == 4 && $matches[2] == 'servcheck');
		}
	}
}

function servcheck_debug($message = '') {
	global $debug;

	if ($debug) {
		cacti_log('DEBUG: ' . trim($message), true, 'SERVCHECK');
	}
}

function servcheck_graph($id, $interval) {
	global $config, $graph_interval;

	$result = db_fetch_assoc_prepared('SELECT
		last_check, duration FROM plugin_servcheck_log
		WHERE test_id = ? AND
		last_check > DATE_SUB(NOW(), INTERVAL ? HOUR)
		ORDER BY id', [$id, $interval]);

	if (cacti_sizeof($result) < 5) {
		print __('No data', 'servcheck');

		return;
	}

	$xid = 'xx' . substr(md5($graph_interval[$interval]), 0, 7);

	foreach ($result as $row) {
		$last_check[]      = $row['last_check'];
		$duration[]        = round($row['duration'], 5);
	}

	// Start chart attributes
	$chart = [
		'bindto' => "#line_$xid",
		'size'   => [
			'height' => 300,
			'width'  => 600
		],
		'point' => [
			'r' => 1.5
		],
		'data' => [
			'type'    => 'area',
			'x'       => 'x',
			'xFormat' => '%Y-%m-%d %H:%M:%S' // rikam mu, jaky je format te timeserie
		]
	];

	$columns = [];
	$axis    = [];
	$axes    = [];

	// Add the X Axis first
	$columns[] = array_merge(['x'], $last_check);
	$columns[] = array_merge(['Duration'], $duration);

	// Setup the Axis
	$axis['x'] = [
		'type' => 'timeseries',
		'tick' => [
			'format'  => '%m-%d %H:%M',
			'culling' => ['max' => 6],
		]
	];

	$axis['y'] = [
		'tick' => [
			'label' => [
				'text' => 'Response in ms',
			],
			'show' => true
		]
	];

	$chart['data']['axes']    = $axes;
	$chart['axis']            = $axis;
	$chart['data']['columns'] = $columns;

	$chart_data = json_encode($chart);

	$content  = '<div id="line_' . $xid . '"></div>';
	$content .= '<script type="text/javascript">';
	$content .= 'line_' . $xid . ' = bb.generate(' . $chart_data . ');';
	$content .= '</script>';

	print $content;
}

// It is not secure, it is better than plaintext
// will be removed in 0.5

function servcheck_hide_text($string) {
	$output = '';

	for ($f = 0; $f < strlen($string); $f++) {
		$output .= dechex(ord($string[$f]));
	}

	return $output;
}

function servcheck_show_text($string) {
	$output = '';

	for ($f = 0; $f < strlen($string); $f = $f + 2) {
		$output .= chr(hexdec($string[$f] . $string[($f + 1)]));
	}

	return $output;
}

function servcheck_encrypt_credential($cred) {
	$servcheck_key = read_user_setting('servcheck_key', null, true, 1);
	$iv_length     = intval(openssl_cipher_iv_length(SERVCHECK_CIPHER));
	$servcheck_iv  = openssl_random_pseudo_bytes($iv_length);

	if (is_null($servcheck_key)) {
		cacti_log('Creating new cipher key', 'servcheck');
		$servcheck_key = hash('sha256', 'ksIBWE' . date('hisv'));

		set_user_setting('servcheck_key', base64_encode($servcheck_key));
	} else {
		$servcheck_key = base64_decode($servcheck_key, true);
	}

	$encrypted = openssl_encrypt(json_encode($cred), SERVCHECK_CIPHER, $servcheck_key, OPENSSL_RAW_DATA, $servcheck_iv);

	return base64_encode($servcheck_iv . $encrypted);
}

function servcheck_decrypt_credential($cred_id) {
	$servcheck_key = read_user_setting('servcheck_key', null, true, 1);

	if (is_null($servcheck_key)) {
		cacti_log('Cannot decrypt credential, key is missing', 'servcheck');

		return false;
	} else {
		$servcheck_key = base64_decode($servcheck_key, true);
	}

	$encrypted = db_fetch_cell_prepared('SELECT data FROM plugin_servcheck_credential
		WHERE id = ?',
		[$cred_id]);

	$encrypted = base64_decode($encrypted, true);

	$iv_length     = intval(openssl_cipher_iv_length(SERVCHECK_CIPHER));
	$servcheck_iv  = substr($encrypted, 0, $iv_length);
	$encrypted     = substr($encrypted, $iv_length);

	$decrypted = openssl_decrypt($encrypted, SERVCHECK_CIPHER, $servcheck_key, OPENSSL_RAW_DATA, $servcheck_iv);

	return json_decode($decrypted, true);
}

function servcheck_legend() {
	global $servcheck_states;

	html_start_box('', '100%', false, '3', 'center', '');

	print '<tr class="tableRow">';

	foreach ($servcheck_states as $index => $state) {
		print '<td class="servcheck_' . $index . '">' . $state . '</td>';
	}
	print '</tr>';

	html_end_box(false);
}

// check if summer/winter time changed in last few hours. We need to know it because of expired/renew certificates

function servcheck_summer_time_changed() {
	$hours = 8;

	if (date_default_timezone_get() === '') {
		return false;
	}

	$now  = new DateTime('now');
	$past = (clone $now)->modify("-{$hours} hours");

	$offsetNow  = $now->getOffset();
	$offsetPast = $past->getOffset();

	$diff = $offsetNow - $offsetPast;

	if ($diff !== 0) {
		return true;
	} else {
		return false;
	}
}
