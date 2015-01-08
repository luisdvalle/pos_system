<?php

/** The name of the database */
define('DB_NAME', 'inin1831_incsalesdb');

/** MySQL database username */
define('DB_USER', 'inin1831_isalesu');

/** MySQL database password */
define('DB_PASSWORD', 'yudi2007');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Absolute path to Incentive Sales directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');	
	
/* Debugging mode. */
//define('WP_DEBUG', true);

/** Sets up vars and included files. */
require_once(ABSPATH . 'config/is-settings.php'); //include 'ABSPATH .' when the absolute path constant is defined
