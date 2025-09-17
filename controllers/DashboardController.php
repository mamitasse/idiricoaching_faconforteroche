<?php
declare(strict_types=1);

namespace App\controllers;

use App\views\View;
use App\models\{SlotManager, ReservationManager, UserManager, SlotEntity};
use DateTime;
use function App\services\flash;

final class DashboardController
{
    /* ------------ Helpers ------------ */

    private function redirect(string $url): void {
        header('Location: '.$url); exit;
    }

    /** Exige la session et un rôle éventuel */
    private function needLogin(?string $role = null): array {
        if (empty($_SESSION['user'])) {
            flash('error','Veuillez vous connecter.');
            $this->redirect(BASE_URL.'?action=connexion');
        }
        $u = $_SESSION['user'];
        if ($role !== null && ($u['role'] ?? '') !== $role) {
            flash('error','Accès non autorisé.');
            $this->redirect(BASE_URL.'?action=connexion');
        }
        return $u;
    }

    /** Date YYYY-MM-DD sûre (aujourd’hui par défaut) */
    private function pickDateFromGet(): string {
        $d = $_GET['date'] ?? (new DateTime('today'))->format('Y-m-d');
        return (is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d))
            ? $d
            : (new DateTime('today'))->format('Y-m-d');
    }

    private function coachFullName(UserManager $um, int $coachId): string {
        // priorité à l’entité
        if (method_exists($um, 'findEntityById')) {
            $e = $um->findEntityById($coachId);
            if ($e && method_exists($e,'getFullName')) return $e->getFullName();
            if ($e && method_exists($e,'getFirstName') && method_exists($e,'getLastName')) {
                return trim($e->getFirstName().' '.$e->getLastName());
            }
        }
        // fallback tableau
        if (method_exists($um, 'getById')) {
            $row = $um->getById($coachId);
            if ($row) return trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? ''));
        }
        return '—';
    }

    /* ------------ ADHERENT ------------ */

    public function adherent(): void
    {
        $u = $this->needLogin('adherent');

        $coachId = (int)($u['coach_id'] ?? 0);
        if ($coachId <= 0) {
            flash('error','Aucun coach associé à votre compte.');
            $this->redirect(BASE_URL.'?action=home');
        }

        $selectedDate = $this->pickDateFromGet();

        // Génère la grille du jour 08→20 (index unique sur (coach_id,date,start_time) conseillé)
        $sm = new SlotManager();
        $sm->ensureDailyGrid($coachId, $selectedDate, 8, 20);

        // Récupère les slots du jour en **entités**
        /** @var SlotEntity[] $slots */
        $slots = $sm->listEntitiesForCoachDate($coachId, $selectedDate);

        // Filtre: visibles = disponibles (et si aujourd’hui, à venir)
        $visibleSlots = array_values(array_filter($slots, function (SlotEntity $s) use ($selectedDate) {
            if (!$s->isAvailable()) return false;
            if ($selectedDate !== (new DateTime('today'))->format('Y-m-d')) return true;
            return $s->getStartDateTime() >= new DateTime(); // pas de créneaux passés aujourd’hui
        }));

        // Mes réservations (tableaux enrichis: coach + heures)
        $rm = new ReservationManager();
        $myRes = $rm->forAdherent((int)$u['id']);

        // Nom du coach
        $um = new UserManager();
        $coachName = $this->coachFullName($um, $coachId);

        View::render('dashboard/adherent', [
            'title'        => 'Mon tableau de bord — Adhérent',
            'userName'     => trim(($u['first_name'] ?? '').' '.($u['last_name'] ?? '')),
            'coachName'    => $coachName,
            'todayDate'    => (new DateTime('today'))->format('d/m/Y'),
            'selectedDate' => $selectedDate,
            'slots'        => $visibleSlots,   // <- ENTITÉS
            'reservations' => $myRes,          // <- TABLEAUX (join pour affichage)
        ]);
    }

    /* ------------ COACH ------------ */

    public function coach(): void
    {
        $u = $this->needLogin('coach');

        $coachId = (int)$u['id'];
        $selectedDate = $this->pickDateFromGet();

        $sm = new SlotManager();
        $sm->ensureDailyGrid($coachId, $selectedDate, 8, 20);

        /** @var SlotEntity[] $slots */
        $slots = $sm->listEntitiesForCoachDate($coachId, $selectedDate);

        // Réservations du jour (tableaux enrichis: adhérent + heures)
        $rm = new ReservationManager();
        $reservations = $rm->forCoachAtDate($coachId, $selectedDate);

        // Adhérents rattachés (utilisé si tu ajoutes un select d’adhérents)
        $um = new UserManager();
        $adherents = method_exists($um,'adherentsOfCoach') ? $um->adherentsOfCoach($coachId) : [];

        View::render('dashboard/coach', [
            'title'        => 'Mon tableau de bord — Coach',
            'coachName'    => trim(($u['first_name'] ?? '').' '.($u['last_name'] ?? '')),
            'todayDate'    => (new DateTime('today'))->format('d/m/Y'),
            'selectedDate' => $selectedDate,
            'slots'        => $slots,        // <- ENTITÉS
            'reservations' => $reservations, // <- TABLEAUX (join pour affichage)
            'adherents'    => $adherents,
        ]);
    }
}
