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

/*
there are 2 problems:
- when to terminate the connection - curl can't easily set "disconnect on first received message".
	I call back and if any data is returned, the connection is terminated (42).
	If no data is returned, the test timeouts (28)
- the data is not returned the same way as with other services, I have to capture it in a file
*/

function mqtt_try($test) {
	global $config;

	// default result
	$results['result']        = 'error';
	$results['curl']          = true;
	$results['error']         = '';
	$results['result_search'] = 'not tested';
	$results['start']         = microtime(true);

	[$category,$service] = explode('_', $test['type']);

	if ($test['cred_id'] > 0) {
		$cred = db_fetch_row_prepared('SELECT * FROM plugin_servcheck_credential WHERE id = ?',
			[$test['cred_id']]);

		if (!$cred) {
			servcheck_debug('Credential is set but not found!');
			cacti_log('Credential not found');
			$results['result'] = 'error';
			$results['error']  = 'Credential not found';

			return $results;
		} else {
			servcheck_debug('Decrypting credential');
			$credential = servcheck_decrypt_credential($test['cred_id']);

			if (empty($cred)) {
				servcheck_debug('Credential is empty!');
				cacti_log('Credential is empty');
				$results['result'] = 'error';
				$results['error']  = 'Credential is empty';

				return $results;
			}
		}
	}

	$cred = '';

	if ($test['cred_id'] > 0) {
		$cred = db_fetch_row_prepared('SELECT * FROM plugin_servcheck_credential WHERE id = ?',
			[$test['cred_id']]);

		if (!$cred) {
			servcheck_debug('Credential is set but not found!');
			cacti_log('Credential not found');
			$results['result'] = 'error';
			$results['error']  = 'Credential not found';

			return $results;
		} else {
			servcheck_debug('Decrypting credential');
			$credential = servcheck_decrypt_credential($test['cred_id']);

			if (empty($credential)) {
				servcheck_debug('Credential is empty!');
				cacti_log('Credential is empty');
				$results['result'] = 'error';
				$results['error']  = 'Credential is empty';

				return $results;
			}
		}
	}

	if ($test['cred_id'] > 0) {
		// curl needs username with %40 instead of @
		$cred = str_replace('@', '%40', $credential['username']);
		$cred .= ':';
		$cred .= $credial['password'];
		$cred .= '@';
	}

	if (strpos($test['hostname'], ':') === 0) {
		$test['hostname'] .= ':' . $service_types_ports[$test['type']];
	}

	if ($test['path'] == '') {
		// try any message
		$test['path'] = '/%23';
	}

	$url = 'mqtt://' . $cred . $test['hostname'] . $test['path'];

	servcheck_debug('Final url is ' . $url);

	$process = curl_init($url);

	$filename = '/tmp/mqtt_' . time() . '.txt';
	$file     = fopen($filename, 'w');

	$options = [
		CURLOPT_HEADER           => true,
		CURLOPT_RETURNTRANSFER   => true,
		CURLOPT_FILE             => $file,
		CURLOPT_TIMEOUT          => $test['duration_trigger'] > 0 ? ($test['duration_trigger'] + 2) : read_config_option('servcheck_test_max_duration'),
		CURLOPT_NOPROGRESS       => false,
		CURLOPT_XFERINFOFUNCTION => function ($download_size, $downloaded, $upload_size, $uploaded) {
			if ($downloaded > 0) {
				return 1;
			}
		},
	];

	servcheck_debug('cURL options: ' . clean_up_lines(var_export($options, true)));

	curl_setopt_array($process,$options);

	servcheck_debug('Executing curl request');

	curl_exec($process);
	fclose($file);

	$data            = str_replace(["'", '\\'], [''], file_get_contents($filename));
	$results['data'] = $data;

	unlink($filename);

	// Get information regarding a specific transfer
	$results['options'] = curl_getinfo($process);

	$results['curl_return'] = curl_errno($process);

	servcheck_debug('cURL error: ' . $results['curl_return']);

	servcheck_debug('Data: ' . clean_up_lines(var_export($data, true)));

	// 42 is ok, it is own CURLE_ABORTED_BY_CALLBACK. Normal return is 28 (timeout)
	if ($results['curl_return'] == 42) {
		$results['curl_return'] = 0;
	} elseif ($results['curl_return'] > 0) {
		$results['error'] =  str_replace(['"', "'"], '', (curl_error($process)));
	}

	curl_close($process);

	if (empty($results['data']) && $results['curl_return'] > 0) {
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
