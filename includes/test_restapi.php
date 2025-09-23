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


function restapi_try ($test) {
	global $user_agent, $config, $ca_info, $service_types;

	$cert_info = array();
	$http_headers = array();

	// default result
	$results['result'] = 'error';
	$results['curl'] = true;
	$results['time'] = time();
	$results['error'] = '';
	$results['result_search'] = 'not tested';
	$results['start'] = microtime(true);

	$options = array(
		CURLOPT_HEADER         => true,
		CURLOPT_USERAGENT      => $user_agent,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_MAXREDIRS      => 4,
		CURLOPT_TIMEOUT        => $test['duration_trigger'] > 0 ? ($test['duration_trigger'] + 3) : 5,
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

			if (empty($cred)) {
				plugin_servcheck_debug('Credential is empty!' , $test);
				cacti_log('Credential is empty');
				$results['result'] = 'error';
				$results['error'] = 'Credential is empty';
				return $results;
			}
		}
	}

	if (is_null($cred['type'])) {
		cacti_log('Rest API method not set');
		$results['result'] = 'error';
		$results['error'] = 'Rest API method not set';
		return $results;
	}

	// Disable Cert checking for now
	$options[CURLOPT_SSL_VERIFYPEER] = false;
	$options[CURLOPT_SSL_VERIFYHOST] = false;

	$url = $credential['data_url'];

	plugin_servcheck_debug('Using Rest API method ' . $service_types[$test['type']] , $test);

	switch ($cred['type']) {
		case 'basic':
			// we don't need set content type for login or GET/POST request because we don't set any data
			$options[CURLOPT_USERPWD] = $credential['username'] . ':' . $credential['password'];
			$options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
			break;
		case 'apikey':
			switch ($credential['option_apikey']) {
				case 'http':
					$http_headers[] = 'Authorization: ' . $credential['token_name'] . ' ' . $credential['token_value'];
					break;
				case 'custom':
					$http_headers[] = $credential['token_name'] . ': ' . $credential['token_value'];
					break;
				case 'post':
					$data = array(
						$credential['token_name'] => $credential['token_value']
					);

					$options[CURLOPT_POST] = true;
					$options[CURLOPT_POSTFIELDS] = $data;
					break;
				case 'post_json':
					$data = json_encode(array(
						$credential['token_name'] => $credential['token_value']
					));

					$options[CURLOPT_POST] = true;
					$options[CURLOPT_POSTFIELDS] = $data;
					$http_headers[] = 'Content-Type: application/json';
					break;

			}

			$options[CURLOPT_HTTPHEADER] = $http_headers;

			break;
		case 'oauth2':
			$valid = db_fetch_cell_prepared('SELECT COUNT(*) FROM plugin_servcheck_test
				WHERE id = ? AND cred_validity > NOW()',
				array($test['id']));

			if (!$valid) {
				plugin_servcheck_debug('No valid token, generating new request' , $test);

				$cred_data = json_encode(array(
					'grant_type' => 'password',
					'username'   => $credential['oauth_client_id'],
					'password'   => $credential['oauth_client_secret']
				));

				$options[CURLOPT_POST] = true;
				$options[CURLOPT_POSTFIELDS] = $cred_data;
				$http_headers[] = 'Content-Type: application/json';

				$options[CURLOPT_HTTPHEADER] = $http_headers;

				$process = curl_init($credential['login_url']);

				plugin_servcheck_debug('cURL options for login: ' . clean_up_lines(var_export($options, true)));

				curl_setopt_array($process,$options);

				plugin_servcheck_debug('Executing curl request for login: ' . $credential['login_url'], $test);

				$response = curl_exec($process);

				if (curl_errno($process) > 0) {
					// Get information regarding a specific transfer, cert info too
					$results['options'] = curl_getinfo($process);
					$results['curl_return'] = curl_errno($process);
					$results['data'] = $response;

					plugin_servcheck_debug('Problem with login: ' . $results['curl_return'] , $test);
					$results['result'] = 'error';
					$results['error'] =  str_replace(array('"', "'"), '', ($results['curl_return']));
					return $results;
				}

				curl_close($process);

				$header_size = curl_getinfo($process, CURLINFO_HEADER_SIZE);
				$header = substr($response, 0, $header_size);
				$header = str_replace(array("'", "\\"), array(''), $header);

				$body = json_decode(substr($response, $header_size), true);

				if (isset($body['token'])) {
					plugin_servcheck_debug('We got token and expiration, saving', $test);

					if (isset($body['expires_in'])) {
						$cred['cred_validity'] = time() + $body['expires_in'];
					} else {
						plugin_servcheck_debug('We got token and don\'t know expiration. We will use it only one time.', $test);
					}

					$cred['type'] = 'oauth2';
					$cred['oauth_client_id'] = $credential['oauth_client_id'];
					$cred['oauth_client_secret'] = $credential['oauth_client_secret'];
					$cred['token_value'] = $body['token'];
					$cred['token_name'] = $credential['token_name'];
					$cred['data_url'] = $credential['data_url'];
					$cred['login_url'] = $credential['login_url'];

					$enc = servcheck_encrypt_credential($cred);

					db_execute_prepared ('UPDATE plugin_servcheck_credential
						SET data = ? WHERE id = ?',
						array($enc, $cred['id']));
				} else {
					plugin_servcheck_debug('We didn\'t get token.', $test);
					$results['options'] = curl_getinfo($process);
					$results['result'] = 'error';
					$results['curl_return'] = curl_errno($process);
					$results['data'] =  str_replace(array("'", "\\"), array(''), $response);
					$results['error'] =  str_replace(array('"', "'"), '', ($results['curl_return']));
					return $results;
				}
			} else {
				plugin_servcheck_debug('Using existing token' , $test);
			}

			$http_headers = array();
			$http_headers[] = 'Authorization: ' . $cred['token_name'] . ' ' . $cred['token_value'];
			$options[CURLOPT_HTTPHEADER] = $http_headers;
			$options[CURLOPT_POST] = false;
			unset ($options[CURLOPT_POSTFIELDS]);

			break;

		case 'cookie':

			// first we have to create login request and get cookie

			$cred_data = array(
				'username'   => $credential['username'],
				'password'   => $credential['password']
			);

			if ($credential['option_cookie'] == 'json') {
				$cred_data = json_encode($cred_data);
				$http_headers[] = 'Content-Type: application/json';
			}

			$options[CURLOPT_POST] = true;
			$options[CURLOPT_POSTFIELDS] = $cred_data;
			$options[CURLOPT_HTTPHEADER] = $http_headers;

			$cookie_file = $config['base_path'] . '/plugins/servcheck/tmp_data/' . $test['cred_id'];
			$options[CURLOPT_COOKIEJAR] = $cookie_file;  // store cookie
			$process = curl_init($credential['login_url']);

			plugin_servcheck_debug('cURL options for login: ' . clean_up_lines(var_export($options, true)));

			curl_setopt_array($process,$options);

			plugin_servcheck_debug('Executing curl request for login: ' . $credential['login_url'], $test);

			$response = curl_exec($process);

			if (curl_errno($process) > 0) {
				// Get information regarding a specific transfer, cert info too
				$results['options'] = curl_getinfo($process);
				$results['curl_return'] = curl_errno($process);
				$results['data'] =  str_replace(array("'", "\\"), array(''), $response);

				plugin_servcheck_debug('Problem with login: ' . $results['curl_return'] , $test);
				$results['result'] = 'error';
				$results['error'] =  str_replace(array('"', "'"), '', ($results['curl_return']));
				return $results;
			}

			$header_size = curl_getinfo($process, CURLINFO_HEADER_SIZE);
			$header = substr($response, 0, $header_size);

			if (preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $header, $matches)) {
				foreach ($matches[1] as $cookie) {
					plugin_servcheck_debug('We got cookie', $test);
				}
			} else {
				plugin_servcheck_debug('We didn\'t get cookie', $test);

				// Get information regarding a specific transfer, cert info too
				$results['options'] = curl_getinfo($process);
				$results['curl_return'] = curl_errno($process);
				$results['data'] =  str_replace(array("'", "\\"), array(''), $response);
				$results['error'] =  str_replace(array('"', "'"), '', ($results['curl_return']));
				$results['result'] = 'error';
				return $results;
			}

			$response = str_replace(array("'", "\\"), array(''), $response);

			curl_close($process);

			// preparing query to protected restapi

			unset($http_headers);
			$options[CURLOPT_COOKIEFILE] = $cookie_file; // send cookie
			$options[CURLOPT_POST] = false;
			unset ($options[CURLOPT_POSTFIELDS]);

			break;
	}

	plugin_servcheck_debug('Final url is ' . $url , $test);

	$process = curl_init($url);

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
		return $results;
	}

	curl_close($process);


	// not found?
	if ($results['options']['http_code'] == 404) {
		$results['result'] = 'error';
		$results['error'] = '404 - Not found';
		return $results;
	}

	if (empty($results['data']) && $results['curl_return'] > 0) {
		$results['result'] = 'error';
		$results['error'] = 'No data returned';

		return $results;
	}

	$results['error'] =  'Some data returned';
	$results['result'] = 'ok';

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
			plugin_servcheck_debug('String not found');
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

	return $results;
}

