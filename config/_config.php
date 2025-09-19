<?php
declare(strict_types=1);

// ---- BASE_URL ----
$https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') === '443';
$scheme = $https ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');

// ex: /idiricoaching_faconforteroche/public
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/public/index.php'));
$scriptDir = rtrim($scriptDir, '/');

// Toujours terminer par un /
define('BASE_URL', $scheme.'://'.$host.$scriptDir.'/');

// ---- DEV / PROD ----
define('IN_DEV', true);
if (IN_DEV) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// ---- DB ----
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'coaching_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_DSN', 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4');

// ---- Session ----
session_name('idiricoaching_sess');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
