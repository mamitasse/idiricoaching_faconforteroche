<?php
declare(strict_types=1);

/**
* Front Controller (point d’entrée unique)
* ----------------------------------------
* - Autoload + config
* - Lecture de ?action
* - Routage vers le bon contrôleur/méthode
*/

require_once __DIR__ . '/../config/autoload.php';

use App\controllers\HomeController;
use App\controllers\AuthController;
use App\controllers\DashboardController;
use App\controllers\CreneauController;
use App\controllers\ContactController;
use App\controllers\AdherentController;
use App\controllers\ErrorController;

// ------------------------------------------------------------
// Helpers (facultatif si présents)
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
// Table de routage : action -> [Classe, Méthode]
// ------------------------------------------------------------
$routes = [
// Pages publiques
'' => [HomeController::class, 'showHomePage'],
'home' => [HomeController::class, 'showHomePage'],
'services' => [HomeController::class, 'showServicesPage'],
'nadia' => [HomeController::class, 'showNadiaPage'],
'sabrina' => [HomeController::class, 'showSabrinaPage'],

// Contact
'contact' => [ContactController::class, 'showContactForm'],
'contactPost' => [ContactController::class, 'handleContactPost'],

// Auth
'connexion' => [AuthController::class, 'showLoginForm'],
'loginPost' => [AuthController::class, 'handleLoginPost'],
'inscription' => [AuthController::class, 'showSignupForm'],
'signupPost' => [AuthController::class, 'handleSignupPost'],
'logout' => [AuthController::class, 'logout'],

// Mot de passe oublié / reset
'forgotPassword' => [AuthController::class, 'showForgotPasswordForm'],
'forgotPasswordPost' => [AuthController::class, 'handleForgotPasswordPost'],
'resetPassword' => [AuthController::class, 'showResetForm'],
'resetPasswordPost' => [AuthController::class, 'handleResetPost'],

// Dashboards
'adherentDashboard' => [DashboardController::class, 'showAdherentDashboard'],
'coachDashboard' => [DashboardController::class, 'showCoachDashboard'],

// Coach : liste/adherent
'coachAdherents' => [DashboardController::class, 'showCoachAdherentsList'],
'coachAdherentProfile' => [AdherentController::class, 'showAdherentProfile'],

// Créneaux / Réservations
'creneauReserve' => [CreneauController::class, 'reserve'],
'reservationCancel' => [CreneauController::class, 'reservationCancel'],
'reservationCancelByCoach' => [CreneauController::class, 'reservationCancelByCoach'],
'slotBlock' => [CreneauController::class, 'block'],
'slotUnblock' => [CreneauController::class, 'unblock'],
];

// ------------------------------------------------------------
// Lecture de l'action
// ------------------------------------------------------------
$action = isset($_GET['action']) ? (string)$_GET['action'] : '';

// Si action non vide et inconnue → 404
if ($action !== '' && !isset($routes[$action])) {
(new ErrorController())->notFound("La page « {$action} » n’existe pas.");
exit;
}

// action vide → home (comportement d’origine)
[$controllerClass, $methodName] = $routes[$action] ?? $routes[''];

// ------------------------------------------------------------
// Sécurité : contrôleur & méthode doivent exister
// ------------------------------------------------------------
if (!class_exists($controllerClass)) {
http_response_code(500);
echo "<h1>Erreur serveur</h1>
<p>Contrôleur introuvable : <code>{$controllerClass}</code>.<br>
Vérifie l’autoload (<code>config/autoload.php</code>), le namespace et le chemin du fichier.</p>";
exit;
}

$controllerInstance = new $controllerClass();

if (!is_callable([$controllerInstance, $methodName])) {
http_response_code(500);
echo "<h1>Erreur serveur</h1>
<p>Méthode introuvable : <code>{$controllerClass}::{$methodName}()</code>.<br>
Mets à jour la table de routage ou implémente la méthode.</p>";
exit;
}

// ------------------------------------------------------------
// Exécution
// ------------------------------------------------------------
$controllerInstance->{$methodName}();