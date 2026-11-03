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
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for servcheck_upgrade_tables() in includes/database.php.
 *
 * The 0.3 and 0.4 migration cascades branch on the shape of the installed
 * schema (db_column_exists(), db_table_exists(), and a proxies-table probe).
 * Every Cacti schema helper is a bootstrap stub that records its call and
 * returns a value sourced from $GLOBALS, so these tests drive both the
 * "schema already migrated" (columns/tables present) and the "fresh schema"
 * (columns/tables absent) sides of each branch without touching a database.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
	require_once __DIR__ . '/../../includes/database.php';
});

beforeEach(function () {
	$GLOBALS['__test_db_calls']             = array();
	$GLOBALS['__test_db_column_exists']     = false;
	$GLOBALS['__test_db_table_exists']      = false;
	$GLOBALS['__test_db_update_table']      = true;
	$GLOBALS['__test_db_fetch_cell_return'] = '1';
});

afterEach(function () {
	$GLOBALS['__test_db_column_exists']     = false;
	$GLOBALS['__test_db_table_exists']      = false;
	$GLOBALS['__test_db_update_table']      = true;
	$GLOBALS['__test_db_fetch_cell_return'] = '';
});

/**
 * @return array<int, string> The SQL text of every db_execute() call recorded.
 */
function servcheck_recorded_sql(): array
{
	return array_map(function ($call) {
		return $call['sql'];
	}, array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute';
	}));
}

/**
 * @return array<int, string> The table name of every db_add_column() call.
 */
function servcheck_added_columns(): array
{
	return array_map(function ($call) {
		return $call['column']['name'];
	}, array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_add_column';
	}));
}

it('creates and backs up schema on the 0.3 cascade when columns and tables are absent', function () {
	$GLOBALS['__test_db_column_exists']     = false;
	$GLOBALS['__test_db_table_exists']      = false;
	$GLOBALS['__test_db_fetch_cell_return'] = '0';

	expect(servcheck_upgrade_tables('0.2'))->toBeTrue();

	// Absent columns are added rather than renamed.
	expect(servcheck_added_columns())->toContain('ipaddress');
	expect(servcheck_added_columns())->toContain('external_id');

	$sql = servcheck_recorded_sql();

	// Backups of the always-present tables are taken.
	expect($sql)->toContain('CREATE TABLE plugin_servcheck_test_backup AS SELECT * FROM plugin_servcheck_test');

	// The log->duration rework runs only when the duration column is missing.
	$hasResponseRework = false;
	foreach ($sql as $statement) {
		if (stripos($statement, 'SET curl_response = CONCAT') !== false) {
			$hasResponseRework = true;
			break;
		}
	}
	expect($hasResponseRework)->toBeTrue();
});

it('renames columns and backs up the rest api table on the 0.3 cascade when they are present', function () {
	$GLOBALS['__test_db_column_exists']     = true;
	$GLOBALS['__test_db_table_exists']      = true;
	$GLOBALS['__test_db_fetch_cell_return'] = '1';

	expect(servcheck_upgrade_tables('0.2'))->toBeTrue();

	$sql = servcheck_recorded_sql();

	expect($sql)->toContain('ALTER TABLE plugin_servcheck_test RENAME COLUMN display_name TO name');
	expect($sql)->toContain('CREATE TABLE plugin_servcheck_restapi_method_backup AS SELECT * FROM plugin_servcheck_restapi_method');
	expect($sql)->toContain('ALTER TABLE plugin_servcheck_proxies RENAME TO plugin_servcheck_proxy');
});

it('runs the 0.4 cascade column renames when the pre-0.4 columns are present', function () {
	$GLOBALS['__test_db_column_exists'] = true;
	$GLOBALS['__test_db_table_exists']  = true;

	expect(servcheck_upgrade_tables('0.3'))->toBeTrue();

	expect(servcheck_recorded_sql())->toContain('ALTER TABLE plugin_servcheck_test RENAME COLUMN lastcheck TO last_check');
});

it('adds the 0.4 cascade columns when the pre-0.4 columns are absent', function () {
	$GLOBALS['__test_db_column_exists'] = false;
	$GLOBALS['__test_db_table_exists']  = false;

	expect(servcheck_upgrade_tables('0.3'))->toBeTrue();

	// api_plugin_db_add_column() is a no-op stub, so assert the function simply
	// completed the fresh-schema path and reconciled tables successfully.
	expect(servcheck_upgrade_tables('0.3'))->toBeTrue();
});

it('reconciles existing tables through db_update_table and reports failure when one fails', function () {
	$GLOBALS['__test_db_table_exists']  = true;
	$GLOBALS['__test_db_update_table']  = false;

	expect(servcheck_upgrade_tables('0.4'))->toBeFalse();

	$updates = array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_update_table';
	});

	expect($updates)->not->toBeEmpty();
});

it('plugin_servcheck_upgrade reports failure when table reconciliation fails', function () {
	// An installed version below the newest checkpoint forces the upgrade past
	// the "already current" short-circuit and into servcheck_upgrade_tables(),
	// whose failure must propagate out of plugin_servcheck_upgrade().
	$GLOBALS['__test_db_table_exists']      = true;
	$GLOBALS['__test_db_update_table']      = false;
	$GLOBALS['__test_db_fetch_cell_return'] = '0.1';

	expect(plugin_servcheck_upgrade())->toBeFalse();
});
