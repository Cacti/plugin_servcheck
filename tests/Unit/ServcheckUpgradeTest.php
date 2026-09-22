<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for plugin_servcheck_upgrade() in setup.php: the
 * always-run steps (realm-file update, hook re-registration, and the
 * final plugin_config version/author/webpage UPDATE) when the stored
 * version is already at or beyond the newest migration checkpoint
 * (0.4), so neither of the two large version-gated migration cascades
 * (0.3 and 0.4) execute.
 *
 * The 0.3/0.4 migration cascades themselves are intentionally NOT
 * exercised here: they perform irreversible schema changes (table
 * renames/drops, backup-table creation) and convert real credential
 * data through servcheck_encrypt_credential() (real encryption, not a
 * pure function safe to assert against in a unit test), which is a
 * much larger and riskier surface than this suite stubs.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	$GLOBALS['__test_db_calls']             = array();
	$GLOBALS['__test_registered_hooks']     = array();
	$GLOBALS['__test_db_fetch_cell_return'] = '99.0';
});

it('reports success, updates the realm file list, re-registers hooks, and updates plugin_config', function () {
	$info = plugin_servcheck_version();

	expect(plugin_servcheck_upgrade())->toBeTrue();

	$realmUpdates = array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute_prepared' && stripos($call['sql'], 'UPDATE plugin_realms') !== false;
	});

	expect($realmUpdates)->toHaveCount(1);

	$hooks = array_column($GLOBALS['__test_registered_hooks'], 'hook');

	expect($hooks)->toContain('replicate_out');
	expect($hooks)->toContain('config_settings');

	$finalUpdate = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute_prepared' && stripos($call['sql'], 'UPDATE plugin_config') !== false;
	}));

	expect($finalUpdate)->toHaveCount(1);
	expect($finalUpdate[0]['params'])->toBe(array($info['version'], $info['author'], $info['homepage']));
});

it('does not run either version-gated migration cascade once already past both checkpoints', function () {
	plugin_servcheck_upgrade();

        $migrationSideEffects = array_filter($GLOBALS['__test_db_calls'], function ($call) {
                if ($call['fn'] !== 'db_execute') {
                        return false;
                }

                return stripos($call['sql'], 'RENAME COLUMN') !== false
                        || stripos($call['sql'], 'CREATE TABLE') !== false
                        || stripos($call['sql'], 'DROP TABLE') !== false;
        });

        expect($migrationSideEffects)->toBeEmpty();
});
