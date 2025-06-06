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

define('SERVCHECK_ACTION_PROXY_DELETE', 1);

define('SERVCHECK_ACTION_CA_DELETE', 1);

define('SERVCHECK_ACTION_TEST_DELETE',    1);
define('SERVCHECK_ACTION_TEST_DISABLE',   2);
define('SERVCHECK_ACTION_TEST_ENABLE',    3);
define('SERVCHECK_ACTION_TEST_DUPLICATE', 4);

define('SERVCHECK_ACTION_RESTAPI_DELETE',    1);
define('SERVCHECK_ACTION_RESTAPI_DUPLICATE', 2);


define('SERVCHECK_FORMAT_HTML', 0);
define('SERVCHECK_FORMAT_PLAIN', 1);
