#!/usr/bin/env php
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

error_reporting(0);

if (!isset($called_by_script_server)) {
	include_once(__DIR__ . '/../include/cli_check.php');

	array_shift($_SERVER['argv']);

	print call_user_func_array('ss_servcheck', $_SERVER['argv']);
}

/**
 * Cacti script-server entry point for this plugin's data query,
 * implementing the standard 'index'/'query'/'get' command protocol used
 * by Cacti's data input methods: 'index' lists every service check
 * test id, 'query' resolves a field name (e.g. 'servcheck_id',
 * 'servcheck_name') to id!value pairs for the data query editor, and
 * 'get' retrieves a specific field's value for a single test. Invoked
 * either directly from the CLI (via call_user_func_array() against
 * $_SERVER['argv']) or by Cacti's script server process
 * ($called_by_script_server) for each configured data query.
 *
 * @param string $cmd  The script-server command to run: 'index',
 *                     'query', or 'get'; defaults to 'index'.
 * @param string $arg1 For 'query'/'get', the field name being resolved;
 *                     defaults to ''.
 * @param string $arg2 For 'get', the test id to retrieve the field value
 *                     for; defaults to ''.
 *
 * @return mixed Prints the command's result directly (one line per
 *               record for 'index'/'query', a single value for 'get')
 *               and returns null.
 */
function ss_servcheck(string $cmd = 'index', string $arg1 = '', string $arg2 = '') : mixed {
	if ($cmd == 'index') {
		if (db_table_exists('plugin_servcheck_test')) {
			$exports = db_fetch_assoc('SELECT id FROM plugin_servcheck_test ORDER BY id');

			if (cacti_sizeof($exports)) {
				foreach ($exports as $export) {
					print $export['id'] . PHP_EOL;
				}
			}
		}
	} elseif ($cmd == 'query') {
		$arg = $arg1;

		if (db_table_exists('plugin_servcheck_test')) {
			if ($arg == 'servcheck_id') {
				$arr = db_fetch_assoc('SELECT id FROM plugin_servcheck_test ORDER BY id');

				if (cacti_sizeof($arr)) {
					foreach ($arr as $item) {
						print $item['id'] . '!' . $item['id'] . PHP_EOL;
					}
				}
			} elseif ($arg == 'servcheck_name') {
				$arr = db_fetch_assoc('SELECT id, name FROM plugin_servcheck_test ORDER BY id');

				if (cacti_sizeof($arr)) {
					foreach ($arr as $item) {
						print $item['id'] . '!' . $item['name'] . PHP_EOL;
					}
				}
			}
		}
	} elseif ($cmd == 'get') {
		$arg   = $arg1;
		$index = $arg2;
		$value = '0';

		if (db_table_exists('plugin_servcheck_test')) {
			switch($arg) {
				case 'attempt':
					$value = db_fetch_cell_prepared('SELECT last_attempt
						FROM plugin_servcheck_test
						WHERE id = ?',
						[$index]);

					break;
				case 'duration':
					$value = db_fetch_cell_prepared('SELECT last_duration
						FROM plugin_servcheck_test
						WHERE id = ?',
						[$index]);

					break;
				case 'result':
					$value = db_fetch_cell_prepared('SELECT last_result
						FROM plugin_servcheck_test
						WHERE id = ?',
						[$index]);

					$value = (int) ($value === 'ok');

					break;
			}
		}

		return (empty($value) ? '0' : $value);
	}

	return false;
}
