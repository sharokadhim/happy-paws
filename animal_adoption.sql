-- ============================================================
--  Happy Paws — Animal Adoption System
--  Database: animal_adoption
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

DROP DATABASE IF EXISTS `animal_adoption`;
CREATE DATABASE `animal_adoption`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE `animal_adoption`;

-- ------------------------------------------------------------
-- users
-- ------------------------------------------------------------
CREATE TABLE `users` (
  `id`         INT          NOT NULL AUTO_INCREMENT,
  `full_name`  VARCHAR(100) NOT NULL,
  `username`   VARCHAR(50)  NOT NULL,
  `email`      VARCHAR(100) NOT NULL,
  `password`   VARCHAR(255) NOT NULL,
  `role`       ENUM('admin','customer') NOT NULL DEFAULT 'customer',
  `created_at` TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email`    (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin account (password: Admin@1234)
-- Customers register themselves via register.php
INSERT INTO `users` (`full_name`,`username`,`email`,`password`,`role`) VALUES
('Admin','admin','admin@happypaws.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','admin');

-- ------------------------------------------------------------
-- animals
-- species options: Dog | Cat | Rabbit | Bird | Parrot | Hamster | Eagle | Other
--
-- sound_file  → relative path inside /sounds/  folder
--              e.g.  dog_bark.mp3
--              LEAVE EMPTY to use the built-in Web-Audio fallback.
--              HOW TO CHANGE A SOUND:
--                1. Drop your MP3/OGG file into the  sounds/  folder.
--                2. Edit the animal row in phpMyAdmin (or via edit_animal.php)
--                   and set  sound_file = 'your_file.mp3'
--                3. Save — done.  The site will play that file on hover.
-- ------------------------------------------------------------
CREATE TABLE `animals` (
  `id`          INT          NOT NULL AUTO_INCREMENT,
  `user_id`     INT          NOT NULL,
  `name`        VARCHAR(100) NOT NULL,
  `species`     ENUM('Dog','Cat','Bird','Parrot','Eagle','Other') NOT NULL DEFAULT 'Dog',
  `breed`       VARCHAR(100) DEFAULT NULL,
  `age`         VARCHAR(50)  NOT NULL,
  `gender`      ENUM('Male','Female') NOT NULL DEFAULT 'Male',
  `size`        ENUM('Small','Medium','Large') NOT NULL DEFAULT 'Medium',
  `energy`      ENUM('Low','Medium','High') NOT NULL DEFAULT 'Medium',
  `good_with`   VARCHAR(200) DEFAULT NULL,   -- comma-separated: kids, dogs, cats, adults
  `description` TEXT,
  `sound_file`  VARCHAR(200) DEFAULT NULL,   -- ← SET YOUR CUSTOM SOUND FILE HERE
  `status`           ENUM('available','pending','adopted') NOT NULL DEFAULT 'available',
  `hourly_rate`        DECIMAL(6,2) NOT NULL DEFAULT 5.00,
  `submission_status` ENUM('approved','awaiting','rejected') NOT NULL DEFAULT 'approved',
  `admin_note`        VARCHAR(255) DEFAULT NULL,
  `submitted_by`      INT DEFAULT NULL,
  `created_at`        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- No sample animals — admin adds animals via the dashboard or customers submit them.

-- ------------------------------------------------------------
-- adoption_applications
-- ------------------------------------------------------------
CREATE TABLE `adoption_applications` (
  `user_id`         INT          DEFAULT NULL,
  `id`              INT          NOT NULL AUTO_INCREMENT,
  `animal_id`       INT          NOT NULL,
  `applicant_name`  VARCHAR(100) NOT NULL,
  `applicant_email` VARCHAR(100) NOT NULL,
  `applicant_phone` VARCHAR(30)  DEFAULT NULL,
  `housing_type`    VARCHAR(50)  DEFAULT NULL,
  `has_children`    VARCHAR(30)  DEFAULT NULL,
  `other_pets`      VARCHAR(100) DEFAULT NULL,
  `experience`      VARCHAR(50)  DEFAULT NULL,
  `reason`          TEXT,
  `status`          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at`      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `animal_id` (`animal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- bookings (customer hourly reservations)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bookings` (
  `id`           INT          NOT NULL AUTO_INCREMENT,
  `user_id`      INT          NOT NULL,
  `animal_id`    INT          NOT NULL,
  `hours`        INT          NOT NULL DEFAULT 1,
  `hourly_rate`  DECIMAL(6,2) NOT NULL,
  `total_cost`   DECIMAL(8,2) NOT NULL,
  `card_last4`   CHAR(4)      DEFAULT NULL,
  `card_name`    VARCHAR(100) DEFAULT NULL,
  `status`          ENUM('pending','confirmed','cancelled','expired') NOT NULL DEFAULT 'pending',
  `booking_ends_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at`      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id`   (`user_id`),
  KEY `animal_id` (`animal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Constraints
ALTER TABLE `animals`
  ADD CONSTRAINT `animals_fk_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE;

ALTER TABLE `adoption_applications`
  ADD CONSTRAINT `apps_fk_animal` FOREIGN KEY (`animal_id`) REFERENCES `animals`(`id`) ON DELETE CASCADE;

COMMIT;

-- ============================================================
-- HOW TO USE CUSTOM SOUNDS
-- ============================================================
-- 1. Add your audio file (MP3 or OGG) to the  sounds/  folder.
-- 2. In phpMyAdmin (or via Edit Animal page), set the
--    sound_file column to the filename, e.g.  my_dog_bark.mp3
-- 3. That animal will now play your custom file on hover.
--    Leave sound_file NULL to use the built-in synthesized sound.
-- ============================================================
