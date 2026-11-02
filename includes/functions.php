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

/**
 * Renders this plugin's shared tab bar (service checks, REST API,
 * credentials, proxies, CAs, etc.), adding a 'Log History' or 'Graphs'
 * tab when those views are currently displayed, and highlighting the
 * currently active tab. Called from each of this plugin's admin pages
 * before rendering their content.
 *
 * @param string $current_tab The currently active page's filename (e.g.
 *                            'servcheck_test.php'), used to determine
 *                            which tab to highlight.
 *
 * @return void Outputs the tab bar HTML directly.
 *
 * @global array $config          Cacti global configuration array; used
 *                                to build the tab link URLs.
 * @global array $servcheck_tabs  The set of tabs to render, keyed by
 *                                page URL; a 'Log History'/'Graphs'
 *                                entry may be added.
 */
function servcheck_show_tab(string $current_tab): void {
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

/**
 * Enables debug output for this run when servcheck is selectively
 * enabled via Cacti's 'selective_plugin_debug' setting, without
 * overriding an already-enabled global debug flag. Called from
 * poller_servcheck.php's and servcheck_process.php's main flow at the
 * start of each run.
 *
 * @return void
 *
 * @global bool $debug Set to true when servcheck-specific debug output
 *                     is enabled.
 */
function servcheck_check_debug(): void {
	global $debug;

	if (!$debug) {
		$plugin_debug = read_config_option('selective_plugin_debug');

		if (preg_match('/(^|[, ]+)(servcheck)($|[, ]+)/', $plugin_debug, $matches)) {
			$debug = (cacti_sizeof($matches) == 4 && $matches[2] == 'servcheck');
		}
	}
}

/**
 * Log debug message
 *
 * Logs a message to the Cacti log when debug output is enabled. Called
 * throughout this plugin's poller/processor scripts to report progress
 * during a check run.
 *
 * @param string $message Text of the message that will be logged
 *
 * @return void
 *
 * @global bool $debug Whether debug output is enabled; when false, this
 *                     function is a no-op.
 */
function servcheck_debug(string $message = ''): void {
	global $debug;

	if ($debug) {
		cacti_log('DEBUG: ' . trim($message), true, 'SERVCHECK');
	}
}

/**
 * Renders a billboard.js area chart of a service check test's response
 * duration over a given trailing time window, or a 'No data' message
 * when fewer than 5 log entries exist in that window. Called from
 * servcheck_show_graph() once per configured graph interval.
 *
 * @param int $id       The plugin_servcheck_test.id to chart.
 * @param int $interval The trailing time window to chart, in hours.
 *
 * @return void Outputs the chart's HTML/JavaScript (or a 'No data'
 *              message) directly.
 *
 * @global array $config         Cacti global configuration array
 *                               (declared but not directly used here).
 * @global array $graph_interval The configured graph time intervals,
 *                               used to derive a unique chart element
 *                               id.
 */
function servcheck_graph(int $id, int $interval): void {
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

	$last_check = [];
	$duration   = [];

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
	$content .= '<script type="text/javascript" ' . plugin_servcheck_csp_nonce() . '>';
	$content .= 'line_' . $xid . ' = bb.generate(' . $chart_data . ');';
	$content .= '</script>';

	print $content;
}

// It is not secure, it is better than plaintext
// will be removed in 0.5

/**
 * Obscures a string by hex-encoding each byte. It is not secure, it is
 * better than plaintext, and will be removed in 0.5. Called from
 * servcheck_restapi.php's form_save() to obscure legacy REST API
 * credential fields before storage.
 *
 * @param string $string The plain-text string to obscure.
 *
 * @return string The hex-encoded string.
 */
function servcheck_hide_text(string $string): string {
	$output = '';

	for ($f = 0; $f < strlen($string); $f++) {
		$output .= dechex(ord($string[$f]));
	}

	return $output;
}

/**
 * Reverses servcheck_hide_text()'s hex-encoding back to the original
 * string. Called from servcheck_restapi.php's servcheck_edit_rest() to
 * display a legacy REST API method's obscured credential fields.
 *
 * @param string $string The hex-encoded string to decode.
 *
 * @return string The original plain-text string.
 */
function servcheck_show_text(string $string): string {
	$output = '';

	for ($f = 0; $f < strlen($string); $f = $f + 2) {
		$output .= chr((int) hexdec($string[$f] . $string[($f + 1)]));
	}

	return $output;
}

/**
 * Encrypts a credential's fields for storage: JSON-encodes them and
 * encrypts with a per-installation symmetric key (generating and
 * persisting a new key on first use), prefixing the result with a fresh
 * random IV. Called from servcheck_credential.php's form_save() when
 * saving a credential.
 *
 * @param array $cred The credential fields to encrypt.
 *
 * @return string The base64-encoded IV + ciphertext.
 */
function servcheck_encrypt_credential(array $cred): string {
	$servcheck_key = read_user_setting('servcheck_key', null, true, 1);
	$iv_length     = intval(openssl_cipher_iv_length(SERVCHECK_CIPHER));
	$servcheck_iv  = openssl_random_pseudo_bytes($iv_length);

	if (is_null($servcheck_key)) {
		cacti_log('Creating new cipher key', false, 'SERVCHECK');
		$servcheck_key = hash('sha256', 'ksIBWE' . date('hisv'));

		set_user_setting('servcheck_key', base64_encode($servcheck_key));
	} else {
		$servcheck_key = (string) base64_decode($servcheck_key, true);
	}

	$encrypted = openssl_encrypt((string) json_encode($cred), SERVCHECK_CIPHER, $servcheck_key, OPENSSL_RAW_DATA, $servcheck_iv);

	return base64_encode($servcheck_iv . $encrypted);
}

/**
 * Decrypts a stored credential's fields, reversing
 * servcheck_encrypt_credential(). Called from
 * servcheck_credential.php's servcheck_data_edit() and from the various
 * test functions when a test references a credential.
 *
 * @param int $cred_id The plugin_servcheck_credential.id to decrypt.
 *
 * @return array|false|null The decrypted credential fields, false if
 *                          the encryption key is missing, or null if
 *                          the stored data is missing/malformed and
 *                          cannot be json_decode()'d.
 */
function servcheck_decrypt_credential(int $cred_id) {
	$servcheck_key = read_user_setting('servcheck_key', null, true, 1);

	if (is_null($servcheck_key)) {
		cacti_log('Cannot decrypt credential, key is missing', false, 'SERVCHECK');

		return false;
	} else {
		$servcheck_key = (string) base64_decode($servcheck_key, true);
	}

	$encrypted = db_fetch_cell_prepared('SELECT data FROM plugin_servcheck_credential
		WHERE id = ?',
		[$cred_id]);

	$encrypted = (string) base64_decode($encrypted, true);

	$iv_length     = intval(openssl_cipher_iv_length(SERVCHECK_CIPHER));
	$servcheck_iv  = substr($encrypted, 0, $iv_length);
	$encrypted     = substr($encrypted, $iv_length);

	$decrypted = openssl_decrypt($encrypted, SERVCHECK_CIPHER, $servcheck_key, OPENSSL_RAW_DATA, $servcheck_iv);

	return json_decode((string) $decrypted, true);
}

/**
 * Renders a color-coded legend row showing each possible test state
 * label. Called from the Tests list view to explain its status
 * color-coding.
 *
 * @return void Outputs the legend HTML directly.
 *
 * @global array $servcheck_states Map of test state codes to their
 *                                display labels/styling.
 */
function servcheck_legend(): void {
	global $servcheck_states;

	html_start_box('', '100%', false, 3, 'center', '');

	$chip_min = 0;

	foreach ($servcheck_states as $state) {
		$chip_min = max($chip_min, mb_strlen($state));
	}

	print '<tr class="tableRow"><td>';
	print '<div class="servcheck_legend" style="--servcheck-chip-min: calc(' . $chip_min . 'ch + 1.5rem)">';

	foreach ($servcheck_states as $index => $state) {
		print '<div class="servcheck_legend_item servcheck_' . $index . '">' . html_escape($state) . '</div>';
	}

	print '</div>';
	print '</td></tr>';

	html_end_box(false);
}

// check if summer/winter time changed in last few hours. We need to know it because of expired/renew certificates

/**
 * Detects whether the system's UTC offset (e.g. due to a DST
 * transition) changed within the last several hours. We need to know it
 * because of expired/renew certificates - a DST shift can otherwise be
 * mistaken for a certificate expiry-related time anomaly. Called from
 * the certificate-expiry notification logic to avoid false positives
 * around DST transitions.
 *
 * @return bool True if the UTC offset changed within the lookback
 *              window, false otherwise (including when no timezone is
 *              configured).
 */
function servcheck_summer_time_changed(): bool {
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


// mask password, for debug messages
function mask_string($string, $all = false) {
	if ($all == true) {
    		return str_pad('X', 10, 'X', STR_PAD_LEFT);
	} else {
    		return str_pad(substr($string, -2), 10, 'X', STR_PAD_LEFT);
	}
}

/**
 * Runs one network operation, capturing only warnings from the listed functions.
 * Other warnings are passed to the previous handler. Always restores it.
 *
 * @param callable $operation The operation to run.
 * @param array $functions PHP function names whose warnings are expected.
 * @param string|null $warning Receives the first warning, or an empty string.
 * @return mixed The operation's return value. Exceptions propagate to the caller.
 */
function servcheck_network_call(callable $operation, array $functions, &$warning) {
	$warning = '';
	$previous = null;
	$previous = set_error_handler(
		static function ($severity, $message, $file, $line) use ($functions, &$warning, &$previous) {
			foreach ($functions as $function) {
				if ($severity === E_WARNING && strpos($message, $function . '():') === 0) {
					if ($warning === '') {
						$warning = $message;
					}

					return true;
				}
			}

			return is_callable($previous) ? $previous($severity, $message, $file, $line) : false;
		},
		E_ALL
	);

	try {
		return $operation();
	} finally {
		restore_error_handler();
	}
}

/**
 * Loads and decrypts a test's stored credential, reporting lookup or
 * decryption failures. Centralises the credential handling shared by the
 * service-check runners so each one validates the credential the same way.
 *
 * @param array $test        The plugin_servcheck_test row; 'cred_id' selects
 *                           the credential to load.
 * @param mixed $cred        Receives the raw plugin_servcheck_credential row
 *                           (by reference), or null.
 * @param mixed $credential  Receives the decrypted credential (by reference),
 *                           or null.
 * @param string $error      Receives a human-readable error on failure (by
 *                           reference), or an empty string.
 *
 * @return bool True when no credential is configured or it was loaded
 *              successfully, false when it is configured but missing or empty.
 */
function servcheck_load_credential(array $test, &$cred, &$credential, &$error): bool {
	$cred       = null;
	$credential = null;
	$error      = '';

	if ($test['cred_id'] <= 0) {
		return true;
	}

	$cred = db_fetch_row_prepared('SELECT * FROM plugin_servcheck_credential WHERE id = ?',
		[$test['cred_id']]);

	if (!$cred) {
		servcheck_debug('Credential is set but not found!');
		cacti_log('Credential not found');
		$error = 'Credential not found';

		return false;
	}

	servcheck_debug('Decrypting credential');
	$credential = servcheck_decrypt_credential($test['cred_id']);

	if (empty($credential)) {
		servcheck_debug('Credential is empty!');
		cacti_log('Credential is empty');
		$error = 'Credential is empty';

		return false;
	}

	return true;
}

/**
 * Evaluates a test's failure, success and maintenance search strings against
 * the response text and records the resulting 'result_search' state.
 * Centralises the pattern evaluation shared by every service-check runner.
 *
 * If a failed-search string is configured it takes precedence; otherwise the
 * expected search string, and finally the maintenance search string, are
 * checked. When none are configured the result array is returned unchanged.
 *
 * @param array  $test    The plugin_servcheck_test row holding the search
 *                        strings ('search_failed', 'search', 'search_maint').
 * @param string $data    The response text to search.
 * @param array  $results The in-progress result array.
 *
 * @return array The result array with 'result_search' updated.
 */
function servcheck_evaluate_search(array $test, string $data, array $results): array {
	// If we have set a failed search string, then ignore the normal searches and only alert on it
	if ($test['search_failed'] != '') {
		servcheck_debug('Processing search_failed');

		if (strpos($data, $test['search_failed']) !== false) {
			servcheck_debug('Search failed string success');
			$results['result_search'] = 'failed ok';

			return $results;
		}
	}

	servcheck_debug('Processing search');

	if ($test['search'] != '') {
		if (strpos($data, $test['search']) !== false) {
			servcheck_debug('Search string success');
			$results['result_search'] = 'ok';

			return $results;
		} else {
			servcheck_debug('String not found');
			$results['result_search'] = 'not ok';

			return $results;
		}
	}

	if ($test['search_maint'] != '') {
		servcheck_debug('Processing search maint');

		if (strpos($data, $test['search_maint']) !== false) {
			servcheck_debug('Search maint string success');
			$results['result_search'] = 'maint ok';

			return $results;
		}
	}

	return $results;
}

/**
 * Validates that a test has a hostname to check, recording an error on the
 * result when it does not. Centralises the empty-hostname guard shared by the
 * service-check runners.
 *
 * @param array $test    The plugin_servcheck_test row.
 * @param array $results The result array, updated on failure (by reference).
 *
 * @return bool True when a hostname is present, false otherwise.
 */
function servcheck_require_hostname(array $test, array &$results): bool {
	if (empty($test['hostname'])) {
		cacti_log('Empty hostname, nothing to test');
		$results['result'] = 'error';
		$results['error']  = 'Empty hostname';

		return false;
	}

	return true;
}

/**
 * Builds the cURL options shared by the HTTP-based service-check runners
 * (web, FTP/SCP and DNS-over-HTTPS). Centralises the common transfer, redirect,
 * timeout and CA settings so every cURL check starts from the same baseline.
 *
 * @param array  $test       The plugin_servcheck_test row; 'duration_trigger'
 *                           sets the per-test timeout.
 * @param string $user_agent The User-Agent string to send.
 * @param string $ca_info    Path to the default CA bundle.
 *
 * @return array<int, mixed> The cURL option map keyed by CURLOPT_* constant.
 */
function servcheck_curl_base_options(array $test, string $user_agent, string $ca_info): array {
	return [
		CURLOPT_HEADER         => true,
		CURLOPT_USERAGENT      => $user_agent,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_MAXREDIRS      => 4,
		CURLOPT_TIMEOUT        => $test['duration_trigger'] > 0 ? ($test['duration_trigger'] + 2) : read_config_option('servcheck_test_max_duration'),
		CURLOPT_CAINFO         => $ca_info,
	];
}