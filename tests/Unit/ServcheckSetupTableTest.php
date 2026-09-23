<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for plugin_servcheck_setup_table() in setup.php.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	$GLOBALS['__test_db_calls'] = array();
});

it('creates every servcheck table via the plugin table-creation API', function () {
	plugin_servcheck_setup_table();

	$createdTables = array_values(array_map(function ($call) {
		return $call['table'];
	}, array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'api_plugin_db_table_create';
	})));

	expect($createdTables)->toEqualCanonicalizing(array(
		'plugin_servcheck_credential',
		'plugin_servcheck_test',
		'plugin_servcheck_log',
		'plugin_servcheck_proxy',
		'plugin_servcheck_ca',
	));

	foreach ($GLOBALS['__test_db_calls'] as $call) {
		if ($call['fn'] === 'api_plugin_db_table_create') {
			expect($call['plugin'])->toBe('servcheck');
		}
	}
});
