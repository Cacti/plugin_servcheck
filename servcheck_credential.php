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
include($config['base_path'] . '/plugins/servcheck/includes/arrays.php');
include_once($config['base_path'] . '/plugins/servcheck/includes/functions.php');

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
		servcheck_edit_credential();
		bottom_footer();

		break;
	default:
		list_credentials();

		break;
}

exit;

function form_actions() {
	global $servcheck_actions_credential;


	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));
		$action         = get_nfilter_request_var('drp_action');

		if ($selected_items != false) {
			if (cacti_sizeof($selected_items)) {
				foreach($selected_items as $row) {
					$credentials[] = $row;
				}
			}

			if (cacti_sizeof($credentials)) {
				if ($action == 'delete') {
					foreach ($credentials as $id) {
//!!pm - testovat, zda je smazatelne?
						db_execute_prepared('DELETE FROM plugin_servcheck_credential WHERE id = ?', array($id));
//!!pm - tady to bude na vice mistech? asi v testech a restapi, mozna i proxy?
						db_execute_prepared('UPDATE plugin_servcheck_test SET credential_id = 0  WHERE credential_id = ?', array($id));
					}
				} elseif ($action == 'duplicate') {
					$newid = 1;

					foreach ($credentials as $id) {
						$save = db_fetch_row_prepared('SELECT * FROM plugin_servcheck_credential WHERE id = ?', array($id));
						$save['id']           = 0;
						$save['name']         = 'New Credential (' . $newid . ')';
						$save['type']         = 'upserpass';
						$save['username']     = '';
						$save['password']     = '';

						$id = sql_save($save, 'plugin_servcheck_credential');

						$newid++;
					}
				}
			}
		}

		header('Location: servcheck_credential.php?header=false');

		exit;
	}

	/* setup some variables */
	$credential_list  = '';
	$credential_array = array();

	/* loop through each of the credentials selected on the previous page and get more info about them */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$credential_list .= '<li>' . __esc(db_fetch_cell_prepared('SELECT name FROM plugin_servcheck_credential WHERE id = ?', array($matches[1]))) . '</li>';
			$credential_array[] = $matches[1];
		}
	}

	top_header();

	form_start('servcheck_credential.php');

	html_start_box($servcheck_actions_credential[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

	$action = get_nfilter_request_var('drp_action');

	if (cacti_sizeof($credential_array)) {
		if ($action == 'delete') {
			print"	<tr>
				<td class='topBoxAlt'>
					<p>" . __n('Click \'Continue\' to Delete the following Credentail.', 'Click \'Continue\' to Delete following Credential.', cacti_sizeof($credential_array)) . "</p>
					<div class='itemlist'><ul>$credential_list</ul></div>
				</td>
			</tr>";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc_n('Delete Credential', 'Delete Credential', cacti_sizeof($credential_array)) . "'>";
		} elseif ($action == 'duplicate') {
			print "<tr>
				<td class='topBoxAlt'>
					<p>" . __n('Click \'Continue\' to Duplicate the following Credential.', 'Click \'Continue\' to Duplicate following Credential.', cacti_sizeof($credential_array)) . "</p>
					<div class='itemlist'><ul>$credential_list</ul></div>
				</td>
			</tr>";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc_n('Duplicate Credential', 'Duplicate Credential', cacti_sizeof($credential_array)) . "'>";
		}
	} else {
		raise_message(40);
		header('Location: servcheck_credential.php');
		exit;
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($credential_array) ? serialize($credential_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_nfilter_request_var('drp_action') . "'>
			$save_html
		</td>
	</tr>";

	html_end_box();

	form_end();

	bottom_footer();
}

function form_save() {
	global $credential_types;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	if (isset_request_var('id')) {
		$save['id'] = get_request_var('id');
	} else {
		$save['id'] = 0;
	}

	if (isset_request_var('type') && array_key_exists(get_nfilter_request_var('type'), $credential_types)) {
		$save['type'] = get_nfilter_request_var('type');
	} else {
		$_SESSION['sess_error_fields']['type'] = 'type';
		raise_message(3);
	}

	if (isset_request_var('name') && get_nfilter_request_var('name') != '' && get_filter_request_var('name', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^[a-z0-9A-Z_\/@.\- \=,]{1,100}$/')))) {
		$save['name'] = get_nfilter_request_var('name');
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

			if (isset_request_var('data_url') && get_nfilter_request_var('data_url') != '' && get_filter_request_var('data_url', FILTER_VALIDATE_URL)) {
				$cred['data_url'] = get_nfilter_request_var('data_url');
			} else {
				$_SESSION['sess_error_fields']['data_url'] = 'data_url';
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

	}

	$save['data'] = servcheck_encrypt_credential($cred);

	if (!is_error_message()) {
		$id = sql_save($save, 'plugin_servcheck_credential', 'id');

		if ($id) {
			raise_message(1);
		} else {
			raise_message(2);
		}
	}

	header('Location: servcheck_credential.php?action=edit&id=' . (isset($id)? $id : get_request_var('id')) . '&header=false');
	exit;
}

function servcheck_edit_credential() {
	global $servcheck_credential_fields;
	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	$credential = array();

	if (!isempty_request_var('id')) {
		$credential = db_fetch_row_prepared('SELECT * FROM plugin_servcheck_credential WHERE id = ?', array(get_request_var('id')), false);
		$header_label = __('Query [edit: %s]', $credential['name'], 'servcheck');
	} else {
		$header_label = __('Query [new]', 'servcheck');
	}

	if (isset($credential['id'])) {
		$credential += servcheck_decrypt_credential($credential['id']);

/*
tohle tu asi nebude
		switch($credential['type']) {
			case 'userpass':
				$credential['username'] = 
				break;
			case 'basic':
			
				break;
			case 'apikey':
			
				break;
			case 'oauth2':
			
				break;
			case 'cookie
			
				break;
			case 'snmp':
			
				break;
			case 'snmp3':
			
				break;
			case 'sshkey':
			
				break;
		}
*/
	}


	form_start('servcheck_credential.php');
	html_start_box($header_label, '100%', '', '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('form_name' => 'chk'),
			'fields' => inject_form_variables($servcheck_credential_fields, $credential)
		)
	);

	html_end_box();

	form_save_button('servcheck_credential.php', 'return');

	form_end();
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
//!!pm dodelat
				break;
		}
	}
	</script>
	<?php
}

/**
 *  This is a generic function for this page that makes sure that
 *  we have a good request.  We want to protect against people who
 *  like to create issues with Cacti.
*/
function servcheck_request_validation() {
	/* ================= input validation and session storage ================= */
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
		'rfilter' => array(
			'filter' => FILTER_VALIDATE_IS_REGEX,
			'default' => '',
			'pageset' => true,
			'options' => array('options' => 'sanitize_search_string')
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
		),
	);

	validate_store_request_vars($filters, 'sess_servcheck_credential');
	/* ================= input validation ================= */
}


function list_credentials() {
	global $servcheck_actions_credential, $config, $credential_types;

	servcheck_request_validation();

	top_header();

	servcheck_show_tab('servcheck_credential.php');

	servcheck_credential_filter();

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	$sql_where = '';

	$sql_order = get_order_string();


	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	if (get_request_var('type') && array_key_exists(get_request_var('type'), $credential_types)) {
		$sql_where .= ($sql_where == '' ? 'WHERE ' : ' AND ') .
			'type = "' . get_request_var('type') . '"';
	} else {
		set_request_var('type', -1);
	}

	if (get_request_var('rfilter') != '') {
		$sql_where .= ($sql_where == '' ? 'WHERE ' : ' AND ') .
			'name RLIKE \'' . get_request_var('rfilter') . '\' OR ' .
			'type RLIKE \'' . get_request_var('rfilter') . '\' OR ' .
			'username RLIKE \'' . get_request_var('rfilter') . '\' OR ';
	}

	$result = db_fetch_assoc("SELECT *
		FROM plugin_servcheck_credential
		$sql_where
		$sql_order
		$sql_limit");

	$total_rows = db_fetch_cell("SELECT COUNT(id)
		FROM plugin_servcheck_credential
		$sql_where");

	$display_text = array(
		'name' => array(
			'display' => __('Name', 'servcheck'),
			'sort'    => 'ASC',
			'align'   => 'left'
		),
		'type' => array(
			'display' => __('Credential', 'servcheck'),
			'sort'    => 'ASC',
			'align'   => 'left'
		),
		'used' => array(
			'display' => __('Credentail using', 'servcheck'),
			'sort'    => 'ASC',
			'align'   => 'right'
		),
	);

	$columns = cacti_sizeof($display_text);

	$nav = html_nav_bar('servcheck_credential.php', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, $columns, __('Records', 'servcheck'), 'page', 'main');

	form_start('servcheck_credential.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '4', 'center', '');

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	if (cacti_sizeof($result)) {
		foreach ($result as $row) {

			$used = 0;

			$used += db_fetch_cell_prepared('SELECT COUNT(*) FROM plugin_servcheck_test WHERE cred_id = ?',
			array($row['id']));

			$used += db_fetch_cell_prepared('SELECT COUNT(*) FROM plugin_servcheck_proxies WHERE cred_id = ?',
				array($row['id']));

//			$used += db_fetch_cell_prepared('SELECT COUNT(*) FROM plugin_servcheck_restapi_method WHERE cred_id = ?',
//				array($row['id']));

			form_alternate_row('line' . $row['id'], true);
			form_selectable_cell(filter_value($row['name'], get_request_var('filter'),'servcheck_credential.php?header=false&action=edit&id=' . $row['id']), $row['id']);
			form_selectable_cell($credential_types[$row['type']], $row['id']);
			form_selectable_cell($used, $row['id']);
			form_checkbox_cell($row['id'], $row['id']);
			form_end_row();
		}
	}

	html_end_box(false);

	if (cacti_sizeof($result)) {
		print $nav;
	}

	draw_actions_dropdown($servcheck_actions_credential);

	form_end();

	?>
	<script type='text/javascript'>
	$(function() {
		$('#servcheck2_child').find('.cactiTooltipHint').each(function() {
			var title = $(this).attr('title');

			if (title != undefined && title.indexOf('/') >= 0) {
				$(this).click(function() {
					window.open(title, 'servcheck');
				});
			}
		});
	});

	</script>
	<?php

	bottom_footer();
}

function servcheck_credential_filter() {
	global $item_rows, $credential_types;

	?>
	<script type='text/javascript'>
	function applyFilter() {
		strURL  = 'servcheck_credential.php?header=false';
		strURL += '&rfilter=' + base64_encode($('#rfilter').val());
		strURL += '&type=' + $('#type').val();
		strURL += '&rows=' + $('#rows').val();
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'servcheck_credential.php?clear=1&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#type, #rfilter').change(function() {
			applyFilter();
		});

		$('#go').click(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#form_servcheck').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>
	<?php

	html_start_box(__('Credential', 'servcheck') , '100%', '', '3', 'center', 'servcheck_credential.php?action=edit');

	?>
	<tr class='even noprint'>
		<td class='noprint'>
			<form id='form_servcheck' action='servcheck_credential.php'>
			<input type='hidden' name='search' value='search'>
			<table class='filterTable'>
				<tr class='noprint'>
					<td>
						<?php print __('Search', 'servcheck');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='rfilter' size='30' value='<?php print html_escape_request_var('rfilter');?>'>
					</td>
					<td>
						<?php print __('Type', 'servcheck');?>
					</td>
					<td>

						<select id='type'>
							<?php

							print "<option value='-1'" . (get_request_var('type') == -1 ? ' selected':'') . ">" . __('Any', 'servcheck') . "</option>";
							foreach ($credential_types as $key => $value) {
								print "<option value='" . $key . "'";
								print get_request_var('type') == $key ? ' selected="selected">' : '>';
								print html_escape($value) . "</option>";
							}

							?>
						</select>

					</td>

					<td>
						<span class='nowrap'>
							<input type='button' id='go' value='<?php print __esc('Go', 'servcheck');?>'>
							<input type='button' id='clear' value='<?php print __esc('Clear', 'servcheck');?>'>
						</span>
					</td>
				</tr>
			</table>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();
}
