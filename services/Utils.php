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
