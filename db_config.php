<?php
/**
 * db_config.php - Database Configuration and Helper Functions
 * CORRECTED VERSION - Works with sent_emails_new and sent_email_attachments_new tables
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
 * Get environment variable or default value
 */
function env($key, $default = null) {
    $value = getenv($key);
    return $value !== false ? $value : $default;
}

/**
 * Get PDO database connection
 */
function getDatabaseConnection() {
    static $pdo = null;
    
    if ($pdo !== null) {
        return $pdo;
    }
    
    try {
        $host = env('DB_HOST', 'localhost');
        $dbname = env('DB_NAME', 'u955994755_SXC_MDTS');
        $username = env('DB_USER', 'u955994755_DB_supremacy');
        $password = env('DB_PASS', 'sxccal.edu#MDTS@2026');
        
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

function getUserId($pdo, $email) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ? $user['id'] : null;
    } catch (PDOException $e) {
        error_log("Error getting user ID: " . $e->getMessage());
        return null;
    }
}

function getUserIdByEmail($pdo, $email) {
    return getUserId($pdo, $email);
}

function createUserIfNotExists($pdo, $email, $displayName = null) {
    try {
        $existingId = getUserId($pdo, $email);
        if ($existingId) {
            return $existingId;
        }
        
        $uuid = generateUuidV4();
        
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

// ==================== SENT EMAILS MANAGEMENT (NEW TABLE) ====================

/**
 * Save sent email to sent_emails_new table
 */
function saveSentEmail($pdo, $emailData) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO sent_emails_new (
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
 * Link attachments to sent email in sent_email_attachments_new table
 */
function linkAttachmentsToSentEmail($pdo, $emailId, $emailUuid, $attachmentIds) {
    try {
        if (empty($attachmentIds)) {
            error_log("No attachments to link");
            return true;
        }
        
        error_log("Linking " . count($attachmentIds) . " attachments to sent email $emailId");
        
        $successCount = 0;
        $failCount = 0;
        
        // Get attachment details from session
        $sessionAttachments = $_SESSION['temp_attachments'] ?? [];
        
        foreach ($attachmentIds as $attachmentId) {
            $attachmentId = trim($attachmentId);
            
            // Find attachment in session
            $attachmentData = null;
            foreach ($sessionAttachments as $attachment) {
                if (isset($attachment['id']) && $attachment['id'] == $attachmentId) {
                    $attachmentData = $attachment;
                    break;
                }
            }
            
            if (!$attachmentData) {
                error_log("✗ Attachment ID $attachmentId not found in session");
                $failCount++;
                continue;
            }
            
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO sent_email_attachments_new (
                        sent_email_id, email_uuid, original_filename, 
                        stored_filename, file_path, file_size, 
                        mime_type, file_extension, upload_session_id, uploaded_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $success = $stmt->execute([
                    $emailId,
                    $emailUuid,
                    $attachmentData['original_name'] ?? 'file',
                    $attachmentData['stored_name'] ?? $attachmentData['path'],
                    $attachmentData['path'] ?? '',
                    $attachmentData['file_size'] ?? 0,
                    $attachmentData['mime_type'] ?? 'application/octet-stream',
                    $attachmentData['extension'] ?? '',
                    session_id()
                ]);
                
                if ($success) {
                    error_log("✓ Linked attachment $attachmentId to sent email $emailId");
                    $successCount++;
                } else {
                    error_log("✗ Failed to link attachment $attachmentId");
                    $failCount++;
                }
                
            } catch (PDOException $e) {
                error_log("Error linking attachment $attachmentId: " . $e->getMessage());
                $failCount++;
            }
        }
        
        error_log("Attachment linking complete: $successCount succeeded, $failCount failed");
        
        return $successCount > 0;
        
    } catch (Exception $e) {
        error_log("Error in linkAttachmentsToSentEmail: " . $e->getMessage());
        return false;
    }
}

/**
 * Get sent emails from sent_emails_new table with filters
 */
function getSentEmails($userEmail, $limit = 50, $offset = 0, $filters = []) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return [];
        
        $sql = "SELECT 
                    se.*,
                    (SELECT GROUP_CONCAT(original_filename SEPARATOR ', ')
                     FROM sent_email_attachments_new sea
                     WHERE sea.sent_email_id = se.id) as attachment_names,
                    (SELECT COUNT(*)
                     FROM sent_email_attachments_new sea
                     WHERE sea.sent_email_id = se.id) as attachment_count
                FROM sent_emails_new se
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
        
        // Apply subject filter
        if (!empty($filters['subject'])) {
            $sql .= " AND se.subject LIKE :subject";
            $params['subject'] = '%' . $filters['subject'] . '%';
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
                FROM sent_emails_new
                WHERE sender_email = :email 
                AND is_deleted = 0";
        
        $params = ['email' => $userEmail];
        
        if (!empty($filters['search'])) {
            $sql .= " AND (recipient_email LIKE :search OR subject LIKE :search OR body_text LIKE :search OR article_title LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        if (!empty($filters['recipient'])) {
            $sql .= " AND recipient_email LIKE :recipient";
            $params['recipient'] = '%' . $filters['recipient'] . '%';
        }
        
        if (!empty($filters['subject'])) {
            $sql .= " AND subject LIKE :subject";
            $params['subject'] = '%' . $filters['subject'] . '%';
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
            SELECT * FROM sent_emails_new
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

/**
 * Update sent email label
 */
function updateSentEmailLabel($pdo, $emailId, $labelId = null, $labelName = null, $labelColor = null) {
    try {
        $stmt = $pdo->prepare("
            UPDATE sent_emails_new 
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

// ==================== LABEL MANAGEMENT ====================

/**
 * Get label counts for user from sent_emails_new table
 */
function getLabelCounts($userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            return [];
        }
        
        $sql = "SELECT 
                    label_id,
                    label_name,
                    label_color,
                    COUNT(*) as email_count
                FROM sent_emails_new
                WHERE sender_email = :user_email
                AND is_deleted = 0
                AND label_id IS NOT NULL
                GROUP BY label_id, label_name, label_color
                ORDER BY label_name ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_email' => $userEmail]);
        
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error fetching label counts: " . $e->getMessage());
        return [];
    }
}

/**
 * Get count of unlabeled emails
 */
function getUnlabeledEmailCount($userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            return 0;
        }
        
        $sql = "SELECT COUNT(*) as count 
                FROM sent_emails_new 
                WHERE sender_email = :user_email 
                AND label_id IS NULL 
                AND is_deleted = 0";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_email' => $userEmail]);
        $result = $stmt->fetch();
        
        return $result['count'] ?? 0;
        
    } catch (PDOException $e) {
        error_log("Error fetching unlabeled count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get user labels from sent emails
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
            FROM sent_emails_new
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

/**
 * Create a new label (if you have a labels table)
 */
function createLabel($userEmail, $labelName, $labelColor = '#0973dc') {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            return false;
        }
        
        // Check if labels table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'labels'");
        if ($stmt->rowCount() == 0) {
            return false;
        }
        
        // Check if label already exists
        $stmt = $pdo->prepare("SELECT id FROM labels WHERE user_email = ? AND label_name = ?");
        $stmt->execute([$userEmail, $labelName]);
        
        if ($stmt->fetch()) {
            return ['error' => 'Label already exists'];
        }
        
        $sql = "INSERT INTO labels (user_email, label_name, label_color, created_at) 
                VALUES (?, ?, ?, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$userEmail, $labelName, $labelColor]);
        
        return $result ? $pdo->lastInsertId() : false;
        
    } catch (PDOException $e) {
        error_log("Error creating label: " . $e->getMessage());
        return false;
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

// Disable error display in production
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
?>