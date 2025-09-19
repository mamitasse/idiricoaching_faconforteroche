<?php
declare(strict_types=1);

require __DIR__ . '/../config/autoload.php';
require __DIR__ . '/../config/_config.php';

use App\controllers\{HomeController, AuthController, DashboardController, CreneauController};

$action = $_GET['action'] ?? 'home';

try {
    switch ($action) {
        // Pages publiques
        case 'home':           (new HomeController())->index(); break;
    case 'connexion':    (new \App\controllers\AuthController())->loginForm();  break;
case 'loginPost':    (new \App\controllers\AuthController())->loginPost();  break;

case 'inscription':  (new \App\controllers\AuthController())->signupForm(); break;
case 'signupPost':   (new \App\controllers\AuthController())->signupPost(); break;

case 'logout':       (new \App\controllers\AuthController())->logout();     break;


        // Dashboards
        case 'adherentDashboard': (new DashboardController())->adherent(); break;
        case 'coachDashboard':    (new DashboardController())->coach(); break;

        // Créneaux / Réservations
        case 'creneauReserve':            (new CreneauController())->reserve(); break;
        case 'reservationCancel':         (new CreneauController())->reservationCancel(); break;
        case 'reservationCancelByCoach':  (new CreneauController())->reservationCancelByCoach(); break;
        case 'creneauBlock':              (new CreneauController())->block(); break;
        case 'creneauUnblock':            (new CreneauController())->unblock(); break;             // POST (coach)

        default:
            http_response_code(404);
            echo "404";
    }
} catch (Throwable $e) {
    if (IN_DEV) { throw $e; }
    http_response_code(500);
    echo "Erreur interne.";
}


