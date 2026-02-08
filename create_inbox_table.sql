-- ==================== INBOX MESSAGES TABLE ====================
-- This table stores received emails fetched from IMAP server
-- Designed to prevent duplicates and support fast queries

CREATE TABLE IF NOT EXISTS inbox_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Message identification
    message_id VARCHAR(255) NOT NULL,
    user_email VARCHAR(255) NOT NULL,
    
    -- Email content
    sender_email VARCHAR(255) NOT NULL,
    sender_name VARCHAR(255) DEFAULT NULL,
    subject TEXT NOT NULL,
    body LONGTEXT NOT NULL,
    
    -- Metadata
    received_date DATETIME NOT NULL,
    fetched_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- Read status
    is_read TINYINT(1) DEFAULT 0,
    read_at DATETIME DEFAULT NULL,
    
    -- Additional flags
    has_attachments TINYINT(1) DEFAULT 0,
    is_starred TINYINT(1) DEFAULT 0,
    is_important TINYINT(1) DEFAULT 0,
    
    -- Soft delete
    is_deleted TINYINT(1) DEFAULT 0,
    deleted_at DATETIME DEFAULT NULL,
    
    -- Indexing for performance
    INDEX idx_user_email (user_email),
    INDEX idx_message_id (message_id),
    INDEX idx_received_date (received_date DESC),
    INDEX idx_is_read (is_read),
    INDEX idx_is_deleted (is_deleted),
    
    -- Prevent duplicate messages
    UNIQUE KEY unique_message (message_id, user_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==================== INDEXES FOR OPTIMIZATION ====================
-- Composite index for main inbox query
CREATE INDEX idx_inbox_main ON inbox_messages (user_email, is_deleted, received_date DESC);

-- Index for unread count queries
CREATE INDEX idx_unread_count ON inbox_messages (user_email, is_read, is_deleted);

-- ==================== LAST FETCH TRACKING TABLE ====================
-- Tracks the last time each user's inbox was synced
CREATE TABLE IF NOT EXISTS inbox_sync_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_email VARCHAR(255) NOT NULL UNIQUE,
    last_sync_date DATETIME NOT NULL,
    last_message_id VARCHAR(255) DEFAULT NULL,
    total_messages INT DEFAULT 0,
    unread_count INT DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_user_email (user_email),
    INDEX idx_last_sync (last_sync_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
