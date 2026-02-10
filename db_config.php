<?php
/**
 * db_config.php - Database Configuration and Helper Functions
 * REFACTORED VERSION - Works with simplified 2-table structure
 * Tables: sent_emails, sent_email_attachments
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==================== FILE ENCRYPTION SETTINGS ====================
if (!defined('FILE_ENCRYPTION_KEY')) {
    define('FILE_ENCRYPTION_KEY', 'k8Bv2nQx7Wp4Yj9Zm5Rt1Lc6Hd3Fg0Sa');
}
if (!defined('FILE_ENCRYPTION_METHOD')) {
    define('FILE_ENCRYPTION_METHOD', 'AES-256-CBC');
}

// ==================== DATABASE CONNECTION ====================

/**
 * Get PDO database connection
 */
function getDatabaseConnection() {
    static $pdo = null;
    
    if ($pdo !== null) {
        return $pdo;
    }
    
    try {
        $host = "localhost";
        $dbname = "u955994755_SXC_MDTS";
        $username = "u955994755_DB_supremacy";
        $password = "sxccal.edu#MDTS@2026";
        
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        
        return $pdo;
        
    } catch (PDOException $e) {
        error_log("Database connection error: " . $e->getMessage());
        return null;
    }
}

// ==================== USER MANAGEMENT ====================

/**
 * Get user ID by email address (if users table exists)
 */
function getUserId($pdo, $email) {
    try {
        // Check if users table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        if ($stmt->rowCount() == 0) {
            return null; // Users table doesn't exist
        }
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ? $user['id'] : null;
    } catch (PDOException $e) {
        error_log("Error getting user ID: " . $e->getMessage());
        return null;
    }
}

/**
 * Create user if doesn't exist (if users table exists)
 */
function createUserIfNotExists($pdo, $email, $displayName = null) {
    try {
        // Check if users table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
        if ($stmt->rowCount() == 0) {
            return null; // Users table doesn't exist
        }
        
        $existingId = getUserId($pdo, $email);
        if ($existingId) {
            return $existingId;
        }
        
        $uuid = generateUuidV4();
        
        // Check columns
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'user_uuid'");
        $hasUuidColumn = $stmt->rowCount() > 0;
        
        if ($hasUuidColumn) {
            $stmt = $pdo->prepare("
                INSERT INTO users (user_uuid, email, full_name, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$uuid, $email, $displayName]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO users (email, display_name, created_at) 
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$email, $displayName ?? $email]);
        }
        
        return $pdo->lastInsertId();
        
    } catch (PDOException $e) {
        error_log("Error creating user: " . $e->getMessage());
        return null;
    }
}

// ==================== SENT EMAIL MANAGEMENT ====================

/**
 * Save sent email to database - SIMPLIFIED for 2-table structure
 * 
 * @param PDO $pdo Database connection
 * @param array $emailData Email data array
 * @return int|null Email ID if successful, null otherwise
 */
function saveSentEmail($pdo, $emailData) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO sent_emails (
                email_uuid, message_id, sender_email, sender_name,
                recipient_email, cc_list, bcc_list, reply_to,
                subject, article_title, body_text, body_html,
                label_id, label_name, label_color,
                has_attachments, email_type, sent_at, created_at
            ) VALUES (
                :email_uuid, :message_id, :sender_email, :sender_name,
                :recipient_email, :cc_list, :bcc_list, :reply_to,
                :subject, :article_title, :body_text, :body_html,
                :label_id, :label_name, :label_color,
                :has_attachments, :email_type, NOW(), NOW()
            )
        ");
        
        $stmt->execute([
            'email_uuid' => $emailData['email_uuid'],
            'message_id' => $emailData['message_id'] ?? null,
            'sender_email' => $emailData['sender_email'],
            'sender_name' => $emailData['sender_name'] ?? null,
            'recipient_email' => $emailData['recipient_email'],
            'cc_list' => $emailData['cc_list'] ?? null,
            'bcc_list' => $emailData['bcc_list'] ?? null,
            'reply_to' => $emailData['reply_to'] ?? null,
            'subject' => $emailData['subject'],
            'article_title' => $emailData['article_title'] ?? null,
            'body_text' => $emailData['body_text'] ?? null,
            'body_html' => $emailData['body_html'] ?? null,
            'label_id' => $emailData['label_id'] ?? null,
            'label_name' => $emailData['label_name'] ?? null,
            'label_color' => $emailData['label_color'] ?? null,
            'has_attachments' => $emailData['has_attachments'] ?? 0,
            'email_type' => $emailData['email_type'] ?? 'sent'
        ]);
        
        $emailId = $pdo->lastInsertId();
        
        if ($emailId) {
            error_log("✓ Sent email saved to database (ID: $emailId, UUID: {$emailData['email_uuid']})");
        } else {
            error_log("✗ Failed to save sent email to database");
        }
        
        return $emailId;
        
    } catch (PDOException $e) {
        error_log("Error saving sent email: " . $e->getMessage());
        error_log("SQL Error Details: " . print_r($e->errorInfo, true));
        return null;
    }
}

/**
 * Update label for a sent email
 * 
 * @param PDO $pdo Database connection
 * @param int $emailId Email ID
 * @param int|null $labelId Label ID (null to remove label)
 * @param string|null $labelName Label name
 * @param string|null $labelColor Label color
 * @return bool Success status
 */
function updateSentEmailLabel($pdo, $emailId, $labelId = null, $labelName = null, $labelColor = null) {
    try {
        $stmt = $pdo->prepare("
            UPDATE sent_emails 
            SET label_id = :label_id,
                label_name = :label_name,
                label_color = :label_color,
                updated_at = NOW()
            WHERE id = :email_id
        ");
        
        $success = $stmt->execute([
            'label_id' => $labelId,
            'label_name' => $labelName,
            'label_color' => $labelColor,
            'email_id' => $emailId
        ]);
        
        if ($success) {
            error_log("✓ Label updated for email $emailId");
        }
        
        return $success;
        
    } catch (PDOException $e) {
        error_log("Error updating email label: " . $e->getMessage());
        return false;
    }
}

/**
 * Soft delete a sent email
 */
function deleteSentEmail($pdo, $emailId) {
    try {
        $stmt = $pdo->prepare("
            UPDATE sent_emails 
            SET is_deleted = 1, updated_at = NOW()
            WHERE id = :email_id
        ");
        
        return $stmt->execute(['email_id' => $emailId]);
        
    } catch (PDOException $e) {
        error_log("Error deleting sent email: " . $e->getMessage());
        return false;
    }
}

// ==================== ATTACHMENT MANAGEMENT ====================

/**
 * Link attachments to sent email - SIMPLIFIED
 * 
 * @param PDO $pdo Database connection
 * @param int $sentEmailId Sent email ID
 * @param string $emailUuid Email UUID
 * @param array $attachmentIds Array of attachment IDs from session
 * @return bool Success status
 */
function linkAttachmentsToSentEmail($pdo, $sentEmailId, $emailUuid, $attachmentIds) {
    try {
        if (empty($attachmentIds) || !isset($_SESSION['temp_attachments'])) {
            return true; // No attachments to link
        }
        
        $sessionAttachments = $_SESSION['temp_attachments'];
        $linkedCount = 0;
        
        foreach ($attachmentIds as $attachmentId) {
            $attachmentId = trim($attachmentId);
            
            // Find attachment in session
            foreach ($sessionAttachments as $attachment) {
                if (isset($attachment['id']) && $attachment['id'] == $attachmentId) {
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO sent_email_attachments (
                            sent_email_id, email_uuid, original_filename, 
                            stored_filename, file_path, file_size, 
                            mime_type, file_extension, uploaded_at
                        ) VALUES (
                            :sent_email_id, :email_uuid, :original_filename,
                            :stored_filename, :file_path, :file_size,
                            :mime_type, :file_extension, NOW()
                        )
                    ");
                    
                    $success = $stmt->execute([
                        'sent_email_id' => $sentEmailId,
                        'email_uuid' => $emailUuid,
                        'original_filename' => $attachment['original_name'] ?? 'attachment',
                        'stored_filename' => $attachment['path'] ?? '',
                        'file_path' => 'uploads/attachments/' . ($attachment['path'] ?? ''),
                        'file_size' => $attachment['file_size'] ?? 0,
                        'mime_type' => $attachment['mime_type'] ?? 'application/octet-stream',
                        'file_extension' => $attachment['extension'] ?? ''
                    ]);
                    
                    if ($success) {
                        $linkedCount++;
                        error_log("✓ Linked attachment: {$attachment['original_name']}");
                    }
                    
                    break;
                }
            }
        }
        
        error_log("✓ Successfully linked $linkedCount attachments to email $sentEmailId");
        return true;
        
    } catch (PDOException $e) {
        error_log("Error linking attachments: " . $e->getMessage());
        return false;
    }
}

/**
 * Get attachments for a sent email
 */
function getSentEmailAttachments($pdo, $sentEmailId) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM sent_email_attachments
            WHERE sent_email_id = :sent_email_id
            ORDER BY uploaded_at ASC
        ");
        
        $stmt->execute(['sent_email_id' => $sentEmailId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error getting attachments: " . $e->getMessage());
        return [];
    }
}

// ==================== LABEL MANAGEMENT ====================

/**
 * Get label counts for a user's sent emails
 */
function getLabelCounts($userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return [];
        
        $stmt = $pdo->prepare("
            SELECT 
                label_id,
                label_name,
                label_color,
                COUNT(*) as email_count
            FROM sent_emails
            WHERE sender_email = :email
            AND is_deleted = 0
            AND label_id IS NOT NULL
            GROUP BY label_id, label_name, label_color
            ORDER BY label_name
        ");
        
        $stmt->execute(['email' => $userEmail]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error getting label counts: " . $e->getMessage());
        return [];
    }
}

/**
 * Get count of unlabeled sent emails
 */
function getUnlabeledEmailCount($userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return 0;
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM sent_emails
            WHERE sender_email = :email
            AND is_deleted = 0
            AND label_id IS NULL
        ");
        
        $stmt->execute(['email' => $userEmail]);
        $result = $stmt->fetch();
        
        return $result['count'] ?? 0;
        
    } catch (PDOException $e) {
        error_log("Error getting unlabeled count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get all unique labels from sent emails for a user
 */
function getUserLabelsFromSentEmails($userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return [];
        
        $stmt = $pdo->prepare("
            SELECT DISTINCT
                label_id as id,
                label_name,
                label_color
            FROM sent_emails
            WHERE sender_email = :email
            AND is_deleted = 0
            AND label_id IS NOT NULL
            ORDER BY label_name
        ");
        
        $stmt->execute(['email' => $userEmail]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error getting user labels: " . $e->getMessage());
        return [];
    }
}

// ==================== QUERY FUNCTIONS ====================

/**
 * Get sent emails with optional filters
 */
function getSentEmails($userEmail, $limit = 50, $offset = 0, $filters = []) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return [];
        
        $sql = "SELECT 
                    se.*,
                    (SELECT GROUP_CONCAT(original_filename SEPARATOR ', ')
                     FROM sent_email_attachments sea
                     WHERE sea.sent_email_id = se.id) as attachment_names,
                    (SELECT COUNT(*)
                     FROM sent_email_attachments sea
                     WHERE sea.sent_email_id = se.id) as attachment_count
                FROM sent_emails se
                WHERE se.sender_email = :email
                AND se.is_deleted = 0";
        
        $params = ['email' => $userEmail];
        
        // Apply search filter
        if (!empty($filters['search'])) {
            $sql .= " AND (se.recipient_email LIKE :search 
                        OR se.subject LIKE :search 
                        OR se.body_text LIKE :search 
                        OR se.article_title LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        // Apply recipient filter
        if (!empty($filters['recipient'])) {
            $sql .= " AND se.recipient_email LIKE :recipient";
            $params['recipient'] = '%' . $filters['recipient'] . '%';
        }
        
        // Apply label filter
        if (!empty($filters['label_id'])) {
            if ($filters['label_id'] === 'unlabeled') {
                $sql .= " AND se.label_id IS NULL";
            } else {
                $sql .= " AND se.label_id = :label_id";
                $params['label_id'] = $filters['label_id'];
            }
        }
        
        // Apply date range filters
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(se.sent_at) >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(se.sent_at) <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }
        
        $sql .= " ORDER BY se.sent_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error fetching sent emails: " . $e->getMessage());
        return [];
    }
}

/**
 * Get count of sent emails with filters
 */
function getSentEmailCount($userEmail, $filters = []) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return 0;
        
        $sql = "SELECT COUNT(*) as count 
                FROM sent_emails
                WHERE sender_email = :email 
                AND is_deleted = 0";
        
        $params = ['email' => $userEmail];
        
        if (!empty($filters['search'])) {
            $sql .= " AND (recipient_email LIKE :search OR subject LIKE :search OR body_text LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        if (!empty($filters['recipient'])) {
            $sql .= " AND recipient_email LIKE :recipient";
            $params['recipient'] = '%' . $filters['recipient'] . '%';
        }
        
        if (!empty($filters['label_id'])) {
            if ($filters['label_id'] === 'unlabeled') {
                $sql .= " AND label_id IS NULL";
            } else {
                $sql .= " AND label_id = :label_id";
                $params['label_id'] = $filters['label_id'];
            }
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(sent_at) >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(sent_at) <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        return $result['count'] ?? 0;
        
    } catch (PDOException $e) {
        error_log("Error counting sent emails: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get single sent email by ID
 */
function getSentEmailById($emailId, $userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return null;
        
        $stmt = $pdo->prepare("
            SELECT * FROM sent_emails
            WHERE id = :id AND sender_email = :email AND is_deleted = 0
        ");
        
        $stmt->execute([
            'id' => $emailId,
            'email' => $userEmail
        ]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error getting sent email: " . $e->getMessage());
        return null;
    }
}

// ==================== FILE ENCRYPTION ====================

/**
 * Encrypt file ID for secure download links
 */
function encryptFileId($fileId) {
    try {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(FILE_ENCRYPTION_METHOD));
        
        $encrypted = openssl_encrypt(
            (string)$fileId,
            FILE_ENCRYPTION_METHOD,
            FILE_ENCRYPTION_KEY,
            0,
            $iv
        );
        
        $result = base64_encode($iv . $encrypted);
        return $result;
        
    } catch (Exception $e) {
        error_log("Encryption error: " . $e->getMessage());
        return null;
    }
}

/**
 * Decrypt file ID
 */
function decryptFileId($encrypted) {
    try {
        $data = base64_decode($encrypted);
        $iv_length = openssl_cipher_iv_length(FILE_ENCRYPTION_METHOD);
        $iv = substr($data, 0, $iv_length);
        $encrypted_data = substr($data, $iv_length);
        
        $decrypted = openssl_decrypt(
            $encrypted_data,
            FILE_ENCRYPTION_METHOD,
            FILE_ENCRYPTION_KEY,
            0,
            $iv
        );
        
        return $decrypted !== false ? (int)$decrypted : null;
    } catch (Exception $e) {
        error_log("Decryption error: " . $e->getMessage());
        return null;
    }
}

// ==================== UTILITY FUNCTIONS ====================

/**
 * Generate UUID v4
 */
function generateUuidV4() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

/**
 * Parse email list from comma/semicolon separated string
 */
function parseEmailList($emailString) {
    $emails = preg_split('/[;,]+/', $emailString);
    $emails = array_map('trim', $emails);
    $emails = array_filter($emails);
    return $emails;
}

/**
 * Format file size to human readable
 */
function formatFileSize($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

// Enable error logging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>