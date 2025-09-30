<?php
declare(strict_types=1);

namespace App\controllers;

use App\views\View;
use App\models\UserManager;
use function App\services\flash;
use function App\services\csrfVerify;
use function App\services\csrf_verify;   // alias legacy
use function App\services\sendMailSmtp;  // helper SMTP (PHPMailer)

/**
 * ContactController (mentor-style)
 * --------------------------------
 * - GET  showContactForm   : affiche le formulaire
 * - POST handleContactPost : valide + envoie le message
 *
 * En DEV (IN_DEV=true) :
 *   - si SMTP configuré -> envoi réel via PHPMailer
 *   - sinon             -> écrit dans logs/mail.log et affiche "succès"
 *
 * En PROD (IN_DEV=false) :
 *   - si SMTP configuré -> envoi réel via PHPMailer
 *   - sinon             -> fallback mail() (si serveur OK)
 *
 * Vue attendue : views/templates/contact.php
 */
final class ContactController
{
    /* ============================
     * Pages (GET)
     * ========================== */

    /** Affiche le formulaire de contact. */
    public function showContactForm(): void
    {
        $userManager   = new UserManager();
        $coachEntities = [];

        // Idéalement : ENTITÉS (getId(), getFullName(), getEmail(), …)
        if (method_exists($userManager, 'coachesEntities')) {
            $coachEntities = $userManager->coachesEntities();
        } elseif (method_exists($userManager, 'listEntitiesByRole')) {
            $coachEntities = $userManager->listEntitiesByRole('coach');
        }

        View::render('templates/contact', [
            'title'         => 'Contact',
            'coachEntities' => $coachEntities,
        ]);
    }

    /* ============================
     * Traitement (POST)
     * ========================== */

    /** Soumission du formulaire de contact. */
    public function handleContactPost(): void
    {
        // 1) CSRF compatible (camelCase ET snake_case)
        $postedToken = $_POST['_token'] ?? null;
        $csrfIsValid = function_exists('\App\services\csrfVerify')
            ? csrfVerify($postedToken)
            : (function_exists('\App\services\csrf_verify') ? csrf_verify($postedToken) : false);

        if (!$csrfIsValid) {
            flash('error', 'Jeton de sécurité invalide.');
            $this->redirectTo('?action=contact');
        }

        // 2) Normalisation des champs
        $firstName      = trim((string)($_POST['first_name'] ?? ''));
        $lastName       = trim((string)($_POST['last_name'] ?? ''));
        $emailAddress   = trim((string)($_POST['email'] ?? ''));
        $phoneNumber    = trim((string)($_POST['phone'] ?? ''));
        $postalAddress  = trim((string)($_POST['address'] ?? ''));
        $coachIdString  = (string)($_POST['coach_id'] ?? '');
        $messageContent = trim((string)($_POST['message'] ?? ''));

        // 3) Validation simple
        $validationErrors = [];
        if ($firstName === '') { $validationErrors[] = 'Le prénom est requis.'; }
        if ($lastName === '')  { $validationErrors[] = 'Le nom est requis.'; }
        if ($emailAddress === '' || !filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
            $validationErrors[] = 'Un email valide est requis.';
        }
        if ($phoneNumber === '')   { $validationErrors[] = 'Le téléphone est requis.'; }
        if ($postalAddress === '') { $validationErrors[] = "L'adresse postale est requise."; }
        if ($coachIdString === '' || !ctype_digit($coachIdString) || (int)$coachIdString <= 0) {
            $validationErrors[] = 'Veuillez choisir un coach.';
        }
        if ($messageContent === '') { $validationErrors[] = 'Le message est requis.'; }

        if (!empty($validationErrors)) {
            foreach ($validationErrors as $msg) {
                flash('error', $msg);
            }
            $this->redirectTo('?action=contact');
        }

        // 4) Email du coach à partir de l’entité
        $coachId     = (int)$coachIdString;
        $userManager = new UserManager();
        $coachEntity = $userManager->findEntityById($coachId);
        $coachEmail  = $coachEntity ? (string)$coachEntity->getEmail() : null;

        if (!$coachEmail || !filter_var($coachEmail, FILTER_VALIDATE_EMAIL)) {
            flash('error', "Impossible de trouver l’e-mail du coach sélectionné.");
            $this->redirectTo('?action=contact');
        }

        // 5) Prépare contenu
        $siteAdminEmail = 'idiricoaching56@gmail.com';
        $recipients     = [$coachEmail, $siteAdminEmail];

        $subject = sprintf('Nouveau contact — %s %s', $firstName, $lastName);
        [$htmlBody, $textBody] = $this->buildBodies(
            $firstName, $lastName, $emailAddress, $phoneNumber, $postalAddress, $messageContent
        );

        // 6) Envoi
        $sendSucceeded = $this->sendEmail($recipients, $subject, $htmlBody, $textBody, $emailAddress);

        flash(
            $sendSucceeded ? 'success' : 'error',
            $sendSucceeded
                ? 'Votre message a bien été envoyé. Merci !'
                : "Un problème est survenu lors de l'envoi. Merci de réessayer."
        );

        $this->redirectTo('?action=contact');
    }

    /* ============================
     * Helpers internes
     * ========================== */

    /** Redirection relative au site (préfixée par BASE_URL). */
    private function redirectTo(string $relativePath): void
    {
        header('Location: ' . BASE_URL . $relativePath);
        exit;
    }

    /** Construit [htmlBody, textBody]. */
    private function buildBodies(
        string $firstName,
        string $lastName,
        string $emailAddress,
        string $phoneNumber,
        string $postalAddress,
        string $messageContent
    ): array {
        $esc = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        $html = '
            <h2>Nouveau message de contact</h2>
            <ul>
              <li><strong>Nom :</strong> ' . $esc($lastName) . '</li>
              <li><strong>Prénom :</strong> ' . $esc($firstName) . '</li>
              <li><strong>Email :</strong> ' . $esc($emailAddress) . '</li>
              <li><strong>Téléphone :</strong> ' . $esc($phoneNumber) . '</li>
              <li><strong>Adresse :</strong> ' . $esc($postalAddress) . '</li>
            </ul>
            <p><strong>Message :</strong></p>
            <p>' . nl2br($esc($messageContent)) . '</p>
        ';

        $text = "Nouveau message de contact\n"
              . "------------------------\n"
              . "Nom       : {$lastName}\n"
              . "Prénom    : {$firstName}\n"
              . "Email     : {$emailAddress}\n"
              . "Téléphone : {$phoneNumber}\n"
              . "Adresse   : {$postalAddress}\n\n"
              . "Message :\n{$messageContent}\n";

        return [$html, $text];
    }

    /**
     * Envoi d’e-mail, avec logique DEV/PROD & SMTP.
     * - Si SMTP (constantes SMTP_*) est configuré → envoi via PHPMailer.
     * - Sinon :
     *    - DEV  → écrit dans logs/mail.log et retourne true
     *    - PROD → tente mail() natif
     *
     * @param string[] $recipients
     */
    private function sendEmail(
        array $recipients,
        string $subject,
        string $htmlBody,
        string $textBody,
        string $replyTo
    ): bool {
        // Lire TOUTES les constantes via defined()+constant() (évite les "undefined constant")
        $smtpHost   = defined('SMTP_HOST')      ? (string) constant('SMTP_HOST')      : '';
        $smtpUser   = defined('SMTP_USER')      ? (string) constant('SMTP_USER')      : '';
        $smtpPass   = defined('SMTP_PASS')      ? (string) constant('SMTP_PASS')      : '';
        $smtpFrom   = defined('SMTP_FROM')      ? (string) constant('SMTP_FROM')      : '';
        $smtpName   = defined('SMTP_FROM_NAME') ? (string) constant('SMTP_FROM_NAME') : 'Idiri Coaching';

        $smtpIsConfigured = ($smtpHost !== '' && $smtpUser !== '' && $smtpPass !== '' && $smtpFrom !== '');

        // 1) SMTP dispo -> envoi via PHPMailer (helper)
        if ($smtpIsConfigured) {
            $allSucceeded = true;
            foreach ($recipients as $toAddress) {
                [$ok, $error] = sendMailSmtp($toAddress, $subject, $htmlBody, $textBody, $smtpFrom, $smtpName);
                if (!$ok) {
                    $allSucceeded = false;
                    error_log('[SMTP] send failed to ' . $toAddress . ' : ' . $error);
                }
            }
            return $allSucceeded;
        }

        // 2) Pas de SMTP
        if (defined('IN_DEV') && IN_DEV === true) {
            // DEV => on loggue et on dit "OK"
            $projectRoot = dirname(__DIR__);
            $logDir      = $projectRoot . '/logs';
            $logFile     = $logDir . '/mail.log';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0777, true);
            }
            $log = "---- " . date('Y-m-d H:i:s') . " ----\n"
                 . "TO      : " . implode(', ', $recipients) . "\n"
                 . "SUBJECT : " . $subject . "\n"
                 . "REPLYTO : " . $replyTo . "\n"
                 . "BODY(TXT): \n" . $textBody . "\n\n";
            @file_put_contents($logFile, $log, FILE_APPEND);
            return true;
        }

        // 3) PROD sans SMTP -> tentative via mail() natif
        return $this->sendViaNativeMail($recipients, $subject, $htmlBody, $textBody, $replyTo);
    }

    /** Fallback via mail() (HTML multipart/alternative). */
    private function sendViaNativeMail(
        array $recipients,
        string $subject,
        string $htmlBody,
        string $textBody,
        string $replyTo
    ): bool {
        $toHeader = implode(',', $recipients);
        $boundary = '=_Boundary_' . md5((string) microtime(true));

        $host     = parse_url((string) (BASE_URL ?? ''), PHP_URL_HOST) ?: 'idiricoaching.local';
        $fromMail = 'no-reply@' . $host;

        $headers   = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'From: Idiri Coaching <' . $fromMail . '>';
        if (filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

        $body  = '';
        $body .= '--' . $boundary . "\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $body .= $textBody . "\r\n\r\n";

        $body .= '--' . $boundary . "\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $body .= $htmlBody . "\r\n\r\n";

        $body .= '--' . $boundary . "--\r\n";

        return @mail($toHeader, $subject, $body, implode("\r\n", $headers));
    }
}
