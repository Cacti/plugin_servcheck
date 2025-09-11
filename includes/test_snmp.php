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


function snmp_try ($test) {
	global $config, $service_types;

	include_once($config['base_path'] . '/lib/snmp.php');

	$version = 2;
	$port = 161;

	// default result
	$results['result'] = 'error';
	$results['curl'] = false;
	$results['time'] = time();
	$results['error'] = '';
	$results['curl_return'] = 'N/A';
	$results['start'] = microtime(true);

	list($category,$service) = explode('_', $test['type']);

	$results['result_search'] = 'not tested';

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
	} else {
		plugin_servcheck_debug('No credential set!' , $test);
		cacti_log('No credential set');
		$results['result'] = 'error';
		$results['error'] = 'No credential set';
		return $results;
	}

	if (str_contains($test['hostname'], ':')) {
		$port = substr($test['hostname'], strpos($test['hostname'], ':') + 1);
	}

	if ($cred['type'] == 'snmp3') {
		$version = 3;
		$credential['community'] = '';
	} else {
		$credential['snmp_username'] = '';
		$credential['snmp_password'] = '';
		$credential['snmp_auth_protocol'] = '';
		$credential['snmp_priv_passphrase'] = '';
		$credential['snmp_priv_protocol'] = '';
		$credential['snmp_context'] = '';
	}

	plugin_servcheck_debug('SNMP request: ' . $test['snmp_oid']);
	plugin_servcheck_debug('SNMP options: ' . clean_up_lines(var_export($credential, true)));

	if ($test['type'] == 'snmp_get') {
		plugin_servcheck_debug('SNMP GET request, hostname ' . $test['hostname'] . ':' . $port);
		$data = cacti_snmp_get($test['hostname'], $credential['community'], $test['snmp_oid'], $version,
		$credential['snmp_username'], $credential['snmp_password'], $credential['snmp_auth_protocol'],
		$credential['snmp_priv_passphrase'], $credential['snmp_priv_protocol'], $credential['snmp_context'], $port);
	} else {
		plugin_servcheck_debug('SNMP WALK request, hostname ' . $test['hostname'] . ':' . $port);
		$data = cacti_snmp_walk($test['hostname'], $credential['community'], $test['snmp_oid'], $version,
		$credential['snmp_username'], $credential['snmp_password'], $credential['snmp_auth_protocol'],
		$credential['snmp_priv_passphrase'], $credential['snmp_priv_protocol'], $credential['snmp_context'], $port);

		$data = var_export($data, true);
	}

	plugin_servcheck_debug('Result: ' . clean_up_lines(var_export($data, true)));

	$results['data'] = $data;
	$results['result'] = 'ok';
	$results['error'] = 'Some data returned';

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


//!!pm smazat
$results['return'] = 'NEVER!';

	return $results;
}

