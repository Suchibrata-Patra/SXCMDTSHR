-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Feb 08, 2026 at 05:58 PM
-- Server version: 11.8.3-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u955994755_SXC_MDTS`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_audit_log`
--

CREATE TABLE `admin_audit_log` (
  `id` int(11) UNSIGNED NOT NULL,
  `admin_email` varchar(255) NOT NULL,
  `action` varchar(100) NOT NULL,
  `target_user` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_audit_log`
--

INSERT INTO `admin_audit_log` (`id`, `admin_email`, `action`, `target_user`, `details`, `ip_address`, `created_at`) VALUES
(1, 'info.official@gmail.com', '', '', NULL, '', '2026-02-08 17:43:46');

-- --------------------------------------------------------

--
-- Table structure for table `attachments`
--

CREATE TABLE `attachments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `file_uuid` varchar(36) NOT NULL COMMENT 'Unique identifier for this file',
  `file_hash` varchar(64) NOT NULL COMMENT 'SHA256 hash of file content for deduplication',
  `original_filename` varchar(500) NOT NULL,
  `file_extension` varchar(50) DEFAULT NULL,
  `mime_type` varchar(255) DEFAULT NULL,
  `file_size` bigint(20) UNSIGNED NOT NULL COMMENT 'Size in bytes',
  `storage_path` varchar(1000) NOT NULL COMMENT 'Relative path from attachments root',
  `storage_type` enum('local','s3','other') DEFAULT 'local',
  `reference_count` int(11) UNSIGNED DEFAULT 0 COMMENT 'Number of users still accessing this file',
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_accessed` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attachments`
--

INSERT INTO `attachments` (`id`, `file_uuid`, `file_hash`, `original_filename`, `file_extension`, `mime_type`, `file_size`, `storage_path`, `storage_type`, `reference_count`, `uploaded_at`, `last_accessed`) VALUES
(1, 'a2f30b8f-49e6-4060-9edb-6898f24b5d70', '2f6cc4373ddf31d9585a1f69f8729164a26552d1c43f618190648264c97b59cb', 'Resume (1).pdf', 'pdf', 'application/pdf', 43149, '2026/02/a2f30b8f-49e6-4060-9edb-6898f24b5d70.pdf', 'local', 1, '2026-02-08 16:48:28', '2026-02-08 16:48:28'),
(2, 'a1aa7877-b8d4-457f-b8e0-f3177553b026', '5e65df5f330ea7d2b589abda23d469fa840c882cc654bcb9055efc96e70b5172', 'TP_PsP_Flowchart.png', 'png', 'image/png', 58863, '2026/02/a1aa7877-b8d4-457f-b8e0-f3177553b026.png', 'local', 1, '2026-02-08 16:48:40', '2026-02-08 16:48:40'),
(3, '520453c3-3a2f-410d-88f0-2c9897cc5649', '15ebfee264e6e07e3df7fe5755a30d2ad3c523fb9ebb305195e3b926a162c790', 'ChatGPT Image Jan 13, 2026, 11_59_06 PM.png', 'png', 'image/png', 1345205, '2026/02/520453c3-3a2f-410d-88f0-2c9897cc5649.png', 'local', 2, '2026-02-08 16:49:24', '2026-02-08 16:49:59');

-- --------------------------------------------------------

--
-- Table structure for table `attachment_downloads`
--

CREATE TABLE `attachment_downloads` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `attachment_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) UNSIGNED NOT NULL,
  `email_uuid` varchar(36) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `downloaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attachment_metadata`
--

CREATE TABLE `attachment_metadata` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `attachment_id` bigint(20) UNSIGNED NOT NULL COMMENT 'References attachments.id',
  `user_id` int(11) UNSIGNED NOT NULL COMMENT 'User who uploaded this',
  `original_filename` varchar(500) NOT NULL,
  `file_extension` varchar(50) DEFAULT NULL,
  `mime_type` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deletion_queue`
--

CREATE TABLE `deletion_queue` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `item_type` enum('email','attachment') NOT NULL,
  `item_id` bigint(20) UNSIGNED NOT NULL COMMENT 'ID of email or attachment',
  `email_uuid` varchar(36) DEFAULT NULL,
  `imap_uid` int(11) UNSIGNED DEFAULT NULL COMMENT 'IMAP UID to delete from server',
  `imap_mailbox` varchar(255) DEFAULT NULL,
  `user_email` varchar(255) DEFAULT NULL COMMENT 'User who owns the IMAP account',
  `file_path` varchar(1000) DEFAULT NULL COMMENT 'File path to delete for attachments',
  `queued_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `scheduled_for` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When to process this deletion',
  `status` enum('pending','processing','completed','failed') DEFAULT 'pending',
  `attempts` int(11) DEFAULT 0,
  `last_attempt_at` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `emails`
--

CREATE TABLE `emails` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `email_uuid` varchar(36) NOT NULL COMMENT 'Unique identifier for this email',
  `message_id` varchar(500) DEFAULT NULL COMMENT 'Email Message-ID header',
  `imap_uid` int(11) UNSIGNED DEFAULT NULL COMMENT 'IMAP UID for received emails',
  `imap_mailbox` varchar(255) DEFAULT NULL COMMENT 'IMAP mailbox name (INBOX, Sent, etc)',
  `sender_email` varchar(255) NOT NULL,
  `sender_name` varchar(255) DEFAULT NULL,
  `recipient_email` text NOT NULL COMMENT 'Primary recipient (can be multiple, comma-separated)',
  `cc_list` text DEFAULT NULL,
  `bcc_list` text DEFAULT NULL,
  `reply_to` varchar(255) DEFAULT NULL,
  `subject` text NOT NULL,
  `body_text` longtext DEFAULT NULL COMMENT 'Plain text body',
  `body_html` longtext DEFAULT NULL COMMENT 'HTML body',
  `article_title` varchar(500) DEFAULT NULL COMMENT 'For article-based emails',
  `email_type` enum('sent','received','draft') NOT NULL DEFAULT 'received',
  `is_read` tinyint(1) DEFAULT 0,
  `is_starred` tinyint(1) DEFAULT 0,
  `is_important` tinyint(1) DEFAULT 0,
  `has_attachments` tinyint(1) DEFAULT 0,
  `email_date` datetime NOT NULL COMMENT 'Original email date/time',
  `received_at` datetime DEFAULT NULL COMMENT 'When fetched from IMAP',
  `sent_at` datetime DEFAULT NULL COMMENT 'When sent via SMTP',
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_internal` tinyint(1) DEFAULT 0 COMMENT 'Both sender and recipient are internal users',
  `thread_id` varchar(36) DEFAULT NULL COMMENT 'For email threading',
  `in_reply_to` varchar(500) DEFAULT NULL COMMENT 'In-Reply-To header',
  `references` text DEFAULT NULL COMMENT 'References header'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_attachments`
--

CREATE TABLE `email_attachments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `email_id` bigint(20) UNSIGNED NOT NULL,
  `attachment_id` bigint(20) UNSIGNED NOT NULL,
  `attachment_order` int(11) DEFAULT 0 COMMENT 'Order of attachment in email',
  `is_inline` tinyint(1) DEFAULT 0 COMMENT 'Inline/embedded attachment',
  `content_id` varchar(255) DEFAULT NULL COMMENT 'Content-ID for inline images',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inbox_sync_status`
--

CREATE TABLE `inbox_sync_status` (
  `id` int(11) NOT NULL,
  `user_email` varchar(255) NOT NULL,
  `last_sync_date` datetime NOT NULL,
  `last_message_id` varchar(255) DEFAULT NULL,
  `total_messages` int(11) DEFAULT 0,
  `unread_count` int(11) DEFAULT 0,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `labels`
--

CREATE TABLE `labels` (
  `id` int(11) NOT NULL,
  `user_email` varchar(255) DEFAULT NULL,
  `label_name` varchar(100) DEFAULT NULL,
  `label_color` varchar(200) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `labels`
--

INSERT INTO `labels` (`id`, `user_email`, `label_name`, `label_color`, `created_at`) VALUES
(1, 'info.official@holidayseva.com', 'INFOSYS', '#0973dc', NULL),
(2, 'info.official@holidayseva.com', 'HSBC', '#34a853', NULL),
(3, 'info.official@holidayseva.com', 'AMEX', 'RED', NULL),
(4, 'info.official@holidayseva.com', 'WIPRO', '#fbbc04', NULL),
(33, 'info.official@holidayseva.com', 'MMT', '#000000', '2026-02-07 12:56:08');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) UNSIGNED NOT NULL,
  `user_uuid` varchar(36) NOT NULL COMMENT 'Unique identifier for file storage',
  `email` varchar(255) NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_admin` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_attachment_access`
--

CREATE TABLE `user_attachment_access` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) UNSIGNED NOT NULL,
  `attachment_id` bigint(20) UNSIGNED NOT NULL,
  `sender_id` int(11) UNSIGNED NOT NULL COMMENT 'User who uploaded/sent the file',
  `receiver_id` int(11) UNSIGNED DEFAULT NULL COMMENT 'User receiving the file (NULL until sent)',
  `email_uuid` varchar(36) DEFAULT NULL COMMENT 'Links to emails.email_uuid when file is sent',
  `access_type` enum('upload','sent','received') NOT NULL DEFAULT 'upload' COMMENT 'How user got access to this file',
  `email_id` bigint(20) UNSIGNED NOT NULL COMMENT 'Email through which user accessed this attachment',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_email_access`
--

CREATE TABLE `user_email_access` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) UNSIGNED NOT NULL,
  `email_id` bigint(20) UNSIGNED NOT NULL,
  `access_type` enum('sender','recipient','cc','bcc') NOT NULL,
  `label_id` int(11) UNSIGNED DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT 0 COMMENT 'User has deleted this email',
  `deleted_at` datetime DEFAULT NULL,
  `is_archived` tinyint(1) DEFAULT 0,
  `archived_at` datetime DEFAULT NULL,
  `user_read` tinyint(1) DEFAULT 0,
  `user_read_at` datetime DEFAULT NULL,
  `user_starred` tinyint(1) DEFAULT 0,
  `user_important` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `user_email_access`
--
DELIMITER $$
CREATE TRIGGER `trg_email_imap_deletion_queue` AFTER UPDATE ON `user_email_access` FOR EACH ROW BEGIN
  DECLARE total_users INT;
  DECLARE deleted_users INT;
  
  IF OLD.is_deleted = 0 AND NEW.is_deleted = 1 THEN
    -- Count total users with access to this email
    SELECT COUNT(*) INTO total_users
    FROM `user_email_access`
    WHERE `email_id` = NEW.email_id;
    
    -- Count users who have deleted this email
    SELECT COUNT(*) INTO deleted_users
    FROM `user_email_access`
    WHERE `email_id` = NEW.email_id AND `is_deleted` = 1;
    
    -- If all users have deleted it, queue for IMAP deletion
    IF total_users = deleted_users THEN
      INSERT INTO `deletion_queue` 
        (`item_type`, `item_id`, `email_uuid`, `imap_uid`, `imap_mailbox`, `user_email`, `scheduled_for`)
      SELECT 
        'email',
        e.id,
        e.email_uuid,
        e.imap_uid,
        e.imap_mailbox,
        e.sender_email,  -- Or get from user_email_access based on access_type
        DATE_ADD(NOW(), INTERVAL 30 DAY)  -- Schedule IMAP deletion 30 days from now
      FROM `emails` e
      WHERE e.id = NEW.email_id 
        AND e.imap_uid IS NOT NULL  -- Only for IMAP emails
        AND NOT EXISTS (
          SELECT 1 FROM `deletion_queue` 
          WHERE `item_type` = 'email' AND `item_id` = e.id AND `status` IN ('pending', 'processing')
        );
    END IF;
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `user_settings`
--

CREATE TABLE `user_settings` (
  `id` int(11) NOT NULL,
  `user_email` varchar(255) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_settings`
--

INSERT INTO `user_settings` (`id`, `user_email`, `setting_key`, `setting_value`, `created_at`, `updated_at`) VALUES
(1, 'info.official@holidayseva.com', 'display_name', 'Dr. Suchibrata Patra', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(2, 'info.official@holidayseva.com', 'designation', 'Student M.Sc Data Science', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(3, 'info.official@holidayseva.com', 'dept', 'data_science', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(4, 'info.official@holidayseva.com', 'hod_email', 'mail@gmail.com', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(5, 'info.official@holidayseva.com', 'staff_id', '24-500-5-08-0403', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(6, 'info.official@holidayseva.com', 'room_no', '44', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(7, 'info.official@holidayseva.com', 'ext_no', '', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(8, 'info.official@holidayseva.com', 'auto_bcc_hod', 'false', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(9, 'info.official@holidayseva.com', 'archive_sent', 'true', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(10, 'info.official@holidayseva.com', 'mandatory_subject', 'false', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(11, 'info.official@holidayseva.com', 'auto_label_sent', 'false', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(12, 'info.official@holidayseva.com', 'priority_level', 'normal', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(13, 'info.official@holidayseva.com', 'attach_size_limit', '25', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(14, 'info.official@holidayseva.com', 'undo_send_delay', '10', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(15, 'info.official@holidayseva.com', 'font_family', 'Inter', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(16, 'info.official@holidayseva.com', 'font_size', '14', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(17, 'info.official@holidayseva.com', 'spell_check', 'false', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(18, 'info.official@holidayseva.com', 'auto_correct', 'false', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(19, 'info.official@holidayseva.com', 'rich_text', 'false', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(20, 'info.official@holidayseva.com', 'default_cc', '', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(21, 'info.official@holidayseva.com', 'default_bcc', '', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(22, 'info.official@holidayseva.com', 'signature', '', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(23, 'info.official@holidayseva.com', 'density', 'relaxed', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(24, 'info.official@holidayseva.com', 'dark_mode', 'auto', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(25, 'info.official@holidayseva.com', 'blur_effects', 'true', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(26, 'info.official@holidayseva.com', 'show_avatars', 'true', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(27, 'info.official@holidayseva.com', 'anim_speed', 'normal', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(28, 'info.official@holidayseva.com', 'font_weight', 'medium', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(29, 'info.official@holidayseva.com', 'push_notif', 'true', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(30, 'info.official@holidayseva.com', 'browser_notif', 'true', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(31, 'info.official@holidayseva.com', 'sound_alerts', 'tink', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(32, 'info.official@holidayseva.com', 'activity_report', 'weekly', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(33, 'info.official@holidayseva.com', 'session_timeout', '60', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(34, 'info.official@holidayseva.com', 'read_receipts', 'true', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(35, 'info.official@holidayseva.com', 'smart_reply', 'false', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(36, 'info.official@holidayseva.com', 'compact_mode', 'false', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(37, 'info.official@holidayseva.com', 'two_factor', 'true', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(38, 'info.official@holidayseva.com', 'ip_lock', 'false', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(39, 'info.official@holidayseva.com', 'debug_logs', 'true', '2026-02-08 06:17:16', '2026-02-08 11:08:26'),
(352, 'info.official@holidayseva.com', 'imap_server', 'imap.hostinger.com', '2026-02-08 10:56:20', '2026-02-08 11:08:26'),
(353, 'info.official@holidayseva.com', 'imap_port', '993', '2026-02-08 10:56:20', '2026-02-08 11:08:26'),
(354, 'info.official@holidayseva.com', 'imap_encryption', 'ssl', '2026-02-08 10:56:21', '2026-02-08 11:08:26'),
(355, 'info.official@holidayseva.com', 'imap_username', 'info.official@holidayseva.com', '2026-02-08 10:56:21', '2026-02-08 11:08:26'),
(356, 'info.official@holidayseva.com', 'settings_locked', 'true', '2026-02-08 10:56:21', '2026-02-08 11:08:26');

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_user_attachments`
-- (See below for the actual view)
--
CREATE TABLE `v_user_attachments` (
`access_id` bigint(20) unsigned
,`user_id` int(11) unsigned
,`sender_id` int(11) unsigned
,`receiver_id` int(11) unsigned
,`email_uuid` varchar(36)
,`email_id` bigint(20) unsigned
,`access_type` enum('upload','sent','received')
,`attachment_id` bigint(20) unsigned
,`file_uuid` varchar(36)
,`file_hash` varchar(64)
,`storage_path` varchar(1000)
,`file_size` bigint(20) unsigned
,`uploaded_at` timestamp
,`original_filename` varchar(500)
,`file_extension` varchar(50)
,`mime_type` varchar(255)
,`sender_email` varchar(255)
,`sender_name` varchar(255)
,`receiver_email` varchar(255)
,`receiver_name` varchar(255)
,`email_subject` text
,`email_sent_at` datetime
);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_audit_log`
--
ALTER TABLE `admin_audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_email` (`admin_email`),
  ADD KEY `idx_target_user` (`target_user`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `attachments`
--
ALTER TABLE `attachments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `file_uuid` (`file_uuid`),
  ADD UNIQUE KEY `unique_file_uuid` (`file_uuid`),
  ADD UNIQUE KEY `unique_file_hash` (`file_hash`),
  ADD KEY `idx_file_hash` (`file_hash`),
  ADD KEY `idx_reference_count` (`reference_count`);

--
-- Indexes for table `attachment_downloads`
--
ALTER TABLE `attachment_downloads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_attachment` (`attachment_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_email_uuid` (`email_uuid`),
  ADD KEY `idx_downloaded_at` (`downloaded_at`);

--
-- Indexes for table `attachment_metadata`
--
ALTER TABLE `attachment_metadata`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_attachment_user` (`attachment_id`,`user_id`),
  ADD KEY `idx_attachment` (`attachment_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `deletion_queue`
--
ALTER TABLE `deletion_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_item_type` (`item_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_scheduled_for` (`scheduled_for`),
  ADD KEY `idx_imap_uid` (`imap_uid`),
  ADD KEY `idx_queue_process` (`status`,`scheduled_for`);

--
-- Indexes for table `emails`
--
ALTER TABLE `emails`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email_uuid` (`email_uuid`),
  ADD UNIQUE KEY `unique_email_uuid` (`email_uuid`),
  ADD KEY `idx_message_id` (`message_id`),
  ADD KEY `idx_imap_uid` (`imap_uid`),
  ADD KEY `idx_sender_email` (`sender_email`),
  ADD KEY `idx_email_type` (`email_type`),
  ADD KEY `idx_email_date` (`email_date` DESC),
  ADD KEY `idx_is_internal` (`is_internal`),
  ADD KEY `idx_thread_id` (`thread_id`),
  ADD KEY `idx_has_attachments` (`has_attachments`),
  ADD KEY `idx_emails_type_date` (`email_type`,`email_date` DESC),
  ADD KEY `idx_emails_sender_date` (`sender_email`,`email_date` DESC);

--
-- Indexes for table `email_attachments`
--
ALTER TABLE `email_attachments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_email_attachment` (`email_id`,`attachment_id`),
  ADD KEY `idx_email_id` (`email_id`),
  ADD KEY `idx_attachment_id` (`attachment_id`);

--
-- Indexes for table `inbox_sync_status`
--
ALTER TABLE `inbox_sync_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_email` (`user_email`),
  ADD KEY `idx_user_email` (`user_email`),
  ADD KEY `idx_last_sync` (`last_sync_date`);

--
-- Indexes for table `labels`
--
ALTER TABLE `labels`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_uuid` (`user_uuid`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `unique_user_uuid` (`user_uuid`),
  ADD UNIQUE KEY `unique_email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_user_uuid` (`user_uuid`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `user_attachment_access`
--
ALTER TABLE `user_attachment_access`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_attachment_email` (`user_id`,`attachment_id`,`email_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_attachment_id` (`attachment_id`),
  ADD KEY `idx_email_id` (`email_id`),
  ADD KEY `idx_uaa_user_deleted` (`user_id`),
  ADD KEY `idx_sender` (`sender_id`),
  ADD KEY `idx_receiver` (`receiver_id`),
  ADD KEY `idx_email_uuid` (`email_uuid`),
  ADD KEY `idx_sender_attachment` (`sender_id`,`attachment_id`),
  ADD KEY `idx_receiver_attachment` (`receiver_id`,`attachment_id`),
  ADD KEY `idx_email_attachment` (`email_uuid`,`attachment_id`);

--
-- Indexes for table `user_email_access`
--
ALTER TABLE `user_email_access`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_email` (`user_id`,`email_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_email_id` (`email_id`),
  ADD KEY `idx_access_type` (`access_type`),
  ADD KEY `idx_is_deleted` (`is_deleted`),
  ADD KEY `idx_label_id` (`label_id`),
  ADD KEY `idx_user_deleted` (`user_id`,`is_deleted`,`email_id`),
  ADD KEY `idx_uea_user_deleted` (`user_id`,`is_deleted`,`email_id`);

--
-- Indexes for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_setting` (`user_email`,`setting_key`),
  ADD KEY `idx_user_email` (`user_email`),
  ADD KEY `idx_setting_key` (`setting_key`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_audit_log`
--
ALTER TABLE `admin_audit_log`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `attachments`
--
ALTER TABLE `attachments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `attachment_downloads`
--
ALTER TABLE `attachment_downloads`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attachment_metadata`
--
ALTER TABLE `attachment_metadata`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `deletion_queue`
--
ALTER TABLE `deletion_queue`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `emails`
--
ALTER TABLE `emails`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_attachments`
--
ALTER TABLE `email_attachments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inbox_sync_status`
--
ALTER TABLE `inbox_sync_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `labels`
--
ALTER TABLE `labels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_attachment_access`
--
ALTER TABLE `user_attachment_access`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_email_access`
--
ALTER TABLE `user_email_access`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_settings`
--
ALTER TABLE `user_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=484;

-- --------------------------------------------------------

--
-- Structure for view `v_user_attachments`
--
DROP TABLE IF EXISTS `v_user_attachments`;

CREATE ALGORITHM=UNDEFINED DEFINER=`u955994755_DB_supremacy`@`127.0.0.1` SQL SECURITY DEFINER VIEW `v_user_attachments`  AS SELECT `uaa`.`id` AS `access_id`, `uaa`.`user_id` AS `user_id`, `uaa`.`sender_id` AS `sender_id`, `uaa`.`receiver_id` AS `receiver_id`, `uaa`.`email_uuid` AS `email_uuid`, `uaa`.`email_id` AS `email_id`, `uaa`.`access_type` AS `access_type`, `a`.`id` AS `attachment_id`, `a`.`file_uuid` AS `file_uuid`, `a`.`file_hash` AS `file_hash`, `a`.`storage_path` AS `storage_path`, `a`.`file_size` AS `file_size`, `a`.`uploaded_at` AS `uploaded_at`, `am`.`original_filename` AS `original_filename`, `am`.`file_extension` AS `file_extension`, `am`.`mime_type` AS `mime_type`, `us`.`email` AS `sender_email`, `us`.`full_name` AS `sender_name`, `ur`.`email` AS `receiver_email`, `ur`.`full_name` AS `receiver_name`, `e`.`subject` AS `email_subject`, `e`.`sent_at` AS `email_sent_at` FROM (((((`user_attachment_access` `uaa` join `attachments` `a` on(`uaa`.`attachment_id` = `a`.`id`)) left join `attachment_metadata` `am` on(`a`.`id` = `am`.`attachment_id` and `uaa`.`sender_id` = `am`.`user_id`)) left join `users` `us` on(`uaa`.`sender_id` = `us`.`id`)) left join `users` `ur` on(`uaa`.`receiver_id` = `ur`.`id`)) left join `emails` `e` on(`uaa`.`email_uuid` = `e`.`email_uuid`)) ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attachment_downloads`
--
ALTER TABLE `attachment_downloads`
  ADD CONSTRAINT `fk_ad_attachment` FOREIGN KEY (`attachment_id`) REFERENCES `attachments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ad_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attachment_metadata`
--
ALTER TABLE `attachment_metadata`
  ADD CONSTRAINT `fk_am_attachment` FOREIGN KEY (`attachment_id`) REFERENCES `attachments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_am_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `email_attachments`
--
ALTER TABLE `email_attachments`
  ADD CONSTRAINT `fk_ea_attachment` FOREIGN KEY (`attachment_id`) REFERENCES `attachments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ea_email` FOREIGN KEY (`email_id`) REFERENCES `emails` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_attachment_access`
--
ALTER TABLE `user_attachment_access`
  ADD CONSTRAINT `fk_uaa_attachment` FOREIGN KEY (`attachment_id`) REFERENCES `attachments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_uaa_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_uaa_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_uaa_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_email_access`
--
ALTER TABLE `user_email_access`
  ADD CONSTRAINT `fk_uea_email` FOREIGN KEY (`email_id`) REFERENCES `emails` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_uea_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
