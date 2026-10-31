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
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/**
 * Return the CSP nonce attribute for inline <script> tags, safely across
 * Cacti versions. Newer Cacti releases enforce a Content-Security-Policy that
 * requires a per-request nonce on parser-inserted scripts; older releases lack
 * the CactiSecureHeaders class, so this returns an empty string there.
 *
 * @return string The nonce attribute when supported, otherwise empty string.
 */
function plugin_servcheck_csp_nonce(): string {
	if (class_exists('CactiSecureHeaders')) {
		return CactiSecureHeaders::getNonceAttribute();
	}

	return '';
}

/**
 * Registers this plugin's Cacti hooks (navigation breadcrumbs, config
 * arrays, poller_bottom, data source replication, settings, page_head)
 * and its servcheck_*.php realm, then creates the plugin's database
 * tables. Invoked by the Cacti plugin framework when the plugin is
 * installed/enabled.
 *
 * @return void
 */
function plugin_servcheck_install(): void {
	api_plugin_register_hook('servcheck', 'draw_navigation_text', 'plugin_servcheck_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('servcheck', 'config_arrays',        'plugin_servcheck_config_arrays',        'setup.php');
	api_plugin_register_hook('servcheck', 'poller_bottom',        'plugin_servcheck_poller_bottom',        'setup.php');
	api_plugin_register_hook('servcheck', 'replicate_out',        'servcheck_replicate_out',               'setup.php');
	api_plugin_register_hook('servcheck', 'config_settings',      'servcheck_config_settings',             'setup.php');
	api_plugin_register_hook('servcheck', 'page_head',            'servcheck_page_head',                   'setup.php');

	api_plugin_register_realm('servcheck', 'servcheck_test.php,servcheck_credential.php,servcheck_curl_code.php,servcheck_proxy.php,servcheck_ca.php', __('Service Check Admin', 'servcheck'), 1);

	require_once(__DIR__ . '/includes/database.php');

	servcheck_setup_database();
}

/**
 * Drops all of this plugin's database tables. Invoked by the Cacti
 * plugin framework when the plugin is uninstalled.
 *
 * @return void
 */
function plugin_servcheck_uninstall(): void {
	db_execute('DROP TABLE IF EXISTS plugin_servcheck_test');
	db_execute('DROP TABLE IF EXISTS plugin_servcheck_log');
	db_execute('DROP TABLE IF EXISTS plugin_servcheck_proxies');
	db_execute('DROP TABLE IF EXISTS plugin_servcheck_proxy');
	db_execute('DROP TABLE IF EXISTS plugin_servcheck_processes');
	db_execute('DROP TABLE IF EXISTS plugin_servcheck_contacts');
	db_execute('DROP TABLE IF EXISTS plugin_servcheck_ca');
	db_execute('DROP TABLE IF EXISTS plugin_servcheck_restapi_method');
	db_execute('DROP TABLE IF EXISTS plugin_servcheck_credential');
}

/**
 * Runs any pending database schema upgrade for this plugin. Invoked by
 * plugin_servcheck_config_arrays() (the 'config_arrays' hook) only when
 * the current page is index.php, plugins.php, or servcheck_test.php -
 * not on every page load.
 *
 * @return bool Always true.
 */
function plugin_servcheck_check_config(): bool {
	// Here we will check to ensure everything is configured
	plugin_servcheck_upgrade();

	return true;
}

/**
 * Applies version-gated schema migrations for this plugin based on
 * comparing the installed version recorded in plugin_config against the
 * current INFO file version. Called from
 * plugin_servcheck_check_config(), which is itself only invoked when
 * the current page is index.php, plugins.php, or servcheck_test.php.
 *
 * @return bool True on success; false if the current schema could not be applied.
 *
 * @global array $config Cacti global configuration array (declared but
 *                       not directly used here).
 */
function plugin_servcheck_upgrade(): bool {
	global $config;

	require_once(__DIR__ . '/includes/functions.php');
	require_once(__DIR__ . '/includes/database.php');

	$info = plugin_servcheck_version();
	$new  = $info['version'];
	$old  = db_fetch_cell('SELECT version FROM plugin_config WHERE directory="servcheck"');

	// Nothing to do when the recorded version already matches the code. This
	// guard is important: plugin_servcheck_config_arrays() reaches this function
	// on every index.php/plugins.php/servcheck_test.php load, and the hook
	// re-registration below calls api_plugin_replicate_config() -> replicate_out(),
	// which fatals when core lib/poller.php is not loaded on that request.
	if ($old == $new) {
		return true;
	}

	db_execute_prepared('UPDATE plugin_realms
		SET file = ?
		WHERE file LIKE "%servcheck_test.php%"',
		['servcheck_test.php,servcheck_credential.php,servcheck_curl_code.php,servcheck_proxy.php,servcheck_ca.php']);

	// Registering the replicate_out hook makes api_plugin_register_hook() call
	// api_plugin_replicate_config() -> replicate_out(), which lives in core
	// lib/poller.php and is not otherwise loaded on these pages.
	if (!function_exists('replicate_out')) {
		if (defined('CACTI_PATH_LIBRARY') && file_exists(CACTI_PATH_LIBRARY . '/poller.php')) {
			include_once(CACTI_PATH_LIBRARY . '/poller.php');
		} elseif (isset($config['library_path']) && file_exists($config['library_path'] . '/poller.php')) {
			include_once($config['library_path'] . '/poller.php');
		}
	}

	api_plugin_register_hook('servcheck', 'replicate_out', 'servcheck_replicate_out', 'setup.php', true);
	api_plugin_register_hook('servcheck', 'config_settings', 'servcheck_config_settings', 'setup.php', true);

	// Register page_head here as well so installs that predate this hook pick it
	// up on upgrade and load the plugin's stylesheets.
	api_plugin_register_hook('servcheck', 'page_head', 'servcheck_page_head', 'setup.php');

	if (!servcheck_upgrade_tables((string) $old)) {
		return false;
	}

	// Set the new version
	db_execute_prepared("UPDATE plugin_config
		SET version = ?, author = ?, webpage = ?
		WHERE directory = 'servcheck'",
		[$info['version'], $info['author'], $info['homepage']]
	);

	servcheck_prune_files();

	return true;
}

/**
 * Reads this plugin's version/author metadata from its INFO file.
 * Invoked by the Cacti plugin framework to display plugin information,
 * and called from plugin_servcheck_upgrade() and
 * poller_servcheck.php's/servcheck_process.php's display_version().
 *
 * @return array The plugin's INFO file 'info' section (name, version,
 *               author, etc.).
 *
 * @global array $config Cacti global configuration array; used to
 *                       locate the plugin's INFO file.
 */
function plugin_servcheck_version(): array {
	global $config;
	$info = parse_ini_file($config['base_path'] . '/plugins/servcheck/INFO', true);

	if (!is_array($info) || !isset($info['info']) || !is_array($info['info'])) {
		return [];
	}

	return $info['info'];
}

/**
 * Compatibility entry point for table creation in includes/database.php. Called
 * from plugin_servcheck_install() during plugin installation.
 *
 * @return void
 */
function plugin_servcheck_setup_table(): void {
	require_once(__DIR__ . '/includes/database.php');

	servcheck_setup_database();
}

/**
 * Launches a background poller_servcheck.php process to run the
 * configured service checks. Invoked by the Cacti plugin framework via
 * the 'poller_bottom' hook at the end of each poller cycle.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to
 *                       resolve the PHP binary and this plugin's poller
 *                       script path.
 */
function plugin_servcheck_poller_bottom(): void {
	global $config;

	require_once($config['library_path'] . '/database.php');

	if (!function_exists('exec_background')) {
		include_once($config['library_path'] . '/poller.php');
	}

	$command_string = trim(read_config_option('path_php_binary'));

	// If its not set, just assume its in the path
	if (trim($command_string) == '') {
		$command_string = 'php';
	}
	$extra_args = ' -q ' . $config['base_path'] . '/plugins/servcheck/poller_servcheck.php';

	exec_background($command_string, $extra_args);
}

/**
 * Adds this plugin's 'Service Checker' entry to the Management menu, and
 * triggers a schema-upgrade check when the currently displayed page is
 * one of this plugin's own pages (or the main index/plugins pages).
 * Invoked by the Cacti plugin framework via the 'config_arrays' hook.
 *
 * @return void
 *
 * @global array $menu                       Cacti's registered admin
 *                                           menu; a 'Service Checker'
 *                                           entry is added under
 *                                           'Management'.
 * @global array $user_auth_realms           Reserved/declared for parity
 *                                           with other hook
 *                                           implementations; not used
 *                                           directly here.
 * @global array $user_auth_realm_filenames  Reserved/declared for parity
 *                                           with other hook
 *                                           implementations; not used
 *                                           directly here.
 */
function plugin_servcheck_config_arrays(): void {
	global $menu, $user_auth_realms, $user_auth_realm_filenames;

	$menu[__('Management')]['plugins/servcheck/servcheck_test.php'] = __('Service Checker', 'servcheck');

	$files = ['index.php', 'plugins.php', 'servcheck_test.php'];

	if (in_array(get_current_page(), $files, true)) {
		plugin_servcheck_check_config();
	}
}

/**
 * Adds this plugin's page breadcrumb/navigation entries (service check,
 * REST API, credential, web proxy, and CA list/edit/save views).
 * Invoked by the Cacti plugin framework via the 'draw_navigation_text'
 * hook.
 *
 * @param array $nav Cacti's registered navigation text entries.
 *
 * @return array The $nav array with this plugin's entries added.
 */
function plugin_servcheck_draw_navigation_text($nav): array {
	$nav['servcheck_test.php:'] = [
		'title'   => __('Service Checks', 'servcheck'),
		'mapping' => 'index.php:',
		'url'     => 'servcheck_test.php',
		'level'   => '1'
	];

	$nav['servcheck_test.php:edit'] = [
		'title'   => __('Service Check Edit', 'servcheck'),
		'mapping' => 'index.php:',
		'url'     => 'servcheck_test.php',
		'level'   => '1'
	];

	$nav['servcheck_test.php:save'] = [
		'title'   => __('Service Check Save', 'servcheck'),
		'mapping' => 'index.php:',
		'url'     => 'servcheck_test.php',
		'level'   => '1'
	];

	$nav['servcheck_credential.php:'] = [
		'title'   => __('Credential', 'servcheck'),
		'mapping' => 'index.php:',
		'url'     => 'servcheck_credential.php',
		'level'   => '1'
	];

	$nav['servcheck_credential.php:edit'] = [
		'title'   => __('Credential Edit', 'servcheck'),
		'mapping' => 'index.php:',
		'url'     => 'servcheck_credential.php',
		'level'   => '1'
	];

	$nav['servcheck_credential.php:save'] = [
		'title'   => __('Credential Save', 'servcheck'),
		'mapping' => 'index.php:',
		'url'     => 'servcheck_credential.php',
		'level'   => '1'
	];

	$nav['servcheck_proxy.php:'] = [
		'title'   => __('Web Proxy', 'servcheck'),
		'mapping' => 'index.php:',
		'url'     => 'servcheck_proxy.php',
		'level'   => '1'
	];

	$nav['servcheck_proxy.php:edit'] = [
		'title'   => __('Web Proxy Edit', 'servcheck'),
		'mapping' => 'index.php:',
		'url'     => 'servcheck_proxy.php',
		'level'   => '1'
	];

	$nav['servcheck_proxy.php:save'] = [
		'title'   => __('Save Web Proxy', 'servcheck'),
		'mapping' => 'index.php:',
		'url'     => 'servcheck_proxy.php',
		'level'   => '1'
	];

	$nav['servcheck_ca.php:'] = [
		'title'   => __('CA', 'servcheck'),
		'mapping' => 'index.php:',
		'url'     => 'servcheck_ca.php',
		'level'   => '1'
	];

	$nav['servcheck_ca.php:edit'] = [
		'title'   => __('CA Edit', 'servcheck'),
		'mapping' => 'index.php:',
		'url'     => 'servcheck_ca.php',
		'level'   => '1'
	];

	$nav['servcheck_ca.php:save'] = [
		'title'   => __('Save CA', 'servcheck'),
		'mapping' => 'index.php:',
		'url'     => 'servcheck_ca.php',
		'level'   => '1'
	];

	return $nav;
}

/**
 * Replicates this plugin's configuration tables (proxies, tests,
 * credentials, CAs) out to a remote data collector poller. Invoked by
 * the Cacti plugin framework via the 'replicate_out' hook during data
 * collector replication.
 *
 * @param array $data Replication context, including 'remote_poller_id',
 *                    'rcnn_id' (remote connection id), and 'class'
 *                    (replication scope, e.g. 'all').
 *
 * @return array The $data array, unmodified, returned for hook chaining.
 */
function servcheck_replicate_out($data): array {
	global $config;

	if (!function_exists('replicate_out_table')) {
		include_once($config['library_path'] . '/poller.php');
	}

	$remote_poller_id = $data['remote_poller_id'];
	$rcnn_id          = $data['rcnn_id'];
	$class            = $data['class'];

	cacti_log('INFO: Replicating for the servcheck Plugin', false, 'REPLICATE');

	$tables = [
		'plugin_servcheck_proxy',
		'plugin_servcheck_test',
		'plugin_servcheck_credential',
		'plugin_servcheck_ca'
	];

	if ($class == 'all') {
		foreach ($tables as $table) {
			$tdata = db_fetch_assoc('SELECT * FROM ' . $table);
			replicate_out_table($rcnn_id, $tdata, $table, $remote_poller_id);
		}
	}

	return $data;
}

/**
 * Registers this plugin's 'Servcheck' Settings tab and its
 * configuration fields (notification preferences, command-execution
 * enablement, certificate expiry thresholds, data retention, process
 * limits). Invoked by the Cacti plugin framework via the
 * 'config_settings' hook when rendering the Settings page.
 *
 * @return void
 *
 * @global array $tabs     Cacti's registered settings tabs; a
 *                         'servcheck' entry is added.
 * @global array $settings Cacti's registered settings fields; a
 *                         'servcheck' entry is added with this plugin's
 *                         field definitions.
 */
function servcheck_config_settings(): void {
	global $tabs, $settings;

	$tabs['servcheck'] = __('Servcheck', 'servcheck');

	$settings['servcheck'] = [
		'servcheck_display_header' => [
			'friendly_name' => __('Notification Preferences', 'servcheck'),
			'method'        => 'spacer',
		],
		'servcheck_send_email_separately' => [
			'friendly_name' => __('Send Email separately for each address', 'servcheck'),
			'description'   => __('If checked, this will cause all Emails to be sent separately for each address.', 'servcheck'),
			'method'        => 'checkbox',
			'default'       => '',
		],
		'servcheck_disable_notification' => [
			'friendly_name' => __('Stop sending all notification', 'servcheck'),
			'description'   => __('If checked, servcheck will not send any emails. You can also disable notification only for specific tests', 'servcheck'),
			'method'        => 'checkbox',
			'default'       => '',
		],
		'servcheck_enable_scripts' => [
			'friendly_name' => __('Enable Command Execution', 'servcheck'),
			'description'   => __('Checking this box will enable the ability to run commands on Servcheck events. You can enable or disable it for each test.', 'servcheck'),
			'method'        => 'checkbox',
			'default'       => ''
		],
		'servcheck_change_command' => [
			'friendly_name' => __('Status Change Command', 'servcheck'),
			'description'   => __('When a basic or search or certificate expiration test returns different result, run the following command... This command must NOT include command line arguments... However, the following variables can be pulled from the environment of the script:<br>&#060SERVCHECK_TEST_NAME&#062 &#060SERVCHECK_EXTERNAL_ID&#062 &#060SERVCHECK_TEST_TYPE&#062 &#060SERVCHECK_POLLER_ID&#062 &#060SERVCHECK_RESULT&#062 &#060SERVCHECK_RESULT_SEARCH&#062 &#060SERVCHECK_CERTIFICATE_EXPIRATION&#062', 'servcheck'),
			'method'        => 'filepath',
			'file_type'     => 'binary',
			'size'          => '100',
			'max_length'    => '100',
			'default'       => ''
		],
		'servcheck_processes' => [
			'friendly_name' => __('Concurrent Check Processes', 'servcheck'),
			'description'   => __('The number of service check processes to run concurrently.  Increasing this number to 2 times the number of cores is not advised.', 'servcheck'),
			'method'        => 'drop_array',
			'default'       => 8,
			'array'         => [
				'1'  => __('1 Process', 'servcheck'),
				'2'  => __('%d Processes', 2, 'servcheck'),
				'3'  => __('%d Processes', 3, 'servcheck'),
				'4'  => __('%d Processes', 4, 'servcheck'),
				'5'  => __('%d Processes', 5, 'servcheck'),
				'6'  => __('%d Processes', 6, 'servcheck'),
				'7'  => __('%d Processes', 7, 'servcheck'),
				'8'  => __('%d Processes', 8, 'servcheck'),
				'9'  => __('%d Processes', 9, 'servcheck'),
				'10' => __('%d Processes', 10, 'servcheck'),
				'15' => __('%d Processes', 15, 'servcheck'),
				'20' => __('%d Processes', 20, 'servcheck'),
				'25' => __('%d Processes', 25, 'servcheck'),
				'30' => __('%d Processes', 30, 'servcheck')
			]
		],
		'servcheck_certificate_expiry_days' => [
			'friendly_name' => __('Certificate expiry date advanced notification email', 'servcheck'),
			'description'   => __('If SSL/TLS service certificate expiration is enabled, set how many days advanced notice period before certificate expiry date the system will send notification', 'servcheck'),
			'method'        => 'drop_array',
			'default'       => 7,
			'array'         => [
				'-1' => __('Disabled', 'servcheck'),
				'3'  => __('3 days in advance', 'servcheck'),
				'7'  => __('1 week in advance', 'servcheck'),
				'21' => __('3 weeks in advance', 'servcheck'),
				'30' => __('30 days in advance', 'servcheck'),
				'60' => __('60 days in advance', 'servcheck'),
				'90' => __('90 days in advance', 'servcheck'),
			]
		],
		'servcheck_data_retention' => [
			'friendly_name' => __('How long to keep logs', 'servcheck'),
			'description'   => __('Enter the period after which older logs will be automatically deleted.', 'servcheck'),
			'method'        => 'drop_array',
			'default'       => 8,
			'array'         => [
				'0'   => __('Never', 'servcheck'),
				'1'   => __('%d hours', 24, 'servcheck'),
				'7'   => __('%d days', 7, 'servcheck'),
				'90'  => __('%d days', 90, 'servcheck'),
				'180' => __('%d days', 180, 'servcheck'),
				'365' => __('%d year', 1, 'servcheck'),
			]
		],
		'servcheck_test_max_duration' => [
			'friendly_name' => __('Maximum test duration in seconds', 'servcheck'),
			'description'   => __('The default value for tests where runtime testing is not enabled. If enabled, the max. duration is calculated as the duration threshold + 2 seconds.', 'servcheck'),
			'method'        => 'textbox',
			'max_length'    => 2,
			'default'       => '3',
		],
		'servcheck_web_header' => [
			'friendly_name' => __('HTTP/HTTPS Check Settings', 'servcheck'),
			'method'        => 'spacer',
		],
		'servcheck_user_agent' => [
			'friendly_name' => __('User Agent', 'servcheck'),
			'description'   => __('The User-Agent string sent with HTTP/HTTPS and other cURL based service checks. Some sites block old or uncommon browsers, so you can set a current User-Agent here. Leave blank to use the built-in default.', 'servcheck'),
			'method'        => 'textbox',
			'size'          => '100',
			'max_length'    => '255',
			'default'       => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36',
		]
	];
}

/**
 * Injects this plugin's common and theme-specific stylesheets into the
 * page head. Invoked by the Cacti plugin framework via the 'page_head'
 * hook.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to
 *                       locate the theme CSS files.
 */
function servcheck_page_head(): void {
	global $config;

	$selectedTheme = get_selected_theme();

	print get_md5_include_css('plugins/servcheck/css/common.css');

	if (file_exists($config['base_path'] . '/plugins/servcheck/css/' . $selectedTheme . '.css')) {
		print get_md5_include_css('plugins/servcheck/css/' . $selectedTheme . '.css');
	}
}

/**
 * Removes files and directories that a previous version of this plugin
 * shipped but that have since moved or been deleted, using the tombstone
 * and whitelist lists in manifest.json. Whitelisted (user-data) paths and
 * any VCS metadata (.git*) are never touched; the dev-only tests/ tree is
 * removed. Any path that resolves outside the plugin directory (a tampered
 * manifest.json) is refused, and any file/directory that cannot be removed
 * (e.g. read-only) is reported to the Cacti log. Any top-level entry that is
 * neither expected nor a tombstone nor whitelisted is logged to the Cacti
 * log and left in place. Called on a plugin version change.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to resolve
 *                       the plugin directory.
 */
function servcheck_prune_files(): void {
	global $config;

	$plugin_dir    = $config['base_path'] . '/plugins/servcheck';
	$manifest_path = $plugin_dir . '/manifest.json';

	if (!is_readable($manifest_path)) {
		return;
	}

	$manifest = json_decode((string) file_get_contents($manifest_path), true);

	if (!is_array($manifest)) {
		cacti_log('WARNING: servcheck manifest.json could not be parsed; skipping file prune', false, 'SERVCHECK');

		return;
	}

	$tombstones = isset($manifest['tombstones']) && is_array($manifest['tombstones']) ? $manifest['tombstones'] : [];
	$expected   = isset($manifest['expected'])   && is_array($manifest['expected'])   ? $manifest['expected']   : [];
	$whitelist  = isset($manifest['whitelist'])  && is_array($manifest['whitelist'])  ? $manifest['whitelist']  : [];

	$protected = function (string $rel) use ($whitelist): bool {
		if (strncmp($rel, '.git', 4) === 0 || strncmp($rel, '.md', 3) === 0) {
			return true;
		}

		foreach ($whitelist as $entry) {
			$entry = trim((string) $entry, '/');

			if ($entry !== '' && ($rel === $entry
				|| strncmp($rel, $entry . '/', strlen($entry) + 1) === 0
				|| strncmp($entry, $rel . '/', strlen($rel) + 1) === 0)) {
				return true;
			}
		}

		return false;
	};

	// Security: resolve the plugin directory so a tampered manifest.json
	// cannot steer the prune outside of it.
	$plugin_real = realpath($plugin_dir);

	// Remove tombstoned (moved/deleted) paths plus the dev-only tests/
	// tree and the phpunit.xml test configuration.
	$remove   = $tombstones;
	$remove[] = 'tests/';
	$remove[] = 'phpunit.xml';

	foreach ($remove as $rel) {
		$rel = trim((string) $rel, '/');

		if ($rel === '' || $protected($rel)) {
			continue;
		}

		// A tombstone must never contain '.'/'..' segments; a tampered manifest
		// could use them to escape the plugin directory or target its root.
		$segments = explode('/', $rel);

		if (in_array('.', $segments, true) || in_array('..', $segments, true)) {
			cacti_log(sprintf('WARNING: servcheck prune refused to remove %s: path contains a traversal segment (tampered manifest.json?)', $rel), false, 'SERVCHECK');

			continue;
		}

		$path = $plugin_dir . '/' . $rel;

		if (!is_link($path) && !file_exists($path)) {
			continue;
		}

		// Refuse any path that, after resolving symlinks and ../ segments,
		// escapes the plugin directory (protects user data from a tampered
		// manifest.json).
		$anchor = is_link($path) ? dirname($path) : $path;
		$real   = realpath($anchor);

		if ($real === false || ($real !== $plugin_real && strncmp($real, $plugin_real . DIRECTORY_SEPARATOR, strlen((string) $plugin_real) + 1) !== 0)) {
			cacti_log(sprintf('WARNING: servcheck prune refused to remove %s: path resolves outside the plugin directory (tampered manifest.json?)', $rel), false, 'SERVCHECK');

			continue;
		}

		if (is_dir($path) && !is_link($path)) {
			$removed = servcheck_rmtree($path);
		} else {
			$removed = @unlink($path);
		}

		if (!$removed) {
			cacti_log(sprintf('WARNING: servcheck upgrade could not remove %s (check file/directory permissions)', $rel), false, 'SERVCHECK');
		}
	}

	// Surface any top-level entry the manifest does not account for.
	$known = [];

	foreach (array_merge($expected, $tombstones) as $entry) {
		$top = explode('/', trim((string) $entry, '/'))[0];

		if ($top !== '') {
			$known[$top] = true;
		}
	}

	$entries = scandir($plugin_dir);

	foreach (($entries !== false ? $entries : []) as $entry) {
		if ($entry === '.' || $entry === '..' || $entry === 'tests' || $entry === 'phpunit.xml' || $protected($entry) || isset($known[$entry])) {
			continue;
		}

		cacti_log(sprintf('WARNING: servcheck upgrade found a file/directory not described in manifest.json: %s (left in place)', $entry), false, 'SERVCHECK');
	}
}

/**
 * Recursively deletes a directory and its contents. Symlinks are removed
 * without being followed. Helper for servcheck_prune_files().
 *
 * @param string $dir Absolute path to the directory to remove.
 *
 * @return bool True if the directory and everything under it was removed;
 *              false if any entry could not be deleted.
 */
function servcheck_rmtree(string $dir): bool {
	$entries = scandir($dir);
	$ok      = true;

	foreach (($entries !== false ? $entries : []) as $entry) {
		if ($entry === '.' || $entry === '..') {
			continue;
		}

		$path = $dir . '/' . $entry;

		if (is_dir($path) && !is_link($path)) {
			if (!servcheck_rmtree($path)) {
				$ok = false;
			}
		} elseif (!@unlink($path)) {
			$ok = false;
		}
	}

	if (!@rmdir($dir)) {
		$ok = false;
	}

	return $ok;
}
