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

$user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36';
$ca_info = $config['base_path'] . '/plugins/servcheck/cert/ca-bundle.crt';

function curl_try ($test) {
	global $user_agent, $config, $ca_info, $service_types_ports;

	$cert_info = array();
	$final_cred = '';

	// default result
	$results['result'] = 'error';
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
		CURLOPT_TIMEOUT        => $test['duration_trigger'] > 0 ? ($test['duration_trigger'] + 1) : 5,
		CURLOPT_CAINFO         => $ca_info,
	);

	list($category,$service) = explode('_', $test['type']);

	if (($test['type'] == 'web_http' || $test['type'] == 'web_https') && empty($test['path'])) {
		cacti_log('Empty path, nothing to test');
		$results['result'] = 'error';
		$results['error'] = 'Empty path';
		return $results;
	}

	if ($test['cred_id'] > 0) {
		$cred = db_fetch_row_prepared('SELECT * FROM plugin_servcheck_credential WHERE id = ?',
			array($test['cred_id']));

		if (!$cred) {
			servcheck_debug('Credential is set but not found!');
			cacti_log('Credential not found');
			$results['result'] = 'error';
			$results['error'] = 'Credential not found';
			return $results;
		} else {
			servcheck_debug('Decrypting credential');
			$credential = servcheck_decrypt_credential($test['cred_id']);

			if (empty($credential)) {
				servcheck_debug('Credential is empty!');
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

	if (($test['type'] == 'web_http' || $test['type'] == 'web_https') && $test['ipaddress'] != '') {
		if (!filter_var($test['ipaddress'], FILTER_VALIDATE_IP)) {
			cacti_log('IP in "Resolve DNS to Address" is invalid.');
			$results['result'] = 'error';
			$results['error'] = 'Invalid IP';
			return $results;
		}
		// By first listing the hostname with a dash in front, it will clear the host from the cache
		$options[CURLOPT_RESOLVE] = array('-' . $test['hostname'], $test['hostname'] . ':' . $test['ipaddress']);
		servcheck_debug('Using CURLOPT_RESOLVE: ' . $test['hostname'] . ':' . $test['ipaddress']);
	}

	// basic auth
	if (($test['type'] == 'web_http' || $test['type'] == 'web_https') && isset($credential)) {
		$options[CURLOPT_USERPWD] = $credential['username'] . ':' . $credential['password'];
		$options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
	}

	// 'tls' is my service name/flag. I need remove it
	// smtp = plaintext, smtps = encrypted on port 465, smtptls = plain + startls
	if (strpos($service, 'tls') !== false ) {
		$service = substr($service, 0, -3);
	}

	if ($service == 'ldap' || $service == 'ldaps') {	// do search
		// ldap needs credentials in options
		$test['path'] = '/' . $test['ldapsearch'];
		$options[CURLOPT_USERPWD] = $credential['username'] . ':' . $credential['password'];
	}

	if ($service == 'smb' || $service == 'smbs') {
		$options[CURLOPT_USERPWD] = str_replace('@', '%40', $credential['username']) . ':' . $credential['password'];
	}

	if ($test['ca_id'] > 0) {
		$ca_file = $config['base_path'] . '/plugins/servcheck/tmp_data/ca_cert_' . $test['id'] . '_' . $test['ca_id'] . '.pem';
		servcheck_debug('Preparing own CA chain file ' . $ca_file);
		// CURLOPT_CAINFO is to updated based on the custom CA certificate
		$options[CURLOPT_CAINFO] = $ca_file;

		$cert = db_fetch_cell_prepared('SELECT cert FROM plugin_servcheck_ca WHERE id = ?',
			array($test['ca_id']));

		$cert_file = fopen($ca_file, 'w+');
		if ($cert_file) {
			fwrite ($cert_file, $cert);
			fclose($cert_file);
		} else {
			cacti_log('Cannot create ca cert file ' . $ca_file);
			$results['result'] = 'error';
			$results['error'] = 'Cannot create ca cert file';
			return $results;
		}
	}

	if ($test['type'] == 'web_http' || $test['type'] == 'web_https') {
		$options[CURLOPT_FAILONERROR] = $test['requiresauth'] == '' ? true : false;

		// use proxy?
		if ($test['proxy_id'] > 0) {

			$proxy = db_fetch_row_prepared('SELECT *
				FROM plugin_servcheck_proxy
				WHERE id = ?',
				array($test['proxy_id']));

			if (cacti_sizeof($proxy)) {
				$options[CURLOPT_PROXY] = $proxy['hostname'];
				$options[CURLOPT_UNRESTRICTED_AUTH] = true;

				if ($test['type'] == 'web_https') {
					$options[CURLOPT_PROXYPORT] = $proxy['https_port'];
				} else {
					$options[CURLOPT_PROXYPORT] = $proxy['http_port'];
				}


				if ($proxy['cred_id'] > 0) {
					$proxy_cred = db_fetch_assoc_prepared('SELECT * FROM plugin_servcheck_credential WHERE id = ?',
						array($proxy['cred_id']));

					if (!$cred) {
						servcheck_debug('Proxy credential is set but not found!');
						cacti_log('Credential not found');
						$results['result'] = 'error';
						$results['error'] = 'Credential not found';
						return $results;
					} else {
						servcheck_debug('Decrypting proxy credential');
						$proxy_cred = servcheck_decrypt_credential($proxy['cred_id']);

						if (empty($proxy_cred)) {
							servcheck_debug('Proxy credential is empty!');
							cacti_log('Credential is empty');
							$results['result'] = 'error';
							$results['error'] = 'Credential is empty';
							return $results;
						}
					}
				}

				if ($proxy_cred['username'] != '') {
					$options[CURLOPT_PROXYUSERPWD] = $proxy_cred['username'] . ':' . $proxy_cred['password'];
				}

			} else {
				cacti_log('ERROR: Unable to obtain Proxy settings');
			}
		}

		if (($test['checkcert'] || $test['certexpirenotify']) && $test['type'] == 'web_http') {
			cacti_log('ERROR: Check certificate is enabled but it is http connection, skipping test');
			servcheck_debug('ERROR: Check certificate or certificate expiration is enabled but it is http connection, skipping test');
		}
	}


	// Disable Cert checking
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

	$url = $service . '://' . $final_cred . $test['hostname'] . $test['path'];

	servcheck_debug('Final url is ' . $url);

	$process = curl_init($url);

	servcheck_debug('cURL options: ' . clean_up_lines(var_export($options, true)));

	curl_setopt_array($process,$options);

	servcheck_debug('Executing curl request');

	$data = curl_exec($process);
	$data = str_replace(array("'", "\\"), array(''), $data);
	$results['data'] = $data;

	// Get information regarding a specific transfer, cert info too
	$results['options'] = curl_getinfo($process);

	$results['curl_return'] = curl_errno($process);

	servcheck_debug('cURL error: ' . $results['curl_return']);

	servcheck_debug('Result: ' . clean_up_lines(var_export($data, true)));

	if ($test['ca_id'] > 0) {
		unlink ($ca_file);
		servcheck_debug('Removing own CA file');
	}

	if (empty($results['data']) && $results['curl_return'] > 0) {
		$results['error'] =  'No data returned';
		$results['result'] = 'error';

		return $results;
	}


	if (!empty($results['data']) && $results['curl_return'] > 0) {
		$results['error'] =  str_replace(array('"', "'"), '', (curl_error($process)));
		$results['result'] = 'error';
		return $results;
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

	$results['result'] = 'ok';
	$results['error'] = 'Some data returned';

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

	if ($test['requiresauth'] != '') {

		servcheck_debug('Processing requires no authentication required');

		if ($results['options']['http_code'] != 401) {
			$results['result'] = 'error';
			$results['error'] = 'The requested URL returned error: ' . $results['options']['http_code'];
			return $results;
		}
	}

	return $results;

}


