<?php
declare(strict_types=1);

/**
 * Front Controller (point d’entrée unique)
 * ----------------------------------------
 * - Charge l’autoload + la config
 * - Lit le paramètre ?action dans l’URL
 * - Route vers la bonne méthode de contrôleur
 * - Convention : CamelCase partout (classes, méthodes publiques)
 */

require_once __DIR__ . '/../config/autoload.php';

use App\controllers\HomeController;
use App\controllers\AuthController;
use App\controllers\DashboardController;
use App\controllers\CreneauController;
use App\controllers\ContactController;
use App\controllers\AdherentController;


// ------------------------------------------------------------
// Helpers (fonctions utilitaires) si disponibles
// ------------------------------------------------------------
$helpersCandidates = [
    __DIR__ . '/../services/utils.php',
];
foreach ($helpersCandidates as $helpersFile) {
    if (is_file($helpersFile)) {
        require_once $helpersFile;
        break;
    }
}

// ------------------------------------------------------------
// Table de routage : action -> [Classe, méthode]
// ------------------------------------------------------------
$routes = [
    // Pages publiques
    ''         => [HomeController::class, 'showHomePage'],
    'home'     => [HomeController::class, 'showHomePage'],
    'services' => [HomeController::class, 'showServicesPage'],
    'nadia'    => [HomeController::class, 'showNadiaPage'],
    'sabrina'  => [HomeController::class, 'showSabrinaPage'],

    // Contact (formulaire + envoi)
    'contact'     => [ContactController::class, 'showContactForm'],
    'contactPost' => [ContactController::class, 'handleContactPost'],

    // Authentification
    'connexion'   => [AuthController::class, 'showLoginForm'],
    'loginPost'   => [AuthController::class, 'handleLoginPost'],
    'inscription' => [AuthController::class, 'showSignupForm'],
    'signupPost'  => [AuthController::class, 'handleSignupPost'],
    'logout'      => [AuthController::class, 'logout'],

    /// Dashboards
'adherentDashboard' => [DashboardController::class, 'showAdherentDashboard'],
'coachDashboard'    => [DashboardController::class, 'showCoachDashboard'],

// --- Coach : nouvelles pages ---
'coachAdherents'       => [DashboardController::class, 'showCoachAdherentsList'],
'coachAdherentProfile' => [AdherentController::class, 'showAdherentProfile'],

// Créneaux / Réservations
'creneauReserve'           => [CreneauController::class, 'reserve'],
'reservationCancel'        => [CreneauController::class, 'reservationCancel'],
'reservationCancelByCoach' => [CreneauController::class, 'reservationCancelByCoach'],
'slotBlock'                => [CreneauController::class, 'block'],
'slotUnblock'              => [CreneauController::class, 'unblock'],

];

// ------------------------------------------------------------
// Lecture de l’action demandée (ex: ?action=connexion)
// ------------------------------------------------------------
$requestedAction = isset($_GET['action']) ? (string)$_GET['action'] : '';

// Si l’action n’existe pas, on retombe sur la home
if (!array_key_exists($requestedAction, $routes)) {
    $requestedAction = '';
}

// Résolution de la paire [Classe, Méthode]
[$controllerClassName, $controllerMethodName] = $routes[$requestedAction];

// ------------------------------------------------------------
// Sécurité : contrôleur et méthode doivent exister
// ------------------------------------------------------------
if (!class_exists($controllerClassName)) {
    http_response_code(500);
    echo "<h1>Erreur serveur</h1>
          <p>Contrôleur introuvable : <code>{$controllerClassName}</code>.<br>
          Vérifie l’autoload (<code>config/autoload.php</code>), le namespace et le chemin du fichier.</p>";
    exit;
}

$controllerInstance = new $controllerClassName();

if (!is_callable([$controllerInstance, $controllerMethodName])) {
    http_response_code(500);
    echo "<h1>Erreur serveur</h1>
          <p>Méthode introuvable : <code>{$controllerClassName}::{$controllerMethodName}()</code>.<br>
          Mets à jour la table de routage ou crée la méthode demandée.</p>";
    exit;
}

// ------------------------------------------------------------
// Exécution de l’action
// ------------------------------------------------------------
$controllerInstance->{$controllerMethodName}();
