<?php
declare(strict_types=1);

/**
 * autoload.php (façon mentor, SAFE)
 * ---------------------------------
 * Rôle :
 *  - Charger la configuration unique (_config.php) : constantes, BASE_URL, DB_*, session, etc.
 *  - Enregistrer un autoloader PSR-4 minimal pour le namespace App\
 *  - Essayer de charger Composer si présent, sinon brancher PHPMailer manuellement
 *
 * Principes :
 *  - On NE redéfinit pas de constantes ici (elles viennent de config/_config.php)
 *  - On NE (re)démarre pas la session ici (la config s’en charge déjà)
 *  - On journalise les classes introuvables pour aider le debug en DEV
 */

/* ----------------------------------------------------------------
 * Compatibilité PHP < 8 : polyfill de str_starts_with
 * ---------------------------------------------------------------- */
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}

/* ----------------------------------------------------------------
 * 1) Charger la configuration (source UNIQUE de vérité)
 *    -> définit notamment BASE_URL, DB_*, IN_DEV, … et la session
 * ---------------------------------------------------------------- */
require_once __DIR__ . '/_config.php';

/* ----------------------------------------------------------------
 * 2) Préparer les dossiers du projet et la table Namespace -> Chemins
 * ---------------------------------------------------------------- */
$projectRoot = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
$projectRoot = str_replace('\\', '/', $projectRoot); // normaliser pour Windows

$namespaceMap = [
    // Exemple de résolution :
    //   App\controllers\HomeController -> {projet}/controllers/HomeController.php
    'App\\controllers\\' => [$projectRoot . '/controllers/'],
    'App\\models\\'      => [$projectRoot . '/models/'],
    'App\\views\\'       => [$projectRoot . '/views/'],
    'App\\services\\'    => [$projectRoot . '/services/'],
    'App\\config\\'      => [$projectRoot . '/config/'],
];

/* ----------------------------------------------------------------
 * 3) Enregistrer l’autoloader PSR-4 minimaliste
 * ---------------------------------------------------------------- */
spl_autoload_register(function (string $class) use ($namespaceMap): void {
    foreach ($namespaceMap as $prefix => $dirs) {
        if (!str_starts_with($class, $prefix)) {
            continue; // pas notre namespace, on essaie le suivant
        }

        // Exemple: App\controllers\HomeController -> HomeController.php
        $relativeClass = substr($class, strlen($prefix));
        $filePart      = str_replace('\\', '/', $relativeClass) . '.php';

        foreach ($dirs as $baseDir) {
            $path = $baseDir . $filePart;
            if (is_file($path)) {
                require_once $path;
                return; // classe résolue
            }
        }

        // Classe introuvable -> utile en DEV pour diagnostiquer
        error_log('[autoload] Classe introuvable : ' . $class . ' — chemins testés : ' .
            implode(', ', array_map(static fn($d) => $d . $filePart, $dirs)));
        return; // on sort quand même (on ne la trouvera pas ici)
    }
});

/* ----------------------------------------------------------------
 * 4) Librairies externes
 *    - On tente d’abord Composer (vendor/autoload.php)
 *    - Sinon on branche PHPMailer manuellement depuis /libs/phpmailer
 *      (aucune dépendance externe, pratique sous XAMPP)
 * ---------------------------------------------------------------- */
$composerAutoload = $projectRoot . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    // Cas où tu as installé des libs via Composer
    require_once $composerAutoload;
} else {
    // Chargement manuel de PHPMailer (sans Composer)
    $phpMailerDir = $projectRoot . '/libs/phpmailer';

    // On vérifie la présence des 3 fichiers requis
    $pmFiles = [
        $phpMailerDir . '/PHPMailer.php',
        $phpMailerDir . '/SMTP.php',
        $phpMailerDir . '/Exception.php',
    ];

    $allPmFilesExist = array_reduce($pmFiles, static function (bool $carry, string $f): bool {
        return $carry && is_file($f);
    }, true);

    if ($allPmFilesExist) {
        // Charge dans l’ordre recommandé par PHPMailer
        require_once $phpMailerDir . '/Exception.php';
        require_once $phpMailerDir . '/PHPMailer.php';
        require_once $phpMailerDir . '/SMTP.php';
    } else {
        // Alerte non bloquante en DEV si les fichiers ne sont pas là
        if (defined('IN_DEV') && IN_DEV) {
            error_log('[autoload] PHPMailer non chargé : place les fichiers ici : ' . $phpMailerDir);
        }
        // En PROD on reste silencieux : la page de contact gérera l’erreur proprement
    }
}
