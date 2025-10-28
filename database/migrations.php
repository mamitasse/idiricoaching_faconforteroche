<?php
/**
 * database/integration.php
 * ------------------------------------------------------------
 * Script d'intégration / migration de la base pour IdiriCoaching.
 * - Crée la base si besoin
 * - Crée / ajuste les tables users, slots, reservations
 * - Ajoute index et clés étrangères
 * - Insère 2 coachs (Nadia, Sabrina) si absents
 * - Idempotent : réexécutable sans casser l'existant
 * ------------------------------------------------------------
 */

declare(strict_types=1);

// 1) Charger la config (doit définir DB_HOST, DB_NAME, DB_USER, DB_PASS)
require __DIR__ . '/../config/_config.php';

date_default_timezone_set('Europe/Paris');

function out(string $msg): void {
    if (PHP_SAPI === 'cli') {
        echo $msg . PHP_EOL;
    } else {
        echo '<pre style="margin:0">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . "</pre>\n";
    }
}

// 2) Connexion au serveur MySQL (sans DB pour pouvoir la créer)
$dsnServer = 'mysql:host=' . DB_HOST . ';charset=utf8mb4';
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdoServer = new PDO($dsnServer, DB_USER, DB_PASS, $options);
} catch (Throwable $e) {
    out('[FATAL] Connexion serveur MySQL impossible : ' . $e->getMessage());
    exit(1);
}

// 3) Créer la base si nécessaire
out("-> CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` ...");
$pdoServer->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");

// 4) Connexion à la base
$dsnDb = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
$pdo = new PDO($dsnDb, DB_USER, DB_PASS, $options);

// Helpers introspection (idempotence)
$hasTable = function (string $table) use ($pdo): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?");
    $stmt->execute([DB_NAME, $table]);
    return (bool)$stmt->fetchColumn();
};

$hasColumn = function (string $table, string $column) use ($pdo): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?");
    $stmt->execute([DB_NAME, $table, $column]);
    return (bool)$stmt->fetchColumn();
};

$hasIndex = function (string $table, string $indexName) use ($pdo): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.statistics
        WHERE table_schema = ? AND table_name = ? AND index_name = ?
    ");
    $stmt->execute([DB_NAME, $table, $indexName]);
    return (bool)$stmt->fetchColumn();
};

// ========================
// 1) Table users
// ========================
out("-> Vérifie/Crée table `users` ...");
if (!$hasTable('users')) {
    $pdo->exec("
        CREATE TABLE `users` (
            `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `first_name`    VARCHAR(100) NOT NULL,
            `last_name`     VARCHAR(100) NOT NULL,
            `email`         VARCHAR(190) NOT NULL,
            `password_hash` VARCHAR(255) NOT NULL,
            `phone`         VARCHAR(30)  NULL,
            `address`       VARCHAR(255) NULL,
            `age`           TINYINT UNSIGNED NULL,
            `gender`        ENUM('female','male','other') NULL,
            `role`          ENUM('adherent','coach') NOT NULL DEFAULT 'adherent',
            `coach_id`      INT UNSIGNED NULL,
            `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_users_email` (`email`),
            KEY `idx_users_coach` (`coach_id`),
            CONSTRAINT `fk_users_coach`
                FOREIGN KEY (`coach_id`) REFERENCES `users` (`id`)
                ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} else {
    if (!$hasColumn('users', 'password_hash')) {
        out("   - Ajout colonne users.password_hash ...");
        $pdo->exec("ALTER TABLE `users` ADD `password_hash` VARCHAR(255) NOT NULL AFTER `email`");
    }
    if (!$hasColumn('users', 'role')) {
        out("   - Ajout colonne users.role ...");
        $pdo->exec("ALTER TABLE `users` ADD `role` ENUM('adherent','coach') NOT NULL DEFAULT 'adherent'");
    }
    if (!$hasColumn('users', 'coach_id')) {
        out("   - Ajout colonne users.coach_id ...");
        $pdo->exec("ALTER TABLE `users` ADD `coach_id` INT UNSIGNED NULL");
        try {
            $pdo->exec("ALTER TABLE `users`
                        ADD CONSTRAINT `fk_users_coach`
                        FOREIGN KEY (`coach_id`) REFERENCES `users` (`id`)
                        ON DELETE SET NULL ON UPDATE CASCADE");
        } catch (Throwable $e) { /* déjà là ou données incompatibles */ }
    }
    if (!$hasIndex('users', 'uk_users_email')) {
        try {
            $pdo->exec("ALTER TABLE `users` ADD UNIQUE KEY `uk_users_email` (`email`)");
        } catch (Throwable $e) {
            out('   ! Impossible d’ajouter uk_users_email : ' . $e->getMessage());
        }
    }
}

// ========================
// 2) Table slots
// ========================
out("-> Vérifie/Crée table `slots` ...");
if (!$hasTable('slots')) {
    $pdo->exec("
        CREATE TABLE `slots` (
            `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `coach_id`   INT UNSIGNED NOT NULL,
            `date`       DATE NOT NULL,
            `start_time` TIME NOT NULL,
            `end_time`   TIME NOT NULL,
            `status`     ENUM('available','reserved','unavailable') NOT NULL DEFAULT 'available',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_slots_coach` (`coach_id`),
            CONSTRAINT `fk_slots_coach`
                FOREIGN KEY (`coach_id`) REFERENCES `users` (`id`)
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} else {
    foreach (['date' => 'DATE', 'start_time' => 'TIME', 'end_time' => 'TIME'] as $col => $type) {
        if ($hasColumn('slots', $col)) {
            try {
                $pdo->exec("ALTER TABLE `slots` MODIFY `$col` $type NOT NULL");
            } catch (Throwable $e) {
                // on ignore si des données existantes empêchent la modif
            }
        } else {
            out("   - Ajout colonne slots.$col ...");
            $pdo->exec("ALTER TABLE `slots` ADD `$col` $type NOT NULL");
        }
    }
}
if (!$hasIndex('slots', 'uk_coach_date_time')) {
    out("   - Ajout index unique `uk_coach_date_time` (coach_id, date, start_time) ...");
    try {
        $pdo->exec("ALTER TABLE `slots` ADD UNIQUE KEY `uk_coach_date_time` (`coach_id`, `date`, `start_time`)");
    } catch (Throwable $e) {
        out('   ! Impossible d’ajouter uk_coach_date_time : ' . $e->getMessage());
        out('     (il existe sans doute des doublons à nettoyer dans `slots`)');
    }
}

// ========================
// 3) Table reservations  (⚠️ corrigée : coach_id + updated_at)
// ========================
out("-> Vérifie/Crée table `reservations` ...");
if (!$hasTable('reservations')) {
    $pdo->exec("
        CREATE TABLE `reservations` (
            `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `slot_id`      INT UNSIGNED NOT NULL,
            `adherent_id`  INT UNSIGNED NOT NULL,
            `coach_id`     INT UNSIGNED NOT NULL,
            `status`       ENUM('pending','confirmed','cancelled','coach_cancelled') NOT NULL DEFAULT 'confirmed',
            `paid`         TINYINT(1) NOT NULL DEFAULT 0,
            `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_res_slot`  (`slot_id`),
            KEY `idx_res_adh`   (`adherent_id`),
            KEY `idx_res_coach` (`coach_id`),
            CONSTRAINT `fk_res_slot`
                FOREIGN KEY (`slot_id`) REFERENCES `slots` (`id`)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk_res_adh`
                FOREIGN KEY (`adherent_id`) REFERENCES `users` (`id`)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk_res_coach`
                FOREIGN KEY (`coach_id`) REFERENCES `users` (`id`)
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} else {
    if (!$hasColumn('reservations', 'coach_id')) {
        out("   - Ajout colonne reservations.coach_id ...");
        $pdo->exec("ALTER TABLE `reservations` ADD `coach_id` INT UNSIGNED NOT NULL DEFAULT 0");
        try {
            $pdo->exec("ALTER TABLE `reservations`
                        ADD CONSTRAINT `fk_res_coach`
                        FOREIGN KEY (`coach_id`) REFERENCES `users` (`id`)
                        ON DELETE CASCADE ON UPDATE CASCADE");
        } catch (Throwable $e) { /* ignore si déjà présent */ }
    }
    if (!$hasColumn('reservations', 'updated_at')) {
        out("   - Ajout colonne reservations.updated_at ...");
        $pdo->exec("ALTER TABLE `reservations`
                    ADD `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }
    if (!$hasColumn('reservations', 'status')) {
        $pdo->exec("ALTER TABLE `reservations`
                    ADD `status` ENUM('pending','confirmed','cancelled','coach_cancelled') NOT NULL DEFAULT 'confirmed'");
    }
    if (!$hasColumn('reservations', 'paid')) {
        $pdo->exec("ALTER TABLE `reservations` ADD `paid` TINYINT(1) NOT NULL DEFAULT 0");
    }
    // S’assurer des FKs (si absentes)
    try {
        $pdo->exec("ALTER TABLE `reservations`
                    ADD CONSTRAINT `fk_res_slot`
                    FOREIGN KEY (`slot_id`) REFERENCES `slots` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE");
    } catch (Throwable $e) { /* ignore */ }

    try {
        $pdo->exec("ALTER TABLE `reservations`
                    ADD CONSTRAINT `fk_res_adh`
                    FOREIGN KEY (`adherent_id`) REFERENCES `users` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE");
    } catch (Throwable $e) { /* ignore */ }

    try {
        $pdo->exec("ALTER TABLE `reservations`
                    ADD CONSTRAINT `fk_res_coach`
                    FOREIGN KEY (`coach_id`) REFERENCES `users` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE");
    } catch (Throwable $e) { /* ignore */ }
}

// ========================
// 4) Seed coachs par défaut
// ========================
out("-> Seed des coachs par défaut (si absents) ...");

function ensureCoach(PDO $pdo, string $first, string $last, string $email, ?string $phone = null, ?string $address = null): void {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $id = $stmt->fetchColumn();
    if ($id) return;

    $hash = password_hash('secret', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("
        INSERT INTO users (first_name, last_name, email, password_hash, phone, address, role, created_at, updated_at)
        VALUES (?,?,?,?,?,?, 'coach', NOW(), NOW())
    ");
    $stmt->execute([$first, $last, $email, $hash, $phone, $address]);
}

ensureCoach($pdo, 'Nadia',   'Coach', 'idirinadia10@gmail.com', '0600000001', 'Marne-la-Vallée');
ensureCoach($pdo, 'Sabrina', 'Coach', 'sabrina.idir@gmail.com', '0600000002', 'Paris / 92-95');

out('OK. Intégration terminée ✅');

if (PHP_SAPI !== 'cli') {
    echo '<p style="font-family:system-ui;margin-top:12px">
            <a href="../public/">Retour au site</a>
          </p>';
}
