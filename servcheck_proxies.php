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

set_default_action();

switch (get_request_var('action')) {
	case 'save':
		proxy_form_save();

		break;
	case 'actions':
		proxy_form_actions();

		break;
	case 'edit':
		top_header();
		proxy_edit();
		bottom_footer();

		break;
	default:
		proxies();
}

function proxy_form_actions() {
	global $servcheck_actions_proxy;

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') == SERVCHECK_ACTION_PROXY_DELETE) { // delete
				/* do a referential integrity check */
				if (cacti_sizeof($selected_items)) {
					foreach($selected_items as $proxy) {
						$proxies[] = $proxy;
					}
				}

				if (cacti_sizeof($proxies)) {
					db_execute_prepared('DELETE FROM plugin_servcheck_proxies WHERE id = ?', array(array_to_sql_or($proxies, 'id')));
					db_execute_prepared('UPDATE plugin_servcheck_test SET proxy = 0 WHERE proxy = ?', array(array_to_sql_or($proxies, 'proxy_server')));
				}
			}
		}

		header('Location: servcheck_proxies.php?header=false');

		exit;
	}

	/* setup some variables */
	$proxy_list  = '';
	$proxy_array = array();

	/* loop through each of the graphs selected on the previous page and get more info about them */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$proxy_list .= '<li>' . db_fetch_cell_prepared('SELECT name FROM plugin_servcheck_proxies WHERE id = ?', array($matches[1])) . '</li>';
			$proxy_array[] = $matches[1];
		}
	}

	top_header();

	form_start('servcheck_proxies.php');

	html_start_box($servcheck_actions_proxy[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (cacti_sizeof($proxy_array) > 0) {
		if (get_nfilter_request_var('drp_action') == SERVCHECK_ACTION_PROXY_DELETE) { // delete
			print "	<tr>
					<td class='topBoxAlt'>
						<p>" . __n('Click \'Continue\' to delete the following Proxy.', 'Click \'Continue\' to delete following Proxies.', cacti_sizeof($proxy_array)) . "</p>
						<div class='itemlist'><ul>$proxy_list</ul></div>
					</td>
				</tr>";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc_n('Delete Proxy', 'Delete Proxies', cacti_sizeof($proxy_array)) . "'>";
		}
	} else {
		raise_message(40);
		header('Location: servcheck_proxies.php');
		exit;
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($proxy_array) ? serialize($proxy_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_nfilter_request_var('drp_action') . "'>
			$save_html
		</td>
	</tr>";

	html_end_box();

	form_end();

	bottom_footer();
}

function proxy_form_save() {
	if (isset_request_var('save_component_proxy')) {
		$save['id']         = get_filter_request_var('id');
		$save['name']       = form_input_validate(get_nfilter_request_var('name'), 'name', '', false, 3);
		$save['hostname']   = form_input_validate(get_nfilter_request_var('hostname'), 'hostname', '', false, 3);
		$save['http_port']  = form_input_validate(get_nfilter_request_var('http_port'), 'http_port', '', false, 3);
		$save['https_port'] = form_input_validate(get_nfilter_request_var('https_port'), 'https_port', '', false, 3);
		$save['username']   = form_input_validate(get_nfilter_request_var('username'), 'username', '', true, 3);
		$save['password']   = form_input_validate(get_nfilter_request_var('password'), 'password', '', true, 3);

		if (!is_error_message()) {
			$proxy_id = sql_save($save, 'plugin_servcheck_proxies');

			if ($proxy_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		header('Location: servcheck_proxies.php?action=edit&header=false&id=' . (empty($proxy_id) ? get_request_var('id') : $proxy_id));
	}
}

function proxy_edit() {
	global $servcheck_proxy_fields;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	$proxy = array();

	if (!isempty_request_var('id')) {
		$proxy = db_fetch_row_prepared('SELECT *
			FROM plugin_servcheck_proxies
			WHERE id = ?',
			array(get_request_var('id')));

		$header_label = __('Proxy [edit: %s]', $proxy['name']);
	} else {
		$header_label = __('Proxy [new]');
	}

	top_header();

	form_start('servcheck_proxies.php');

	html_start_box($header_label, '100%', true, '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($servcheck_proxy_fields, $proxy)
		)
	);

	html_end_box(true, true);

	form_hidden_box('save_component_proxy', '1', '');

	form_save_button('servcheck_proxies.php', 'return');

	bottom_footer();
}

/**
 *  This is a generic function for this page that makes sure that
 *  we have a good request.  We want to protect against people who
 *  like to create issues with Cacti.
*/
function request_validation() {
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
		'refresh' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '20',
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

	validate_store_request_vars($filters, 'sess_servcheck_proxy');
	/* ================= input validation ================= */
}

function proxies() {
	global $servcheck_actions_proxy;

	request_validation();

	top_header();

	servcheck_show_tab('servcheck_proxies.php');

	servcheck_filter();

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	$sql_where = '';

	if (get_request_var('filter') != '') {
		$sql_where .= ($sql_where == '' ? 'WHERE ' : ' AND ') . ' name LIKE "%' . get_request_var('filter') . '%" OR hostname LIKE "%' . get_request_var('filter') . '%"';
	}

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$result = db_fetch_assoc("SELECT *
		FROM plugin_servcheck_proxies
		$sql_where
		$sql_order
		$sql_limit");

	$total_rows = db_fetch_cell("SELECT COUNT(id)
		FROM plugin_servcheck_proxies
		$sql_where");

	$display_text = array(
		'name' => array(
			'display' => __('Name', 'servcheck'),
			'sort'    => 'ASC'
		),
		'hostname' => array(
			'display' => __('Hostname', 'servcheck'),
			'sort'    => 'ASC'
		),
		'nosort1' => array(
			'display' => __('Ports (http/https)', 'servcheck'),
			'sort'    => 'ASC'
		),
		'username' => array(
			'display' => __('Username', 'servcheck'),
			'sort'    => 'ASC'
		),
	);

	$columns = cacti_sizeof($display_text);

	$nav = html_nav_bar('servcheck_proxies.php', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, $columns, __('Proxies', 'servcheck'), 'page', 'main');

	form_start('servcheck_proxies.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	if (cacti_sizeof($result)) {
		foreach ($result as $row) {
			form_alternate_row('line' . $row['id'], true);

			form_selectable_cell(filter_value($row['name'], get_request_var('filter'), 'servcheck_proxies.php?header=false&action=edit&id=' . $row['id']), $row['id']);
			form_selectable_cell($row['hostname'], $row['id']);
			form_selectable_cell($row['http_port'] . '/' . $row['https_port'], $row['id']);
			form_selectable_cell($row['username'] == '' ? __('Not Set', 'servcheck'):$row['username'], $row['id']);
			form_checkbox_cell($row['name'], $row['id']);

			form_end_row();
		}
	}

	html_end_box(false);

	if (cacti_sizeof($result)) {
		print $nav;
	}

	draw_actions_dropdown($servcheck_actions_proxy);

	form_end();

	bottom_footer();
}

function servcheck_filter() {
	global $item_rows;

	html_start_box(__('Servcheck Proxy Management', 'servcheck') , '100%', '', '3', 'center', 'servcheck_proxies.php?action=edit&header=false');

	?>
	<tr class='even noprint'>
		<td class='noprint'>
		<form id='servcheck' action='servcheck_proxies.php' method='post'>
			<table class='filterTable'>
				<tr class='noprint'>
					<td>
						<?php print __('Search', 'servcheck');?>
					</td>
					<td>
						<input type='text' size='30' id='filter' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Proxies', 'servcheck');?>
					</td>
					<td>
						<select id='rows'>
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
							<input type='submit' id='go' value='<?php print __esc('Go', 'servcheck');?>'>
							<input type='button' id='clear' value='<?php print __esc('Clear', 'servcheck');?>'>
						</span>
					</td>
				</tr>
			</table>
		</form>
		<script type='text/javascript'>

		function applyFilter() {
			strURL  = 'servcheck_proxies.php?header=false';
			strURL += '&filter=' + $('#filter').val();
			strURL += '&rows=' + $('#rows').val();
			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			strURL = 'servcheck_proxies.php?clear=1&header=false';
			loadPageNoHeader(strURL);
		}

		$(function() {
			$('#rows').change(function() {
				applyFilter();
			});

			$('#clear').click(function() {
				clearFilter();
			});

			$('#servcheck').submit(function(event) {
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

