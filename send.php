<?php
// send.php - Email Sending with Attachment Support
session_start();
require 'vendor/autoload.php';
require 'config.php';
require 'db_config.php';

if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    header("Location: login.php");
    exit();
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $mail = new PHPMailer(true);
    
    $successEmails = [];
    $failedEmails = [];
    $dbSaved = false;

    try {
        // --- SMTP Configuration ---
        $mail->isSMTP();
        $mail->Host       = env("SMTP_HOST", "smtp.hostinger.com"); 
        $mail->SMTPAuth   = true;
        $mail->Username   = $_SESSION['smtp_user']; 
        $mail->Password   = $_SESSION['smtp_pass']; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
        $mail->Port       = env("SMTP_PORT", 465);

        // --- Get Settings ---
        $settings = $_SESSION['user_settings'] ?? [];
        $displayName = !empty($settings['display_name']) ? $settings['display_name'] : "Mail Sender";
        
        $mail->setFrom($_SESSION['smtp_user'], $displayName);
        
        // --- Main Recipient ---
        $recipient = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $mail->addAddress($recipient);
            $successEmails[] = ['email' => $recipient, 'type' => 'To'];
        } else {
            throw new Exception("Invalid recipient email address");
        }

        // --- Handle CC Recipients ---
        $ccEmailsList = [];
        if (!empty($_POST['cc'])) {
            $ccEmails = parseEmailList($_POST['cc']);
            foreach ($ccEmails as $ccEmail) {
                if (filter_var($ccEmail, FILTER_VALIDATE_EMAIL)) {
                    $mail->addCC($ccEmail);
                    $successEmails[] = ['email' => $ccEmail, 'type' => 'CC'];
                    $ccEmailsList[] = $ccEmail;
                }
            }
        }

        // --- Handle BCC Recipients ---
        $bccEmailsList = [];
        if (!empty($_POST['bcc'])) {
            $bccEmails = parseEmailList($_POST['bcc']);
            foreach ($bccEmails as $bccEmail) {
                if (filter_var($bccEmail, FILTER_VALIDATE_EMAIL)) {
                    $mail->addBCC($bccEmail);
                    $successEmails[] = ['email' => $bccEmail, 'type' => 'BCC'];
                    $bccEmailsList[] = $bccEmail;
                }
            }
        }

        // --- Handle Attachments from Session ---
        $attachmentPaths = [];
        if (!empty($_SESSION['temp_attachments'])) {
            foreach ($_SESSION['temp_attachments'] as $attachment) {
                $filePath = 'uploads/attachments/' . $attachment['path'];
                
                if (file_exists($filePath)) {
                    $mail->addAttachment($filePath, $attachment['original_name']);
                    $attachmentPaths[] = $attachment;
                    error_log("Attached file: " . $attachment['original_name']);
                } else {
                    error_log("Warning: Attachment file not found: " . $filePath);
                }
            }
        }

        // --- Email Content ---
        $mail->isHTML(true);
        
        // Subject with optional prefix
        $subject = $_POST['subject'] ?? 'Notification';
        if (!empty($settings['default_subject_prefix'])) {
            $subject = $settings['default_subject_prefix'] . " " . $subject;
        }
        $mail->Subject = $subject;
        
        $messageBody = $_POST['message'] ?? '';
        $articleTitle = $_POST['articletitle'] ?? '';
        $isHtml = isset($_POST['message_is_html']) && $_POST['message_is_html'] === 'true';
        
        // Get signature components
        $signatureWish = $_POST['signatureWish'] ?? '';
        $signatureName = $_POST['signatureName'] ?? '';
        $signatureDesignation = $_POST['signatureDesignation'] ?? '';
        $signatureExtra = $_POST['signatureExtra'] ?? '';
        
        // Load and process template
        $templatePath = 'templates/template1.html';
        $finalHtml = '';

        if (file_exists($templatePath)) {
            $htmlStructure = file_get_contents($templatePath);
            
            // Format message
            if ($isHtml) {
                $formattedText = $messageBody;
            } else {
                $formattedText = nl2br(htmlspecialchars($messageBody));
            }
            
            // Replace all placeholders
            $finalHtml = str_replace('{{MESSAGE}}', $formattedText, $htmlStructure);
            $finalHtml = str_replace('{{SUBJECT}}', htmlspecialchars($subject), $finalHtml);
            $finalHtml = str_replace('{{articletitle}}', htmlspecialchars($articleTitle), $finalHtml);
            $finalHtml = str_replace('{{SENDER_NAME}}', htmlspecialchars($displayName), $finalHtml);
            $finalHtml = str_replace('{{SENDER_EMAIL}}', htmlspecialchars($_SESSION['smtp_user']), $finalHtml);
            $finalHtml = str_replace('{{RECIPIENT_EMAIL}}', htmlspecialchars($recipient), $finalHtml);
            $finalHtml = str_replace('{{CURRENT_DATE}}', date('F j, Y'), $finalHtml);
            $finalHtml = str_replace('{{CURRENT_YEAR}}', date('Y'), $finalHtml);
            $finalHtml = str_replace('{{YEAR}}', date('Y'), $finalHtml);
            $finalHtml = str_replace('{{ATTACHMENT}}', '', $finalHtml);
            
            // Signature replacements
            $finalHtml = str_replace('{{SIGNATURE_WISH}}', htmlspecialchars($signatureWish), $finalHtml);
            $finalHtml = str_replace('{{SIGNATURE_NAME}}', htmlspecialchars($signatureName), $finalHtml);
            $finalHtml = str_replace('{{SIGNATURE_DESIGNATION}}', htmlspecialchars($signatureDesignation), $finalHtml);
            $finalHtml = str_replace('{{SIGNATURE_EXTRA}}', nl2br(htmlspecialchars($signatureExtra)), $finalHtml);
            
            $mail->Body = $finalHtml;
            $mail->AltBody = strip_tags($messageBody);
        } else {
            // Fallback
            if ($isHtml) {
                $mail->Body = $messageBody;
                $mail->AltBody = strip_tags($messageBody);
            } else {
                $mail->Body = nl2br(htmlspecialchars($messageBody));
                $mail->AltBody = $messageBody;
            }
            $finalHtml = $mail->Body;
        }
        
        // --- SEND EMAIL ---
        $mail->send();
        error_log("✓ Email sent successfully to: " . $recipient);
        
        // --- SAVE TO DATABASE ---
        $pdo = getDatabaseConnection();
        
        if ($pdo) {
            // Get or create sender user ID
            $senderId = getUserId($pdo, $_SESSION['smtp_user']);
            
            if (!$senderId) {
                $senderId = createUserIfNotExists($pdo, $_SESSION['smtp_user'], $displayName);
                error_log("Created user during send: " . $_SESSION['smtp_user'] . " (ID: $senderId)");
            }
            
            if ($senderId) {
                // Generate email UUID
                $emailUuid = generateUuidV4();
                
                // Get label ID if set
                $labelId = isset($_POST['label_id']) && !empty($_POST['label_id']) ? $_POST['label_id'] : null;
                
                // Save email to database
                $emailData = [
                    'email_uuid' => $emailUuid,
                    'sender_email' => $_SESSION['smtp_user'],
                    'sender_name' => $displayName,
                    'recipient_email' => $recipient,
                    'cc_list' => !empty($ccEmailsList) ? implode(', ', $ccEmailsList) : null,
                    'bcc_list' => !empty($bccEmailsList) ? implode(', ', $bccEmailsList) : null,
                    'subject' => $subject,
                    'body_text' => strip_tags($messageBody),
                    'body_html' => $finalHtml,
                    'article_title' => $articleTitle,
                    'email_type' => 'sent',
                    'has_attachments' => !empty($attachmentPaths) ? 1 : 0
                ];
                
                $emailId = saveEmailToDatabase($pdo, $emailData);
                
                if ($emailId) {
                    error_log("✓ Email saved to database (ID: $emailId, UUID: $emailUuid)");
                    
                    // Create sender access record
                    createEmailAccess($pdo, $emailId, $senderId, 'sender', $labelId);
                    
                    // Create recipient access record (if they're a registered user)
                    $recipientId = getUserIdByEmail($pdo, $recipient);
                    if ($recipientId) {
                        createEmailAccess($pdo, $emailId, $recipientId, 'recipient', null);
                    }
                    
                    // Process CC recipients
                    foreach ($ccEmailsList as $ccEmail) {
                        $ccUserId = getUserIdByEmail($pdo, $ccEmail);
                        if ($ccUserId) {
                            createEmailAccess($pdo, $emailId, $ccUserId, 'cc', null);
                        }
                    }
                    
                    // Link attachments to email
                    if (!empty($attachmentPaths)) {
                        linkAttachmentsToEmail($pdo, $emailId, $emailUuid, $senderId, $attachmentPaths);
                    }
                    
                    $dbSaved = true;
                } else {
                    error_log("✗ Failed to save email to database");
                }
            } else {
                error_log("✗ Could not get/create sender user ID");
            }
        } else {
            error_log("✗ Database connection failed");
        }
        
        // Clear temporary attachments from session
        unset($_SESSION['temp_attachments']);
        
        // Show success page
        showResultPage($subject, $successEmails, $failedEmails, $dbSaved);

    } catch (Exception $e) {
        error_log("✗ Email send failed: " . $e->getMessage());
        
        // Clear temp attachments on error too
        unset($_SESSION['temp_attachments']);
        
        showErrorPage("Message could not be sent. Error: " . $e->getMessage());
    }
} else {
    header("Location: index.php");
    exit();
}

// ==================== HELPER FUNCTIONS ====================

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
 * Link attachments to sent email
 */
function linkAttachmentsToEmail($pdo, $emailId, $emailUuid, $senderId, $attachments) {
    try {
        // Check if user_attachment_access has sender_id column
        $stmt = $pdo->query("SHOW COLUMNS FROM user_attachment_access");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $hasSenderColumn = in_array('sender_id', $columns);
        
        foreach ($attachments as $attachment) {
            $attachmentId = $attachment['id'];
            
            // Link attachment to email in email_attachments table
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO email_attachments (email_id, attachment_id, created_at)
                    VALUES (?, ?, NOW())
                ");
                $stmt->execute([$emailId, $attachmentId]);
                error_log("✓ Linked attachment $attachmentId to email $emailId");
            } catch (Exception $e) {
                error_log("✗ Failed to link attachment to email: " . $e->getMessage());
            }
            
            // Update user_attachment_access with email UUID
            try {
                if ($hasSenderColumn) {
                    // Update with sender_id
                    $stmt = $pdo->prepare("
                        UPDATE user_attachment_access 
                        SET email_uuid = ?, access_type = 'sent', updated_at = NOW()
                        WHERE user_id = ? AND attachment_id = ? AND (email_uuid IS NULL OR email_uuid = '')
                        LIMIT 1
                    ");
                    $stmt->execute([$emailUuid, $senderId, $attachmentId]);
                } else {
                    // Update without sender_id
                    $stmt = $pdo->prepare("
                        UPDATE user_attachment_access 
                        SET email_uuid = ?, access_type = 'sent', updated_at = NOW()
                        WHERE user_id = ? AND attachment_id = ? AND (email_uuid IS NULL OR email_uuid = '')
                        LIMIT 1
                    ");
                    $stmt->execute([$emailUuid, $senderId, $attachmentId]);
                }
                
                error_log("✓ Updated access record for attachment $attachmentId with UUID: $emailUuid");
            } catch (Exception $e) {
                error_log("✗ Failed to update attachment access: " . $e->getMessage());
            }
        }
        
        error_log("✓ Processed " . count($attachments) . " attachments for email UUID: $emailUuid");
        return true;
        
    } catch (Exception $e) {
        error_log("✗ Error in linkAttachmentsToEmail: " . $e->getMessage());
        return false;
    }
}

/**
 * Parse email list (comma, semicolon, newline separated)
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
 * Show success result page
 */
function showResultPage($subject, $successEmails, $failedEmails, $dbSaved) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Email Sent - SXC MDTS</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                background: #f8f9fa;
                color: #191919;
                line-height: 1.6;
                padding-left: 280px;
            }

            .main-content {
                min-height: 100vh;
                padding: 40px;
            }

            .success-container {
                max-width: 800px;
                margin: 0 auto;
                background: white;
                border-radius: 12px;
                box-shadow: 0 2px 12px rgba(0,0,0,0.08);
                overflow: hidden;
            }

            .success-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 40px;
                text-align: center;
            }

            .success-icon {
                font-size: 64px;
                margin-bottom: 20px;
                animation: checkmark 0.6s ease-in-out;
            }

            @keyframes checkmark {
                0% { transform: scale(0); }
                50% { transform: scale(1.2); }
                100% { transform: scale(1); }
            }

            h1 {
                font-size: 32px;
                margin-bottom: 10px;
            }

            .success-subtitle {
                opacity: 0.9;
                font-size: 16px;
            }

            .success-body {
                padding: 40px;
            }

            .detail-box {
                background: #f8f9fa;
                border-left: 4px solid #667eea;
                padding: 20px;
                margin-bottom: 24px;
                border-radius: 4px;
            }

            .detail-label {
                font-size: 12px;
                text-transform: uppercase;
                color: #666;
                letter-spacing: 0.5px;
                margin-bottom: 8px;
                font-weight: 600;
            }

            .detail-value {
                font-size: 18px;
                color: #191919;
                font-weight: 500;
            }

            .email-list {
                list-style: none;
            }

            .email-list li {
                padding: 12px;
                background: #f8f9fa;
                margin-bottom: 8px;
                border-radius: 6px;
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .email-list li i {
                color: #28a745;
            }

            .email-badge {
                margin-left: auto;
                background: #e3f2fd;
                color: #1976d2;
                padding: 4px 12px;
                border-radius: 12px;
                font-size: 11px;
                font-weight: 600;
            }

            .warning-box {
                background: #fff3cd;
                border-left: 4px solid #ffc107;
                padding: 16px;
                margin-top: 24px;
                border-radius: 4px;
            }

            .action-buttons {
                display: flex;
                gap: 16px;
                margin-top: 32px;
            }

            .btn {
                flex: 1;
                padding: 14px 24px;
                border-radius: 8px;
                text-decoration: none;
                font-weight: 600;
                text-align: center;
                transition: all 0.3s;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
            }

            .btn-primary {
                background: #667eea;
                color: white;
                border: none;
            }

            .btn-primary:hover {
                background: #5568d3;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            }

            .btn-secondary {
                background: white;
                color: #667eea;
                border: 2px solid #667eea;
            }

            .btn-secondary:hover {
                background: #f8f9fa;
            }

            @media (max-width: 768px) {
                body {
                    padding-left: 0;
                }

                .success-body {
                    padding: 24px;
                }

                .action-buttons {
                    flex-direction: column;
                }
            }
        </style>
    </head>
    <body>
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <div class="success-container">
                <div class="success-header">
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h1>Email Sent Successfully!</h1>
                    <p class="success-subtitle">Your message has been delivered</p>
                </div>

                <div class="success-body">
                    <div class="detail-box">
                        <div class="detail-label">Subject</div>
                        <div class="detail-value"><?= htmlspecialchars($subject) ?></div>
                    </div>

                    <div class="detail-box">
                        <div class="detail-label">Recipients (<?= count($successEmails) ?>)</div>
                        <ul class="email-list">
                            <?php foreach ($successEmails as $email): ?>
                            <li>
                                <i class="fas fa-check-circle"></i>
                                <span><?= htmlspecialchars($email['email']) ?></span>
                                <span class="email-badge"><?= htmlspecialchars($email['type']) ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <?php if (!$dbSaved): ?>
                    <div class="warning-box">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Note:</strong> Email was sent but not saved to database. This won't affect delivery.
                    </div>
                    <?php endif; ?>

                    <div class="action-buttons">
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i>
                            Send Another Email
                        </a>
                        <a href="sent_history.php" class="btn btn-secondary">
                            <i class="fas fa-history"></i>
                            View Sent History
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}

/**
 * Show error page
 */
function showErrorPage($errorMessage) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Send Error - SXC MDTS</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                background: #f8f9fa;
                color: #191919;
                line-height: 1.6;
                padding-left: 280px;
            }

            .main-content {
                min-height: 100vh;
                padding: 40px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .error-container {
                max-width: 600px;
                background: white;
                border-radius: 12px;
                box-shadow: 0 2px 12px rgba(0,0,0,0.08);
                overflow: hidden;
            }

            .error-header {
                background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                color: white;
                padding: 40px;
                text-align: center;
            }

            .error-icon {
                font-size: 64px;
                margin-bottom: 20px;
            }

            h1 {
                font-size: 28px;
                margin-bottom: 10px;
            }

            .error-body {
                padding: 40px;
            }

            .error-message {
                background: #fff3cd;
                border-left: 4px solid #ffc107;
                padding: 20px;
                margin-bottom: 24px;
                border-radius: 4px;
                word-break: break-word;
            }

            .btn {
                display: inline-block;
                padding: 14px 32px;
                background: #f5576c;
                color: white;
                border-radius: 8px;
                text-decoration: none;
                font-weight: 600;
                transition: all 0.3s;
            }

            .btn:hover {
                background: #e04555;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(245, 87, 108, 0.4);
            }

            @media (max-width: 768px) {
                body {
                    padding-left: 0;
                }
            }
        </style>
    </head>
    <body>
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <div class="error-container">
                <div class="error-header">
                    <div class="error-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <h1>Email Sending Failed</h1>
                </div>

                <div class="error-body">
                    <div class="error-message">
                        <strong>Error:</strong><br>
                        <?= htmlspecialchars($errorMessage) ?>
                    </div>

                    <a href="index.php" class="btn">
                        <i class="fas fa-arrow-left"></i> Try Again
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>