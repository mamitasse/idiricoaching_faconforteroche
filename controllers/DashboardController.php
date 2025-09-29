<?php

declare(strict_types=1);

namespace App\controllers;

use DateTime;
use App\views\View;
use App\models\UserManager;
use App\models\SlotManager;
use App\models\ReservationManager;
use App\models\SlotEntity; // pour l'annotation de type
use function App\services\flash;

/**
 * Tableau de bord adhérent / coach (version mentor)
 * - camelCase strict
 * - noms de variables explicites
 * - commentaires pédagogiques
 */
final class DashboardController
{
    /* =========================================================
     * Helpers internes
     * =======================================================*/

    /** Redirection relative au site (préfixée par BASE_URL). */
    private function redirectTo(string $relativePath): void
    {
        header('Location: ' . BASE_URL . $relativePath);
        exit;
    }

    /**
     * Exige qu’un utilisateur soit connecté, et éventuellement avec un rôle précis.
     * Retourne le tableau de session de l’utilisateur.
     */
    private function requireLogin(?string $requiredRole = null): array
    {
        if (empty($_SESSION['user'])) {
            flash('error', 'Veuillez vous connecter.');
            $this->redirectTo('?action=connexion');
        }
        $sessionUser = $_SESSION['user'];

        if ($requiredRole !== null && ($sessionUser['role'] ?? '') !== $requiredRole) {
            flash('error', 'Accès non autorisé pour ce rôle.');
            $this->redirectTo('?action=connexion');
        }
        return $sessionUser;
    }

    /**
     * Récupère une date YYYY-MM-DD depuis $_GET['date'], sinon retourne la date du jour.
     */
    private function getSelectedDateFromGet(): string
    {
        $candidate = $_GET['date'] ?? (new DateTime('today'))->format('Y-m-d');
        if (is_string($candidate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $candidate)) {
            return $candidate;
        }
        return (new DateTime('today'))->format('Y-m-d');
    }

    /**
     * Construit le nom complet d’un coach à partir de son id.
     * Essaie d’abord via entité, puis fallback via ligne (array) si besoin.
     */
    private function buildCoachFullName(UserManager $userManager, int $coachId): string
    {
        $coachEntity = $userManager->findEntityById($coachId);
        if ($coachEntity) {
            // Si l’entité a un getFullName(), on l’utilise. Sinon on compose.
            if (method_exists($coachEntity, 'getFullName')) {
                return $coachEntity->getFullName();
            }
            return trim($coachEntity->getFirstName() . ' ' . $coachEntity->getLastName());
        }
        return '—';
    }


    /* =========================================================
     * Tableaux de bord (GET)
     * =======================================================*/

    /**
     * GET ?action=adherentDashboard
     * Page de tableau de bord pour un adhérent.
     * Vue: views/dashboard/adherent.php
     */
    public function showAdherentDashboard(): void
    {
        $sessionUser = $this->requireLogin('adherent');

        $coachId = (int)($sessionUser['coach_id'] ?? 0);
        if ($coachId <= 0) {
            flash('error', 'Aucun coach associé à votre compte.');
            $this->redirectTo('?action=home');
        }

        $selectedDate = $this->getSelectedDateFromGet();

        // 1) Génère les créneaux du jour (08→20) si manquants
        $slotManager = new SlotManager();
        $slotManager->ensureDailyGrid($coachId, $selectedDate, 8, 20);

        // 2) Récupère les créneaux du jour (en ENTITÉS)
        /** @var SlotEntity[] $daySlotsAsEntities */
        $daySlotsAsEntities = $slotManager->listEntitiesForCoachDate($coachId, $selectedDate);

        // 3) Filtre d’affichage : uniquement disponibles, et si aujourd’hui -> à venir
        $visibleSlots = array_values(array_filter(
            $daySlotsAsEntities,
            function (SlotEntity $slot) use ($selectedDate) {
                if (!$slot->isAvailable()) {
                    return false;
                }
                $today = (new DateTime('today'))->format('Y-m-d');
                if ($selectedDate !== $today) {
                    return true;
                }
                return $slot->getStartDateTime() >= new DateTime();
            }
        ));

        // 4) Mes réservations (tableaux enrichis pour la vue)
        $reservationManager = new ReservationManager();
        $myReservations = $reservationManager->forAdherent((int)$sessionUser['id']);

        // 5) Nom du coach
        $userManager = new UserManager();
        $coachFullName = $this->buildCoachFullName($userManager, $coachId);

        // 6) Rendu
        View::render('templates/dashboard/adherent', [
            'title'        => 'Mon tableau de bord — Adhérent',
            'userName'     => trim(($sessionUser['first_name'] ?? '') . ' ' . ($sessionUser['last_name'] ?? '')),
            'coachName'    => $coachFullName,
            'todayDate'    => (new DateTime('today'))->format('d/m/Y'),
            'selectedDate' => $selectedDate,
            'slots'        => $visibleSlots,   // ENTITÉS
            'reservations' => $myReservations, // TABLEAUX (jointure pour affichage simple)
        ]);
    }

    /**
     * GET ?action=coachDashboard
     * Page de tableau de bord pour un coach.
     * Vue: views/dashboard/coach.php
     */
    public function showCoachDashboard(): void
    {
        $sessionUser = $this->requireLogin('coach');

        $coachId = (int)$sessionUser['id'];
        $selectedDate = $this->getSelectedDateFromGet();

        // 1) Grille du jour (08→20) si manquante
        $slotManager = new SlotManager();
        $slotManager->ensureDailyGrid($coachId, $selectedDate, 8, 20);

        // 2) Créneaux du jour en ENTITÉS
        /** @var SlotEntity[] $daySlotsAsEntities */
        $daySlotsAsEntities = $slotManager->listEntitiesForCoachDate($coachId, $selectedDate);

        // 3) Réservations du jour (tableaux enrichis)
        $reservationManager = new ReservationManager();
        if (method_exists($reservationManager, 'forCoachAtDate')) {
            $reservationsForDay = $reservationManager->forCoachAtDate($coachId, $selectedDate);
        } else {
            // Fallback : filtre local si seule forCoach() existe
            $allReservations = $reservationManager->forCoach($coachId);
            $reservationsForDay = array_values(array_filter(
                $allReservations,
                fn(array $reservationRow) => ($reservationRow['date'] ?? null) === $selectedDate
            ));
        }

        // 4) Liste des adhérents rattachés (ENTITÉS uniquement)
        $userManager = new UserManager();
        $attachedAdherents = $userManager->adherentsOfCoachEntities($coachId);


        // 5) Rendu
        View::render('templates/dashboard/coach', [
            'title'        => 'Mon tableau de bord — Coach',
            'coachName'    => trim(($sessionUser['first_name'] ?? '') . ' ' . ($sessionUser['last_name'] ?? '')),
            'todayDate'    => (new DateTime('today'))->format('d/m/Y'),
            'selectedDate' => $selectedDate,
            'slots'        => $daySlotsAsEntities, // ENTITÉS
            'reservations' => $reservationsForDay, // TABLEAUX
            'adherents'    => $attachedAdherents,
        ]);
    }

    /* =========================================================
     * ALIAS (compatibilité avec anciens noms d’actions)
     * =======================================================*/

    // Ancien nom souvent vu : 'adherent'
    public function adherent(): void
    {
        $this->showAdherentDashboard();
    }

    // Ancien nom souvent vu : 'coach'
    public function coach(): void
    {
        $this->showCoachDashboard();
    }

    // Alias encore plus directs si ton routeur attend exactement ces noms :
    public function adherentDashboard(): void
    {
        $this->showAdherentDashboard();
    }

    public function coachDashboard(): void
    {
        $this->showCoachDashboard();
    }
}
