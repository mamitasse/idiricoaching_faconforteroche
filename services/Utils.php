<?php
declare(strict_types=1);

namespace App\services;

/** Échappe le HTML en toute sécurité */
function e(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

/* -------------------- FLASH MESSAGES -------------------- */

/** Ajoute un message flash dans la session */
function flash(string $type, string $msg): void {
    $_SESSION['flash'][$type][] = $msg;
}

/** Consomme (lit puis vide) tous les messages flash */
function consume_flash(): array {
    $a = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $a;
}

/** Alias utilisé dans certaines vues : renvoie les flashs et les vide */
function get_flashes(): array {
    return consume_flash();
}

/* ------------------------ CSRF -------------------------- */

/** Retourne le token CSRF, le crée s’il n’existe pas */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

/** Vérifie un token CSRF reçu (POST/GET) */
function csrf_verify(?string $t): bool {
    return !empty($t) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}

/** Champ caché <input> contenant le token CSRF, à insérer dans les <form> */
function csrf_input(): string {
    return '<input type="hidden" name="_token" value="'.e(csrf_token()).'">';
}

/**
 * ✅ Alias de compatibilité appelé par tes contrôleurs
 * - Cherche le token sous les deux noms possibles: _token (nouveau) ou csrf (ancien)
 */
function csrf_check(): bool {
    $t = $_POST['_token'] ?? $_POST['csrf'] ?? null;
    return csrf_verify($t);
}
