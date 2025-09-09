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


function dns_try ($test) {
	include_once(__DIR__ . '/../includes/mxlookup.php');

	// default result
	$results['result'] = 'failed';
	$results['curl'] = false;
	$results['time'] = time();
	$results['error'] = '';
	$results['result_search'] = 'not tested';
	$results['data'] = '';
	$results['start'] = microtime(true);

	list($category,$service) = explode('_', $test['type']);

	plugin_servcheck_debug('Querying ' . $test['hostname'] . ' for record ' . $test['dns_query']);

	$a = new mxlookup($test['dns_query'], $test['hostname'], $test['duration_trigger'] > 0 ? ($test['duration_trigger'] + 3) : 5);

	if (!cacti_sizeof($a->arrMX)) {
		$results['result'] = 'error';
		$results['error'] = 'Server did not respond';
		$results['result_search'] = 'not tested';

		plugin_servcheck_debug('Test failed: ' . $results['error']);
	} else {
		foreach ($a->arrMX as $m) {
			$results['data'] .= "$m\n";
		}

		plugin_servcheck_debug('Result is ' . $results['data']);

		// If we have set a failed search string, then ignore the normal searches and only alert on it
		if ($test['search_failed'] != '') {
			plugin_servcheck_debug('Processing search_failed');

			if (strpos($results['data'], $test['search_failed']) !== false) {
				plugin_servcheck_debug('Search failed string success');
				$results['result'] = 'ok';
				$results['result_search'] = 'failed ok';
				return $results;
			}
		}

		plugin_servcheck_debug('Processing search');

		if ($test['search'] != '') {
			if (strpos($results['data'], $test['search']) !== false) {
				plugin_servcheck_debug('Search string success');
				$results['result'] = 'ok';
				$results['result_search'] = 'ok';
				return $results;
			} else {
				$results['result'] = 'ok';
				$results['result_search'] = 'not ok';
				return $results;
			}
		}

		if ($test['search_maint'] != '') {
			plugin_servcheck_debug('Processing search maint');
			if (strpos($results['data'], $test['search_maint']) !== false) {
				plugin_servcheck_debug('Search maint string success');
				$results['result'] = 'ok';
				$results['result_search'] = 'maint ok';
				return $results;
			}
		}
	}

	return $results;
}


function doh_try ($test) {
	global $user_agent, $config, $ca_info, $service_types_ports;

	if (!function_exists('curl_init')) {
		print "FATAL: You must install php-curl to use this test" . PHP_EOL;
		plugin_servcheck_debug('Test ' . $test['id'] . ' requires php-curl library', $test);
		$results['result'] = 'error';
		$results['error'] = 'missing php-curl library';
		return $results;
	}

	$cert_info = array();

	// default result
	$results['result'] = 'ok';
	$results['curl'] = true;
	$results['error'] = '';
	$results['result_search'] = 'not tested';
	$results['start'] = microtime(true);

	$options = array(
		CURLOPT_HEADER         => true,
		CURLOPT_USERAGENT      => $user_agent,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_MAXREDIRS      => 4,
		CURLOPT_TIMEOUT        => $test['duration_trigger'] > 0 ? ($test['duration_trigger'] + 3) : 5 ,
		CURLOPT_CAINFO         => $ca_info,
	);

	list($category,$service) = explode('_', $test['type']);

	if (empty($test['hostname']) || empty($test['dns_query'])) {
		cacti_log('Empty hostname or dns_query, nothing to test');
		$results['result'] = 'error';
		$results['error'] = 'Empty hostname/dns';
		return $results;
	}

	if (strpos($test['hostname'], ':') === 0) {
		$test['hostname'] .=  ':' . $service_types_ports[$test['type']];
	}

	$url = 'https://' . $test['hostname'] . '/' . $test['dns_query'];

	plugin_servcheck_debug('Final url is ' . $url , $test);

	$process = curl_init($url);

	if ($test['ca_id'] > 0) { 
		$ca_info = $config['base_path'] . '/plugins/servcheck/cert_' . $test['ca_id'] . '.pem'; // The folder /plugins/servcheck does exist, hence the ca_cert_x.pem can be created here
		plugin_servcheck_debug('Preparing own CA chain file ' . $ca_info , $test);
		// CURLOPT_CAINFO is to updated based on the custom CA certificate
		$options[CURLOPT_CAINFO] = $ca_info;

		$cert = db_fetch_cell_prepared('SELECT cert FROM plugin_servcheck_ca WHERE id = ?',
			array($test['ca_id']));

		$cert_file = fopen($ca_info, 'a');
		if ($cert_file) {
			fwrite ($cert_file, $cert);
			fclose($cert_file);
		} else {
			cacti_log('Cannot create ca cert file ' . $ca_info);
			$results['result'] = 'error';
			$results['error'] = 'Cannot create ca cert file';
			return $results;
		}
	}

	// Disable Cert checking for now
	if ($test['checkcert'] == '') {
		$options[CURLOPT_SSL_VERIFYPEER] = false;
		$options[CURLOPT_SSL_VERIFYHOST] = false;
	} else { // for sure, it seems that it isn't enabled by default now
		$options[CURLOPT_SSL_VERIFYPEER] = true;
		$options[CURLOPT_SSL_VERIFYHOST] = 2;
	}

	if ($test['certexpirenotify'] != '') {
		$options[CURLOPT_CERTINFO] = true;
	}

	plugin_servcheck_debug('cURL options: ' . clean_up_lines(var_export($options, true)));

	curl_setopt_array($process,$options);

	plugin_servcheck_debug('Executing curl request', $test);

	$data = curl_exec($process);
	$data = str_replace(array("'", "\\"), array(''), $data);
	$results['data'] = $data;

	// Get information regarding a specific transfer, cert info too
	$results['options'] = curl_getinfo($process);

	$results['curl_return'] = curl_errno($process);

	plugin_servcheck_debug('cURL error: ' . $results['curl_return']);

	plugin_servcheck_debug('Data: ' . clean_up_lines(var_export($data, true)));

	if ($results['curl_return'] > 0) {
		$results['error'] =  str_replace(array('"', "'"), '', (curl_error($process)));
	}

	if ($test['ca_id'] > 0) {
		unlink ($ca_info);
		plugin_servcheck_debug('Removing own CA file');
	}

	curl_close($process);

	if ($test['type'] == 'web_http' || $test['type'] == 'web_https') {

		// not found?
		if ($results['options']['http_code'] == 404) {
			$results['result'] = 'error';
			$results['error'] = '404 - Not found';
			return $results;
		}
	}

	if (empty($results['data']) && $results['curl_return'] > 0) {
		$results['result'] = 'error';
		$results['error'] = 'No data returned';

		return $results;
	}

	// If we have set a failed search string, then ignore the normal searches and only alert on it
	if ($test['search_failed'] != '') {

		plugin_servcheck_debug('Processing search_failed');

		if (strpos($data, $test['search_failed']) !== false) {
			plugin_servcheck_debug('Search failed string success');
			$results['result_search'] = 'failed ok';
			return $results;
		}
	}

	plugin_servcheck_debug('Processing search');

	if ($test['search'] != '') {
		if (strpos($data, $test['search']) !== false) {
			plugin_servcheck_debug('Search string success');
			$results['result_search'] = 'ok';
			return $results;
		} else {
			$results['result_search'] = 'not ok';
			return $results;
		}
	}

	if ($test['search_maint'] != '') {

		plugin_servcheck_debug('Processing search maint');

		if (strpos($data, $test['search_maint']) !== false) {
			plugin_servcheck_debug('Search maint string success');
			$results['result_search'] = 'maint ok';
			return $results;
		}
	}

	if ($test['requiresauth'] != '') {

		plugin_servcheck_debug('Processing requires no authentication required');

		if ($results['options']['http_code'] != 401) {
			$results['error'] = 'The requested URL returned error: ' . $results['options']['http_code'];
		}
	}

	return $results;
}

