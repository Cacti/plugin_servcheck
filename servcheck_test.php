<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
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
	case 'enable':
		$id = get_filter_request_var('id');

		if ($id > 0) {
			db_execute_prepared('UPDATE plugin_servcheck_test SET enabled = "on" WHERE id = ?', array($id));
		}

		header('Location: servcheck_test.php?header=false');
		exit;

		break;
	case 'disable':
		$id = get_filter_request_var('id');

		if ($id > 0) {
			db_execute_prepared('UPDATE plugin_servcheck_test SET enabled = "" WHERE id = ?', array($id));
		}

		header('Location: servcheck_test.php?header=false');
		exit;

		break;
	case 'purge':
		$id = get_filter_request_var('id');

		if ($id > 0) {
			purge_log_events($id);
		}

		header('Location: servcheck_test.php?header=false');
		exit;

		break;
	case 'edit':
		top_header();
		servcheck_edit_test();
		bottom_footer();

		break;
	case 'history':
		servcheck_show_history();

		break;
	case 'graph':
		servcheck_show_graph();

		break;

	case 'last_data':
		servcheck_show_last_data();

		break;
	default:
		list_tests();

		break;
}

exit;

function form_actions() {
	global $servcheck_actions_test;

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));
		$action         = get_nfilter_request_var('drp_action');

		if ($selected_items != false) {
			if (cacti_sizeof($selected_items)) {
				foreach($selected_items as $test) {
					$tests[] = $test;
				}
			}

			if (cacti_sizeof($tests)) {
				if ($action == SERVCHECK_ACTION_TEST_DELETE) { // delete
					foreach ($tests as $id) {
						db_execute_prepared('DELETE FROM plugin_servcheck_test WHERE id = ?', array($id));
						db_execute_prepared('DELETE FROM plugin_servcheck_log WHERE test_id = ?', array($id));
					}
				} elseif ($action == SERVCHECK_ACTION_TEST_DISABLE) { // disable
					foreach ($tests as $id) {
						db_execute_prepared('UPDATE plugin_servcheck_test SET enabled = "" WHERE id = ?', array($id));
					}
				} elseif ($action == SERVCHECK_ACTION_TEST_ENABLE) { // enable
					foreach ($tests as $id) {
						db_execute_prepared('UPDATE plugin_servcheck_test SET enabled = "on" WHERE id = ?', array($id));
					}
				} elseif ($action == SERVCHECK_ACTION_TEST_DUPLICATE) { // duplicate
					foreach($tests as $test) {
						$newid = 1;

						foreach ($tests as $id) {
							$save = db_fetch_row_prepared('SELECT * FROM plugin_servcheck_test WHERE id = ?', array($id));
							$save['id']           = 0;
							$save['display_name'] = 'New Service Check (' . $newid . ')';
							$save['path']         = '/';
							$save['lastcheck']    = '0000-00-00 00:00:00';
							$save['triggered']    = 0;
							$save['enabled']      = '';
							$save['username']     = '';
							$save['password']     = '';
							$save['failures']     = 0;
							$save['stats_ok']     = 0;
							$save['stats_bad']    = 0;

							$id = sql_save($save, 'plugin_servcheck_test');

							$newid++;
						}
					}
				}
			}
		}

		header('Location: servcheck_test.php?header=false');

		exit;
	}

	/* setup some variables */
	$test_list  = '';
	$test_array = array();

	/* loop through each of the tests selected on the previous page and get more info about them */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$test_list .= '<li>' . __esc(db_fetch_cell_prepared('SELECT display_name FROM plugin_servcheck_test WHERE id = ?', array($matches[1]))) . '</li>';
			$test_array[] = $matches[1];
		}
	}

	top_header();

	form_start('servcheck_test.php');

	html_start_box($servcheck_actions_test[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

	$action = get_nfilter_request_var('drp_action');

	if (cacti_sizeof($test_array)) {
		if ($action == SERVCHECK_ACTION_TEST_DELETE) {
			print"	<tr>
				<td class='topBoxAlt'>
					<p>" . __n('Click \'Continue\' to Delete the following tests.', 'Click \'Continue\' to Delete following tests.', cacti_sizeof($test_array)) . "</p>
					<div class='itemlist'><ul>$test_list</ul></div>
				</td>
			</tr>";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc_n('Delete rest', 'Delete tests', cacti_sizeof($test_array)) . "'>";
		} elseif ($action == SERVCHECK_ACTION_TEST_DISABLE) {
			print "<tr>
				<td class='topBoxAlt'>
					<p>" . __n('Click \'Continue\' to Disable the following test.', 'Click \'Continue\' to Disable following Tests.', cacti_sizeof($test_array)) . "</p>
					<div class='itemlist'><ul>$test_list</ul></div>
				</td>
			</tr>";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc_n('Disable test', 'Disable Tests', cacti_sizeof($test_array)) . "'>";
		} elseif ($action == SERVCHECK_ACTION_TEST_ENABLE) {
			print "<tr>
				<td class='topBoxAlt'>
					<p>" . __n('Click \'Continue\' to Enable the following test.', 'Click \'Continue\' to Enable following tests.', cacti_sizeof($test_array)) . "</p>
					<div class='itemlist'><ul>$test_list</ul></div>
				</td>
			</tr>";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc_n('Enable test', 'Enable tests', cacti_sizeof($test_array)) . "'>";
		} elseif ($action == SERVCHECK_ACTION_TEST_DUPLICATE) {
			print "<tr>
				<td class='topBoxAlt'>
					<p>" . __n('Click \'Continue\' to Duplicate the following test.', 'Click \'Continue\' to Duplicate following tests.', cacti_sizeof($test_array)) . "</p>
					<div class='itemlist'><ul>$test_list</ul></div>
				</td>
			</tr>";

			$save_html = "<input type='button' value='" . __esc('Cancel') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' value='" . __esc('Continue') . "' title='" . __esc_n('Duplicate test', 'Duplicate tests', cacti_sizeof($test_array)) . "'>";
		}
	} else {
		raise_message(40);
		header('Location: servcheck_test.php');
		exit;
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($test_array) ? serialize($test_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_nfilter_request_var('drp_action') . "'>
			$save_html
		</td>
	</tr>";

	html_end_box();

	form_end();

	bottom_footer();
}

function form_save() {
	global $service_types;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('poller_id');
	get_filter_request_var('downtrigger');
	get_filter_request_var('timeout_trigger');
	get_filter_request_var('how_often');
	/* ==================================================== */

	if (isset_request_var('id')) {
		$save['id'] = get_request_var('id');
	} else {
		$save['id'] = 0;
	}

	if (isset_request_var('poller_id')) {
		$save['poller_id'] = get_request_var('poller_id');
	} else {
		$save['poller_id'] = 1;
	}

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
		raise_message(3);
		$_SESSION['sess_error_fields']['type'] = 'type';
	}

	if (get_filter_request_var('hostname', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^[a-zA-Z0-9\.\-]+(\:[0-9]{1,5})?$/')))) {
		$save['hostname'] = get_nfilter_request_var('hostname');
	} else {
		raise_message(3);
		$_SESSION['sess_error_fields']['hostname'] = 'hostname';
	}

	if ($category == 'dns') {
		if (filter_var(get_nfilter_request_var('dns_query'), FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
			$save['dns_query'] = get_nfilter_request_var('dns_query');
		} else {
			raise_message(3);
			$_SESSION['sess_error_fields']['dns_query'] = 'dns_query';
		}
	}

	if ($category == 'ldap') {
		if (get_filter_request_var('ldapsearch', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^[a-zA-Z0-9\.\- \*\=,]+$/')))) {
			$save['ldapsearch'] = get_nfilter_request_var('ldapsearch');
		} else {
			raise_message(3);
			$_SESSION['sess_error_fields']['ldapsearch'] = 'ldapsearch';
		}
	}

	if (isset_request_var('notes')) {
		$save['notes'] = get_nfilter_request_var('notes');
	}

	if (isset_request_var('external_id')) {
		$save['external_id'] = get_nfilter_request_var('external_id');
	}

	if (get_filter_request_var('ca') > 0) {
		$save['ca'] = get_filter_request_var('ca');
	} else {
		$save['ca'] = 0;
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

	if (isset_request_var('username') && get_nfilter_request_var('username') != '' && get_filter_request_var('username', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^[a-z0-9A-Z_\/@.\- \=,]{1,}$/')))) {
		$save['username'] = servcheck_hide_text(get_nfilter_request_var('username'));
		$save['password'] = servcheck_hide_text(get_nfilter_request_var('password'));
	}

	if ($category == 'web' || $category == 'ftp' || $category == 'smb') {
		if (isset_request_var('path') && get_filter_request_var('path', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^[a-zA-Z0-9_;\-\/\.\?=]+$/')))) {
			$save['path'] = get_nfilter_request_var('path');
		} else {
			raise_message(3);
			$_SESSION['sess_error_fields']['path'] = 'path';
		}
	}

	$save['proxy_server']    = get_nfilter_request_var('proxy_server');
	$save['display_name']    = form_input_validate(get_nfilter_request_var('display_name'), 'display_name', '', false, 3);
	$save['search']          = get_nfilter_request_var('search');
	$save['search_maint']    = get_nfilter_request_var('search_maint');
	$save['search_failed']   = get_nfilter_request_var('search_failed');

	if (api_plugin_installed('thold')) {
		$save['notify_list']     = get_filter_request_var('notify_list');
	}

	$save['notify_extra']    = get_nfilter_request_var('notify_extra');
	$save['downtrigger']     = get_filter_request_var('downtrigger');
	$save['timeout_trigger'] = get_filter_request_var('timeout_trigger');
	$save['how_often']       = get_filter_request_var('how_often');

	plugin_servcheck_remove_old_users();

	if (!is_error_message()) {
		$id = sql_save($save, 'plugin_servcheck_test', 'id');

		if ($id) {
			raise_message(1);
		} else {
			raise_message(2);
		}
	}

	header('Location: servcheck_test.php?action=edit&id=' . (isset($id)? $id : get_request_var('id')) . '&header=false');
	exit;

}

function purge_log_events($id) {
	$name = db_fetch_cell_prepared('SELECT display_name
		FROM plugin_servcheck_test
		WHERE id = ?',
		array($id));

	db_execute_prepared('DELETE FROM plugin_servcheck_log WHERE test_id = ?', array($id));

	raise_message('test_log_purged', __('The Service Check history was purged for %s', $name, 'servcheck'), MESSAGE_LEVEL_INFO);
}

function servcheck_edit_test() {
	global $servcheck_test_fields, $service_types;


	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	$test = array();

	if (!isempty_request_var('id')) {
		$test = db_fetch_row_prepared('SELECT * FROM plugin_servcheck_test WHERE id = ?', array(get_request_var('id')), false);
		$header_label = __('Query [edit: %s]', $test['display_name'], 'servcheck');
	} else {
		$header_label = __('Query [new]', 'servcheck');
	}

	if (!api_plugin_installed('thold')) {
		$servcheck_test_fields['notify_list']['method'] = 'hidden';
	}

	if (isset($test['username'])) {
		$test['username'] = servcheck_show_text($test['username']);
	}
	if (isset($test['password'])) {
		$test['password'] = servcheck_show_text($test['password']);
	}

	form_start('servcheck_test.php');

	html_start_box($header_label, '100%', '', '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('form_name' => 'chk'),
			'fields' => inject_form_variables($servcheck_test_fields, $test)
		)
	);

	html_end_box();

	form_save_button('servcheck_test.php', 'return');

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

		switch(category) {
			case 'web':
				$('#row_dns_query').hide();
				$('#row_username').hide();
				$('#row_password').hide();
				$('#row_ldapsearch').hide();

				if (subcategory == 'http') {
					$('#row_ca').hide();
					$('#row_checkcert').hide();
					$('#row_certexpirenotify').hide();
				}

				$('#row_hostname').show();
				$('#row_path').show();
				$('#row_requiresauth').show();
				$('#row_proxy_server').show();
				if (subcategory == 'https') {
					$('#row_ca').show();
					$('#row_checkcert').show();
					$('#row_certexpirenotify').show();
				}

				break;
			case 'mail':
				$('#row_path').hide();
				$('#row_requiresauth').hide();
				$('#row_proxy_server').hide();
				$('#row_dns_query').hide();
				$('#row_ldapsearch').hide();

				if (subcategory == 'smtp' || subcategory == 'smtps' || subcategory == 'smtptls') {
					$('#row_username').hide();
					$('#row_password').hide();
				} else {
					$('#row_username').show();
					$('#row_password').show();
				}

				if (subcategory == 'smtps') {
					$('#row_ca').show();
					$('#row_checkcert').show();
					$('#row_certexpirenotify').show();
				}

				$('#password').attr('type', 'password');

				break
			case 'dns':
				$('#row_path').hide();
				$('#row_requiresauth').hide();
				$('#row_proxy_server').hide();
				$('#row_username').hide();
				$('#row_password').hide();
				$('#row_ldapsearch').hide();

				$('#row_dns_query').show();

				break;
/*
			case 'telnet':
				$('#row_path').hide();
				$('#row_requiresauth').hide();
				$('#row_proxy_server').hide();
				$('#row_dns_query').hide();
				$('#row_ca').hide();
				$('#row_checkcert').hide();
				$('#row_certexpirenotify').hide();

				$('#row_hostname').show();

				break;
*/
			case 'ldap':
				$('#row_dns_query').hide();
				$('#row_path').hide();
				$('#row_requiresauth').hide();
				$('#row_proxy_server').hide();

				$('#row_username').show();
				$('#row_password').show();
				$('#row_ldapsearch').show();

				$('#password').attr('type', 'password');

				break;
			case 'ftp':
				$('#row_dns_query').hide();
				$('#row_requiresauth').hide();
				$('#row_proxy_server').hide();
				$('#row_ldapsearch').hide();

				if (subcategory == 'tftp') {
					$('#row_username').hide();
					$('#row_password').hide();
				}

				$('#row_path').show();
				$('#row_username').show();
				$('#row_password').show();

				$('#password').attr('type', 'password');

				break;
			case 'smb':
				$('#row_dns_query').hide();
				$('#row_requiresauth').hide();
				$('#row_proxy_server').hide();
				$('#row_ldapsearch').hide();

				$('#row_username').show();
				$('#row_password').show();
				$('#row_path').show();

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
			'default' => 'display_name',
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

	validate_store_request_vars($filters, 'sess_servchecktest');
	/* ================= input validation ================= */
}

function servcheck_log_request_validation() {
	global $title, $rows_selector, $config, $reset_multi;

	/* ================= input validation and session storage ================= */
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
	/* ================= input validation ================= */
}

function servcheck_show_history() {
	global $config, $httperrors, $search_result;

	servcheck_log_request_validation();

	if (isset_request_var('id')) {
		$id = get_filter_request_var('id');
	} else {
		header('Location: servcheck_test.php?header=false');
		exit;
	}

	$refresh['seconds'] = 9999999;
	$refresh['page']    = 'servcheck_test.php?action=history&id=' . get_filter_request_var('id') . '&header=false';
	$refresh['logout']  = 'false';

	set_page_refresh($refresh);

	top_header();

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

	$result = db_fetch_assoc_prepared("SELECT sl.*, st.display_name
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
		'display_name' => array(
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

	$nav = html_nav_bar('servcheck_test.php?action=history', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, cacti_sizeof($display_text), __('Tests', 'servcheck'), 'page', 'main');

	servcheck_show_tab('servcheck_test.php');

	servcheck_log_filter();

	print $nav;

	html_start_box('', '100%', '', '4', 'center', '');

	html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), 1, 'servcheck_test.php?action=history&id=' . get_request_var('id'), 'main');

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
			form_selectable_cell($row['display_name'], $row['id']);
			form_selectable_cell(($row['result'] == 'ok' ? __('Service UP', 'servcheck') : __('Service Down', 'servcheck')), $row['id']);
			form_selectable_cell($search_result[$row['result_search']], $row['id']);

			form_selectable_cell('<a href="' . html_escape($config['url_path'] . 'plugins/servcheck/servcheck_curl_code.php?findcode=' . $row['curl_return_code']) . '">' . $row['curl_return_code'] . '<a/>', $row['id'], '', 'right');

			form_selectable_cell(round($row['namelookup_time'], 4), $row['id'], '', ($row['namelookup_time'] > 4 ? 'background-color: red;text-align:right' : ($row['namelookup_time'] > 1 ? 'background-color: yellow;text-align:right':'text-align:right')));
			form_selectable_cell(round($row['connect_time'], 4), $row['id'], '', ($row['connect_time'] > 4 ? 'background-color: red;text-align:right' : ($row['connect_time'] > 1 ? 'background-color: yellow;text-align:right':'text-align:right')));
			form_selectable_cell(round($row['redirect_time'], 4), $row['id'], '', ($row['redirect_time'] > 4 ? 'background-color: red;text-align:right' : ($row['redirect_time'] > 1 ? 'background-color: yellow;text-align:right':'text-align:right')));
			form_selectable_cell(round($row['total_time'], 4), $row['id'], '', ($row['total_time'] > 4 ? 'background-color: red;text-align:right' : ($row['total_time'] > 1 ? 'background-color: yellow;text-align:right':'text-align:right')));

			form_end_row();
		}
	} else {
		form_alternate_row();
		print '<td colspan="' . (cacti_sizeof($display_text)) . '"><i>' . __('No Service Check Events in History', 'servcheck') . '</i></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($result)) {
		print $nav;
	}
}

function servcheck_show_graph() {
	global $graph_interval;

	if (isset_request_var('id')) {
		$id = get_filter_request_var('id');
	} else {
		header('Location: servcheck_test.php?header=false');
		exit;
	}

	top_header();

	servcheck_show_tab('servcheck_test.php');

	$result = db_fetch_row_prepared('SELECT display_name
		FROM plugin_servcheck_test
		WHERE id = ?',
		array($id));

	print '<br/><br/><b>' . html_escape($result['display_name']) . ':</b><br/>';

	foreach ($graph_interval as $key => $value) {
		print ($value) . ': ';
		plugin_servcheck_graph ($id, $key);
		print '<br/><br/>';
	}
}

function servcheck_show_last_data() {
	if (isset_request_var('id')) {
		$id = get_filter_request_var('id');
	} else {
		header('Location: servcheck_test.php?header=false');
		exit;
	}

	top_header();

	servcheck_show_tab('servcheck_test.php');

	$result = db_fetch_row_prepared('SELECT display_name, last_returned_data
		FROM plugin_servcheck_test
		WHERE id = ?',
		array($id));

	print '<br/><br/><b>' . __('Last returned data of test', 'servcheck') . ' ' . html_escape($result['display_name']) . ':</b><br/>';

	print '<pre>' . html_escape($result['last_returned_data']) . '</pre>';
}

function list_tests() {
	global $servcheck_actions_test, $httperrors, $config, $hostid, $refresh;

	/* ================= input validation and session storage ================= */
	$filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'state' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'refresh' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => read_config_option('log_refresh_interval')
		),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'display_name',
			'options' => array('options' => 'sanitize_search_string')
		),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
		)
	);

	validate_store_request_vars($filters, 'sess_wbsu');
	/* ================= input validation ================= */

	servcheck_request_validation();

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

	top_header();

	servcheck_show_tab('servcheck_test.php');

	servcheck_filter();

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	$sql_where = '';

	if ($statefilter != '') {
		$sql_where .= ($sql_where == '' ? 'WHERE ' : ' AND ') . $statefilter;
	}

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	if (get_request_var('rfilter') != '') {
		$sql_where .= ($sql_where == '' ? 'WHERE ' : ' AND ') .
			'display_name RLIKE \'' . get_request_var('rfilter') . '\' OR ' .
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
		'display_name' => array(
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

	$nav = html_nav_bar('servcheck_test.php', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, $columns, __('Checks', 'servcheck'), 'page', 'main');

	form_start('servcheck_test.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '4', 'center', '');

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

			form_selectable_cell($row['display_name'], $row['id']);
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
		form_alternate_row();
		print '<td colspan="' . (cacti_sizeof($display_text) + 1) . '"><center>' . __('No Tests Found', 'servcheck') . '</center></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($result)) {
		print $nav;
	}

	draw_actions_dropdown($servcheck_actions_test);

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

function servcheck_checknull($value) {
	if ($value == NULL) {
		return '0';
	} else {
		return $value;
	}
}

function servcheck_filter() {
	global $item_rows, $page_refresh_interval;

	$refresh['page']    = 'servcheck_test.php?header=false';
	$refresh['seconds'] = get_request_var('refresh');
	$refresh['logout']  = 'false';

	set_page_refresh($refresh);

	?>
	<script type='text/javascript'>
	function applyFilter() {
		strURL  = 'servcheck_test.php?header=false&state=' + $('#state').val();
		strURL += '&refresh=' + $('#refresh').val();
		strURL += '&rfilter=' + base64_encode($('#rfilter').val());
		strURL += '&rows=' + $('#rows').val();
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'servcheck_test.php?clear=1&header=false';
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

		$('#form_servcheck').submit(function(event) {
			event.preventDefault();
			applyFilter();
		});
	});
	</script>
	<?php

	html_start_box(__('Service Checks', 'servcheck') , '100%', '', '3', 'center', 'servcheck_test.php?action=edit');
	?>
	<tr class='even noprint'>
		<td class='noprint'>
			<form id='form_servcheck' action='servcheck_test.php'>
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

function servcheck_log_filter() {
	global $item_rows;

	?>
	<script type='text/javascript'>

	refreshMSeconds=99999999;

	function applyFilter() {
		strURL  = 'servcheck_test.php?action=history&header=false&id=<?php print get_request_var('id');?>';
		strURL += '&filter=' + $('#filter').val();
		strURL += '&rows=' + $('#rows').val();
		refreshMSeconds=99999999;
		loadPageNoHeader(strURL);
	}

	function clearFilter() {
		strURL = 'servcheck_test.php?action=history&clear=1&header=false';
		loadPageNoHeader(strURL);
	}

	function purgeEvents() {
		strURL = 'servcheck_test.php?action=purge&id=<?php print get_request_var('id');?>';
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
	<?php

	html_end_box();
}
