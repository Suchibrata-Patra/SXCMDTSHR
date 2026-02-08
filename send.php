<?php
/**
 * send.php - Comprehensive Email Sending Handler
 * Version: 2.0
 * Features:
 * - Secure session-based authentication
 * - Multiple recipients (To, CC, BCC)
 * - File attachment support (from upload_handler.php session)
 * - Template-based HTML emails
 * - Database logging to both 'sent_emails' and 'emails' tables
 * - Detailed delivery reporting
 * - Error handling and validation
 */

session_start();

// ==================== DEPENDENCIES ====================
require 'vendor/autoload.php';
require 'db_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ==================== SECURITY CHECK ====================
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    header("Location: login.php");
    exit();
}

// ==================== POST REQUEST CHECK ====================
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit();
}

// ==================== INITIALIZATION ====================
$pdo = getDatabaseConnection();
$mail = new PHPMailer(true);

// Delivery tracking
$deliveryReport = [
    'total_attempted' => 0,
    'successful' => [],
    'failed' => [],
    'attachments' => [],
    'database_saved' => false,
    'email_id' => null,
    'timestamp' => date('Y-m-d H:i:s')
];

try {
    // ==================== SMTP CONFIGURATION ====================
    // Using same simple configuration as login.php
    require 'config.php';
    if (file_exists(__DIR__ . '/.env')) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        $dotenv->load();
    }
    
    $mail->isSMTP();
    $mail->Host       = env("SMTP_HOST"); 
    $mail->SMTPAuth   = true;
    $mail->Username   = $_SESSION['smtp_user'];
    $mail->Password   = $_SESSION['smtp_pass'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = env("SMTP_PORT");
    
    // Debug mode - ENABLED FOR TROUBLESHOOTING
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = function($str, $level) {
        error_log("SMTP Debug [$level]: $str");
    };
    
    // Get display name from settings or use default
    $settings = $_SESSION['user_settings'] ?? [];
    $displayName = !empty($settings['display_name']) 
        ? $settings['display_name'] 
        : 'MailDash Sender';
    
    $mail->setFrom($_SESSION['smtp_user'], $displayName);
    $mail->addReplyTo($_SESSION['smtp_user'], $displayName);
    
    // ==================== RECIPIENT VALIDATION & PROCESSING ====================
    
    // Main recipient (required)
    $recipientEmail = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    if (empty($recipientEmail) || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid or missing recipient email address");
    }
    
    $mail->addAddress($recipientEmail);
    $deliveryReport['successful'][] = [
        'email' => $recipientEmail,
        'type' => 'To',
        'status' => 'Added'
    ];
    $deliveryReport['total_attempted']++;
    
    // CC recipients (optional)
    $ccList = [];
    if (!empty($_POST['cc'])) {
        $ccEmails = parseEmailList($_POST['cc']);
        foreach ($ccEmails as $ccEmail) {
            $deliveryReport['total_attempted']++;
            if (filter_var($ccEmail, FILTER_VALIDATE_EMAIL)) {
                try {
                    $mail->addCC($ccEmail);
                    $ccList[] = $ccEmail;
                    $deliveryReport['successful'][] = [
                        'email' => $ccEmail,
                        'type' => 'CC',
                        'status' => 'Added'
                    ];
                } catch (Exception $e) {
                    $deliveryReport['failed'][] = [
                        'email' => $ccEmail,
                        'type' => 'CC',
                        'reason' => $e->getMessage()
                    ];
                }
            } else {
                $deliveryReport['failed'][] = [
                    'email' => $ccEmail,
                    'type' => 'CC',
                    'reason' => 'Invalid email format'
                ];
            }
        }
    }
    
    // BCC recipients (optional)
    $bccList = [];
    if (!empty($_POST['bcc'])) {
        $bccEmails = parseEmailList($_POST['bcc']);
        foreach ($bccEmails as $bccEmail) {
            $deliveryReport['total_attempted']++;
            if (filter_var($bccEmail, FILTER_VALIDATE_EMAIL)) {
                try {
                    $mail->addBCC($bccEmail);
                    $bccList[] = $bccEmail;
                    $deliveryReport['successful'][] = [
                        'email' => $bccEmail,
                        'type' => 'BCC',
                        'status' => 'Added'
                    ];
                } catch (Exception $e) {
                    $deliveryReport['failed'][] = [
                        'email' => $bccEmail,
                        'type' => 'BCC',
                        'reason' => $e->getMessage()
                    ];
                }
            } else {
                $deliveryReport['failed'][] = [
                    'email' => $bccEmail,
                    'type' => 'BCC',
                    'reason' => 'Invalid email format'
                ];
            }
        }
    }
    
    // CC yourself if enabled
    if (!empty($settings['cc_yourself']) && $settings['cc_yourself'] === true) {
        $mail->addCC($_SESSION['smtp_user']);
    }
    
    // ==================== ATTACHMENT PROCESSING ====================
    
    // Process attachments from session (uploaded via upload_handler.php)
    $attachmentIds = [];
    $attachmentNames = [];
    $attachmentData = [];
    
    if (!empty($_POST['attachment_ids'])) {
        $attachmentIds = explode(',', $_POST['attachment_ids']);
        $attachmentIds = array_filter(array_map('trim', $attachmentIds));
        
        foreach ($attachmentIds as $attachmentId) {
            try {
                // Fetch attachment details from database
                $stmt = $pdo->prepare("
                    SELECT * FROM attachments 
                    WHERE id = ? 
                    LIMIT 1
                ");
                $stmt->execute([$attachmentId]);
                $attachment = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($attachment) {
                    $filePath = 'uploads/attachments/' . $attachment['storage_path'];
                    
                    if (file_exists($filePath)) {
                        $mail->addAttachment($filePath, $attachment['original_filename']);
                        
                        $attachmentNames[] = $attachment['original_filename'];
                        $attachmentData[] = [
                            'id' => $attachment['id'],
                            'name' => $attachment['original_filename'],
                            'size' => $attachment['file_size'],
                            'type' => $attachment['mime_type'],
                            'status' => 'Attached'
                        ];
                        
                        $deliveryReport['attachments'][] = [
                            'filename' => $attachment['original_filename'],
                            'size' => formatBytes($attachment['file_size']),
                            'status' => 'Attached successfully'
                        ];
                    } else {
                        $deliveryReport['attachments'][] = [
                            'filename' => $attachment['original_filename'],
                            'size' => formatBytes($attachment['file_size']),
                            'status' => 'File not found on server'
                        ];
                    }
                }
            } catch (Exception $e) {
                error_log("Attachment error: " . $e->getMessage());
                $deliveryReport['attachments'][] = [
                    'filename' => 'Unknown (ID: ' . $attachmentId . ')',
                    'size' => 'N/A',
                    'status' => 'Error: ' . $e->getMessage()
                ];
            }
        }
    }
    
    // ==================== EMAIL CONTENT PROCESSING ====================
    
    // Subject
    $subject = trim($_POST['subject'] ?? 'Notification');
    if (!empty($settings['default_subject_prefix'])) {
        $subject = $settings['default_subject_prefix'] . " " . $subject;
    }
    $mail->Subject = $subject;
    
    // Article title
    $articleTitle = trim($_POST['articletitle'] ?? '');
    
    // Message body
    $messageBody = $_POST['message'] ?? '';
    $isHtml = true; // Assuming Quill editor always sends HTML
    
    // Build signature
    $signatureHtml = buildSignature(
        $_POST['signatureWish'] ?? '',
        $_POST['signatureName'] ?? '',
        $_POST['signatureDesignation'] ?? '',
        $_POST['signatureExtra'] ?? ''
    );
    
    // Load email template
    $templatePath = 'templates/template1.html';
    $finalHtml = '';
    
    if (file_exists($templatePath)) {
        $htmlStructure = file_get_contents($templatePath);
        
        // Prepare message content
        $messageContent = $messageBody;
        if (!empty($signatureHtml)) {
            $messageContent .= '<br><br>' . $signatureHtml;
        }
        
        // Replace template placeholders
        $finalHtml = str_replace('{{MESSAGE}}', $messageContent, $htmlStructure);
        $finalHtml = str_replace('{{SUBJECT}}', htmlspecialchars($subject), $finalHtml);
        $finalHtml = str_replace('{{articletitle}}', htmlspecialchars($articleTitle), $finalHtml);
        $finalHtml = str_replace('{{SENDER_NAME}}', htmlspecialchars($displayName), $finalHtml);
        $finalHtml = str_replace('{{SENDER_EMAIL}}', htmlspecialchars($_SESSION['smtp_user']), $finalHtml);
        $finalHtml = str_replace('{{RECIPIENT_EMAIL}}', htmlspecialchars($recipientEmail), $finalHtml);
        $finalHtml = str_replace('{{CURRENT_DATE}}', date('F j, Y'), $finalHtml);
        $finalHtml = str_replace('{{CURRENT_YEAR}}', date('Y'), $finalHtml);
        $finalHtml = str_replace('{{YEAR}}', date('Y'), $finalHtml);
        
        // Handle attachment placeholder
        if (!empty($attachmentNames)) {
            $attachmentList = '<div style="margin-top: 20px; padding: 15px; background: #f5f5f5; border-radius: 8px;">';
            $attachmentList .= '<strong>Attachments:</strong><br>';
            foreach ($attachmentData as $att) {
                $attachmentList .= '📎 ' . htmlspecialchars($att['name']) . ' (' . formatBytes($att['size']) . ')<br>';
            }
            $attachmentList .= '</div>';
            $finalHtml = str_replace('{{ATTACHMENT}}', $attachmentList, $finalHtml);
        } else {
            $finalHtml = str_replace('{{ATTACHMENT}}', '', $finalHtml);
        }
        
        $mail->Body = $finalHtml;
        $mail->AltBody = strip_tags($messageBody);
    } else {
        // Fallback without template
        $messageContent = $messageBody;
        if (!empty($signatureHtml)) {
            $messageContent .= '<br><br>' . $signatureHtml;
        }
        $mail->Body = $messageContent;
        $mail->AltBody = strip_tags($messageBody);
        $finalHtml = $messageContent;
    }
    
    $mail->isHTML(true);
    
    // ==================== SEND EMAIL ====================
    
    $sendResult = $mail->send();
    
    if (!$sendResult) {
        throw new Exception("Email sending failed: " . $mail->ErrorInfo);
    }
    
    // ==================== DATABASE LOGGING ====================
    
    try {
        // Get user ID
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$_SESSION['smtp_user']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $userId = $user['id'] ?? null;
        
        // Generate UUID for email
        $emailUuid = generateUuid();
        $emailDate = date('Y-m-d H:i:s');
        
        // ==================== INSERT INTO 'emails' TABLE ====================
        
        $stmt = $pdo->prepare("
            INSERT INTO emails (
                email_uuid, message_id, sender_email, sender_name,
                recipient_email, cc_list, bcc_list,
                subject, body_html, body_text, article_title,
                email_type, has_attachments, email_date, sent_at,
                created_at
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?,
                'sent', ?, ?, NOW(),
                NOW()
            )
        ");
        
        $stmt->execute([
            $emailUuid,
            $mail->getLastMessageID(),
            $_SESSION['smtp_user'],
            $displayName,
            $recipientEmail,
            !empty($ccList) ? implode(', ', $ccList) : null,
            !empty($bccList) ? implode(', ', $bccList) : null,
            $subject,
            $finalHtml,
            strip_tags($messageBody),
            $articleTitle,
            count($attachmentIds) > 0 ? 1 : 0,
            $emailDate
        ]);
        
        $emailId = $pdo->lastInsertId();
        $deliveryReport['email_id'] = $emailId;
        
        // ==================== CREATE USER EMAIL ACCESS ====================
        
        if ($userId) {
            $stmt = $pdo->prepare("
                INSERT INTO user_email_access (
                    user_id, email_id, access_type, is_deleted, 
                    user_read, user_starred, user_important
                ) VALUES (?, ?, 'sender', 0, 1, 0, 0)
            ");
            $stmt->execute([$userId, $emailId]);
        }
        
        // ==================== LINK ATTACHMENTS ====================
        
        if (!empty($attachmentIds) && $emailId) {
            $order = 0;
            foreach ($attachmentIds as $attachmentId) {
                $stmt = $pdo->prepare("
                    INSERT INTO email_attachments (
                        email_id, attachment_id, attachment_order, is_inline
                    ) VALUES (?, ?, ?, 0)
                ");
                $stmt->execute([$emailId, $attachmentId, $order++]);
            }
        }
        
        // ==================== INSERT INTO 'sent_emails' TABLE (Legacy support) ====================
        
        $labelId = !empty($_POST['label_id']) ? $_POST['label_id'] : null;
        
        $stmt = $pdo->prepare("
            INSERT INTO sent_emails (
                sender_email, recipient_email, cc_list, bcc_list,
                subject, article_title, message_body, 
                attachment_names, label_id, sent_at, current_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)
        ");
        
        $stmt->execute([
            $_SESSION['smtp_user'],
            $recipientEmail,
            !empty($ccList) ? implode(', ', $ccList) : '',
            !empty($bccList) ? implode(', ', $bccList) : '',
            $subject,
            $articleTitle,
            $finalHtml,
            !empty($attachmentNames) ? implode(', ', $attachmentNames) : '',
            $labelId
        ]);
        
        $deliveryReport['database_saved'] = true;
        
        error_log("Email successfully saved to database. Email ID: " . $emailId);
        
    } catch (PDOException $e) {
        error_log("Database save error: " . $e->getMessage());
        $deliveryReport['database_saved'] = false;
        // Don't throw - email was sent successfully
    }
    
    // ==================== SHOW SUCCESS REPORT ====================
    
    showDeliveryReport($deliveryReport, $subject, $recipientEmail, $articleTitle);
    
} catch (Exception $e) {
    error_log("Email send error: " . $e->getMessage());
    
    // Show error report
    showErrorReport($e->getMessage(), $deliveryReport);
}

// ==================== HELPER FUNCTIONS ====================

/**
 * Parse comma/semicolon/newline separated email list
 */
function parseEmailList($emailString) {
    $emails = preg_split('/[,;\n\r]+/', $emailString);
    $emails = array_map('trim', $emails);
    $emails = array_filter($emails, function($email) {
        return !empty($email);
    });
    return array_unique($emails);
}

/**
 * Build HTML signature
 */
function buildSignature($wish, $name, $designation, $extra) {
    if (empty($wish) && empty($name) && empty($designation) && empty($extra)) {
        return '';
    }
    
    $signature = '<div style="margin-top: 30px; font-family: Arial, sans-serif; color: #333;">';
    
    if (!empty($wish)) {
        $signature .= '<p style="margin: 0 0 5px 0;">' . htmlspecialchars($wish) . '</p>';
    }
    
    if (!empty($name)) {
        $signature .= '<p style="margin: 0 0 5px 0; font-weight: 600; font-size: 15px;">' . htmlspecialchars($name) . '</p>';
    }
    
    if (!empty($designation)) {
        $signature .= '<p style="margin: 0 0 5px 0; color: #666; font-size: 14px;">' . htmlspecialchars($designation) . '</p>';
    }
    
    if (!empty($extra)) {
        $signature .= '<p style="margin: 5px 0 0 0; color: #666; font-size: 13px;">' . nl2br(htmlspecialchars($extra)) . '</p>';
    }
    
    $signature .= '</div>';
    
    return $signature;
}

/**
 * Generate UUID v4
 */
function generateUuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

/**
 * Format bytes to human readable
 */
function formatBytes($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

/**
 * Show delivery success report
 */
function showDeliveryReport($report, $subject, $recipient, $articleTitle) {
    $successCount = count($report['successful']);
    $failedCount = count($report['failed']);
    $attachmentCount = count($report['attachments']);
    $timestamp = date('d F Y, H:i');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Email Sent Successfully - SXC MDTS</title>
        
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            
            .report-container {
                background: white;
                border-radius: 20px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                max-width: 800px;
                width: 100%;
                overflow: hidden;
            }
            
            .report-header {
                background: linear-gradient(135deg, #34a853 0%, #2d8f47 100%);
                color: white;
                padding: 40px;
                text-align: center;
            }
            
            .success-icon {
                width: 80px;
                height: 80px;
                background: rgba(255, 255, 255, 0.2);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 20px;
                font-size: 40px;
            }
            
            .report-header h1 {
                font-size: 28px;
                margin-bottom: 10px;
                font-weight: 600;
            }
            
            .report-header p {
                font-size: 16px;
                opacity: 0.9;
            }
            
            .report-body {
                padding: 40px;
            }
            
            .summary-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }
            
            .summary-card {
                background: #f8f9fa;
                border-radius: 12px;
                padding: 20px;
                text-align: center;
                border: 2px solid #e9ecef;
            }
            
            .summary-card.success {
                border-color: #34a853;
                background: #f0f9f4;
            }
            
            .summary-card.failed {
                border-color: #ea4335;
                background: #fef2f2;
            }
            
            .summary-card.attachments {
                border-color: #4285f4;
                background: #f0f6ff;
            }
            
            .summary-number {
                font-size: 36px;
                font-weight: 700;
                margin-bottom: 5px;
            }
            
            .summary-card.success .summary-number {
                color: #34a853;
            }
            
            .summary-card.failed .summary-number {
                color: #ea4335;
            }
            
            .summary-card.attachments .summary-number {
                color: #4285f4;
            }
            
            .summary-label {
                font-size: 14px;
                color: #666;
                font-weight: 500;
            }
            
            .section {
                margin-bottom: 30px;
            }
            
            .section-title {
                font-size: 18px;
                font-weight: 600;
                color: #333;
                margin-bottom: 15px;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .section-title i {
                color: #667eea;
            }
            
            .info-box {
                background: #f8f9fa;
                border-radius: 12px;
                padding: 20px;
                margin-bottom: 15px;
            }
            
            .info-row {
                display: flex;
                justify-content: space-between;
                padding: 10px 0;
                border-bottom: 1px solid #e9ecef;
            }
            
            .info-row:last-child {
                border-bottom: none;
            }
            
            .info-label {
                font-weight: 500;
                color: #666;
            }
            
            .info-value {
                color: #333;
                font-weight: 500;
            }
            
            .recipient-list {
                background: #f8f9fa;
                border-radius: 12px;
                padding: 15px;
            }
            
            .recipient-item {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px;
                background: white;
                border-radius: 8px;
                margin-bottom: 10px;
            }
            
            .recipient-item:last-child {
                margin-bottom: 0;
            }
            
            .recipient-icon {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 16px;
                color: white;
            }
            
            .recipient-icon.success {
                background: #34a853;
            }
            
            .recipient-icon.failed {
                background: #ea4335;
            }
            
            .recipient-details {
                flex: 1;
            }
            
            .recipient-email {
                font-weight: 500;
                color: #333;
                margin-bottom: 3px;
            }
            
            .recipient-type {
                font-size: 13px;
                color: #666;
            }
            
            .attachment-list {
                background: #f8f9fa;
                border-radius: 12px;
                padding: 15px;
            }
            
            .attachment-item {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px;
                background: white;
                border-radius: 8px;
                margin-bottom: 10px;
            }
            
            .attachment-item:last-child {
                margin-bottom: 0;
            }
            
            .attachment-icon {
                width: 40px;
                height: 40px;
                border-radius: 8px;
                background: #4285f4;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 18px;
            }
            
            .attachment-details {
                flex: 1;
            }
            
            .attachment-name {
                font-weight: 500;
                color: #333;
                margin-bottom: 3px;
            }
            
            .attachment-info {
                font-size: 13px;
                color: #666;
            }
            
            .database-status {
                background: #f0f9f4;
                border: 2px solid #34a853;
                border-radius: 12px;
                padding: 15px 20px;
                display: flex;
                align-items: center;
                gap: 12px;
                margin-bottom: 20px;
            }
            
            .database-status.failed {
                background: #fef2f2;
                border-color: #ea4335;
            }
            
            .database-status i {
                font-size: 24px;
                color: #34a853;
            }
            
            .database-status.failed i {
                color: #ea4335;
            }
            
            .database-status-text {
                flex: 1;
                font-weight: 500;
                color: #333;
            }
            
            .action-buttons {
                display: flex;
                gap: 15px;
                margin-top: 30px;
            }
            
            .btn {
                flex: 1;
                padding: 14px 24px;
                border-radius: 10px;
                font-size: 15px;
                font-weight: 600;
                text-decoration: none;
                text-align: center;
                transition: all 0.3s;
                cursor: pointer;
                border: none;
            }
            
            .btn-primary {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
            }
            
            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
            }
            
            .btn-secondary {
                background: #f8f9fa;
                color: #333;
                border: 2px solid #e9ecef;
            }
            
            .btn-secondary:hover {
                background: #e9ecef;
            }
            
            .email-preview {
                background: #f8f9fa;
                border-radius: 12px;
                padding: 20px;
                max-height: 300px;
                overflow-y: auto;
                border: 2px solid #e9ecef;
            }
            
            @media (max-width: 768px) {
                .report-body {
                    padding: 25px;
                }
                
                .summary-grid {
                    grid-template-columns: 1fr;
                }
                
                .action-buttons {
                    flex-direction: column;
                }
            }
        </style>
    </head>
    <body>
        <div class="report-container">
            <div class="report-header">
                <div class="success-icon">
                    <i class="fas fa-check"></i>
                </div>
                <h1>Email Sent Successfully!</h1>
                <p><?= htmlspecialchars($timestamp) ?></p>
            </div>
            
            <div class="report-body">
                <!-- Summary Statistics -->
                <div class="summary-grid">
                    <div class="summary-card success">
                        <div class="summary-number"><?= $successCount ?></div>
                        <div class="summary-label">Recipients</div>
                    </div>
                    
                    <?php if ($failedCount > 0): ?>
                    <div class="summary-card failed">
                        <div class="summary-number"><?= $failedCount ?></div>
                        <div class="summary-label">Failed</div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($attachmentCount > 0): ?>
                    <div class="summary-card attachments">
                        <div class="summary-number"><?= $attachmentCount ?></div>
                        <div class="summary-label">Attachments</div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Database Status -->
                <div class="database-status <?= $report['database_saved'] ? '' : 'failed' ?>">
                    <i class="fas fa-<?= $report['database_saved'] ? 'database' : 'exclamation-triangle' ?>"></i>
                    <div class="database-status-text">
                        <?= $report['database_saved'] 
                            ? 'Email saved to database (ID: ' . $report['email_id'] . ')' 
                            : 'Warning: Email sent but not saved to database' 
                        ?>
                    </div>
                </div>
                
                <!-- Email Details -->
                <div class="section">
                    <h3 class="section-title">
                        <i class="fas fa-envelope"></i>
                        Email Details
                    </h3>
                    <div class="info-box">
                        <div class="info-row">
                            <span class="info-label">Subject</span>
                            <span class="info-value"><?= htmlspecialchars($subject) ?></span>
                        </div>
                        <?php if (!empty($articleTitle)): ?>
                        <div class="info-row">
                            <span class="info-label">Article Title</span>
                            <span class="info-value"><?= htmlspecialchars($articleTitle) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="info-row">
                            <span class="info-label">From</span>
                            <span class="info-value"><?= htmlspecialchars($_SESSION['smtp_user']) ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Sent At</span>
                            <span class="info-value"><?= $timestamp ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Recipients -->
                <div class="section">
                    <h3 class="section-title">
                        <i class="fas fa-users"></i>
                        Recipients (<?= $successCount ?>)
                    </h3>
                    <div class="recipient-list">
                        <?php foreach ($report['successful'] as $recipient): ?>
                        <div class="recipient-item">
                            <div class="recipient-icon success">
                                <i class="fas fa-check"></i>
                            </div>
                            <div class="recipient-details">
                                <div class="recipient-email"><?= htmlspecialchars($recipient['email']) ?></div>
                                <div class="recipient-type"><?= $recipient['type'] ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Failed Recipients -->
                <?php if (!empty($report['failed'])): ?>
                <div class="section">
                    <h3 class="section-title">
                        <i class="fas fa-exclamation-circle"></i>
                        Failed Recipients (<?= $failedCount ?>)
                    </h3>
                    <div class="recipient-list">
                        <?php foreach ($report['failed'] as $failed): ?>
                        <div class="recipient-item">
                            <div class="recipient-icon failed">
                                <i class="fas fa-times"></i>
                            </div>
                            <div class="recipient-details">
                                <div class="recipient-email"><?= htmlspecialchars($failed['email']) ?></div>
                                <div class="recipient-type"><?= $failed['type'] ?> - <?= htmlspecialchars($failed['reason']) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Attachments -->
                <?php if (!empty($report['attachments'])): ?>
                <div class="section">
                    <h3 class="section-title">
                        <i class="fas fa-paperclip"></i>
                        Attachments (<?= $attachmentCount ?>)
                    </h3>
                    <div class="attachment-list">
                        <?php foreach ($report['attachments'] as $attachment): ?>
                        <div class="attachment-item">
                            <div class="attachment-icon">
                                <i class="fas fa-file"></i>
                            </div>
                            <div class="attachment-details">
                                <div class="attachment-name"><?= htmlspecialchars($attachment['filename']) ?></div>
                                <div class="attachment-info"><?= $attachment['size'] ?> - <?= $attachment['status'] ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Action Buttons -->
                <div class="action-buttons">
                    <a href="index.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Compose New Email
                    </a>
                    <a href="sent.php" class="btn btn-secondary">
                        <i class="fas fa-list"></i> View Sent Emails
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}

/**
 * Show error report
 */
function showErrorReport($errorMessage, $report) {
    $timestamp = date('d F Y, H:i');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Email Send Failed - SXC MDTS</title>
        
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
                background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            
            .error-container {
                background: white;
                border-radius: 20px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                max-width: 700px;
                width: 100%;
                overflow: hidden;
            }
            
            .error-header {
                background: linear-gradient(135deg, #ea4335 0%, #c5221f 100%);
                color: white;
                padding: 40px;
                text-align: center;
            }
            
            .error-icon {
                width: 80px;
                height: 80px;
                background: rgba(255, 255, 255, 0.2);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 20px;
                font-size: 40px;
            }
            
            .error-header h1 {
                font-size: 28px;
                margin-bottom: 10px;
                font-weight: 600;
            }
            
            .error-header p {
                font-size: 16px;
                opacity: 0.9;
            }
            
            .error-body {
                padding: 40px;
            }
            
            .error-message {
                background: #fef2f2;
                border-left: 4px solid #ea4335;
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 30px;
            }
            
            .error-message-title {
                font-weight: 600;
                color: #ea4335;
                margin-bottom: 10px;
                font-size: 16px;
            }
            
            .error-message-text {
                color: #666;
                line-height: 1.6;
            }
            
            .action-buttons {
                display: flex;
                gap: 15px;
            }
            
            .btn {
                flex: 1;
                padding: 14px 24px;
                border-radius: 10px;
                font-size: 15px;
                font-weight: 600;
                text-decoration: none;
                text-align: center;
                transition: all 0.3s;
                cursor: pointer;
            }
            
            .btn-primary {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
            }
            
            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
            }
            
            @media (max-width: 768px) {
                .error-body {
                    padding: 25px;
                }
                
                .action-buttons {
                    flex-direction: column;
                }
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-header">
                <div class="error-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h1>Email Send Failed</h1>
                <p><?= htmlspecialchars($timestamp) ?></p>
            </div>
            
            <div class="error-body">
                <div class="error-message">
                    <div class="error-message-title">
                        <i class="fas fa-times-circle"></i> Error Details
                    </div>
                    <div class="error-message-text">
                        <?= htmlspecialchars($errorMessage) ?>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <a href="index.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Composer
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>