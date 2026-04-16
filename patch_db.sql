-- ============================================================
--  Happy Paws — DATABASE PATCH
--  Run this if you get "Undefined array key" errors.
--  It safely adds any missing columns to your animals table.
--  Safe to run multiple times (uses IF NOT EXISTS logic).
-- ============================================================

USE `animal_adoption`;

-- Add  sound_file  column (stores custom MP3/OGG filename)
ALTER TABLE `animals`
  ADD COLUMN IF NOT EXISTS `sound_file` VARCHAR(200) DEFAULT NULL
  AFTER `description`;

-- Add  energy  column
ALTER TABLE `animals`
  ADD COLUMN IF NOT EXISTS `energy` ENUM('Low','Medium','High') NOT NULL DEFAULT 'Medium'
  AFTER `size`;

-- Add  good_with  column
ALTER TABLE `animals`
  ADD COLUMN IF NOT EXISTS `good_with` VARCHAR(200) DEFAULT NULL
  AFTER `energy`;

-- Verify columns are there
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'animal_adoption'
  AND TABLE_NAME   = 'animals'
ORDER BY ORDINAL_POSITION;

-- ============================================================
--  FIX: Foreign key error when adding animals
--  Run this if you get "Cannot add or update a child row" error.
--  It checks your actual user ID and fixes any mismatch.
-- ============================================================

-- See what user IDs exist
SELECT id, username FROM users;

-- If your animals table has sample data with wrong user_id, fix it:
-- (Replace 'admin' with your actual username if different)
UPDATE `animals`
SET `user_id` = (SELECT id FROM users WHERE username = 'admin' LIMIT 1)
WHERE `user_id` NOT IN (SELECT id FROM users);

-- Add role column if missing (for existing databases)
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `role` ENUM('admin','customer') NOT NULL DEFAULT 'customer'
  AFTER `password`;

-- Make sure the admin user has admin role
UPDATE `users` SET `role` = 'admin' WHERE `username` = 'admin';

-- Add user_id to adoption_applications if missing
ALTER TABLE `adoption_applications`
  ADD COLUMN IF NOT EXISTS `user_id` INT DEFAULT NULL AFTER `id`;
