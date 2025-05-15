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
include_once($config['base_path'] . '/plugins/servcheck/includes/arrays.php');

global $refresh;

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
		servcheck_edit_rest();
		bottom_footer();

		break;
	default:
		list_restapis();

		break;
}

exit;


function form_actions() {
	global $servcheck_actions_restapi;

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));
		$action         = get_nfilter_request_var('drp_action');

		if ($selected_items != false) {
			if (cacti_sizeof($selected_items)) {
				foreach($selected_items as $row) {
					$restapis[] = $row;
				}
			}

			if (cacti_sizeof($restapis)) {
				if ($action == SERVCHECK_ACTION_RESTAPI_DELETE) {
					foreach ($restapis as $id) {
						db_execute_prepared('DELETE FROM plugin_servcheck_restapi_method WHERE id = ?', array($id));
						db_execute_prepared('UPDATE plugin_servcheck_test SET restapi_id = 0  WHERE restapi_id = ?', array($id));
					}
				} elseif ($action == SERVCHECK_ACTION_RESTAPI_DUPLICATE) {
					$newid = 1;

					foreach ($restapis as $id) {
						$save = db_fetch_row_prepared('SELECT * FROM plugin_servcheck_restapi_method WHERE id = ?', array($id));
						$save['id']           = 0;
						$save['name']         = 'New Rest API (' . $newid . ')';
						$save['type']         = 'basic';
						$save['format']       = 'raw';
						$save['authid_name']  = '';
						$save['http_method']  = 'get';
						$save['username']     = '';
						$save['password']     = '';
						$save['login_url']    = '';
						$save['data_url']     = '';

						$id = sql_save($save, 'plugin_servcheck_restapi_method');

						$newid++;
					}
				}
			}
		}

		header('Location: servcheck_restapi.php?header=false');

		exit;
	}

	/* setup some variables */
	$restapi_list  = '';
	$restapi_array = array();

	/* loop through each of the restapis selected on the previous page and get more info about them */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$restapi_list .= '<li>' . __esc(db_fetch_cell_prepared('SELECT name FROM plugin_servcheck_restapi_method WHERE id = ?', array($matches[1]))) . '</li>';
			$restapi_array[] = $matches[1];
		}
	}

	top_header();

	form_start('servcheck_restapi.php');

	html_start_box($servcheck_actions_restapi[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

	$action = get_nfilter_request_var('drp_action');

	if (cacti_sizeof($restapi_array)) {
		if ($action == SERVCHECK_ACTION_RESTAPI_DELETE) {
			print"	<tr>
				<td class='topBoxAlt'>
					<p>" . __n('Click \'Continue\' to Delete the following Rest API.', 'Click \'Continue\' to Delete following Rest API.', cacti_sizeof($restapi_array)) . "</p>
					<div class='itemlist'><ul>$restapi_list</ul></div>
				</td>
			</tr>";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc_n('Delete Rest API', 'Delete Rest API', cacti_sizeof($restapi_array)) . "'>";
		} elseif ($action == SERVCHECK_ACTION_RESTAPI_DUPLICATE) {
			print "<tr>
				<td class='topBoxAlt'>
					<p>" . __n('Click \'Continue\' to Duplicate the following Rest API.', 'Click \'Continue\' to Duplicate following Rest APIs.', cacti_sizeof($restapi_array)) . "</p>
					<div class='itemlist'><ul>$restapi_list</ul></div>
				</td>
			</tr>";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc_n('Duplicate Rest API', 'Duplicate Rest API', cacti_sizeof($restapi_array)) . "'>";
		}
	} else {
		raise_message(40);
		header('Location: servcheck_restapi.php');
		exit;
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($restapi_array) ? serialize($restapi_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_nfilter_request_var('drp_action') . "'>
			$save_html
		</td>
	</tr>";

	html_end_box();

	form_end();

	bottom_footer();
}

function form_save() {
	global $rest_api_auth_method, $rest_api_format, $rest_api_http_method;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	if (isset_request_var('id')) {
		$save['id'] = get_request_var('id');
	} else {
		$save['id'] = 0;
	}

	if (isset_request_var('type') && array_key_exists(get_nfilter_request_var('type'), $rest_api_auth_method)) {
		$save['type'] = get_nfilter_request_var('type');
	} else {
		raise_message(3);
		$_SESSION['sess_error_fields']['type'] = 'type';
	}

	if (isset_request_var('format') && array_key_exists(get_nfilter_request_var('format'), $rest_api_format)) {
		$save['format'] = get_nfilter_request_var('format');
	} else {
		raise_message(3);
		$_SESSION['sess_error_fields']['format'] = 'format';
	}

	if (isset_request_var('http_method') && array_key_exists(get_nfilter_request_var('http_method'), $rest_api_http_method)) {
		$save['http_method'] = get_nfilter_request_var('http_method');
	} else {
		raise_message(3);
		$_SESSION['sess_error_fields']['http_method'] = 'http_method';
	}

	if (isset_request_var('name') && get_nfilter_request_var('name') != '' && get_filter_request_var('name', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^[a-z0-9A-Z_\/@.\- \=,]{1,100}$/')))) {
		$save['name'] = get_nfilter_request_var('name');
	}

	if (isset_request_var('username') && get_nfilter_request_var('username') != '' && get_filter_request_var('username', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^[a-z0-9A-Z_\/@.\- \=,]{1,100}$/')))) {
		$save['username'] = servcheck_hide_text(get_nfilter_request_var('username'));
	}

	if (isset_request_var('password') && get_nfilter_request_var('password') != '' && get_filter_request_var('password', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^[a-z0-9A-Z_\/@.\- \=,]{1,100}$/')))) {
		$save['password'] = servcheck_hide_text(get_nfilter_request_var('password'));
	}

	if (isset_request_var('authid_name') && get_nfilter_request_var('authid_name') != '' && get_filter_request_var('authid_name', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^[a-z0-9A-Z_\/@.\-]{1,100}$/')))) {
		$save['authid_name'] = get_nfilter_request_var('authid_name');
	}

	if (isset_request_var('token_value') && get_nfilter_request_var('token_value') != '' && get_filter_request_var('token_value', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^[a-z0-9A-Z_\/@.\- \=,]{1,100}$/')))) {
		$save['token_value'] = servcheck_hide_text(get_nfilter_request_var('token_value'));
	}

	if (isset_request_var('login_url') && get_nfilter_request_var('login_url') != '' && get_filter_request_var('login_url', FILTER_VALIDATE_URL)) {
		$save['login_url'] = get_nfilter_request_var('login_url');
	}

	if (isset_request_var('data_url') && get_nfilter_request_var('data_url') != '' && get_filter_request_var('data_url', FILTER_VALIDATE_URL)) {
		$save['data_url'] = get_nfilter_request_var('data_url');
	} else {
		raise_message(3);
		$_SESSION['sess_error_fields']['data_url'] = 'data_url';
	}

	if (!is_error_message()) {
		$id = sql_save($save, 'plugin_servcheck_restapi_method', 'id');

		if ($id) {
			raise_message(1);
		} else {
			raise_message(2);
		}
	}

	header('Location: servcheck_restapi.php?action=edit&id=' . (isset($id)? $id : get_request_var('id')) . '&header=false');
	exit;
}

function servcheck_edit_rest() {
	global $servcheck_restapi_fields;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	$restapi = array();

	if (!isempty_request_var('id')) {
		$restapi = db_fetch_row_prepared('SELECT * FROM plugin_servcheck_restapi_method WHERE id = ?', array(get_request_var('id')), false);
		$header_label = __('Query [edit: %s]', $restapi['name'], 'servcheck');
	} else {
		$header_label = __('Query [new]', 'servcheck');
	}

	if (isset($restapi['username'])) {
		$restapi['username'] = servcheck_show_text($restapi['username']);
	}
	if (isset($restapi['password'])) {
		$restapi['password'] = servcheck_show_text($restapi['password']);
	}
	if (isset($restapi['token_value'])) {
		$restapi['token_value'] = servcheck_show_text($restapi['token_value']);
	}

	form_start('servcheck_restapi.php');

	html_start_box($header_label, '100%', '', '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('form_name' => 'chk'),
			'fields' => inject_form_variables($servcheck_restapi_fields, $restapi)
		)
	);

	html_end_box();

	form_save_button('servcheck_restapi.php', 'return');

	?>
	<script type='text/javascript'>

	$(function() {

		setRestAPI();
	});

	function setRestAPI() {
		var restapi_type = $('#type').val();

		switch(restapi_type) {
			case 'no':
				$('#row_username').hide();
				$('#row_password').hide();
				$('#row_authid_name').hide();
				$('#row_token_value').hide();
				$('#row_login_url').hide();
				$('#row_data_url').show();
				break;
			case 'basic':
				$('#row_authid_name').hide();
				$('#row_token_value').hide();
				$('#row_login_url').hide();
				$('#row_username').show();
				$('#row_password').show();
				$('#row_data_url').show();
				$('#password').attr('type', 'password');
				break
			case 'apikey':
				$('#row_authid_name').show();
				$('#row_token_value').show();
				$('#row_username').show();
				$('#row_password').show();
				$('#row_login_url').show();
				$('#row_data_url').show();
				break;
			case 'oauth2':
				$('#row_authid_name').show();
				$('#row_token_value').show();
				$('#row_username').show();
				$('#row_password').show();
				$('#row_login_url').show();
				$('#row_data_url').show();
				$('#password').attr('type', 'password');
				break;
			case 'cookie':
				$('#row_authid_name').show();
				$('#row_username').show();
				$('#row_password').show();
				$('#row_login_url').show();
				$('#row_data_url').show();
				$('#password').attr('type', 'password');
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

	validate_store_request_vars($filters, 'sess_servcheck_restapi');
	/* ================= input validation ================= */
}


function list_restapis() {
	global $servcheck_actions_restapi, $config, $rest_api_auth_method;

	servcheck_request_validation();

	top_header();

	servcheck_show_tab('servcheck_restapi.php');

	servcheck_restapi_filter();

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	$sql_where = '';

	$sql_order = get_order_string();


	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	if (get_request_var('rest_method') && array_key_exists(get_request_var('rest_method'), $rest_api_auth_method)) {
		$sql_where .= ($sql_where == '' ? 'WHERE ' : ' AND ') .
			'type = "' . get_request_var('rest_method') . '"';
	} else {
		set_request_var('rest_method', -1);
	}

	if (get_request_var('rfilter') != '') {
		$sql_where .= ($sql_where == '' ? 'WHERE ' : ' AND ') .
			'name RLIKE \'' . get_request_var('rfilter') . '\' OR ' .
			'type RLIKE \'' . get_request_var('rfilter') . '\' OR ' .
			'username RLIKE \'' . get_request_var('rfilter') . '\' OR ' .
			'authid_name RLIKE \'' . get_request_var('rfilter') . '\'';
	}

	$result = db_fetch_assoc("SELECT *, 
		(SELECT COUNT(*) FROM plugin_servcheck_test AS st WHERE st.restapi_id = plugin_servcheck_restapi_method.id) AS used
		FROM plugin_servcheck_restapi_method
		$sql_where
		$sql_order
		$sql_limit");

	$total_rows = db_fetch_cell("SELECT COUNT(id)
		FROM plugin_servcheck_restapi_method AS rm
		$sql_where");

	$display_text = array(
		'name' => array(
			'display' => __('Name', 'servcheck'),
			'sort'    => 'ASC',
			'align'   => 'left'
		),
		'type' => array(
			'display' => __('Rest API Method', 'servcheck'),
			'sort'    => 'ASC',
			'align'   => 'left'
		),
		'used' => array(
			'display' => __('Rest API using', 'servcheck'),
			'sort'    => 'ASC',
			'align'   => 'right'
		),
	);

	$columns = cacti_sizeof($display_text);

	$nav = html_nav_bar('servcheck_restapi.php', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, $columns, __('Records', 'servcheck'), 'page', 'main');

	form_start('servcheck_restapi.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '4', 'center', '');

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	if (cacti_sizeof($result)) {
		foreach ($result as $row) {

			form_alternate_row('line' . $row['id'], true);
			form_selectable_cell(filter_value($row['name'], get_request_var('filter'),'servcheck_restapi.php?header=false&action=edit&id=' . $row['id']), $row['id']);
			form_selectable_cell($rest_api_auth_method[$row['type']], $row['id']);
			form_selectable_cell($row['used'], $row['id']);
			form_checkbox_cell($row['id'], $row['id']);
			form_end_row();
		}
	}

	html_end_box(false);

	if (cacti_sizeof($result)) {
		print $nav;
	}

	draw_actions_dropdown($servcheck_actions_restapi);

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

function servcheck_restapi_filter() {
	global $item_rows, $rest_api_auth_method;

	?>
	<script type='text/javascript'>
	function applyFilter() {
		strURL  = 'servcheck_restapi.php?header=false';
		strURL += '&rfilter=' + base64_encode($('#rfilter').val());
		strURL += '&rest_method=' + $('#rest_method').val();
		strURL += '&rows=' + $('#rows').val();
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'servcheck_restapi.php?clear=1&header=false';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#rest_method, #rfilter').change(function() {
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

	html_start_box(__('Rest API', 'servcheck') , '100%', '', '3', 'center', 'servcheck_restapi.php?action=edit');

	?>
	<tr class='even noprint'>
		<td class='noprint'>
			<form id='form_servcheck' action='servcheck_restapi.php'>
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

						<select id='rest_method'>
							<?php

							print "<option value='-1'" . (get_request_var('rest_method') == -1 ? ' selected':'') . ">" . __('Any', 'servcheck') . "</option>";
							foreach ($rest_api_auth_method as $key => $value) {
								print "<option value='" . $key . "'";
								print get_request_var('rest_method') == $key ? ' selected="selected">' : '>';
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
