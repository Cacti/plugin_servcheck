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
