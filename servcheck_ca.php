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
require_once('./include/auth.php');
require_once($config['base_path'] . '/plugins/servcheck/includes/functions.php');
require($config['base_path'] . '/plugins/servcheck/includes/arrays.php');

$servcheck_actions_menu = array(
	1 => __('Delete', 'servcheck'),
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
						db_execute_prepared('DELETE FROM plugin_servcheck_ca WHERE id = ?', array($item));
						db_execute_prepared('UPDATE plugin_servcheck_test SET ca_id = 0 WHERE ca_id = ?', array($item));
					}
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

			$item_list .= '<li>' . db_fetch_cell_prepared('SELECT name FROM plugin_servcheck_ca WHERE id = ?', array($matches[1])) . '</li>';
			$items_array[] = $matches[1];
		}
	}

	top_header();

	form_start(htmlspecialchars(basename($_SERVER['PHP_SELF'])));

	html_start_box($servcheck_actions_menu[get_filter_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (cacti_sizeof($items_array) > 0) {
		if (get_filter_request_var('drp_action') == 1) {
			print "	<tr>
					<td class='topBoxAlt'>
						<p>" . __n('Click \'Continue\' to delete the following items.', 'Click \'Continue\' to delete following items.', cacti_sizeof($items_array)) . "</p>
						<div class='itemlist'><ul>$item_list</ul></div>
					</td>
				</tr>";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc_n('Delete item', 'Delete items', cacti_sizeof($items_array)) . "'>";
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

	if (isset_request_var('save_component')) {

		$save['id']   = get_filter_request_var('id');
		$save['name'] = form_input_validate(get_nfilter_request_var('name'), 'name', '', false, 3);
		$save['cert'] = form_input_validate(get_nfilter_request_var('cert'), 'cert', '^-----BEGIN CERTIFICATE-----.*', false, 3);

		if ($save['id'] > 0 && isset($_SESSION['sess_error_fields']) && cacti_sizeof($_SESSION['sess_error_fields'])) {
			foreach ($_SESSION['sess_error_fields'] as $item) {
				unset($save[$item], $_SESSION['sess_error_fields'][$item]);
			}
			clear_messages();
		}

		if (!is_error_message()) {
			$saved_id = sql_save($save, 'plugin_servcheck_ca');

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
	global $servcheck_ca_fields;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	$data = array();

	if (!isempty_request_var('id')) {
		$data = db_fetch_row_prepared('SELECT *
			FROM plugin_servcheck_ca
			WHERE id = ?',
			array(get_request_var('id')));

		$header_label = __('CA [edit: %s]', $data['name']);
	} else {
		$header_label = __('CA [new]');
	}

	form_start(htmlspecialchars(basename($_SERVER['PHP_SELF'])));

	html_start_box($header_label, '100%', true, '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($servcheck_ca_fields, $data)
		)
	);

	form_hidden_box('save_component', '1', '');

	html_end_box(true, true);

	form_save_button(htmlspecialchars(basename($_SERVER['PHP_SELF'])));
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

	validate_store_request_vars($filters, 'sess_servcheck_proxy');
}


function data_list() {
	global $servcheck_actions_menu;

	request_validation();

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	servcheck_show_tab(htmlspecialchars(basename($_SERVER['PHP_SELF'])));

	servcheck_filter();

	$sql_where = '';

	if (get_request_var('filter') != '') {
		$sql_where .= ($sql_where == '' ? 'WHERE ' : ' AND ') . ' name LIKE "%' . get_request_var('filter');
	}

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$total_rows = db_fetch_cell("SELECT COUNT(id)
		FROM plugin_servcheck_ca
		$sql_where");

	$result = db_fetch_assoc("SELECT *,
		(SELECT COUNT(*) FROM plugin_servcheck_test WHERE plugin_servcheck_ca.id=ca_id) AS `used`
		FROM plugin_servcheck_ca
		$sql_where
		$sql_order
		$sql_limit");

	$display_text = array(
		'name' => array(
			'display' => __('Name', 'servcheck'),
			'sort'    => 'ASC'
		),
		'used' => array(
			'display' => __('Used', 'servcheck'),
			'sort'    => 'ASC'
		),
	);

	$columns = cacti_sizeof($display_text);

	$nav = html_nav_bar(htmlspecialchars(basename($_SERVER['PHP_SELF'])) . '?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, $columns, __('CAs', 'servcheck'), 'page', 'main');

	form_start(htmlspecialchars(basename($_SERVER['PHP_SELF'])), 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	if (cacti_sizeof($result)) {
		foreach ($result as $row) {
			if ($row['used'] == 0) {
				$disabled = false;
			} else {
				$disabled = true;
			}

			form_alternate_row('line' . $row['id'], false, $disabled);
			form_selectable_cell("<a class='linkEditMain' href='" . html_escape(htmlspecialchars(basename($_SERVER['PHP_SELF'])) . '?header=false&action=edit&id=' . $row['id']) . "'>" . $row['name'] . '</a>', $row['id']);
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

	html_start_box(__('Servcheck CA Management', 'servcheck') , '100%', '', '3', 'center', htmlspecialchars(basename($_SERVER['PHP_SELF'])) . '?action=edit');

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
						<?php print __('CAs', 'servcheck');?>
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


