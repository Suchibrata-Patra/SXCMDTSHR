<?php
// db_config.php - REDESIGNED Database Configuration
// Supports unified email storage, IMAP UID tracking, attachment deduplication, and deletion queue

function getDatabaseConnection() {
    $host = "localhost";
    $dbname = "u955994755_SXC_MDTS";
    $username = "u955994755_DB_supremacy";
    $password = "sxccal.edu#MDTS@2026";
    
    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        return null;
    }
}

// ==================== USER MANAGEMENT ====================

/**
 * Get or create user with UUID
 */
function getOrCreateUser($email, $fullName = null) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;
        
        // Check if user exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        
        if ($user) {
            return $user;
        }
        
        // Create new user with UUID
        $userUuid = generateUUID();
        $stmt = $pdo->prepare(
            "INSERT INTO users (user_uuid, email, full_name, created_at) 
             VALUES (:user_uuid, :email, :full_name, NOW())"
        );
        
        $stmt->execute([
            ':user_uuid' => $userUuid,
            ':email' => $email,
            ':full_name' => $fullName
        ]);
        
        return [
            'id' => $pdo->lastInsertId(),
            'user_uuid' => $userUuid,
            'email' => $email,
            'full_name' => $fullName
        ];
        
    } catch (PDOException $e) {
        error_log("Error in getOrCreateUser: " . $e->getMessage());
        return false;
    }
}

/**
 * Get user by email
 */
function getUserByEmail($email) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return null;
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        return $stmt->fetch();
        
    } catch (PDOException $e) {
        error_log("Error in getUserByEmail: " . $e->getMessage());
        return null;
    }
}

// ==================== EMAIL MANAGEMENT ====================

/**
 * Save email to unified emails table
 * Handles both SENT (SMTP) and RECEIVED (IMAP) emails
 */
function saveEmail($data) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;
        
        $pdo->beginTransaction();
        
        // Generate email UUID
        $emailUuid = generateUUID();
        
        // Prepare email data
        $emailType = $data['email_type'] ?? 'received'; // 'sent', 'received', 'draft'
        
        // Insert into emails table
        $sql = "INSERT INTO emails (
            email_uuid, message_id, imap_uid, imap_mailbox,
            sender_email, sender_name, recipient_email, cc_list, bcc_list,
            subject, body_text, body_html, article_title,
            email_type, is_read, is_starred, is_important, has_attachments,
            email_date, received_at, sent_at, is_internal, thread_id
        ) VALUES (
            :email_uuid, :message_id, :imap_uid, :imap_mailbox,
            :sender_email, :sender_name, :recipient_email, :cc_list, :bcc_list,
            :subject, :body_text, :body_html, :article_title,
            :email_type, :is_read, :is_starred, :is_important, :has_attachments,
            :email_date, :received_at, :sent_at, :is_internal, :thread_id
        )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':email_uuid' => $emailUuid,
            ':message_id' => $data['message_id'] ?? null,
            ':imap_uid' => $data['imap_uid'] ?? null,
            ':imap_mailbox' => $data['imap_mailbox'] ?? null,
            ':sender_email' => $data['sender_email'],
            ':sender_name' => $data['sender_name'] ?? null,
            ':recipient_email' => $data['recipient_email'],
            ':cc_list' => $data['cc_list'] ?? null,
            ':bcc_list' => $data['bcc_list'] ?? null,
            ':subject' => $data['subject'],
            ':body_text' => $data['body_text'] ?? null,
            ':body_html' => $data['body_html'] ?? null,
            ':article_title' => $data['article_title'] ?? null,
            ':email_type' => $emailType,
            ':is_read' => $data['is_read'] ?? 0,
            ':is_starred' => $data['is_starred'] ?? 0,
            ':is_important' => $data['is_important'] ?? 0,
            ':has_attachments' => $data['has_attachments'] ?? 0,
            ':email_date' => $data['email_date'] ?? date('Y-m-d H:i:s'),
            ':received_at' => $data['received_at'] ?? null,
            ':sent_at' => $data['sent_at'] ?? null,
            ':is_internal' => $data['is_internal'] ?? 0,
            ':thread_id' => $data['thread_id'] ?? null
        ]);
        
        $emailId = $pdo->lastInsertId();
        
        // Create user_email_access records
        // For SENT emails - add sender access
        if ($emailType === 'sent') {
            $sender = getOrCreateUser($data['sender_email'], $data['sender_name'] ?? null);
            if ($sender) {
                createUserEmailAccess($pdo, $sender['id'], $emailId, 'sender', $data['label_id'] ?? null);
            }
        }
        
        // For RECEIVED emails or internal emails - add recipient access
        $recipients = array_filter(array_map('trim', explode(',', $data['recipient_email'])));
        foreach ($recipients as $recipientEmail) {
            $recipient = getOrCreateUser($recipientEmail);
            if ($recipient) {
                createUserEmailAccess($pdo, $recipient['id'], $emailId, 'recipient', $data['label_id'] ?? null);
            }
        }
        
        // Add CC access
        if (!empty($data['cc_list'])) {
            $ccList = array_filter(array_map('trim', explode(',', $data['cc_list'])));
            foreach ($ccList as $ccEmail) {
                $ccUser = getOrCreateUser($ccEmail);
                if ($ccUser) {
                    createUserEmailAccess($pdo, $ccUser['id'], $emailId, 'cc');
                }
            }
        }
        
        // Handle attachments if present
        if (!empty($data['attachments']) && is_array($data['attachments'])) {
            handleEmailAttachments($pdo, $emailId, $data['attachments'], $data['sender_email']);
        }
        
        $pdo->commit();
        
        return [
            'email_id' => $emailId,
            'email_uuid' => $emailUuid
        ];
        
    } catch (PDOException $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        error_log("Error saving email: " . $e->getMessage());
        return false;
    }
}

/**
 * Create user email access record
 */
function createUserEmailAccess($pdo, $userId, $emailId, $accessType, $labelId = null) {
    $sql = "INSERT IGNORE INTO user_email_access 
            (user_id, email_id, access_type, label_id, created_at) 
            VALUES (:user_id, :email_id, :access_type, :label_id, NOW())";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        ':user_id' => $userId,
        ':email_id' => $emailId,
        ':access_type' => $accessType,
        ':label_id' => $labelId
    ]);
}

/**
 * Get user's inbox (received emails, not deleted)
 */
function getUserInbox($userEmail, $limit = 100, $offset = 0, $filters = []) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return [];
        
        $user = getUserByEmail($userEmail);
        if (!$user) return [];
        
        $sql = "SELECT 
                    e.*,
                    uea.user_read,
                    uea.user_starred,
                    uea.user_important,
                    uea.label_id,
                    l.label_name,
                    l.label_color,
                    (SELECT COUNT(*) FROM email_attachments ea WHERE ea.email_id = e.id) as attachment_count
                FROM emails e
                INNER JOIN user_email_access uea ON e.id = uea.email_id
                LEFT JOIN labels l ON uea.label_id = l.id
                WHERE uea.user_id = :user_id 
                AND e.email_type = 'received'
                AND uea.is_deleted = 0";
        
        $params = [':user_id' => $user['id']];
        
        // Add filters
        $sql = applyEmailFilters($sql, $params, $filters);
        
        $sql .= " ORDER BY e.email_date DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error fetching inbox: " . $e->getMessage());
        return [];
    }
}

/**
 * Get user's sent emails (not deleted)
 */
function getUserSentEmails($userEmail, $limit = 100, $offset = 0, $filters = []) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return [];
        
        $user = getUserByEmail($userEmail);
        if (!$user) return [];
        
        $sql = "SELECT 
                    e.*,
                    uea.label_id,
                    l.label_name,
                    l.label_color,
                    (SELECT COUNT(*) FROM email_attachments ea WHERE ea.email_id = e.id) as attachment_count
                FROM emails e
                INNER JOIN user_email_access uea ON e.id = uea.email_id
                LEFT JOIN labels l ON uea.label_id = l.id
                WHERE uea.user_id = :user_id 
                AND e.email_type = 'sent'
                AND uea.is_deleted = 0
                AND uea.access_type = 'sender'";
        
        $params = [':user_id' => $user['id']];
        
        // Add filters
        $sql = applyEmailFilters($sql, $params, $filters);
        
        $sql .= " ORDER BY e.sent_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error fetching sent emails: " . $e->getMessage());
        return [];
    }
}

/**
 * Get user's deleted emails (both sent and received)
 */
function getUserDeletedEmails($userEmail, $limit = 100, $offset = 0, $filters = []) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return [];
        
        $user = getUserByEmail($userEmail);
        if (!$user) return [];
        
        $sql = "SELECT 
                    e.*,
                    uea.deleted_at,
                    uea.access_type,
                    uea.label_id,
                    l.label_name,
                    l.label_color,
                    (SELECT COUNT(*) FROM email_attachments ea WHERE ea.email_id = e.id) as attachment_count
                FROM emails e
                INNER JOIN user_email_access uea ON e.id = uea.email_id
                LEFT JOIN labels l ON uea.label_id = l.id
                WHERE uea.user_id = :user_id 
                AND uea.is_deleted = 1";
        
        $params = [':user_id' => $user['id']];
        
        // Add filters
        $sql = applyEmailFilters($sql, $params, $filters);
        
        $sql .= " ORDER BY uea.deleted_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error fetching deleted emails: " . $e->getMessage());
        return [];
    }
}

/**
 * Apply common email filters to SQL query
 */
function applyEmailFilters($sql, &$params, $filters) {
    if (!empty($filters['search'])) {
        $sql .= " AND (
            e.sender_email LIKE :search 
            OR e.recipient_email LIKE :search
            OR e.subject LIKE :search 
            OR e.body_text LIKE :search
        )";
        $params[':search'] = '%' . $filters['search'] . '%';
    }
    
    if (!empty($filters['sender'])) {
        $sql .= " AND e.sender_email LIKE :sender";
        $params[':sender'] = '%' . $filters['sender'] . '%';
    }
    
    if (!empty($filters['recipient'])) {
        $sql .= " AND e.recipient_email LIKE :recipient";
        $params[':recipient'] = '%' . $filters['recipient'] . '%';
    }
    
    if (!empty($filters['subject'])) {
        $sql .= " AND e.subject LIKE :subject";
        $params[':subject'] = '%' . $filters['subject'] . '%';
    }
    
    if (!empty($filters['label_id'])) {
        if ($filters['label_id'] === 'unlabeled') {
            $sql .= " AND uea.label_id IS NULL";
        } else {
            $sql .= " AND uea.label_id = :label_id";
            $params[':label_id'] = $filters['label_id'];
        }
    }
    
    if (!empty($filters['date_from'])) {
        $sql .= " AND DATE(e.email_date) >= :date_from";
        $params[':date_from'] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $sql .= " AND DATE(e.email_date) <= :date_to";
        $params[':date_to'] = $filters['date_to'];
    }
    
    return $sql;
}

/**
 * Mark email as deleted for a user
 */
function markEmailAsDeleted($userEmail, $emailId) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;
        
        $user = getUserByEmail($userEmail);
        if (!$user) return false;
        
        $sql = "UPDATE user_email_access 
                SET is_deleted = 1, deleted_at = NOW() 
                WHERE user_id = :user_id AND email_id = :email_id";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':user_id' => $user['id'],
            ':email_id' => $emailId
        ]);
        
    } catch (PDOException $e) {
        error_log("Error marking email as deleted: " . $e->getMessage());
        return false;
    }
}

/**
 * Restore deleted email for a user
 */
function restoreEmail($userEmail, $emailId) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;
        
        $user = getUserByEmail($userEmail);
        if (!$user) return false;
        
        $sql = "UPDATE user_email_access 
                SET is_deleted = 0, deleted_at = NULL 
                WHERE user_id = :user_id AND email_id = :email_id";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':user_id' => $user['id'],
            ':email_id' => $emailId
        ]);
        
    } catch (PDOException $e) {
        error_log("Error restoring email: " . $e->getMessage());
        return false;
    }
}

/**
 * Permanently delete email from database (for super admin)
 * This should be called by cron job from deletion_queue
 */
function permanentlyDeleteEmail($emailId) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;
        
        $pdo->beginTransaction();
        
        // Delete will cascade to user_email_access, email_attachments via foreign keys
        $stmt = $pdo->prepare("DELETE FROM emails WHERE id = :email_id");
        $result = $stmt->execute([':email_id' => $emailId]);
        
        $pdo->commit();
        return $result;
        
    } catch (PDOException $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        error_log("Error permanently deleting email: " . $e->getMessage());
        return false;
    }
}

// ==================== ATTACHMENT MANAGEMENT ====================

/**
 * Handle email attachments with deduplication
 */
function handleEmailAttachments($pdo, $emailId, $attachments, $userEmail) {
    try {
        $user = getUserByEmail($userEmail);
        if (!$user) return false;
        
        $attachmentDir = "/var/www/html/attachments/" . $user['user_uuid'];
        
        // Create user directory if it doesn't exist
        if (!is_dir($attachmentDir)) {
            mkdir($attachmentDir, 0755, true);
        }
        
        foreach ($attachments as $index => $attachment) {
            // Calculate file hash for deduplication
            $fileHash = hash_file('sha256', $attachment['tmp_path']);
            
            // Check if file already exists in database
            $stmt = $pdo->prepare("SELECT * FROM attachments WHERE file_hash = :file_hash");
            $stmt->execute([':file_hash' => $fileHash]);
            $existingAttachment = $stmt->fetch();
            
            if ($existingAttachment) {
                // File already exists, just create mapping
                $attachmentId = $existingAttachment['id'];
            } else {
                // New file, save it
                $fileUuid = generateUUID();
                $fileName = $fileUuid . '_' . basename($attachment['filename']);
                $filePath = $attachmentDir . '/' . $fileName;
                
                // Move uploaded file
                if (!move_uploaded_file($attachment['tmp_path'], $filePath)) {
                    error_log("Failed to move uploaded file: " . $attachment['filename']);
                    continue;
                }
                
                // Insert into attachments table
                $stmt = $pdo->prepare(
                    "INSERT INTO attachments 
                    (file_uuid, file_hash, original_filename, file_extension, mime_type, file_size, storage_path, reference_count, uploaded_at) 
                    VALUES 
                    (:file_uuid, :file_hash, :original_filename, :file_extension, :mime_type, :file_size, :storage_path, 0, NOW())"
                );
                
                $stmt->execute([
                    ':file_uuid' => $fileUuid,
                    ':file_hash' => $fileHash,
                    ':original_filename' => $attachment['filename'],
                    ':file_extension' => pathinfo($attachment['filename'], PATHINFO_EXTENSION),
                    ':mime_type' => $attachment['mime_type'] ?? 'application/octet-stream',
                    ':file_size' => filesize($filePath),
                    ':storage_path' => 'attachments/' . $user['user_uuid'] . '/' . $fileName
                ]);
                
                $attachmentId = $pdo->lastInsertId();
            }
            
            // Create email_attachments mapping
            $stmt = $pdo->prepare(
                "INSERT INTO email_attachments 
                (email_id, attachment_id, attachment_order, is_inline, content_id) 
                VALUES 
                (:email_id, :attachment_id, :attachment_order, :is_inline, :content_id)"
            );
            
            $stmt->execute([
                ':email_id' => $emailId,
                ':attachment_id' => $attachmentId,
                ':attachment_order' => $index,
                ':is_inline' => $attachment['is_inline'] ?? 0,
                ':content_id' => $attachment['content_id'] ?? null
            ]);
            
            // Create user_attachment_access record
            $stmt = $pdo->prepare(
                "INSERT INTO user_attachment_access 
                (user_id, attachment_id, email_id, display_filename, is_deleted) 
                VALUES 
                (:user_id, :attachment_id, :email_id, :display_filename, 0)"
            );
            
            $stmt->execute([
                ':user_id' => $user['id'],
                ':attachment_id' => $attachmentId,
                ':email_id' => $emailId,
                ':display_filename' => $attachment['filename']
            ]);
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error handling attachments: " . $e->getMessage());
        return false;
    }
}

/**
 * Get attachments for an email
 */
function getEmailAttachments($emailId, $userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return [];
        
        $user = getUserByEmail($userEmail);
        if (!$user) return [];
        
        $sql = "SELECT 
                    a.*,
                    ea.attachment_order,
                    ea.is_inline,
                    ea.content_id,
                    uaa.display_filename,
                    uaa.is_deleted
                FROM attachments a
                INNER JOIN email_attachments ea ON a.id = ea.attachment_id
                INNER JOIN user_attachment_access uaa ON a.id = uaa.attachment_id
                WHERE ea.email_id = :email_id 
                AND uaa.user_id = :user_id
                AND uaa.is_deleted = 0
                ORDER BY ea.attachment_order";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':email_id' => $emailId,
            ':user_id' => $user['id']
        ]);
        
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error fetching attachments: " . $e->getMessage());
        return [];
    }
}

/**
 * Mark attachment as deleted for a user
 */
function markAttachmentAsDeleted($userEmail, $attachmentId, $emailId) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;
        
        $user = getUserByEmail($userEmail);
        if (!$user) return false;
        
        // The trigger will handle decrementing reference_count and queueing for deletion
        $sql = "UPDATE user_attachment_access 
                SET is_deleted = 1, deleted_at = NOW() 
                WHERE user_id = :user_id 
                AND attachment_id = :attachment_id 
                AND email_id = :email_id";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':user_id' => $user['id'],
            ':attachment_id' => $attachmentId,
            ':email_id' => $emailId
        ]);
        
    } catch (PDOException $e) {
        error_log("Error marking attachment as deleted: " . $e->getMessage());
        return false;
    }
}

// ==================== DELETION QUEUE MANAGEMENT ====================

/**
 * Get pending items from deletion queue (for cron job)
 */
function getPendingDeletionQueueItems($limit = 100) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return [];
        
        $sql = "SELECT * FROM deletion_queue 
                WHERE status = 'pending' 
                AND scheduled_for <= NOW() 
                ORDER BY scheduled_for ASC 
                LIMIT :limit";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error fetching deletion queue: " . $e->getMessage());
        return [];
    }
}

/**
 * Update deletion queue item status
 */
function updateDeletionQueueStatus($queueId, $status, $errorMessage = null) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;
        
        $sql = "UPDATE deletion_queue 
                SET status = :status, 
                    attempts = attempts + 1,
                    last_attempt_at = NOW(),
                    error_message = :error_message,
                    completed_at = CASE WHEN :status = 'completed' THEN NOW() ELSE NULL END
                WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':id' => $queueId,
            ':status' => $status,
            ':error_message' => $errorMessage
        ]);
        
    } catch (PDOException $e) {
        error_log("Error updating deletion queue status: " . $e->getMessage());
        return false;
    }
}

// ==================== LABEL MANAGEMENT ====================

/**
 * Get all labels for a user
 */
function getUserLabels($userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return [];
        
        $user = getUserByEmail($userEmail);
        if (!$user) return [];
        
        $sql = "SELECT * FROM labels 
                WHERE user_id = :user_id 
                AND is_active = 1
                ORDER BY sort_order, label_name ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $user['id']]);
        
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error fetching labels: " . $e->getMessage());
        return [];
    }
}

/**
 * Get label counts for a user
 */
function getLabelCounts($userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return [];
        
        $user = getUserByEmail($userEmail);
        if (!$user) return [];
        
        $sql = "SELECT 
                    l.id, 
                    l.label_name, 
                    l.label_color,
                    l.created_at,
                    COUNT(uea.id) as email_count
                FROM labels l
                LEFT JOIN user_email_access uea ON l.id = uea.label_id 
                    AND uea.user_id = :user_id
                    AND uea.is_deleted = 0
                WHERE l.user_id = :user_id
                AND l.is_active = 1
                GROUP BY l.id, l.label_name, l.label_color, l.created_at
                ORDER BY l.sort_order, l.label_name ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $user['id']]);
        
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error fetching label counts: " . $e->getMessage());
        return [];
    }
}

/**
 * Create a new label
 */
function createLabel($userEmail, $labelName, $labelColor = '#0973dc') {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;
        
        $user = getUserByEmail($userEmail);
        if (!$user) return false;
        
        // Check if label already exists
        $stmt = $pdo->prepare("SELECT id FROM labels WHERE user_id = :user_id AND label_name = :label_name");
        $stmt->execute([
            ':user_id' => $user['id'],
            ':label_name' => $labelName
        ]);
        
        if ($stmt->fetch()) {
            return ['error' => 'Label already exists'];
        }
        
        $sql = "INSERT INTO labels (user_id, label_name, label_color, created_at) 
                VALUES (:user_id, :label_name, :label_color, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':user_id' => $user['id'],
            ':label_name' => $labelName,
            ':label_color' => $labelColor
        ]);
        
        return $result ? $pdo->lastInsertId() : false;
        
    } catch (PDOException $e) {
        error_log("Error creating label: " . $e->getMessage());
        return false;
    }
}

/**
 * Update email label for a user
 */
function updateEmailLabel($userEmail, $emailId, $labelId) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;
        
        $user = getUserByEmail($userEmail);
        if (!$user) return false;
        
        $sql = "UPDATE user_email_access 
                SET label_id = :label_id 
                WHERE user_id = :user_id AND email_id = :email_id";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':user_id' => $user['id'],
            ':email_id' => $emailId,
            ':label_id' => $labelId
        ]);
        
    } catch (PDOException $e) {
        error_log("Error updating email label: " . $e->getMessage());
        return false;
    }
}

// ==================== UTILITY FUNCTIONS ====================

/**
 * Generate UUID v4
 */
function generateUUID() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Check if email is internal (both sender and recipient are internal users)
 */
function isInternalEmail($senderEmail, $recipientEmail) {
    $internalDomains = ['holidayseva.com', 'sxccal.edu']; // Add your internal domains
    
    $senderDomain = substr(strrchr($senderEmail, "@"), 1);
    $recipientDomain = substr(strrchr($recipientEmail, "@"), 1);
    
    return in_array($senderDomain, $internalDomains) && in_array($recipientDomain, $internalDomains);
}

?>