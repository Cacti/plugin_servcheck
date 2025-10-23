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

top_header();

servcheck_show_tab('servcheck_curl_code.php');

$findcode = get_filter_request_var('findcode');

html_start_box('', '100%', '', '3', 'center', '');

if (cacti_sizeof($curl_error)) {
	foreach ($curl_error as $id=>$code) {
		form_alternate_row('line' . $id, true);

		if ($id == $findcode) {
			print '<td class="bold">' . $id . '</td>';
			print '<td class="bold">' . $code['title'] . '</td>';
			print '<td class="bold">' . $code['description'] . '</td>';
		} else {
			print '<td>' . $id . '</td>';
			print '<td>' . $code['title'] . '</td>';
			print '<td>' . $code['description'] . '</td>';
		}

		form_end_row();
	}
} else {
	print '<tr><td colspan="' . $columns . '"><em>' . __('No error codes', 'servcheck') . '</em></td></tr>';
}

html_end_box(false);

bottom_footer();

