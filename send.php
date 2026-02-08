<?php
// /Applications/XAMPP/xamppfiles/htdocs/send.php
session_start();
require 'vendor/autoload.php';
require 'config.php';
require 'db_config.php'; // Include database configuration

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

    try {
        // --- SMTP Configuration ---
        $mail->isSMTP();
        $mail->Host       = env("SMTP_HOST", "smtp.hostinger.com"); 
        $mail->SMTPAuth   = true;
        $mail->Username   = $_SESSION['smtp_user']; 
        $mail->Password   = $_SESSION['smtp_pass']; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
        $mail->Port       = env("SMTP_PORT", 465);

        // --- Recipients ---
        $settings = $_SESSION['user_settings'] ?? [];
        $displayName = !empty($settings['display_name']) ? $settings['display_name'] : "MailDash Sender";
        
        $mail->setFrom($_SESSION['smtp_user'], $displayName);
        
        // Main recipient
        $recipient = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $mail->addAddress($recipient);
            $successEmails[] = ['email' => $recipient, 'type' => 'To'];
        } else {
            $failedEmails[] = ['email' => $recipient, 'type' => 'To', 'reason' => 'Invalid email format'];
        }

        // --- Handle CC Recipients ---
        $ccEmailsList = [];
        if (!empty($_POST['cc'])) {
            $ccEmails = parseEmailList($_POST['cc']);
            foreach ($ccEmails as $ccEmail) {
                if (filter_var($ccEmail, FILTER_VALIDATE_EMAIL)) {
                    try {
                        $mail->addCC($ccEmail);
                        $successEmails[] = ['email' => $ccEmail, 'type' => 'CC'];
                        $ccEmailsList[] = $ccEmail;
                    } catch (Exception $e) {
                        $failedEmails[] = ['email' => $ccEmail, 'type' => 'CC', 'reason' => $e->getMessage()];
                    }
                } else {
                    $failedEmails[] = ['email' => $ccEmail, 'type' => 'CC', 'reason' => 'Invalid email format'];
                }
            }
        }

        // --- Handle BCC Recipients ---
        $bccEmailsList = [];
        if (!empty($_POST['bcc'])) {
            $bccEmails = parseEmailList($_POST['bcc']);
            foreach ($bccEmails as $bccEmail) {
                if (filter_var($bccEmail, FILTER_VALIDATE_EMAIL)) {
                    try {
                        $mail->addBCC($bccEmail);
                        $successEmails[] = ['email' => $bccEmail, 'type' => 'BCC'];
                        $bccEmailsList[] = $bccEmail;
                    } catch (Exception $e) {
                        $failedEmails[] = ['email' => $bccEmail, 'type' => 'BCC', 'reason' => $e->getMessage()];
                    }
                } else {
                    $failedEmails[] = ['email' => $bccEmail, 'type' => 'BCC', 'reason' => 'Invalid email format'];
                }
            }
        }

        // --- Handle Multiple File Attachments ---
        $attachmentNames = [];
        if (isset($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
            $fileCount = count($_FILES['attachments']['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['attachments']['error'][$i] == UPLOAD_ERR_OK) {
                    $mail->addAttachment(
                        $_FILES['attachments']['tmp_name'][$i],
                        $_FILES['attachments']['name'][$i]
                    );
                    $attachmentNames[] = $_FILES['attachments']['name'][$i];
                }
            }
        }

        // --- Content Processing ---
        $mail->isHTML(true);
        
        // Apply subject prefix if set
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
        
        // Load template
        $templatePath = 'templates/template1.html';
        $finalHtml = '';

        if (file_exists($templatePath)) {
            $htmlStructure = file_get_contents($templatePath);
            
            // If message is already HTML (from rich text editor), use it directly
            // Otherwise, convert plain text to HTML
            if ($isHtml) {
                $formattedText = $messageBody;
            } else {
                $formattedText = nl2br(htmlspecialchars($messageBody));
            }
            
            // Replace placeholders in template
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
            
            // Replace signature components
            $finalHtml = str_replace('{{SIGNATURE_WISH}}', htmlspecialchars($signatureWish), $finalHtml);
            $finalHtml = str_replace('{{SIGNATURE_NAME}}', htmlspecialchars($signatureName), $finalHtml);
            $finalHtml = str_replace('{{SIGNATURE_DESIGNATION}}', htmlspecialchars($signatureDesignation), $finalHtml);
            $finalHtml = str_replace('{{SIGNATURE_EXTRA}}', nl2br(htmlspecialchars($signatureExtra)), $finalHtml);
            
            $mail->Body = $finalHtml;
            $mail->AltBody = strip_tags($messageBody);
        } else {
            // Fallback if template doesn't exist
            if ($isHtml) {
                $finalHtml = $messageBody;
                $mail->Body = $messageBody;
                $mail->AltBody = strip_tags($messageBody);
            } else {
                $finalHtml = nl2br(htmlspecialchars($messageBody));
                $mail->Body = $finalHtml;
                $mail->AltBody = $messageBody;
            }
        }
        
        // Send the email
        $mail->send();
        
        // --- DATABASE LOGGING: Save sent email with UUID ---
        error_log("=== ATTEMPTING TO SAVE EMAIL TO DATABASE ===");
        
        $pdo = getDatabaseConnection();
        
        if ($pdo) {
            // Get sender user ID - create if doesn't exist
            $senderId = getUserId($pdo, $_SESSION['smtp_user']);
            
            if (!$senderId) {
                // Create user if they don't exist
                $senderId = createUserIfNotExists($pdo, $_SESSION['smtp_user'], $displayName);
                error_log("Created user in database during send: " . $_SESSION['smtp_user'] . " (ID: $senderId)");
            }
            
            if (!$senderId) {
                error_log("ERROR: Could not get or create sender user ID");
                $dbSaved = false;
            } else {
                // Generate UUID for this email
                $emailUuid = generateUuidV4();
                
                error_log("Generated email UUID: " . $emailUuid);
                error_log("Sender ID: " . $senderId);
                
                // Get label ID if set
                $labelId = isset($_POST['label_id']) && !empty($_POST['label_id']) ? $_POST['label_id'] : null;
                
                // Insert into emails table
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
                    'has_attachments' => !empty($_SESSION['temp_attachments']) ? 1 : 0
                ];
                
                $emailId = saveEmailToDatabase($pdo, $emailData);
                
                if ($emailId) {
                    error_log("Email saved to database. ID: $emailId, UUID: $emailUuid");
                    
                    // Create sender access record
                    createEmailAccess($pdo, $emailId, $senderId, 'sender', $labelId);
                    
                    // Create receiver access records - only if recipient is a registered user
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
                    
                    // Process attachments from session
                    if (!empty($_SESSION['temp_attachments'])) {
                        processAttachments($pdo, $emailId, $emailUuid, $senderId, $recipientId, $_SESSION['temp_attachments']);
                    }
                    
                    // Clear temp attachments from session
                    unset($_SESSION['temp_attachments']);
                    
                    $dbSaved = true;
                } else {
                    error_log("=== DATABASE SAVE FAILED ===");
                    $dbSaved = false;
                }
            }
        } else {
            error_log("=== DATABASE CONNECTION FAILED ===");
            $dbSaved = false;
        }
        
        // Generate response HTML
        showResultPage($subject, $successEmails, $failedEmails, $dbSaved);

    } catch (Exception $e) {
        error_log("=== EMAIL SEND FAILED ===");
        error_log("Error: " . $e->getMessage());
        showErrorPage("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
    }
} else {
    header("Location: index.php");
    exit();
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
 * Process attachments and link them to receiver
 */
function processAttachments($pdo, $emailId, $emailUuid, $senderId, $recipientId, $attachments) {
    try {
        foreach ($attachments as $attachment) {
            $attachmentId = $attachment['id'];
            
            // Link attachment to email
            $stmt = $pdo->prepare("
                INSERT INTO email_attachments (email_id, attachment_id, created_at)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$emailId, $attachmentId]);
            
            // Update sender's access record with email_uuid
            $stmt = $pdo->prepare("
                UPDATE user_attachment_access 
                SET email_uuid = ?, access_type = 'sent', updated_at = NOW()
                WHERE user_id = ? AND attachment_id = ? AND email_uuid IS NULL
            ");
            $stmt->execute([$emailUuid, $senderId, $attachmentId]);
            
            // Create receiver's access record if recipient is a registered user
            if ($recipientId) {
                $stmt = $pdo->prepare("
                    INSERT INTO user_attachment_access 
                    (user_id, attachment_id, sender_id, receiver_id, email_uuid, access_type, created_at)
                    VALUES (?, ?, ?, ?, ?, 'received', NOW())
                    ON DUPLICATE KEY UPDATE
                        email_uuid = VALUES(email_uuid),
                        updated_at = NOW()
                ");
                $stmt->execute([$recipientId, $attachmentId, $senderId, $recipientId, $emailUuid]);
                
                error_log("Created receiver access for attachment $attachmentId, email UUID: $emailUuid");
            }
        }
        
        error_log("Processed " . count($attachments) . " attachments for email UUID: $emailUuid");
        return true;
        
    } catch (Exception $e) {
        error_log("Error processing attachments: " . $e->getMessage());
        return false;
    }
}

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
 * Show success result page
 */
function showResultPage($subject, $successEmails, $failedEmails, $dbSaved) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Email Sent Successfully</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                background: #f8f9fa;
                color: #191919;
                line-height: 1.6;
                padding-left: 280px;
            }

            .main-content {
                min-height: 100vh;
                display: flex;
                flex-direction: column;
            }

            .content-area {
                flex: 1;
                padding: 0;
            }

            .page-header {
                background: white;
                border-bottom: 1px solid #e0e0e0;
                padding: 24px 40px;
                position: sticky;
                top: 0;
                z-index: 100;
            }

            .header-container {
                max-width: 860px;
                margin: 0 auto;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .breadcrumb {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 14px;
                color: #666;
            }

            .breadcrumb a {
                color: #0973dc;
                text-decoration: none;
                transition: color 0.2s;
            }

            .breadcrumb a:hover {
                color: #006bb3;
            }

            .breadcrumb-separator {
                color: #c0c0c0;
            }

            .article-type {
                background: #e8f5e9;
                color: #2e7d32;
                padding: 4px 12px;
                border-radius: 12px;
                font-size: 13px;
                font-weight: 500;
            }

            .article-container {
                max-width: 860px;
                margin: 0 auto;
                padding: 48px 40px 80px;
            }

            .article-header {
                margin-bottom: 32px;
                padding-bottom: 32px;
                border-bottom: 1px solid #e0e0e0;
            }

            h1 {
                font-family: 'Harding', Georgia, serif;
                font-size: 36px;
                font-weight: 600;
                line-height: 1.2;
                color: #191919;
                margin-bottom: 20px;
                letter-spacing: -0.5px;
            }

            .article-meta {
                display: flex;
                align-items: center;
                gap: 20px;
                font-size: 14px;
                color: #666;
                flex-wrap: wrap;
            }

            .meta-item {
                display: flex;
                align-items: center;
                gap: 6px;
            }

            .meta-item i {
                color: #2e7d32;
                font-size: 13px;
            }

            .status-badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                background: #e8f5e9;
                color: #2e7d32;
                padding: 4px 12px;
                border-radius: 12px;
                font-size: 13px;
                font-weight: 500;
            }

            .status-badge i {
                color: #2e7d32;
            }

            .warning-badge {
                background: #fff3e0;
                color: #f57c00;
            }

            .warning-badge i {
                color: #f57c00;
            }

            .article-section {
                margin-bottom: 40px;
            }

            .section-title {
                font-family: 'Harding', Georgia, serif;
                font-size: 22px;
                font-weight: 600;
                color: #191919;
                margin-bottom: 16px;
                letter-spacing: -0.3px;
            }

            .success-box {
                background: #f1f8f4;
                border-left: 4px solid #2e7d32;
                padding: 20px 24px;
                border-radius: 4px;
                margin-bottom: 24px;
            }

            .success-title {
                font-size: 15px;
                font-weight: 600;
                color: #1b5e20;
                margin-bottom: 8px;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .success-message {
                color: #191919;
                line-height: 1.6;
                font-size: 14px;
            }

            .email-list {
                list-style: none;
                margin-top: 16px;
            }

            .email-list li {
                padding: 12px 16px;
                background: white;
                border: 1px solid #e0e0e0;
                border-radius: 4px;
                margin-bottom: 8px;
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .email-list li i {
                color: #2e7d32;
                font-size: 16px;
            }

            .email-badge {
                background: #e3f2fd;
                color: #1976d2;
                padding: 2px 8px;
                border-radius: 4px;
                font-size: 11px;
                font-weight: 500;
                text-transform: uppercase;
                margin-left: auto;
            }

            .article-actions {
                margin-top: 48px;
                padding-top: 32px;
                border-top: 1px solid #e0e0e0;
                display: flex;
                gap: 16px;
                flex-wrap: wrap;
            }

            .btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 12px 24px;
                border-radius: 4px;
                font-size: 15px;
                font-weight: 500;
                text-decoration: none;
                transition: all 0.2s;
                border: 1px solid;
                cursor: pointer;
            }

            .btn-primary {
                background: #0973dc;
                color: white;
                border-color: #0973dc;
            }

            .btn-primary:hover {
                background: #006bb3;
                border-color: #006bb3;
                box-shadow: 0 2px 8px rgba(9, 115, 220, 0.25);
            }

            .btn-secondary {
                background: white;
                color: #191919;
                border-color: #c0c0c0;
            }

            .btn-secondary:hover {
                background: #f5f5f5;
                border-color: #a0a0a0;
            }

            ::-webkit-scrollbar {
                width: 10px;
            }

            ::-webkit-scrollbar-track {
                background: #f5f5f5;
            }

            ::-webkit-scrollbar-thumb {
                background: #c0c0c0;
                border-radius: 5px;
            }

            ::-webkit-scrollbar-thumb:hover {
                background: #a0a0a0;
            }

            @media (max-width: 768px) {
                body {
                    padding-left: 0;
                }

                .article-container {
                    padding: 32px 24px 60px;
                }

                h1 {
                    font-size: 28px;
                }

                .section-title {
                    font-size: 20px;
                }
            }
        </style>
    </head>
    <body>
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <div class="content-area">
                <div class="page-header">
                    <div class="header-container">
                        <div class="breadcrumb">
                            <a href="index.php">Home</a>
                            <span class="breadcrumb-separator">›</span>
                            <span>Email Delivery</span>
                        </div>
                        <span class="article-type">Success</span>
                    </div>
                </div>

                <article class="article-container">
                    <header class="article-header">
                        <h1>Email Sent Successfully</h1>
                        <div class="article-meta">
                            <div class="meta-item">
                                <i class="fa-regular fa-clock"></i>
                                <span><?= date('d F Y, H:i') ?></span>
                            </div>
                            <span class="status-badge">
                                <i class="fa-solid fa-circle-check"></i>
                                Delivered
                            </span>
                            <?php if (!$dbSaved): ?>
                            <span class="status-badge warning-badge">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                Not Logged
                            </span>
                            <?php endif; ?>
                        </div>
                    </header>

                    <section class="article-section">
                        <h2 class="section-title">Email Details</h2>
                        <div class="success-box">
                            <div class="success-title">
                                <i class="fa-solid fa-paper-plane"></i>
                                Subject
                            </div>
                            <div class="success-message"><?= htmlspecialchars($subject) ?></div>
                        </div>
                    </section>

                    <?php if (!empty($successEmails)): ?>
                    <section class="article-section">
                        <h2 class="section-title">Recipients (<?= count($successEmails) ?>)</h2>
                        <ul class="email-list">
                            <?php foreach ($successEmails as $email): ?>
                            <li>
                                <i class="fa-solid fa-check-circle"></i>
                                <span><?= htmlspecialchars($email['email']) ?></span>
                                <span class="email-badge"><?= htmlspecialchars($email['type']) ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                    <?php endif; ?>

                    <div class="article-actions">
                        <a href="index.php" class="btn btn-primary">
                            <i class="fa-solid fa-arrow-left"></i>
                            Return to Composer
                        </a>
                        <a href="sent.php" class="btn btn-secondary">
                            <i class="fa-solid fa-inbox"></i>
                            View Sent Emails
                        </a>
                    </div>
                </article>
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
        <title>Email Delivery Failed</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                background: #f8f9fa;
                color: #191919;
                line-height: 1.6;
                padding-left: 280px;
            }

            .main-content {
                min-height: 100vh;
                display: flex;
                flex-direction: column;
            }

            .content-area {
                flex: 1;
                padding: 0;
            }

            .page-header {
                background: white;
                border-bottom: 1px solid #e0e0e0;
                padding: 24px 40px;
                position: sticky;
                top: 0;
                z-index: 100;
            }

            .header-container {
                max-width: 860px;
                margin: 0 auto;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .breadcrumb {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 14px;
                color: #666;
            }

            .breadcrumb a {
                color: #0973dc;
                text-decoration: none;
                transition: color 0.2s;
            }

            .breadcrumb a:hover {
                color: #006bb3;
            }

            .breadcrumb-separator {
                color: #c0c0c0;
            }

            .article-type {
                background: #ffebee;
                color: #c62828;
                padding: 4px 12px;
                border-radius: 12px;
                font-size: 13px;
                font-weight: 500;
            }

            .article-container {
                max-width: 860px;
                margin: 0 auto;
                padding: 48px 40px 80px;
            }

            .article-header {
                margin-bottom: 32px;
                padding-bottom: 32px;
                border-bottom: 1px solid #e0e0e0;
            }

            h1 {
                font-family: 'Harding', Georgia, serif;
                font-size: 36px;
                font-weight: 600;
                line-height: 1.2;
                color: #191919;
                margin-bottom: 20px;
                letter-spacing: -0.5px;
            }

            .article-meta {
                display: flex;
                align-items: center;
                gap: 20px;
                font-size: 14px;
                color: #666;
                flex-wrap: wrap;
            }

            .meta-item {
                display: flex;
                align-items: center;
                gap: 6px;
            }

            .meta-item i {
                color: #c62828;
                font-size: 13px;
            }

            .status-badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                background: #ffebee;
                color: #c62828;
                padding: 4px 12px;
                border-radius: 12px;
                font-size: 13px;
                font-weight: 500;
            }

            .status-badge i {
                color: #c62828;
            }

            .article-section {
                margin-bottom: 40px;
            }

            .section-title {
                font-family: 'Harding', Georgia, serif;
                font-size: 22px;
                font-weight: 600;
                color: #191919;
                margin-bottom: 16px;
                letter-spacing: -0.3px;
            }

            .error-box {
                background: #fff3e0;
                border-left: 4px solid #f57c00;
                padding: 20px 24px;
                border-radius: 4px;
                margin-bottom: 24px;
            }

            .error-title {
                font-size: 15px;
                font-weight: 600;
                color: #e65100;
                margin-bottom: 8px;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .error-message {
                color: #191919;
                line-height: 1.6;
                font-size: 14px;
            }

            .article-actions {
                margin-top: 48px;
                padding-top: 32px;
                border-top: 1px solid #e0e0e0;
                display: flex;
                gap: 16px;
                flex-wrap: wrap;
            }

            .btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 12px 24px;
                border-radius: 4px;
                font-size: 15px;
                font-weight: 500;
                text-decoration: none;
                transition: all 0.2s;
                border: 1px solid;
                cursor: pointer;
            }

            .btn-primary {
                background: #0973dc;
                color: white;
                border-color: #0973dc;
            }

            .btn-primary:hover {
                background: #006bb3;
                border-color: #006bb3;
                box-shadow: 0 2px 8px rgba(9, 115, 220, 0.25);
            }

            ::-webkit-scrollbar {
                width: 10px;
            }

            ::-webkit-scrollbar-track {
                background: #f5f5f5;
            }

            ::-webkit-scrollbar-thumb {
                background: #c0c0c0;
                border-radius: 5px;
            }

            ::-webkit-scrollbar-thumb:hover {
                background: #a0a0a0;
            }

            @media (max-width: 768px) {
                .article-container {
                    padding: 32px 24px 60px;
                }

                h1 {
                    font-size: 28px;
                }

                .section-title {
                    font-size: 20px;
                }
            }
        </style>
    </head>
    <body>
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <div class="content-area">
                <div class="page-header">
                    <div class="header-container">
                        <div class="breadcrumb">
                            <a href="index.php">Home</a>
                            <span class="breadcrumb-separator">›</span>
                            <span>Email Delivery</span>
                        </div>
                        <span class="article-type">Delivery Error</span>
                    </div>
                </div>

                <article class="article-container">
                    <header class="article-header">
                        <h1>Email Delivery Failed</h1>
                        <div class="article-meta">
                            <div class="meta-item">
                                <i class="fa-regular fa-clock"></i>
                                <span><?= date('d F Y, H:i') ?></span>
                            </div>
                            <span class="status-badge">
                                <i class="fa-solid fa-circle-xmark"></i>
                                Failed
                            </span>
                        </div>
                    </header>

                    <section class="article-section">
                        <h2 class="section-title">Error Information</h2>
                        <div class="error-box">
                            <div class="error-title">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                Delivery Error
                            </div>
                            <div class="error-message"><?= htmlspecialchars($errorMessage) ?></div>
                        </div>
                    </section>

                    <div class="article-actions">
                        <a href="index.php" class="btn btn-primary">
                            <i class="fa-solid fa-arrow-left"></i>
                            Return to Composer
                        </a>
                    </div>
                </article>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>