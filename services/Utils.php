<?php
declare(strict_types=1);

/**
 * ============================================================================
 * Helpers applicatifs — Idiri Coaching (mentor-style)
 * ============================================================================
 * TABLE DES MATIÈRES
 *   0) Namespace & notes
 *   1) Capitalisation (capitalizeWords)
 *   2) Échappement HTML (escapeHtml)
 *   3) Flash messages (addFlash / consumeFlashes)
 *   4) CSRF helpers (csrfToken / csrfVerify / csrfInput / csrfCheck)
 *   5) Utilitaires (isPost / validateEmail)
 *   6) Emailing
 *        - sendMailSmtp()  : via PHPMailer/SMTP (recommandé)
 *        - logMailForDev() : log local (DEV)
 *        - sendMailNative(): mail() (fallback si pas d’SMTP)
 *   7) Aliases rétro-compatibles (snake_case)
 * ----------------------------------------------------------------------------
 * Conventions “façon mentor” :
 *   - Noms publics en camelCase
 *   - Compat assurée avec d’anciens noms snake_case via alias
 *   - Pas de logique métier ici : uniquement des helpers transverses
 * ============================================================================ 
 */

namespace App\services;

/* ============================================================================
 * 1) CAPITALISATION
 * ========================================================================== */

/**
 * Met en majuscule la 1ʳᵉ lettre de chaque mot (UTF-8), 
 * en préservant espaces, tirets, apostrophes :
 *  "jean-luc d'argenteuil" → "Jean-Luc D'Argenteuil"
 */
function capitalizeWords(string $value): string
{
    if ($value === '') return '';
    // Découpe en conservant les séparateurs (capture)
    $parts = preg_split("/([\\s\\-’']+)/u", $value, -1, \PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) return $value;

    $result = '';
    foreach ($parts as $piece) {
        // Séparateurs → recollés tels quels
        if (preg_match("/^[\\s\\-’']+$/u", $piece)) {
            $result .= $piece;
            continue;
        }
        $first = mb_substr($piece, 0, 1, 'UTF-8');
        $rest  = mb_substr($piece, 1, null, 'UTF-8');
        $result .= mb_strtoupper($first, 'UTF-8') . mb_strtolower($rest, 'UTF-8');
    }
    return $result;
}


/* ============================================================================
 * 2) ÉCHAPPEMENT HTML (sécurité XSS)
 * ========================================================================== */

/**
 * Échappe une chaîne pour l’affichage HTML.
 */
function escapeHtml(?string $value): string
{
    return htmlspecialchars($value ?? '', \ENT_QUOTES, 'UTF-8');
}


/* ============================================================================
 * 3) FLASH MESSAGES (éphémères en session)
 * ========================================================================== */

/**
 * Ajoute un message “flash” dans la session.
 * @param string $type    ex: success|error|info|warning
 * @param string $message message utilisateur
 */
function addFlash(string $type, string $message): void
{
    $_SESSION['flash'][$type][] = $message;
}

/**
 * Récupère tous les messages flash puis vide la pile.
 * À appeler UNE FOIS dans le layout.
 * @return array<string,string[]>
 */
function consumeFlashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}


/* ============================================================================
 * 4) PROTECTION CSRF
 * ========================================================================== */

/** Retourne (et crée si besoin) le jeton CSRF courant. */
function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Vérifie qu’un jeton fourni correspond au jeton stocké en session. */
function csrfVerify(?string $token): bool
{
    return !empty($token)
        && !empty($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $token);
}

/** 
 * Rend un <input type="hidden"> avec le jeton CSRF.
 * À mettre dans chaque <form method="post">.
 */
function csrfInput(string $name = '_token'): string
{
    return '<input type="hidden" name="' . escapeHtml($name) . '" value="' . escapeHtml(csrfToken()) . '">';
}

/** Vérifie directement le token dans $_POST[$name]. */
function csrfCheck(string $name = '_token'): bool
{
    return csrfVerify($_POST[$name] ?? null);
}


/* ============================================================================
 * 5) UTILITAIRES
 * ========================================================================== */

function isPost(): bool
{
    return (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST');
}

function validateEmail(string $email): bool
{
    return (bool) filter_var($email, \FILTER_VALIDATE_EMAIL);
}


/* ============================================================================
 * 6) EMAILING (SMTP PHPMailer + logs DEV + mail() fallback)
 * ========================================================================== *
 * Hypothèses de config (optionnelles) dans config/_config.local.php :
 *   - SMTP_HOST, SMTP_PORT, SMTP_SECURE ('tls'|'ssl'|''), SMTP_USER, SMTP_PASS
 *   - SMTP_FROM, SMTP_FROM_NAME
 *   - BASE_URL (utilisé pour fallback d’adresse From)
 * 
 * Arborescence PHPMailer tolérée :
 *   - PROJECT_ROOT/libs/phpmailer/{PHPMailer.php,SMTP.php,Exception.php}
 *   - ou PROJECT_ROOT/lib/PHPMailer/{PHPMailer.php,SMTP.php,Exception.php}
 * ========================================================================== */

/**
 * Envoie un e-mail via PHPMailer (SMTP).
 * @return array{0:bool,1:string} [ok, erreur]
 */
function sendMailSmtp(
    string $to,
    string $subject,
    string $htmlBody,
    string $textBody = '',
    ?string $from = null,
    ?string $fromName = null
): array {
    // 1) Localise PHPMailer
    $projectRoot  = dirname(__DIR__); // …/project
    $phpMailerDir = is_dir($projectRoot . '/libs/phpmailer')
        ? $projectRoot . '/libs/phpmailer'
        : $projectRoot . '/lib/PHPMailer';

    $hasMailer = is_file($phpMailerDir . '/PHPMailer.php')
        && is_file($phpMailerDir . '/SMTP.php')
        && is_file($phpMailerDir . '/Exception.php');

    if (!$hasMailer) {
        return [false, 'PHPMailer introuvable dans ' . $phpMailerDir];
    }

    require_once $phpMailerDir . '/PHPMailer.php';
    require_once $phpMailerDir . '/SMTP.php';
    require_once $phpMailerDir . '/Exception.php';

    // 2) Lit la config en toute sécurité
    $smtpHost   = \defined('SMTP_HOST')      ? (string) \constant('SMTP_HOST')      : '';
    $smtpPort   = \defined('SMTP_PORT')      ? (int)    \constant('SMTP_PORT')      : 25;
    $smtpSecure = \defined('SMTP_SECURE')    ? (string) \constant('SMTP_SECURE')    : ''; // 'tls'|'ssl'|''
    $smtpUser   = \defined('SMTP_USER')      ? (string) \constant('SMTP_USER')      : '';
    $smtpPass   = \defined('SMTP_PASS')      ? (string) \constant('SMTP_PASS')      : '';

    $defaultFrom     = \defined('SMTP_FROM')      ? (string) \constant('SMTP_FROM')      : '';
    $defaultFromName = \defined('SMTP_FROM_NAME') ? (string) \constant('SMTP_FROM_NAME') : 'Idiri Coaching';

    $fromEmail  = $from     ?? $defaultFrom;
    $senderName = $fromName ?? $defaultFromName;

    // 3) Envoi
    try {
        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->CharSet = 'UTF-8';

        if ($smtpHost && $smtpUser && $smtpPass) {
            $mailer->isSMTP();
            $mailer->Host       = $smtpHost;
            $mailer->Port       = $smtpPort;
            $mailer->SMTPAuth   = true;
            $mailer->Username   = $smtpUser;
            $mailer->Password   = $smtpPass;
            if ($smtpSecure !== '') {
                $mailer->SMTPSecure = $smtpSecure; // 'tls' ou 'ssl'
            }
        } else {
            return [false, 'SMTP non configuré (voir _config.local.php).'];
        }

        // From (fallback si vide)
        if ($fromEmail !== '') {
            $mailer->setFrom($fromEmail, $senderName);
        } else {
            $host     = parse_url((string) (\defined('BASE_URL') ? \constant('BASE_URL') : ''), \PHP_URL_HOST) ?: 'localhost';
            $fallback = 'no-reply@' . $host;
            $mailer->setFrom($fallback, $senderName);
        }

        $mailer->addAddress($to);
        $mailer->Subject = $subject;
        $mailer->isHTML(true);
        $mailer->Body    = $htmlBody;
        $mailer->AltBody = ($textBody !== '') ? $textBody : strip_tags($htmlBody);

        $mailer->send();
        return [true, ''];
    } catch (\Throwable $e) {
        return [false, $e->getMessage()];
    }
}

/**
 * Log d’un “mail” en DEV (si pas d’SMTP).
 * Fichier: PROJECT_ROOT/logs/mail.log
 */
function logMailForDev(array $recipients, string $subject, string $textBody, string $replyTo = ''): bool
{
    $projectRoot = dirname(__DIR__);
    $logDir      = $projectRoot . '/logs';
    $logFile     = $logDir . '/mail.log';

    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }

    $log  = "---- " . date('Y-m-d H:i:s') . " ----\n";
    $log .= 'TO: ' . implode(', ', $recipients) . "\n";
    $log .= 'SUBJECT: ' . $subject . "\n";
    if ($replyTo !== '') {
        $log .= 'REPLY-TO: ' . $replyTo . "\n";
    }
    $log .= "BODY:\n" . $textBody . "\n\n";

    return (bool) @file_put_contents($logFile, $log, \FILE_APPEND);
}

/**
 * Envoi via mail() natif (fallback).
 * ⚠️ Nécessite un MTA fonctionnel sur le serveur.
 */
function sendMailNative(
    array $recipients,
    string $subject,
    string $htmlBody,
    string $textBody,
    string $replyTo = ''
): bool {
    $toHeader = implode(',', $recipients);

    // Boundary multipart/alternative
    $boundary = '=_Boundary_' . md5((string) microtime(true));

    $host     = parse_url((string) (\defined('BASE_URL') ? \constant('BASE_URL') : ''), \PHP_URL_HOST) ?: 'localhost';
    $fromMail = 'no-reply@' . $host;

    $headers   = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'From: Idiri Coaching <' . $fromMail . '>';
    if ($replyTo !== '' && filter_var($replyTo, \FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . $replyTo;
    }
    $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

    // Corps multipart (texte puis HTML)
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


/* ============================================================================
 * 7) ALIASES RÉTRO-COMPATIBLES (snake_case)
 * ========================================================================== */

/* Capitalisation */
function capitalize_words(string $value): string { return capitalizeWords($value); }

/* Échappement */
function e(?string $value): string { return escapeHtml($value); } // ancien nom

/* Flash */
function flash(string $type, string $message): void { addFlash($type, $message); }
function consume_flash(): array { return consumeFlashes(); }
function get_flashes(): array { return consumeFlashes(); }
function getFlashes(): array { return consumeFlashes(); } // alias lisible

/* CSRF */
function csrf_token(): string { return csrfToken(); }
function csrf_verify(?string $t): bool { return csrfVerify($t); }
function csrf_input(string $name = '_token'): string { return csrfInput($name); }
function csrf_check(string $name = '_token'): bool { return csrfCheck($name); }

/* Utils */
function is_post(): bool { return isPost(); }
function validate_email(string $email): bool { return validateEmail($email); }

/* Email */
function send_mail_smtp(
    string $to,
    string $subject,
    string $htmlBody,
    string $textBody = '',
    ?string $from = null,
    ?string $fromName = null
): array {
    return sendMailSmtp($to, $subject, $htmlBody, $textBody, $from, $fromName);
}
function log_mail_for_dev(array $recipients, string $subject, string $textBody, string $replyTo = ''): bool
{
    return logMailForDev($recipients, $subject, $textBody, $replyTo);
}
function send_mail_native(
    array $recipients,
    string $subject,
    string $htmlBody,
    string $textBody,
    string $replyTo = ''
): bool {
    return sendMailNative($recipients, $subject, $htmlBody, $textBody, $replyTo);
}
