# GitHub Copilot Instructions

## Priority Guidelines

When generating code for this repository:

1. **Version Compatibility**: This is a Cacti plugin (version 0.3) requiring Cacti 1.2.24+ compatibility
2. **Context Files**: Prioritize patterns and standards defined in this file (`.github/copilot-instructions.md`)
3. **Codebase Patterns**: When context files don't provide specific guidance, scan the codebase for established patterns
4. **Architectural Consistency**: Maintain plugin-based architecture extending Cacti core
5. **Code Quality**: Prioritize security, maintainability, and compatibility in all generated code

## Technology Stack

### Core Technologies
- **PHP**: Minimum PHP 7.x (inherited from Cacti requirements)
- **Platform**: Cacti Plugin Architecture (Cacti 1.2.24+)
- **Database**: MySQL/MariaDB with InnoDB engine
- **Encryption**: OpenSSL with AES-256-CBC cipher

### Key Dependencies
- Cacti core framework (functions like `api_plugin_*`, `db_execute_*`, etc.)
- PHP extensions: `openssl`, `curl`, `mysqli`
- Optional: `gettext` for internationalization

## Project Structure

```
servcheck/             # Repository root (install to plugins/servcheck/ in Cacti)
├── includes/          # Test implementation modules
│   ├── functions.php  # Core utility functions
│   ├── arrays.php     # Configuration arrays and constants
│   ├── test_*.php     # Protocol-specific test implementations
│   └── index.php      # Access protection
├── locales/           # Internationalization files
│   ├── po/            # Translation source files
│   └── LC_MESSAGES/   # Compiled translation files
├── cert/              # SSL/TLS certificates
├── tmp_data/          # Temporary data storage
├── setup.php          # Plugin installation and upgrade hooks
├── servcheck_*.php    # Main UI/management pages
└── poller_servcheck.php  # Background poller integration
```

## Naming Conventions

### Function Names

#### Plugin Hook Functions
Functions that integrate with Cacti's plugin system MUST be prefixed with `plugin_servcheck_`:

```php
function plugin_servcheck_install() { }
function plugin_servcheck_upgrade() { }
function plugin_servcheck_poller_bottom() { }
function plugin_servcheck_config_arrays() { }
```

#### Internal Functions
All other functions MUST be prefixed with `servcheck_`:

```php
function servcheck_check_debug() { }
function servcheck_debug($message) { }
function servcheck_encrypt_credential($cred) { }
function servcheck_filter() { }
```

**IMPORTANT**: Never use `plugin_servcheck_` prefix for internal functions. This was corrected in commit a216a5e. Functions like `plugin_servcheck_check_debug` were renamed to `servcheck_check_debug`.

### Database Tables
All database tables MUST be prefixed with `plugin_servcheck_`:

```php
plugin_servcheck_test
plugin_servcheck_log
plugin_servcheck_credential
plugin_servcheck_proxy
plugin_servcheck_ca
plugin_servcheck_processes
```

### Variables and Constants
- Use snake_case for variables: `$test_id`, `$result_search`, `$ca_info`
- Use UPPERCASE for constants: `SERVCHECK_CIPHER`
- Global arrays use descriptive names: `$servcheck_tabs`, `$service_types`, `$credential_types`

## Code Style

### Indentation and Formatting
- **Tabs**: Use tabs (not spaces) for indentation throughout all PHP files
- **Braces**: Opening brace on same line for functions and control structures
- **Spacing**: Space after control structure keywords (`if`, `foreach`, `while`)

```php
function servcheck_example($param) {
	if ($param > 0) {
		foreach ($items as $item) {
			// code here
		}
	}
}
```

### File Headers
ALL PHP files MUST include the standard GPL v2 license header:

```php
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
```

## Security Standards

### SQL Query Security
**ALWAYS use prepared statements** for database operations - never concatenate user input into SQL:

```php
// CORRECT - Always use prepared statements
$result = db_fetch_row_prepared('SELECT * FROM plugin_servcheck_test WHERE id = ?', 
	array($id));

$result = db_fetch_assoc_prepared('SELECT * FROM plugin_servcheck_log 
	WHERE test_id = ? AND lastcheck > DATE_SUB(NOW(), INTERVAL ? HOUR)',
	array($test_id, $interval));

db_execute_prepared('UPDATE plugin_servcheck_test SET enabled = ? WHERE id = ?',
	array($enabled, $id));

// WRONG - Never do this
$result = db_fetch_row("SELECT * FROM plugin_servcheck_test WHERE id = $id");
```

### Input Validation
Use Cacti's built-in input validation functions:

```php
// For filtered request variables with validation
get_filter_request_var('id');  // Validates and returns integer
get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP, 
	array('options' => array('regexp' => '/^([a-zA-Z0-9_]+)$/')));

// For non-filtered request variables
get_nfilter_request_var('name');
get_request_var('action');

// Check if variable exists and is not empty
if (!isempty_request_var('id')) {
	$id = get_request_var('id');
}

// Form input validation
$save['name'] = form_input_validate(get_nfilter_request_var('name'), 'name', '', false, 3);
$save['hostname'] = form_input_validate(get_nfilter_request_var('hostname'), 'hostname', '', false, 3);

// Numeric validation
input_validate_input_number($value);
```

### Credential Encryption
All sensitive credentials MUST be encrypted using AES-256-CBC:

```php
// Encrypting credentials
$encrypted_data = servcheck_encrypt_credential($credential_array);
db_execute_prepared('INSERT INTO plugin_servcheck_credential (name, type, data) VALUES (?, ?, ?)',
	array($name, $type, $encrypted_data));

// Decrypting credentials
$credential = servcheck_decrypt_credential($cred_id);
if (empty($credential)) {
	servcheck_debug('Credential is empty!');
	cacti_log('Credential is empty');
	return false;
}
```

### IP Address Validation
Always validate IP addresses before using them:

```php
if ($test['ipaddress'] != '') {
	if (!filter_var($test['ipaddress'], FILTER_VALIDATE_IP)) {
		cacti_log('IP in "Resolve DNS to Address" is invalid.');
		$results['result'] = 'error';
		$results['error'] = 'Invalid IP';
		return $results;
	}
}
```

## Database Operations

### Table Creation
Use Cacti's `api_plugin_db_table_create()` function with proper structure:

```php
$data = array();
$data['columns'][] = array('name' => 'id', 'type' => 'int(11)', 'NULL' => false, 'auto_increment' => true);
$data['columns'][] = array('name' => 'name', 'type' => 'varchar(64)', 'NULL' => false, 'default' => '');
$data['columns'][] = array('name' => 'enabled', 'type' => 'varchar(2)', 'NULL' => false, 'default' => 'on');
$data['primary'] = 'id';
$data['keys'][] = array('name' => 'enabled', 'columns' => 'enabled');
$data['type'] = 'InnoDB';
$data['comment'] = 'Holds servcheck Service Check Definitions';

api_plugin_db_table_create('servcheck', 'plugin_servcheck_test', $data);
```

### Database Queries
Common patterns for database operations:

```php
// Fetch single value
$name = db_fetch_cell_prepared('SELECT name FROM plugin_servcheck_test WHERE id = ?', 
	array($id));

// Fetch single row
$test = db_fetch_row_prepared('SELECT * FROM plugin_servcheck_test WHERE id = ?', 
	array($id));

// Fetch multiple rows
$results = db_fetch_assoc_prepared('SELECT * FROM plugin_servcheck_log 
	WHERE test_id = ? ORDER BY lastcheck DESC LIMIT ?',
	array($test_id, $limit));

// Execute update/insert/delete
db_execute_prepared('UPDATE plugin_servcheck_test SET lastcheck = NOW() WHERE id = ?',
	array($id));

db_execute_prepared('DELETE FROM plugin_servcheck_log WHERE test_id = ?',
	array($test_id));

// Get insert ID after insert
$cred_id = db_fetch_insert_id();

// Check table/column existence
if (db_table_exists('plugin_servcheck_restapi_method')) {
	// table exists
}

if (db_column_exists('plugin_servcheck_test', 'display_name')) {
	db_execute('ALTER TABLE plugin_servcheck_test RENAME COLUMN display_name TO name');
}
```

## Internationalization

### Translation Wrapping
ALL user-facing strings MUST use the `__()` function with 'servcheck' text domain:

```php
// CORRECT
$tab_name = __('Tests', 'servcheck');
$error_msg = __('Service Check Admin', 'servcheck');
$label = __('HTTP plaintext, default port 80', 'servcheck');

// Array definitions
$servcheck_tabs = array(
	'servcheck_test.php' => __('Tests', 'servcheck'),
	'servcheck_ca.php' => __('CA certificates', 'servcheck'),
	'servcheck_proxy.php' => __('Proxies', 'servcheck'),
);

// HTML output
print __('No data', 'servcheck');
html_start_box(__('Service Checks', 'servcheck'), '100%', '', '3', 'center', '');

// WRONG - Never use plain strings for user-facing text
$tab_name = 'Tests';  // Missing translation
```

## Logging and Debugging

### Logging Patterns
Use appropriate logging functions for different scenarios:

```php
// General logging with cacti_log()
cacti_log('Empty path, nothing to test');
cacti_log('Credential not found');
cacti_log('ERROR: Unable to obtain Proxy settings');
cacti_log('INFO: Replicating for the servcheck Plugin', false, 'REPLICATE');

// Debug logging - only logs when debug is enabled
servcheck_debug('Using CURLOPT_RESOLVE: ' . $test['hostname'] . ':' . $test['ipaddress']);
servcheck_debug('Decrypting credential');
servcheck_debug('Final url is ' . $url);
servcheck_debug('cURL options: ' . clean_up_lines(var_export($options, true)));

// Enable debug mode check
servcheck_check_debug();  // Call this before any servcheck_debug() calls
```

### Debug Mode
The debug mode detection pattern:

```php
function servcheck_check_debug() {
	global $debug;

	if (!$debug) {
		$plugin_debug = read_config_option('selective_plugin_debug');

		if (preg_match('/(^|[, ]+)(servcheck)($|[, ]+)/', $plugin_debug, $matches)) {
			$debug = (cacti_sizeof($matches) == 4 && $matches[2] == 'servcheck');
		}
	}
}

function servcheck_debug($message='') {
	global $debug;

	if ($debug) {
		cacti_log('DEBUG: ' . trim($message), true, 'SERVCHECK');
	}
}
```

## Error Handling

### Result Arrays
Functions that perform tests or operations should return result arrays with consistent structure:

```php
// Initialize default error result
$results['result'] = 'error';
$results['curl'] = true;
$results['error'] = '';
$results['result_search'] = 'not tested';
$results['start'] = microtime(true);

// On error
if ($error_condition) {
	cacti_log('Error description');
	$results['result'] = 'error';
	$results['error'] = 'Error description';
	return $results;
}

// On success
$results['result'] = 'ok';
$results['duration'] = microtime(true) - $results['start'];
return $results;
```

### Result Enums
Use predefined enums for results:

```php
// Main result states
result ENUM('ok', 'not yet', 'error')

// Search result states
result_search ENUM('ok', 'not ok', 'failed ok', 'failed not ok', 'maint ok', 'not yet', 'not tested')
```

## Plugin Architecture

### Plugin Hooks
Register all plugin hooks in `plugin_servcheck_install()`:

```php
function plugin_servcheck_install() {
	api_plugin_register_hook('servcheck', 'draw_navigation_text', 'plugin_servcheck_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('servcheck', 'config_arrays', 'plugin_servcheck_config_arrays', 'setup.php');
	api_plugin_register_hook('servcheck', 'poller_bottom', 'plugin_servcheck_poller_bottom', 'setup.php');
	api_plugin_register_hook('servcheck', 'replicate_out', 'servcheck_replicate_out', 'setup.php');
	api_plugin_register_hook('servcheck', 'config_settings', 'servcheck_config_settings', 'setup.php');

	api_plugin_register_realm('servcheck', 'servcheck_test.php,servcheck_restapi.php,...', 
		__('Service Check Admin', 'servcheck'), 1);

	plugin_servcheck_setup_table();
}
```

### Upgrade Handling
Always include upgrade logic in `plugin_servcheck_upgrade()`:

```php
function plugin_servcheck_upgrade() {
	global $config;

	require_once(__DIR__ . '/includes/functions.php');

	$info = plugin_servcheck_version();
	$new = $info['version'];
	$old = db_fetch_cell('SELECT version FROM plugin_config WHERE directory="servcheck"');

	if (cacti_version_compare($old, '0.3', '<')) {
		// Create backup tables before making destructive changes
		db_execute('CREATE TABLE plugin_servcheck_test_backup AS SELECT * FROM plugin_servcheck_test');
		
		// Check table/column existence before operations
		if (db_table_exists('plugin_servcheck_restapi_method')) {
			// upgrade logic
		}
		
		if (db_column_exists('plugin_servcheck_test', 'display_name')) {
			db_execute('ALTER TABLE plugin_servcheck_test RENAME COLUMN display_name TO name');
		}
	}

	// Always update version at the end
	db_execute_prepared("UPDATE plugin_config SET version = ? WHERE directory = 'servcheck'",
		array($new));
}
```

## Test Implementation Patterns

### Test Modules
Test implementations in `includes/test_*.php` should follow this structure:

```php
function test_type_try($test) {
	global $config;
	
	// Initialize results
	$results['result'] = 'error';
	$results['error'] = '';
	$results['start'] = microtime(true);
	
	// Validate input
	if (empty($test['required_field'])) {
		cacti_log('Required field missing');
		$results['result'] = 'error';
		$results['error'] = 'Required field missing';
		return $results;
	}
	
	// Get credentials if needed
	if ($test['cred_id'] > 0) {
		$credential = servcheck_decrypt_credential($test['cred_id']);
		if (empty($credential)) {
			servcheck_debug('Credential is empty!');
			cacti_log('Credential is empty');
			$results['result'] = 'error';
			$results['error'] = 'Credential is empty';
			return $results;
		}
	}
	
	// Perform test
	try {
		// test implementation
		$results['result'] = 'ok';
		$results['duration'] = microtime(true) - $results['start'];
	} catch (Exception $e) {
		$results['result'] = 'error';
		$results['error'] = $e->getMessage();
	}
	
	return $results;
}
```

### cURL Configuration
Standard cURL options pattern:

```php
$options = array(
	CURLOPT_HEADER => true,
	CURLOPT_USERAGENT => $user_agent,
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_FOLLOWLOCATION => true,
	CURLOPT_MAXREDIRS => 4,
	CURLOPT_TIMEOUT => $test['duration_trigger'] > 0 ? ($test['duration_trigger'] + 1) : 5,
	CURLOPT_CAINFO => $ca_info,
);

// Apply credential if needed
if ($credential['type'] == 'basic') {
	$options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
	$options[CURLOPT_USERPWD] = $credential['username'] . ':' . $credential['password'];
}

servcheck_debug('cURL options: ' . clean_up_lines(var_export($options, true)));
```

## Configuration Arrays

Define configuration in `includes/arrays.php`:

```php
// Service types with descriptions
$service_types = array(
	'web_http' => __('HTTP plaintext, default port 80', 'servcheck'),
	'web_https' => __('HTTP encrypted (HTTPS), default port 443', 'servcheck'),
	// ...
);

// Default ports for each service type
$service_types_ports = array(
	'web_http' => 80,
	'web_https' => 443,
	// ...
);

// State definitions with colors
$servcheck_states = array(
	'error' => array(
		'color' => '#FB4A14',
		'display' => __('Error', 'servcheck')
	),
	'ok' => array(
		'color' => '#E0FFE0',
		'display' => __('Ok', 'servcheck')
	),
	// ...
);
```

## Best Practices

### 1. Consistency Over Innovation
- Match existing code patterns exactly
- Don't introduce new patterns without documented reason
- Follow established naming conventions without exception

### 2. Security First
- Always use prepared statements for SQL
- Encrypt all credentials using `servcheck_encrypt_credential()`
- Validate all user input using Cacti's validation functions
- Never trust user input in file operations

### 3. Cacti Integration
- Use Cacti's API functions (`api_plugin_*`, `db_*`, etc.)
- Follow Cacti's plugin architecture requirements
- Respect Cacti's configuration options and settings
- Integrate with Cacti's maintenance plugin when appropriate

### 4. Error Handling
- Always log errors with `cacti_log()`
- Return proper error states in result arrays
- Provide meaningful error messages for debugging
- Handle edge cases explicitly

### 5. Internationalization
- Wrap ALL user-facing strings with `__('text', 'servcheck')`
- Never use plain strings for labels, messages, or UI text
- Keep text domain consistent ('servcheck')

### 6. Code Documentation
- Use inline comments for complex logic
- Document function purposes when not obvious
- Explain security-critical sections
- Note dependencies and requirements

### 7. Performance
- Cache configuration values when appropriate
- Use proper database indexes
- Limit query result sets appropriately
- Clean up temporary files and resources

### 8. Testing
- Test with Cacti's maintenance mode
- Verify poller integration doesn't block
- Check encryption/decryption round-trips
- Validate upgrade paths from earlier versions

## Common Pitfalls to Avoid

### ❌ NEVER Do This
```php
// Don't use plugin_servcheck_ prefix for internal functions
function plugin_servcheck_check_debug() { }  // WRONG

// Don't concatenate SQL queries
$sql = "SELECT * FROM table WHERE id = $id";  // WRONG

// Don't use hardcoded strings for UI
print 'Service Check';  // WRONG

// Don't use spaces for indentation
    if ($condition) {  // WRONG (spaces used)

// Don't skip input validation
$id = $_GET['id'];  // WRONG
```

### ✅ ALWAYS Do This
```php
// Use servcheck_ prefix for internal functions
function servcheck_check_debug() { }  // CORRECT

// Use prepared statements
$result = db_fetch_row_prepared('SELECT * FROM table WHERE id = ?', array($id));  // CORRECT

// Translate all user-facing strings
print __('Service Check', 'servcheck');  // CORRECT

// Use tabs for indentation
	if ($condition) {  // CORRECT (tabs used)

// Always validate input
$id = get_filter_request_var('id');  // CORRECT
```

## Version Control

### Changelog Maintenance
Document all changes in `CHANGELOG.md`:

```markdown
--- develop ---

--- 0.3 ---

* feature: Add new functionality description
* issue#XX: Fix specific problem description
* issue: General improvement description
```

### Commit Messages
Follow the established pattern from git history:
- Use descriptive commit messages
- Reference issue numbers when applicable
- Group related changes logically
- Co-author when using Copilot assistance

## References

- Cacti Plugin Development Guide
- Cacti API Documentation
- PHP OpenSSL documentation for encryption
- Project README.md for feature descriptions
- CHANGELOG.md for version history
