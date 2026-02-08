-- =====================================================
-- DATABASE MIGRATION SCRIPT
-- IMAP Settings Refactor with Settings Lock Mechanism
-- =====================================================

-- Create admin audit log table for tracking super admin actions
CREATE TABLE IF NOT EXISTS `admin_audit_log` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_email` VARCHAR(255) NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `target_user` VARCHAR(255) NOT NULL,
  `details` TEXT,
  `ip_address` VARCHAR(45) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_admin_email` (`admin_email`),
  INDEX `idx_target_user` (`target_user`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ensure user_settings table has proper structure
-- (This should already exist, but we're making sure)
CREATE TABLE IF NOT EXISTS `user_settings` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_email` VARCHAR(255) NOT NULL,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_setting` (`user_email`, `setting_key`),
  INDEX `idx_user_email` (`user_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add default IMAP settings for existing users who don't have them
-- This will use Hostinger defaults, but users can change them once
INSERT IGNORE INTO `user_settings` 
  (`user_email`, `setting_key`, `setting_value`, `updated_at`)
SELECT DISTINCT 
  `sender_email` as user_email,
  'imap_server',
  'imap.hostinger.com',
  NOW()
FROM `sent_emails`
WHERE `sender_email` NOT IN (
  SELECT `user_email` FROM `user_settings` WHERE `setting_key` = 'imap_server'
);

INSERT IGNORE INTO `user_settings` 
  (`user_email`, `setting_key`, `setting_value`, `updated_at`)
SELECT DISTINCT 
  `sender_email` as user_email,
  'imap_port',
  '993',
  NOW()
FROM `sent_emails`
WHERE `sender_email` NOT IN (
  SELECT `user_email` FROM `user_settings` WHERE `setting_key` = 'imap_port'
);

INSERT IGNORE INTO `user_settings` 
  (`user_email`, `setting_key`, `setting_value`, `updated_at`)
SELECT DISTINCT 
  `sender_email` as user_email,
  'imap_encryption',
  'ssl',
  NOW()
FROM `sent_emails`
WHERE `sender_email` NOT IN (
  SELECT `user_email` FROM `user_settings` WHERE `setting_key` = 'imap_encryption'
);

INSERT IGNORE INTO `user_settings` 
  (`user_email`, `setting_key`, `setting_value`, `updated_at`)
SELECT DISTINCT 
  `sender_email` as user_email,
  'imap_username',
  `sender_email`,
  NOW()
FROM `sent_emails`
WHERE `sender_email` NOT IN (
  SELECT `user_email` FROM `user_settings` WHERE `setting_key` = 'imap_username'
);

-- Set settings_locked to false for all existing users
-- (They can change settings once, then it will lock)
INSERT IGNORE INTO `user_settings` 
  (`user_email`, `setting_key`, `setting_value`, `updated_at`)
SELECT DISTINCT 
  `sender_email` as user_email,
  'settings_locked',
  'false',
  NOW()
FROM `sent_emails`
WHERE `sender_email` NOT IN (
  SELECT `user_email` FROM `user_settings` WHERE `setting_key` = 'settings_locked'
);

-- =====================================================
-- SUPER ADMIN SETUP (OPTIONAL)
-- =====================================================
-- To make a user a super admin, run this query:
-- 
-- INSERT INTO user_settings (user_email, setting_key, setting_value, updated_at)
-- VALUES ('admin@sxccal.edu', 'is_super_admin', 'true', NOW())
-- ON DUPLICATE KEY UPDATE setting_value = 'true', updated_at = NOW();
-- 
-- Replace 'admin@sxccal.edu' with the actual super admin email
-- =====================================================
