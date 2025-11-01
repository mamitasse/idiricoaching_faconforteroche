<?php
declare(strict_types=1);

namespace App\controllers;

use DateTime;
use App\models\SlotManager;
use App\models\ReservationManager;

use function App\services\flash;
use function App\services\csrf_verify;

/**
 * CreneauController (mentor-style)
 * --------------------------------
 * - Réserve un créneau (adhérent)
 * - Annule une réservation (adhérent : règle modèle, coach : sans contrainte)
 * - Notifie par e-mail l’adhérent, le coach concerné, et l’admin en copie
 *
 * Stratégie d’envoi :
 *   - Si MAIL_TRANSPORT === 'smtp' + constantes SMTP_* définies => \App\services\sendMailSmtp()
 *   - Sinon, en DEV (IN_DEV=true) => \App\services\logMailForDev() dans logs/mail.log
 *   - Sinon, PROD => \App\services\sendMailNative() (si MTA côté serveur)
 */
final class CreneauController
{
    /* ==============================
     * Helpers génériques
     * ============================ */

    /** Redirection simple. */
    private function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    /**
     * Vérifie la session et le rôle éventuel.
     * @return array<string,mixed> l’utilisateur en session
     */
    private function needLogin(?string $role = null): array
    {
        if (empty($_SESSION['user'])) {
            flash('error', 'Veuillez vous connecter.');
            $this->redirect(BASE_URL . '?action=connexion');
        }
        $user = $_SESSION['user'];

        if ($role !== null && ($user['role'] ?? '') !== $role) {
            flash('error', 'Accès non autorisé.');
            $this->redirect(BASE_URL);
        }
        return $user;
    }

    /** Normalise une date YYYY-MM-DD ou renvoie aujourd’hui. */
    private function safeDate(?string $date): string
    {
        if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }
        return (new DateTime('today'))->format('Y-m-d');
    }

    /* ==============================
     * Emails : helpers internes
     * ============================ */

    /** Petite étiquette lisible pour un créneau (ex: "30/10/2025 09:00–10:00"). */
    private function slotLabel(\App\models\SlotEntity $slot): string
    {
        $date  = $slot->getDate(); // 'Y-m-d'
        $start = $slot->getStartDateTime()->format('H:i');
        $end   = $slot->getEndDateTime()->format('H:i');

        $dateFr = \DateTime::createFromFormat('Y-m-d', $date);
        $dateFr = $dateFr ? $dateFr->format('d/m/Y') : $date;

        return "{$dateFr} {$start}–{$end}";
    }

    /**
     * Construit sujet + corps HTML/TXT d’un email (confirmation ou annulation).
     * @param array<string,mixed> $payload
     * @param 'confirm'|'cancel'  $type
     * @return array{subject:string, html:string, text:string}
     */
    private function buildEmailBodies(array $payload, string $type): array
    {
        $date  = (new DateTime((string)$payload['date']))->format('d/m/Y');
        $start = (new DateTime((string)$payload['start_time']))->format('H:i');
        $end   = (new DateTime((string)$payload['end_time']))->format('H:i');

        $adherentFull = trim(($payload['adherent_first'] ?? '') . ' ' . ($payload['adherent_last'] ?? ''));
        $coachFull    = trim(($payload['coach_first'] ?? '') . ' ' . ($payload['coach_last'] ?? ''));

        if ($type === 'confirm') {
            $subject = "Confirmation de réservation — {$date} {$start}-{$end}";
            $html = "<p>Bonjour {$adherentFull},</p>
                     <p>Votre réservation est <strong>confirmée</strong> pour le <strong>{$date}</strong> de <strong>{$start}</strong> à <strong>{$end}</strong> avec <strong>{$coachFull}</strong>.</p>
                     <p>À bientôt,<br>Idiri Coaching</p>";
        } else {
            $subject = "Annulation de réservation — {$date} {$start}-{$end}";
            $html = "<p>Bonjour {$adherentFull},</p>
                     <p>Votre réservation du <strong>{$date}</strong> de <strong>{$start}</strong> à <strong>{$end}</strong> avec <strong>{$coachFull}</strong> a été <strong>annulée</strong>.</p>
                     <p>Idiri Coaching</p>";
        }

        // Version texte simple
        $text = strip_tags(str_replace(['<br>','<br/>','<br />'], "\n", $html));

        return [
            'subject' => $subject,
            'html'    => $html,
            'text'    => $text,
        ];
    }

    /**
     * Envoi à une liste de destinataires, avec la stratégie SMTP/DEV/Native.
     * @param string[] $recipients
     */
    private function sendToRecipients(array $recipients, string $subject, string $html, string $text, string $replyTo = ''): void
    {
        // Filtre e-mails valides
        $recipients = array_values(array_filter($recipients, static function ($mail) {
            return is_string($mail) && filter_var($mail, FILTER_VALIDATE_EMAIL);
        }));
        if (!$recipients) {
            return;
        }

        // SMTP disponible ?
        $hasSmtp = (defined('MAIL_TRANSPORT') && MAIL_TRANSPORT === 'smtp')
            && defined('SMTP_HOST') && SMTP_HOST
            && defined('SMTP_USER') && SMTP_USER
            && defined('SMTP_PASS') && SMTP_PASS;

        if ($hasSmtp && function_exists('\App\services\sendMailSmtp')) {
            foreach ($recipients as $to) {
                [$ok, $err] = \App\services\sendMailSmtp(
                    $to,
                    $subject,
                    $html,
                    $text,
                    defined('MAIL_FROM') ? MAIL_FROM : null,
                    defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : null
                );
                if (!$ok && defined('IN_DEV') && IN_DEV && function_exists('\App\services\logMailForDev')) {
                    \App\services\logMailForDev([$to], $subject, $text, $replyTo);
                }
            }
            return;
        }

        // Pas de SMTP : en DEV -> log fichier
        if (defined('IN_DEV') && IN_DEV && function_exists('\App\services\logMailForDev')) {
            \App\services\logMailForDev($recipients, $subject, $text, $replyTo);
            return;
        }

        // PROD sans SMTP -> mail() natif
        if (function_exists('\App\services\sendMailNative')) {
            \App\services\sendMailNative($recipients, $subject, $html, $text, $replyTo);
        }
    }

    /**
     * Enveloppe d’envoi : à partir d’un payload (réservation) + type.
     * @param array<string,mixed> $payload
     * @param 'confirm'|'cancel'  $type
     */
    private function sendBookingEmails(array $payload, string $type): void
    {
        // Corps
        $bodies = $this->buildEmailBodies($payload, $type);

        // Destinataires : adhérent + coach + admin en copie
        $recipients = [];
        $adherentEmail = (string)($payload['adherent_email'] ?? '');
        $coachEmail    = (string)($payload['coach_email'] ?? '');

        if (filter_var($adherentEmail, FILTER_VALIDATE_EMAIL)) {
            $recipients[] = $adherentEmail;
        }
        if (filter_var($coachEmail, FILTER_VALIDATE_EMAIL)) {
            $recipients[] = $coachEmail;
        }
        if (defined('SITE_ADMIN_EMAIL') && filter_var(SITE_ADMIN_EMAIL, FILTER_VALIDATE_EMAIL)) {
            $recipients[] = SITE_ADMIN_EMAIL;
        }

        $this->sendToRecipients(
            $recipients,
            $bodies['subject'],
            $bodies['html'],
            $bodies['text'],
            $adherentEmail // reply-to = l’adhérent
        );
    }

    /* ==============================
     * Actions
     * ============================ */

    /** Réservation d’un créneau par un adhérent + email de confirmation. */
    public function reserve(): void
    {
        // CSRF
        if (!csrf_verify($_POST['_token'] ?? null)) {
            flash('error', 'Jeton CSRF invalide.');
            $this->redirect(BASE_URL . '?action=adherentDashboard');
        }

        $user = $this->needLogin('adherent');

        $slotId = isset($_POST['slot_id']) ? (int)$_POST['slot_id'] : 0;
        if ($slotId <= 0) {
            flash('error', 'Créneau invalide.');
            $this->redirect(BASE_URL . '?action=adherentDashboard');
        }

        $slotManager = new SlotManager();
        $slotEntity  = $slotManager->findEntityById($slotId);
        if (!$slotEntity) {
            flash('error', 'Créneau introuvable.');
            $this->redirect(BASE_URL . '?action=adherentDashboard');
        }

        // Le créneau doit appartenir au coach de l’adhérent
        $coachId = (int)($user['coach_id'] ?? 0);
        if ($coachId <= 0 || $slotEntity->getCoachId() !== $coachId) {
            flash('error', 'Ce créneau n’appartient pas à votre coach.');
            $this->redirect(BASE_URL . '?action=adherentDashboard&date=' . $slotEntity->getDate());
        }

        // Doit être disponible et dans le futur
        if (!$slotEntity->isAvailable()) {
            flash('error', 'Ce créneau n’est plus disponible.');
            $this->redirect(BASE_URL . '?action=adherentDashboard&date=' . $slotEntity->getDate());
        }
        if ($slotEntity->getStartDateTime() <= new DateTime()) {
            flash('error', 'Impossible de réserver un créneau passé.');
            $this->redirect(BASE_URL . '?action=adherentDashboard&date=' . $slotEntity->getDate());
        }

        // Réserver
        $reservationManager = new ReservationManager();
        try {
            $reservationId = $reservationManager->reserve($slotId, (int)$user['id']); // renvoie l’ID
            flash('success', 'Créneau réservé avec succès.');

            // Emails
            if (is_int($reservationId) && $reservationId > 0 && method_exists($reservationManager, 'emailPayload')) {
                $payload = $reservationManager->emailPayload($reservationId);
                if (is_array($payload)) {
                    $this->sendBookingEmails($payload, 'confirm');
                }
            }
        } catch (\Throwable $e) {
            // Remonte un message neutre côté UI
            flash('error', 'Impossible de réserver ce créneau.');
        }

        $this->redirect(BASE_URL . '?action=adherentDashboard&date=' . $slotEntity->getDate());
    }

    /**
     * Annulation par l’adhérent (règle métier appliquée par le modèle : 48h/36h selon ta version)
     * + email d’annulation aux 3 parties.
     */
    public function reservationCancel(): void
    {
        if (!csrf_verify($_POST['_token'] ?? null)) {
            flash('error', 'Jeton CSRF invalide.');
            $this->redirect(BASE_URL . '?action=adherentDashboard');
        }

        $user = $this->needLogin('adherent');

        $reservationId = isset($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : 0;
        if ($reservationId <= 0) {
            flash('error', 'Réservation invalide.');
            $this->redirect(BASE_URL . '?action=adherentDashboard');
        }

        $reservationManager = new ReservationManager();

        // Vérification d’appartenance + récupération du payload avant annulation
        $payload = method_exists($reservationManager, 'emailPayload')
            ? $reservationManager->emailPayload($reservationId)
            : null;

        if (!$payload || (int)($payload['adherent_id'] ?? 0) !== (int)$user['id']) {
            flash('error', 'Accès interdit.');
            $this->redirect(BASE_URL . '?action=adherentDashboard');
        }

        try {
            $ok = $reservationManager->cancelByAdherent($reservationId); // applique la règle du modèle
            if ($ok) {
                $this->sendBookingEmails($payload, 'cancel');
                flash('success', 'Réservation annulée.');
            } else {
                flash('error', "Impossible d'annuler (délai dépassé ou réservation introuvable).");
            }
        } catch (\Throwable $e) {
            // Exemple : “Annulation impossible : délai de 48h dépassé.”
            flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '?action=adherentDashboard');
    }

    /** Annulation par le coach (sans contrainte de délai) + email aux 3 parties. */
    public function reservationCancelByCoach(): void
    {
        if (!csrf_verify($_POST['_token'] ?? null)) {
            flash('error', 'Jeton CSRF invalide.');
            $this->redirect(BASE_URL . '?action=coachDashboard');
        }

        $user = $this->needLogin('coach');

        $reservationId = isset($_POST['reservation_id']) ? (int)$_POST['reservation_id'] : 0;
        if ($reservationId <= 0) {
            flash('error', 'Réservation invalide.');
            $this->redirect(BASE_URL . '?action=coachDashboard');
        }

        $reservationManager = new ReservationManager();

        // Récupération du payload avant annulation (contrôle d’appartenance)
        $payload = method_exists($reservationManager, 'emailPayload')
            ? $reservationManager->emailPayload($reservationId)
            : null;

        if (!$payload || (int)($payload['coach_id'] ?? 0) !== (int)$user['id']) {
            flash('error', 'Accès interdit.');
            $this->redirect(BASE_URL . '?action=coachDashboard');
        }

        try {
            $ok = $reservationManager->cancelByCoach($reservationId); // pas de contrainte de délai
            if ($ok) {
                $this->sendBookingEmails($payload, 'cancel');
                flash('success', 'Réservation annulée.');
            } else {
                flash('error', 'Annulation impossible.');
            }
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }

        $this->redirect(BASE_URL . '?action=coachDashboard');
    }

    /** Coach : marquer un créneau indisponible (unavailable). */
    public function block(): void
    {
        if (!csrf_verify($_POST['_token'] ?? null)) {
            flash('error', 'Jeton CSRF invalide.');
            $this->redirect(BASE_URL . '?action=coachDashboard');
        }
        $this->needLogin('coach');

        $slotId = isset($_POST['slot_id']) ? (int)$_POST['slot_id'] : 0;
        $date   = $this->safeDate($_POST['date'] ?? null);

        if ($slotId <= 0) {
            flash('error', 'Créneau invalide.');
            $this->redirect(BASE_URL . '?action=coachDashboard&date=' . $date);
        }

        $ok = (new SlotManager())->block($slotId);
        flash($ok ? 'success' : 'error', $ok ? 'Créneau marqué indisponible.' : 'Action impossible.');

        $this->redirect(BASE_URL . '?action=coachDashboard&date=' . $date);
    }

    /** Coach : libérer un créneau (available). */
    public function unblock(): void
    {
        if (!csrf_verify($_POST['_token'] ?? null)) {
            flash('error', 'Jeton CSRF invalide.');
            $this->redirect(BASE_URL . '?action=coachDashboard');
        }
        $this->needLogin('coach');

        $slotId = isset($_POST['slot_id']) ? (int)$_POST['slot_id'] : 0;
        $date   = $this->safeDate($_POST['date'] ?? null);

        if ($slotId <= 0) {
            flash('error', 'Créneau invalide.');
            $this->redirect(BASE_URL . '?action=coachDashboard&date=' . $date);
        }

        $ok = (new SlotManager())->free($slotId);
        flash($ok ? 'success' : 'error', $ok ? 'Créneau libéré.' : 'Action impossible.');

        $this->redirect(BASE_URL . '?action=coachDashboard&date=' . $date);
    }
}
