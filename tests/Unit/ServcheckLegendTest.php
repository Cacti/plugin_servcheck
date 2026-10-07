<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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
 | about.php and/or the AUTHORS file for specific developer information.    |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/*
 * Rendering of the status legend, servcheck_legend().
 *
 * The renderer walks $servcheck_states (from includes/arrays.php) and prints
 * one .servcheck_legend_item chip per state. html_start_box()/html_end_box()
 * are no-op stubs from the bootstrap, so only the chip markup reaches the
 * output buffer. The container carries a --servcheck-chip-min variable, sized
 * to the longest label, so every chip shares one min-width and stays equal
 * width as the legend wraps.
 */

beforeAll(function () {
	// Publishes $servcheck_states and defines servcheck_legend().
	servcheck_test_load(__DIR__ . '/../../includes/arrays.php');
	servcheck_test_load(__DIR__ . '/../../includes/functions.php');
});

it('renders one chip per state with a longest-label chip-min variable', function () {
	ob_start();
	servcheck_legend();
	$output = ob_get_clean();

	$states = $GLOBALS['servcheck_states'];

	$expected = 0;
	foreach ($states as $state) {
		$expected = max($expected, mb_strlen($state));
	}

	expect($output)->toContain('<div class="servcheck_legend" style="--servcheck-chip-min: calc(' . $expected . 'ch + 1.5rem)">');
	expect(substr_count($output, 'servcheck_legend_item'))->toBe(count($states));

	foreach ($states as $index => $state) {
		expect($output)->toContain('<div class="servcheck_legend_item servcheck_' . $index . '">' . $state . '</div>');
	}
});
