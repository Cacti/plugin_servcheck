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
 * Runs a plain DNS lookup service check test: resolves the test's
 * configured query against its target DNS server (via the dnslookup
 * class) and evaluates the returned records against the expected/
 * maintenance/failure search patterns. Called from servcheck_run_test()
 * for tests of type 'dns'.
 *
 * @param array $test The plugin_servcheck_test row describing the check
 *                    to run.
 *
 * @return array The check result: 'result' ('ok'/'error'), 'curl'
 *               (false), 'time', 'error', 'result_search', 'data', and
 *               'start'.
 */
function dns_try(array $test): array {
	include_once(__DIR__ . '/../includes/dnslookup.php');

	// default result
	$results['result']        = 'error';
	$results['curl']          = false;
	$results['time']          = time();
	$results['error']         = '';
	$results['result_search'] = 'not tested';
	$results['data']          = '';
	$results['start']         = microtime(true);

	[$category,$service] = explode('_', $test['type']);

	if (empty($test['hostname'])) {
		cacti_log('Empty hostname, nothing to test');
		$results['result'] = 'error';
		$results['error']  = 'Empty hostname';

		return $results;
	}

	servcheck_debug('Querying ' . $test['hostname'] . ' for record ' . $test['dns_query']);

	$a = new dnslookup($test['dns_query'], $test['hostname'],
	$test['duration_trigger'] > 0 ? ($test['duration_trigger'] + 2) : read_config_option('servcheck_test_max_duration'));

	if (!$a->is_success()) {
		$results['result']        = 'error';
		$results['error']         = $a->get_error();
		$results['result_search'] = 'not tested';

		servcheck_debug('Test failed: ' . $results['error']);
	} else {
		$dns_results = $a->get_results();

		if ($dns_results !== false) {
			$results['data']  .= $dns_results;
			$results['result'] = 'ok';
			$results['error']  = 'Some data returned';
		} else {
			$results['result'] = 'ok';
			$results['error']  = 'DNS server responded, but no A/AAAA record was returned';
		}

		servcheck_debug('Result is ' . $results['data']);

		// If we have set a failed search string, then ignore the normal searches and only alert on it
		if ($test['search_failed'] != '') {
			servcheck_debug('Processing search_failed');

			if (strpos($results['data'], $test['search_failed']) !== false) {
				servcheck_debug('Search failed string success');
				$results['result']        = 'ok';
				$results['result_search'] = 'failed ok';

				return $results;
			}
		}

		servcheck_debug('Processing search');

		if ($test['search'] != '') {
			if (strpos($results['data'], $test['search']) !== false) {
				servcheck_debug('Search string success');
				$results['result_search'] = 'ok';

				return $results;
			} else {
				$results['result_search'] = 'not ok';

				return $results;
			}
		}

		if ($test['search_maint'] != '') {
			servcheck_debug('Processing search maint');

			if (strpos($results['data'], $test['search_maint']) !== false) {
				servcheck_debug('Search maint string success');
				$results['result_search'] = 'maint ok';

				return $results;
			}
		}
	}

	return $results;
}

/**
 * Runs a DNS-over-HTTPS (DoH) service check test via cURL, applying the
 * test's configured proxy, CA certificate, and timeout, and evaluating
 * the response against the expected/maintenance/failure search
 * patterns. Called from servcheck_run_test() for tests of type 'doh'.
 *
 * @param array $test The plugin_servcheck_test row describing the check
 *                    to run.
 *
 * @return array The check result: 'result' ('ok'/'error'), 'curl'
 *               (true), 'error', 'result_search', 'start', and (once
 *               the request completes) cURL timing/status 'options' and
 *               response 'data'.
 *
 * @global string $user_agent          The User-Agent string sent with
 *                                     the request.
 * @global array  $config              Cacti global configuration array;
 *                                     used to build a per-test CA file
 *                                     path.
 * @global string $ca_info              Path to the bundled CA
 *                                     certificate file used for TLS
 *                                     verification.
 * @global array  $service_types_ports Default port numbers per service
 *                                     type (declared but not directly
 *                                     used here).
 */
function doh_try(array $test): array {
	global $user_agent, $config, $ca_info, $service_types_ports;

	$cert_info = [];

	// default result
	$results['result']        = 'error';
	$results['curl']          = true;
	$results['error']         = '';
	$results['result_search'] = 'not tested';
	$results['start']         = microtime(true);

	$options = [
		CURLOPT_HEADER         => true,
		CURLOPT_USERAGENT      => $user_agent,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_MAXREDIRS      => 4,
		CURLOPT_TIMEOUT        => $test['duration_trigger'] > 0 ? ($test['duration_trigger'] + 2) : read_config_option('servcheck_test_max_duration'),
		CURLOPT_CAINFO         => $ca_info,
	];

	[$category,$service] = explode('_', $test['type']);

	if (empty($test['hostname']) || empty($test['dns_query'])) {
		cacti_log('Empty hostname or dns_query, nothing to test');
		$results['result'] = 'error';
		$results['error']  = 'Empty hostname/dns';

		return $results;
	}

	$parsed = parse_url('//' . $test['hostname']);

	if (!isset($parsed['port'])) {
		$test['hostname'] .= ':' . $service_types_ports[$test['type']];
	}

	$url = 'https://' . $test['hostname'] . '/' . $test['dns_query'];

	servcheck_debug('Final url is ' . $url);

	$process = curl_init($url);

	if ($test['ca_id'] > 0) {

		servcheck_debug('Preparing own CA chain file ' . $ca_info);

		$cert = db_fetch_cell_prepared('SELECT cert FROM plugin_servcheck_ca WHERE id = ?',
			[$test['ca_id']]);

		$ca_file = tempnam(sys_get_temp_dir(), 'srvck');
		$cert_file = fopen($ca_file, 'a');

		if ($cert_file) {
			fwrite($cert_file, $cert);
			fclose($cert_file);

			// CURLOPT_CAINFO is to updated based on the custom CA certificate
			$options[CURLOPT_CAINFO] = $ca_file;

		} else {
			cacti_log('Cannot create ca cert file ' . $ca_file);
			$results['result'] = 'error';
			$results['error']  = 'Cannot create ca cert file';

			return $results;
		}
	}

	// Disable Cert checking for now
	if ($test['checkcert'] == '') {
		$options[CURLOPT_SSL_VERIFYPEER] = false;
		$options[CURLOPT_SSL_VERIFYHOST] = 0;
	} else { // for sure, it seems that it isn't enabled by default now
		$options[CURLOPT_SSL_VERIFYPEER] = true;
		$options[CURLOPT_SSL_VERIFYHOST] = 2;
	}

	if ($test['certexpirenotify'] != '') {
		$options[CURLOPT_CERTINFO] = true;
	}

	servcheck_debug('cURL options: ' . clean_up_lines(var_export($options, true)));

	curl_setopt_array($process,$options);

	servcheck_debug('Executing curl request');

	$data            = curl_exec($process);
	$data            = str_replace(["'", '\\'], [''], (string) $data);
	$results['data'] = $data;

	// Get information regarding a specific transfer, cert info too
	$results['options'] = curl_getinfo($process);

	$results['curl_return'] = curl_errno($process);

	servcheck_debug('cURL error: ' . $results['curl_return']);

	servcheck_debug('Data: ' . clean_up_lines(var_export($data, true)));

	if ($test['ca_id'] > 0 && file_exists($ca_file)) {
		servcheck_debug('Removing own CA file');
		unlink($ca_file);
	}

	if ($results['curl_return'] > 0) {
		$results['error']  =  str_replace(['"', "'"], '', (curl_error($process)));
		$results['result'] = 'error';

		return $results;
	}

	curl_close($process);

	if ($test['type'] == 'web_http' || $test['type'] == 'web_https') {
		// not found?
		if ($results['options']['http_code'] == 404) {
			$results['result'] = 'error';
			$results['error']  = '404 - Not found';

			return $results;
		}
	}

	if (empty($results['data'])) {
		$results['result'] = 'error';
		$results['error']  = 'No data returned';

		return $results;
	}

	$results['result'] = 'ok';
	$results['error']  = 'Some data returned';

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
