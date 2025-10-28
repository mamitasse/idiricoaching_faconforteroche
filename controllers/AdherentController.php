<?php
declare(strict_types=1);

namespace App\controllers;

use DateTime;
use App\views\View;
use App\models\UserManager;
use App\models\ReservationManager;
use function App\services\flash;

/**
 * AdherentController
 * ------------------
 * Affiche le profil d’un adhérent (réservé aux COACHS).
 * - Sécurise : le coach ne peut voir QUE les adhérents rattachés à lui.
 * - Récupère infos adhérent + réservations
 * - Sépare réservations en "à venir" et "passées"
 */
final class AdherentController
{
    /** Redirection relative au site */
    private function redirectTo(string $relativePath): void
    {
        header('Location: ' . BASE_URL . $relativePath);
        exit;
    }

    /** Exige un coach connecté et retourne la session user */
    private function requireCoach(): array
    {
        if (empty($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'coach') {
            flash('error', 'Accès réservé aux coachs.');
            $this->redirectTo('?action=connexion');
        }
        return $_SESSION['user'];
    }

    /** GET ?action=adherentProfile&id=123 */
    public function showAdherentProfile(): void
    {
        $coachSession = $this->requireCoach();

        // 1) Paramètre d’URL "id"
        $adherentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($adherentId <= 0) {
            flash('error', 'Adhérent invalide.');
            $this->redirectTo('?action=coachDashboard');
        }

        // 2) Récupération de l’adhérent (entité)
        $userManager = new UserManager();
        $adherentEntity = $userManager->findEntityById($adherentId);
        if (!$adherentEntity || $adherentEntity->getRole() !== 'adherent') {
            flash('error', 'Adhérent introuvable.');
            $this->redirectTo('?action=coachDashboard');
        }

        // 3) Sécurité : vérifier que cet adhérent est bien rattaché au coach courant
        $adherentCoachId = (int)($adherentEntity->getCoachId() ?? 0);
        if ($adherentCoachId !== (int)$coachSession['id']) {
            flash('error', 'Cet adhérent n’est pas rattaché à vous.');
            $this->redirectTo('?action=coachDashboard');
        }

        // 4) Réservations — on réutilise la méthode existante et on découpe passé/à venir
        $reservationManager = new ReservationManager();
        $reservations = $reservationManager->forAdherent($adherentId); // tableaux enrichis : coach + créneaux

        $now = new DateTime();
        $upcomingReservations = [];
        $pastReservations     = [];

        foreach ($reservations as $reservationRow) {
            // Convertit date + start_time en DateTime
            $date = (string)($reservationRow['date'] ?? '');
            $startTime = (string)($reservationRow['start_time'] ?? '00:00:00');
            $startDateTime = DateTime::createFromFormat('Y-m-d H:i:s', $date . ' ' . $startTime) ?: new DateTime('1970-01-01 00:00:00');

            if ($startDateTime >= $now) {
                $upcomingReservations[] = $reservationRow;
            } else {
                $pastReservations[] = $reservationRow;
            }
        }

        // 5) Rendu
        View::render('templates/adherent/profile', [
            'title'                => 'Profil adhérent',
            'coachName'            => trim(($coachSession['first_name'] ?? '') . ' ' . ($coachSession['last_name'] ?? '')),
            'adherent'             => $adherentEntity,
            'upcomingReservations' => $upcomingReservations,
            'pastReservations'     => $pastReservations,
        ]);
    }
}
