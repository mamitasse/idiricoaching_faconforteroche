<?php
declare(strict_types=1);

namespace App\controllers;

use App\models\SlotManager;
use App\models\ReservationManager;
use DateTime;

use function App\services\flash;
use function App\services\csrf_verify;

final class CreneauController
{
    /* ---------------- Helpers ---------------- */

    private function redirect(string $url): void
    {
        header('Location: '.$url);
        exit;
    }

    /** Vérifie la session et le rôle éventuel. Retourne l’utilisateur (array). */
    private function needLogin(?string $role = null): array
    {
        if (empty($_SESSION['user'])) {
            flash('error', 'Veuillez vous connecter.');
            $this->redirect(BASE_URL.'?action=connexion');
        }
        $u = $_SESSION['user'];
        if ($role !== null && ($u['role'] ?? '') !== $role) {
            flash('error', 'Accès non autorisé.');
            $this->redirect(BASE_URL);
        }
        return $u;
    }

    private function safeDate(?string $d): string
    {
        if (is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return $d;
        return (new DateTime('today'))->format('Y-m-d');
    }

    /* ---------------- Actions ---------------- */

    /** Réservation d’un créneau par un adhérent */
    public function reserve(): void
    {
        // CSRF
        if (!csrf_verify($_POST['_token'] ?? null)) {
            flash('error', 'Jeton CSRF invalide.');
            $this->redirect(BASE_URL.'?action=adherentDashboard');
        }

        $u = $this->needLogin('adherent');

        $slotId = isset($_POST['slot_id']) ? (int)$_POST['slot_id'] : 0;
        if ($slotId <= 0) {
            flash('error', 'Créneau invalide.');
            $this->redirect(BASE_URL.'?action=adherentDashboard');
        }

        $sm = new SlotManager();
        $slot = $sm->findEntityById($slotId);
        if (!$slot) {
            flash('error', 'Créneau introuvable.');
            $this->redirect(BASE_URL.'?action=adherentDashboard');
        }

        // Le créneau doit appartenir au coach de l’adhérent
        $coachId = (int)($u['coach_id'] ?? 0);
        if ($coachId <= 0 || $slot->getCoachId() !== $coachId) {
            flash('error', 'Ce créneau n’appartient pas à votre coach.');
            $this->redirect(BASE_URL.'?action=adherentDashboard&date='.$slot->getDate());
        }

        // Doit être disponible et dans le futur
        if (!$slot->isAvailable()) {
            flash('error', 'Ce créneau n’est plus disponible.');
            $this->redirect(BASE_URL.'?action=adherentDashboard&date='.$slot->getDate());
        }
        if ($slot->getStartDateTime() <= new DateTime()) {
            flash('error', 'Impossible de réserver un créneau passé.');
            $this->redirect(BASE_URL.'?action=adherentDashboard&date='.$slot->getDate());
        }

        // Réserver
        $rm = new ReservationManager();
        try {
            // Selon ta version : reserve() peut renvoyer bool ou int ; on ne s’en sert pas ici
            $rm->reserve($slotId, (int)$u['id']);
            flash('success', 'Créneau réservé avec succès.');
        } catch (\Throwable $e) {
            flash('error', 'Impossible de réserver ce créneau.');
        }

        $this->redirect(BASE_URL.'?action=adherentDashboard&date='.$slot->getDate());
    }

    /** Annulation par l’adhérent (règle des 36h) */
    public function reservationCancel(): void
    {
        if (!csrf_verify($_POST['_token'] ?? null)) {
            flash('error', 'Jeton CSRF invalide.');
            $this->redirect(BASE_URL.'?action=adherentDashboard');
        }

        $u = $this->needLogin('adherent');

        $resId = isset($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : 0;
        if ($resId <= 0) {
            flash('error', 'Réservation invalide.');
            $this->redirect(BASE_URL.'?action=adherentDashboard');
        }

        $rm = new ReservationManager();

        // Compatibilité : certaines versions attendent aussi l’adherentId
        $ok = false;
        try {
            $ref = new \ReflectionMethod($rm, 'cancelByAdherent');
            if ($ref->getNumberOfParameters() >= 2) {
                // Ancienne signature: cancelByAdherent(int $reservationId, int $adherentId)
                $ok = $rm->cancelByAdherent($resId, (int)$u['id']);
            } else {
                // Nouvelle signature: cancelByAdherent(int $reservationId)
                $ok = $rm->cancelByAdherent($resId);
            }
        } catch (\ReflectionException $e) {
            // Si la méthode n'existe pas : fallback générique
            try {
                $ok = $rm->cancel($resId, true);
            } catch (\Throwable $t) {
                $ok = false;
            }
        }

        if ($ok) {
            flash('success', 'Réservation annulée.');
        } else {
            flash('error', "Impossible d'annuler (délai dépassé ou réservation introuvable).");
        }

        $this->redirect(BASE_URL.'?action=adherentDashboard');
    }

    /** Annulation par le coach (pas de contrainte de délai) */
    public function reservationCancelByCoach(): void
    {
        if (!csrf_verify($_POST['_token'] ?? null)) {
            flash('error', 'Jeton CSRF invalide.');
            $this->redirect(BASE_URL.'?action=coachDashboard');
        }

        $u = $this->needLogin('coach');

        $resId = isset($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : 0;
        if ($resId <= 0) {
            flash('error', 'Réservation invalide.');
            $this->redirect(BASE_URL.'?action=coachDashboard');
        }

        $rm = new ReservationManager();

        // Compat signature : avec ou sans coachId
        $ok = false;
        try {
            $ref = new \ReflectionMethod($rm, 'cancelByCoach');
            if ($ref->getNumberOfParameters() >= 2) {
                // Ancienne signature: cancelByCoach(int $reservationId, int $coachId)
                $ok = $rm->cancelByCoach($resId, (int)$u['id']);
            } else {
                // Nouvelle signature: cancelByCoach(int $reservationId)
                $ok = $rm->cancelByCoach($resId);
            }
        } catch (\ReflectionException $e) {
            // Fallback sur cancel(..., false)
            try {
                $ok = $rm->cancel($resId, false);
            } catch (\Throwable $t) {
                $ok = false;
            }
        }

        if ($ok) {
            flash('success', 'Réservation annulée.');
        } else {
            flash('error', 'Annulation impossible.');
        }

        $this->redirect(BASE_URL.'?action=coachDashboard');
    }

    /** Coach : marquer un créneau indisponible (unavailable) */
    public function block(): void
    {
        if (!csrf_verify($_POST['_token'] ?? null)) {
            flash('error', 'Jeton CSRF invalide.');
            $this->redirect(BASE_URL.'?action=coachDashboard');
        }
        $this->needLogin('coach');

        $slotId = isset($_POST['slot_id']) ? (int)$_POST['slot_id'] : 0;
        $date   = $this->safeDate($_POST['date'] ?? null);

        if ($slotId <= 0) {
            flash('error', 'Créneau invalide.');
            $this->redirect(BASE_URL.'?action=coachDashboard&date='.$date);
        }

        $ok = (new SlotManager())->block($slotId);
        flash($ok ? 'success' : 'error', $ok ? 'Créneau marqué indisponible.' : 'Action impossible.');

        $this->redirect(BASE_URL.'?action=coachDashboard&date='.$date);
    }

    /** Coach : libérer un créneau (available) */
    public function unblock(): void
    {
        if (!csrf_verify($_POST['_token'] ?? null)) {
            flash('error', 'Jeton CSRF invalide.');
            $this->redirect(BASE_URL.'?action=coachDashboard');
        }
        $this->needLogin('coach');

        $slotId = isset($_POST['slot_id']) ? (int)$_POST['slot_id'] : 0;
        $date   = $this->safeDate($_POST['date'] ?? null);

        if ($slotId <= 0) {
            flash('error', 'Créneau invalide.');
            $this->redirect(BASE_URL.'?action=coachDashboard&date='.$date);
        }

        $ok = (new SlotManager())->free($slotId);
        flash($ok ? 'success' : 'error', $ok ? 'Créneau libéré.' : 'Action impossible.');

        $this->redirect(BASE_URL.'?action=coachDashboard&date='.$date);
    }
}