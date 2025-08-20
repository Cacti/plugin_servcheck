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

global $refresh;

$servcheck_actions_menu = array(
	1 => __('Delete', 'servcheck'),
	2 => __('Disable', 'servcheck'),
	3 => __('Enable', 'servcheck'),
	4 => __('Duplicate', 'servcheck'),
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

	case 'enable':
		$id = get_filter_request_var('id');

		if ($id > 0) {
			db_execute_prepared('UPDATE plugin_servcheck_test
			SET enabled = "on"
			WHERE id = ?', array($id));
		}

		header('Location: ' . htmlspecialchars(basename($_SERVER['PHP_SELF'])) .'?header=false');
		exit;

		break;
	case 'disable':
		$id = get_filter_request_var('id');

		if ($id > 0) {
			db_execute_prepared('UPDATE plugin_servcheck_test
			SET enabled = ""
			WHERE id = ?', array($id));
		}

		header('Location: ' . htmlspecialchars(basename($_SERVER['PHP_SELF'])) .'?header=false');
		exit;

		break;
	case 'purge':
		$id = get_filter_request_var('id');

		if ($id > 0) {
			purge_log_events($id);
		}

		header('Location: ' . htmlspecialchars(basename($_SERVER['PHP_SELF'])) .'?header=false');
		exit;

		break;

	case 'history':
		top_header();
		servcheck_show_history();
		bottom_footer();

		break;
	case 'graph':
		top_header();
		servcheck_show_graph();
		bottom_footer();

		break;

	case 'last_data':
		top_header();
		servcheck_show_last_data();
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
			if (get_filter_request_var('drp_action') == 1) { // delete
				if (cacti_sizeof($selected_items)) {
					foreach($selected_items as $item) {
						db_execute_prepared('DELETE FROM plugin_servcheck_test WHERE id = ?', array($item));
						db_execute_prepared('DELETE FROM plugin_servcheck_log WHERE test_id = ?', array($item));
					}
				}
			} elseif (get_filter_request_var('drp_action') == 2) { // disable
				foreach ($selected_items as $item) {
					db_execute_prepared('UPDATE plugin_servcheck_test SET enabled = "" WHERE id = ?', array($item));
				}
			} elseif (get_filter_request_var('drp_action') == 3) { // enable
				foreach ($selected_items as $item) {
					db_execute_prepared('UPDATE plugin_servcheck_test SET enabled = "on" WHERE id = ?', array($item));
				}
			} elseif (get_filter_request_var('drp_action') == 4) { // duplicate
				$newid = 1;

				foreach ($selected_items as $item) {
					$save = db_fetch_row_prepared('SELECT * FROM plugin_servcheck_test
						WHERE id = ?', array($item));

					$save['id']           = 0;
					$save['name'] = 'New Service Check (' . $newid . ')';
					$save['lastcheck']    = '0000-00-00 00:00:00';
					$save['triggered']    = 0;
					$save['enabled']      = '';
					$save['failures']     = 0;
					$save['stats_ok']     = 0;
					$save['stats_bad']    = 0;

					$id = sql_save($save, 'plugin_servcheck_test');

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

			$item_list .= '<li>' . db_fetch_cell_prepared('SELECT name FROM plugin_servcheck_test WHERE id = ?', array($matches[1])) . '</li>';
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
		} elseif (get_filter_request_var('drp_action') == 2) { // disable
			print "	<tr>
					<td class='topBoxAlt'>
						<p>" . __n('Click \'Continue\' to disable the following items.', 'Click \'Continue\' to disable following items.', cacti_sizeof($items_array)) . "</p>
						<div class='itemlist'><ul>$item_list</ul></div>
					</td>
				</tr>";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc_n('Disable item', 'Disable items', cacti_sizeof($items_array)) . "'>";
		} elseif (get_filter_request_var('drp_action') == 3) { // enable
			print "	<tr>
					<td class='topBoxAlt'>
						<p>" . __n('Click \'Continue\' to enable the following items.', 'Click \'Continue\' to enable following items.', cacti_sizeof($items_array)) . "</p>
						<div class='itemlist'><ul>$item_list</ul></div>
					</td>
				</tr>";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc_n('Enable item', 'Enable items', cacti_sizeof($items_array)) . "'>";
		} elseif (get_filter_request_var('drp_action') == 4) { // duplicate
			print "	<tr>
					<td class='topBoxAlt'>
						<p>" . __n('Click \'Continue\' to duplicate the following items.', 'Click \'Continue\' to duplicate following items.', cacti_sizeof($items_array)) . "</p>
						<div class='itemlist'><ul>$item_list</ul></div>
					</td>
				</tr>";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc_n('Duplicate item', 'Duplicate items', cacti_sizeof($items_array)) . "'>";
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
	global $service_types;

	if (isset_request_var('save_component')) {

		$save['id']          = get_filter_request_var('id');
		$save['poller_id']   = get_filter_request_var('poller_id');
		$save['cred_id']     = get_filter_request_var('cred_id');
		$save['ca_id']       = get_filter_request_var('ca_id');
		$save['external_id'] = get_filter_request_var('external_id');
		$save['proxy_id']    = get_filter_request_var('proxy_id');
		$save['downtrigger']     = get_filter_request_var('downtrigger');
		$save['timeout_trigger'] = get_filter_request_var('timeout_trigger');
		$save['how_often']       = get_filter_request_var('how_often');

		if (isset_request_var('enabled')) {
			$save['enabled'] = 'on';
		} else {
			$save['enabled'] = '';
		}

		if (isset_request_var('notify_accounts')) {
			if (is_array(get_nfilter_request_var('notify_accounts'))) {
				foreach (get_nfilter_request_var('notify_accounts') as $na) {
					input_validate_input_number($na);
				}
				$save['notify_accounts'] = implode(',', get_nfilter_request_var('notify_accounts'));
			} else {
				set_request_var('notify_accounts', '');
			}
		} else {
			$save['notify_accounts'] = '';
		}

		if (isset_request_var('type') && array_key_exists(get_nfilter_request_var('type'), $service_types)) {
			$save['type'] = get_nfilter_request_var('type');
			list ($category, $subcategory) = explode('_', get_nfilter_request_var('type'));
		} else {
			$_SESSION['sess_error_fields']['type'] = 'type';
			raise_message(3);
		}

		if (isset_request_var('hostname')) {
			form_input_validate(get_nfilter_request_var('hostname'), 'hostname', '^[a-zA-Z0-9\.\-]+(\:[0-9]{1,5})?$', false, 3);
			$save['hostname'] = get_nfilter_request_var('hostname');
		}

		if ($category == 'web') {
			if (isset_request_var('ipaddress')) {
				if (get_nfilter_request_var('ipaddress') == '' || filter_var(get_nfilter_request_var('ipaddress'), FILTER_VALIDATE_IP)) {
					$save['ipaddress'] = get_nfilter_request_var('ipaddress');
				} else {
					$_SESSION['sess_error_fields']['ipaddress'] = 'ipaddress';
					raise_message(3);
				}
			}
		}

		if ($category == 'dns') {
			if ($subcategory == 'dns') {
				if (filter_var(get_nfilter_request_var('dns_query'), FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
					$save['dns_query'] = get_nfilter_request_var('dns_query');
				} else {
					$_SESSION['sess_error_fields']['dns_query'] = 'dns_query';
					raise_message(3);
				}
			} else { // dns over https
				if (filter_var(get_nfilter_request_var('dns_query'), FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '#^/(resolve|query-dns)\?[a-zA-Z0-9\=\.&\-]+$#')))) {
					$save['dns_query'] = get_nfilter_request_var('dns_query');
				} else {
					$_SESSION['sess_error_fields']['dns_query'] = 'dns_query';
					raise_message(3);
				}
			}
		}

		if ($category == 'ldap') {
			if (isset_request_var('ldapsearch')) {
				form_input_validate(get_nfilter_request_var('ldapsearch'), 'ldapsearch', '^[a-zA-Z0-9\.\- \*\=,]+$', false, 3);
				$save['ldapsearch'] = get_nfilter_request_var('ldapsearch');
			}
		}

		if ($category == 'snmp') {
			if (filter_var(get_nfilter_request_var('snmp_oid'), FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '#^[0-9\.]+$#')))) {
				$save['snmp_oid'] = get_nfilter_request_var('snmp_oid');
			} else {
				$_SESSION['sess_error_fields']['snmp_oid'] = 'snmp_oid';
				raise_message(3);
			}
		}

		if ($category == 'ssh') {
			if (filter_var(get_nfilter_request_var('ssh_command'), FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '#^[a-zA-Z0-9\./\-]+$#')))) {
				$save['ssh_command'] = get_nfilter_request_var('ssh_command');
			} else {
				$_SESSION['sess_error_fields']['ssh_command'] = 'ssh_command';
				raise_message(3);
			}
		}

		if (isset_request_var('notes')) {
			$save['notes'] = get_nfilter_request_var('notes');
		}

		if (isset_request_var('requiresauth')) {
			$save['requiresauth'] = 'on';
		} else {
			$save['requiresauth'] = '';
		}

		if (isset_request_var('checkcert')) {
			$save['checkcert'] = 'on';
		} else {
			$save['checkcert'] = '';
		}

		if (isset_request_var('certexpirenotify')) {
			$save['certexpirenotify'] = 'on';
		} else {
			$save['certexpirenotify'] = '';
		}

		if ($category == 'web' || $category == 'ftp' || $category == 'smb') {
			if (isset_request_var('path')) {
				form_input_validate(get_nfilter_request_var('path'), 'path', '^[a-zA-Z0-9_;\-\/\.\?=]+$', false, 3);
				$save['path'] = get_nfilter_request_var('path');
			}
		}


		$save['name']    = form_input_validate(get_nfilter_request_var('name'), 'name', '', false, 3);
//!!pm - tyhle bych mel nejak osetrovat asi
		$save['search']          = get_nfilter_request_var('search');
		$save['search_maint']    = get_nfilter_request_var('search_maint');
		$save['search_failed']   = get_nfilter_request_var('search_failed');
		$save['notify_extra']    = get_nfilter_request_var('notify_extra');

		if (api_plugin_installed('thold')) {
			$save['notify_list']     = get_filter_request_var('notify_list');
		}


		plugin_servcheck_remove_old_users();

		if (!is_error_message()) {
			$saved_id = sql_save($save, 'plugin_servcheck_test');

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

function purge_log_events($id) {
	$name = db_fetch_cell_prepared('SELECT name
		FROM plugin_servcheck_test
		WHERE id = ?',
		array($id));

	db_execute_prepared('DELETE FROM plugin_servcheck_log WHERE test_id = ?', array($id));

	raise_message('test_log_purged', __('The Service Check history was purged for %s', $name, 'servcheck'), MESSAGE_LEVEL_INFO);
}




function data_edit() {
	global $servcheck_test_fields, $service_types;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	$data = array();

	if (!isempty_request_var('id')) {
		$data = db_fetch_row_prepared('SELECT *
			FROM plugin_servcheck_test
			WHERE id = ?',
			array(get_request_var('id')));

		$header_label = __('Test [edit: %s]', $data['name']);
	} else {
		$header_label = __('Test [new]');
	}

	if (!api_plugin_installed('thold')) {
		$servcheck_test_fields['notify_list']['method'] = 'hidden';
	}


	form_start(htmlspecialchars(basename($_SERVER['PHP_SELF'])));

	html_start_box($header_label, '100%', true, '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($servcheck_test_fields, $data)
		)
	);

	form_hidden_box('save_component', '1', '');

	html_end_box(true, true);

	form_save_button(htmlspecialchars(basename($_SERVER['PHP_SELF'])));

	?>
	<script type='text/javascript'>
	$(function() {
		var msWidth = 100;

		$('#notify_accounts option').each(function() {
			if ($(this).textWidth() > msWidth) {
				msWidth = $(this).textWidth();
			}
			$('#notify_accounts').css('width', msWidth+80+'px');
		});

		$('#notify_accounts').hide().multiselect({
			noneSelectedText: '<?php print __('No Users Selected', 'servcheck');?>',
			selectedText: function(numChecked, numTotal, checkedItems) {
				myReturn = numChecked + ' <?php print __('Users Selected', 'servcheck');?>';
				$.each(checkedItems, function(index, value) {
					if (value.value == '0') {
						myReturn='<?php print __('All Users Selected', 'servcheck');?>';
						return false;
					}
				});
				return myReturn;
			},
			checkAllText: '<?php print __('All', 'servcheck');?>',
			uncheckAllText: '<?php print __('None', 'servcheck');?>',
			uncheckall: function() {
				$(this).multiselect('widget').find(':checkbox:first').each(function() {
					$(this).prop('checked', true);
				});
			},
			open: function(event, ui) {
				$("input[type='search']:first").focus();
			},
			click: function(event, ui) {
				checked=$(this).multiselect('widget').find('input:checked').length;

				if (ui.value == 0) {
					if (ui.checked == true) {
						$('#notify_accounts').multiselect('uncheckAll');
						$(this).multiselect('widget').find(':checkbox:first').each(function() {
							$(this).prop('checked', true);
						});
					}
				} else if (checked == 0) {
					$(this).multiselect('widget').find(':checkbox:first').each(function() {
						$(this).click();
					});
				} else if ($(this).multiselect('widget').find('input:checked:first').val() == '0') {
					if (checked > 0) {
						$(this).multiselect('widget').find(':checkbox:first').each(function() {
							$(this).click();
							$(this).prop('disable', true);
						});
					}
				}
			}
		}).multiselectfilter({
			label: '<?php print __('Search', 'servcheck');?>',
			width: msWidth
		});

		setTest();
	});

	function setTest() {
		var test_type = $('#type').val();
		var tmp = test_type.split('_');

		var category = tmp[0];
		var subcategory = tmp[1];

		$('#row_format').hide();
		$('#row_dns_query').hide();
		$('#row_ldapsearch').hide();
		$('#row_ipaddress').hide();
		$('#row_path').hide();
		$('#row_requiresauth').hide();
		$('#row_proxy_id').hide();
		$('#row_ldapsearch').hide();
		$('#row_ca_id').hide();
		$('#row_checkcert').hide();
		$('#row_certexpirenotify').hide();
		$('#row_hostname').hide();
		$('#row_snmp_oid').hide();
		$('#row_ssh_command').hide();
		$('#row_cred_id').hide();

		switch(category) {
			case 'web':

				$('#row_hostname').show();
				$('#row_ipaddress').show();
				$('#row_path').show();
				$('#row_requiresauth').show();
				$('#row_proxy_id').show();
				$('#row_cred_id').show();

				if (subcategory == 'https') {
					$('#row_ca_id').show();
					$('#row_checkcert').show();
					$('#row_certexpirenotify').show();
				}

				break;
			case 'mail':
				$('#row_hostname').show();

				if (subcategory == 'smtps' || subcategory == 'smtptls') {
					$('#row_cred_id').show();
				}

				if (subcategory == 'smtps') {
					$('#row_ca_id').show();
					$('#row_checkcert').show();
					$('#row_certexpirenotify').show();
				}

				break
			case 'dns':
				$('#row_dns_query').show();
				$('#row_hostname').show();

				if (subcategory == 'doh') {
					$('#row_ca_id').show();
					$('#row_checkcert').show();
					$('#row_certexpirenotify').show();
				}

				break;

			case 'ldap':
				$('#row_ldapsearch').show();
				$('#row_hostname').show();
				$('#row_cred_id').show();

				break;
			case 'ftp':
				$('#row_path').show();
				$('#row_hostname').show();
				$('#row_cred_id').show();

				if (subcategory == 'tftp') {
					$('#row_cred_id').hide();
				}
				break;
			case 'smb':
				$('#row_path').show();
				$('#row_hostname').show();
				$('#row_cred_id').show();

				break;
			case 'mqtt':
				$('#row_path').show();
				$('#row_hostname').show();
				$('#row_cred_id').show();
				break;
			case 'rest':
				$('#row_format').show();

				break;

			case 'snmp':
				$('#row_hostname').show();
				$('#row_cred_id').show();
				$('#row_snmp_oid').show();

				break;

			case 'ssh':
				$('#row_hostname').show();
				$('#row_cred_id').show();
				$('#row_ssh_command').show();

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
		'refresh' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => read_config_option('log_refresh_interval')
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
		'state' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		)
	);

	validate_store_request_vars($filters, 'sess_servcheck_test');
}

function servcheck_log_request_validation() {

	$filters = array(
		'id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '-1'
		),
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
			'filter' => FILTER_CALLBACK,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
		),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'lastcheck',
			'options' => array('options' => 'sanitize_search_string')
		),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'DESC',
			'options' => array('options' => 'sanitize_search_string')
		),
	);

	validate_store_request_vars($filters, 'sess_servcheck_log');
}


function servcheck_show_history() {
	global $config, $httperrors, $search_result;

	servcheck_log_request_validation();

	$refresh['seconds'] = 9999999;
	$refresh['page']    = htmlspecialchars(basename($_SERVER['PHP_SELF'])) . '?action=history&id=' . get_filter_request_var('id') . '&header=false';
	$refresh['logout']  = 'false';

	set_page_refresh($refresh);

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	$sql_where    = '';
	$sql_params[] = get_filter_request_var('id');

	if (get_request_var('filter') != '') {
		$sql_where .= 'AND sl.lastcheck LIKE ?';
		$sql_params[] = '%' . get_request_var('filter') . '%';
	}

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$result = db_fetch_assoc_prepared("SELECT sl.*, st.name
		FROM plugin_servcheck_log AS sl
		INNER JOIN plugin_servcheck_test st
		ON sl.test_id = st.id
		WHERE sl.test_id = ?
		$sql_where
		$sql_order
		$sql_limit",
		$sql_params);

	$total_rows = db_fetch_cell_prepared("SELECT COUNT(*)
		FROM plugin_servcheck_log as sl
		WHERE sl.test_id = ?
		$sql_where",
		$sql_params);

	$display_text = array(
		'lastcheck' => array(
			'display' => __('Date', 'servcheck')
		),
		'name' => array(
			'display' => __('Name', 'servcheck'),
		),
		'result' => array(
			'display' => __('Result', 'servcheck'),
		),
		'result_search' => array(
			'display' => __('Search result', 'servcheck'),
		),
		'curl_return_code' => array(
			'display' => __('Curl return code', 'servcheck'),
			'align'   => 'right',
			'sort'    => 'DESC'
		),
		'namelookup_time' => array(
			'display' => __('DNS', 'servcheck'),
			'align'   => 'right',
			'sort'    => 'DESC'
		),
		'connect_time' => array(
			'display' => __('Connect', 'servcheck'),
			'align'   => 'right',
			'sort'    => 'DESC'
		),
		'redirect_time' => array(
			'display' => __('Redirect', 'servcheck'),
			'align'   => 'right',
			'sort'    => 'DESC'
		),
		'total_time' => array(
			'display' => __('Total', 'servcheck'),
			'align'   => 'right',
			'sort'    => 'DESC'
		),
	);

	$columns = cacti_sizeof($display_text);

	$nav = html_nav_bar(htmlspecialchars(basename($_SERVER['PHP_SELF'])) . '?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, $columns, __('Proxies', 'servcheck'), 'page', 'main');

	form_start(htmlspecialchars(basename($_SERVER['PHP_SELF'])), 'chk');

	servcheck_show_tab('servcheck_test.php');

	servcheck_log_filter();

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	if (count($result)) {
		foreach ($result as $row) {
			if ($row['result_search'] != 'ok') {
				$style = "color:rgba(10,10,10,0.8);background-color:rgba(242, 242, 100, 0.6)";
			} elseif ($row['result'] == 'ok') {
				$style = "color:rgba(10,10,10,0.8);background-color:rgba(204, 255, 204, 0.6)";
			} else {
				$style = "color:rgba(10,10,10,0.8);background-color:rgba(242, 25, 36, 0.6);";
			}

			print "<tr class='tableRow selectable' style='$style' id='line" . $row['id'] . "'>";

			form_selectable_cell($row['lastcheck'], $row['id']);
			form_selectable_cell($row['name'], $row['id']);
			form_selectable_cell(($row['result'] == 'ok' ? __('Service UP', 'servcheck') : __('Service Down', 'servcheck')), $row['id']);
			form_selectable_cell($search_result[$row['result_search']], $row['id']);

			form_selectable_cell('<a href="' . html_escape($config['url_path'] . 'plugins/servcheck/servcheck_curl_code.php?findcode=' . $row['curl_return_code']) . '">' . $row['curl_return_code'] . '<a/>', $row['id'], '', 'right');

			form_selectable_cell(round($row['namelookup_time'], 4), $row['id'], '', ($row['namelookup_time'] > 4 ? 'background-color: red;text-align:right' : ($row['namelookup_time'] > 1 ? 'background-color: yellow;text-align:right':'text-align:right')));
			form_selectable_cell(round($row['connect_time'], 4), $row['id'], '', ($row['connect_time'] > 4 ? 'background-color: red;text-align:right' : ($row['connect_time'] > 1 ? 'background-color: yellow;text-align:right':'text-align:right')));
			form_selectable_cell(round($row['redirect_time'], 4), $row['id'], '', ($row['redirect_time'] > 4 ? 'background-color: red;text-align:right' : ($row['redirect_time'] > 1 ? 'background-color: yellow;text-align:right':'text-align:right')));
			form_selectable_cell(round($row['total_time'], 4), $row['id'], '', ($row['total_time'] > 4 ? 'background-color: red;text-align:right' : ($row['total_time'] > 1 ? 'background-color: yellow;text-align:right':'text-align:right')));

			form_end_row();
		}
	}

	html_end_box(false);

	if (cacti_sizeof($result)) {
		print $nav;
	}

	form_end();
}


function servcheck_show_graph() {
	global $graph_interval;

	servcheck_show_tab('servcheck_test.php');
	$id = get_filter_request_var('id');

	$result = db_fetch_row_prepared('SELECT name
		FROM plugin_servcheck_test
		WHERE id = ?',
		array($id));

	print '<b>' . html_escape($result['name']) . ':</b><br/>';

	foreach ($graph_interval as $key => $value) {
		print '<b>' . ($value) . ':</b>';
		plugin_servcheck_graph ($id, $key);
		print '<br/><hr style="width: 50%; margin-left: 0; margin-right: auto;"><br/>'; //print line below the graph.
	}
}


function data_list() {
	global $config, $servcheck_actions_menu, $refresh;

	request_validation();

	$statefilter = '';
	if (isset_request_var('state')) {
		if (get_request_var('state') == '-1') {
			$statefilter = '';                                     //state = Any
		} elseif (get_request_var('state') == '2') {
			$statefilter = "plugin_servcheck_test.enabled = ''";   //state = Disabled
		} elseif (get_request_var('state') == '1') {
			$statefilter = "plugin_servcheck_test.enabled = 'on'"; //state = Enabled
		} elseif (get_request_var('state') == '3') {
			$statefilter = 'plugin_servcheck_test.triggered = 1';  //state = Triggered
		}
	}

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	servcheck_show_tab(htmlspecialchars(basename($_SERVER['PHP_SELF'])));

	servcheck_filter();

	$sql_where = '';

	if ($statefilter != '') {
		$sql_where .= ($sql_where == '' ? 'WHERE ' : ' AND ') . $statefilter;
	}

	$sql_order = get_order_string();
	// `statistics` is not a table column, the columns are:
	// `stats_ok` and `stats_bad`, hence, the ORDER BY should be based on these 2 columns
	if ($sql_order == 'ORDER BY `statistics` ASC') {
		$sql_order = 'ORDER BY `stats_ok` ASC, `stats_bad` ASC';
	} elseif ($sql_order == 'ORDER BY `statistics` DESC') {
		$sql_order = 'ORDER BY `stats_ok` DESC, `stats_bad` DESC';
	}
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	if (get_request_var('rfilter') != '') {
		$sql_where .= ($sql_where == '' ? 'WHERE ' : ' AND ') .
			'name RLIKE \'' . get_request_var('rfilter') . '\' OR ' .
			'path RLIKE \'' . get_request_var('rfilter') . '\' OR ' .
			'search RLIKE \'' . get_request_var('rfilter') . '\' OR ' .
			'search_maint RLIKE \'' . get_request_var('rfilter') . '\' OR ' .
			'search_failed RLIKE \'' . get_request_var('rfilter') . '\'';
	}

	$result = db_fetch_assoc("SELECT *
		FROM plugin_servcheck_test
		$sql_where
		$sql_order
		$sql_limit");

	$total_rows = db_fetch_cell("SELECT COUNT(id)
		FROM plugin_servcheck_test
		$sql_where");

	$display_text = array(
		'nosort' => array(
			'display' => __('Actions', 'servcheck'),
			'sort'    => '',
			'align'   => 'left'
		),
		'name' => array(
			'display' => __('Name', 'servcheck'),
			'sort'    => 'ASC',
			'align'   => 'left'
		),
		'enabled' => array(
			'display' => __('Enabled', 'servcheck'),
			'sort'    => 'ASC',
			'align'   => 'right'
		),
		'lastcheck' => array(
			'display' => __('Last Check', 'servcheck'),
			'sort'    => 'ASC',
			'align'   => 'right'
		),
		'statistics' => array(
			'display' => __('Stats (OK/problem)', 'servcheck'),
			'sort'    => 'ASC',
			'align'   => 'right'
		),
		'triggered' => array(
			'display' => __('Triggered', 'servcheck'),
			'sort'    => 'ASC',
			'align'   => 'right'
		),
		'result' => array(
			'display' => __('Result', 'servcheck'),
			'sort'    => 'ASC',
			'align'   => 'right'
		),
		'result_search' => array(
			'display' => __('Search result', 'servcheck'),
			'sort'    => 'ASC',
			'align'   => 'right'
		),
		'curl_result' => array(
			'display' => __('Curl return code', 'servcheck'),
			'sort'    => 'ASC',
			'align'   => 'right'
		),
	);

	$columns = cacti_sizeof($display_text);

	$nav = html_nav_bar(htmlspecialchars(basename($_SERVER['PHP_SELF'])) . '?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, $columns, __('Tests', 'servcheck'), 'page', 'main');

	form_start(htmlspecialchars(basename($_SERVER['PHP_SELF'])), 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	if (cacti_sizeof($result)) {
		foreach ($result as $row) {
			$last_log = db_fetch_row_prepared("SELECT *,
				(SELECT count(id) FROM plugin_servcheck_log WHERE test_id = ? ) as `count`
				FROM plugin_servcheck_log
				WHERE test_id = ? ORDER BY id DESC LIMIT 1",
				array ($row['id'], $row['id']));

			if (!$last_log) {
				$last_log['result'] = 'not yet';
				$last_log['result_search'] = 'not yet';
				$last_log['curl_return_code'] = '0';
				$last_log['count'] = 0;
			}

			if ($row['enabled'] == '') {
				$style = "color:rgba(10,10,10,0.8);background-color:rgba(205, 207, 196, 0.6)";
			} elseif ($row['failures'] > 0 && $row['failures'] < $row['downtrigger']) {
				$style = "color:rgba(10,10,10,0.8);background-color:rgba(242, 242, 36, 0.6);";
			} elseif ($last_log['result'] != 'ok' && strtotime($row['lastcheck']) > 0) {
				$style = "color:rgba(10,10,10,0.8);background-color:rgba(242, 25, 36, 0.6);";
			} else {
				$style = "color:rgba(10,10,10,0.8);background-color:rgba(204, 255, 204, 0.6)";
			}

			print "<tr class='tableRow selectable' style='$style' id='line" . $row['id'] . "'>";

			print "<td width='1%' style='padding:0px;white-space:nowrap'>
				<a class='pic' href='" . html_escape($config['url_path'] . 'plugins/servcheck/servcheck_test.php?action=edit&id=' . $row['id']) . "' title='" . __esc('Edit Service Check', 'servcheck') . "'>
					<i class='tholdGlyphEdit fas fa-wrench'></i>
				</a>";

			if ($row['enabled'] == '') {
				print "<a class='pic' href='" . html_escape($config['url_path'] . 'plugins/servcheck/servcheck_test.php?action=enable&id=' . $row['id']) . "' title='" . __esc('Enable Service Check', 'servcheck') . "'>
					<i class='tholdGlyphEnable fas fa-play-circle'></i>
				</a>";
			} else {
				print "<a class='pic' href='" . html_escape($config['url_path'] . 'plugins/servcheck/servcheck_test.php?action=disable&id=' . $row['id']) . "' title='" . __esc('Disable Service Check', 'servcheck') . "'>
					<i class='tholdGlyphDisable fas fa-stop-circle'></i>
				</a>";
			}

			print "<a class='pic' href='" . html_escape($config['url_path'] . 'plugins/servcheck/servcheck_test.php?action=history&id=' . $row['id']) . "' title='" . __esc('View Service Check History', 'servcheck') . "'>
					<i class='tholdGlyphLog fas fa-exclamation-triangle'></i>
				</a>";

			print "<a class='pic' href='" . html_escape($config['url_path'] . 'plugins/servcheck/servcheck_test.php?action=last_data&id=' . $row['id']) . "' title='" . __esc('View Last returned data', 'servcheck') . "'>
					<i class='tholdGlyphLog fas fa-search'></i>
				</a>";

			if ($last_log['count'] > 4) {
				print "<a class='pic' href='" . html_escape($config['url_path'] . 'plugins/servcheck/servcheck_test.php?action=graph&id=' . $row['id']) . "' title='" . __esc('View Graph', 'servcheck') . "'>
						<i class='tholdGlyphLog fas fa-chart-area'></i>
					</a>
				</td>";
			} else {
				print "<i class='tholdGlyphLog fas fa-chart-area'></i>
				</td>";
			}

			form_selectable_cell($row['name'], $row['id']);
			form_selectable_cell(($row['enabled'] == 'on' ? __('Enabled', 'servcheck') : __('Disabled', 'servcheck')), $row['id'], '', 'right');

			if ($row['lastcheck'] == '0000-00-00 00:00:00') {
				form_selectable_cell(__('N/A', 'servcheck'), $row['id'], '', 'right');
			} else {
				form_selectable_cell($row['lastcheck'], $row['id'], '', 'right');
			}

			form_selectable_cell($row['stats_ok'] . '/' . $row['stats_bad'], $row['id'], '', 'right');
			$tmp = ' (' . $row['failures'] . ' of ' . $row['downtrigger'] . ')';
			form_selectable_cell($row['triggered'] == '0' ? __('No', 'servcheck') . $tmp : __('Yes', 'servcheck') . $tmp, $row['id'], '', 'right');
			form_selectable_cell($last_log['result'] == 'not yet' ? __('Not tested yet', 'servcheck'): $last_log['result'], $row['id'], '', 'right');
			form_selectable_cell($last_log['result_search'] == 'not yet' ? __('Not tested yet', 'servcheck'): $last_log['result_search'], $row['id'], '', 'right');
			form_selectable_cell('<a href="' . html_escape($config['url_path'] . 'plugins/servcheck/servcheck_curl_code.php?findcode=' . $last_log['curl_return_code']) . '">' . $last_log['curl_return_code'] . '<a/>', $row['id'], '', 'right');

			form_checkbox_cell($row['id'], $row['id']);

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

//!!pm -  je ten skript nutny?
	?>
	<script type='text/javascript'>
/*
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
*/
	</script>
	<?php

}


function servcheck_show_last_data() {

	servcheck_show_tab(htmlspecialchars(basename($_SERVER['PHP_SELF'])));

	$result = db_fetch_row_prepared('SELECT name, last_returned_data
		FROM plugin_servcheck_test
		WHERE id = ?',
		array(get_filter_request_var('id')));

	print '<b>' . __('Last returned data of test', 'servcheck') . ' ' . html_escape($result['name']) . ':</b><br/>';
	print '<style> pre {white-space: pre-wrap; word-wrap: break-word; overflow-wrap: break-word; max-width: 25%; width: 25%}</style><pre>' . html_escape($result['last_returned_data']) . '</pre>';
}



function servcheck_filter() {

	global $item_rows, $page_refresh_interval;

	$refresh['page']    = 'servcheck_test.php?header=false';
	$refresh['seconds'] = get_request_var('refresh');
	$refresh['logout']  = 'false';

	set_page_refresh($refresh);

	html_start_box(__('Servcheck Test Management', 'servcheck') , '100%', '', '3', 'center', htmlspecialchars(basename($_SERVER['PHP_SELF'])) . '?action=edit');
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
						<input type='text' class='ui-state-default ui-corner-all' id='rfilter' size='30' value='<?php print html_escape_request_var('rfilter');?>'>
					</td>
					<td>
						<?php print __('State', 'servcheck');?>
					</td>
					<td>
						<select id='state'>
							<option value='-1'><?php print __('Any', 'servcheck');?></option>
							<?php
							foreach (array('2' => 'Disabled', '1' => 'Enabled', '3' => 'Triggered') as $key => $row) {
								print "<option value='" . $key . "'" . (isset_request_var('state') && $key == get_request_var('state') ? ' selected' : '') . '>' . $row . '</option>';
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Refresh', 'servcheck');?>
					</td>
					<td>
						<select id='refresh'>
							<?php
							foreach($page_refresh_interval AS $seconds => $display_text) {
								print "<option value='" . $seconds . "'";
								if (get_request_var('refresh') == $seconds) {
									print ' selected';
								}
								print '>' . $display_text . "</option>";
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Checks', 'servcheck');?>
					</td>
					<td>
						<select id='rows'>
							<?php
							print "<option value='-1'" . (get_request_var('rows') == $key ? ' selected':'') . ">" . __('Default', 'servcheck') . "</option>";
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
							<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc('Go');?>' title='<?php print __esc('Set/Refresh Filters', 'servcheck');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear');?>' title='<?php print __esc('Clear Filters', 'servcheck');?>'>
						</span>
					</td>
				</tr>
			</table>
		</form>

		<script type='text/javascript'>
		function applyFilter() {
			strURL  = '<?php print htmlspecialchars(basename($_SERVER['PHP_SELF']));?>?header=false&state=' + $('#state').val();
			strURL += '&refresh=' + $('#refresh').val();
			strURL += '&rfilter=' + base64_encode($('#rfilter').val());
			strURL += '&rows=' + $('#rows').val();
			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			strURL = '<?php print htmlspecialchars(basename($_SERVER['PHP_SELF']));?>?clear=1&header=false';
			loadPageNoHeader(strURL);
		}
		$(function() {
			$('#refresh, #state, #rows, #rfilter').change(function() {
				applyFilter();
			});

			$('#go').click(function() {
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





function servcheck_log_filter() {
	global $item_rows;

	html_start_box(__('Service Check History', 'servcheck') , '100%', '', '3', 'center', '');

	?>
	<tr class='even noprint'>
		<td class='noprint'>
			<form id='servcheck' action='servcheck_test.php?action=history'>
			<table class='filterTable'>
				<tr class='noprint'>
					<td>
						<?php print __('Date Search', 'servcheck');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' size='30' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Entries', 'servcheck');?>
					</td>
					<td>
						<select id='rows'>
							<?php
							print "<option value='-1'" . (get_request_var('rows') == '-1' ? ' selected':'') . ">" . __('Default', 'servcheck') . "</option>";
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
							<input type='submit' id='go' alt='' value='<?php print __esc('Go', 'servcheck');?>'>
							<input type='button' id='clear' alt='' value='<?php print __esc('Clear', 'servcheck');?>'>
							<input type='button' id='purge' alt='' value='<?php print __esc('Purge Events', 'servcheck');?>'>
						</span>
					</td>
				</tr>
			</table>
			</form>
		</td>
	</tr>
	<script type='text/javascript'>

	refreshMSeconds=99999999;

	function applyFilter() {
		strURL  = '<?php print htmlspecialchars(basename($_SERVER['PHP_SELF']));?>?action=history&header=false&id=<?php print get_filter_request_var('id');?>';
		strURL += '&filter=' + $('#filter').val();
		strURL += '&rows=' + $('#rows').val();
		refreshMSeconds=99999999;
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = '<?php print htmlspecialchars(basename($_SERVER['PHP_SELF']));?>?action=history&clear=1&header=false';
		loadPageNoHeader(strURL);
	}

	function purgeEvents() {
		strURL = '<?php print htmlspecialchars(basename($_SERVER['PHP_SELF']));?>?action=purge&id=<?php print get_request_var('id');?>';
		loadPageNoHeader(strURL);
	}

	$(function() {
		$('#rows').change(function() {
			applyFilter();
		});

		$('#clear').click(function() {
			clearFilter();
		});

		$('#purge').click(function() {
			purgeEvents();
		});

		$('#servcheck').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});

	</script>

	<?php

	html_end_box();
}
