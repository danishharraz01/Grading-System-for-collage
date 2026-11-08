<?php
/**
 * ALIAS Kolej Grading System - configuration.
 * Default values match a fresh XAMPP install (user "root", empty password).
 */

define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'alias_grading');
define('DB_USER', 'root');
define('DB_PASS', '');

define('APP_NAME', 'ALIAS Kolej Grading System');
define('COLLEGE_NAME', 'ALIAS Kolej');

// Log the user out after this many seconds of inactivity.
define('SESSION_TIMEOUT', 1800);
