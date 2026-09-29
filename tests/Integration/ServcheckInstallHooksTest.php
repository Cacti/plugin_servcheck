<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Integration coverage for plugin_servcheck_install(): verifies every
 * hook and the realm the plugin depends on at runtime are actually
 * registered, together with its full table set, in a single end-to-end
 * pass.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	$GLOBALS['__test_db_calls']          = array();
	$GLOBALS['__test_registered_hooks']  = array();
	$GLOBALS['__test_registered_realms'] = array();
});

it('registers every hook servcheck depends on, its realm, and provisions its tables', function () {
	plugin_servcheck_install();

	$hooks = array();
	foreach ($GLOBALS['__test_registered_hooks'] as $registered) {
		$hooks[$registered['hook']] = $registered;
	}

	$expectedHooks = array(
		'draw_navigation_text' => 'plugin_servcheck_draw_navigation_text',
		'config_arrays'        => 'plugin_servcheck_config_arrays',
		'poller_bottom'        => 'plugin_servcheck_poller_bottom',
		'replicate_out'        => 'servcheck_replicate_out',
		'config_settings'      => 'servcheck_config_settings',
		'page_head'            => 'servcheck_page_head',
	);

	foreach ($expectedHooks as $expected => $expectedFunction) {
		expect($hooks)->toHaveKey($expected);
		expect($hooks[$expected]['plugin'])->toBe('servcheck');
		expect($hooks[$expected]['function'])->toBe($expectedFunction);
		expect($hooks[$expected]['file'])->toBe('setup.php');
	}

	expect($GLOBALS['__test_registered_realms'])->toHaveCount(1);
	expect($GLOBALS['__test_registered_realms'][0]['file'])->toBe('servcheck_test.php,servcheck_restapi.php,servcheck_credential.php,servcheck_curl_code.php,servcheck_proxy.php,servcheck_ca.php');

	$createdTables = array_values(array_unique(array_map(function ($call) {
		return $call['table'];
	}, array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'api_plugin_db_table_create';
	}))));

	expect($createdTables)->toEqualCanonicalizing(array(
		'plugin_servcheck_credential',
		'plugin_servcheck_test',
		'plugin_servcheck_log',
		'plugin_servcheck_proxy',
		'plugin_servcheck_ca',
	));
});
