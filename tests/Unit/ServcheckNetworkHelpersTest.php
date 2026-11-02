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
 | about.php and/or the AUTHORS file for specific developer information.    |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for the shared service-check helpers in includes/functions.php:
 * servcheck_network_call(), servcheck_load_credential() and
 * servcheck_evaluate_search(). These are extracted so every service-check
 * runner captures expected network warnings, validates its stored credential,
 * and evaluates the test's search strings in exactly the same way.
 */

beforeAll(function () {
	// Publishes SERVCHECK_CIPHER and defines the helpers under test.
	servcheck_test_load(__DIR__ . '/../../includes/arrays.php');
	servcheck_test_load(__DIR__ . '/../../includes/functions.php');
});

afterEach(function () {
	$GLOBALS['__test_db_fetch_row_prepared_return']  = array();
	$GLOBALS['__test_db_fetch_cell_prepared_return'] = '';
	$GLOBALS['__test_user_settings']                 = array();
	$GLOBALS['service_types_ports']                  = array();
});

// ---------------------------------------------------------------------------
// servcheck_network_call()
// ---------------------------------------------------------------------------

it('returns the operation result and clears the warning when none is raised', function () {
	$warning = 'preset';
	$result  = servcheck_network_call(static fn () => 42, ['fgets'], $warning);

	expect($result)->toBe(42);
	expect($warning)->toBe('');
});

it('captures an expected warning raised by a listed function', function () {
	$warning = '';
	servcheck_network_call(static fn () => preg_match('/(/', 'x'), ['preg_match'], $warning);

	expect($warning)->toContain('preg_match():');
});

it('passes an unlisted warning through to the previous handler', function () {
	$seen = '';

	set_error_handler(static function ($severity, $message) use (&$seen) {
		$seen = $message;

		return true;
	});

	$warning = '';
	servcheck_network_call(static fn () => preg_match('/(/', 'x'), ['fgets'], $warning);

	restore_error_handler();

	expect($warning)->toBe('');
	expect($seen)->toContain('preg_match():');
});

// ---------------------------------------------------------------------------
// servcheck_load_credential()
// ---------------------------------------------------------------------------

it('succeeds with nulls when no credential is configured', function () {
	$cred = $credential = $error = 'unset';

	$ok = servcheck_load_credential(['cred_id' => 0], $cred, $credential, $error);

	expect($ok)->toBeTrue();
	expect($cred)->toBeNull();
	expect($credential)->toBeNull();
	expect($error)->toBe('');
});

it('fails when the configured credential row is missing', function () {
	$GLOBALS['__test_db_fetch_row_prepared_return'] = array();

	$cred = $credential = $error = null;
	$ok   = servcheck_load_credential(['cred_id' => 5], $cred, $credential, $error);

	expect($ok)->toBeFalse();
	expect($error)->toBe('Credential not found');
});

it('fails when the stored credential cannot be decrypted', function () {
	$GLOBALS['__test_db_fetch_row_prepared_return'] = ['id' => 5, 'type' => 'userpass'];
	$GLOBALS['__test_user_settings']                = array(); // no key -> decrypt returns false

	$cred = $credential = $error = null;
	$ok   = servcheck_load_credential(['cred_id' => 5], $cred, $credential, $error);

	expect($ok)->toBeFalse();
	expect($error)->toBe('Credential is empty');
});

it('loads and decrypts a configured credential', function () {
	$key = random_bytes(32);
	$GLOBALS['__test_user_settings']['servcheck_key'] = base64_encode($key);
	$GLOBALS['__test_db_fetch_row_prepared_return']   = ['id' => 5, 'type' => 'userpass'];

	$iv         = random_bytes((int) openssl_cipher_iv_length(SERVCHECK_CIPHER));
	$ciphertext = openssl_encrypt(
		(string) json_encode(['username' => 'u', 'password' => 'p']),
		SERVCHECK_CIPHER,
		$key,
		OPENSSL_RAW_DATA,
		$iv
	);
	$GLOBALS['__test_db_fetch_cell_prepared_return'] = base64_encode($iv . $ciphertext);

	$cred = $credential = $error = null;
	$ok   = servcheck_load_credential(['cred_id' => 5], $cred, $credential, $error);

	expect($ok)->toBeTrue();
	expect($error)->toBe('');
	expect($credential)->toBe(['username' => 'u', 'password' => 'p']);
});

// ---------------------------------------------------------------------------
// servcheck_require_hostname()
// ---------------------------------------------------------------------------

it('accepts a test that has a hostname', function () {
	$results = ['result' => 'ok'];

	expect(servcheck_require_hostname(['hostname' => 'example.com'], $results))->toBeTrue();
	expect($results['result'])->toBe('ok');
});

it('rejects a test without a hostname', function () {
	$results = [];

	$ok = servcheck_require_hostname(['hostname' => ''], $results);

	expect($ok)->toBeFalse();
	expect($results['result'])->toBe('error');
	expect($results['error'])->toBe('Empty hostname');
});

// ---------------------------------------------------------------------------
// servcheck_curl_base_options()
// ---------------------------------------------------------------------------

it('builds cURL base options with a per-test timeout', function () {
	$options = servcheck_curl_base_options(['duration_trigger' => 5], 'Servcheck/1.0', '/tmp/ca.pem');

	expect($options[CURLOPT_USERAGENT])->toBe('Servcheck/1.0');
	expect($options[CURLOPT_CAINFO])->toBe('/tmp/ca.pem');
	expect($options[CURLOPT_TIMEOUT])->toBe(7);
	expect($options[CURLOPT_FOLLOWLOCATION])->toBeTrue();
});

it('falls back to the configured maximum when no per-test timeout is set', function () {
	$options = servcheck_curl_base_options(['duration_trigger' => 0], 'Servcheck/1.0', '/tmp/ca.pem');

	expect($options)->toHaveKey(CURLOPT_HEADER);
	expect($options)->toHaveKey(CURLOPT_TIMEOUT);
});

// ---------------------------------------------------------------------------
// servcheck_prepare_test()
// ---------------------------------------------------------------------------

it('prepares a test and appends the default port when absent', function () {
	$GLOBALS['service_types_ports'] = ['mail_smtp' => '25'];

	$test    = ['type' => 'mail_smtp', 'hostname' => 'example.com', 'cred_id' => 0];
	$results = [];
	$cred    = $credential = null;

	$ok = servcheck_prepare_test($test, $results, $cred, $credential);

	expect($ok)->toBeTrue();
	expect($test['hostname'])->toBe('example.com:25');
});

it('keeps an explicit port when preparing a test', function () {
	$GLOBALS['service_types_ports'] = ['mail_smtp' => '25'];

	$test    = ['type' => 'mail_smtp', 'hostname' => 'example.com:587', 'cred_id' => 0];
	$results = [];
	$cred    = $credential = null;

	$ok = servcheck_prepare_test($test, $results, $cred, $credential);

	expect($ok)->toBeTrue();
	expect($test['hostname'])->toBe('example.com:587');
});

it('fails to prepare a test without a hostname', function () {
	$test    = ['type' => 'mail_smtp', 'hostname' => '', 'cred_id' => 0];
	$results = [];
	$cred    = $credential = null;

	expect(servcheck_prepare_test($test, $results, $cred, $credential))->toBeFalse();
	expect($results['error'])->toBe('Empty hostname');
});

it('fails to prepare a test when the credential cannot be loaded', function () {
	$GLOBALS['__test_db_fetch_row_prepared_return'] = ['id' => 5, 'type' => 'userpass'];

	$test    = ['type' => 'mail_smtp', 'hostname' => 'example.com', 'cred_id' => 5];
	$results = [];
	$cred    = $credential = null;

	expect(servcheck_prepare_test($test, $results, $cred, $credential))->toBeFalse();
	expect($results['error'])->toBe('Credential is empty');
});

// ---------------------------------------------------------------------------
// servcheck_evaluate_search()
// ---------------------------------------------------------------------------

it('evaluates the configured search strings', function (array $test, string $data, string $expected) {
	$results = servcheck_evaluate_search($test, $data, ['result_search' => 'not tested']);

	expect($results['result_search'])->toBe($expected);
})->with([
	'matched failure string'          => [['search_failed' => 'boom', 'search' => '',   'search_maint' => ''],      'xx boom xx',      'failed ok'],
	'unmatched failure falls through' => [['search_failed' => 'boom', 'search' => '',   'search_maint' => ''],      'clean output',    'not tested'],
	'matched search string'           => [['search_failed' => '',     'search' => 'ok', 'search_maint' => ''],      'all ok here',     'ok'],
	'missing search string'           => [['search_failed' => '',     'search' => 'ok', 'search_maint' => ''],      'nope',            'not ok'],
	'matched maintenance string'      => [['search_failed' => '',     'search' => '',   'search_maint' => 'maint'], 'in maint window', 'maint ok'],
	'unmatched maintenance string'    => [['search_failed' => '',     'search' => '',   'search_maint' => 'maint'], 'nothing here',    'not tested'],
	'no search strings configured'    => [['search_failed' => '',     'search' => '',   'search_maint' => ''],      'whatever',        'not tested'],
]);
