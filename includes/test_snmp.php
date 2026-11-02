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
 * Runs an SNMP service check test: queries the test's configured OID
 * using its associated credential (SNMPv1/v2 community or SNMPv3
 * security parameters), and evaluates the returned value against the
 * expected/maintenance/failure search patterns. Called from
 * servcheck_run_test() for tests of type 'snmp'.
 *
 * @param array $test The plugin_servcheck_test row describing the check
 *                    to run.
 *
 * @return array The check result: 'result' ('ok'/'error'), 'curl'
 *               (false), 'time', 'error', 'start', and 'result_search'.
 *
 * @global array $config        Cacti global configuration array; used to
 *                              locate lib/snmp.php.
 * @global array $service_types_ports Default port numbers per service
 *                                    type, used when the test's
 *                                    hostname doesn't specify one.
 */
function snmp_try(array $test): array {
	global $config, $service_types_ports, $snmp_error;

	require_once(__DIR__ . '/functions.php');

	include_once($config['base_path'] . '/lib/snmp.php');

	$version = 2;
	$port    = 161;
	$timeout = ($test['duration_trigger'] > 0 ? ($test['duration_trigger'] + 2) : read_config_option('servcheck_test_max_duration')) * 1000;

	// default result
	$results['result'] = 'error';
	$results['curl']   = false;
	$results['time']   = time();
	$results['error']  = '';
	$results['start']  = microtime(true);
	$results['data'] = '';
	$results['result_search'] = 'not tested';

	if (empty($test['hostname'])) {
		cacti_log('Empty hostname, nothing to test');
		$results['result'] = 'error';
		$results['error']  = 'Empty hostname';

		return $results;
	}

	[$category,$service] = explode('_', $test['type']);

	$results['result_search'] = 'not tested';

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
			$debug_credential = $credential;

			if (empty($credential)) {
				servcheck_debug('Credential is empty!');
				cacti_log('Credential is empty');
				$results['result'] = 'error';
				$results['error']  = 'Credential is empty';

				return $results;
			}
		}
	} else {
		servcheck_debug('No credential set!');
		cacti_log('No credential set');
		$results['result'] = 'error';
		$results['error']  = 'No credential set';

		return $results;
	}

	$parsed = parse_url('//' . $test['hostname']);

	$port = $parsed['port'] ?? $service_types_ports[$test['type']];
	$test['hostname'] = $parsed['host'] ?? $test['hostname'];

	if (($cred['type'] ?? '') == 'snmp3') {
		$version                 = 3;
		$credential['community'] = '';
		$debug_credential['snmp_username'] = mask_string($credential['snmp_username']);
		$debug_credential['snmp_password'] = mask_string($credential['snmp_password'], true);
		$debug_credential['snmp_priv_passphrase'] = mask_string($credential['snmp_priv_passphrase'], true);
	} else {
		$credential['snmp_username']        = '';
		$credential['snmp_password']        = '';
		$credential['snmp_auth_protocol']   = '';
		$credential['snmp_priv_passphrase'] = '';
		$credential['snmp_priv_protocol']   = '';
		$credential['snmp_context']         = '';
		$debug_credential['community'] = mask_string($credential['community']);
	}

	servcheck_debug('SNMP request: ' . $test['snmp_oid']);
	servcheck_debug('SNMP options: ' . clean_up_lines(var_export($debug_credential, true)));

	$snmp_error = '';

	try {
		$data = servcheck_network_call(function () use ($test, $credential, $version, $port, $timeout) {
			if ($test['type'] == 'snmp_get') {

				servcheck_debug('SNMP GET request, hostname ' . $test['hostname'] . ':' . $port);

				return cacti_snmp_get($test['hostname'], $credential['community'], $test['snmp_oid'], $version,
					$credential['snmp_username'], $credential['snmp_password'], $credential['snmp_auth_protocol'],
					$credential['snmp_priv_passphrase'], $credential['snmp_priv_protocol'], $credential['snmp_context'],
					$port, $timeout);
			} else {

				servcheck_debug('SNMP WALK request, hostname ' . $test['hostname'] . ':' . $port);

				return cacti_snmp_walk($test['hostname'], $credential['community'], $test['snmp_oid'], $version,
					$credential['snmp_username'], $credential['snmp_password'], $credential['snmp_auth_protocol'],
					$credential['snmp_priv_passphrase'], $credential['snmp_priv_protocol'], $credential['snmp_context'],
					$port, $timeout);

			}

		}, ['snmpget', 'snmp2_get', 'snmp3_get', 'snmpwalk', 'snmprealwalk',
			'snmp2_real_walk', 'snmp3_real_walk', 'SNMP::get', 'SNMP::walk'], $warning);
	} catch (\Exception | \ValueError $e) {
		$results['error'] = $e->getMessage();

		return $results;
	}

	if ($data === false || $data === null || $data === '' || $data === 'U'
		|| (is_array($data) && $data === []) || $snmp_error !== '' || $warning !== '') {
		$results['error'] = $snmp_error ?: $warning ?: 'SNMP request failed or no response received';

		return $results;
	}

	$data = is_array($data) ? var_export($data, true) : (string) $data;

	servcheck_debug('Result: ' . clean_up_lines(var_export($data, true)));

	$results['data']   = $data;
	$results['result'] = 'ok';
	$results['error']  = 'Some data returned';

	return servcheck_evaluate_search($test, $data, $results);
}
