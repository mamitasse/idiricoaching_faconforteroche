<?php
declare(strict_types=1);

/**
 * Front Controller
 * ----------------
 * - Point d’entrée unique de l’application
 * - Charge la config + autoload
 * - Lit "action" dans l’URL et route vers la bonne méthode contrôleur
 */

require_once __DIR__ . '/../config/autoload.php';

use App\controllers\HomeController;
use App\controllers\AuthController;
use App\controllers\DashboardController;
use App\controllers\CreneauController;
use App\controllers\ContactController; // <— CamelCase correct

// ---- Charge les helpers (fonctions) si présents ----
$helpersCandidates = [
    __DIR__ . '/../services/utils.php',
];
foreach ($helpersCandidates as $file) {
    if (is_file($file)) {
        require_once $file;
        break;
    }
}

// Table de routage: action -> [Classe, méthode]
$routes = [
    // Pages publiques
    ''         => [HomeController::class, 'showHomePage'],
    'home'     => [HomeController::class, 'showHomePage'],
    'services' => [HomeController::class, 'showServicesPage'],
    'nadia'    => [HomeController::class, 'showNadiaPage'],
    'sabrina'  => [HomeController::class, 'showSabrinaPage'],

    // Contact (form + envoi)
    'contact'     => [ContactController::class, 'showContactForm'],
    'contactPost' => [ContactController::class, 'handleContactPost'],

    // Authentification
    'connexion'   => [AuthController::class, 'showLoginForm'],
    'loginPost'   => [AuthController::class, 'handleLoginPost'],
    'inscription' => [AuthController::class, 'showSignupForm'],
    'signupPost'  => [AuthController::class, 'handleSignupPost'],
    'logout'      => [AuthController::class, 'logout'],

    // Dashboards
    'adherentDashboard' => [DashboardController::class, 'showAdherentDashboard'],
    'coachDashboard'    => [DashboardController::class, 'showCoachDashboard'],

    // Créneaux / Réservations
    'creneauReserve'           => [CreneauController::class, 'reserve'],
    'reservationCancel'        => [CreneauController::class, 'reservationCancel'],
    'reservationCancelByCoach' => [CreneauController::class, 'reservationCancelByCoach'],
    'slotBlock'                => [CreneauController::class, 'block'],
    'slotUnblock'              => [CreneauController::class, 'unblock'],
];

// Récupère l'action demandée (ex: ?action=connexion)
$action = isset($_GET['action']) ? (string)$_GET['action'] : '';
if (!isset($routes[$action])) {
    $action = ''; // route par défaut (home)
}

[$controllerClass, $methodName] = $routes[$action];

// Sécurité: message clair si l’autoload ne trouve pas la classe
if (!class_exists($controllerClass)) {
    http_response_code(500);
    echo "<h1>Erreur serveur</h1>
          <p>Contrôleur introuvable : {$controllerClass}.<br>
          Vérifie l’autoload (config/autoload.php), le namespace et le chemin du fichier.</p>";
    exit;
}

// Instancie le contrôleur
$controllerInstance = new $controllerClass();

// Sécurité: message clair si la méthode n’existe pas
if (!is_callable([$controllerInstance, $methodName])) {
    http_response_code(500);
    echo "<h1>Erreur serveur</h1>
          <p>Méthode introuvable : {$controllerClass}::{$methodName}()</p>";
    exit;
}

// Exécute l’action
$controllerInstance->{$methodName}();
