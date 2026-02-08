<?php
/**
 * ENHANCED INBOX FUNCTIONS
 * Database operations for inbox with attachment support
 */

/**
 * Save fetched email message to database with attachment data
 */
function saveInboxMessage($messageData) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            return false;
        }
        
        $sql = "INSERT INTO inbox_messages 
                (message_id, user_email, sender_email, sender_name, subject, 
                 body, received_date, has_attachments, attachment_data, fetched_at) 
                VALUES 
                (:message_id, :user_email, :sender_email, :sender_name, :subject, 
                 :body, :received_date, :has_attachments, :attachment_data, NOW())
                ON DUPLICATE KEY UPDATE
                    body = VALUES(body),
                    has_attachments = VALUES(has_attachments),
                    attachment_data = VALUES(attachment_data),
                    fetched_at = NOW()";
        
        $stmt = $pdo->prepare($sql);
        
        return $stmt->execute([
            ':message_id' => $messageData['message_id'],
            ':user_email' => $messageData['user_email'],
            ':sender_email' => $messageData['sender_email'],
            ':sender_name' => $messageData['sender_name'] ?? '',
            ':subject' => $messageData['subject'],
            ':body' => $messageData['body'],
            ':received_date' => $messageData['received_date'],
            ':has_attachments' => $messageData['has_attachments'] ?? 0,
            ':attachment_data' => $messageData['attachment_data'] ?? null
        ]);
        
    } catch (PDOException $e) {
        error_log("Error saving inbox message: " . $e->getMessage());
        return false;
    }
}

/**
 * Fetch inbox messages from database with pagination and filters
 */
function getInboxMessages($userEmail, $limit = 50, $offset = 0, $filters = []) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            return [];
        }
        
        $sql = "SELECT * FROM inbox_messages 
                WHERE user_email = :user_email 
                AND is_deleted = 0";
        
        $params = [':user_email' => $userEmail];
        
        // Add search filter
        if (!empty($filters['search'])) {
            $sql .= " AND (
                sender_email LIKE :search 
                OR sender_name LIKE :search 
                OR subject LIKE :search 
                OR body LIKE :search
            )";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        // Filter by read status
        if (!empty($filters['unread_only'])) {
            $sql .= " AND is_read = 0";
        }
        
        // Filter by sender
        if (!empty($filters['sender'])) {
            $sql .= " AND sender_email LIKE :sender";
            $params[':sender'] = '%' . $filters['sender'] . '%';
        }
        
        // Date range filters
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(received_date) >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(received_date) <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }
        
        // Sort by newest first
        $sql .= " ORDER BY received_date DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        
        // Bind all parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error fetching inbox messages: " . $e->getMessage());
        return [];
    }
}

/**
 * Get total count of inbox messages with filters
 */
function getInboxMessageCount($userEmail, $filters = []) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            return 0;
        }
        
        $sql = "SELECT COUNT(*) as count FROM inbox_messages 
                WHERE user_email = :user_email 
                AND is_deleted = 0";
        
        $params = [':user_email' => $userEmail];
        
        if (!empty($filters['search'])) {
            $sql .= " AND (
                sender_email LIKE :search 
                OR sender_name LIKE :search 
                OR subject LIKE :search 
                OR body LIKE :search
            )";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        if (!empty($filters['unread_only'])) {
            $sql .= " AND is_read = 0";
        }
        
        if (!empty($filters['sender'])) {
            $sql .= " AND sender_email LIKE :sender";
            $params[':sender'] = '%' . $filters['sender'] . '%';
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(received_date) >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(received_date) <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
        
    } catch (PDOException $e) {
        error_log("Error counting inbox messages: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get unread message count
 */
function getUnreadCount($userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            return 0;
        }
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count FROM inbox_messages 
            WHERE user_email = :user_email 
            AND is_read = 0 
            AND is_deleted = 0
        ");
        $stmt->execute([':user_email' => $userEmail]);
        
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
        
    } catch (PDOException $e) {
        error_log("Error counting unread messages: " . $e->getMessage());
        return 0;
    }
}

/**
 * Mark message as read
 */
function markMessageAsRead($messageId, $userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            return false;
        }
        
        $stmt = $pdo->prepare("
            UPDATE inbox_messages 
            SET is_read = 1, read_at = NOW() 
            WHERE id = :id 
            AND user_email = :user_email
        ");
        
        return $stmt->execute([
            ':id' => $messageId,
            ':user_email' => $userEmail
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
        if (!$pdo) {
            return false;
        }
        
        $stmt = $pdo->prepare("
            UPDATE inbox_messages 
            SET is_read = 0, read_at = NULL 
            WHERE id = :id 
            AND user_email = :user_email
        ");
        
        return $stmt->execute([
            ':id' => $messageId,
            ':user_email' => $userEmail
        ]);
        
    } catch (PDOException $e) {
        error_log("Error marking message as unread: " . $e->getMessage());
        return false;
    }
}

/**
 * Get last sync timestamp for user
 */
function getLastSyncDate($userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            return null;
        }
        
        $stmt = $pdo->prepare("
            SELECT last_sync_date FROM inbox_sync_status 
            WHERE user_email = :user_email
        ");
        $stmt->execute([':user_email' => $userEmail]);
        
        $result = $stmt->fetch();
        return $result['last_sync_date'] ?? null;
        
    } catch (PDOException $e) {
        error_log("Error getting last sync date: " . $e->getMessage());
        return null;
    }
}

/**
 * Update last sync timestamp for user
 */
function updateLastSyncDate($userEmail, $lastMessageId = null) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            return false;
        }
        
        $totalCount = getInboxMessageCount($userEmail, []);
        $unreadCount = getUnreadCount($userEmail);
        
        $sql = "INSERT INTO inbox_sync_status 
                (user_email, last_sync_date, last_message_id, total_messages, unread_count) 
                VALUES 
                (:user_email, NOW(), :last_message_id, :total_messages, :unread_count)
                ON DUPLICATE KEY UPDATE
                    last_sync_date = NOW(),
                    last_message_id = :last_message_id,
                    total_messages = :total_messages,
                    unread_count = :unread_count";
        
        $stmt = $pdo->prepare($sql);
        
        return $stmt->execute([
            ':user_email' => $userEmail,
            ':last_message_id' => $lastMessageId,
            ':total_messages' => $totalCount,
            ':unread_count' => $unreadCount
        ]);
        
    } catch (PDOException $e) {
        error_log("Error updating last sync date: " . $e->getMessage());
        return false;
    }
}

/**
 * Soft delete inbox message (move to trash)
 */
function deleteInboxMessage($messageId, $userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            return false;
        }
        
        $stmt = $pdo->prepare("
            UPDATE inbox_messages 
            SET is_deleted = 1, deleted_at = NOW() 
            WHERE id = :id 
            AND user_email = :user_email
        ");
        
        return $stmt->execute([
            ':id' => $messageId,
            ':user_email' => $userEmail
        ]);
        
    } catch (PDOException $e) {
        error_log("Error deleting inbox message: " . $e->getMessage());
        return false;
    }
}

/**
 * Toggle star status on message
 */
function toggleStarMessage($messageId, $userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            return false;
        }
        
        $stmt = $pdo->prepare("
            UPDATE inbox_messages 
            SET is_starred = NOT is_starred 
            WHERE id = :id 
            AND user_email = :user_email
        ");
        
        return $stmt->execute([
            ':id' => $messageId,
            ':user_email' => $userEmail
        ]);
        
    } catch (PDOException $e) {
        error_log("Error toggling star: " . $e->getMessage());
        return false;
    }
}
?>