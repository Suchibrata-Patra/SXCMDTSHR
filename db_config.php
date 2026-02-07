<?php
// db_config.php - Enhanced Database configuration with label management

function env($key, $default = null) {
    return getenv($key) ?: $default;
}

function getDatabaseConnection() {
    // Using your actual database credentials
    $host = env("DB_HOST", "localhost");
    $dbname = env("DB_NAME", "u955994755_SXC_MDTS");
    $username = env("DB_USER", "u955994755_DB_supremacy");
    $password = env("DB_PASS", "sxccal.edu#MDTS@2026");
    
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

/**
 * Save sent email to database
 */
function saveSentEmail($data) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            error_log("Failed to save sent email: No database connection");
            return false;
        }
        
        $sql = "INSERT INTO sent_emails 
                (sender_email, recipient_email, cc_list, bcc_list, subject, 
                 article_title, message_body, attachment_names, label_id, sent_at) 
                VALUES 
                (:sender_email, :recipient_email, :cc_list, :bcc_list, :subject, 
                 :article_title, :message_body, :attachment_names, :label_id, NOW())";
        
        $stmt = $pdo->prepare($sql);
        
        $result = $stmt->execute([
            ':sender_email' => $data['sender_email'],
            ':recipient_email' => $data['recipient_email'],
            ':cc_list' => $data['cc_list'],
            ':bcc_list' => $data['bcc_list'],
            ':subject' => $data['subject'],
            ':article_title' => $data['article_title'],
            ':message_body' => $data['message_body'],
            ':attachment_names' => $data['attachment_names'],
            ':label_id' => $data['label_id'] ?? null
        ]);
        
        if ($result) {
            error_log("Email saved to database successfully. ID: " . $pdo->lastInsertId());
        }
        
        return $result;
        
    } catch (PDOException $e) {
        error_log("Error saving sent email: " . $e->getMessage());
        error_log("SQL State: " . $e->getCode());
        return false;
    }
}

/**
 * Get all sent emails for current user with optional filters
 */
function getSentEmails($userEmail, $limit = 100, $offset = 0, $filters = []) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            return [];
        }
        
        // Build the base query
        $sql = "SELECT se.*, el.label_name, el.label_color 
                FROM sent_emails se 
                LEFT JOIN email_labels el ON se.label_id = el.id 
                WHERE se.sender_email = :sender_email";
        
        $params = [':sender_email' => $userEmail];
        
        // Add search filters
        if (!empty($filters['search'])) {
            $sql .= " AND (
                se.recipient_email LIKE :search 
                OR se.subject LIKE :search 
                OR se.message_body LIKE :search
                OR se.article_title LIKE :search
            )";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        // Add recipient filter
        if (!empty($filters['recipient'])) {
            $sql .= " AND se.recipient_email LIKE :recipient";
            $params[':recipient'] = '%' . $filters['recipient'] . '%';
        }
        
        // Add subject filter
        if (!empty($filters['subject'])) {
            $sql .= " AND se.subject LIKE :subject";
            $params[':subject'] = '%' . $filters['subject'] . '%';
        }
        
        // Add label filter
        if (!empty($filters['label_id'])) {
            if ($filters['label_id'] === 'unlabeled') {
                $sql .= " AND se.label_id IS NULL";
            } else {
                $sql .= " AND se.label_id = :label_id";
                $params[':label_id'] = $filters['label_id'];
            }
        }
        
        // Add date range filter
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(se.sent_at) >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(se.sent_at) <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }
        
        // Add sorting
        $sql .= " ORDER BY se.sent_at DESC LIMIT :limit OFFSET :offset";
        
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
        error_log("Error fetching sent emails: " . $e->getMessage());
        return [];
    }
}

/**
 * Get count of sent emails for current user with optional filters
 */
function getSentEmailCount($userEmail, $filters = []) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            return 0;
        }
        
        $sql = "SELECT COUNT(*) as count FROM sent_emails se 
                WHERE se.sender_email = :sender_email";
        
        $params = [':sender_email' => $userEmail];
        
        // Add the same filters as getSentEmails
        if (!empty($filters['search'])) {
            $sql .= " AND (
                se.recipient_email LIKE :search 
                OR se.subject LIKE :search 
                OR se.message_body LIKE :search
                OR se.article_title LIKE :search
            )";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        if (!empty($filters['recipient'])) {
            $sql .= " AND se.recipient_email LIKE :recipient";
            $params[':recipient'] = '%' . $filters['recipient'] . '%';
        }
        
        if (!empty($filters['subject'])) {
            $sql .= " AND se.subject LIKE :subject";
            $params[':subject'] = '%' . $filters['subject'] . '%';
        }
        
        if (!empty($filters['label_id'])) {
            if ($filters['label_id'] === 'unlabeled') {
                $sql .= " AND se.label_id IS NULL";
            } else {
                $sql .= " AND se.label_id = :label_id";
                $params[':label_id'] = $filters['label_id'];
            }
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(se.sent_at) >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(se.sent_at) <= :date_to";
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

// ==================== LABEL MANAGEMENT FUNCTIONS ====================

/**
 * Get all labels for a user
 */
function getUserLabels($userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            return [];
        }
        
        $sql = "SELECT * FROM email_labels 
                WHERE user_email = :user_email 
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
 */
function getLabelCounts($userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            return [];
        }
        
        $sql = "SELECT 
                    el.id, 
                    el.label_name, 
                    el.label_color,
                    COUNT(se.id) as email_count
                FROM email_labels el
                LEFT JOIN sent_emails se ON el.id = se.label_id AND se.sender_email = :user_email
                WHERE el.user_email = :user_email
                GROUP BY el.id, el.label_name, el.label_color
                ORDER BY el.label_name ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_email' => $userEmail]);
        
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
        $stmt = $pdo->prepare("SELECT id FROM email_labels WHERE user_email = :user_email AND label_name = :label_name");
        $stmt->execute([
            ':user_email' => $userEmail,
            ':label_name' => $labelName
        ]);
        
        if ($stmt->fetch()) {
            return ['error' => 'Label already exists'];
        }
        
        $sql = "INSERT INTO email_labels (user_email, label_name, label_color) 
                VALUES (:user_email, :label_name, :label_color)";
        
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
        
        $sql = "UPDATE email_labels 
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
        $stmt = $pdo->prepare("UPDATE sent_emails SET label_id = NULL WHERE label_id = :label_id");
        $stmt->execute([':label_id' => $labelId]);
        
        // Then delete the label
        $sql = "DELETE FROM email_labels WHERE id = :id AND user_email = :user_email";
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
        
        $sql = "UPDATE sent_emails 
                SET label_id = :label_id 
                WHERE id = :id AND sender_email = :sender_email";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':id' => $emailId,
            ':sender_email' => $userEmail,
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
        
        $sql = "SELECT COUNT(*) as count FROM sent_emails 
                WHERE sender_email = :sender_email AND label_id IS NULL";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':sender_email' => $userEmail]);
        
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
        
    } catch (PDOException $e) {
        error_log("Error counting unlabeled emails: " . $e->getMessage());
        return 0;
    }
}

/**
 * Create default labels for new users
 */
function createDefaultLabels($userEmail) {
    $defaultLabels = [
        ['name' => 'Work', 'color' => '#0973dc'],
        ['name' => 'Personal', 'color' => '#34a853'],
        ['name' => 'Urgent', 'color' => '#ea4335'],
        ['name' => 'Marketing', 'color' => '#fbbc04'],
        ['name' => 'Archive', 'color' => '#5f6368']
    ];
    
    foreach ($defaultLabels as $label) {
        createLabel($userEmail, $label['name'], $label['color']);
    }
}
?>