<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for plugin_servcheck_version() and plugin_servcheck_uninstall()
 * in setup.php.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	$GLOBALS['__test_db_calls'] = array();
});

it('parses the plugin INFO file into an info array', function () {
	$info = plugin_servcheck_version();

	expect($info)->toBeArray();
	expect($info)->toHaveKey('name');
	expect($info)->toHaveKey('version');
	expect($info['name'])->toBe('servcheck');
});

it('drops every servcheck table on uninstall', function () {
	plugin_servcheck_uninstall();

	$droppedTables = array_values(array_map(function ($call) {
		preg_match('/DROP TABLE IF EXISTS (\S+)/i', $call['sql'], $matches);
		return $matches[1] ?? null;
	}, array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute' && stripos($call['sql'], 'DROP TABLE') !== false;
	})));

	expect($droppedTables)->toEqualCanonicalizing(array(
		'plugin_servcheck_test',
		'plugin_servcheck_log',
		'plugin_servcheck_proxies',
		'plugin_servcheck_proxy',
		'plugin_servcheck_processes',
		'plugin_servcheck_contacts',
		'plugin_servcheck_ca',
		'plugin_servcheck_restapi_method',
		'plugin_servcheck_credential',
	));
});
