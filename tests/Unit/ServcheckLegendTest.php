<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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

	expect($output)->toContain('<div class="servcheck_legend" style="--servcheck-chip-min: calc(');
	expect(substr_count($output, 'servcheck_legend_item'))->toBe(count($states));

	foreach ($states as $index => $state) {
		expect($output)->toContain('<div class="servcheck_legend_item servcheck_' . $index . '">' . $state . '</div>');
	}
});
