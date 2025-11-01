<?php
/**
 * Helpers applicatifs (version "mentor friendly")
 * -----------------------------------------------
 * - Nommage camelCase pour les fonctions publiques
 * - Aliases rétro-compatibles pour les anciens noms snake_case
 * - Docblocks clairs
 *
 * Fonctions canoniques à utiliser désormais :
 *   - escapeHtml(string|null): string
 *   - addFlash(string $type, string $message): void
 *   - consumeFlashes(): array
 *   - getFlashes(): array            (alias)
 *   - csrfToken(): string
 *   - csrfVerify(?string $token): bool
 *   - csrfInput(string $name = '_token'): string
 *   - csrfCheck(string $name = '_token'): bool
 *   - isPost(): bool
 *   - validateEmail(string $email): bool
 */

declare(strict_types=1);

namespace App\services;


namespace App\services;

/**
 * Met en majuscule la 1ʳᵉ lettre de chaque “mot” (accents OK) et
 * préserve les séparateurs courants (espace, tiret, apostrophe).
 * Ex: "jean-luc d'argenteuil" -> "Jean-Luc D'Argenteuil"
 */
function capitalizeWords(string $value): string
{
    if ($value === '') return '';
    // On découpe en gardant les séparateurs (capturés)
    $parts = preg_split("/([\\s\\-’']+)/u", $value, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) return $value;

    $result = '';
    foreach ($parts as $piece) {
        // Si c'est un séparateur (espace/tiret/apostrophe), on le recolle tel quel
        if (preg_match("/^[\\s\\-’']+$/u", $piece)) {
            $result .= $piece;
            continue;
        }
        // Mot: majuscule 1ʳᵉ lettre, reste en minuscules (respect UTF-8)
        $first = mb_substr($piece, 0, 1, 'UTF-8');
        $rest  = mb_substr($piece, 1, null, 'UTF-8');
        $result .= mb_strtoupper($first, 'UTF-8') . mb_strtolower($rest, 'UTF-8');
    }
    return $result;
}

/** Alias rétro-compat (si jamais) */
function capitalize_words(string $value): string { return capitalizeWords($value); }



/* ============================================================
 * 1) ÉCHAPPEMENT HTML (sécurité XSS)
 * ============================================================ */

/**
 * Échappe une chaîne pour l’affichage HTML.
 */
function escapeHtml(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** ---- Alias rétro-compatibles (ne pas utiliser dans le nouveau code) ---- */
function e(?string $value): string { return escapeHtml($value); } // ancien nom


/* ============================================================
 * 2) FLASH MESSAGES (messages éphémères en session)
 * ============================================================ */

/**
 * Ajoute un message flash dans la session.
 * @param string $type    ex: success|error|info|warning
 * @param string $message le texte du message
 */
function addFlash(string $type, string $message): void
{
    $_SESSION['flash'][$type][] = $message;
}

/**
 * Récupère tous les messages flash puis vide la pile.
 * À appeler une fois dans le layout.
 * @return array<string, string[]>
 */
function consumeFlashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/** ---- Alias rétro-compatibles ---- */
function flash(string $type, string $message): void { addFlash($type, $message); }          // ancien nom
function consume_flash(): array { return consumeFlashes(); }                                 // ancien nom
function get_flashes(): array { return consumeFlashes(); }                                   // très ancien
function getFlashes(): array { return consumeFlashes(); }                                    // alias lisible


/* ============================================================
 * 3) PROTECTION CSRF
 * ============================================================ */

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

/** Rend un <input type="hidden"> avec le jeton CSRF (à mettre dans chaque <form method="post">). */
function csrfInput(string $name = '_token'): string
{
    return '<input type="hidden" name="'.escapeHtml($name).'" value="'.escapeHtml(csrfToken()).'">';
}

/** Vérifie directement le token dans $_POST[$name]. */
function csrfCheck(string $name = '_token'): bool
{
    return csrfVerify($_POST[$name] ?? null);
}

/** ---- Alias rétro-compatibles ---- */
function csrf_token(): string { return csrfToken(); }
function csrf_verify(?string $t): bool { return csrfVerify($t); }
function csrf_input(string $name = '_token'): string { return csrfInput($name); }
function csrf_check(string $name = '_token'): bool { return csrfCheck($name); }


/* ============================================================
 * 4) UTILITAIRES
 * ============================================================ */

/** Retourne true si la requête est un POST. */
function isPost(): bool
{
    return (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST');
}

/** Valide la forme d’un email. */
function validateEmail(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

/** ---- Alias rétro-compatibles ---- */
function is_post(): bool { return isPost(); }
function validate_email(string $email): bool { return validateEmail($email); }
/* =========================================================
/* =========================================================
 * 5) EMAIL (SMTP via PHPMailer, fallback, log en DEV)
 * =========================================================
 *
 * Hypothèses :
 * - Les constantes SMTP_* sont (optionnellement) définies dans config/_config.local.php
 *   SMTP_HOST, SMTP_PORT, SMTP_SECURE ('tls'|'ssl'|''), SMTP_USER, SMTP_PASS,
 *   SMTP_FROM, (optionnel) SMTP_FROM_NAME
 * - La librairie PHPMailer est disponible soit dans:
 *      PROJECT_ROOT/libs/phpmailer
 *   soit dans:
 *      PROJECT_ROOT/lib/PHPMailer
 *   avec les 3 fichiers: PHPMailer.php, SMTP.php, Exception.php
 */

/**
 * Envoie un e-mail via PHPMailer en mode SMTP.
 * Retourne [true, ''] si l’envoi a réussi, sinon [false, 'Message d’erreur'].
 *
 * @return array{0:bool,1:string}
 */
function sendMailSmtp(
    string $to,
    string $subject,
    string $htmlBody,
    string $textBody = '',
    ?string $from = null,
    ?string $fromName = null
): array {
    // 1) Localise la librairie PHPMailer (accepte 2 arborescences possibles)
    $projectRoot  = dirname(__DIR__); // .../projet
    $phpMailerDir = is_dir($projectRoot . '/libs/phpmailer')
        ? $projectRoot . '/libs/phpmailer'
        : $projectRoot . '/lib/PHPMailer';

    $phpMailerOk = is_file($phpMailerDir . '/PHPMailer.php')
        && is_file($phpMailerDir . '/SMTP.php')
        && is_file($phpMailerDir . '/Exception.php');

    if (!$phpMailerOk) {
        return [false, 'PHPMailer introuvable dans ' . $phpMailerDir];
    }

    // 2) Charge PHPMailer
    require_once $phpMailerDir . '/PHPMailer.php';
    require_once $phpMailerDir . '/SMTP.php';
    require_once $phpMailerDir . '/Exception.php';

    // 3) Lit la configuration SMTP de manière sûre (évite les “constante non définie”)
    $smtpHost   = defined('SMTP_HOST')      ? (string) constant('SMTP_HOST')      : '';
    $smtpPort   = defined('SMTP_PORT')      ? (int)    constant('SMTP_PORT')      : 25;
    $smtpSecure = defined('SMTP_SECURE')    ? (string) constant('SMTP_SECURE')    : ''; // 'tls' | 'ssl' | ''
    $smtpUser   = defined('SMTP_USER')      ? (string) constant('SMTP_USER')      : '';
    $smtpPass   = defined('SMTP_PASS')      ? (string) constant('SMTP_PASS')      : '';

    $defaultFrom     = defined('SMTP_FROM')      ? (string) constant('SMTP_FROM')      : '';
    $defaultFromName = defined('SMTP_FROM_NAME') ? (string) constant('SMTP_FROM_NAME') : 'Idiri Coaching';

    $fromEmail  = $from     ?? $defaultFrom;
    $senderName = $fromName ?? $defaultFromName;

    // 4) Configure et envoie
    try {
        $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->CharSet = 'UTF-8';

        // SMTP requis : on vérifie que l’essentiel est présent
        if ($smtpHost && $smtpUser && $smtpPass) {
            $mailer->isSMTP();
            $mailer->Host     = $smtpHost;
            $mailer->Port     = $smtpPort;
            if ($smtpSecure !== '') {
                $mailer->SMTPSecure = $smtpSecure;   // 'tls' ou 'ssl'
            }
            $mailer->SMTPAuth = true;
            $mailer->Username = $smtpUser;
            $mailer->Password = $smtpPass;
        } else {
            return [false, 'SMTP non configuré (voir _config.local.php).'];
        }

        // From : si absent, fallback sur no-reply@<host du site>
        if ($fromEmail !== '') {
            $mailer->setFrom($fromEmail, $senderName);
        } else {
            $host     = parse_url((string) (defined('BASE_URL') ? constant('BASE_URL') : ''), PHP_URL_HOST) ?: 'localhost';
            $fallback = 'no-reply@' . $host;
            $mailer->setFrom($fallback, $senderName);
        }

        // Destinataire
        $mailer->addAddress($to);

        // Contenu
        $mailer->Subject = $subject;
        $mailer->isHTML(true);
        $mailer->Body    = $htmlBody;
        $mailer->AltBody = ($textBody !== '') ? $textBody : strip_tags($htmlBody);

        $mailer->send();
        return [true, ''];
    } catch (\Throwable $exception) {
        return [false, $exception->getMessage()];
    }
}

/** Alias rétro-compatible snake_case (ancien code). */
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

/**
 * Écrit un “mail” dans un fichier de log (utile en DEV si pas d’SMTP).
 * Fichier : PROJECT_ROOT/logs/mail.log
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

    return (bool) @file_put_contents($logFile, $log, FILE_APPEND);
}

/** Alias rétro-compatible snake_case. */
function log_mail_for_dev(array $recipients, string $subject, string $textBody, string $replyTo = ''): bool
{
    return logMailForDev($recipients, $subject, $textBody, $replyTo);
}

/**
 * Envoi via mail() natif (fallback). Attention : nécessite un MTA sur le serveur.
 */
function sendMailNative(
    array $recipients,
    string $subject,
    string $htmlBody,
    string $textBody,
    string $replyTo = ''
): bool {
    $toHeader = implode(',', $recipients);

    // Boundary unique multipart/alternative
    $boundary = '=_Boundary_' . md5((string) microtime(true));

    $host     = parse_url((string) (defined('BASE_URL') ? constant('BASE_URL') : ''), PHP_URL_HOST) ?: 'localhost';
    $fromMail = 'no-reply@' . $host;

    $headers   = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'From: Idiri Coaching <' . $fromMail . '>';
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
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

/** Alias rétro-compatible snake_case. */
function send_mail_native(
    array $recipients,
    string $subject,
    string $htmlBody,
    string $textBody,
    string $replyTo = ''
): bool {
    return sendMailNative($recipients, $subject, $htmlBody, $textBody, $replyTo);
}
