<?php
declare(strict_types=1);

/**
 * _config.php
 * ------------
 * UNIQUE source de vérité pour :
 * - BASE_URL (liens vers /public/assets..., etc.)
 * - variables d'environnement (IN_DEV)
 * - configuration BDD (DB_* et DSN)
 * - démarrage de la session
 *
 * ⚠️ AUCUNE autre config ne doit redéfinir ces constantes.
 */

// --- Environnement (dev/prod) ---
if (!defined('IN_DEV')) {
    define('IN_DEV', true); // passe à false en prod
}

// --- BASE_URL calculée dynamiquement à partir du script en cours (/public/index.php) ---
if (!defined('BASE_URL')) {
    $scheme     = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host       = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/public/index.php';
    $baseDir    = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    define('BASE_URL', $scheme . '://' . $host . $baseDir . '/');
    // Exemple: http://localhost/idiricoaching_faconforteroche/public/
}

// --- Config base de données ---
if (!defined('DB_HOST')) define('DB_HOST', '127.0.0.1');
if (!defined('DB_PORT')) define('DB_PORT', '3306');
if (!defined('DB_NAME')) define('DB_NAME', 'coaching_db'); // adapte si besoin
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_DSN'))  define('DB_DSN', 'mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset=utf8mb4');

// --- Session (une seule fois) ---
if (session_status() !== PHP_SESSION_ACTIVE) {
    // Nommer la session AVANT session_start()
    session_name('idiricoaching_sess');
    session_start();
}
