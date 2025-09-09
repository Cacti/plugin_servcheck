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

// for ssh command test, scp and sftp

function ssh_try ($test) {
	global $config, $service_types_ports;

	// default result
	$results['result'] = 'failed';
	$results['curl'] = false;
	$results['time'] = time();
	$results['error'] = '';
	$results['result_search'] = 'not tested';
	$results['start'] = microtime(true);

	list($category,$service) = explode('_', $test['type']);

	if (empty($test['hostname'])) {
		cacti_log('Empty hostname, nothing to test');
		$results['result'] = 'error';
		$results['error'] = 'Empty hostname';
		return $results;
	}

	list($category,$service) = explode('_', $test['type']);

	if (strpos($test['hostname'], ':') === 0) {
		$test['hostname'] .=  ':' . $service_types_ports[$test['type']];
	}

	plugin_servcheck_debug('Final address is ' . $test['hostname'] , $test);

	plugin_servcheck_debug('Creating ssh connection' , $test);

	if ($service == 'command') {
		$ssh = new \phpseclib3\Net\SSH2($test['hostname']);
		$ssh->enableQuietMode();
	} else {
		$ssh = new \phpseclib3\Net\SFTP($test['hostname']);
		$ssh->enableQuietMode();
	}

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
			
			if ($cred['type'] != 'userpass' && $cred['type'] != 'sshkey') {
				plugin_servcheck_debug('Incorrect credential type, use user/pass or sshkey' , $test);
				cacti_log('Incorrect credential type, use user/pass or sshkey');
				$results['result'] = 'error';
				$results['error'] = 'Incorrect credential type';
				return $results;
			}
		}
	} else {
		plugin_servcheck_debug('Credential is not set!' , $test);
		cacti_log('Credential is not set');
		$results['result'] = 'error';
		$results['error'] = 'Credential is not set';
		return $results;
	}


	if ($cred['type'] == 'sshkey') {
		plugin_servcheck_debug('Preparing ssh private key file' , $test);

		$keyfilename = $config['base_path'] . '/plugins/servcheck/tmp_data/sshkey_' . $cred['id'];
		$keyfile = fopen($keyfilename, 'w+');
		if ($keyfile) {
			fwrite ($keyfile, $credential['sshkey']);
			fclose($keyfile);

			if (isset($credential['sshkey_passphrase'])) {
				$key = \phpseclib3\Crypt\PublicKeyLoader::load(file_get_contents($keyfilename),$credential['sshkey_passphrase']);
			} else {
				$key = \phpseclib3\Crypt\PublicKeyLoader::load(file_get_contents($keyfilename));
			}

			if (!$ssh->login($credential['ssh_username'], $key)) {
				plugin_servcheck_debug('Connection failed', $test);

				$errors = $ssh->getStdError();
				plugin_servcheck_debug('Error: ' . clean_up_lines(var_export($errors, true)));

				$results['result'] = 'error';
				$results['error'] = 'Connection failed';
				return $results;
			}
			
		} else {
			cacti_log('Cannot create private key file ' . $keyfilename);
			$results['result'] = 'error';
			$results['error'] = 'Cannot create private key file';
			return $results;
		}
	} elseif ($cred['type'] == 'userpass') {
		if (!$ssh->login($credential['username'], $credential['password'])) {
			plugin_servcheck_debug('Connection failed', $test);

			$errors = $ssh->getStdError();
			plugin_servcheck_debug('Error: ' . clean_up_lines(var_export($errors, true)));

			$results['result'] = 'error';
			$results['error'] = 'Connection failed';
			return $results;
		}
	}

	plugin_servcheck_debug('Connected, running command ' . $test['ssh_command'], $test);

	if ($service == 'command') {
		$data = $ssh->exec($test['ssh_command']);
	} else {
		$data = $ssh->nlist($test['path']);
	}

	$errors = $ssh->getStdError();
	plugin_servcheck_debug('Error: ' . clean_up_lines(var_export($errors, true)));

	plugin_servcheck_debug('Data: ' . clean_up_lines(var_export($data, true)));

	$results['data'] = $data;

	if (isset($key_filename)) {
		unlink ($key_filename);
		plugin_servcheck_debug('Removing private key file');
	}

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

	return $results;
}


