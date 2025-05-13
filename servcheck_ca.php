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
		ca_form_save();

		break;
	case 'actions':
		ca_form_actions();

		break;
	case 'edit':
		top_header();
		ca_edit();
		bottom_footer();

		break;
	default:
		ca();
}

function ca_form_actions() {
	global $servcheck_actions_ca;

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') == SERVCHECK_ACTION_CA_DELETE) { // delete
				/* do a referential integrity check */
				if (cacti_sizeof($selected_items)) {
					foreach($selected_items as $ca) {
						$cas[] = $ca;
					}
				}

				if (cacti_sizeof($cas)) {
					db_execute('DELETE FROM plugin_servcheck_ca WHERE ' . array_to_sql_or($cas, 'id'));
					db_execute('UPDATE plugin_servcheck_test SET ca = 0  WHERE ' . array_to_sql_or($cas, 'ca'));
				}
			}
		}

		header('Location: servcheck_ca.php?header=false');

		exit;
	}

	/* setup some variables */
	$ca_list  = '';
	$ca_array = array();

	/* loop through each of the graphs selected on the previous page and get more info about them */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$ca_list .= '<li>' . db_fetch_cell_prepared('SELECT name FROM plugin_servcheck_ca WHERE id = ?', array($matches[1])) . '</li>';
			$ca_array[] = $matches[1];
		}
	}

	top_header();

	form_start('servcheck_ca.php');

	html_start_box($servcheck_actions_ca[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (cacti_sizeof($ca_array) > 0) {
		if (get_nfilter_request_var('drp_action') == SERVCHECK_ACTION_CA_DELETE) { // delete
			print "	<tr>
					<td class='topBoxAlt'>
						<p>" . __n('Click \'Continue\' to delete the following CA.', 'Click \'Continue\' to delete following CA.', cacti_sizeof($ca_array)) . "</p>
						<div class='itemlist'><ul>$ca_list</ul></div>
					</td>
				</tr>";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc_n('Delete CA', 'Delete CA', cacti_sizeof($ca_array)) . "'>";
		}
	} else {
		raise_message(40);
		header('Location: servcheck_ca.php');
		exit;
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($ca_array) ? serialize($ca_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_nfilter_request_var('drp_action') . "'>
			$save_html
		</td>
	</tr>";

	html_end_box();

	form_end();

	bottom_footer();
}

function ca_form_save() {
	if (isset_request_var('save_component_ca')) {
		$save['id']         = get_filter_request_var('id');
		$save['name']       = form_input_validate(get_nfilter_request_var('name'), 'name', '', false, 3);
		$save['cert']       = form_input_validate(get_nfilter_request_var('cert'), 'cert', '^-----BEGIN CERTIFICATE-----.*', false, 3);

		if (!is_error_message()) {
			$ca_id = sql_save($save, 'plugin_servcheck_ca');

			if ($ca_id) {
				raise_message(1);
			} else {
				raise_message(2);
			}
		}

		header('Location: servcheck_ca.php?action=edit&header=false&id=' . (empty($ca_id) ? get_request_var('id') : $ca_id));
	}
}

function ca_edit() {
	global $servcheck_ca_fields;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	if (!isempty_request_var('id')) {
		$ca = db_fetch_row_prepared('SELECT *
			FROM plugin_servcheck_ca
			WHERE id = ?',
			array(get_request_var('id')));

		$header_label = __('CA [edit: %s]', $ca['name']);
	} else {
		$header_label = __('CA [new]');
	}

	top_header();

	form_start('servcheck_ca.php');

	html_start_box($header_label, '100%', true, '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($servcheck_ca_fields, (isset($ca) ? $ca : array()))
		)
	);

	html_end_box(true, true);

	form_hidden_box('save_component_ca', '1', '');

	form_save_button('servcheck_ca.php', 'return');

	bottom_footer();
}

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

	validate_store_request_vars($filters, 'sess_servcheck_ca');
	/* ================= input validation ================= */
}

function ca() {
	global $servcheck_actions_ca;

	request_validation();

	top_header();

	servcheck_show_tab('servcheck_ca.php');

	servcheck_filter();

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	$sql_where = '';

	if (get_request_var('filter') != '') {
		$sql_where .= ($sql_where == '' ? 'WHERE ' : ' AND ') . ' name LIKE "%' . get_request_var('filter');
	}

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$result = db_fetch_assoc("SELECT id, name,
			(SELECT COUNT(*) FROM plugin_servcheck_test WHERE plugin_servcheck_ca.id=ca) AS `used`
		FROM plugin_servcheck_ca
		$sql_where
		$sql_limit");

	$total_rows = db_fetch_cell("SELECT COUNT(id)
		FROM plugin_servcheck_ca
		$sql_where");

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

	$nav = html_nav_bar('servcheck_ca.php', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, $columns, __('CAs', 'servcheck'), 'page', 'main');

	form_start('servcheck_ca.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	if (cacti_sizeof($result)) {
		foreach ($result as $row) {
			form_alternate_row('line' . $row['id'], true);

			form_selectable_cell(filter_value($row['name'], get_request_var('filter'), 'servcheck_ca.php?header=false&action=edit&id=' . $row['id']), $row['id']);
			form_selectable_cell($row['used'], $row['id']);
			form_checkbox_cell($row['name'], $row['id']);

			form_end_row();
		}
	}

	html_end_box(false);

	if (cacti_sizeof($result)) {
		print $nav;
	}

	draw_actions_dropdown($servcheck_actions_ca);

	form_end();

	bottom_footer();
}

function servcheck_filter() {
	global $item_rows;

	html_start_box(__('Servcheck CA Management', 'servcheck') , '100%', '', '3', 'center', 'servcheck_ca.php?action=edit&header=false');

	?>
	<tr class='even noprint'>
		<td class='noprint'>

		<?php
		print __('Servcheck uses a file of certificates from common root CAs to check certificates. This will work for certificates issued by common CAs. If you are using a custom CA (for example, in a Microsoft AD environment), the test for that certificate will fail because servcheck does not know your CA. You must upload the entire chain (CA certificate and intermediate certificates). You then associate these with the test where the certificate issued by your CA is.', 'servcheck');
		?>
		<form id='servcheck' action='servcheck_ca.php' method='post'>
			<table class='filterTable'>
				<tr class='noprint'>
					<td>
						<?php print __('Search', 'servcheck');?>
					</td>
					<td>
						<input type='text' size='30' id='filter' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('CAs', 'servcheck');?>
					</td>
					<td>
						<select id='rows'>
							<?php
							print "<option value='-1'" . (get_request_var('rows') == -1 ? ' selected':'') . ">" . __('Default', 'servcheck') . "</option>";
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . html_escape($value) . "</option>";
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
			strURL  = 'servcheck_ca.php?header=false';
			strURL += '&filter=' + $('#filter').val();
			strURL += '&rows=' + $('#rows').val();
			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			strURL = 'servcheck_ca.php?clear=1&header=false';
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

