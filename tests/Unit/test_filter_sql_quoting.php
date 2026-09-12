<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

$payload = "%' OR 1=1 --";
$quoted  = "'" . str_replace("'", "\\'", '%' . $payload . '%') . "'";

// Strip the outer delimiters and any escaped quotes; a safe implementation
// must leave no bare apostrophe that could break out of the SQL string.
$inner                        = substr($quoted, 1, -1);
$inner_without_escaped_quotes = str_replace("\\'", '', $inner);

if (strpos($quoted, "\\'") !== false
	&& strpos($inner_without_escaped_quotes, "'") === false
	&& strpos($quoted, 'OR 1=1') !== false
) {
	print "OK\n";
	exit(0);
}

fwrite(STDERR, "Expected filter text to remain quoted in SQL fragments\n");
exit(1);
