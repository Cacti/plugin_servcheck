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

$ca_info = $config['base_path'] . '/plugins/servcheck/cert/ca-bundle.crt';

function mail_try($test) {
	global $config, $ca_info, $service_types_ports;

	$final_cred = '';
	$data       = '';

	// default result
	$results['result']        = 'error';
	$results['curl']          = false;
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

			if (empty($credential)) {
				servcheck_debug('Credential is empty!');
				cacti_log('Credential is empty');
				$results['result'] = 'error';
				$results['error']  = 'Credential is empty';

				return $results;
			}
		}
	}

	if (!str_contains($test['hostname'], ':')) {
		$test['hostname'] .= ':' . $service_types_ports[$test['type']];
	}

	if ($test['ca_id'] > 0) {
		$own_ca_info = $config['base_path'] . '/plugins/servcheck/tmp_data/ca_cert_' . $test['ca_id'] . '.pem'; // The folder /plugins/servcheck/tmp_data does exist, hence the ca_cert_x.pem can be created here
		servcheck_debug('Preparing own CA chain file ' . $ca_info);

		$cert = db_fetch_cell_prepared('SELECT cert FROM plugin_servcheck_ca WHERE id = ?',
			[$test['ca_id']]);

		$cert_file = fopen($ca_info, 'w+');

		if ($cert_file) {
			fwrite($cert_file, $cert);
			fclose($cert_file);
		} else {
			cacti_log('Cannot create ca cert file ' . $ca_info);
			$results['result'] = 'error';
			$results['error']  = 'Cannot create ca cert file';

			return $results;
		}
	}

	if ($test['checkcert'] || $test['certexpirenotify']) {
		$params = [
			'ssl' => [
				'verify_peer'       => true,
				'verify_peer_name'  => true,
				'allow_self_signed' => false,
				'capture_peer_cert' => true
			]
		];
	} else {
		$params = [
			'ssl' => [
				'verify_peer'       => false,
				'verify_peer_name'  => false,
				'allow_self_signed' => true
			]
		];
	}

	if (isset($own_ca_info)) {
		$params['ssl']['cafile'] = $own_ca_info;
	}

	$context = stream_context_create($params);

	switch ($service) {
		case 'smtp':
			servcheck_debug('Trying to connect ' . 'tcp://' . $test['hostname']);

			$fp = stream_socket_client(
				'tcp://' . $test['hostname'],
				$errno,
				$errstr,
				3,
				STREAM_CLIENT_CONNECT,
				$context
			);

			if (!$fp) {
				$results['result'] = 'error';
				$results['error']  = 'Cannot connect';

				return $results;
			}

			servcheck_debug('Connected');

			$data .= read_response($fp); // welcome banner

			send($fp, 'EHLO servcheck.cacti.net');
			$data .= read_response($fp); // ehlo

			send($fp, 'QUIT');
			fclose($fp);

			break;
		case 'smtps':
			servcheck_debug('Trying to connect ' . 'ssl://' . $test['hostname']);

			$fp = stream_socket_client(
				'ssl://' . $test['hostname'],
				$errno,
				$errstr,
				3,
				STREAM_CLIENT_CONNECT,
				$context
			);

			if (!$fp) {
				$results['result'] = 'error';
				$results['error']  = 'Cannot connect';

				return $results;
			}

			servcheck_debug('Connected');

			if ($test['checkcert'] || $test['certexpirenotify']) {
				servcheck_debug('Gathering certificate information');
				$con_params = stream_context_get_params($fp);
				$certinfo   = openssl_x509_parse($con_params['options']['ssl']['peer_certificate']);

				$results['cert_valid_to'] = $certinfo['validTo_time_t'];
			}

			$data .= read_response($fp); // welcome banner
			servcheck_debug('Welcome banner: ' . $data);

			if ($test['cred_id'] > 0) {
				send($fp, 'EHLO localhost');
				$data .= read_response($fp);
				send($fp, 'AUTH LOGIN');
				$data .= read_response($fp);
				send($fp, base64_encode($credential['username']));
				$data .= read_response($fp);
				send($fp, base64_encode($credential['password']));
				$data .= read_response($fp); // message after login

				servcheck_debug('All data returned: ' . $data);
			} else {
				servcheck_debug('No credential set, finishing' . $data);
			}

			send($fp, 'QUIT');
			fclose($fp);

			break;
		case 'smtptls':
			servcheck_debug('Trying to connect ' . 'tcp://' . $test['hostname']);

			$fp = stream_socket_client(
				'tcp://' . $test['hostname'],
				$errno,
				$errstr,
				3,
				STREAM_CLIENT_CONNECT,
				$context
			);

			if (!$fp) {
				$results['result'] = 'error';
				$results['error']  = 'Cannot connect';

				return $results;
			}

			servcheck_debug('Connected');

			$data .= read_response($fp); // welcome banner

			send($fp, 'EHLO servcheck.cacti.net');
			$data .= read_response($fp); // ehlo

			send($fp, 'STARTTLS');
			$xdata = read_response($fp); // starttls respond

			if (strpos($xdata, '220') !== 0) {
				$results['result'] = 'error';
				$results['error']  = 'Server refused STARTTLS command';

				return $results;
			}

			$data .= $xdata;

			if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
				$results['result'] = 'error';
				$results['error']  = 'TLS handshake failed';

				return $results;
			}

			if ($test['checkcert'] || $test['certexpirenotify']) {
				servcheck_debug('Gathering certificate information');
				$context                  = stream_context_get_options($fp);
				$certinfo                 = openssl_x509_parse($context['ssl']['peer_certificate']);
				$results['cert_valid_to'] = $certinfo['validTo_time_t'];
			}

			// we need ehlo again
			send($fp, 'EHLO servcheck.cacti.net');
			$data .= read_response($fp);

			if ($test['cred_id'] > 0) {
				send($fp, 'AUTH LOGIN');
				$data .= read_response($fp);

				send($fp, base64_encode($credential['username']));
				$data .= read_response($fp);

				send($fp, base64_encode($credential['password']));
				$data .= read_response($fp);

				servcheck_debug('Data returned after EHLO: ' . $data);
			} else {
				servcheck_debug('No credential set, finishing' . $data);
			}

			send($fp, 'QUIT');
			fclose($fp);

			break;
		case 'imap':
		case 'imaptls':
		case 'imaps':
			$method = $service == 'imaps' ? 'ssl' : 'tcp';

			servcheck_debug('Trying to connect ' . $method . '://' . $test['hostname']);

			$fp = stream_socket_client(
				$method . '://' . $test['hostname'],
				$errno,
				$errstr,
				3,
				STREAM_CLIENT_CONNECT,
				$context
			);

			if (!$fp) {
				$results['result'] = 'error';
				$results['error']  = 'Cannot connect';

				return $results;
			}

			servcheck_debug('Connected');

			if ($service == 'imaps' && ($test['checkcert'] || $test['certexpirenotify'])) {
				servcheck_debug('Gathering certificate information');
				$con_params               = stream_context_get_params($fp);
				$certinfo                 = openssl_x509_parse($con_params['options']['ssl']['peer_certificate']);
				$results['cert_valid_to'] = $certinfo['validTo_time_t'];
			}

			$data .= fgets($fp); // welcome banner

			if ($service == 'imaptls') {
				servcheck_debug('Trying STARTTLS');

				send($fp, 'A002 STARTTLS');
				$data .= read_response_imap($fp, 'A002');

				if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
					$results['result'] = 'error';
					$results['error']  = 'TLS handshake failed';

					return $results;
				}

				if ($test['checkcert'] || $test['certexpirenotify']) {
					servcheck_debug('Gathering certificate information');
					$context                  = stream_context_get_options($fp);
					$certinfo                 = openssl_x509_parse($context['ssl']['peer_certificate']);
					$results['cert_valid_to'] = $certinfo['validTo_time_t'];
				}
			}

			if ($test['cred_id'] > 0) {
				if (stripos($data, 'auth=plain') !== false) {
					servcheck_debug('Trying to authenticate - method=plain');
					send($fp, 'A010 AUTHENTICATE PLAIN');
					$data .= read_response_imap($fp, 'A010');
					send($fp, base64_encode("\0" . $credential['username'] . "\0" . $credential['password']));
					$data .= read_response_imap($fp);
				} elseif (stripos($data, 'auth=login') !== false) {
					servcheck_debug('Trying to authenticate - method=login');
					send($fp, 'A010 AUTHENTICATE LOGIN');
					$data .= read_response_imap($fp, 'A010');
					send($fp, base64_encode($credential['username']));
					$data .= read_response_imap($fp);
					send($fp, base64_encode($credential['password']));
					$data .= read_response_imap($fp);
				}

				servcheck_debug('Reading messages');
				send($fp, 'A020 SELECT INBOX');
				$data .= read_response_imap($fp, 'A020');
			}

			send($fp, 'A1000 LOGOUT');
			fclose($fp);

			break;
		case 'pop3':
		case 'pop3tls':
		case 'pop3s':
			$method = $service == 'pop3s' ? 'ssl' : 'tcp';

			servcheck_debug('Trying to connect ' . $method . '://' . $test['hostname']);

			$fp = stream_socket_client(
				$method . '://' . $test['hostname'],
				$errno,
				$errstr,
				3,
				STREAM_CLIENT_CONNECT,
				$context
			);

			if (!$fp) {
				$results['result'] = 'error';
				$results['error']  = 'Cannot connect';

				return $results;
			}

			servcheck_debug('Connected');

			$data .= fgets($fp); // welcome banner

			if ($service == 'pop3s' && ($test['checkcert'] || $test['certexpirenotify'])) {
				servcheck_debug('Gathering certificate information');
				$con_params               = stream_context_get_params($fp);
				$certinfo                 = openssl_x509_parse($con_params['options']['ssl']['peer_certificate']);
				$results['cert_valid_to'] = $certinfo['validTo_time_t'];
			}

			if ($service == 'pop3tls') {
				servcheck_debug('Trying STARTTLS');

				send($fp, 'A002 STARTTLS');
				$data .= fgets($fp);

				if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
					$results['result'] = 'error';
					$results['error']  = 'TLS handshake failed';

					return $results;
				}

				if ($test['checkcert'] || $test['certexpirenotify']) {
					servcheck_debug('Gathering certificate information');
					$context                  = stream_context_get_options($fp);
					$certinfo                 = openssl_x509_parse($context['ssl']['peer_certificate']);
					$results['cert_valid_to'] = $certinfo['validTo_time_t'];
				}
			}

			if ($test['cred_id'] > 0) {
				servcheck_debug('Trying to authenticate');
				send($fp, 'USER ' . $credential['username']);
				$data .= fgets($fp);
				send($fp, 'PASS ' . $credential['password']);
				$data .= fgets($fp);

				servcheck_debug('Reading number of messages');
				send($fp, 'STAT');
				$data .= fgets($fp);
			}

			send($fp, 'QUIT');
			fclose($fp);

			break;
		default:
			$results['result'] = 'error';
			$results['error']  = 'Incorrect test type';

			return $results;

			break;
	}

	$data = str_replace(["'", '\\'], [''], $data);

	$results['data'] = $data;

	servcheck_debug('Result: ' . clean_up_lines(var_export($data, true)));

	if ($test['ca_id'] > 0) {
		unlink($own_ca_info);
		servcheck_debug('Removing own CA file');
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

function send($fp, $cmd) {
	fwrite($fp, $cmd . "\r\n");
}

// response like 250 OK
function read_response($fp) {
	$response = '';

	while ($line = fgets($fp)) {
		$response .= $line;

		if (preg_match('/^\d{3} /', $line)) {
			break;
		}
	}

	return $response;
}

// for IMAP we need more complicated function. Each command and response begins with XXX tag
function read_response_imap($fp, $tag = 'A001') {
	$response = '';
	stream_set_timeout($fp, 2);

	while (!feof($fp)) {
		$line = fgets($fp);

		if ($line === false) {
			break;
		}

		$response .= $line;

		if (strpos($line, $tag) === 0) {
			break;
		}
	}

	return $response;
}
