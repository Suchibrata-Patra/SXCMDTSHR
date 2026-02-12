<?php
/**
 * process_bulk_mail.php
 * 
 * Processes emails from bulk_mail_queue table and sends them using PHPMailer
 * Handles drive file attachments and provides detailed SMTP error logging
 */

session_start();
require_once 'vendor/autoload.php';
require_once 'config.php';
require_once 'db_config.php';

// Security check
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized - Please login'
    ]);
    exit();
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Set JSON header
header('Content-Type: application/json');

// Get action from request
$action = $_GET['action'] ?? '';

try {
    $pdo = getDatabaseConnection();
    
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Get current user from session
    $user_email = $_SESSION['smtp_user'] ?? null;
    $user_id = getUserId($pdo, $user_email);
    
    if (!$user_id) {
        throw new Exception('User not found');
    }
    
    switch ($action) {
        case 'status':
            // Get queue statistics
            $stmt = $pdo->prepare("
                SELECT 
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                FROM bulk_mail_queue
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'pending' => (int)($stats['pending'] ?? 0),
                'completed' => (int)($stats['completed'] ?? 0),
                'failed' => (int)($stats['failed'] ?? 0)
            ]);
            break;
            
        case 'queue_list':
            // Get queue list
            $stmt = $pdo->prepare("
                SELECT *
                FROM bulk_mail_queue
                WHERE user_id = ?
                ORDER BY 
                    CASE status
                        WHEN 'pending' THEN 1
                        WHEN 'processing' THEN 2
                        WHEN 'failed' THEN 3
                        WHEN 'completed' THEN 4
                    END,
                    created_at DESC
                LIMIT 200
            ");
            $stmt->execute([$user_id]);
            $queue = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'queue' => $queue
            ]);
            break;
            
        case 'process':
            // Process next email in queue
            $result = processNextEmail($pdo, $user_id);
            echo json_encode($result);
            break;
            
        case 'clear':
            // Clear pending emails
            $stmt = $pdo->prepare("
                DELETE FROM bulk_mail_queue
                WHERE user_id = ? AND status = 'pending'
            ");
            $stmt->execute([$user_id]);
            $deleted = $stmt->rowCount();
            
            echo json_encode([
                'success' => true,
                'message' => "Cleared {$deleted} pending emails from queue"
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Process the next pending email in the queue
 */
function processNextEmail($pdo, $user_id) {
    try {
        // Get next pending email
        $stmt = $pdo->prepare("
            SELECT * FROM bulk_mail_queue
            WHERE user_id = ? AND status = 'pending'
            ORDER BY created_at ASC
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        $queueItem = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$queueItem) {
            return [
                'success' => true,
                'message' => 'No pending emails in queue',
                'email_sent' => false
            ];
        }
        
        // Update status to processing
        $updateStmt = $pdo->prepare("
            UPDATE bulk_mail_queue
            SET status = 'processing', processing_started_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$queueItem['id']]);
        
        // Send email using PHPMailer
        $sendResult = sendBulkEmail($pdo, $queueItem);
        
        if ($sendResult['success']) {
            // Update status to completed
            $updateStmt = $pdo->prepare("
                UPDATE bulk_mail_queue
                SET status = 'completed', 
                    completed_at = NOW(),
                    sent_email_id = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$sendResult['sent_email_id'], $queueItem['id']]);
            
            return [
                'success' => true,
                'message' => 'Email sent successfully',
                'email_sent' => true,
                'recipient' => $queueItem['recipient_email']
            ];
        } else {
            // Update status to failed
            $updateStmt = $pdo->prepare("
                UPDATE bulk_mail_queue
                SET status = 'failed', 
                    completed_at = NOW(),
                    error_message = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$sendResult['error'], $queueItem['id']]);
            
            return [
                'success' => true,
                'message' => 'Email failed to send',
                'email_sent' => false,
                'error' => $sendResult['error'],
                'recipient' => $queueItem['recipient_email']
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Send email using PHPMailer with improved SMTP error handling
 */
function sendBulkEmail($pdo, $queueItem) {
    $mail = new PHPMailer(true);
    
    try {
        // ==================== SMTP Configuration ====================
        $mail->isSMTP();
        $mail->SMTPDebug = 0; // Set to 2 for debugging
        
        $settings = $_SESSION['user_settings'] ?? [];
        
        $mail->Host = "smtp.hostinger.com";
        $mail->SMTPAuth = true;
        $mail->Username = $_SESSION['smtp_user'];
        $mail->Password = $_SESSION['smtp_pass'];
        $mail->Port = 465;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        
        // SMTP options to improve delivery
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        $displayName = !empty($settings['display_name']) ? $settings['display_name'] : "St. Xavier's College";
        $mail->setFrom($_SESSION['smtp_user'], $displayName);
        
        // ==================== Add Recipient ====================
        $recipient = filter_var(trim($queueItem['recipient_email']), FILTER_SANITIZE_EMAIL);
        
        if (!$recipient || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid recipient email address: " . $queueItem['recipient_email']);
        }
        
        // Validate recipient email format more strictly
        if (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $recipient)) {
            throw new Exception("Invalid email format: " . $recipient);
        }
        
        $mail->addAddress($recipient);
        
        // ==================== Attachment Handling ====================
        // Handle both drive files and uploaded attachments
        if (!empty($queueItem['drive_file_path'])) {
            // Drive file attachment
            $filePath = $queueItem['drive_file_path'];
            
            if (file_exists($filePath)) {
                $fileName = basename($filePath);
                $mail->addAttachment($filePath, $fileName);
                error_log("Added drive file attachment: $filePath");
            } else {
                error_log("WARNING: Drive file not found: $filePath");
            }
        } elseif (!empty($queueItem['attachment_id'])) {
            // Regular uploaded attachment
            $stmt = $pdo->prepare("SELECT * FROM attachments WHERE id = ?");
            $stmt->execute([$queueItem['attachment_id']]);
            $attachment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($attachment) {
                $filePath = $attachment['storage_type'] === 'drive' 
                    ? $attachment['storage_path']  // Drive file uses full path
                    : 'uploads/attachments/' . $attachment['storage_path'];  // Uploaded file
                
                if (file_exists($filePath)) {
                    $mail->addAttachment($filePath, $attachment['original_filename']);
                    error_log("Added attachment: $filePath");
                } else {
                    error_log("WARNING: Attachment file not found: $filePath");
                }
            }
        }
        
        // ==================== Email Content ====================
        $subject = $queueItem['subject'] ?: 'Official Communication';
        $articleTitle = $queueItem['article_title'] ?: 'Official Communication';
        $messageContent = $queueItem['message_content'] ?: '';
        $closingWish = $queueItem['closing_wish'] ?: 'Best Regards,';
        $senderName = $queueItem['sender_name'] ?: 'St. Xavier\'s College';
        $senderDesignation = $queueItem['sender_designation'] ?: '';
        $additionalInfo = $queueItem['additional_info'] ?: 'St. Xavier\'s College (Autonomous), Kolkata';
        
        // ==================== Load Email Template ====================
        $templatePath = __DIR__ . '/templates/template1.html';
        
        if (!file_exists($templatePath)) {
            throw new Exception("Email template not found");
        }
        
        $emailTemplate = file_get_contents($templatePath);
        
        $emailBody = str_replace([
            '{{articletitle}}',
            '{{MESSAGE}}',
            '{{SIGNATURE_WISH}}',
            '{{SIGNATURE_NAME}}',
            '{{SIGNATURE_DESIGNATION}}',
            '{{SIGNATURE_EXTRA}}'
        ], [
            htmlspecialchars($articleTitle, ENT_QUOTES, 'UTF-8'),
            nl2br(htmlspecialchars($messageContent, ENT_QUOTES, 'UTF-8')),
            htmlspecialchars($closingWish, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($senderName, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($senderDesignation, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($additionalInfo, ENT_QUOTES, 'UTF-8')
        ], $emailTemplate);
        
        // ==================== Set Email Properties ====================
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body = $emailBody;
        
        // Alternative plain text body
        $mail->AltBody = strip_tags(str_replace('<br>', "\n", $emailBody));
        
        // Send email
        if (!$mail->send()) {
            throw new Exception("SMTP Error: " . $mail->ErrorInfo);
        }
        
        // ==================== Save to Database ====================
        $emailUuid = generateUuidV4();
        
        // Save to sent_emails_new table
        $stmt = $pdo->prepare("
            INSERT INTO sent_emails_new (
                email_uuid, user_id, sender_email, recipient_email, recipient_name,
                subject, article_title, message_content, 
                closing_wish, sender_name, sender_designation, additional_info,
                cc_emails, bcc_emails, attachment_count, 
                sent_at, created_at
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?,
                '', '', ?, 
                NOW(), NOW()
            )
        ");
        
        $attachmentCount = (!empty($queueItem['drive_file_path']) || !empty($queueItem['attachment_id'])) ? 1 : 0;
        
        $stmt->execute([
            $emailUuid,
            getUserId($pdo, $_SESSION['smtp_user']),
            $_SESSION['smtp_user'],
            $recipient,
            $queueItem['recipient_name'] ?? '',
            $subject,
            $articleTitle,
            $messageContent,
            $closingWish,
            $senderName,
            $senderDesignation,
            $additionalInfo,
            $attachmentCount
        ]);
        
        $sentEmailId = $pdo->lastInsertId();
        
        // Link attachment if exists
        if (!empty($queueItem['drive_file_path']) || !empty($queueItem['attachment_id'])) {
            $filePath = '';
            $fileName = '';
            $fileSize = 0;
            $mimeType = 'application/octet-stream';
            $extension = '';
            
            if (!empty($queueItem['drive_file_path'])) {
                $filePath = $queueItem['drive_file_path'];
                $fileName = basename($filePath);
                $fileSize = file_exists($filePath) ? filesize($filePath) : 0;
                $extension = pathinfo($fileName, PATHINFO_EXTENSION);
            } elseif (!empty($queueItem['attachment_id'])) {
                $stmt = $pdo->prepare("SELECT * FROM attachments WHERE id = ?");
                $stmt->execute([$queueItem['attachment_id']]);
                $attachment = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($attachment) {
                    $fileName = $attachment['original_filename'];
                    $filePath = $attachment['storage_path'];
                    $fileSize = $attachment['file_size'];
                    $mimeType = $attachment['mime_type'];
                    $extension = $attachment['file_extension'];
                }
            }
            
            if ($fileName) {
                $stmt = $pdo->prepare("
                    INSERT INTO sent_email_attachments_new (
                        sent_email_id, email_uuid, original_filename, 
                        stored_filename, file_path, file_size, 
                        mime_type, file_extension, uploaded_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $sentEmailId,
                    $emailUuid,
                    $fileName,
                    basename($filePath),
                    $filePath,
                    $fileSize,
                    $mimeType,
                    $extension
                ]);
            }
        }
        
        return [
            'success' => true,
            'sent_email_id' => $sentEmailId
        ];
        
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
        
        // Parse SMTP errors for common issues
        if (strpos($errorMessage, 'SMTP Error') !== false) {
            // SMTP Error: data not accepted - usually means:
            // 1. Recipient email is invalid or blocked
            // 2. Email content triggered spam filters
            // 3. Attachment is too large or has blocked extension
            // 4. Daily sending limit reached
            // 5. Sender reputation issue
            
            if (strpos($errorMessage, 'data not accepted') !== false) {
                $errorMessage .= " | Possible causes: Invalid recipient, spam filters, blocked attachment, or daily limit reached.";
            }
        }
        
        error_log("Bulk email error for {$queueItem['recipient_email']}: " . $errorMessage);
        
        return [
            'success' => false,
            'error' => $errorMessage
        ];
    }
}
?>