-- Crée la base et switch dessus
CREATE DATABASE IF NOT EXISTS `idiricoaching` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `idiricoaching`;

-- Table users
CREATE TABLE IF NOT EXISTS `users` (
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
  CONSTRAINT `fk_users_coach` FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table slots
CREATE TABLE IF NOT EXISTS `slots` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `coach_id`   INT UNSIGNED NOT NULL,
  `date`       DATE NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time`   TIME NOT NULL,
  `status`     ENUM('available','reserved','unavailable') NOT NULL DEFAULT 'available',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_coach_date_time` (`coach_id`, `date`, `start_time`),
  KEY `idx_slots_coach` (`coach_id`),
  CONSTRAINT `fk_slots_coach` FOREIGN KEY (`coach_id`) REFERENCES `users`(`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table reservations (inclut coach_id + updated_at)
CREATE TABLE IF NOT EXISTS `reservations` (
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
  CONSTRAINT `fk_res_slot`  FOREIGN KEY (`slot_id`)     REFERENCES `slots`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_res_adh`   FOREIGN KEY (`adherent_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_res_coach` FOREIGN KEY (`coach_id`)    REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed (insère si absents)
INSERT INTO users (first_name,last_name,email,password_hash,phone,address,role,created_at,updated_at)
SELECT 'Nadia','Coach','idirinadia10@gmail.com', '$2y$10$RkA0m7rj2H5WZ8K9r8JvseQaV2zCP1J9mfpS.gy5pE3q2zRrA8p9y','0600000001','Marne-la-Vallée','coach',NOW(),NOW()
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email='idirinadia10@gmail.com');

INSERT INTO users (first_name,last_name,email,password_hash,phone,address,role,created_at,updated_at)
SELECT 'Sabrina','Coach','sabrina.idir@gmail.com', '$2y$10$RkA0m7rj2H5WZ8K9r8JvseQaV2zCP1J9mfpS.gy5pE3q2zRrA8p9y','0600000002','Paris / 92-95','coach',NOW(),NOW()
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email='sabrina.idir@gmail.com');
