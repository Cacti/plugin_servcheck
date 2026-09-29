<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for plugin_servcheck_config_arrays(),
 * plugin_servcheck_draw_navigation_text(), servcheck_config_settings(),
 * servcheck_page_head(), and servcheck_replicate_out() in setup.php.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	$GLOBALS['__test_db_calls']         = array();
	$GLOBALS['__test_current_page']     = 'graphs.php';
	$GLOBALS['__test_db_fetch_cell_return'] = '99.0';
});

it('adds the Service Checker menu entry and skips the version check off relevant pages', function () {
	global $menu;

	$menu = array(__('Management') => array());

	plugin_servcheck_config_arrays();

	expect($menu[__('Management')])->toHaveKey('plugins/servcheck/servcheck_test.php');

	$writes = array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute_prepared';
	});

	expect($writes)->toBeEmpty();
});

it('runs the version check when on a relevant page', function () {
	global $menu;

	$menu                            = array(__('Management') => array());
	$GLOBALS['__test_current_page']  = 'servcheck_test.php';

	plugin_servcheck_config_arrays();

	$realmUpdates = array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute_prepared' && stripos($call['sql'], 'UPDATE plugin_realms') !== false;
	});

	expect($realmUpdates)->not->toBeEmpty();
});

it('adds the servcheck breadcrumb entries without disturbing existing ones', function () {
	$nav = plugin_servcheck_draw_navigation_text(array('other.php:' => array('title' => 'Other')));

	expect($nav)->toHaveKey('other.php:');
	expect($nav)->toHaveKey('servcheck_test.php:');
	expect($nav['servcheck_test.php:']['url'])->toBe('servcheck_test.php');
});

it('registers the Servcheck settings tab and its options', function () {
	global $tabs, $settings;

	$tabs     = array();
	$settings = array();

	servcheck_config_settings();

	expect($tabs['servcheck'])->toBe('Servcheck');
	expect($settings['servcheck'])->toHaveKey('servcheck_processes');
});

it('prints the common stylesheet in the page head', function () {
	ob_start();
	servcheck_page_head();
	$output = ob_get_clean();

	expect($output)->toContain('common.css');
});

it('does not replicate when the class is not "all"', function () {
	$data = array('remote_poller_id' => 1, 'rcnn_id' => 2, 'class' => 'poller');

	$result = servcheck_replicate_out($data);

	expect($result)->toBe($data);
});
