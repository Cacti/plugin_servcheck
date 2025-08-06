<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2025 The Cacti Group                                 |
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

// for encrypt credentials
if (!defined('SERVCHECK_CIPHER')) {
	define('SERVCHECK_CIPHER', 'aes-256-cbc');
}


$servcheck_tabs = array(
	'servcheck_test.php'       => __('Tests', 'servcheck'),
	'servcheck_ca.php'         => __('CA certificates', 'servcheck'),
	'servcheck_proxies.php'    => __('Proxies', 'servcheck'),
	'servcheck_restapi.php'    => __('Rest API methods', 'servcheck'),
	'servcheck_credential.php' => __('Credential', 'servcheck'),
	'servcheck_curl_code.php'  => __('Curl return codes', 'servcheck'),
);

$service_types = array(
	'web_http'     => __('HTTP plaintext, default port 80', 'servcheck'),
	'web_https'    => __('HTTP encrypted (HTTPS), default port 443', 'servcheck'),

	'mail_smtp'    => __('SMTP plaintext, default port 25 (or 587 for submission)', 'servcheck'),
	'mail_smtptls' => __('SMTP with STARTTLS, default port 25(or 587 for submission)', 'servcheck'),
	'mail_smtps'   => __('SMTP encrypted (SMTPS), default port 465', 'servcheck'),

	'mail_imap'    => __('IMAP plaintext, default port 143', 'servcheck'),
	'mail_imaptls' => __('IMAP with STARTTLS, default port 143', 'servcheck'),
	'mail_imaps'   => __('IMAP encrypted (IMAPS), default port 993', 'servcheck'),

	'mail_pop3'    => __('POP3 plaintext, default port 110', 'servcheck'),
	'mail_pop3tls' => __('POP3 with STARTTLS, default port 110', 'servcheck'),
	'mail_pop3s'   => __('POP3 encrypted (POP3S), default port 995', 'servcheck'),

	'dns_dns'      => __('DNS plaintext, default port 53', 'servcheck'),
	'dns_doh'      => __('DNS over HTTPS, default port 443', 'servcheck'),

	'ldap_ldap'    => __('LDAP plaintext, default port 389', 'servcheck'),
	'ldap_ldaps'   => __('LDAP encrypted (LDAPS), default port 636', 'servcheck'),

	'ftp_ftp'      => __('FTP plaintext, default port 21', 'servcheck'),
	'ftp_ftps'     => __('FTP encrypted (FTPS), default port 990', 'servcheck'),
	'ftp_scp'      => __('SCP download file, default port 22', 'servcheck'),
	'ftp_tftp'     => __('TFTP protocol - download file, default port 69', 'servcheck'),

	'smb_smb'      => __('SMB plaintext download file, default port 445', 'servcheck'),
	'smb_smbs'     => __('SMB encrypted (SMBS) download file, default port 445', 'servcheck'),

	'mqtt_mqtt'    => __('MQTT plaintext, default port 1883', 'servcheck'),

	'rest_no'      => __('Rest API without auth', 'servcheck'),
	'rest_basic'   => __('Rest API with Basic HTTP auth', 'servcheck'),
	'rest_apikey'  => __('Rest API with API key auth', 'servcheck'),
	'rest_oauth2'  => __('Rest API with OAuth2/Bearer token auth', 'servcheck'),
	'rest_cookie'  => __('Rest API with Cookie based auth', 'servcheck'),
);

$service_types_ports = array(
	'web_http'     => 80,
	'web_https'    => 443,
	'mail_smtp'    => 25,
	'mail_smtptls' => 25,
	'mail_smtps'   => 465,
	'mail_imap'    => 143,
	'mail_imaptls' => 143,
	'mail_imaps'   => 993,
	'mail_pop3'    => 110,
	'mail_pop3tls' => 110,
	'mail_pop3s'   => 995,
	'dns_dns'      => 53,
	'dns_doh'      => 443,
	'ldap_ldap'    => 389,
	'ldap_ldaps'   => 636,
	'ftp_ftp'      => 21,
	'ftp_ftps'     => 990,
	'ftp_scp'      => 22,
	'ftp_tftp'     => 69,
	'smb_smb'      => 389,
	'smb_smbs'     => 636,
	'mqtt_mqtt'    => 1883,
	'rest_no'      => 443,
	'rest_basic'   => 443,
	'rest_apikey'  => 443,
	'rest_oauth2'  => 443,
	'rest_cookie'  => 443,
);


$graph_interval = array (
	  1 => __('Last hour', 'servcheck'),
	  6 => __('Last 6 hours', 'servcheck'),
	 24 => __('Last day', 'servcheck'),
	168 => __('Last week', 'servcheck'),
);

// !!pm zrusit, uz je soucast pole nahore
$rest_api_auth_method = array(
	'no'     => __('Without auth', 'servcheck'),
	'basic'  => __('Basic HTTP auth', 'servcheck'),
	'apikey' => __('API key auth', 'servcheck'),
	'oauth2' => __('OAuth2/Bearer token auth', 'servcheck'),
	'cookie' => __('Cookie based auth', 'servcheck'),
);


$rest_api_format = array(
	'urlencoded'  => 'Form-urlencoded',
//	'xml'            => 'XML',
	'json'           => 'JSON'
);

$search_result = array(
	'ok'            => __('String found', 'servcheck'),
	'not ok'        => __('String not found', 'servcheck'),
	'failed ok'     => __('Failed string found', 'servcheck'),
	'failed not ok' => __('Failed string not found', 'servcheck'),
	'maint ok'      => __('Maint string found', 'servcheck'),
	'not yet'       => __('Not tested yet', 'servcheck'),
	'not tested'    => __('Search not performed', 'servcheck')
);

$credential_types = array(
	'userpass'       => __('Username and password', 'servcheck'),
	'snmp'           => __('SNMP v1 or v2', 'servcheck'),
	'snmp3'          => __('SNMP v3', 'servcheck'),
	'sshkey'         => __('SSH private key', 'servcheck'),
	'basic'  => __('Rest API - Basic HTTP auth', 'servcheck'),
	'apikey' => __('Rest API - API key auth', 'servcheck'),
	'oauth2' => __('Rest API - OAuth2/Bearer token auth', 'servcheck'),
	'cookie' => __('Rest API - Cookie based auth', 'servcheck')
);

$httperrors = array(
	  0 => __('Unable to Connect', 'servcheck'),
	100 => __('Continue', 'servcheck'),
	101 => __('Switching Protocols', 'servcheck'),
	200 => __('OK', 'servcheck'),
	201 => __('Created', 'servcheck'),
	202 => __('Accepted', 'servcheck'),
	203 => __('Non-Authoritative Information', 'servcheck'),
	204 => __('No Content', 'servcheck'),
	205 => __('Reset Content', 'servcheck'),
	206 => __('Partial Content', 'servcheck'),
	300 => __('Multiple Choices', 'servcheck'),
	301 => __('Moved Permanently', 'servcheck'),
	302 => __('Found', 'servcheck'),
	303 => __('See Other', 'servcheck'),
	304 => __('Not Modified', 'servcheck'),
	305 => __('Use Proxy', 'servcheck'),
	306 => __('(Unused)', 'servcheck'),
	307 => __('Temporary Redirect', 'servcheck'),
	400 => __('Bad Request', 'servcheck'),
	401 => __('Unauthorized', 'servcheck'),
	402 => __('Payment Required', 'servcheck'),
	403 => __('Forbidden', 'servcheck'),
	404 => __('Not Found', 'servcheck'),
	405 => __('Method Not Allowed', 'servcheck'),
	406 => __('Not Acceptable', 'servcheck'),
	407 => __('Proxy Authentication Required', 'servcheck'),
	408 => __('Request Timeout', 'servcheck'),
	409 => __('Conflict', 'servcheck'),
	410 => __('Gone', 'servcheck'),
	411 => __('Length Required', 'servcheck'),
	412 => __('Precondition Failed', 'servcheck'),
	413 => __('Request Entity Too Large', 'servcheck'),
	414 => __('Request-URI Too Long', 'servcheck'),
	415 => __('Unsupported Media Type', 'servcheck'),
	416 => __('Requested Range Not Satisfiable', 'servcheck'),
	417 => __('Expectation Failed', 'servcheck'),
	500 => __('Internal Server Error', 'servcheck'),
	501 => __('Not Implemented', 'servcheck'),
	502 => __('Bad Gateway', 'servcheck'),
	503 => __('Service Unavailable', 'servcheck'),
	504 => __('Gateway Timeout', 'servcheck'),
	505 => __('HTTP Version Not Supported', 'servcheck')
);

$servcheck_cycles = array(
	1  => __('%d Poller run', 1, 'servcheck'),
	2  => __('%d Poller runs', 2, 'servcheck'),
	3  => __('%d Poller runs', 3, 'servcheck'),
	4  => __('%d Poller runs', 4, 'servcheck'),
	5  => __('%d Poller runs', 5, 'servcheck'),
	6  => __('%d Poller runs', 6, 'servcheck'),
	7  => __('%d Poller runs', 7, 'servcheck'),
	8  => __('%d Poller runs', 8, 'servcheck'),
	9  => __('%d Poller runs', 9, 'servcheck'),
	10 => __('%d Poller runs', 10, 'servcheck'),
);

$servcheck_seconds = array(
	3  => __('%d Seconds', 3, 'servcheck'),
	4  => __('%d Seconds', 4, 'servcheck'),
	5  => __('%d Seconds', 5, 'servcheck'),
	6  => __('%d Seconds', 6, 'servcheck'),
	7  => __('%d Seconds', 7, 'servcheck'),
	8  => __('%d Seconds', 8, 'servcheck'),
	9  => __('%d Seconds', 9, 'servcheck'),
	10 => __('%d Seconds', 10, 'servcheck'),
);

$servcheck_notify_formats = array(
	'html' => 'html',
	'plain' => 'plain',
);

if (db_table_exists('plugin_servcheck_contacts')) {
	$servcheck_contact_users = db_fetch_assoc("SELECT pwc.id, pwc.data, pwc.type, ua.full_name
		FROM plugin_servcheck_contacts AS pwc
		LEFT JOIN user_auth AS ua
		ON ua.id=pwc.user_id
		WHERE pwc.data != ''");
} else {
	$servcheck_contact_users = array();
}

$servcheck_notify_accounts = array();
if (!empty($servcheck_contact_users)) {
	foreach ($servcheck_contact_users as $servcheck_contact_user) {
		$servcheck_notify_accounts[$servcheck_contact_user['id']] = $servcheck_contact_user['full_name'] . ' - ' . ucfirst($servcheck_contact_user['type']);
	}
}

$servcheck_actions_proxy = array(
	'delete' => __('Delete', 'servcheck'),
);

$servcheck_actions_ca = array(
	'delete' => __('Delete', 'servcheck'),
);

$servcheck_actions_test = array(
	'delete'    => __('Delete', 'servcheck'),
	'disable'   => __('Disable', 'servcheck'),
	'enable'    => __('Enable', 'servcheck'),
	'duplicate' => __('Duplicate', 'servcheck'),
);

$servcheck_actions_restapi = array(
	'delete'    => __('Delete', 'servcheck'),
	'duplicate' => __('Duplicate', 'servcheck'),
);

$servcheck_actions_credential = array(
	'delete'    => __('Delete', 'servcheck'),
	'duplicate' => __('Duplicate', 'servcheck'),
);

$servcheck_ca_fields = array(
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Name'),
		'description' => __('A Useful Name for this CA chain.'),
		'value' => '|arg1:name|',
		'max_length' => '100',
		'size' => '100',
		'default' => __('New CA')
	),
	'cert'  => array(
		'friendly_name' => __('CA Chain', 'servcheck'),
		'method' => 'textarea',
		'textarea_rows' => 10,
		'textarea_cols' => 100,
		'description' => __('CA and intermediate certs, PEM encoded', 'servcheck'),
		'value' => '|arg1:cert|'
	)
);

$servcheck_proxy_fields = array(
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Name'),
		'description' => __('A Useful Name for this Proxy.'),
		'value' => '|arg1:name|',
		'max_length' => '40',
		'size' => '40',
		'default' => __('New Proxy')
	),
	'hostname' => array(
		'method' => 'textbox',
		'friendly_name' => __('Hostname'),
		'description' => __('The Proxy Hostname.'),
		'value' => '|arg1:hostname|',
		'max_length' => '64',
		'size' => '40',
		'default' => ''
	),
	'http_port' => array(
		'method' => 'textbox',
		'friendly_name' => __('HTTP Port'),
		'description' => __('The HTTP Proxy Port.'),
		'value' => '|arg1:http_port|',
		'max_length' => '5',
		'size' => '5',
		'default' => '80'
	),
	'https_port' => array(
		'method' => 'textbox',
		'friendly_name' => __('HTTPS Port'),
		'description' => __('The HTTPS Proxy Port.'),
		'value' => '|arg1:https_port|',
		'max_length' => '5',
		'size' => '5',
		'default' => '443'
	),
	'cred_id' => array(
		'friendly_name' => __('Credential', 'servcheck'),
		'method' => 'drop_sql',
		'default' => 0,
		'description' => __('Select correct credential', 'servcheck'),
		'value' => '|arg1:cred_id|',
		'sql' => "SELECT id, name FROM plugin_servcheck_credential WHERE type = 'userpass' ORDER BY name",
	),
	'id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:id|'
	),
	'save_component_proxy' => array(
		'method' => 'hidden',
		'value' => '1'
	)
);

$servcheck_test_fields = array(
	'general_spacer' => array(
		'method' => 'spacer',
		'friendly_name' => __('General Settings', 'servcheck')
	),
	'display_name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Service Check Name', 'servcheck'),
		'description' => __('The name that is displayed for this Service Check, and is included in any Alert notifications.', 'servcheck'),
		'value' => '|arg1:display_name|',
		'max_length' => '256',
	),
	'enabled' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Enable Service Check', 'servcheck'),
		'description' => __('Uncheck this box to disable this test from being checked.', 'servcheck'),
		'value' => '|arg1:enabled|',
		'default' => 'on',
	),
	'poller_id' => array(
		'friendly_name' => __('Poller', 'servcheck'),
		'method' => 'drop_sql',
		'default' => 1,
		'description' => __('Poller on which the test will run', 'servcheck'),
		'value' => '|arg1:poller_id|',
		'sql' => 'SELECT id, name FROM poller ORDER BY id',
	),
	'type' => array(
		'friendly_name' => __('Type of service', 'servcheck'),
		'method' => 'drop_array',
		'on_change' => 'setTest()',
		'array' => $service_types,
		'default' => 'web_http',
		'description' => __('What type of service?', 'servcheck'),
		'value' => '|arg1:type|',
	),
	'hostname' => array(
		'method' => 'textbox',
		'friendly_name' => __('IP Address or DNS name of server', 'servcheck'),
		'description' => __('You can specify another port (example.com:5000) otherwise default will be used. For DOH (DNS over HTTPS) use here name of server (example 10.10.10.10, cloudflare-dns.com, ..)', 'servcheck'),
		'value' => '|arg1:hostname|',
		'max_length' => '100',
		'size' => '30'
	),
	'ipaddress' => array(
		'method' => 'textbox',
		'friendly_name' => __('Resolve DNS to Address'),
		'description' => __('Enter an IP Address to force DNS name to resolve to. Leaving blank will use DNS Resoution instead.'),
		'value' => '|arg1:ipaddress|',
		'max_length' => '46',
		'size' => '40',
		'default' => ''
	),
	'service_spacer' => array(
		'method' => 'spacer',
		'friendly_name' => __('Service settings', 'servcheck')
	),
	'cred_id' => array(
		'friendly_name' => __('Credential', 'servcheck'),
		'method' => 'drop_sql',
		'default' => 0,
		'description' => __('Select correct credential', 'servcheck'),
		'value' => '|arg1:cred_id|',
		'sql' => "SELECT id, name FROM plugin_servcheck_credential ORDER BY name",
	),
	'format' => array(
		'friendly_name' => __('Data Format', 'servcheck'),
		'method' => 'drop_array',
		'array' => $rest_api_format,
		'default' => 'urlencoded',
		'description' => __('Select correct format for communication, check your Rest API documentation.', 'servcheck'),
		'value' => '|arg1:format|',
	),

	'ca' => array(
		'friendly_name' => __('CA Chain', 'servcheck'),
		'method' => 'drop_sql',
		'none_value' => __('None', 'servcheck'),
		'default' => '0',
		'description' => __('Your own CA and intermediate certs', 'servcheck'),
		'value' => '|arg1:ca|',
		'sql' => 'SELECT id, name FROM plugin_servcheck_ca ORDER by name'
	),
	'ldapsearch' => array(
		'friendly_name' => __('LDAP Search', 'servcheck'),
		'method' => 'textbox',
		'description' => __('LDAP search filter, it could be ,OU=anygroup,DC=example,DC=com', 'servcheck'),
		'value' => '|arg1:ldapsearch|',
		'max_length' => '200',
		'size' => '50'
	),
	'dns_query' => array(
		'method' => 'textbox',
		'friendly_name' => __('DNS Name for Query', 'servcheck'),
		'description' => __('DNS name for querying. For DOH (DNS over HTTPS) use /resolve?name=example.com or /dns-query?name=example.com&type=A', 'servcheck'),
		'value' => '|arg1:dns_query|',
		'max_length' => '40',
		'size' => '30'
	),
	'path' => array(
		'method' => 'textbox',
		'friendly_name' => __('Path Part of URL', 'servcheck'),
		'description' => __('For web service insert at least "/" or something like "/any/path/". For FTP listing must end with char "/". For TFTP/SCP/SMB download test insert /path/file. For MQTT you can specify topic (bedroom/temp). Left blank for any topic.', 'servcheck'),
		'value' => '|arg1:path|',
		'max_length' => '140',
		'size' => '30'
	),
	'requiresauth' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Requires Authentication', 'servcheck'),
		'description' => __('Check this box if the site will normally return a 401 Error as it requires a username and password.', 'servcheck'),
		'value' => '|arg1:requiresauth|',
		'default' => '',
	),
	'proxy_server' => array(
		'method' => 'drop_sql',
		'friendly_name' => __('Proxy Server', 'servcheck'),
		'description' => __('If this connection text requires a proxy, select it here.  Otherwise choose \'None\'.', 'servcheck'),
		'value' => '|arg1:proxy_server|',
		'none_value' => __('None', 'servcheck'),
		'default' => '0',
		'sql' => 'SELECT id, name FROM plugin_servcheck_proxies ORDER by name'
	),
	'checkcert' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Check Certificate', 'servcheck'),
		'description' => __('If using SSL, check this box if you want to validate the certificate. Default on, turn off if you the site uses a self-signed certificate.', 'servcheck'),
		'value' => '|arg1:checkcert|',
		'default' => '',
	),
	'certexpirenotify' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Check and notify Certificate expiration', 'servcheck'),
		'description' => __('If using SSL, check this box if you want to check the certificate expiration. You can set in settings how many days before expiration notify..', 'servcheck'),
		'value' => '|arg1:certexpirenotify|',
		'default' => '',
	),
	'timings_spacer' => array(
		'method' => 'spacer',
		'friendly_name' => __('Notification Timing', 'servcheck')
	),
	'how_often' => array(
		'friendly_name' => __('How Often to Test', 'servcheck'),
		'method' => 'drop_array',
		'array' => $servcheck_cycles,
		'default' => 1,
		'description' => __('Test every (N * (poller cycle - 10 seconds))', 'servcheck'),
		'value' => '|arg1:how_often|',
	),
	'downtrigger' => array(
		'friendly_name' => __('Trigger', 'servcheck'),
		'method' => 'drop_array',
		'array' => $servcheck_cycles,
		'default' => 1,
		'description' => __('How many poller cycles the service must be DOWN before an alert email is triggered. The same number is applicable in case of \'Site Recovering\'.', 'servcheck'),
		'value' => '|arg1:downtrigger|',
	),
	'timeout_trigger' => array(
		'friendly_name' => __('Time Out', 'servcheck'),
		'method' => 'drop_array',
		'array' => $servcheck_seconds,
		'default' => 4,
		'description' => __('How many seconds to allow the page to timeout before reporting it as down.', 'servcheck'),
		'value' => '|arg1:timeout_trigger|',
	),
	'verifications_spacer' => array(
		'method' => 'spacer',
		'friendly_name' => __('Verification Strings', 'servcheck')
	),
	'search' => array(
		'method' => 'textarea',
		'friendly_name' => __('Response Search String', 'servcheck'),
		'description' => __('This is the string to search for in the response for a live and working service.', 'servcheck'),
		'value' => '|arg1:search|',
		'textarea_rows' => '3',
		'textarea_cols' => '80',
	),
	'search_maint' => array(
		'method' => 'textarea',
		'friendly_name' => __('Response Search String - Maintenance', 'servcheck'),
		'description' => __('This is the string to search for on the Maintenance .  The Service Check will check for this string if the above string is not found.  If found, it means that the service is under maintenance.', 'servcheck'),
		'value' => '|arg1:search_maint|',
		'textarea_rows' => '3',
		'textarea_cols' => '80',
	),
	'search_failed' => array(
		'method' => 'textarea',
		'friendly_name' => __('Response Search String - Failed', 'servcheck'),
		'description' => __('This is the string to search for a known failure in the service response.  The Service Check will only alert if this string is found, ignoring any timeout issues and the search strings above.', 'servcheck'),
		'value' => '|arg1:search_failed|',
		'textarea_rows' => '3',
		'textarea_cols' => '80',
	),
	'notification_spacer' => array(
		'method' => 'spacer',
		'friendly_name' => __('Notification Settings', 'servcheck')
	),
	'notify_format' => array(
		'friendly_name' => __('Notify Format', 'servcheck'),
		'method' => 'drop_array',
		'description' => __('This is the format to use when sending the notification email', 'servcheck'),
		'array' => $servcheck_notify_formats,
		'value' => '|arg1:notify_format|',
	),
	'notify_list' => array(
		'friendly_name' => __('Notification List', 'servcheck'),
		'method' => 'drop_sql',
		'description' => __('Use this Notification List for those to be notified when this service goes down.', 'servcheck'),
		'sql' => 'SELECT id, name FROM plugin_notification_lists ORDER BY name',
		'value' => '|arg1:notify_list|',
		'none_value' => __('None', 'servcheck')
	),
	'notify_accounts' => array(
		'friendly_name' => __('Notify Accounts', 'servcheck'),
		'method' => 'drop_multi',
		'description' => __('This is a listing of accounts that will be notified when this service goes down.', 'servcheck'),
		'array' => $servcheck_notify_accounts,
		'value' => '|arg1:notify_accounts|',
	),
	'notify_extra' => array(
		'friendly_name' => __('Extra Alert Emails', 'servcheck'),
		'method' => 'textarea',
		'textarea_rows' => 3,
		'textarea_cols' => 50,
		'description' => __('You may specify here extra Emails to receive alerts for this test (comma separated)', 'servcheck'),
		'value' => '|arg1:notify_extra|',
	),
	'notes' => array(
		'friendly_name' => __('Notes', 'servcheck'),
		'method' => 'textarea',
		'textarea_rows' => 3,
		'textarea_cols' => 50,
		'description' => __('Notes sent in email', 'servcheck'),
		'value' => '|arg1:notes|',
	),
	'external_id' => array(
		'friendly_name' => __('External ID', 'servcheck'),
		'method' => 'textbox',
		'description' => __('Enter an External ID for this test.', 'servcheck'),
		'default' => '',
		'size' => '20',
		'max_length' => '20',
		'value' => '|arg1:external_id|',
	),
	'id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:id|'
	),
);


$servcheck_restapi_fields = array(
	'general_spacer' => array(
		'method' => 'spacer',
		'friendly_name' => __('General Settings', 'servcheck')
	),
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Rest API Name', 'servcheck'),
		'description' => __('The name that is displayed for this Rest API.', 'servcheck'),
		'value' => '|arg1:name|',
		'max_length' => '100',
	),
	'type' => array(
		'friendly_name' => __('Auth type', 'servcheck'),
		'method' => 'drop_array',
		'on_change' => 'setRestAPI()',
		'array' => $rest_api_auth_method,
		'default' => 'basic_auth',
		'description' => __('Details of auth methods:<br/>
		No authorization - <i>just send only request and read the response.</i><br/>
		Basic - <i>uses HTTP auth. Username and password is Base64 encoded. Credentials are not encrypted.</i><br/>
		API key - <i>you need API key from your Rest API server. It will be send with all request. Key is send ind http headers. You can also add it to the data URL.</i><br/>
		OAuth2 - <i>Oauth2/bearer token auth. Insert your token or use your credentials for getting a token.</i><br/>
		Cookie - <i>Use your credentials for getting cookie. Cookie will be send with each request.</i><br/>
		Note: HTTP method POST is used for login. For data query is used GET.', 'servcheck'),
		'value' => '|arg1:type|',
	),
	'cred_id' => array(
		'friendly_name' => __('Credential', 'servcheck'),
		'method' => 'drop_sql',
		'default' => 0,
		'description' => __('Select correct credential', 'servcheck'),
		'value' => '|arg1:cred_id|',
		'sql' => "SELECT id, name FROM plugin_servcheck_credential WHERE type IN ('basic', 'apikey', 'oauth2', 'cookie') ORDER BY name",
	),
	'format' => array(
		'friendly_name' => __('Data Format', 'servcheck'),
		'method' => 'drop_array',
		'array' => $rest_api_format,
		'default' => 'urlencoded',
		'description' => __('Select correct format for communication, check your Rest API documentation.', 'servcheck'),
		'value' => '|arg1:format|',
	),
	'cred_name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Token/API Key name', 'servcheck'),
		'description' => __('Auth can use different token or API Key name. You can specify it here.
		Commonly used names are  \'Bearer\' for OAuth2,  \'apikey\' for API Key method. You need know correct name, check your Rest API server documentation. ', 'servcheck'),
		'value' => '|arg1:cred_name|',
		'max_length' => '100',
	),
	'login_url' => array(
		'method' => 'textbox',
		'friendly_name' => __('Login URL', 'servcheck'),
		'description' => __('URL which is used to log in or get the token.', 'servcheck'),
		'value' => '|arg1:login_url|',
		'max_length' => '200',
	),
	'data_url' => array(
		'method' => 'textbox',
		'friendly_name' => __('Data URL', 'servcheck'),
		'description' => __('URL to retrieve data. Insert with http:// or https://', 'servcheck'),
		'value' => '|arg1:data_url|',
		'max_length' => '200',
	),
	'id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:id|'
	),
);


$servcheck_credential_fields = array(
	'general_spacer' => array(
		'method' => 'spacer',
		'friendly_name' => __('General Settings', 'servcheck')
	),
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Credential Name', 'servcheck'),
		'description' => __('The name that is displayed for this Credential.', 'servcheck'),
		'value' => '|arg1:name|',
		'max_length' => '100',
	),
	'type' => array(
		'friendly_name' => __('Credential type', 'servcheck'),
		'method' => 'drop_array',
		'on_change' => 'setCredential()',
		'array' => $credential_types,
		'default' => 'userpass',
		'description' => __('Select correct Credential type.', 'servcheck'),
		'value' => '|arg1:type|',
	),
	'username' => array(
		'friendly_name' => __('Username', 'servcheck'),
		'method' => 'textbox',
		'description' => __('<i>LDAP -</i> insert something like cn=John Doe,OU=anygroup,DC=example,DC=com<br/>
					<i>SMB -</i> use DOMAIN/user<br/>
					<i>MQTT -</i> username<br/>
					<i>OAuth2-</i> client_id<br/>', 'servcheck'),
		'value' => '|arg1:username|',
		'max_length' => '100',
		'size' => '30'
	),
	'password' => array(
		'friendly_name' => __('Password', 'servcheck'),
		'method' => 'textbox',
		'description' => __('<i>OAuth2 -</i> client_secret<br/>
			<i>Anonymous FTP -</i>email address', 'servcheck'),
		'value' => '|arg1:password|',
		'max_length' => '100',
		'size' => '30'
	),
	'cred_name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Token/API Key name', 'servcheck'),
		'description' => __('Auth can use different token or API Key name. You can specify it here. You need know correct name, check your Rest API server documentation.<br/>
			<i>OAuth2 -</i> Commonly used name is \'Bearer\'<br/>
			<i>API Key -</i> commonly used name is \'apikey\'<br/> ', 'servcheck'),
		'value' => '|arg1:cred_name|',
		'max_length' => '100',
	),
	'login_url' => array(
		'method' => 'textbox',
		'friendly_name' => __('Login URL', 'servcheck'),
		'description' => __('URL which is used to log in or get the token.', 'servcheck'),
		'value' => '|arg1:login_url|',
		'max_length' => '200',
	),
	'data_url' => array(
		'method' => 'textbox',
		'friendly_name' => __('Data URL', 'servcheck'),
		'description' => __('URL to retrieve data. Insert with http:// or https://', 'servcheck'),
		'value' => '|arg1:data_url|',
		'max_length' => '200',
	),
	'cred_value' => array(
		'method' => 'textbox',
		'friendly_name' => __('Token/API key value', 'servcheck'),
		'description' => __('API key and OAuth2 have two flows - You can have key/token from server and insert it here or use auth flow with credentials.', 'servcheck'),
		'value' => '|arg1:cred_value|',
		'max_length' => '200',
	),
	'community' => array(
		'friendly_name' => __('SNMP v1 or v2 community', 'servcheck'),
		'method' => 'textbox',
		'description' => __('SNMP community string for SNMP v1 or v2(c)', 'servcheck'),
		'value' => '|arg1:community|',
		'max_length' => '30',
		'size' => '30'
	),
	'id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:id|'
	),
);


$curl_error = array(
	0 => array (
		'title'       => 'CURLE_OK',
		'description' => __('All fine. Proceed as usual.', 'servcheck')
	),
	1 => array (
		'title'       => 'CURLE_UNSUPPORTED_PROTOCOL',
		'description' => __('The URL you passed to libcurl used a protocol that this libcurl does not support. The support might be a compile-time option that you did not use, it can be a misspelled protocol string or just a protocol libcurl has no code for.', 'servcheck')
	),
	2 => array (
		'title'       => 'CURLE_FAILED_INIT',
		'description' => __('Early initialization code failed. This is likely to be an internal error or problem, or a resource problem where something fundamental could not get done at init time.', 'servcheck')
	),
	3 => array (
		'title'       => 'CURLE_URL_MALFORMAT',
		'description' => __('The URL was not properly formatted.', 'servcheck')
	),
	4 => array (
		'title'       => 'CURLE_NOT_BUILT_IN',
		'description' => __('A requested feature, protocol or option was not found built-in in this libcurl due to a build-time decision. This means that a feature or option was not enabled or explicitly disabled when libcurl was built and in order to get it to function you have to get a rebuilt libcurl.', 'servcheck')
	),
	5 => array (
		'title'       => 'CURLE_COULDNT_RESOLVE_PROXY',
		'description' => __('Could not resolve proxy. The given proxy host could not be resolved.', 'servcheck')
	),
	6 => array (
		'title'       => 'CURLE_COULDNT_RESOLVE_HOST',
		'description' => __('Could not resolve host. The given remote host was not resolved.', 'servcheck')
	),
	7 => array (
		'title'       => 'CURLE_COULDNT_CONNECT',
		'description' => __('Failed to connect() to host or proxy.', 'servcheck')
	),
	8 => array (
		'title'       => 'CURLE_WEIRD_SERVER_REPLY',
		'description' => __('The server sent data libcurl could not parse. This error code was known as CURLE_FTP_WEIRD_SERVER_REPLY before 7.51.0.', 'servcheck')
	),
	9 => array (
		'title'       => 'CURLE_REMOTE_ACCESS_DENIED',
		'description' => __('We were denied access to the resource given in the URL. For FTP, this occurs while trying to change to the remote directory.', 'servcheck')
	),
	10 => array (
		'title'       => 'CURLE_FTP_ACCEPT_FAILED',
		'description' => __('While waiting for the server to connect back when an active FTP session is used, an error code was sent over the control connection or similar.', 'servcheck')
	),
	11 => array (
		'title'       => 'CURLE_FTP_WEIRD_PASS_REPLY',
		'description' => __('After having sent the FTP password to the server, libcurl expects a proper reply. This error code indicates that an unexpected code was returned.', 'servcheck')
	),
	12 => array (
		'title'       => 'CURLE_FTP_ACCEPT_TIMEOUT',
		'description' => __('During an active FTP session while waiting for the server to connect, the CURLOPT_ACCEPTTIMEOUT_MS (or the internal default) timeout expired.', 'servcheck')
	),
	13 => array (
		'title'       => 'CURLE_FTP_WEIRD_PASV_REPLY',
		'description' => __('Libcurl failed to get a sensible result back from the server as a response to either a PASV or a EPSV command. The server is flawed.', 'servcheck')
	),
	14 => array (
		'title'       => 'CURLE_FTP_WEIRD_227_FORMAT',
		'description' => __('FTP servers return a 227-line as a response to a PASV command. If libcurl fails to parse that line, this return code is passed back.', 'servcheck')
	),
	15 => array (
		'title'       => 'CURLE_FTP_CANT_GET_HOST',
		'description' => __('An internal failure to lookup the host used for the new connection.', 'servcheck')
	),
	16 => array (
		'title'       => 'CURLE_HTTP2',
		'description' => __('A problem was detected in the HTTP2 framing layer. This is somewhat generic and can be one out of several problems, see the error buffer for details.', 'servcheck')
	),
	17 => array (
		'title'       => 'CURLE_FTP_COULDNT_SET_TYPE',
		'description' => __('Received an error when trying to set the transfer mode to binary or ASCII.', 'servcheck')
	),
	18 => array (
		'title'       => 'CURLE_PARTIAL_FILE',
		'description' => __('A file transfer was shorter or larger than expected. This happens when the server first reports an expected transfer size, and then delivers data that does not match the previously given size.', 'servcheck')
	),
	19 => array (
		'title'       => 'CURLE_FTP_COULDNT_RETR_FILE',
		'description' => __('This was either a weird reply to a \'RETR\' command or a zero byte transfer complete.', 'servcheck')
	),
	20 => array (
		'title'       => __('Obsolete Error', 'servcheck'),
		'description' => __('Not used in modern versions.', 'servcheck')
	),
	21 => array (
		'title'       => 'CURLE_QUOTE_ERROR',
		'description' => __('When sending custom "QUOTE" commands to the remote server, one of the commands returned an error code that was 400 or higher (for FTP) or otherwise indicated unsuccessful completion of the command.', 'servcheck')
	),
	22 => array (
		'title'       => 'CURLE_HTTP_RETURNED_ERROR',
		'description' => __('This is returned if CURLOPT_FAILONERROR is set true and the HTTP server returns an error code that is >= 400.', 'servcheck')
	),
	23 => array (
		'title'       => 'CURLE_WRITE_ERROR',
		'description' => __('An error occurred when writing received data to a local file, or an error was returned to libcurl from a write callback.', 'servcheck')
	),
	24 => array (
		'title'       => __('Obsolete Error', 'servcheck'),
		'description' => __('Not used in modern versions.', 'servcheck')
	),
	25 => array (
		'title'       => 'CURLE_UPLOAD_FAILED',
		'description' => __('Failed starting the upload. For FTP, the server typically denied the STOR command. The error buffer usually contains the server\'s explanation for this.', 'servcheck')
	),
	26 => array (
		'title'       => 'CURLE_READ_ERROR',
		'description' => __('There was a problem reading a local file or an error returned by the read callback.', 'servcheck')
	),
	27 => array (
		'title'       => 'CURLE_OUT_OF_MEMORY',
		'description' => __('A memory allocation request failed. This is serious badness and things are severely screwed up if this ever occurs.', 'servcheck')
	),
	28 => array (
		'title'       => 'CURLE_OPERATION_TIMEDOUT',
		'description' => __('Operation timeout. The specified time-out period was reached according to the conditions.', 'servcheck')
	),
	29 => array (
		'title'       => __('Obsolete Error', 'servcheck'),
		'description' => __('Not used in modern versions.', 'servcheck')
	),
	30 => array (
		'title'       => 'CURLE_FTP_PORT_FAILED',
		'description' => __('The FTP PORT command returned error. This mostly happens when you have not specified a good enough address for libcurl to use. See CURLOPT_FTPPORT.', 'servcheck')
	),
	31 => array (
		'title'       => 'CURLE_FTP_COULDNT_USE_REST',
		'description' => __('The FTP REST command returned error. This should never happen if the server is sane.', 'servcheck')
	),
	32 => array (
		'title'       => __('Obsolete Error', 'servcheck'),
		'description' => __('Not used in modern versions.', 'servcheck')
	),
	33 => array (
		'title'       => 'CURLE_RANGE_ERROR',
		'description' => __('The server does not support or accept range requests.', 'servcheck')
	),
	34 => array (
		'title'       => 'CURLE_HTTP_POST_ERROR (34)',
		'description' => __('This is an odd error that mainly occurs due to internal confusion.', 'servcheck')
	),
	35 => array (
		'title'       => 'CURLE_SSL_CONNECT_ERROR',
		'description' => __('A problem occurred somewhere in the SSL/TLS handshake. You really want the error buffer and read the message there as it pinpoints the problem slightly more. Could be certificates (file formats, paths, permissions), passwords, and others.', 'servcheck')
	),
	36 => array (
		'title'       => 'CURLE_BAD_DOWNLOAD_RESUME',
		'description' => __('The download could not be resumed because the specified offset was out of the file boundary.', 'servcheck')
	),
	37 => array (
		'title'       => 'CURLE_FILE_COULDNT_READ_FILE',
		'description' => __('A file given with FILE:// could not be opened. Most likely because the file path does not identify an existing file. Did you check file permissions?', 'servcheck')
	),
	38 => array (
		'title'       => 'CURLE_LDAP_CANNOT_BIND',
		'description' => __('LDAP cannot bind. LDAP bind operation failed.', 'servcheck')
	),
	39 => array (
		'title'       => 'CURLE_LDAP_SEARCH_FAILED',
		'description' => __('LDAP search failed.', 'servcheck')
	),
	40 => array (
		'title'       => __('Obsolete Error', 'servcheck'),
		'description' => __('Not used in modern versions.', 'servcheck')
	),
	41 => array (
		'title'       => 'CURLE_FUNCTION_NOT_FOUND',
		'description' => __('Function not found. A required zlib function was not found.', 'servcheck')
	),
	42 => array (
		'title'       => 'CURLE_ABORTED_BY_CALLBACK',
		'description' => __('Aborted by callback. A callback returned "abort" to libcurl.', 'servcheck')
	),
	43 => array (
		'title'       => 'CURLE_BAD_FUNCTION_ARGUMENT',
		'description' => __('A function was called with a bad parameter.', 'servcheck')
	),
	44 => array (
		'title'       => __('Obsolete Error', 'servcheck'),
		'description' => __('Not used in modern versions.', 'servcheck')
	),
	45 => array (
		'title'       => 'CURLE_INTERFACE_FAILED',
		'description' => __('Interface error. A specified outgoing interface could not be used. Set which interface to use for outgoing connections\' source IP address with URLOPT_INTERFACE.', 'servcheck')
	),
	46 => array (
		'title'       => __('Obsolete Error', 'servcheck'),
		'description' => __('Not used in modern versions.', 'servcheck')
	),
	47 => array (
		'title'       => 'CURLE_TOO_MANY_REDIRECTS',
		'description' => __('Too many redirects. When following redirects, libcurl hit the maximum amount. Set your limit with CURLOPT_MAXREDIRS.', 'servcheck')
	),
	48 => array (
		'title'       => 'CURLE_UNKNOWN_OPTION',
		'description' => __('An option passed to libcurl is not recognized/known. Refer to the appropriate documentation. This is most likely a problem in the program that uses libcurl. The error buffer might contain more specific information about which exact option it concerns.', 'servcheck')
	),
	49 => array (
		'title'       => 'CURLE_SETOPT_OPTION_SYNTAX',
		'description' => __('An option passed in to a setopt was wrongly formatted. See error message for details about what option.', 'servcheck')
	),
	50 => array (
		'title'       => __('Obsolete Error', 'servcheck'),
		'description' => __('Not used in modern versions.', 'servcheck')
	),
	51 => array (
		'title'       => __('Obsolete Error', 'servcheck'),
		'description' => __('Not used in modern versions.', 'servcheck')
	),
	52 => array (
		'title'       => 'CURLE_GOT_NOTHING',
		'description' => __('Nothing was returned from the server, and under the circumstances, getting nothing is considered an error.', 'servcheck')
	),
	53 => array (
		'title'       => 'CURLE_SSL_ENGINE_NOTFOUND',
		'description' => __('The specified crypto engine was not found.', 'servcheck')
	),
	54 => array (
		'title'       => 'CURLE_SSL_ENGINE_SETFAILED',
		'description' => __('Failed setting the selected SSL crypto engine as default.', 'servcheck')
	),
	55 => array (
		'title'       => 'CURLE_SEND_ERROR',
		'description' => __('Failed sending network data.', 'servcheck')
	),
	56 => array (
		'title'       => 'CURLE_RECV_ERROR',
		'description' => __('Failure with receiving network data.', 'servcheck')
	),
	57 => array (
		'title'       => __('Obsolete Error', 'servcheck'),
		'description' => __('Not used in modern versions.', 'servcheck')
	),
	58 => array (
		'title'       => 'CURLE_SSL_CERTPROBLEM',
		'description' => __('problem with the local client certificate.', 'servcheck')
	),
	59 => array (
		'title'       => 'CURLE_SSL_CIPHER',
		'description' => __('Could not use specified cipher.', 'servcheck')
	),
	60 => array (
		'title'       => 'CURLE_PEER_FAILED_VERIFICATION',
		'description' => __('The remote server\'s SSL certificate or SSH fingerprint was deemed not OK. This error code has been unified with CURLE_SSL_CACERT since 7.62.0. Its previous value was 51.', 'servcheck')
	),
	61 => array (
		'title'       => 'CURLE_BAD_CONTENT_ENCODING',
		'description' => __('Unrecognized transfer encoding.', 'servcheck')
	),
	62 => array (
		'title'       => __('Obsolete Error', 'servcheck'),
		'description' => __('Not used in modern versions.', 'servcheck')
	),
	63 => array (
		'title'       => 'CURLE_FILESIZE_EXCEEDED',
		'description' => __('Maximum file size exceeded.', 'servcheck')
	),
	64 => array (
		'title'       => 'CURLE_USE_SSL_FAILED',
		'description' => __('Requested FTP SSL level failed.', 'servcheck')
	),
	65 => array (
		'title'       => 'CURLE_SEND_FAIL_REWIND',
		'description' => __('When doing a send operation curl had to rewind the data to retransmit, but the rewinding operation failed.', 'servcheck')
	),
	66 => array (
		'title'       => 'CURLE_SSL_ENGINE_INITFAILED',
		'description' => __('Initiating the SSL Engine failed.', 'servcheck')
	),
	67 => array (
		'title'       => 'CURLE_LOGIN_DENIED',
		'description' => __('The remote server denied curl to login (Added in 7.13.1)', 'servcheck')
	),
	68 => array (
		'title'       => 'CURLE_TFTP_NOTFOUND',
		'description' => __('File not found on TFTP server.', 'servcheck')
	),
	69 => array (
		'title'       => 'CURLE_TFTP_PERM',
		'description' => __('Permission problem on TFTP server.', 'servcheck')
	),
	70 => array (
		'title'       => 'CURLE_REMOTE_DISK_FULL',
		'description' => __('Out of disk space on the server.', 'servcheck')
	),
	71 => array (
		'title'       => 'CURLE_TFTP_ILLEGAL',
		'description' => __('Illegal TFTP operation.', 'servcheck')
	),
	72 => array (
		'title'       => 'CURLE_TFTP_UNKNOWNID',
		'description' => __('Unknown TFTP transfer ID.', 'servcheck')
	),
	73 => array (
		'title'       => 'CURLE_REMOTE_FILE_EXISTS',
		'description' => __('File already exists and is not overwritten.', 'servcheck')
	),
	74 => array (
		'title'       => 'CURLE_TFTP_NOSUCHUSER',
		'description' => __('This error should never be returned by a properly functioning TFTP server.', 'servcheck')
	),
	75 => array (
		'title'       => __('Obsolete Error', 'servcheck'),
		'description' => __('Not used in modern versions.', 'servcheck')
	),
	76 => array (
		'title'       => __('Obsolete Error', 'servcheck'),
		'description' => __('Not used in modern versions.', 'servcheck')
	),
	77 => array (
		'title'       => 'CURLE_SSL_CACERT_BADFILE',
		'description' => __('Problem with reading the SSL CA cert (path? access rights?)', 'servcheck')
	),
	78 => array (
		'title'       => 'CURLE_REMOTE_FILE_NOT_FOUND',
		'description' => __('The resource referenced in the URL does not exist.', 'servcheck')
	),
	79 => array (
		'title'       => 'CURLE_SSH',
		'description' => __('An unspecified error occurred during the SSH session.', 'servcheck')
	),
	80 => array (
		'title'       => 'CURLE_SSL_SHUTDOWN_FAILED',
		'description' => __('Failed to shut down the SSL connection.' , 'servcheck')
	),
	81 => array (
		'title'       => 'CURLE_AGAIN',
		'description' => __('Socket is not ready for send/recv wait till it\'s ready and try again. This return code is only returned from curl_easy_recv and curl_easy_send (Added in 7.18.2)', 'servcheck')
	),
	82 => array (
		'title'       => 'CURLE_SSL_CRL_BADFILE',
		'description' => __('Failed to load CRL file (Added in 7.19.0)', 'servcheck')
	),
	83 => array (
		'title'       => 'CURLE_SSL_ISSUER_ERROR',
		'description' => __('Issuer check failed (Added in 7.19.0)', 'servcheck')
	),
	84 => array (
		'title'       => 'CURLE_FTP_PRET_FAILED',
		'description' => __('The FTP server does not understand the PRET command at all or does not support the given argument. Be careful when using CURLOPT_CUSTOMREQUEST, a custom LIST command is sent with the PRET command before PASV as well. (Added in 7.20.0)', 'servcheck')
	),
	85 => array (
		'title'       => 'CURLE_RTSP_CSEQ_ERROR',
		'description' => __('Mismatch of RTSP CSeq numbers.', 'servcheck')
	),
	86 => array (
		'title'       => 'CURLE_RTSP_SESSION_ERROR',
		'description' => __('Mismatch of RTSP Session Identifiers.', 'servcheck')
	),
	87 => array (
		'title'       => 'CURLE_FTP_BAD_FILE_LIST',
		'description' => __('Unable to parse FTP file list (during FTP wildcard downloading).', 'servcheck')
	),
	88 => array (
		'title'       => 'CURLE_CHUNK_FAILED',
		'description' => __('Chunk callback reported error.', 'servcheck')
	),
	89 => array (
		'title'       => 'CURLE_NO_CONNECTION_AVAILABLE',
		'description' => __('(For internal use only, is never returned by libcurl) No connection available, the session is queued. (added in 7.30.0)', 'servcheck')
	),
	90 => array (
		'title'       => 'CURLE_SSL_PINNEDPUBKEYNOTMATCH',
		'description' => __('Failed to match the pinned key specified with CURLOPT_PINNEDPUBLICKEY.', 'servcheck')
	),
	91 => array (
		'title'       => 'CURLE_SSL_INVALIDCERTSTATUS',
		'description' => __('Status returned failure when asked with CURLOPT_SSL_VERIFYSTATUS.', 'servcheck')
	),
	92 => array (
		'title'       => 'CURLE_HTTP2_STREAM',
		'description' => __('Stream error in the HTTP/2 framing layer.', 'servcheck')
	),
	93 => array (
		'title'       => 'CURLE_RECURSIVE_API_CALL',
		'description' => __('An API function was called from inside a callback.', 'servcheck')
	),
	94 => array (
		'title'       => 'CURLE_AUTH_ERROR',
		'description' => __('An authentication function returned an error.', 'servcheck')
	),
	95 => array (
		'title'       => 'CURLE_HTTP3',
		'description' => __('A problem was detected in the HTTP/3 layer. This is somewhat generic and can be one out of several problems, see the error buffer for details.', 'servcheck')
	),
	96 => array (
		'title'       => 'CURLE_QUIC_CONNECT_ERROR',
		'description' => __('QUIC connection error. This error may be caused by an SSL library error. QUIC is the protocol used for HTTP/3 transfers.', 'servcheck')
	),
	97 => array (
		'title'       => 'CURLE_PROXY',
		'description' => __('Proxy handshake error. CURLINFO_PROXY_ERROR provides extra details on the specific problem.', 'servcheck')
	),
	98 => array (
		'title'       => 'CURLE_SSL_CLIENTCERT',
		'description' => __('SSL Client Certificate required.', 'servcheck')
	),
	99 => array (
		'title'       => 'CURLE_UNRECOVERABLE_POLL',
		'description' => __('An internal call to poll() or select() returned error that is not recoverable.', 'servcheck')
	)
);


