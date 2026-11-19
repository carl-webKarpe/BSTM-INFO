<?php
/**
 * ---------------------------------------------------------------
 *  APPLICATION CONFIGURATION
 * ---------------------------------------------------------------
 *  LOCAL (XAMPP + MySQL Workbench) – the defaults below usually work.
 *
 *  INFINITYFREE – change the DB_* values to the ones shown in
 *  Control Panel → MySQL Databases, e.g.
 *      DB_HOST = 'sql123.infinityfree.com'
 *      DB_NAME = 'if0_12345678_conference'
 *      DB_USER = 'if0_12345678'
 *      DB_PASS = 'your vPanel password'
 *  and set APP_DEBUG to false.
 * ---------------------------------------------------------------
 */

define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'bstm_conference');
define('DB_USER', 'root');
define('DB_PASS', '');

// Show detailed PHP errors. Set to false on the live server.
define('APP_DEBUG', true);

// Time zone used for all dates (registration time, check-in time...).
define('APP_TIMEZONE', 'Asia/Manila');

// Optional: full public address of the app WITHOUT trailing slash,
// e.g. 'https://myconference.infinityfreeapp.com'. Leave empty to
// auto-detect. (This can also be set in Admin → System Settings.)
define('APP_URL', '');
