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

chdir('../../');
include_once('./include/auth.php');
include_once($config['base_path'] . '/plugins/servcheck/includes/functions.php');
include($config['base_path'] . '/plugins/servcheck/includes/arrays.php');

$servcheck_actions_menu = array(
	1 => __('Delete', 'servcheck'),
	2 => __('Duplicate', 'servcheck'),
);


set_default_action();

switch (get_request_var('action')) {
	case 'save':
		form_save();

		break;
	case 'actions':
		form_actions();

		break;
	case 'edit':
		top_header();
		data_edit();
		bottom_footer();

		break;
	default:
		top_header();
		data_list();
		bottom_footer();

		break;
}


function form_actions() {
	global $servcheck_actions_menu;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9_]+)$/')));
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_filter_request_var('drp_action') == 1) {
				if (cacti_sizeof($selected_items)) {
					foreach($selected_items as $item) {
						db_execute_prepared('DELETE FROM plugin_servcheck_credential WHERE id = ?', array($item));
						db_execute_prepared('UPDATE plugin_servcheck_test SET cred_id = 0 WHERE cred_id = ?', array($item));
						db_execute_prepared('UPDATE plugin_servcheck_proxy SET cred_id = 0 WHERE cred_id = ?', array($item));
					}
				}
			} elseif (get_filter_request_var('drp_action') == 2) { // duplicate
				$newid = 1;

				foreach ($credentials as $id) {
					$save = db_fetch_row_prepared('SELECT * FROM plugin_servcheck_credential WHERE id = ?', array($id));
					$save['id']       = 0;
					$save['name']     = 'New Credential (' . $newid . ')';
					$save['type']     = 'userpass';
					$save['username'] = '';
					$save['password'] = '';

					$id = sql_save($save, 'plugin_servcheck_credential');

					$newid++;
				}
			}
		}

		header('Location: ' . htmlspecialchars(basename($_SERVER['PHP_SELF'])) . '?header=false');
		exit;
	}

	/* setup some variables */
	$item_list  = '';
	$items_array = array();

	/* loop through each of the graphs selected on the previous page and get more info about them */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$item_list .= '<li>' . db_fetch_cell_prepared('SELECT name FROM plugin_servcheck_credential WHERE id = ?', array($matches[1])) . '</li>';
			$items_array[] = $matches[1];
		}
	}

	top_header();

	form_start(htmlspecialchars(basename($_SERVER['PHP_SELF'])));

	html_start_box($servcheck_actions_menu[get_filter_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (cacti_sizeof($items_array) > 0) {
		if (get_filter_request_var('drp_action') == 1) { // delete
			print "	<tr>
					<td class='topBoxAlt'>
						<p>" . __n('Click \'Continue\' to delete the following items.', 'Click \'Continue\' to delete following items.', cacti_sizeof($items_array)) . "</p>
						<div class='itemlist'><ul>$item_list</ul></div>
					</td>
				</tr>";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc_n('Delete item', 'Delete items', cacti_sizeof($items_array)) . "'>";
		} elseif (get_filter_request_var('drp_action') == 2) { // duplicate
			print "<tr>
				<td class='topBoxAlt'>
					<p>" . __n('Click \'Continue\' to Duplicate the following Credential.', 'Click \'Continue\' to Duplicate following Credential.', cacti_sizeof($credential_array)) . "</p><div class='itemlist'><ul>$credential_list</ul></div>
				</td>
			</tr>";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc_n('Duplicate Credential', 'Duplicate Credential', cacti_sizeof($credential_array)) . "'>";
		}
	} else {
		raise_message(40);
		header('Location: ' . htmlspecialchars(basename($_SERVER['PHP_SELF'])) . '?header=false');
		exit;
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($items_array) ? serialize($items_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_request_var('drp_action') . "'>
			$save_html
		</td>
	</tr>";

	html_end_box();

	form_end();

	bottom_footer();
}

function form_save() {
	global $credential_types;

	if (isset_request_var('save_component')) {
		/* ================= input validation ================= */
		get_filter_request_var('id');
		/* ==================================================== */

		$save['id']         = get_nfilter_request_var('id');

		if (isset_request_var('name') && get_nfilter_request_var('name') != '' && get_filter_request_var('name', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^[a-z0-9A-Z_\/@.\- \=,]{1,100}$/')))) {
			$save['name'] = get_nfilter_request_var('name');
		}

		if (isset_request_var('type') && array_key_exists(get_nfilter_request_var('type'), $credential_types)) {
			$save['type'] = get_nfilter_request_var('type');
		} else {
			$_SESSION['sess_error_fields']['type'] = 'type';
			raise_message(3);
		}

		switch(get_nfilter_request_var('type')) {
			case 'userpass':
				if (isset_request_var('username') && get_nfilter_request_var('username') != '' && get_filter_request_var('username', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^[a-z0-9A-Z_\/@.\- \=,]{1,100}$/')))) {
					$cred['username'] = get_nfilter_request_var('username');
				} else {
					$_SESSION['sess_error_fields']['username'] = 'username';
					raise_message(3);
				}

				if (isset_request_var('password') && get_nfilter_request_var('password') != '' && get_filter_request_var('password', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^[a-z0-9A-Z_\/@.\- \=,]{1,100}$/')))) {
					$cred['password'] = get_nfilter_request_var('password');
				} else {
					$_SESSION['sess_error_fields']['password'] = 'password';
					raise_message(3);
				}

				break;


			case 'basic':
				if (isset_request_var('username') && get_nfilter_request_var('username') != '' && get_filter_request_var('username', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^[a-z0-9A-Z_\/@.\- \=,]{1,100}$/')))) {
					$cred['username'] = get_nfilter_request_var('username');
				} else {
					$_SESSION['sess_error_fields']['username'] = 'username';
					raise_message(3);
				}

				if (isset_request_var('password') && get_nfilter_request_var('password') != '' && get_filter_request_var('password', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^[a-z0-9A-Z_\/@.\- \=,]{1,100}$/')))) {
					$cred['password'] = get_nfilter_request_var('password');
				} else {
					$_SESSION['sess_error_fields']['password'] = 'password';
					raise_message(3);
				}

				if (isset_request_var('data_url') && get_nfilter_request_var('data_url') != '' && get_filter_request_var('data_url', FILTER_VALIDATE_URL)) {
					$cred['data_url'] = get_nfilter_request_var('data_url');
				} else {
					$_SESSION['sess_error_fields']['data_url'] = 'data_url';
					raise_message(3);
				}

				break;

			case 'apikey':
				if (isset_request_var('username') && get_nfilter_request_var('username') != '' && get_filter_request_var('username', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^[a-z0-9A-Z_\/@.\- \=,]{1,100}$/')))) {
					$cred['username'] = get_nfilter_request_var('username');
				} else {
					$_SESSION['sess_error_fields']['username'] = 'username';
					raise_message(3);
				}

				if (isset_request_var('password') && get_nfilter_request_var('password') != '' && get_filter_request_var('password', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^[a-z0-9A-Z_\/@.\- \=,]{1,100}$/')))) {
					$cred['password'] = get_nfilter_request_var('password');
				} else {
					$_SESSION['sess_error_fields']['password'] = 'password';
					raise_message(3);
				}

				if (isset_request_var('data_url') && get_nfilter_request_var('data_url') != '' && get_filter_request_var('data_url', FILTER_VALIDATE_URL)) {
					$cred['data_url'] = get_nfilter_request_var('data_url');
				} else {
					$_SESSION['sess_error_fields']['data_url'] = 'data_url';
					raise_message(3);
				}

				break;

			case 'oauth2':

				if (isset_request_var('username') && get_nfilter_request_var('username') != '' && get_filter_request_var('username', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^[a-z0-9A-Z_\/@.\- \=,]{1,100}$/')))) {
					$cred['username'] = get_nfilter_request_var('username');
				} else {
					$_SESSION['sess_error_fields']['username'] = 'username';
					raise_message(3);
				}

				if (isset_request_var('password') && get_nfilter_request_var('password') != '' && get_filter_request_var('password', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^[a-z0-9A-Z_\/@.\- \=,]{1,100}$/')))) {
					$cred['password'] = get_nfilter_request_var('password');
				} else {
					$_SESSION['sess_error_fields']['password'] = 'password';
					raise_message(3);
				}

				if (isset_request_var('data_url') && get_nfilter_request_var('data_url') != '' && get_filter_request_var('data_url', FILTER_VALIDATE_URL)) {
					$cred['data_url'] = get_nfilter_request_var('data_url');
				} else {
					$_SESSION['sess_error_fields']['data_url'] = 'data_url';
					raise_message(3);
				}

				if (isset_request_var('login_url') && get_nfilter_request_var('login_url') != '' && get_filter_request_var('login_url', FILTER_VALIDATE_URL)) {
					$cred['login_url'] = get_nfilter_request_var('login_url');
				} else {
					$_SESSION['sess_error_fields']['login_url'] = 'login_url';
					raise_message(3);
				}

				if (isset_request_var('cred_name') && get_nfilter_request_var('cred_name') != '' && get_filter_request_var('cred_name', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^[a-z0-9A-Z_\/@.\- \=,]{1,30}$/')))) {
					$cred['cred_name'] = get_nfilter_request_var('cred_name');
				} else {
					$_SESSION['sess_error_fields']['cred_name'] = 'cred_name';
					raise_message(3);
				}

				if (isset_request_var('cred_value') && (get_nfilter_request_var('cred_value') != '' && get_filter_request_var('cred_value', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^[a-z0-9A-Z_\/@.\- \=,]{1,250}$/'))))) {
					$cred['cred_value'] = get_nfilter_request_var('cred_value');
				} else {
					$_SESSION['sess_error_fields']['cred_value'] = 'cred_value';
					raise_message(3);
				}

				break;

			case 'cookie':
				if (isset_request_var('username') && get_nfilter_request_var('username') != '' && get_filter_request_var('username', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^[a-z0-9A-Z_\/@.\- \=,]{1,100}$/')))) {
					$cred['username'] = get_nfilter_request_var('username');
				} else {
					$_SESSION['sess_error_fields']['username'] = 'username';
					raise_message(3);
				}

				if (isset_request_var('password') && get_nfilter_request_var('password') != '' && get_filter_request_var('password', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^[a-z0-9A-Z_\/@.\- \=,]{1,100}$/')))) {
					$cred['password'] = get_nfilter_request_var('password');
				} else {
					$_SESSION['sess_error_fields']['password'] = 'password';
					raise_message(3);
				}

				if (isset_request_var('data_url') && get_nfilter_request_var('data_url') != '' && get_filter_request_var('data_url', FILTER_VALIDATE_URL)) {
					$cred['data_url'] = get_nfilter_request_var('data_url');
				} else {
					$_SESSION['sess_error_fields']['data_url'] = 'data_url';
					raise_message(3);
				}

				if (isset_request_var('login_url') && get_nfilter_request_var('login_url') != '' && get_filter_request_var('login_url', FILTER_VALIDATE_URL)) {
					$cred['login_url'] = get_nfilter_request_var('login_url');
				} else {
					$_SESSION['sess_error_fields']['login_url'] = 'login_url';
					raise_message(3);
				}

				break;

			case 'snmp':
				if (isset_request_var('community') && get_nfilter_request_var('community') != '' && get_filter_request_var('community', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^[a-z0-9A-Z_\/@.\- \=,]{1,100}$/')))) {
					$cred['community'] = get_nfilter_request_var('community');
				} else {
					$_SESSION['sess_error_fields']['community'] = 'community';
					raise_message(3);
				}

				break;

			case 'snmp3':

				break;

			case 'sshkey':
				if (isset_request_var('ssh_username') && get_nfilter_request_var('ssh_username') != '' && get_filter_request_var('ssh_username', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^[a-z0-9A-Z_\/@.\-]{1,100}$/')))) {
					$cred['ssh_username'] = get_nfilter_request_var('ssh_username');
				} else {
					$_SESSION['sess_error_fields']['ssh_username'] = 'ssh_username';
					raise_message(3);
				}

				if (isset_request_var('sshkey') && get_nfilter_request_var('sshkey') != '' && get_filter_request_var('sshkey', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^-----BEGIN OPENSSH PRIVATE KEY-----.*/')))) {
					$cred['sshkey'] = get_nfilter_request_var('sshkey');
				} else {
					$_SESSION['sess_error_fields']['sshkey'] = 'sshkey';
					raise_message(3);
				}

				if (isset_request_var('sshkey_passphrase') && get_nfilter_request_var('sshkey_passphrase') != '' && get_filter_request_var('sshkey_passphrase', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^[a-z0-9A-Z_\/@.\- \=,]{1,100}$/')))) {
					$cred['sshkey_passphrase'] = get_nfilter_request_var('sshkey_passphrase');
				} else {
					$_SESSION['sess_error_fields']['sshkey_passphrase'] = 'sshkey_passphrase';
					raise_message(3);
				}

				break;
		}

		$save['data'] = servcheck_encrypt_credential($cred);

		if (!is_error_message()) {
			$saved_id = sql_save($save, 'plugin_servcheck_credential');

			if ($saved_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		if (is_error_message()) {
			header('Location: ' . htmlspecialchars(basename($_SERVER['PHP_SELF'])) . '?header=false&action=edit&id=' . (empty($saved_id) ? get_nfilter_request_var('id') : $saved_id));
		} else {
			header('Location: ' . htmlspecialchars(basename($_SERVER['PHP_SELF'])) . '?header=false');
		}
	}
	exit;
}


function data_edit() {
	global $servcheck_credential_fields;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	$data = array();

	if (!isempty_request_var('id')) {
		$data = db_fetch_row_prepared('SELECT *
			FROM plugin_servcheck_credential
			WHERE id = ?',
			array(get_request_var('id')));

		$header_label = __('Credential [edit: %s]', $data['name']);

		$data += servcheck_decrypt_credential($data['id']);
	} else {
		$header_label = __('Credential [new]');
	}

	form_start(htmlspecialchars(basename($_SERVER['PHP_SELF'])));

	html_start_box($header_label, '100%', true, '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($servcheck_credential_fields, $data)
		)
	);

	form_hidden_box('save_component', '1', '');

	html_end_box(true, true);

	form_save_button(htmlspecialchars(basename($_SERVER['PHP_SELF'])));

	?>
	<script type='text/javascript'>

	$(function() {

		setCredential();
	});

	function setCredential() {
		var credential_type = $('#type').val();

				$('#row_username').hide();
				$('#row_password').hide();
				$('#row_cred_value').hide();
				$('#row_cred_name').hide();
				$('#row_login_url').hide();
				$('#row_data_url').hide();
				$('#row_community').hide();
				$('#row_ssh_username').hide();
				$('#row_sshkey').hide();
				$('#row_sshkey_passphrase').hide();

		switch(credential_type) {
			case 'no':
				break;
			case 'userpass':
				$('#row_username').show();
				$('#row_password').show();
				$('#password').attr('type', 'password');
				break
			case 'basic':
				$('#row_username').show();
				$('#row_password').show();
				$('#row_data_url').hide();
				$('#password').attr('type', 'password');
				break;
			case 'apikey':
				$('#row_username').show();
				$('#row_cred_value').show();
				$('#row_data_url').hide();
				$('#cred_value').attr('type', 'password');
				break;
			case 'oauth2':
				$('#row_username').show();
				$('#row_password').show();
				$('#row_cred_name').show();
				$('#row_cred_value').show();
				$('#row_login_url').show();
				$('#row_data_url').show();
				$('#password').attr('type', 'password');
				$('#cred_value').attr('type', 'password');
				break;
			case 'cookie':
				$('#row_username').show();
				$('#row_password').show();
				$('#row_data_url').hide();
				$('#password').attr('type', 'password');
				break;
			case 'snmp':
				$('#row_community').show();
				break;

			case 'snmp3':
//!!pm dodelat
				break;

			case 'sshkey':
				$('#row_ssh_username').show();
				$('#row_sshkey').show();
				$('#row_sshkey_passphrase').show();
				$('#sshkey_passphrase').attr('type', 'password');
				break;
		}
	}
	</script>
	<?php
}


function request_validation() {

	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
			),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'filter' => array(
			'filter' => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
			),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'name',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			)
	);

	validate_store_request_vars($filters, 'sess_servcheck_credential');
}


function data_list() {
	global $servcheck_actions_menu, $credential_types;

	request_validation();

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_filter_request_var('rows');
	}

	servcheck_show_tab(htmlspecialchars(basename($_SERVER['PHP_SELF'])));

	servcheck_filter();

	$sql_where = '';

	if (get_request_var('filter') != '') {
		$sql_where .= ($sql_where == '' ? 'WHERE ' : ' AND ') . ' name LIKE "%' . get_request_var('filter') . '%" OR hostname LIKE "%' . get_request_var('filter') . '%"';
	}

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$total_rows = db_fetch_cell("SELECT COUNT(id)
		FROM plugin_servcheck_credential
		$sql_where");

	$result = db_fetch_assoc("SELECT * FROM plugin_servcheck_credential
		$sql_where
		$sql_order
		$sql_limit");

	$display_text = array(
		'name' => array(
			'display' => __('Name', 'servcheck'),
			'sort'    => 'ASC'
		),
		'type' => array(
			'display' => __('Type', 'servcheck'),
			'sort'    => 'ASC'
		),
		'used' => array(
			'display' => __('Used', 'servcheck'),
			'sort'    => 'ASC'
		),
	);

	$columns = cacti_sizeof($display_text);

	$nav = html_nav_bar(htmlspecialchars(basename($_SERVER['PHP_SELF'])) . '?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, $columns, __('Proxies', 'servcheck'), 'page', 'main');

	form_start(htmlspecialchars(basename($_SERVER['PHP_SELF'])), 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	if (cacti_sizeof($result)) {
		foreach ($result as $row) {
			$row['used'] = 0;
			$row['used'] += db_fetch_cell_prepared('SELECT COUNT(*) FROM plugin_servcheck_proxy WHERE cred_id = ?',
				array($row['id']));
			$row['used'] += db_fetch_cell_prepared('SELECT COUNT(*) FROM plugin_servcheck_test WHERE cred_id = ?',
				array($row['id']));

			if ($row['used'] == 0) {
				$disabled = false;
			} else {
				$disabled = true;
			}

			form_alternate_row('line' . $row['id'], false, $disabled);

			form_selectable_cell("<a class='linkEditMain' href='" . html_escape(htmlspecialchars(basename($_SERVER['PHP_SELF'])) . '?header=false&action=edit&id=' . $row['id']) . "'>" . $row['name'] . '</a>', $row['id']);
			form_selectable_cell($credential_types[$row['type']], $row['id']);
			form_selectable_cell($row['used'], $row['id']);
			form_checkbox_cell($row['name'], $row['id'], $disabled);

			form_end_row();
		}
	} else {
		print "<tr class='tableRow'><td colspan='" . $columns . "'><em>" . __('Empty', 'servcheck') . "</em></td></tr>\n";
	}

	html_end_box(false);

	if (cacti_sizeof($result)) {
		print $nav;
	}

	draw_actions_dropdown($servcheck_actions_menu, 1);

	form_end();

}

function servcheck_filter() {
	global $item_rows;

	html_start_box(__('Servcheck Credential Management', 'servcheck') , '100%', '', '3', 'center', htmlspecialchars(basename($_SERVER['PHP_SELF'])) . '?action=edit');

	?>
	<tr class='even'>
		<td>
		<form id='form_servcheck_item' action='<?php print htmlspecialchars(basename($_SERVER['PHP_SELF']));?>'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search', 'servcheck');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' name='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Credentials', 'servcheck');?>
					</td>
					<td>
						<select id='rows' onChange='applyFilter()'>
							<?php
							print "<option value='-1'" . (get_request_var('rows') == -1 ? ' selected':'') . ">" . __('Default', 'servcheck') . "</option>";
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . htmlspecialchars($value) . "</option>";
								}
							}
							?>
						</select>
					</td>
					<td>
						<span class='nowrap'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters', 'servcheck');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters', 'servcheck');?>'>
						</span>
					</td>
				</tr>
			</table>
		</form>
		<script type='text/javascript'>

		function applyFilter() {
			strURL  = '<?php print htmlspecialchars(basename($_SERVER['PHP_SELF']));?>?header=false';
			strURL += '&filter=' + $('#filter').val();
			strURL += '&rows=' + $('#rows').val();
			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			strURL = '<?php print htmlspecialchars(basename($_SERVER['PHP_SELF']));?>?clear=1&header=false';
			loadPageNoHeader(strURL);
		}

		$(function() {
			$('#rows').click(function() {
				applyFilter();
			});

			$('#clear').click(function() {
				clearFilter();
			});

			$('#form_servcheck_item').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});
		});

		</script>
		</td>
	</tr>
	<?php
	html_end_box();
}

