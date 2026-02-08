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
?>