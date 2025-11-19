/* ============================================================
   FICHIER : migration.sql
   OBJET   : Création STRUCTURELLE de la base coaching_db
   NOTE    : AUCUNE donnée d'exemple n'est insérée ici.
             Les données seront fournies dans fixtures.php
   ============================================================ */

CREATE DATABASE IF NOT EXISTS `coaching_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE `coaching_db`;

/* ============================================================
   TABLE users
   ------------------------------------------------------------
   Contient :
     - les adhérents
     - les coachs

   Structure :
     - email unique
     - mot de passe hashé (password_hash)
     - role : adherent / coach
     - coach_id : adhérent → coach
   ============================================================ */

CREATE TABLE IF NOT EXISTS `users` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `first_name`      VARCHAR(100) NOT NULL,
  `last_name`       VARCHAR(100) NOT NULL,
  `email`           VARCHAR(190) NOT NULL,
  `password_hash`   VARCHAR(255) NOT NULL,

  `reset_token`      VARCHAR(64)  DEFAULT NULL,
  `reset_expires_at` DATETIME     DEFAULT NULL,

  `phone`         VARCHAR(30)  DEFAULT NULL,
  `address`       VARCHAR(255) DEFAULT NULL,
  `age`           TINYINT(3) UNSIGNED DEFAULT NULL,

  `gender`        ENUM('female','male') DEFAULT NULL,

  `role`          ENUM('adherent','coach') NOT NULL DEFAULT 'adherent',

  `coach_id`      INT UNSIGNED DEFAULT NULL,

  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                   ON UPDATE CURRENT_TIMESTAMP,

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

/* ============================================================
   TABLE slots
   ------------------------------------------------------------
   Contient les créneaux proposés par les coachs.
   Chaque créneau = 1 coach + 1 date + 1 plage horaire
   ============================================================ */

CREATE TABLE IF NOT EXISTS `slots` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `coach_id`   INT UNSIGNED NOT NULL,

  `date`       DATE NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time`   TIME NOT NULL,

  `status`     ENUM('available','reserved','unavailable')
                NOT NULL DEFAULT 'available',

  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_slots_coach_date` (`coach_id`, `date`),

  CONSTRAINT `fk_slots_coach`
    FOREIGN KEY (`coach_id`)
    REFERENCES `users`(`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB;

/* ============================================================
   TABLE reservations
   ------------------------------------------------------------
   Relie :
     - un adhérent
     - un coach
     - un créneau

   Status :
     - pending
     - confirmed
     - cancelled

   paid :
     - 0 (non payé)
     - 1 (payé)
   ============================================================ */

CREATE TABLE IF NOT EXISTS `reservations` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,

  `adherent_id` INT UNSIGNED NOT NULL,
  `coach_id`    INT UNSIGNED NOT NULL,
  `slot_id`     INT UNSIGNED NOT NULL,

  `status`      ENUM('pending','confirmed','cancelled')
                 NOT NULL DEFAULT 'pending',

  `paid`        TINYINT(1) NOT NULL DEFAULT 0,

  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                 ON UPDATE CURRENT_TIMESTAMP,

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
