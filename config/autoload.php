<?php
declare(strict_types=1);

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $h, string $n): bool {
        return $n === '' || strpos($h, $n) === 0;
    }
}

define('APP_ROOT', rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, __DIR__.'/..'), DIRECTORY_SEPARATOR));
define('AUTOLOAD_DEBUG', false);

$MAP = [
    'App\\controllers\\' => [APP_ROOT.'/controllers/'],
    'App\\models\\'      => [APP_ROOT.'/models/'],
    'App\\views\\'       => [APP_ROOT.'/views/'],
    'App\\entities\\'    => [APP_ROOT.'/entities/'],   // <-- ENTITÉS
    'App\\services\\'    => [APP_ROOT.'/services/'],  // <- utile si tu as des classes ici
    'App\\config\\'      => [APP_ROOT.'/config/'],
];

spl_autoload_register(function(string $class) use ($MAP): void {
    foreach ($MAP as $prefix => $dirs) {
        if (!str_starts_with($class, $prefix)) continue;
        $relative = substr($class, strlen($prefix));
        $filename = str_replace('\\', '/', $relative) . '.php';
        foreach ($dirs as $base) {
            $file = $base . $filename;
            if (is_file($file)) { require $file; return; }
        }
        $msg = "[autoload] Not found for {$class} -> ".implode(', ', array_map(fn($d)=>$d.$filename, $dirs));
        error_log($msg);
        if (AUTOLOAD_DEBUG) echo "<pre style='color:#f88'>{$msg}</pre>";
        return;
    }
});

/* ===== IMPORTANT : charger explicitement les fichiers de fonctions ===== */
$FUNCTION_FILES = [
    APP_ROOT.'/services/Utils.php',   // contient flash(), get_flashes(), csrf_*(), e()
];
foreach ($FUNCTION_FILES as $fn) {
    if (is_file($fn)) require_once $fn;
}
