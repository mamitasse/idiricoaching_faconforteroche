<?php
/**
 * ------------------------------------------------------------
 * FICHIER : database/migration.php
 * ------------------------------------------------------------
 * Objectif :
 *   - Créer la base coaching_db si elle n'existe pas
 *   - Créer les tables : users, slots, reservations
 *   - NE CRÉE AUCUNE DONNÉE
 *
 * IMPORTANT :
 *   Les données de test (coachs, adhérents, créneaux...)
 *   seront gérées dans fixtures.php
 * ------------------------------------------------------------
 */

declare(strict_types=1);

require __DIR__ . '/../config/_config.php';

date_default_timezone_set('Europe/Paris');

function out(string $msg): void {
    if (PHP_SAPI === 'cli') echo $msg . PHP_EOL;
    else echo "<pre style='margin:0'>" . htmlspecialchars($msg) . "</pre>";
}

/* ------------------------------------------------------------
 * Connexion au serveur MySQL
 * ------------------------------------------------------------ */
try {
    $dsn = 'mysql:host=' . DB_HOST . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    out("Connexion MySQL : OK");
} catch (PDOException $e) {
    out("❌ ERREUR connexion MySQL : " . $e->getMessage());
    exit;
}

/* ------------------------------------------------------------
 * Création de la base coaching_db
 * ------------------------------------------------------------ */
try {
    $pdo->exec("
        CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "`
        CHARACTER SET utf8mb4
        COLLATE utf8mb4_general_ci
    ");
    out("Base " . DB_NAME . " créée ou déjà existante.");

    $pdo->exec("USE `" . DB_NAME . "`");
    out("Base sélectionnée.");
} catch (PDOException $e) {
    out("❌ ERREUR création base : " . $e->getMessage());
    exit;
}

function runSql(PDO $pdo, string $label, string $sql): void {
    out("---- $label ----");
    try {
        $pdo->exec($sql);
        out("OK");
    } catch (PDOException $e) {
        out("❌ ERREUR $label : " . $e->getMessage());
    }
}

/* ------------------------------------------------------------
 * TABLE users
 * ------------------------------------------------------------ */
$sqlUsers = <<<SQL
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name`  VARCHAR(100) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,

  `reset_token` VARCHAR(64) DEFAULT NULL,
  `reset_expires_at` DATETIME DEFAULT NULL,

  `phone` VARCHAR(30) DEFAULT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  `age` TINYINT(3) UNSIGNED DEFAULT NULL,

  `gender` ENUM('female','male') DEFAULT NULL,

  `role` ENUM('adherent','coach') NOT NULL DEFAULT 'adherent',

  `coach_id` INT UNSIGNED DEFAULT NULL,

  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_email` (`email`),

  KEY `idx_users_role` (`role`),
  KEY `idx_users_coach_id` (`coach_id`),

  CONSTRAINT `fk_users_coach`
    FOREIGN KEY (`coach_id`)
    REFERENCES `users`(`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB;
SQL;

runSql($pdo, "Création table users", $sqlUsers);

/* ------------------------------------------------------------
 * TABLE slots
 * ------------------------------------------------------------ */
$sqlSlots = <<<SQL
CREATE TABLE IF NOT EXISTS `slots` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `coach_id` INT UNSIGNED NOT NULL,
  `date` DATE NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,

  `status` ENUM('available','reserved','unavailable')
           NOT NULL DEFAULT 'available',

  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_slots_coach_date` (`coach_id`, `date`),

  CONSTRAINT `fk_slots_coach`
    FOREIGN KEY (`coach_id`)
    REFERENCES `users`(`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;
SQL;

runSql($pdo, "Création table slots", $sqlSlots);

/* ------------------------------------------------------------
 * TABLE reservations
 * ------------------------------------------------------------ */
$sqlReservations = <<<SQL
CREATE TABLE IF NOT EXISTS `reservations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,

  `adherent_id` INT UNSIGNED NOT NULL,
  `coach_id` INT UNSIGNED NOT NULL,
  `slot_id` INT UNSIGNED NOT NULL,

  `status` ENUM('pending','confirmed','cancelled')
           NOT NULL DEFAULT 'pending',

  `paid` TINYINT(1) NOT NULL DEFAULT 0,

  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  KEY `idx_res_adherent` (`adherent_id`),
  KEY `idx_res_coach` (`coach_id`),
  KEY `idx_res_slot` (`slot_id`),

  CONSTRAINT `fk_res_adherent`
    FOREIGN KEY (`adherent_id`)
    REFERENCES `users`(`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,

  CONSTRAINT `fk_res_coach`
    FOREIGN KEY (`coach_id`)
    REFERENCES `users`(`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,

  CONSTRAINT `fk_res_slot`
    FOREIGN KEY (`slot_id`)
    REFERENCES `slots`(`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;
SQL;

runSql($pdo, "Création table reservations", $sqlReservations);

out("🎉 Migration terminée avec succès (structure uniquement).");
