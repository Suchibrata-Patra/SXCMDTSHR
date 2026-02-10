<?php
/**
 * process_bulk_mail.php - Backend processor for bulk email sending
 * Handles CSV upload, queue management, and email processing
 */

session_start();
require_once 'db_config.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Security check
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$pdo = getDatabaseConnection();
$userEmail = $_SESSION['smtp_user'];
$userId = getUserId($pdo, $userEmail);

if (!$userId) {
    $userId = createUserIfNotExists($pdo, $userEmail, null);
}

$action = $_GET['action'] ?? 'status';

try {
    switch ($action) {
        case 'upload':
            handleCSVUpload($pdo, $userId);
            break;
        
        case 'process':
            processNextEmail($pdo, $userId);
            break;
        
        case 'status':
            getQueueStatus($pdo, $userId);
            break;
        
        case 'clear':
            clearQueue($pdo, $userId);
            break;
        
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Bulk mail error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Handle CSV upload and populate queue
 */
function handleCSVUpload($pdo, $userId) {
    if (!isset($_FILES['csv_file'])) {
        throw new Exception('No CSV file uploaded');
    }

    $csvFile = $_FILES['csv_file'];
    
    if ($csvFile['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload error');
    }

    // Read CSV file
    $handle = fopen($csvFile['tmp_name'], 'r');
    if (!$handle) {
        throw new Exception('Could not open CSV file');
    }

    // Read header row
    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        throw new Exception('CSV file is empty');
    }

    // Validate required columns
    $requiredColumns = [
        'mail_id', 'receiver_name', 'Mail_Subject', 'Article_Title',
        'Personalised_message', 'closing_wish', 'Name', 'Designation',
        'Additional_information', 'Attachments'
    ];

    foreach ($requiredColumns as $col) {
        if (!in_array($col, $header)) {
            fclose($handle);
            throw new Exception("Missing required column: $col");
        }
    }

    // Get column indices
    $columnMap = array_flip($header);

    // Get attachment ID if provided
    $attachmentId = !empty($_POST['attachment_id']) ? intval($_POST['attachment_id']) : null;

    // Ensure queue table exists
    createQueueTable($pdo);

    // Clear existing pending/failed entries for this user
    $stmt = $pdo->prepare("DELETE FROM bulk_mail_queue WHERE user_id = ? AND status IN ('pending', 'failed')");
    $stmt->execute([$userId]);

    // Insert CSV rows into queue
    $insertCount = 0;
    $batchUuid = generateUuidV4();

    $insertStmt = $pdo->prepare("
        INSERT INTO bulk_mail_queue (
            user_id, batch_uuid, recipient_email, recipient_name,
            subject, article_title, message_content, closing_wish,
            sender_name, sender_designation, additional_info,
            attachment_id, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");

    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) < count($header)) {
            continue; // Skip incomplete rows
        }

        try {
            $insertStmt->execute([
                $userId,
                $batchUuid,
                trim($row[$columnMap['mail_id']]),
                trim($row[$columnMap['receiver_name']]),
                trim($row[$columnMap['Mail_Subject']]),
                trim($row[$columnMap['Article_Title']]),
                trim($row[$columnMap['Personalised_message']]),
                trim($row[$columnMap['closing_wish']]),
                trim($row[$columnMap['Name']]),
                trim($row[$columnMap['Designation']]),
                trim($row[$columnMap['Additional_information']]),
                $attachmentId
            ]);
            $insertCount++;
        } catch (Exception $e) {
            error_log("Error inserting row: " . $e->getMessage());
        }
    }

    fclose($handle);

    echo json_encode([
        'success' => true,
        'message' => "Added $insertCount emails to queue",
        'count' => $insertCount,
        'batch_uuid' => $batchUuid
    ]);
}

/**
 * Process next email in queue
 */
function processNextEmail($pdo, $userId) {
    // Get next pending email
    $stmt = $pdo->prepare("
        SELECT * FROM bulk_mail_queue
        WHERE user_id = ? AND status = 'pending'
        ORDER BY created_at ASC
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$userId]);
    $queueItem = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$queueItem) {
        // No more emails to process
        $status = getQueueStatusData($pdo, $userId);
        echo json_encode([
            'success' => true,
            'has_more' => false,
            'pending' => $status['pending'],
            'processing' => $status['processing'],
            'completed' => $status['completed'],
            'failed' => $status['failed'],
            'total' => $status['total']
        ]);
        return;
    }

    // Mark as processing
    $updateStmt = $pdo->prepare("UPDATE bulk_mail_queue SET status = 'processing', processing_started_at = NOW() WHERE id = ?");
    $updateStmt->execute([$queueItem['id']]);

    // Send the email
    $result = sendBulkEmail($pdo, $queueItem);

    if ($result['success']) {
        // Mark as completed
        $updateStmt = $pdo->prepare("
            UPDATE bulk_mail_queue 
            SET status = 'completed', 
                completed_at = NOW(),
                sent_email_id = ?
            WHERE id = ?
        ");
        $updateStmt->execute([$result['email_id'], $queueItem['id']]);
    } else {
        // Mark as failed
        $updateStmt = $pdo->prepare("
            UPDATE bulk_mail_queue 
            SET status = 'failed', 
                error_message = ?,
                completed_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$result['error'], $queueItem['id']]);
    }

    // Get updated status
    $status = getQueueStatusData($pdo, $userId);

    echo json_encode([
        'success' => true,
        'has_more' => $status['pending'] > 0,
        'pending' => $status['pending'],
        'processing' => $status['processing'],
        'completed' => $status['completed'],
        'failed' => $status['failed'],
        'total' => $status['total'],
        'current_email' => [
            'recipient' => $queueItem['recipient_email'],
            'subject' => $queueItem['subject']
        ],
        'last_result' => [
            'recipient' => $queueItem['recipient_email'],
            'status' => $result['success'] ? 'completed' : 'failed',
            'error_message' => $result['error'] ?? null
        ]
    ]);
}

/**
 * Send a single bulk email
 */
function sendBulkEmail($pdo, $queueItem) {
    try {
        $mail = new PHPMailer(true);
        
        // SMTP Configuration
        $mail->isSMTP();
        $mail->SMTPDebug = 0;
        $mail->Host = "smtp.hostinger.com";
        $mail->SMTPAuth = true;
        $mail->Username = $_SESSION['smtp_user'];
        $mail->Password = $_SESSION['smtp_pass'];
        $mail->Port = 465;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        
        $settings = $_SESSION['user_settings'] ?? [];
        $displayName = !empty($settings['display_name']) ? $settings['display_name'] : "St. Xavier's College";
        
        $mail->setFrom($_SESSION['smtp_user'], $displayName);
        
        // Set recipient
        $recipient = filter_var(trim($queueItem['recipient_email']), FILTER_SANITIZE_EMAIL);
        if (!$recipient || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid recipient email address");
        }
        
        $mail->addAddress($recipient);
        
        // Handle attachment
        if (!empty($queueItem['attachment_id'])) {
            $attachmentId = intval($queueItem['attachment_id']);
            $stmt = $pdo->prepare("SELECT * FROM attachments WHERE id = ?");
            $stmt->execute([$attachmentId]);
            $attachment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($attachment) {
                $uploadDir = 'uploads/attachments/';
                $fullFilePath = $uploadDir . $attachment['storage_path'];
                
                if (file_exists($fullFilePath)) {
                    $mail->addAttachment($fullFilePath, $attachment['original_filename']);
                } else {
                    error_log("Attachment file not found: $fullFilePath");
                }
            }
        }
        
        // Load email template
        $templatePath = __DIR__ . '/templates/template1.html';
        
        if (!file_exists($templatePath)) {
            throw new Exception("Email template not found");
        }
        
        $emailTemplate = file_get_contents($templatePath);
        
        // Replace placeholders
        $emailBody = str_replace([
            '{{articletitle}}',
            '{{MESSAGE}}',
            '{{SIGNATURE_WISH}}',
            '{{SIGNATURE_NAME}}',
            '{{SIGNATURE_DESIGNATION}}',
            '{{SIGNATURE_EXTRA}}'
        ], [
            htmlspecialchars($queueItem['article_title'], ENT_QUOTES, 'UTF-8'),
            nl2br(htmlspecialchars($queueItem['message_content'], ENT_QUOTES, 'UTF-8')),
            htmlspecialchars($queueItem['closing_wish'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($queueItem['sender_name'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($queueItem['sender_designation'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($queueItem['additional_info'], ENT_QUOTES, 'UTF-8')
        ], $emailTemplate);
        
        // Set email content
        $mail->isHTML(true);
        $mail->Subject = $queueItem['subject'];
        $mail->Body = $emailBody;
        $mail->AltBody = strip_tags($queueItem['message_content']);
        
        // Send email
        if (!$mail->send()) {
            throw new Exception("Mailer Error: " . $mail->ErrorInfo);
        }
        
        // Save to database
        $emailUuid = generateUuidV4();
        $emailData = [
            'email_uuid' => $emailUuid,
            'sender_email' => $_SESSION['smtp_user'],
            'sender_name' => $displayName,
            'recipient_email' => $recipient,
            'cc_list' => null,
            'bcc_list' => null,
            'subject' => $queueItem['subject'],
            'body_text' => strip_tags($queueItem['message_content']),
            'body_html' => $emailBody,
            'article_title' => $queueItem['article_title'],
            'email_type' => 'sent',
            'has_attachments' => !empty($queueItem['attachment_id']) ? 1 : 0
        ];
        
        $emailId = saveEmailToDatabase($pdo, $emailData);
        
        if ($emailId) {
            createEmailAccess($pdo, $emailId, $queueItem['user_id'], 'sender', null);
        }
        
        return [
            'success' => true,
            'email_id' => $emailId
        ];
        
    } catch (Exception $e) {
        error_log("Error sending bulk email: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Get queue status
 */
function getQueueStatus($pdo, $userId) {
    $status = getQueueStatusData($pdo, $userId);
    echo json_encode([
        'success' => true,
        'pending' => $status['pending'],
        'processing' => $status['processing'],
        'completed' => $status['completed'],
        'failed' => $status['failed'],
        'total' => $status['total']
    ]);
}

/**
 * Get queue status data
 */
function getQueueStatusData($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
            COUNT(*) as total
        FROM bulk_mail_queue
        WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'pending' => intval($result['pending'] ?? 0),
        'processing' => intval($result['processing'] ?? 0),
        'completed' => intval($result['completed'] ?? 0),
        'failed' => intval($result['failed'] ?? 0),
        'total' => intval($result['total'] ?? 0)
    ];
}

/**
 * Clear queue
 */
function clearQueue($pdo, $userId) {
    $stmt = $pdo->prepare("DELETE FROM bulk_mail_queue WHERE user_id = ? AND status IN ('pending', 'failed')");
    $stmt->execute([$userId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Queue cleared'
    ]);
}

/**
 * Create queue table if it doesn't exist
 */
function createQueueTable($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS bulk_mail_queue (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        batch_uuid VARCHAR(36) NOT NULL,
        recipient_email VARCHAR(255) NOT NULL,
        recipient_name VARCHAR(255),
        subject VARCHAR(500) NOT NULL,
        article_title VARCHAR(500) NOT NULL,
        message_content TEXT NOT NULL,
        closing_wish VARCHAR(255),
        sender_name VARCHAR(255),
        sender_designation VARCHAR(255),
        additional_info VARCHAR(500),
        attachment_id INT NULL,
        status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
        error_message TEXT NULL,
        sent_email_id INT NULL,
        created_at DATETIME NOT NULL,
        processing_started_at DATETIME NULL,
        completed_at DATETIME NULL,
        INDEX idx_user_status (user_id, status),
        INDEX idx_batch (batch_uuid),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
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
?>