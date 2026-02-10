<?php
/**
 * CORRECTED PHP PORTION for sent_history.php
 * Replace lines 1-250 with this corrected version
 */

session_start();
require 'config.php';
require 'db_config.php';

// Security check
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    header("Location: login.php");
    exit();
}

$userEmail = $_SESSION['smtp_user'];

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'fetch_messages':
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            
            $filters = [];
            if (isset($_GET['search'])) $filters['search'] = $_GET['search'];
            if (isset($_GET['recipient'])) $filters['recipient'] = $_GET['recipient'];
            if (isset($_GET['label_id'])) $filters['label_id'] = $_GET['label_id'];
            
            $messages = getSentEmails($userEmail, $limit, $offset, $filters);
            $total = getSentEmailCount($userEmail, $filters);
            
            echo json_encode([
                'success' => true,
                'messages' => $messages,
                'total' => $total
            ]);
            exit();
            
        case 'get_message':
            $messageId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            $message = getSentEmailById($messageId, $userEmail);
            
            if ($message) {
                // Get attachments if any
                $attachments = [];
                if ($message['has_attachments']) {
                    try {
                        $pdo = getDatabaseConnection();
                        $stmt = $pdo->prepare("
                            SELECT * FROM email_attachments 
                            WHERE email_id = ? 
                            ORDER BY created_at ASC
                        ");
                        $stmt->execute([$messageId]);
                        $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                        error_log("Error fetching attachments: " . $e->getMessage());
                    }
                }
                $message['attachments'] = $attachments;
                
                echo json_encode(['success' => true, 'message' => $message]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Message not found']);
            }
            exit();
            
        case 'delete':
            $messageId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            try {
                $pdo = getDatabaseConnection();
                $userId = getUserId($pdo, $userEmail);
                
                if ($userId) {
                    // Mark as deleted in user_email_access
                    $stmt = $pdo->prepare("
                        UPDATE user_email_access 
                        SET is_deleted = 1 
                        WHERE email_id = ? AND user_id = ? AND access_type = 'sender'
                    ");
                    $success = $stmt->execute([$messageId, $userId]);
                    echo json_encode(['success' => $success]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'User not found']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit();
            
        case 'get_counts':
            $total = getSentEmailCount($userEmail);
            $labeled = getSentEmailCount($userEmail, ['has_label' => true]);
            $unlabeled = getUnlabeledEmailCount($userEmail);
            
            echo json_encode([
                'success' => true,
                'total' => $total,
                'labeled' => $labeled,
                'unlabeled' => $unlabeled
            ]);
            exit();
    }
}

/**
 * Get sent emails for a user with filters
 */
function getSentEmails($userEmail, $limit = 50, $offset = 0, $filters = []) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return [];
        
        $userId = getUserId($pdo, $userEmail);
        if (!$userId) return [];
        
        $sql = "SELECT 
                    e.*,
                    uea.label_id,
                    l.name as label_name,
                    l.color as label_color,
                    (SELECT GROUP_CONCAT(original_filename SEPARATOR ', ')
                     FROM email_attachments ea
                     WHERE ea.email_id = e.id) as attachment_names,
                    (SELECT COUNT(*)
                     FROM email_attachments ea
                     WHERE ea.email_id = e.id) as attachment_count
                FROM emails e
                JOIN user_email_access uea ON e.id = uea.email_id
                LEFT JOIN labels l ON uea.label_id = l.id
                WHERE uea.user_id = :user_id
                AND uea.access_type = 'sender'
                AND uea.is_deleted = 0";
        
        $params = ['user_id' => $userId];
        
        // Apply search filter
        if (!empty($filters['search'])) {
            $sql .= " AND (e.recipient_email LIKE :search 
                        OR e.subject LIKE :search 
                        OR e.body_text LIKE :search 
                        OR e.article_title LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        // Apply recipient filter
        if (!empty($filters['recipient'])) {
            $sql .= " AND e.recipient_email LIKE :recipient";
            $params['recipient'] = '%' . $filters['recipient'] . '%';
        }
        
        // Apply subject filter
        if (!empty($filters['subject'])) {
            $sql .= " AND e.subject LIKE :subject";
            $params['subject'] = '%' . $filters['subject'] . '%';
        }
        
        // Apply label filter
        if (!empty($filters['label_id'])) {
            if ($filters['label_id'] === 'unlabeled') {
                $sql .= " AND uea.label_id IS NULL";
            } else {
                $sql .= " AND uea.label_id = :label_id";
                $params['label_id'] = $filters['label_id'];
            }
        }
        
        // Apply date range filters
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(e.sent_at) >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(e.sent_at) <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }
        
        $sql .= " ORDER BY e.sent_at DESC LIMIT :limit OFFSET :offset";
        
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
 * Get a specific sent email by ID
 */
function getSentEmailById($emailId, $userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return null;
        
        $userId = getUserId($pdo, $userEmail);
        if (!$userId) return null;
        
        $stmt = $pdo->prepare("
            SELECT 
                e.*,
                uea.label_id,
                l.name as label_name,
                l.color as label_color
            FROM emails e
            JOIN user_email_access uea ON e.id = uea.email_id
            LEFT JOIN labels l ON uea.label_id = l.id
            WHERE e.id = :email_id 
            AND uea.user_id = :user_id
            AND uea.access_type = 'sender'
            AND uea.is_deleted = 0
            LIMIT 1
        ");
        
        $stmt->execute([
            'email_id' => $emailId,
            'user_id' => $userId
        ]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error fetching sent email by ID: " . $e->getMessage());
        return null;
    }
}

// Continue with HTML portion from line 251 onwards...
?>