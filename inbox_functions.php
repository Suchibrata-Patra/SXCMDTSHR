<?php
/**
 * Enhanced Inbox Functions - OPTIMIZED VERSION
 * Supports body_preview for fast queries
 * New vs unread email distinction
 */
require_once __DIR__ . '/security_handler.php';
require_once 'db_config.php';

/**
 * Save inbox message to database - WITH BODY PREVIEW
 */
function saveInboxMessage($messageData) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;
        
        // Generate preview if not provided
        $bodyPreview = $messageData['body_preview'] ?? substr($messageData['body'] ?? '', 0, 500);
        
        $stmt = $pdo->prepare("
            INSERT INTO inbox_messages (
                message_id, user_email, sender_email, sender_name,
                subject, body, body_preview, received_date, fetched_at,
                has_attachments, attachment_data, is_read
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, 0)
        ");
        
        return $stmt->execute([
            $messageData['message_id'],
            $messageData['user_email'],
            $messageData['sender_email'],
            $messageData['sender_name'] ?? null,
            $messageData['subject'],
            $messageData['body'],
            $bodyPreview,
            $messageData['received_date'],
            $messageData['has_attachments'] ?? 0,
            $messageData['attachment_data'] ?? null
        ]);
        
    } catch (PDOException $e) {
        error_log("Error saving inbox message: " . $e->getMessage());
        return false;
    }
}

/**
 * Get inbox messages with filters - OPTIMIZED VERSION
 * Uses body_preview instead of full body
 */
function getInboxMessages($userEmail, $limit = 50, $offset = 0, $filters = []) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return [];
        
        $sql = "SELECT 
                    id, message_id, sender_email, sender_name, 
                    subject, body_preview, received_date, fetched_at,
                    is_read, read_at, has_attachments, attachment_data,
                    is_starred, is_important,
                    CASE 
                        WHEN is_read = 0 AND TIMESTAMPDIFF(MINUTE, fetched_at, NOW()) <= 5 
                        THEN 1 
                        ELSE 0 
                    END as is_new
                FROM inbox_messages 
                WHERE user_email = :email 
                AND is_deleted = 0
                AND subject != 'Login Verification'";
        
        $params = [':email' => $userEmail];
        
        // Apply filters
        if (!empty($filters['search'])) {
            $sql .= " AND (subject LIKE :search OR sender_email LIKE :search OR body_preview LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        if (!empty($filters['unread_only'])) {
            $sql .= " AND is_read = 0";
        }
        
        if (!empty($filters['starred_only'])) {
            $sql .= " AND is_starred = 1";
        }
        
        if (!empty($filters['new_only'])) {
            $sql .= " AND is_read = 0 AND TIMESTAMPDIFF(MINUTE, fetched_at, NOW()) <= 5";
        }
        
        $sql .= " ORDER BY received_date DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        
        // Bind parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error getting inbox messages: " . $e->getMessage());
        return [];
    }
}

/**
 * Get single message with FULL body (for viewing)
 */
function getInboxMessageById($messageId, $userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return null;
        
        $stmt = $pdo->prepare("
            SELECT * FROM inbox_messages 
            WHERE id = :id AND user_email = :email AND is_deleted = 0
        ");
        
        $stmt->execute([
            ':id' => $messageId,
            ':email' => $userEmail
        ]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error getting message by ID: " . $e->getMessage());
        return null;
    }
}

/**
 * Get total message count
 */
function getInboxMessageCount($userEmail, $filters = []) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return 0;
        
        $sql = "SELECT COUNT(*) as count 
                FROM inbox_messages 
                WHERE user_email = :email 
                AND is_deleted = 0
                AND subject != 'Login Verification'";
        
        $params = [':email' => $userEmail];
        
        if (!empty($filters['search'])) {
            $sql .= " AND (subject LIKE :search OR sender_email LIKE :search OR body_preview LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        if (!empty($filters['unread_only'])) {
            $sql .= " AND is_read = 0";
        }
        
        if (!empty($filters['starred_only'])) {
            $sql .= " AND is_starred = 1";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
        
    } catch (PDOException $e) {
        error_log("Error getting message count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get unread count - OPTIMIZED with covering index
 */
function getUnreadCount($userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return 0;
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM inbox_messages 
            WHERE user_email = :email 
            AND is_read = 0 
            AND is_deleted = 0
            AND subject != 'Login Verification'
        ");
        
        $stmt->execute([':email' => $userEmail]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
        
    } catch (PDOException $e) {
        error_log("Error getting unread count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get new messages count (fetched in last 5 minutes)
 */
function getNewCount($userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return 0;
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM inbox_messages 
            WHERE user_email = :email 
            AND is_read = 0
            AND TIMESTAMPDIFF(MINUTE, fetched_at, NOW()) <= 5
            AND is_deleted = 0
            AND subject != 'Login Verification'
        ");
        
        $stmt->execute([':email' => $userEmail]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
        
    } catch (PDOException $e) {
        error_log("Error getting new count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Mark message as read
 */
function markMessageAsRead($messageId, $userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;
        
        $stmt = $pdo->prepare("
            UPDATE inbox_messages 
            SET is_read = 1, read_at = NOW() 
            WHERE id = :id AND user_email = :email
        ");
        
        return $stmt->execute([
            ':id' => $messageId,
            ':email' => $userEmail
        ]);
        
    } catch (PDOException $e) {
        error_log("Error marking message as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Mark message as unread
 */
function markMessageAsUnread($messageId, $userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;
        
        $stmt = $pdo->prepare("
            UPDATE inbox_messages 
            SET is_read = 0, read_at = NULL 
            WHERE id = :id AND user_email = :email
        ");
        
        return $stmt->execute([
            ':id' => $messageId,
            ':email' => $userEmail
        ]);
        
    } catch (PDOException $e) {
        error_log("Error marking message as unread: " . $e->getMessage());
        return false;
    }
}

/**
 * Toggle star on message
 */
function toggleStarMessage($messageId, $userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;
        
        $stmt = $pdo->prepare("
            UPDATE inbox_messages 
            SET is_starred = NOT is_starred 
            WHERE id = :id AND user_email = :email
        ");
        
        return $stmt->execute([
            ':id' => $messageId,
            ':email' => $userEmail
        ]);
        
    } catch (PDOException $e) {
        error_log("Error toggling star: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete inbox message (soft delete)
 */
function deleteInboxMessage($messageId, $userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;
        
        $stmt = $pdo->prepare("
            UPDATE inbox_messages 
            SET is_deleted = 1, deleted_at = NOW() 
            WHERE id = :id AND user_email = :email
        ");
        
        return $stmt->execute([
            ':id' => $messageId,
            ':email' => $userEmail
        ]);
        
    } catch (PDOException $e) {
        error_log("Error deleting message: " . $e->getMessage());
        return false;
    }
}

/**
 * Get last sync date
 */
function getLastSyncDate($userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return null;
        
        $stmt = $pdo->prepare("
            SELECT MAX(fetched_at) as last_sync 
            FROM inbox_messages 
            WHERE user_email = :email
        ");
        
        $stmt->execute([':email' => $userEmail]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['last_sync'] ?? null;
        
    } catch (PDOException $e) {
        error_log("Error getting last sync date: " . $e->getMessage());
        return null;
    }
}

/**
 * Update last sync date
 */
function updateLastSyncDate($userEmail, $lastMessageId = null) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;
        
        // Get or create inbox sync status
        $stmt = $pdo->prepare("
            INSERT INTO inbox_sync_status (user_email, last_sync_date, last_message_id)
            VALUES (:email, NOW(), :message_id)
            ON DUPLICATE KEY UPDATE 
                last_sync_date = NOW(),
                last_message_id = :message_id
        ");
        
        return $stmt->execute([
            ':email' => $userEmail,
            ':message_id' => $lastMessageId
        ]);
        
    } catch (PDOException $e) {
        error_log("Error updating last sync date: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if message exists in database
 */
function messageExists($userEmail, $messageId) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;
        
        $stmt = $pdo->prepare("SELECT id FROM inbox_messages WHERE user_email = :email AND message_id = :message_id");
        $stmt->execute([':email' => $userEmail, ':message_id' => $messageId]);
        
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        error_log("Error checking message existence: " . $e->getMessage());
        return false;
    }
}

/**
 * Clear all inbox messages for user (for force refresh)
 */
function clearInboxMessages($userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;
        
        $stmt = $pdo->prepare("DELETE FROM inbox_messages WHERE user_email = :email");
        return $stmt->execute([':email' => $userEmail]);
    } catch (Exception $e) {
        error_log("Error clearing inbox messages: " . $e->getMessage());
        return false;
    }
}

/**
 * Get Login Verification messages for activity log
 * NOTE: This function retrieves only "Login Verification" emails
 * These are excluded from the regular inbox view
 */
function getLoginVerificationMessages($userEmail, $limit = 50, $offset = 0) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return [];
        
        $sql = "SELECT 
                    id, message_id, sender_email, sender_name, 
                    subject, body_preview, received_date, fetched_at,
                    is_read, read_at
                FROM inbox_messages 
                WHERE user_email = :email 
                AND subject = 'Login Verification'
                AND is_deleted = 0
                ORDER BY received_date DESC 
                LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':email', $userEmail, PDO::PARAM_STR);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error getting login verification messages: " . $e->getMessage());
        return [];
    }
}

/**
 * Get count of Login Verification messages
 */
function getLoginVerificationCount($userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return 0;
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM inbox_messages 
            WHERE user_email = :email 
            AND subject = 'Login Verification'
            AND is_deleted = 0
        ");
        
        $stmt->execute([':email' => $userEmail]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
        
    } catch (PDOException $e) {
        error_log("Error getting login verification count: " . $e->getMessage());
        return 0;
    }
}
?>