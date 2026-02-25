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

function snmp_try($test) {
	global $config, $service_types;

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

	if (str_contains($test['hostname'], ':')) {
		$port = substr($test['hostname'], strpos($test['hostname'], ':') + 1);
	}

	if ($cred['type'] == 'snmp3') {
		$version                 = 3;
		$credential['community'] = '';
	} else {
		$credential['snmp_username']        = '';
		$credential['snmp_password']        = '';
		$credential['snmp_auth_protocol']   = '';
		$credential['snmp_priv_passphrase'] = '';
		$credential['snmp_priv_protocol']   = '';
		$credential['snmp_context']         = '';
	}

	servcheck_debug('SNMP request: ' . $test['snmp_oid']);
	servcheck_debug('SNMP options: ' . clean_up_lines(var_export($credential, true)));

	if ($test['type'] == 'snmp_get') {
		servcheck_debug('SNMP GET request, hostname ' . $test['hostname'] . ':' . $port);
		$data = cacti_snmp_get($test['hostname'], $credential['community'], $test['snmp_oid'], $version,
			$credential['snmp_username'], $credential['snmp_password'], $credential['snmp_auth_protocol'],
			$credential['snmp_priv_passphrase'], $credential['snmp_priv_protocol'], $credential['snmp_context'],
			$port, $timeout);
	} else {
		servcheck_debug('SNMP WALK request, hostname ' . $test['hostname'] . ':' . $port);
		$data = cacti_snmp_walk($test['hostname'], $credential['community'], $test['snmp_oid'], $version,
			$credential['snmp_username'], $credential['snmp_password'], $credential['snmp_auth_protocol'],
			$credential['snmp_priv_passphrase'], $credential['snmp_priv_protocol'], $credential['snmp_context'],
			$port, $timeout);

		$data = var_export($data, true);
	}

	servcheck_debug('Result: ' . clean_up_lines(var_export($data, true)));

	$results['data']   = $data;
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
			servcheck_debug('String not found');
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
