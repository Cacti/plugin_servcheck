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
 * Runs a mail-protocol (SMTP/IMAP/POP3) service check test: connects to
 * the target mail server via a raw socket, authenticates using the
 * test's associated credential when configured, exchanges the
 * protocol's greeting/command sequence, and evaluates the server's
 * response against the expected/maintenance/failure search patterns.
 * Called from servcheck_run_test() for tests of a mail-protocol type.
 *
 * @param array $test The plugin_servcheck_test row describing the check
 *                    to run.
 *
 * @return array The check result: 'result' ('ok'/'error'), 'curl'
 *               (false), 'error', 'result_search', and 'start'.
 *
 * @global array  $config              Cacti global configuration array;
 *                                     used to build a per-test CA file
 *                                     path.
 * @global array  $service_types_ports Default port numbers per service
 *                                     type, used when the test's
 *                                     hostname doesn't specify one.
 */
function mail_try(array $test): array {
	global $config, $service_types_ports;

	$final_cred = '';
	$data       = '';

	// default result
	$results['result']        = 'error';
	$results['curl']          = false;
	$results['error']         = '';
	$results['result_search'] = 'not tested';
	$results['start']         = microtime(true);
	$results['data']          = '';

	[$category,$service] = explode('_', $test['type']);

	if (!servcheck_prepare_test($test, $results, $cred, $credential)) {
		return $results;
	}

	if ($test['ca_id'] > 0) {
		servcheck_debug('Preparing own CA chain file ');

		$cert = db_fetch_cell_prepared('SELECT cert FROM plugin_servcheck_ca WHERE id = ?',
			[$test['ca_id']]);

		$ca_file = tempnam(sys_get_temp_dir(), 'srvck');
		$cert_file = fopen($ca_file, 'w+');

		if ($cert_file) {
			fwrite($cert_file, $cert);
			fclose($cert_file);
		} else {

			cacti_log('Cannot create ca cert file ' . $ca_file);

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

	if (isset($ca_file)) {
		$params['ssl']['cafile'] = $ca_file;
	}

	$context = stream_context_create($params);

	try {
		switch ($service) {
			case 'smtp':
				$fp = servcheck_mail_open($test, 'tcp://', $context, $results);

				if ($fp === false) {
					return $results;
				}

				$data .= read_response($fp); // welcome banner

				send($fp, 'EHLO servcheck.cacti.net');
				$data .= read_response($fp); // ehlo

				send($fp, 'QUIT');
				fclose($fp);

				break;
			case 'smtps':
				$fp = servcheck_mail_open($test, 'ssl://', $context, $results);

				if ($fp === false) {
					return $results;
				}

				if ($test['checkcert'] || $test['certexpirenotify']) {
					servcheck_mail_cert_expiry($fp, $results);
				}

				$data .= read_response($fp); // welcome banner
				servcheck_debug('Welcome banner: ' . $data);

				if ($test['cred_id'] > 0) {
					send($fp, 'EHLO localhost');
					$data .= read_response($fp);
					send($fp, 'AUTH LOGIN');
					$data .= read_response($fp);
					send($fp, base64_encode(($credential['username'] ?? '')));
					$data .= read_response($fp);
					send($fp, base64_encode(($credential['password'] ?? '')));
					$data .= read_response($fp); // message after login

					servcheck_debug('All data returned: ' . $data);
				} else {
					servcheck_debug('No credential set, finishing' . $data);
				}

				send($fp, 'QUIT');
				fclose($fp);

				break;
			case 'smtptls':
				$fp = servcheck_mail_open($test, 'tcp://', $context, $results);

				if ($fp === false) {
					return $results;
				}

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

				if (!servcheck_mail_enable_crypto($fp)) {
					$results['result'] = 'error';
					$results['error']  = 'TLS handshake failed';

					return $results;
				}

				if ($test['checkcert'] || $test['certexpirenotify']) {
					servcheck_mail_cert_expiry($fp, $results);
				}

				// we need ehlo again
				send($fp, 'EHLO servcheck.cacti.net');
				$data .= read_response($fp);

				if ($test['cred_id'] > 0) {
					send($fp, 'AUTH LOGIN');
					$data .= read_response($fp);

					send($fp, base64_encode(($credential['username'] ?? '')));
					$data .= read_response($fp);

					send($fp, base64_encode(($credential['password'] ?? '')));
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

				$fp = servcheck_mail_open($test, $method . '://', $context, $results);

				if ($fp === false) {
					return $results;
				}

				if ($service == 'imaps' && ($test['checkcert'] || $test['certexpirenotify'])) {
					servcheck_mail_cert_expiry($fp, $results);
				}

				$data .= servcheck_mail_read($fp); // welcome banner

				if ($service == 'imaptls') {
					servcheck_debug('Trying STARTTLS');

					send($fp, 'A002 STARTTLS');
					$data .= read_response_imap($fp, 'A002');

					if (!servcheck_mail_enable_crypto($fp)) {
						$results['result'] = 'error';
						$results['error']  = 'TLS handshake failed';

						return $results;
					}

					if ($test['checkcert'] || $test['certexpirenotify']) {
						servcheck_mail_cert_expiry($fp, $results);
					}
				}

				if ($test['cred_id'] > 0) {
					if (stripos($data, 'auth=plain') !== false) {
						servcheck_debug('Trying to authenticate - method=plain');
						send($fp, 'A010 AUTHENTICATE PLAIN');
						$data .= read_response_imap($fp, 'A010');
						send($fp, base64_encode("\0" . ($credential['username'] ?? '') . "\0" . ($credential['password'] ?? '')));
						$data .= read_response_imap($fp);
					} elseif (stripos($data, 'auth=login') !== false) {
						servcheck_debug('Trying to authenticate - method=login');
						send($fp, 'A010 AUTHENTICATE LOGIN');
						$data .= read_response_imap($fp, 'A010');
						send($fp, base64_encode(($credential['username'] ?? '')));
						$data .= read_response_imap($fp);
						send($fp, base64_encode(($credential['password'] ?? '')));
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

				$fp = servcheck_mail_open($test, $method . '://', $context, $results);

				if ($fp === false) {
					return $results;
				}

				$data .= servcheck_mail_read($fp); // welcome banner

				if ($service == 'pop3s' && ($test['checkcert'] || $test['certexpirenotify'])) {
					servcheck_mail_cert_expiry($fp, $results);
				}

				if ($service == 'pop3tls') {
					servcheck_debug('Trying STARTTLS');

					send($fp, 'A002 STARTTLS');
					$data .= servcheck_mail_read($fp);

					if (!servcheck_mail_enable_crypto($fp)) {
						$results['result'] = 'error';
						$results['error']  = 'TLS handshake failed';

						return $results;
					}

					if ($test['checkcert'] || $test['certexpirenotify']) {
						servcheck_mail_cert_expiry($fp, $results);
					}
				}

				if ($test['cred_id'] > 0) {
					servcheck_debug('Trying to authenticate');
					send($fp, 'USER ' . ($credential['username'] ?? ''));
					$data .= servcheck_mail_read($fp);
					send($fp, 'PASS ' . ($credential['password'] ?? ''));
					$data .= servcheck_mail_read($fp);

					servcheck_debug('Reading number of messages');
					send($fp, 'STAT');
					$data .= servcheck_mail_read($fp);
				}

				send($fp, 'QUIT');
				fclose($fp);

				break;
			default:
				$results['result'] = 'error';
				$results['error']  = 'Incorrect test type';

				return $results;
		}
	} catch (\RuntimeException $e) {
		$results['error'] = $e->getMessage();

		return $results;
	} finally {
		if (isset($fp) && is_resource($fp)) {
			fclose($fp);
		}

		if (isset($ca_file) && file_exists($ca_file)) {
			servcheck_debug('Removing own CA file');
			unlink($ca_file);
		}
	}

	$data = str_replace(["'", '\\'], [''], $data);

	$results['data'] = $data;

	servcheck_debug('Result: ' . clean_up_lines(var_export($data, true)));

	if (empty($results['data'])) {
		$results['result'] = 'error';
		$results['error']  = 'No data returned';

		return $results;
	}

	$results['result'] = 'ok';
	$results['error']  = 'Some data returned';

	return servcheck_evaluate_search($test, $data, $results);
}

/**
 * Connects to a mail server for a service check and records a connection
 * failure on the result. Centralises the connect-and-check sequence shared by
 * every mail service type.
 *
 * @param array    $test    The plugin_servcheck_test row (hostname,
 *                          duration_trigger).
 * @param string   $scheme  The transport scheme prefix, e.g. 'tcp://' or
 *                          'ssl://'.
 * @param resource $context The stream context, including TLS options.
 * @param array    $results The result array, updated on failure (by reference).
 *
 * @return resource|false The connected socket, or false on failure.
 */
function servcheck_mail_open(array $test, string $scheme, $context, array &$results) {
	servcheck_debug('Trying to connect ' . $scheme . $test['hostname']);

	$timeout = $test['duration_trigger'] > 0
		? ($test['duration_trigger'] + 2)
		: read_config_option('servcheck_test_max_duration');

	$fp = servcheck_mail_connect($scheme . $test['hostname'], $errno, $errstr, $timeout, $context);

	if ($fp === false) {
		$results['result'] = 'error';
		$results['error']  = sprintf('Cannot connect to %s: %s (code %d)',
			$test['hostname'], $errstr, $errno);
	} else {
		servcheck_debug('Connected');
	}

	return $fp;
}

/**
 * Opens a mail socket, handling expected connection warnings as test failures.
 * The Cacti error handler is restored even if opening the socket throws.
 *
 * @param string $address The transport, hostname and port.
 * @param int    $errno   Receives the socket error code.
 * @param string $errstr  Receives the socket error description.
 * @param float  $timeout The connection timeout in seconds.
 * @param resource $context The stream context, including TLS options.
 * @return resource|false The connected socket, or false on failure.
 */
function servcheck_mail_connect(string $address, &$errno, &$errstr, float $timeout, $context) {
	require_once(__DIR__ . '/functions.php');

	$errno  = 0;
	$errstr = '';
	$fp = servcheck_network_call(
		static function () use ($address, &$errno, &$errstr, $timeout, $context) {
			return stream_socket_client($address, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
		},
		['stream_socket_client'],
		$warning
	);

	if ($fp === false && $errstr === '') {
		$errstr = $warning !== '' ? $warning : 'Connection failed';
	}

	if ($fp !== false) {
		$seconds = (int) $timeout;
		stream_set_timeout($fp, $seconds, (int) (($timeout - $seconds) * 1000000));
	}

	return $fp;
}

/**
 * Converts an expected mail I/O warning into a test error, not a PHP log entry.
 *
 * @param callable $operation The network operation.
 * @param array $functions The PHP functions whose warnings are expected.
 * @return mixed The operation's return value.
 */
function servcheck_mail_io(callable $operation, array $functions) {
	require_once(__DIR__ . '/functions.php');

	$result = servcheck_network_call($operation, $functions, $warning);

	if ($warning !== '') {
		throw new \RuntimeException($warning);
	}

	return $result;
}

/**
 * Completes a TLS handshake on an open mail socket, negotiating only TLS 1.2
 * and TLS 1.3. The weak TLS 1.0 and 1.1 protocols implied by
 * STREAM_CRYPTO_METHOD_TLS_CLIENT are intentionally excluded. Centralises the
 * handshake so every mail service type negotiates the same strong protocols.
 *
 * @param resource $fp The connected mail socket.
 *
 * @return bool True on a successful handshake, false otherwise.
 */
function servcheck_mail_enable_crypto($fp): bool {
	$method = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;

	return (bool) servcheck_mail_io(static function () use ($fp, $method) {
		return stream_socket_enable_crypto($fp, true, $method);
	}, ['stream_socket_enable_crypto']);
}

/**
 * Reads the peer certificate expiry from an open TLS mail socket and records
 * it on the result as 'cert_valid_to'. Centralises certificate gathering so
 * every mail service type reads the expiry the same way.
 *
 * @param resource $fp      The TLS-enabled mail socket.
 * @param array    $results The result array to update (by reference).
 *
 * @return void
 */
function servcheck_mail_cert_expiry($fp, array &$results): void {
	servcheck_debug('Gathering certificate information');
	$options  = stream_context_get_options($fp);
	$certinfo = isset($options['ssl']['peer_certificate'])
		? openssl_x509_parse($options['ssl']['peer_certificate'])
		: false;
	$results['cert_valid_to'] = is_array($certinfo) ? ($certinfo['validTo_time_t'] ?? null) : null;
}

/**
 * Reads a mail response line and reports read timeouts as test failures.
 *
 * @param resource $fp The connected socket.
 * @return string|false A response line, or false on end of stream.
 */
function servcheck_mail_read($fp) {
	$line = servcheck_mail_io(static function () use ($fp) {
		return fgets($fp);
	}, ['fgets']);
	$status = stream_get_meta_data($fp);

	if (!empty($status['timed_out'])) {
		throw new \RuntimeException('Mail server response timed out');
	}

	return $line;
}

/**
 * Writes a single command line (with a trailing CRLF) to the mail
 * server's socket stream. Called from mail_try() to send each protocol
 * command.
 *
 * @param resource $fp  The open socket stream to the mail server.
 * @param string   $cmd The command line to send (without the trailing
 *                      CRLF).
 *
 * @return void
 */
function send($fp, string $cmd): void {
	$command = $cmd . "\r\n";
	$written = servcheck_mail_io(static function () use ($fp, $command) {
		return fwrite($fp, $command);
	}, ['fwrite']);

	if ($written !== strlen($command)) {
		throw new \RuntimeException('Cannot send command to mail server');
	}
}

// response like 250 OK

/**
 * Reads lines from the mail server's socket stream until a final
 * SMTP/POP3-style numeric status line (response like 250 OK) is seen.
 * Called from mail_try() to read the server's reply to each command.
 *
 * @param resource $fp The open socket stream to the mail server.
 *
 * @return string The accumulated response text.
 */
function read_response($fp): string {
	$response = '';

	while ($line = servcheck_mail_read($fp)) {
		$response .= $line;

		if (preg_match('/^\d{3} /', $line)) {
			break;
		}
	}

	return $response;
}

// for IMAP we need more complicated function. Each command and response begins with XXX tag

/**
 * Reads lines from the mail server's socket stream until a line tagged
 * with the given IMAP command tag is seen, since for IMAP we need more
 * complicated function - each command and response begins with XXX tag
 * rather than a fixed numeric status code. Called from mail_try() to
 * read an IMAP server's tagged response.
 *
 * @param resource $fp  The open socket stream to the mail server.
 * @param string   $tag The IMAP command tag to look for at the start of
 *                      a line; defaults to 'A001'.
 *
 * @return string The accumulated response text.
 */
function read_response_imap($fp, string $tag = 'A001'): string {
	$response = '';
	stream_set_timeout($fp, 2);

	while (!feof($fp)) {
		$line = servcheck_mail_read($fp);

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
