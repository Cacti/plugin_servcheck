<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for includes/arrays.php help definitions: verifies that
 * credential and test help strings are clean single-line prose with balanced
 * inline italic markup and no raw backslash-newline continuations.
 */

it('loads servcheck help arrays without raw backslash continuations or unclosed italic tags', function () {
	servcheck_test_load(__DIR__ . '/../../includes/arrays.php');

	$credHelp = $GLOBALS['servcheck_help_credential'] ?? null;
	$testHelp = $GLOBALS['servcheck_help_test'] ?? null;

	expect($credHelp)->toBeArray()->not->toBeEmpty();
	expect($testHelp)->toBeArray()->not->toBeEmpty();

	foreach (array_merge(array_values($credHelp), array_values($testHelp)) as $help) {
		expect($help)->not->toContain("\\\n");
		expect($help)->not->toContain("\t");
		expect(substr_count($help, '<i>'))->toBe(substr_count($help, '</i>'));
	}

	expect($credHelp['apikey'])->toContain('Auth is sent');
	expect($credHelp['apikey'])->toContain('Credentials in URL are not supported.');
	expect($testHelp['mail_smtptls'])->toContain('STARTTLS.');
	expect($testHelp['mqtt_mqtt'])->toContain('Connect to MQTT server');
});

it('renders bulk action confirmation markup via servcheck_render_action_confirm', function () {
	servcheck_test_load(__DIR__ . '/../../includes/functions.php');

	ob_start();
	$buttons = servcheck_render_action_confirm('Confirm action.', 'Duplicate Credentials', '<li>Cred A</li>');
	$row     = ob_get_clean();

	expect($row)->toContain('<p>Confirm action.</p>');
	expect($row)->toContain('<ul><li>Cred A</li></ul>');
	expect($buttons)->toContain("class='cactiReturnTo'");
	expect($buttons)->toContain("title='Duplicate Credentials'");
});

it('renders bulk action confirmation form and handles empty selection via servcheck_render_action_form', function () {
	servcheck_test_load(__DIR__ . '/../../includes/functions.php');

	$_SERVER['PHP_SELF'] = '/plugins/servcheck/servcheck_ca.php';

	ob_start();
	$rendered = servcheck_render_action_form(
		[1 => 'Delete'],
		1,
		[42],
		'<li>CA One</li>',
		[1 => ['Confirm delete.', 'Delete item']]
	);
	$html = ob_get_clean();

	expect($rendered)->toBeTrue();
	expect($html)->toContain('<p>Confirm delete.</p>');
	expect($html)->toContain('<ul><li>CA One</li></ul>');
	expect($html)->toContain("name='selected_items' value='" . serialize([42]) . "'");
	expect($html)->toContain("name='drp_action' value='1'");
	expect($html)->toContain("title='Delete item'");

	ob_start();
	$empty = servcheck_render_action_form([1 => 'Delete'], 1, [], '', [1 => ['Confirm delete.', 'Delete item']]);
	ob_end_clean();

	expect($empty)->toBeFalse();
});
