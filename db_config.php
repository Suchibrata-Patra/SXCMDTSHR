<?php
/**
 * db_config.php - Database Configuration and Helper Functions
 * Updated for unified email and attachment tracking system
 */

function getDatabaseConnection() {
    // Direct database credentials
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
function createUserIfNotExists($pdo, $email, $fullName = null) {
    try {
        $existingId = getUserId($pdo, $email);
        if ($existingId) {
            return $existingId;
        }
        
        $uuid = generateUuidV4();
        $stmt = $pdo->prepare("
            INSERT INTO users (user_uuid, email, full_name, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$uuid, $email, $fullName]);
        
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
        $stmt = $pdo->prepare("
            INSERT INTO emails (
                email_uuid, message_id, sender_email, sender_name,
                recipient_email, cc_list, bcc_list, reply_to,
                subject, body_text, body_html, article_title,
                email_type, has_attachments, email_date, sent_at, created_at
            ) VALUES (
                :email_uuid, :message_id, :sender_email, :sender_name,
                :recipient_email, :cc_list, :bcc_list, :reply_to,
                :subject, :body_text, :body_html, :article_title,
                :email_type, :has_attachments, NOW(), NOW(), NOW()
            )
        ");
        
        $result = $stmt->execute([
            ':email_uuid' => $emailData['email_uuid'],
            ':message_id' => $emailData['message_id'] ?? null,
            ':sender_email' => $emailData['sender_email'],
            ':sender_name' => $emailData['sender_name'] ?? null,
            ':recipient_email' => $emailData['recipient_email'],
            ':cc_list' => $emailData['cc_list'] ?? null,
            ':bcc_list' => $emailData['bcc_list'] ?? null,
            ':reply_to' => $emailData['reply_to'] ?? null,
            ':subject' => $emailData['subject'],
            ':body_text' => $emailData['body_text'] ?? null,
            ':body_html' => $emailData['body_html'] ?? null,
            ':article_title' => $emailData['article_title'] ?? null,
            ':email_type' => $emailData['email_type'] ?? 'sent',
            ':has_attachments' => $emailData['has_attachments'] ?? 0
        ]);
        
        if ($result) {
            return $pdo->lastInsertId();
        }
        
        return null;
        
    } catch (PDOException $e) {
        error_log("Error saving email: " . $e->getMessage());
        return null;
    }
}

/**
 * Create email access record for user
 */
function createEmailAccess($pdo, $emailId, $userId, $accessType = 'recipient', $labelId = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_email_access (
                email_id, user_id, access_type, label_id,
                is_deleted, created_at
            ) VALUES (?, ?, ?, ?, 0, NOW())
        ");
        
        return $stmt->execute([$emailId, $userId, $accessType, $labelId]);
        
    } catch (PDOException $e) {
        error_log("Error creating email access: " . $e->getMessage());
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
        
        // Get user ID
        $userId = getUserId($pdo, $userEmail);
        if (!$userId) {
            return [];
        }
        
        // Use the view for sent emails
        $sql = "SELECT * FROM v_user_sent 
                WHERE user_id = :user_id";
        
        $params = [':user_id' => $userId];
        
        // Add search filters
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
        
        if (!empty($filters['subject'])) {
            $sql .= " AND subject LIKE :subject";
            $params[':subject'] = '%' . $filters['subject'] . '%';
        }
        
        if (!empty($filters['label_id'])) {
            if ($filters['label_id'] === 'unlabeled') {
                $sql .= " AND label_id IS NULL";
            } else {
                $sql .= " AND label_id = :label_id";
                $params[':label_id'] = $filters['label_id'];
            }
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(sent_at) >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(sent_at) <= :date_to";
            $params[':date_to'] = $filters['date_to'];
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

/**
 * Get count of sent emails
 */
function getSentEmailCount($userEmail, $filters = []) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            return 0;
        }
        
        $userId = getUserId($pdo, $userEmail);
        if (!$userId) {
            return 0;
        }
        
        $sql = "SELECT COUNT(*) as count FROM v_user_sent 
                WHERE user_id = :user_id";
        
        $params = [':user_id' => $userId];
        
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
        
        if (!empty($filters['subject'])) {
            $sql .= " AND subject LIKE :subject";
            $params[':subject'] = '%' . $filters['subject'] . '%';
        }
        
        if (!empty($filters['label_id'])) {
            if ($filters['label_id'] === 'unlabeled') {
                $sql .= " AND label_id IS NULL";
            } else {
                $sql .= " AND label_id = :label_id";
                $params[':label_id'] = $filters['label_id'];
            }
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(sent_at) >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(sent_at) <= :date_to";
            $params[':date_to'] = $filters['date_to'];
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
 * Get label counts for a user
 * FIXED: Works even if user doesn't exist in users table
 */
function getLabelCounts($userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            error_log("getLabelCounts: Database connection failed");
            return [];
        }
        
        // Get user ID (but don't fail if user doesn't exist)
        $userId = getUserId($pdo, $userEmail);
        
        // Build query based on whether user exists
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
            // User doesn't exist in users table, just show labels without email counts
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
        
        $results = $stmt->fetchAll();
        error_log("getLabelCounts: Found " . count($results) . " labels for user: $userEmail (userId: " . ($userId ?? 'NULL') . ")");
        
        return $results;
        
    } catch (PDOException $e) {
        error_log("Error fetching label counts: " . $e->getMessage());
        error_log("SQL Error: " . print_r($e->errorInfo, true));
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
        $stmt = $pdo->prepare("SELECT id FROM labels WHERE user_email = :user_email AND label_name = :label_name");
        $stmt->execute([
            ':user_email' => $userEmail,
            ':label_name' => $labelName
        ]);
        
        if ($stmt->fetch()) {
            return ['error' => 'Label already exists'];
        }
        
        $sql = "INSERT INTO labels (user_email, label_name, label_color, created_at) 
                VALUES (:user_email, :label_name, :label_color, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':user_email' => $userEmail,
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
 * Update a label
 */
function updateLabel($labelId, $userEmail, $labelName, $labelColor) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            return false;
        }
        
        $sql = "UPDATE labels 
                SET label_name = :label_name, label_color = :label_color 
                WHERE id = :id AND user_email = :user_email";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':id' => $labelId,
            ':user_email' => $userEmail,
            ':label_name' => $labelName,
            ':label_color' => $labelColor
        ]);
        
    } catch (PDOException $e) {
        error_log("Error updating label: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete a label
 */
function deleteLabel($labelId, $userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            return false;
        }
        
        // First, remove the label from all emails
        $stmt = $pdo->prepare("UPDATE user_email_access SET label_id = NULL WHERE label_id = :label_id");
        $stmt->execute([':label_id' => $labelId]);
        
        // Then delete the label
        $sql = "DELETE FROM labels WHERE id = :id AND user_email = :user_email";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':id' => $labelId,
            ':user_email' => $userEmail
        ]);
        
    } catch (PDOException $e) {
        error_log("Error deleting label: " . $e->getMessage());
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
                SET label_id = :label_id 
                WHERE email_id = :email_id AND user_id = :user_id";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':email_id' => $emailId,
            ':user_id' => $userId,
            ':label_id' => $labelId
        ]);
        
    } catch (PDOException $e) {
        error_log("Error updating email label: " . $e->getMessage());
        return false;
    }
}

/**
 * Get unlabeled email count
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
        
        $sql = "SELECT COUNT(*) as count FROM user_email_access 
                WHERE user_id = :user_id 
                AND label_id IS NULL 
                AND is_deleted = 0";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
        
    } catch (PDOException $e) {
        error_log("Error counting unlabeled emails: " . $e->getMessage());
        return 0;
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

// ==================== BACKWARD COMPATIBILITY ====================
// Keep old function for backward compatibility during transition

/**
 * @deprecated Use saveEmailToDatabase() and createEmailAccess() instead
 */
function saveSentEmail($data) {
    error_log("DEPRECATED: saveSentEmail() called - please update to use new email system");
    return false;
}
?><?php
/**
 * db_config.php - Database Configuration and Helper Functions
 */

// Encryption key for file IDs - CHANGE THIS IN PRODUCTION!
define('FILE_ENCRYPTION_KEY', 'your-32-char-secret-key-here!!');
define('FILE_ENCRYPTION_METHOD', 'AES-256-CBC');

/**
 * Get PDO database connection
 */
function getDatabaseConnection() {
    static $pdo = null;
    
    if ($pdo !== null) {
        return $pdo;
    }
    
    try {
        // Database credentials - update these with your actual credentials
        $host = env('DB_HOST', 'localhost');
        $dbname = env('DB_NAME', 'u955994755_SXC_MDTS');
        $username = env('DB_USER', 'root');
        $password = env('DB_PASS', '');
        
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

/**
 * Get user ID by email
 */
function getUserId($pdo, $email) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $result = $stmt->fetch();
        
        return $result ? $result['id'] : null;
    } catch (Exception $e) {
        error_log("Error getting user ID: " . $e->getMessage());
        return null;
    }
}

/**
 * Get user ID by email (alias for compatibility)
 */
function getUserIdByEmail($pdo, $email) {
    return getUserId($pdo, $email);
}

/**
 * Create user if not exists
 */
function createUserIfNotExists($pdo, $email, $displayName = null) {
    try {
        // Check if user exists
        $userId = getUserId($pdo, $email);
        
        if ($userId) {
            return $userId;
        }
        
        // Create new user
        $stmt = $pdo->prepare("
            INSERT INTO users (email, display_name, created_at) 
            VALUES (?, ?, NOW())
        ");
        
        $stmt->execute([$email, $displayName ?? $email]);
        
        return $pdo->lastInsertId();
        
    } catch (Exception $e) {
        error_log("Error creating user: " . $e->getMessage());
        return null;
    }
}

/**
 * Save email to database
 */
function saveEmailToDatabase($pdo, $emailData) {
    try {
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
        
        return $pdo->lastInsertId();
        
    } catch (Exception $e) {
        error_log("Error saving email: " . $e->getMessage());
        return null;
    }
}

/**
 * Create email access record
 */
function createEmailAccess($pdo, $emailId, $userId, $accessType = 'sender', $labelId = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO email_access (
                email_id, user_id, access_type, label_id, created_at
            ) VALUES (?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([$emailId, $userId, $accessType, $labelId]);
        
        return true;
        
    } catch (Exception $e) {
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
            // Update user_attachment_access with email_uuid
            $stmt = $pdo->prepare("
                UPDATE user_attachment_access 
                SET email_uuid = ?, sender_id = ?
                WHERE user_id = ? AND attachment_id = ?
            ");
            
            $stmt->execute([$emailUuid, $senderId, $senderId, $attachmentId]);
            
            // Create email-attachment link
            $stmt = $pdo->prepare("
                INSERT INTO email_attachments (email_id, attachment_id, created_at)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE email_id = email_id
            ");
            
            $stmt->execute([$emailId, $attachmentId]);
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error linking attachments: " . $e->getMessage());
        return false;
    }
}

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

/**
 * Load IMAP config to session (placeholder)
 */
function loadImapConfigToSession($email, $password) {
    // Placeholder - implement IMAP settings loading if needed
    $_SESSION['imap_configured'] = true;
}

/**
 * Parse email list from comma/semicolon separated string
 */
function parseEmailList($emailString) {
    // Split by comma or semicolon
    $emails = preg_split('/[;,]+/', $emailString);
    
    // Trim and filter
    $emails = array_map('trim', $emails);
    $emails = array_filter($emails);
    
    return $emails;
}
?><?php
/**
 * db_config.php - Database Configuration and Helper Functions
 */

// Encryption key for file IDs - CHANGE THIS IN PRODUCTION!
define('FILE_ENCRYPTION_KEY', 'your-32-char-secret-key-here!!');
define('FILE_ENCRYPTION_METHOD', 'AES-256-CBC');

/**
 * Get PDO database connection
 */
function getDatabaseConnection() {
    static $pdo = null;
    
    if ($pdo !== null) {
        return $pdo;
    }
    
    try {
        // Database credentials - update these with your actual credentials
        $host = env('DB_HOST', 'localhost');
        $dbname = env('DB_NAME', 'u955994755_SXC_MDTS');
        $username = env('DB_USER', 'root');
        $password = env('DB_PASS', '');
        
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

/**
 * Get user ID by email
 */
function getUserId($pdo, $email) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $result = $stmt->fetch();
        
        return $result ? $result['id'] : null;
    } catch (Exception $e) {
        error_log("Error getting user ID: " . $e->getMessage());
        return null;
    }
}

/**
 * Get user ID by email (alias for compatibility)
 */
function getUserIdByEmail($pdo, $email) {
    return getUserId($pdo, $email);
}

/**
 * Create user if not exists
 */
function createUserIfNotExists($pdo, $email, $displayName = null) {
    try {
        // Check if user exists
        $userId = getUserId($pdo, $email);
        
        if ($userId) {
            return $userId;
        }
        
        // Create new user
        $stmt = $pdo->prepare("
            INSERT INTO users (email, display_name, created_at) 
            VALUES (?, ?, NOW())
        ");
        
        $stmt->execute([$email, $displayName ?? $email]);
        
        return $pdo->lastInsertId();
        
    } catch (Exception $e) {
        error_log("Error creating user: " . $e->getMessage());
        return null;
    }
}

/**
 * Save email to database
 */
function saveEmailToDatabase($pdo, $emailData) {
    try {
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
        
        return $pdo->lastInsertId();
        
    } catch (Exception $e) {
        error_log("Error saving email: " . $e->getMessage());
        return null;
    }
}

/**
 * Create email access record
 */
function createEmailAccess($pdo, $emailId, $userId, $accessType = 'sender', $labelId = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO email_access (
                email_id, user_id, access_type, label_id, created_at
            ) VALUES (?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([$emailId, $userId, $accessType, $labelId]);
        
        return true;
        
    } catch (Exception $e) {
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
            // Update user_attachment_access with email_uuid
            $stmt = $pdo->prepare("
                UPDATE user_attachment_access 
                SET email_uuid = ?, sender_id = ?
                WHERE user_id = ? AND attachment_id = ?
            ");
            
            $stmt->execute([$emailUuid, $senderId, $senderId, $attachmentId]);
            
            // Create email-attachment link
            $stmt = $pdo->prepare("
                INSERT INTO email_attachments (email_id, attachment_id, created_at)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE email_id = email_id
            ");
            
            $stmt->execute([$emailId, $attachmentId]);
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error linking attachments: " . $e->getMessage());
        return false;
    }
}

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

/**
 * Load IMAP config to session (placeholder)
 */
function loadImapConfigToSession($email, $password) {
    // Placeholder - implement IMAP settings loading if needed
    $_SESSION['imap_configured'] = true;
}

/**
 * Parse email list from comma/semicolon separated string
 */
function parseEmailList($emailString) {
    // Split by comma or semicolon
    $emails = preg_split('/[;,]+/', $emailString);
    
    // Trim and filter
    $emails = array_map('trim', $emails);
    $emails = array_filter($emails);
    
    return $emails;
}
?>