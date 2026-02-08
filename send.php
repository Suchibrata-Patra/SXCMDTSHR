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
        
        // --- DATABASE LOGGING: Save sent email to database ---
        error_log("=== ATTEMPTING TO SAVE EMAIL TO DATABASE ===");
        error_log("Sender: " . $_SESSION['smtp_user']);
        error_log("Recipient: " . $recipient);
        error_log("Subject: " . $subject);
        
        $emailData = [
            'sender_email' => $_SESSION['smtp_user'],
            'recipient_email' => $recipient,
            'cc_list' => !empty($ccEmailsList) ? implode(', ', $ccEmailsList) : '',
            'bcc_list' => !empty($bccEmailsList) ? implode(', ', $bccEmailsList) : '',
            'subject' => $subject,
            'article_title' => $articleTitle,
            'message_body' => $finalHtml,
            'attachment_names' => !empty($attachmentNames) ? implode(', ', $attachmentNames) : ''
        ];
        
        // Attempt to save to database (non-blocking - email already sent)
        $dbSaved = saveSentEmail($emailData);
        
        if (!$dbSaved) {
            error_log("=== DATABASE SAVE FAILED ===");
            error_log("Warning: Email sent successfully but failed to save to database");
        } else {
            error_log("=== DATABASE SAVE SUCCESS ===");
            error_log("Email successfully saved to database");
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
 * Show result page with Nature.com-inspired design
 */
function showResultPage($subject, $successEmails, $failedEmails, $dbSaved = true) {
    $totalEmails = count($successEmails) + count($failedEmails);
    $successCount = count($successEmails);
    $failureCount = count($failedEmails);
    $timestamp = date('d F Y, H:i');
    
    $userEmail = $_SESSION['smtp_user'];
    $userInitial = strtoupper(substr($userEmail, 0, 1));
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>SXC MDTS</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Harding:wght@400;500;600&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            * { 
                margin: 0; 
                padding: 0; 
                box-sizing: border-box; 
            }
            
            body { 
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
                background-color: #fff;
                color: #191919;
                display: flex;
                height: 100vh;
                overflow: hidden;
                line-height: 1.6;
                font-size: 16px;
            }

            .main-content {
                flex: 1;
                display: flex;
                overflow: hidden;
            }

            .content-area {
                flex: 1;
                overflow-y: auto;
                background: #fff;
            }

            /* Nature.com inspired header */
            .page-header {
                background: #fff;
                border-bottom: 1px solid #e0e0e0;
                padding: 0;
            }

            .header-container {
                max-width: 1280px;
                margin: 0 auto;
                padding: 20px 40px;
            }

            .breadcrumb {
                font-size: 14px;
                color: #666;
                margin-bottom: 12px;
            }

            .breadcrumb a {
                color: #0973dc;
                text-decoration: none;
            }

            .breadcrumb a:hover {
                text-decoration: underline;
            }

            .breadcrumb-separator {
                margin: 0 8px;
                color: #999;
            }

            .article-type {
                display: inline-block;
                font-size: 13px;
                font-weight: 600;
                color: #0c7b93;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-bottom: 16px;
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
                color: #0c7b93;
                font-size: 13px;
            }

            .status-badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                background: #e8f4f8;
                color: #0c7b93;
                padding: 4px 12px;
                border-radius: 12px;
                font-size: 13px;
                font-weight: 500;
            }

            .status-badge i {
                color: #0c7b93;
            }

            .status-badge.warning {
                background: #fff3e0;
                color: #f57c00;
            }

            .status-badge.warning i {
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

            .email-list {
                background: #f9fafb;
                border: 1px solid #e0e0e0;
                border-radius: 4px;
                padding: 0;
            }

            .email-item {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 16px 20px;
                border-bottom: 1px solid #e0e0e0;
            }

            .email-item:last-child {
                border-bottom: none;
            }

            .email-address {
                display: flex;
                align-items: center;
                gap: 12px;
                font-size: 14px;
                color: #191919;
            }

            .email-address i {
                color: #0c7b93;
                font-size: 16px;
            }

            .email-type {
                font-size: 12px;
                font-weight: 500;
                padding: 2px 8px;
                border-radius: 4px;
                background: #e8f4f8;
                color: #0c7b93;
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

            .info-box {
                background: #e3f2fd;
                border-left: 4px solid #1976d2;
                padding: 16px 20px;
                border-radius: 4px;
                margin-bottom: 24px;
            }

            .info-box p {
                font-size: 14px;
                color: #191919;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .info-box i {
                color: #1976d2;
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
                border-color: #e0e0e0;
            }

            .btn-secondary:hover {
                background: #f9fafb;
                border-color: #c0c0c0;
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
                        <span class="article-type">Delivery Confirmation</span>
                    </div>
                </div>

                <article class="article-container">
                    <header class="article-header">
                        <h1>Email Sent Successfully</h1>
                        <div class="article-meta">
                            <div class="meta-item">
                                <i class="fa-regular fa-clock"></i>
                                <span><?= $timestamp ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="fa-solid fa-envelope"></i>
                                <span><?= $successCount ?> recipient<?= $successCount > 1 ? 's' : '' ?></span>
                            </div>
                            <span class="status-badge">
                                <i class="fa-solid fa-circle-check"></i>
                                Delivered
                            </span>
                            <?php if (!$dbSaved): ?>
                            <span class="status-badge warning">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                Not Logged
                            </span>
                            <?php endif; ?>
                        </div>
                    </header>

                    <?php if (!$dbSaved): ?>
                    <div class="info-box">
                        <p>
                            <i class="fa-solid fa-info-circle"></i>
                            Email was sent successfully, but could not be saved to sent history. Check your database configuration.
                        </p>
                    </div>
                    <?php endif; ?>

                    <section class="article-section">
                        <h2 class="section-title">Email Details</h2>
                        <div class="email-list">
                            <div class="email-item">
                                <div class="email-address">
                                    <i class="fa-solid fa-paper-plane"></i>
                                    <span><strong>Subject:</strong> <?= htmlspecialchars($subject) ?></span>
                                </div>
                            </div>
                        </div>
                    </section>

                    <?php if (!empty($successEmails)): ?>
                    <section class="article-section">
                        <h2 class="section-title">Recipients (<?= $successCount ?>)</h2>
                        <div class="email-list">
                            <?php foreach ($successEmails as $email): ?>
                            <div class="email-item">
                                <div class="email-address">
                                    <i class="fa-solid fa-user"></i>
                                    <span><?= htmlspecialchars($email['email']) ?></span>
                                </div>
                                <span class="email-type"><?= $email['type'] ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <?php endif; ?>

                    <?php if (!empty($failedEmails)): ?>
                    <section class="article-section">
                        <h2 class="section-title">Failed Recipients (<?= $failureCount ?>)</h2>
                        <div class="error-box">
                            <div class="error-title">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                The following recipients could not be added:
                            </div>
                            <?php foreach ($failedEmails as $failed): ?>
                            <div class="error-message">
                                <strong><?= htmlspecialchars($failed['email']) ?></strong> (<?= $failed['type'] ?>): <?= htmlspecialchars($failed['reason']) ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <?php endif; ?>

                    <div class="article-actions">
                        <a href="index.php" class="btn btn-primary">
                            <i class="fa-solid fa-plus"></i>
                            Compose New Email
                        </a>
                        <a href="sent_history.php" class="btn btn-secondary">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                            View Sent History
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
    $userEmail = $_SESSION['smtp_user'];
    $userInitial = strtoupper(substr($userEmail, 0, 1));
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Email Delivery Failed</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Harding:wght@400;500;600&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            * { 
                margin: 0; 
                padding: 0; 
                box-sizing: border-box; 
            }
            
            body { 
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
                background-color: #fff;
                color: #191919;
                display: flex;
                height: 100vh;
                overflow: hidden;
                line-height: 1.6;
                font-size: 16px;
            }

            .main-content {
                flex: 1;
                display: flex;
                overflow: hidden;
            }

            .content-area {
                flex: 1;
                overflow-y: auto;
                background: #fff;
            }

            .page-header {
                background: #fff;
                border-bottom: 1px solid #e0e0e0;
                padding: 0;
            }

            .header-container {
                max-width: 1280px;
                margin: 0 auto;
                padding: 20px 40px;
            }

            .breadcrumb {
                font-size: 14px;
                color: #666;
                margin-bottom: 12px;
            }

            .breadcrumb a {
                color: #0973dc;
                text-decoration: none;
            }

            .breadcrumb a:hover {
                text-decoration: underline;
            }

            .breadcrumb-separator {
                margin: 0 8px;
                color: #999;
            }

            .article-type {
                display: inline-block;
                font-size: 13px;
                font-weight: 600;
                color: #c62828;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-bottom: 16px;
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