<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for plugin_servcheck_poller_bottom() in setup.php.
 *
 * It include_once()s $config['library_path'] . '/database.php', so that is
 * pointed at a throwaway empty stub file for the duration of this test:
 * library_path (unlike base_path/lib) is fully test-controlled.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';

	$stubLibraryPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'servcheck-test-lib-stub';

	if (!is_dir($stubLibraryPath)) {
		mkdir($stubLibraryPath, 0777, true);
	}

	file_put_contents($stubLibraryPath . '/database.php', "<?php\n");

	$GLOBALS['config']['library_path'] = $stubLibraryPath;
});

beforeEach(function () {
	$GLOBALS['__test_db_calls'] = array();
});

it('dispatches the background poller with the php binary from config', function () {
	plugin_servcheck_poller_bottom();

	$execCalls = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'exec_background';
	}));

	expect($execCalls)->toHaveCount(1);
	expect($execCalls[0]['command'])->toBe('php');
	expect($execCalls[0]['args'])->toContain('poller_servcheck.php');
});
