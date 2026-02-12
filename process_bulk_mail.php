<?php
/**
 * process_bulk_mail.php - FIXED VERSION
 * 
 * Processes emails from bulk_mail_queue table with:
 * - Proper PHPMailer configuration (same as send.php)
 * - Complete database logging for all attempts
 * - Real-time progress tracking
 * - No silent failures
 */

session_start();

// Load dependencies
require_once 'db_config.php';

// Try to load vendor autoload if it exists
if (file_exists('vendor/autoload.php')) {
    require_once 'vendor/autoload.php';
} else {
    // Manual PHPMailer includes
    require_once 'PHPMailer/src/Exception.php';
    require_once 'PHPMailer/src/PHPMailer.php';
    require_once 'PHPMailer/src/SMTP.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Security check
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized - Please login'
    ]);
    exit();
}

// Set JSON header
header('Content-Type: application/json');

// Get action from request
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $pdo = getDatabaseConnection();
    
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Get current user
    $user_email = $_SESSION['smtp_user'];
    $user_password = $_SESSION['smtp_pass'];
    $user_id = getUserId($pdo, $user_email);
    
    if (!$user_id) {
        throw new Exception('User not found in database');
    }
    
    // Load user settings
    $settings = $_SESSION['user_settings'] ?? [];
    
    switch ($action) {
        case 'status':
            // Get queue statistics
            $stmt = $pdo->prepare("
                SELECT 
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
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
                'processing' => (int)($stats['processing'] ?? 0),
                'completed' => (int)($stats['completed'] ?? 0),
                'failed' => (int)($stats['failed'] ?? 0)
            ]);
            break;
            
        case 'queue_list':
            // Get queue list with pagination
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 200;
            
            $stmt = $pdo->prepare("
                SELECT 
                    id, recipient_email, recipient_name, subject, article_title,
                    status, error_message, created_at, processing_started_at, completed_at
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
                LIMIT ?
            ");
            $stmt->execute([$user_id, $limit]);
            $queue = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'queue' => $queue
            ]);
            break;
            
        case 'process':
            // Process ONE email and return result immediately
            $result = processNextEmail($pdo, $user_id, $user_email, $user_password, $settings);
            echo json_encode($result);
            break;
            
        case 'clear':
            // Clear all pending emails from queue
            $stmt = $pdo->prepare("
                DELETE FROM bulk_mail_queue
                WHERE user_id = ? AND status = 'pending'
            ");
            $stmt->execute([$user_id]);
            $deleted = $stmt->rowCount();
            
            echo json_encode([
                'success' => true,
                'message' => "Cleared {$deleted} pending emails from queue",
                'deleted' => $deleted
            ]);
            break;
            
        default:
            throw new Exception('Invalid action: ' . $action);
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
 * Returns detailed result for real-time progress tracking
 */
function processNextEmail($pdo, $user_id, $smtp_user, $smtp_pass, $settings) {
    try {
        // Begin transaction to ensure atomicity
        $pdo->beginTransaction();
        
        // Get next pending email with row lock
        $stmt = $pdo->prepare("
            SELECT * FROM bulk_mail_queue
            WHERE user_id = ? AND status = 'pending'
            ORDER BY created_at ASC
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([$user_id]);
        $queueItem = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$queueItem) {
            $pdo->rollBack();
            return [
                'success' => true,
                'email_sent' => false,
                'message' => 'No pending emails in queue',
                'no_more' => true
            ];
        }
        
        // Mark as processing
        $updateStmt = $pdo->prepare("
            UPDATE bulk_mail_queue
            SET status = 'processing', processing_started_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$queueItem['id']]);
        
        $pdo->commit();
        
        // Send email (outside transaction to avoid long locks)
        $sendResult = sendBulkEmail($pdo, $queueItem, $smtp_user, $smtp_pass, $settings);
        
        if ($sendResult['success']) {
            // EMAIL SENT SUCCESSFULLY - Update to completed
            $updateStmt = $pdo->prepare("
                UPDATE bulk_mail_queue
                SET status = 'completed', 
                    completed_at = NOW(),
                    sent_email_id = ?,
                    error_message = NULL
                WHERE id = ?
            ");
            $updateStmt->execute([$sendResult['sent_email_id'] ?? null, $queueItem['id']]);
            
            return [
                'success' => true,
                'email_sent' => true,
                'recipient' => $queueItem['recipient_email'],
                'recipient_name' => $queueItem['recipient_name'],
                'subject' => $queueItem['subject'],
                'message' => 'Email sent successfully'
            ];
            
        } else {
            // EMAIL FAILED - Update to failed with error message
            $errorMessage = $sendResult['error'] ?? 'Unknown error';
            
            $updateStmt = $pdo->prepare("
                UPDATE bulk_mail_queue
                SET status = 'failed', 
                    completed_at = NOW(),
                    error_message = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$errorMessage, $queueItem['id']]);
            
            return [
                'success' => true,
                'email_sent' => false,
                'recipient' => $queueItem['recipient_email'],
                'recipient_name' => $queueItem['recipient_name'],
                'subject' => $queueItem['subject'],
                'error' => $errorMessage,
                'message' => 'Email failed to send: ' . $errorMessage
            ];
        }
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        return [
            'success' => false,
            'error' => 'Processing error: ' . $e->getMessage()
        ];
    }
}

/**
 * Send email using PHPMailer
 * USES THE SAME SMTP CONFIGURATION AS send.php
 * 
 * @return array ['success' => bool, 'sent_email_id' => int] or ['success' => false, 'error' => string]
 */
function sendBulkEmail($pdo, $queueItem, $smtp_user, $smtp_pass, $settings) {
    $mail = new PHPMailer(true);
    
    try {
        // ==================== SMTP CONFIGURATION (SAME AS send.php) ====================
        $mail->isSMTP();
        $mail->SMTPDebug = 0; // Set to 2 for debugging
        
        // Use Hostinger SMTP (same as send.php)
        $mail->Host = "smtp.hostinger.com";
        $mail->SMTPAuth = true;
        $mail->Username = $smtp_user;
        $mail->Password = $smtp_pass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
        $mail->Port = 465;
        
        // SSL options for compatibility
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Character encoding
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        
        // ==================== SENDER CONFIGURATION ====================
        $displayName = !empty($settings['display_name']) 
            ? $settings['display_name'] 
            : "St. Xavier's College";
        
        $mail->setFrom($smtp_user, $displayName);
        $mail->addReplyTo($smtp_user, $displayName);
        
        // ==================== RECIPIENT ====================
        $recipient = filter_var(trim($queueItem['recipient_email']), FILTER_SANITIZE_EMAIL);
        
        if (!$recipient || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid recipient email address: " . $queueItem['recipient_email']);
        }
        
        $recipientName = trim($queueItem['recipient_name'] ?? '');
        if ($recipientName) {
            $mail->addAddress($recipient, $recipientName);
        } else {
            $mail->addAddress($recipient);
        }
        
        // ==================== EMAIL CONTENT ====================
        $subject = $queueItem['subject'] ?: 'Official Communication';
        $articleTitle = $queueItem['article_title'] ?: 'Official Communication';
        $messageContent = $queueItem['message_content'] ?: '';
        $closingWish = $queueItem['closing_wish'] ?: 'Best Regards,';
        $senderName = $queueItem['sender_name'] ?: $displayName;
        $senderDesignation = $queueItem['sender_designation'] ?: '';
        $additionalInfo = $queueItem['additional_info'] ?: "St. Xavier's College (Autonomous), Kolkata";
        
        $mail->Subject = $subject;
        
        // ==================== EMAIL BODY (USING TEMPLATE) ====================
        $templatePath = __DIR__ . '/templates/template1.html';
        
        if (file_exists($templatePath)) {
            // Use template
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
        } else {
            // Fallback to simple HTML if template not found
            $emailBody = "
            <html>
            <head><style>body{font-family:Arial,sans-serif;line-height:1.6;color:#333;}</style></head>
            <body>
                <h2>" . htmlspecialchars($articleTitle, ENT_QUOTES, 'UTF-8') . "</h2>
                <p>" . nl2br(htmlspecialchars($messageContent, ENT_QUOTES, 'UTF-8')) . "</p>
                <br>
                <p>" . htmlspecialchars($closingWish, ENT_QUOTES, 'UTF-8') . "<br>
                <strong>" . htmlspecialchars($senderName, ENT_QUOTES, 'UTF-8') . "</strong><br>
                " . htmlspecialchars($senderDesignation, ENT_QUOTES, 'UTF-8') . "<br>
                <em>" . htmlspecialchars($additionalInfo, ENT_QUOTES, 'UTF-8') . "</em></p>
            </body>
            </html>";
        }
        
        $mail->isHTML(true);
        $mail->Body = $emailBody;
        $mail->AltBody = strip_tags(str_replace('<br>', "\n", $emailBody));
        
        // ==================== ATTACHMENTS ====================
        if (!empty($queueItem['drive_file_path']) && file_exists($queueItem['drive_file_path'])) {
            $mail->addAttachment($queueItem['drive_file_path'], basename($queueItem['drive_file_path']));
        }
        
        // ==================== SEND EMAIL ====================
        if (!$mail->send()) {
            throw new Exception("Mail send failed: " . $mail->ErrorInfo);
        }
        
        // ==================== DATABASE LOGGING (MANDATORY) ====================
        // Save to sent_emails_new table
        $emailUuid = generateUuidV4();
        
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
        
        $attachmentCount = (!empty($queueItem['drive_file_path'])) ? 1 : 0;
        
        $stmt->execute([
            $emailUuid,
            getUserId($pdo, $smtp_user),
            $smtp_user,
            $recipient,
            $recipientName,
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
        if ($attachmentCount > 0) {
            $filePath = $queueItem['drive_file_path'];
            $fileName = basename($filePath);
            $fileSize = filesize($filePath);
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);
            
            $stmt = $pdo->prepare("
                INSERT INTO sent_email_attachments_new (
                    sent_email_id, email_uuid, original_filename, 
                    stored_filename, file_path, file_size, 
                    mime_type, file_extension, uploaded_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $mimeTypes = [
                'pdf' => 'application/pdf',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'zip' => 'application/zip'
            ];
            
            $mimeType = $mimeTypes[strtolower($extension)] ?? 'application/octet-stream';
            
            $stmt->execute([
                $sentEmailId,
                $emailUuid,
                $fileName,
                $fileName,
                $filePath,
                $fileSize,
                $mimeType,
                $extension
            ]);
        }
        
        return [
            'success' => true,
            'sent_email_id' => $sentEmailId
        ];
        
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
        
        // Enhanced error logging
        error_log("BULK EMAIL ERROR - Recipient: {$queueItem['recipient_email']} - Error: {$errorMessage}");
        
        // Parse common SMTP errors for better user feedback
        if (strpos($errorMessage, 'SMTP Error') !== false || strpos($errorMessage, 'SMTP connect() failed') !== false) {
            if (strpos($errorMessage, 'data not accepted') !== false) {
                $errorMessage = "SMTP rejected email. Possible causes: invalid recipient, spam filters, or sending limit reached.";
            } elseif (strpos($errorMessage, 'connect') !== false) {
                $errorMessage = "Could not connect to SMTP server. Check your internet connection.";
            } elseif (strpos($errorMessage, 'auth') !== false || strpos($errorMessage, 'authenticate') !== false) {
                $errorMessage = "SMTP authentication failed. Check your email credentials.";
            }
        }
        
        return [
            'success' => false,
            'error' => $errorMessage
        ];
    }
}