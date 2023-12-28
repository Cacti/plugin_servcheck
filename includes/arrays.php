<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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

include_once(__DIR__ . '/constants.php');

global	$servcheck_actions_proxy, $servcheck_actions_test, $servcheck_actions_ca, $graph_interval,
	$servcheck_proxy_fields, $servcheck_test_fields, $servcheck_ca_fields,
	$servcheck_notify_accounts, $httperrors, $servcheck_seconds,
	$search, $mail_serv, $service_types, $curl_error, $search_result;

$search_result = array(
	'ok' => 'String found',
	'not ok' => 'String not found',
	'failed ok' => 'Failed string found',
	'failed not ok' => 'Failed strint not found',
	'maint ok' => 'Maint string found',
	'not yet' => 'Not tested yet',
	'not tested' => 'Search not performed'
);

$service_types = array(

	'web_http'        => __('HTTP plaintext, default port 80', 'servcheck'),
	'web_https'       => __('HTTP encrypted (HTTPS), default port 443', 'servcheck'),

	'mail_smtp'       => __('SMTP plaintext, default port 25 (or 587 for submission)', 'servcheck'),
	'mail_smtptls'    => __('SMTP with STARTTLS, default port 25(or 587 for submission)', 'servcheck'),
	'mail_smtps'      => __('SMTP encrypted (SMTPS), default port 465', 'servcheck'),

	'mail_imap'       => __('IMAP plaintext, default port 143', 'servcheck'),
	'mail_imaptls'    => __('IMAP with STARTTLS, default port 143', 'servcheck'),
	'mail_imaps'      => __('IMAP encrypted (IMAPS), default port 993', 'servcheck'),

	'mail_pop3'       => __('POP3 plaintext, default port 110', 'servcheck'),
	'mail_pop3tls'    => __('POP3 with STARTTLS, default port 110', 'servcheck'),
	'mail_pop3s'      => __('POP3 encrypted (POP3S), default port 995', 'servcheck'),

	'dns_dns'         => __('DNS plaintext, default port 53', 'servcheck'),
//	'dns_doh'         => __('DNS over HTTPS, default port 443', 'servcheck'),

	'ldap_ldap'       => __('LDAP plaintext, default port 389', 'servcheck'),
	'ldap_ldaps'      => __('LDAP encrypted (LDAPS), default port 636', 'servcheck'),

	'ftp_ftp'         => __('FTP plaintext, default port 21', 'servcheck'),
	'ftp_ftps'        => __('FTP encrypted (FTPS), default port 990', 'servcheck'),
	'ftp_scp'         => __('SCP download file, default port 22', 'servcheck'),
	'ftp_tftp'        => __('TFTP protocol - download file, default port 69', 'servcheck'),

	'smb_smb'         => __('SMB plaintext download file, default port 445', 'servcheck'),
	'smb_smbs'        => __('SMB encrypted (SMBS) download file, default port 445', 'servcheck'),

//	'telnet_telnet'   => __('Telnet plaintext, default port 23', 'servcheck'),
//	'mqtt_mqtt'       => __('MQTT - default port 80', 'servcheck'),
);

$service_types_ports = array(

	'web_http'        => 80,
	'web_https'       => 443,

	'mail_smtp'       => 25,
	'mail_smtptls'    => 25,
	'mail_smtps'      => 465,

	'mail_imap'       => 143,
	'mail_imaptls'    => 143,
	'mail_imaps'      => 993,

	'mail_pop3'       => 110,
	'mail_pop3tls'    => 110,
	'mail_pop3s'      => 995,

	'dns_dns'         => 53,
//	'dns_doh'         => 443,

	'ldap_ldap'       => 389,
	'ldap_ldaps'      => 636,

	'ftp_ftp'         => 21,
	'ftp_ftps'        => 990,
	'ftp_scp'         => 22,
	'ftp_tftp'        => 69,

	'smb_smb'         => 389,
	'smb_smbs'        => 636,

//	'telnet_telnet'   => 23,
//	'mqtt_mqtt'       => 80,
);


$graph_interval = array (
	  1 => __('Last hour', 'servcheck'),
	  6 => __('Last 6 hours', 'servcheck'),
	 24 => __('Last day', 'servcheck'),
	168 => __('Last week', 'servcheck'),
);


$httperrors = array(
	  0 => 'Unable to Connect',
	100 => 'Continue',
	101 => 'Switching Protocols',
	200 => 'OK',
	201 => 'Created',
	202 => 'Accepted',
	203 => 'Non-Authoritative Information',
	204 => 'No Content',
	205 => 'Reset Content',
	206 => 'Partial Content',
	300 => 'Multiple Choices',
	301 => 'Moved Permanently',
	302 => 'Found',
	303 => 'See Other',
	304 => 'Not Modified',
	305 => 'Use Proxy',
	306 => '(Unused)',
	307 => 'Temporary Redirect',
	400 => 'Bad Request',
	401 => 'Unauthorized',
	402 => 'Payment Required',
	403 => 'Forbidden',
	404 => 'Not Found',
	405 => 'Method Not Allowed',
	406 => 'Not Acceptable',
	407 => 'Proxy Authentication Required',
	408 => 'Request Timeout',
	409 => 'Conflict',
	410 => 'Gone',
	411 => 'Length Required',
	412 => 'Precondition Failed',
	413 => 'Request Entity Too Large',
	414 => 'Request-URI Too Long',
	415 => 'Unsupported Media Type',
	416 => 'Requested Range Not Satisfiable',
	417 => 'Expectation Failed',
	500 => 'Internal Server Error',
	501 => 'Not Implemented',
	502 => 'Bad Gateway',
	503 => 'Service Unavailable',
	504 => 'Gateway Timeout',
	505 => 'HTTP Version Not Supported',
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
	SERVCHECK_FORMAT_HTML  => 'html',
	SERVCHECK_FORMAT_PLAIN => 'plain',
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
	SERVCHECK_ACTION_PROXY_DELETE => __('Delete', 'servcheck'),
);

$servcheck_actions_ca = array(
	SERVCHECK_ACTION_CA_DELETE => __('Delete', 'servcheck'),
);

$servcheck_actions_test = array(
	SERVCHECK_ACTION_TEST_DELETE    => __('Delete', 'servcheck'),
	SERVCHECK_ACTION_TEST_DISABLE   => __('Disable', 'servcheck'),
	SERVCHECK_ACTION_TEST_ENABLE    => __('Enable', 'servcheck'),
	SERVCHECK_ACTION_TEST_DUPLICATE => __('Duplicate', 'servcheck'),
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
		'friendly_name' => __('CA chain', 'servcheck'),
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
	'username' => array(
		'method' => 'textbox',
		'friendly_name' => __('User Name'),
		'description' => __('The user to use to authenticate with the Proxy if any.'),
		'value' => '|arg1:username|',
		'max_length' => '64',
		'size' => '40',
		'default' => ''
	),
	'password' => array(
		'method' => 'textbox_password',
		'friendly_name' => __('Password'),
		'description' => __('The user password to use to authenticate with the Proxy if any.'),
		'value' => '|arg1:password|',
		'max_length' => '40',
		'size' => '40',
		'default' => ''
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
		'description' => __('Uncheck this box to disabled this test from being checked.', 'servcheck'),
		'value' => '|arg1:enabled|',
		'default' => 'on',
	),
	'poller' => array(
		'friendly_name' => __('Poller', 'servcheck'),
		'method' => 'drop_sql',
		'default' => 1,
		'description' => __('Poller on which the test will run', 'servcheck'),
		'value' => '|arg1:poller|',
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
		'description' => __('You can specify another port (example.com:5000) otherwise default will be used', 'servcheck'),
		'value' => '|arg1:hostname|',
		'max_length' => '40',
		'size' => '30'
	),
	'service_spacer' => array(
		'method' => 'spacer',
		'friendly_name' => __('Service settings', 'servcheck')
	),
	'ca' => array(
		'friendly_name' => __('CA chain', 'servcheck'),
		'method' => 'drop_sql',
		'none_value' => __('None', 'servcheck'),
		'default' => '0',
		'description' => __('Your own CA and intermediate certs', 'servcheck'),
		'value' => '|arg1:ca|',
		'sql' => 'SELECT id, name FROM plugin_servcheck_ca ORDER by name'
	),
	'username' => array(
		'friendly_name' => __('Username', 'servcheck'),
		'method' => 'textbox',
		'description' => __('With authentication the test gains more information. For LDAP something like cn=John Doe,OU=anygroup,DC=example,DC=com. For smb use DOMAIN/user', 'servcheck'),
		'value' => '|arg1:username|',
		'max_length' => '100',
		'size' => '30'
	),
	'password' => array(
		'friendly_name' => __('Password', 'servcheck'),
		'method' => 'textbox',
		'description' => __('For anonymous ftp insert email address here.', 'servcheck'),
		'value' => '|arg1:password|',
		'max_length' => '100',
		'size' => '30'
	),
	'ldapsearch' => array(
		'friendly_name' => __('LDAP search', 'servcheck'),
		'method' => 'textbox',
		'description' => __('LDAP search filter, it could be ,OU=anygroup,DC=example,DC=com', 'servcheck'),
		'value' => '|arg1:ldapsearch|',
		'max_length' => '200',
		'size' => '50'
	),
	'dns_query' => array(
		'method' => 'textbox',
		'friendly_name' => __('DNS name for query', 'servcheck'),
		'description' => __('DNS name for querying', 'servcheck'),
		'value' => '|arg1:dns_query|',
		'max_length' => '40',
		'size' => '30'
	),
	'path' => array(
		'method' => 'textbox',
		'friendly_name' => __('path part of url', 'servcheck'),
		'description' => __('For web service insert at least "/" or something like "/any/path/". For FTP listing must end with char "/". For TFTP/SCP/SMB download test insert /path/file', 'servcheck'),
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
		'description' => __('If using SSL, check this box if you want to check the certificate expiration. You will be warn when last 10 days left.', 'servcheck'),
		'value' => '|arg1:certexpirenotify|',
		'default' => '',
	),
	'timings_spacer' => array(
		'method' => 'spacer',
		'friendly_name' => __('Notification Timing', 'servcheck')
	),
	'how_often' => array(
		'friendly_name' => __('How often test', 'servcheck'),
		'method' => 'drop_array',
		'array' => $servcheck_cycles,
		'default' => 1,
		'description' => __('Test each poller cycle or less', 'servcheck'),
		'value' => '|arg1:how_often|',
	),
	'downtrigger' => array(
		'friendly_name' => __('Trigger', 'servcheck'),
		'method' => 'drop_array',
		'array' => $servcheck_cycles,
		'default' => 1,
		'description' => __('How many poller cycles must be down before it will send an alert. After an alert is sent, in order for a \'Site Recovering\' Email to be send, it must also be up this number of poller cycles.', 'servcheck'),
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
	'id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:id|'
	),
);

$curl_error = array(
	0 => array (
		'title' => 'CURLE_OK',
		'description' => 'All fine. Proceed as usual.'
	),
	1 => array (
		'title' => 'CURLE_UNSUPPORTED_PROTOCOL',
		'description' => 'The URL you passed to libcurl used a protocol that this libcurl does not support. The support might be a compile-time option that you did not use, it 				
			can be a misspelled protocol string or just a protocol libcurl has no code for.'
	),
	2 => array (
		'title' => 'CURLE_FAILED_INIT',
		'description' => 'Early initialization code failed. This is likely to be an internal error or problem, or a resource problem where something fundamental could not get 
			done at init time.'
	),
	3 => array (
		'title' => 'CURLE_URL_MALFORMAT',
		'description' => 'The URL was not properly formatted.'
	),
	4 => array (
		'title' => 'CURLE_NOT_BUILT_IN',
		'description' => 'A requested feature, protocol or option was not found built-in in this libcurl due to a build-time decision. This means that a feature or option was not 
			enabled or explicitly disabled when libcurl was built and in order to get it to function you have to get a rebuilt libcurl.'
	),
	5 => array (
		'title' => 'CURLE_COULDNT_RESOLVE_PROXY',
		'description' => 'Could not resolve proxy. The given proxy host could not be resolved.'
	),
	6 => array (
		'title' => 'CURLE_COULDNT_RESOLVE_HOST',
		'description' => 'Could not resolve host. The given remote host was not resolved.'
	),
	7 => array (
		'title' => 'CURLE_COULDNT_CONNECT',
		'description' => 'Failed to connect() to host or proxy.'
	),
	8 => array (
		'title' => 'CURLE_WEIRD_SERVER_REPLY',
		'description' => 'The server sent data libcurl could not parse. This error code was known as CURLE_FTP_WEIRD_SERVER_REPLY before 7.51.0.'
	),
	9 => array (
		'title' => 'CURLE_REMOTE_ACCESS_DENIED',
		'description' => 'We were denied access to the resource given in the URL. For FTP, this occurs while trying to change to the remote directory.'
	),
	10 => array (
		'title' => 'CURLE_FTP_ACCEPT_FAILED',
		'description' => 'While waiting for the server to connect back when an active FTP session is used, an error code was sent over the control connection or similar.'
	),
	11 => array (
		'title' => 'CURLE_FTP_WEIRD_PASS_REPLY',
		'description' => 'After having sent the FTP password to the server, libcurl expects a proper reply. This error code indicates that an unexpected code was returned.'
	),
	12 => array (
		'title' => 'CURLE_FTP_ACCEPT_TIMEOUT',
		'description' => 'During an active FTP session while waiting for the server to connect, the CURLOPT_ACCEPTTIMEOUT_MS (or the internal default) timeout expired.'
	),
	13 => array (
		'title' => 'CURLE_FTP_WEIRD_PASV_REPLY',
		'description' => 'Libcurl failed to get a sensible result back from the server as a response to either a PASV or a EPSV command. The server is flawed.'
	),
	14 => array (
		'title' => 'CURLE_FTP_WEIRD_227_FORMAT',
		'description' => 'FTP servers return a 227-line as a response to a PASV command. If libcurl fails to parse that line, this return code is passed back.'
	),
	15 => array (
		'title' => 'CURLE_FTP_CANT_GET_HOST',
		'description' => 'An internal failure to lookup the host used for the new connection.'
	),
	16 => array (
		'title' => 'CURLE_HTTP2',
		'description' => 'A problem was detected in the HTTP2 framing layer. This is somewhat generic and can be one out of several problems, see the error buffer for details.'
	),
	17 => array (
		'title' => 'CURLE_FTP_COULDNT_SET_TYPE',
		'description' => 'Received an error when trying to set the transfer mode to binary or ASCII.'
	),
	18 => array (
		'title' => 'CURLE_PARTIAL_FILE',
		'description' => 'A file transfer was shorter or larger than expected. This happens when the server first reports an expected transfer size, and then delivers data that 
			does not match the previously given size.'
	),
	19 => array (
		'title' => 'CURLE_FTP_COULDNT_RETR_FILE',
		'description' => 'This was either a weird reply to a \'RETR\' command or a zero byte transfer complete.'
	),
	20 => array (
		'title' => 'Obsolete error',
		'description' => 'Not used in modern versions.'
	),
	21 => array (
		'title' => 'CURLE_QUOTE_ERROR',
		'description' => 'When sending custom "QUOTE" commands to the remote server, one of the commands returned an error code that was 400 or higher (for FTP) or otherwise 	
			indicated unsuccessful completion of the command.'
	),
	22 => array (
		'title' => 'CURLE_HTTP_RETURNED_ERROR',
		'description' => 'This is returned if CURLOPT_FAILONERROR is set TRUE and the HTTP server returns an error code that is >= 400.'
	),
	23 => array (
		'title' => 'CURLE_WRITE_ERROR',
		'description' => 'An error occurred when writing received data to a local file, or an error was returned to libcurl from a write callback.'
	),
	24 => array (
		'title' => 'Obsolete error',
		'description' => 'Not used in modern versions.'
	),
	25 => array (
		'title' => 'CURLE_UPLOAD_FAILED',
		'description' => 'Failed starting the upload. For FTP, the server typically denied the STOR command. The error buffer usually contains the server\'s explanation for this.'
	),
	26 => array (
		'title' => 'CURLE_READ_ERROR',
		'description' => 'There was a problem reading a local file or an error returned by the read callback.'
	),
	27 => array (
		'title' => 'CURLE_OUT_OF_MEMORY',
		'description' => 'A memory allocation request failed. This is serious badness and things are severely screwed up if this ever occurs.'
	),
	28 => array (
		'title' => 'CURLE_OPERATION_TIMEDOUT',
		'description' => 'Operation timeout. The specified time-out period was reached according to the conditions.'
	),
	29 => array (
		'title' => 'Obsolete error',
		'description' => 'Not used in modern versions.'
	),
	30 => array (
		'title' => 'CURLE_FTP_PORT_FAILED',
		'description' => 'The FTP PORT command returned error. This mostly happens when you have not specified a good enough address for libcurl to use. See CURLOPT_FTPPORT.'
	),
	31 => array (
		'title' => 'CURLE_FTP_COULDNT_USE_REST',
		'description' => 'The FTP REST command returned error. This should never happen if the server is sane.'
	),
	32 => array (
		'title' => 'Obsolete error',
		'description' => 'Not used in modern versions.'
	),
	33 => array (
		'title' => 'CURLE_RANGE_ERROR',
		'description' => 'The server does not support or accept range requests.'
	),
	34 => array (
		'title' => 'CURLE_HTTP_POST_ERROR (34)',
		'description' => 'This is an odd error that mainly occurs due to internal confusion.'
	),
	35 => array (
		'title' => 'CURLE_SSL_CONNECT_ERROR',
		'description' => 'A problem occurred somewhere in the SSL/TLS handshake. You really want the error buffer and read the message there as it pinpoints the problem slightly 					
			more. Could be certificates (file formats, paths, permissions), passwords, and others.'
	),
	36 => array (
		'title' => 'CURLE_BAD_DOWNLOAD_RESUME',
		'description' => 'The download could not be resumed because the specified offset was out of the file boundary.'
	),
	37 => array (
		'title' => 'CURLE_FILE_COULDNT_READ_FILE',
		'description' => 'A file given with FILE:// could not be opened. Most likely because the file path does not identify an existing file. Did you check file permissions?'
	),
	38 => array (
		'title' => 'CURLE_LDAP_CANNOT_BIND',
		'description' => 'LDAP cannot bind. LDAP bind operation failed.'
	),
	39 => array (
		'title' => 'CURLE_LDAP_SEARCH_FAILED',
		'description' => 'LDAP search failed.'
	),
	40 => array (
		'title' => 'Obsolete error',
		'description' => 'Not used in modern versions.'
	),
	41 => array (
		'title' => 'CURLE_FUNCTION_NOT_FOUND',
		'description' => 'Function not found. A required zlib function was not found.'
	),
	42 => array (
		'title' => 'CURLE_ABORTED_BY_CALLBACK',
		'description' => 'Aborted by callback. A callback returned "abort" to libcurl.'
	),
	43 => array (
		'title' => 'CURLE_BAD_FUNCTION_ARGUMENT',
		'description' => 'A function was called with a bad parameter.'
	),
	44 => array (
		'title' => 'Obsolete error',
		'description' => 'Not used in modern versions.'
	),
	45 => array (
		'title' => 'CURLE_INTERFACE_FAILED',
		'description' => 'Interface error. A specified outgoing interface could not be used. Set which interface to use for outgoing connections\' source IP address with 
			URLOPT_INTERFACE.'
	),
	46 => array (
		'title' => 'Obsolete error',
		'description' => 'Not used in modern versions.'
	),
	47 => array (
		'title' => 'CURLE_TOO_MANY_REDIRECTS',
		'description' => 'Too many redirects. When following redirects, libcurl hit the maximum amount. Set your limit with CURLOPT_MAXREDIRS.'
	),
	48 => array (
		'title' => 'CURLE_UNKNOWN_OPTION',
		'description' => 'An option passed to libcurl is not recognized/known. Refer to the appropriate documentation. This is most likely a problem in the program that uses 
			libcurl. The error buffer might contain more specific information about which exact option it concerns.'
	),
	49 => array (
		'title' => 'CURLE_SETOPT_OPTION_SYNTAX',
		'description' => 'An option passed in to a setopt was wrongly formatted. See error message for details about what option.'
	),
	50 => array (
		'title' => 'Obsolete errors',
		'description' => 'Not used in modern versions.'
	),
	51 => array (
		'title' => 'Obsolete errors',
		'description' => 'Not used in modern versions.'
	),
	52 => array (
		'title' => 'CURLE_GOT_NOTHING',
		'description' => 'Nothing was returned from the server, and under the circumstances, getting nothing is considered an error.'
	),
	53 => array (
		'title' => 'CURLE_SSL_ENGINE_NOTFOUND',
		'description' => 'The specified crypto engine was not found.'
	),
	54 => array (
		'title' => 'CURLE_SSL_ENGINE_SETFAILED',
		'description' => 'Failed setting the selected SSL crypto engine as default.'
	),
	55 => array (
		'title' => 'CURLE_SEND_ERROR',
		'description' => 'Failed sending network data.'
	),
	56 => array (
		'title' => 'CURLE_RECV_ERROR',
		'description' => 'Failure with receiving network data.'
	),
	57 => array (
		'title' => 'Obsolete error',
		'description' => 'Not used in modern versions.'
	),
	58 => array (
		'title' => 'CURLE_SSL_CERTPROBLEM',
		'description' => 'problem with the local client certificate.'
	),
	59 => array (
		'title' => 'CURLE_SSL_CIPHER',
		'description' => 'Could not use specified cipher.'
	),
	60 => array (
		'title' => 'CURLE_PEER_FAILED_VERIFICATION',
		'description' => 'The remote server\'s SSL certificate or SSH fingerprint was deemed not OK. This error code has been unified with CURLE_SSL_CACERT since 7.62.0. Its 
			previous value was 51.'
	),
	61 => array (
		'title' => 'CURLE_BAD_CONTENT_ENCODING',
		'description' => 'Unrecognized transfer encoding.'
	),
	62 => array (
		'title' => 'Obsolete error',
		'description' => 'Not used in modern versions.'
	),
	63 => array (
		'title' => 'CURLE_FILESIZE_EXCEEDED',
		'description' => 'Maximum file size exceeded.'
	),
	64 => array (
		'title' => 'CURLE_USE_SSL_FAILED',
		'description' => 'Requested FTP SSL level failed.'
	),
	65 => array (
		'title' => 'CURLE_SEND_FAIL_REWIND',
		'description' => 'When doing a send operation curl had to rewind the data to retransmit, but the rewinding operation failed.'
	),
	66 => array (
		'title' => 'CURLE_SSL_ENGINE_INITFAILED',
		'description' => 'Initiating the SSL Engine failed.'
	),
	67 => array (
		'title' => 'CURLE_LOGIN_DENIED',
		'description' => 'The remote server denied curl to login (Added in 7.13.1)'
	),
	68 => array (
		'title' => 'CURLE_TFTP_NOTFOUND',
		'description' => 'File not found on TFTP server.'
	),
	69 => array (
		'title' => 'CURLE_TFTP_PERM',
		'description' => 'Permission problem on TFTP server.'
	),
	70 => array (
		'title' => 'CURLE_REMOTE_DISK_FULL',
		'description' => 'Out of disk space on the server.'
	),
	71 => array (
		'title' => 'CURLE_TFTP_ILLEGAL',
		'description' => 'Illegal TFTP operation.'
	),
	72 => array (
		'title' => 'CURLE_TFTP_UNKNOWNID',
		'description' => 'Unknown TFTP transfer ID.'
	),
	73 => array (
		'title' => 'CURLE_REMOTE_FILE_EXISTS',
		'description' => 'File already exists and is not overwritten.'
	),
	74 => array (
		'title' => 'CURLE_TFTP_NOSUCHUSER',
		'description' => 'This error should never be returned by a properly functioning TFTP server.'
	),
	75 => array (
		'title' => 'Obsolete error',
		'description' => 'Not used in modern versions.'
	),
	76 => array (
		'title' => 'Obsolete error',
		'description' => 'Not used in modern versions.'
	),
	77 => array (
		'title' => 'CURLE_SSL_CACERT_BADFILE',
		'description' => 'Problem with reading the SSL CA cert (path? access rights?)'
	),
	78 => array (
		'title' => 'CURLE_REMOTE_FILE_NOT_FOUND',
		'description' => 'The resource referenced in the URL does not exist.'
	),
	79 => array (
		'title' => 'CURLE_SSH',
		'description' => 'An unspecified error occurred during the SSH session.'
	),
	80 => array (
		'title' => 'CURLE_SSL_SHUTDOWN_FAILED',
		'description' => 'Failed to shut down the SSL connection.'
	),
	81 => array (
		'title' => 'CURLE_AGAIN',
		'description' => 'Socket is not ready for send/recv wait till it\'s ready and try again. This return code is only returned from curl_easy_recv and curl_easy_send (Added in 
			7.18.2)'
	),
	82 => array (
		'title' => 'CURLE_SSL_CRL_BADFILE',
		'description' => 'Failed to load CRL file (Added in 7.19.0)'
	),
	83 => array (
		'title' => 'CURLE_SSL_ISSUER_ERROR',
		'description' => 'Issuer check failed (Added in 7.19.0)'
	),
	84 => array (
		'title' => 'CURLE_FTP_PRET_FAILED',
		'description' => 'The FTP server does not understand the PRET command at all or does not support the given argument. Be careful when using CURLOPT_CUSTOMREQUEST, a custom 
			LIST command is sent with the PRET command before PASV as well. (Added in 7.20.0)'
	),
	85 => array (
		'title' => 'CURLE_RTSP_CSEQ_ERROR',
		'description' => 'Mismatch of RTSP CSeq numbers.'
	),
	86 => array (
		'title' => 'CURLE_RTSP_SESSION_ERROR',
		'description' => 'Mismatch of RTSP Session Identifiers.'
	),
	87 => array (
		'title' => 'CURLE_FTP_BAD_FILE_LIST',
		'description' => 'Unable to parse FTP file list (during FTP wildcard downloading).'
	),
	88 => array (
		'title' => 'CURLE_CHUNK_FAILED',
		'description' => 'Chunk callback reported error.'
	),
	89 => array (
		'title' => 'CURLE_NO_CONNECTION_AVAILABLE',
		'description' => '(For internal use only, is never returned by libcurl) No connection available, the session is queued. (added in 7.30.0)'
	),
	90 => array (
		'title' => 'CURLE_SSL_PINNEDPUBKEYNOTMATCH',
		'description' => 'Failed to match the pinned key specified with CURLOPT_PINNEDPUBLICKEY.'
	),
	91 => array (
		'title' => 'CURLE_SSL_INVALIDCERTSTATUS',
		'description' => 'Status returned failure when asked with CURLOPT_SSL_VERIFYSTATUS.'
	),
	92 => array (
		'title' => 'CURLE_HTTP2_STREAM',
		'description' => 'Stream error in the HTTP/2 framing layer.'
	),
	93 => array (
		'title' => 'CURLE_RECURSIVE_API_CALL',
		'description' => 'An API function was called from inside a callback.'
	),
	94 => array (
		'title' => 'CURLE_AUTH_ERROR',
		'description' => 'An authentication function returned an error.'
	),
	95 => array (
		'title' => 'CURLE_HTTP3',
		'description' => 'A problem was detected in the HTTP/3 layer. This is somewhat generic and can be one out of several problems, see the error buffer for details.'
	),
	96 => array (
		'title' => 'CURLE_QUIC_CONNECT_ERROR',
		'description' => 'QUIC connection error. This error may be caused by an SSL library error. QUIC is the protocol used for HTTP/3 transfers.'
	),
	97 => array (
		'title' => 'CURLE_PROXY',
		'description' => 'Proxy handshake error. CURLINFO_PROXY_ERROR provides extra details on the specific problem.'
	),
	98 => array (
		'title' => 'CURLE_SSL_CLIENTCERT',
		'description' => 'SSL Client Certificate required.'
	),
	99 => array (
		'title' => 'CURLE_UNRECOVERABLE_POLL',
		'description' => 'An internal call to poll() or select() returned error that is not recoverable. '
	)
);


