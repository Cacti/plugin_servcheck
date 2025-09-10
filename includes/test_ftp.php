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

// $user_agent can be of user's choice e.g. Linux or Windows based
$user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36';

$ca_info = $config['base_path'] . '/plugins/servcheck/cert/ca-bundle.crt';

function ftp_try ($test) {
	global $user_agent, $config, $ca_info, $service_types_ports;

	if (!function_exists('curl_init')) {
		print "FATAL: You must install php-curl to use this test" . PHP_EOL;
		plugin_servcheck_debug('Test ' . $test['id'] . ' requires php-curl library', $test);
		$results['result'] = 'error';
		$results['error'] = 'missing php-curl library';
		return $results;
	}

	// default result
	$results['result'] = 'error';
	$results['curl'] = true;
	$results['time'] = time();
	$results['error'] = '';
	$results['result_search'] = 'not tested';
	$results['start'] = microtime(true);

	$final_cred = '';

	$options = array(
		CURLOPT_HEADER         => true,
		CURLOPT_USERAGENT      => $user_agent,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_MAXREDIRS      => 4,
		CURLOPT_TIMEOUT        => $test['duration_trigger'] > 0 ? ($test['duration_trigger'] + 1) : 5,
		CURLOPT_CAINFO         => $ca_info,
	);

	list($category,$service) = explode('_', $test['type']);

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

			if (empty($credential)) {
				plugin_servcheck_debug('Credential is empty!' , $test);
				cacti_log('Credential is empty');
				$results['result'] = 'error';
				$results['error'] = 'Credential is empty';
				return $results;
			}
		}
	}

	if (!str_contains($test['hostname'], ':')) {
		$test['hostname'] .=  ':' . $service_types_ports[$test['type']];
	}

	if ($service == 'ftp' || $service == 'scp')  {

		if ($cred['type'] == 'userpass') {
			// curl needs username with %40 instead of @
			$final_cred  = str_replace('@', '%40', $credential['username']);
			$final_cred .= ':';
			$final_cred .= str_replace('@', '%40', $credential['password']);
			$final_cred .= '@';
		} else {
			plugin_servcheck_debug('Incorrect credential type, use user/pass' , $test);
			cacti_log('Incorrect credential type, use user/pass');
			$results['result'] = 'error';
			$results['error'] = 'Incorrect credential type';
			return $results;
		}
	}

	$url = $service . '://' . $final_cred . $test['hostname'] . $test['path'];

	plugin_servcheck_debug('Final url is ' . $url , $test);

	$process = curl_init($url);

	if ($test['ca_id'] > 0) {
		$ca_info = $config['base_path'] . '/plugins/servcheck/tmp_data/ca_cert_' . $test['ca_id'] . '.pem'; // The folder /plugins/servcheck/tmp_data does exist, hence the ca_cert_x.pem can be created here
		plugin_servcheck_debug('Preparing own CA chain file ' . $ca_info , $test);
		// CURLOPT_CAINFO is to updated based on the custom CA certificate
		$options[CURLOPT_CAINFO] = $ca_info;

		$cert = db_fetch_cell_prepared('SELECT cert FROM plugin_servcheck_ca WHERE id = ?',
			array($test['ca_id']));

		$cert_file = fopen($ca_info, 'w+');
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
		$results['result'] = 'error';
		return $result;
	}

	if ($test['ca_id'] > 0) {
		unlink ($ca_info);
		plugin_servcheck_debug('Removing own CA file');
	}

	curl_close($process);

	if (empty($results['data']) && $results['curl_return'] > 0) {
		$results['result'] = 'error';
		$results['error'] = 'No data returned';
		return $results;
	}

	$results['result'] = 'ok';
	$results['error'] = 'Some data returned';

	// If we have set a failed search string, then ignore the normal searches and only alert on it
	if ($test['search_failed'] != '') {

		plugin_servcheck_debug('Processing search_failed');

		if (strpos($data, $test['search_failed']) !== false) {
			plugin_servcheck_debug('Search failed string success');
			$results['result'] = 'ok';
			$results['result_search'] = 'failed ok';
			return $results;
		}
	}

	plugin_servcheck_debug('Processing search');

	if ($test['search'] != '') {
		if (strpos($data, $test['search']) !== false) {
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

		if (strpos($data, $test['search_maint']) !== false) {
			plugin_servcheck_debug('Search maint string success');
			$results['result'] = 'ok';
			$results['result_search'] = 'maint ok';
			return $results;
		}
	}


//!!pm smazat
$results['return'] = 'NEVER!';

	return $results;
}

