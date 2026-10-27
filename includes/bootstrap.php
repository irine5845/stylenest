<?php
/** Loaded first by every page: errors, session, helpers. */
require_once __DIR__ . '/functions.php';

date_default_timezone_set(config('timezone') ?: 'Africa/Nairobi');
mb_internal_encoding('UTF-8');

if (config('debug')) { ini_set('display_errors', '1'); error_reporting(E_ALL); }
else { ini_set('display_errors', '0'); error_reporting(E_ALL & ~E_DEPRECATED); ini_set('log_errors', '1'); }

// Secure session cookies
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name('stylenest_sid');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_start();

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
if ($https) header('Strict-Transport-Security: max-age=31536000');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/cart_lib.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/orders_lib.php';
require_once __DIR__ . '/mpesa_lib.php';
require_once __DIR__ . '/catalog.php';

if (mt_rand(1, 20) === 1) { try { expire_stale_orders(); } catch (Throwable $e) { error_log('expire_stale_orders: ' . $e->getMessage()); } }
