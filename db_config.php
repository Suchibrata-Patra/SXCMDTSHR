<?php
/**
 * db_config.php - Database Configuration and Helper Functions
 * CORRECTED VERSION with proper session handling
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==================== FILE ENCRYPTION SETTINGS ====================
// IMPORTANT: Change this to a unique 32-character key in production!
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
        // Direct database credentials
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
 * Get user ID by email address
 */
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

/**
 * Get user ID by email - alias for consistency
 */
function getUserIdByEmail($pdo, $email) {
    return getUserId($pdo, $email);
}

/**
 * Create user if doesn't exist
 */
function createUserIfNotExists($pdo, $email, $displayName = null) {
    try {
        $existingId = getUserId($pdo, $email);
        if ($existingId) {
            return $existingId;
        }
        
        // Generate UUID for new user
        $uuid = generateUuidV4();
        
        // Check if users table has user_uuid column
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

// ==================== EMAIL MANAGEMENT ====================

/**
 * Save email to database and return email ID
 */
function saveEmailToDatabase($pdo, $emailData) {
    try {
        // Check which columns exist in emails table
        $stmt = $pdo->query("SHOW COLUMNS FROM emails");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Build query based on available columns
        $hasMessageId = in_array('message_id', $columns);
        $hasReplyTo = in_array('reply_to', $columns);
        $hasEmailDate = in_array('email_date', $columns);
        
        if ($hasMessageId && $hasReplyTo && $hasEmailDate) {
            // New schema
            $stmt = $pdo->prepare("
                INSERT INTO emails (
                    email_uuid, message_id, sender_email, sender_name,
                    recipient_email, cc_list, bcc_list, reply_to,
                    subject, body_text, body_html, article_title,
                    email_type, has_attachments, email_date, sent_at, created_at
                ) VALUES (
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, NOW(), NOW(), NOW()
                )
            ");
            
            $stmt->execute([
                $emailData['email_uuid'],
                $emailData['message_id'] ?? null,
                $emailData['sender_email'],
                $emailData['sender_name'] ?? null,
                $emailData['recipient_email'],
                $emailData['cc_list'] ?? null,
                $emailData['bcc_list'] ?? null,
                $emailData['reply_to'] ?? null,
                $emailData['subject'],
                $emailData['body_text'] ?? null,
                $emailData['body_html'] ?? null,
                $emailData['article_title'] ?? null,
                $emailData['email_type'] ?? 'sent',
                $emailData['has_attachments'] ?? 0
            ]);
        } else {
            // Old schema
            $stmt = $pdo->prepare("
                INSERT INTO emails (
                    email_uuid, sender_email, sender_name, recipient_email,
                    cc_list, bcc_list, subject, body_text, body_html,
                    article_title, email_type, has_attachments, sent_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $emailData['email_uuid'],
                $emailData['sender_email'],
                $emailData['sender_name'],
                $emailData['recipient_email'],
                $emailData['cc_list'] ?? null,
                $emailData['bcc_list'] ?? null,
                $emailData['subject'],
                $emailData['body_text'],
                $emailData['body_html'],
                $emailData['article_title'] ?? null,
                $emailData['email_type'] ?? 'sent',
                $emailData['has_attachments'] ?? 0
            ]);
        }
        
        return $pdo->lastInsertId();
        
    } catch (PDOException $e) {
        error_log("Error saving email: " . $e->getMessage());
        return null;
    }
}

/**
 * Create email access record for user
 */
function createEmailAccess($pdo, $emailId, $userId, $accessType = 'sender', $labelId = null) {
    try {
        // Check if table is user_email_access or email_access
        $stmt = $pdo->query("SHOW TABLES LIKE 'user_email_access'");
        $useUserEmailAccess = $stmt->rowCount() > 0;
        
        if ($useUserEmailAccess) {
            // Check for is_deleted column
            $stmt = $pdo->query("SHOW COLUMNS FROM user_email_access LIKE 'is_deleted'");
            $hasIsDeleted = $stmt->rowCount() > 0;
            
            if ($hasIsDeleted) {
                $stmt = $pdo->prepare("
                    INSERT INTO user_email_access (
                        email_id, user_id, access_type, label_id,
                        is_deleted, created_at
                    ) VALUES (?, ?, ?, ?, 0, NOW())
                ");
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO user_email_access (
                        email_id, user_id, access_type, label_id, created_at
                    ) VALUES (?, ?, ?, ?, NOW())
                ");
            }
        } else {
            // email_access table
            $stmt = $pdo->prepare("
                INSERT INTO email_access (
                    email_id, user_id, access_type, label_id, created_at
                ) VALUES (?, ?, ?, ?, NOW())
            ");
        }
        
        return $stmt->execute([$emailId, $userId, $accessType, $labelId]);
        
    } catch (PDOException $e) {
        error_log("Error creating email access: " . $e->getMessage());
        return false;
    }
}

/**
 * Link attachments to email
 */
function linkAttachmentsToEmail($pdo, $emailId, $emailUuid, $senderId, $attachmentIds) {
    try {
        if (empty($attachmentIds)) {
            return true;
        }
        
        foreach ($attachmentIds as $attachmentId) {
            // Update user_attachment_access with email_uuid if columns exist
            $stmt = $pdo->query("SHOW COLUMNS FROM user_attachment_access");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (in_array('email_uuid', $columns) && in_array('sender_id', $columns)) {
                $stmt = $pdo->prepare("
                    UPDATE user_attachment_access 
                    SET email_uuid = ?, sender_id = ?
                    WHERE user_id = ? AND attachment_id = ?
                ");
                $stmt->execute([$emailUuid, $senderId, $senderId, $attachmentId]);
            }
            
            // Create email-attachment link
            $stmt = $pdo->prepare("
                INSERT INTO email_attachments (email_id, attachment_id, created_at)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE email_id = email_id
            ");
            $stmt->execute([$emailId, $attachmentId]);
        }
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Error linking attachments: " . $e->getMessage());
        return false;
    }
}

/**
 * Get sent emails for current user
 */
function getSentEmails($userEmail, $limit = 100, $offset = 0, $filters = []) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            return [];
        }
        
        $userId = getUserId($pdo, $userEmail);
        if (!$userId) {
            return [];
        }
        
        // Check if view exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'v_user_sent'");
        $viewExists = $stmt->rowCount() > 0;
        
        if ($viewExists) {
            $sql = "SELECT * FROM v_user_sent WHERE user_id = :user_id";
        } else {
            // Fallback to direct query
            $sql = "SELECT e.*, uea.label_id 
                    FROM emails e
                    JOIN user_email_access uea ON e.id = uea.email_id
                    WHERE uea.user_id = :user_id AND uea.access_type = 'sender'";
        }
        
        $params = [':user_id' => $userId];
        
        // Add filters
        if (!empty($filters['search'])) {
            $sql .= " AND (
                recipient_email LIKE :search 
                OR subject LIKE :search 
                OR body_text LIKE :search
                OR article_title LIKE :search
            )";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        if (!empty($filters['recipient'])) {
            $sql .= " AND recipient_email LIKE :recipient";
            $params[':recipient'] = '%' . $filters['recipient'] . '%';
        }
        
        if (!empty($filters['label_id'])) {
            if ($filters['label_id'] === 'unlabeled') {
                $sql .= " AND label_id IS NULL";
            } else {
                $sql .= " AND label_id = :label_id";
                $params[':label_id'] = $filters['label_id'];
            }
        }
        
        $sql .= " ORDER BY sent_at DESC LIMIT :limit OFFSET :offset";
        
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

// ==================== LABEL MANAGEMENT ====================

/**
 * Get all labels for a user
 */
function getUserLabels($userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            return [];
        }
        
        $sql = "SELECT * FROM labels 
                WHERE user_email = :user_email OR user_email IS NULL
                ORDER BY label_name ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_email' => $userEmail]);
        
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error fetching labels: " . $e->getMessage());
        return [];
    }
}

/**
 * Get label counts - works even if user doesn't exist in users table
 */
function getLabelCounts($userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            return [];
        }
        
        $userId = getUserId($pdo, $userEmail);
        
        if ($userId) {
            $sql = "SELECT 
                        l.id, 
                        l.label_name, 
                        l.label_color,
                        l.created_at,
                        COUNT(uea.email_id) as count
                    FROM labels l
                    LEFT JOIN user_email_access uea ON l.id = uea.label_id 
                        AND uea.user_id = :user_id
                        AND uea.is_deleted = 0
                    WHERE l.user_email = :user_email
                    GROUP BY l.id, l.label_name, l.label_color, l.created_at
                    ORDER BY l.label_name ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':user_id' => $userId,
                ':user_email' => $userEmail
            ]);
        } else {
            $sql = "SELECT 
                        id, 
                        label_name, 
                        label_color,
                        created_at,
                        0 as count
                    FROM labels
                    WHERE user_email = :user_email
                    ORDER BY label_name ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':user_email' => $userEmail]);
        }
        
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
        if (!$pdo) {
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

/**
 * Update email label
 */
function updateEmailLabel($emailId, $userEmail, $labelId) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            return false;
        }
        
        $userId = getUserId($pdo, $userEmail);
        if (!$userId) {
            return false;
        }
        
        $sql = "UPDATE user_email_access 
                SET label_id = ? 
                WHERE email_id = ? AND user_id = ?";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$labelId, $emailId, $userId]);
        
    } catch (PDOException $e) {
        error_log("Error updating email label: " . $e->getMessage());
        return false;
    }
}

// ==================== FILE ENCRYPTION FUNCTIONS ====================

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
        
        // Combine IV and encrypted data
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
 * Load IMAP config to session
 */
function loadImapConfigToSession($email, $password) {
    // Ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $_SESSION['imap_configured'] = true;
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
 * Get count of emails for a user that have no label assigned
 */
function getUnlabeledEmailCount($userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            return 0;
        }
        
        $userId = getUserId($pdo, $userEmail);
        if (!$userId) {
            return 0;
        }
        
        // Count entries in user_email_access where label_id is NULL
        $sql = "SELECT COUNT(*) as count 
                FROM user_email_access 
                WHERE user_id = :user_id 
                AND label_id IS NULL 
                AND is_deleted = 0";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch();
        
        return $result['count'] ?? 0;
        
    } catch (PDOException $e) {
        error_log("Error fetching unlabeled count: " . $e->getMessage());
        return 0;
    }
}

// Enable error display for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>