<?php
/**
 * Loaded at the top of every page.
 * Sets up configuration, errors, session, database and helpers.
 */

define('APP_ROOT', dirname(__DIR__));

require APP_ROOT . '/config.php';

// Defaults for any setting missing from config.php
foreach ([
    'DB_HOST' => 'localhost', 'DB_PORT' => 3306, 'DB_NAME' => 'studentregister', 'DB_USER' => 'root', 'DB_PASS' => '',
    'APP_DEBUG' => false, 'APP_TIMEZONE' => 'Asia/Manila', 'APP_URL' => '', 'APP_NAME' => 'Student Registration',
] as $const => $default) {
    if (!defined($const)) {
        define($const, $default);
    }
}

date_default_timezone_set(APP_TIMEZONE);

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// ---------------------------------------------------------------
// Session (secure cookie settings)
// ---------------------------------------------------------------
if (session_status() !== PHP_SESSION_ACTIVE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('CONFREGSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Basic security headers
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

require __DIR__ . '/functions.php';
require __DIR__ . '/auth.php';
