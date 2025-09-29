<?php
declare(strict_types=1);

namespace App\controllers;

use App\views\View;
use App\models\UserManager;
use function App\services\flash;

/**
 * ContactController (mentor-style)
 * -------------------------------
 * - Affiche le formulaire de contact
 * - Valide et traite l'envoi
 * - En PROD : essai d’envoi réel (PHPMailer si disponible, sinon mail())
 * - En DEV  : écriture dans un log + message succès (pour éviter les problèmes de mail local)
 *
 * Vue attendue : views/templates/contact.php
 *   Le contrôleur passe : ['title' => 'Contact', 'coachEntities' => [...]]
 */
final class ContactController
{
    /* =========================================================
     * Pages (GET)
     * ======================================================= */

    /**
     * Affiche le formulaire de contact.
     * On fournit la liste des coachs pour la liste déroulante.
     */
    public function showContactForm(): void
    {
        $userManager   = new UserManager();
        $coachEntities = [];

        // Idéalement, on récupère les ENTITÉS des coachs (plus riche, typed getters)
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

    /* =========================================================
     * Traitement (POST)
     * ======================================================= */

    /**
     * Traite la soumission du formulaire de contact.
     * Valide les champs, construit le message, et tente l'envoi.
     */
    public function handleContactPost(): void
    {
        // 1) CSRF
        if (!$this->isCsrfValid($_POST['_token'] ?? null)) {
            flash('error', 'Jeton de sécurité invalide. Merci de réessayer.');
            $this->redirectTo('?action=contact');
        }

        // 2) Récupération + normalisation
        $firstName      = trim((string)($_POST['first_name'] ?? ''));
        $lastName       = trim((string)($_POST['last_name'] ?? ''));
        $emailAddress   = trim((string)($_POST['email'] ?? ''));
        $phoneNumber    = trim((string)($_POST['phone'] ?? ''));
        $postalAddress  = trim((string)($_POST['address'] ?? ''));
        $coachIdString  = (string)($_POST['coach_id'] ?? '');
        $messageContent = trim((string)($_POST['message'] ?? ''));

        // 3) Validation simple (tu peux renforcer selon ton besoin)
        $validationErrors = [];

        if ($firstName === '')       { $validationErrors[] = 'Le prénom est requis.'; }
        if ($lastName === '')        { $validationErrors[] = 'Le nom est requis.'; }
        if ($emailAddress === '' ||
            !filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
            $validationErrors[] = 'Un email valide est requis.';
        }
        if ($phoneNumber === '')     { $validationErrors[] = 'Le téléphone est requis.'; }
        if ($postalAddress === '')   { $validationErrors[] = "L'adresse postale est requise."; }
        if ($coachIdString === '' ||
            !ctype_digit($coachIdString) || (int)$coachIdString <= 0) {
            $validationErrors[] = 'Veuillez choisir un coach.';
        }
        if ($messageContent === '')  { $validationErrors[] = 'Le message est requis.'; }

        if (!empty($validationErrors)) {
            foreach ($validationErrors as $errorMsg) {
                flash('error', $errorMsg);
            }
            $this->redirectTo('?action=contact');
        }

        // 4) Récupère l'adresse e-mail du coach
        $coachId     = (int)$coachIdString;
        $userManager = new UserManager();
        $coachEmail  = null;

        if (method_exists($userManager, 'findEntityById')) {
            $coachEntity = $userManager->findEntityById($coachId);
            $coachEmail  = $coachEntity ? (string)$coachEntity->getEmail() : null;
        } elseif (method_exists($userManager, 'getById')) {
            $coachRow   = $userManager->getById($coachId);
            $coachEmail = is_array($coachRow) ? (string)($coachRow['email'] ?? '') : null;
        }

        if (!$coachEmail || !filter_var($coachEmail, FILTER_VALIDATE_EMAIL)) {
            flash('error', "Impossible de trouver l'e-mail du coach sélectionné.");
            $this->redirectTo('?action=contact');
        }

        // 5) Prépare le message / destinataires
        $siteAdminEmail = 'idiricoaching56@gmail.com'; // e-mail “principal”
        $recipients     = [$coachEmail, $siteAdminEmail];

        $subject = sprintf(
            'Nouveau contact — %s %s',
            $firstName,
            $lastName
        );

        // Corps HTML
        $htmlBody = $this->buildHtmlBody(
            $firstName,
            $lastName,
            $emailAddress,
            $phoneNumber,
            $postalAddress,
            $messageContent
        );

        // Corps texte brut (fallback)
        $textBody = $this->buildTextBody(
            $firstName,
            $lastName,
            $emailAddress,
            $phoneNumber,
            $postalAddress,
            $messageContent
        );

        // 6) Envoi
        $sendOk = $this->sendEmail($recipients, $subject, $htmlBody, $textBody, $emailAddress);
        if ($sendOk) {
            flash('success', 'Votre message a bien été envoyé. Merci !');
        } else {
            flash('error', "Un problème est survenu lors de l'envoi. Merci de réessayer.");
        }

        $this->redirectTo('?action=contact');
    }

    /* =========================================================
     * Helpers internes
     * ======================================================= */

    /** Redirection vers une route relative au site (préfixée par BASE_URL). */
    private function redirectTo(string $relativePath): void
    {
        header('Location: ' . BASE_URL . $relativePath);
        exit;
    }

    /**
     * Vérifie le CSRF en restant compatible avec les deux variantes que tu as :
     * - App\services\csrfVerify($token)   (mentor)
     * - App\services\csrf_verify($token)  (legacy)
     */
    private function isCsrfValid(?string $postedToken): bool
    {
        if (function_exists('\App\services\csrfVerify')) {
            return \App\services\csrfVerify($postedToken);
        }
        if (function_exists('\App\services\csrf_verify')) {
            return \App\services\csrf_verify($postedToken);
        }
        // Par sécurité : si aucune fonction, on refuse
        return false;
    }

    /**
     * Construit le HTML du message.
     */
    private function buildHtmlBody(
        string $firstName,
        string $lastName,
        string $emailAddress,
        string $phoneNumber,
        string $postalAddress,
        string $messageContent
    ): string {
        $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        return '
            <h2>Nouveau message de contact</h2>
            <ul>
              <li><strong>Nom :</strong> ' . $e($lastName) . '</li>
              <li><strong>Prénom :</strong> ' . $e($firstName) . '</li>
              <li><strong>Email :</strong> ' . $e($emailAddress) . '</li>
              <li><strong>Téléphone :</strong> ' . $e($phoneNumber) . '</li>
              <li><strong>Adresse :</strong> ' . $e($postalAddress) . '</li>
            </ul>
            <p><strong>Message :</strong></p>
            <p>' . nl2br($e($messageContent)) . '</p>
        ';
    }

    /**
     * Construit une version texte du message (fallback si HTML non supporté).
     */
    private function buildTextBody(
        string $firstName,
        string $lastName,
        string $emailAddress,
        string $phoneNumber,
        string $postalAddress,
        string $messageContent
    ): string {
        return "Nouveau message de contact\n"
             . "------------------------\n"
             . "Nom       : {$lastName}\n"
             . "Prénom    : {$firstName}\n"
             . "Email     : {$emailAddress}\n"
             . "Téléphone : {$phoneNumber}\n"
             . "Adresse   : {$postalAddress}\n\n"
             . "Message :\n{$messageContent}\n";
    }

    /**
     * Envoi de l’e-mail.
     * - En DEV  : écrit un log et renvoie true.
     * - En PROD : tente PHPMailer si présent (libs/phpmailer/*), sinon mail().
     *
     * @param string[] $recipients Liste d’emails destinataires
     * @param string   $subject    Sujet
     * @param string   $htmlBody   Message HTML
     * @param string   $textBody   Message texte (alt)
     * @param string   $replyTo    Email de la personne qui contacte (pour répondre)
     */
    private function sendEmail(
        array $recipients,
        string $subject,
        string $htmlBody,
        string $textBody,
        string $replyTo
    ): bool {
        // DEV : journalise plutôt que d'envoyer réellement
        if (defined('IN_DEV') && IN_DEV === true) {
            $logDir  = dirname(__DIR__) . '/logs';
            $logFile = $logDir . '/mail.log';
            if (!is_dir($logDir)) { @mkdir($logDir, 0777, true); }

            $log = "---- " . date('Y-m-d H:i:s') . " ----\n"
                 . "TO      : " . implode(', ', $recipients) . "\n"
                 . "SUBJECT : " . $subject . "\n"
                 . "REPLYTO : " . $replyTo . "\n"
                 . "BODY(TXT): \n" . $textBody . "\n\n";

            @file_put_contents($logFile, $log, FILE_APPEND);
            return true;
        }

        // PROD : tente PHPMailer si présent (libs/phpmailer/*.php), sinon mail()
        $phpMailerRoot = dirname(__DIR__) . '/libs/phpmailer';
        $phpMailerOk   = is_file($phpMailerRoot . '/PHPMailer.php')
                      && is_file($phpMailerRoot . '/SMTP.php')
                      && is_file($phpMailerRoot . '/Exception.php');

        if ($phpMailerOk) {
            // Chargement manuel des classes (pas de composer)
            require_once $phpMailerRoot . '/PHPMailer.php';
            require_once $phpMailerRoot . '/SMTP.php';
            require_once $phpMailerRoot . '/Exception.php';

            try {
                $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);

                // Par défaut, on passe par la fonction mail() du serveur.
                // (Si tu veux du SMTP, configure ici : Host, Username, Password, etc.)
                $mailer->isMail();

                // From & Reply-To
                $host     = parse_url((string)(BASE_URL ?? ''), PHP_URL_HOST) ?: 'idiricoaching.local';
                $fromMail = 'no-reply@' . $host;
                $mailer->setFrom($fromMail, 'Idiri Coaching');
                if (filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                    $mailer->addReplyTo($replyTo);
                }

                // Destinataires
                foreach ($recipients as $email) {
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $mailer->addAddress($email);
                    }
                }

                // Contenu
                $mailer->Subject = $subject;
                $mailer->isHTML(true);
                $mailer->Body    = $htmlBody;
                $mailer->AltBody = $textBody;

                $mailer->send();
                return true;
            } catch (\Throwable $e) {
                // Fallback mail() si PHPMailer échoue
                return $this->sendViaNativeMail($recipients, $subject, $htmlBody, $textBody, $replyTo);
            }
        }

        // Pas de PHPMailer : fallback mail()
        return $this->sendViaNativeMail($recipients, $subject, $htmlBody, $textBody, $replyTo);
    }

    /**
     * Envoi via la fonction mail() native de PHP (HTML multipart/alternative).
     * Attention : sur Windows/XAMPP, mail() n'enverra souvent rien sans config SMTP locale.
     */
    private function sendViaNativeMail(
        array $recipients,
        string $subject,
        string $htmlBody,
        string $textBody,
        string $replyTo
    ): bool {
        $toHeader = implode(',', $recipients);

        // Génère une boundary unique pour multipart/alternative
        $boundary = '=_Boundary_' . md5((string)microtime(true));

        $host     = parse_url((string)(BASE_URL ?? ''), PHP_URL_HOST) ?: 'idiricoaching.local';
        $fromMail = 'no-reply@' . $host;

        $headers  = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'From: Idiri Coaching <' . $fromMail . '>';
        if (filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

        // Corps multipart: partie texte puis HTML
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
