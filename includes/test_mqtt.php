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

/*
there are 2 problems:
- when to terminate the connection - curl can't easily set "disconnect on first received message".
	I call back and if any data is returned, the connection is terminated (42).
	If no data is returned, the test timeouts (28)
- the data is not returned the same way as with other services, I have to capture it in a file
*/

function mqtt_try ($test) {
	global $config;

	// default result
	$results['result'] = 'ok';
	$results['error'] = '';
	$results['result_search'] = 'not tested';

	list($category,$service) = explode('_', $test['type']);
	plugin_servcheck_debug('Category: ' . $category , $test);
	plugin_servcheck_debug('Service: ' . $service , $test);

	if ($test['cred_id'] > 0) {
		$cred = db_fetch_row_prepared('SELECT * FROM plugin_servcheck_credential WHERE id = ?', 
			array($test['cred_id']));

		if (!$cred) {
			plugin_servcheck_debug('Credential is set but not found!' , $test);
			cacti_log('Credential not found');
			$results['result'] = 'error';
			$results['error'] = 'Credential not found';
			return $results;
		} else {
			plugin_servcheck_debug('Decrypting credential' , $test);
			$credential = servcheck_decrypt_credential($test['cred_id']);

			if (empty($cred)) {
				plugin_servcheck_debug('Credential is empty!' , $test);
				cacti_log('Credential is empty');
				$results['result'] = 'error';
				$results['error'] = 'Credential is empty';
				return $results;
			}
		}
	}

	$credential = '';

	if ($cred['username'] != '') {
		// curl needs username with %40 instead of @
		$credential = str_replace('@', '%40', $cred['username']);
		$credential .= ':';
		$credential .= $cred['password'];
		$credential .= '@';
	}

	if (strpos($test['hostname'], ':') === 0) {
		$test['hostname'] .=  ':' . $service_types_ports[$test['type']];
	}

	if ($test['path'] == '') {
		// try any message
		$test['path'] = '/%23';
	}

	$url = 'mqtt://' . $credential . $test['hostname'] . $test['path'];

	plugin_servcheck_debug('Final url is ' . $url , $test);

	$s = microtime(true);

	$process = curl_init($url);
//!!pm - pak to po sobe nemazu
	$filename = '/tmp/mqtt_' . time() . '.txt';
	$file = fopen($filename, 'w');

	$options = array(
		CURLOPT_HEADER           => true,
		CURLOPT_RETURNTRANSFER   => true,
		CURLOPT_FILE             => $file,
		CURLOPT_TIMEOUT          => 5,
		CURLOPT_NOPROGRESS       => false,
		CURLOPT_XFERINFOFUNCTION =>function(  $download_size, $downloaded, $upload_size, $uploaded){
			if ($downloaded > 0) {
				return 1;
			}
		},
	);

	plugin_servcheck_debug('cURL options: ' . clean_up_lines(var_export($options, true)));

	curl_setopt_array($process,$options);

	plugin_servcheck_debug('Executing curl request', $test);

	curl_exec($process);
	$x = fclose($file);

	$t = microtime(true) - $s;
	$results['options']['connect_time'] = $results['options']['total_time'] = $results['options']['namelookup_time'] = round($t, 4);


	$data = str_replace(array("'", "\\"), array(''), file_get_contents($filename));
	$results['data'] = $data;

	// Get information regarding a specific transfer
	$results['options'] = curl_getinfo($process);

	$results['curl_return'] = curl_errno($process);

	plugin_servcheck_debug('cURL error: ' . $results['curl_return']);

	plugin_servcheck_debug('Data: ' . clean_up_lines(var_export($data, true)));

	// 42 is ok, it is own CURLE_ABORTED_BY_CALLBACK. Normal return is 28 (timeout)
	if ($results['curl_return'] == 42) {
		$results['curl_return'] = 0;
	} elseif ($results['curl_return'] > 0) {
		$results['error'] =  str_replace(array('"', "'"), '', (curl_error($process)));
	}

	curl_close($process);

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

