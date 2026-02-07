<?php
// db_config.php - Database configuration for sent emails tracking

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
                 article_title, message_body, attachment_names, sent_at) 
                VALUES 
                (:sender_email, :recipient_email, :cc_list, :bcc_list, :subject, 
                 :article_title, :message_body, :attachment_names, NOW())";
        
        $stmt = $pdo->prepare($sql);
        
        $result = $stmt->execute([
            ':sender_email' => $data['sender_email'],
            ':recipient_email' => $data['recipient_email'],
            ':cc_list' => $data['cc_list'],
            ':bcc_list' => $data['bcc_list'],
            ':subject' => $data['subject'],
            ':article_title' => $data['article_title'],
            ':message_body' => $data['message_body'],
            ':attachment_names' => $data['attachment_names']
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
 * Get all sent emails for current user
 */
function getSentEmails($userEmail, $limit = 100, $offset = 0) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            return [];
        }
        
        $sql = "SELECT * FROM sent_emails 
                WHERE sender_email = :sender_email 
                ORDER BY sent_at DESC 
                LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':sender_email', $userEmail, PDO::PARAM_STR);
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
 * Get count of sent emails for current user
 */
function getSentEmailCount($userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            return 0;
        }
        
        $sql = "SELECT COUNT(*) as count FROM sent_emails 
                WHERE sender_email = :sender_email";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':sender_email' => $userEmail]);
        
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
        
    } catch (PDOException $e) {
        error_log("Error counting sent emails: " . $e->getMessage());
        return 0;
    }
}
?>