<?php
declare(strict_types=1);

/** base URL (ex: http://localhost/idiricoaching_faconforteroche/public/) */
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base   = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/public/index.php'), '/\\');
define('BASE_URL', $scheme.'://'.$host.$base.'/');

define('IN_DEV', true); // passe à false en prod

// ---- DB ----
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'coaching_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_DSN', 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4');

session_name('idiricoaching_sess');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

