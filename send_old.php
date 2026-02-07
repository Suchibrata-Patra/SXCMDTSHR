<?php
// /Applications/XAMPP/xamppfiles/htdocs/send.php
session_start();
require 'vendor/autoload.php';
require 'config.php';

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
        if (!empty($_POST['cc'])) {
            $ccEmails = parseEmailList($_POST['cc']);
            foreach ($ccEmails as $ccEmail) {
                if (filter_var($ccEmail, FILTER_VALIDATE_EMAIL)) {
                    try {
                        $mail->addCC($ccEmail);
                        $successEmails[] = ['email' => $ccEmail, 'type' => 'CC'];
                    } catch (Exception $e) {
                        $failedEmails[] = ['email' => $ccEmail, 'type' => 'CC', 'reason' => $e->getMessage()];
                    }
                } else {
                    $failedEmails[] = ['email' => $ccEmail, 'type' => 'CC', 'reason' => 'Invalid email format'];
                }
            }
        }

        // --- Handle BCC Recipients ---
        if (!empty($_POST['bcc'])) {
            $bccEmails = parseEmailList($_POST['bcc']);
            foreach ($bccEmails as $bccEmail) {
                if (filter_var($bccEmail, FILTER_VALIDATE_EMAIL)) {
                    try {
                        $mail->addBCC($bccEmail);
                        $successEmails[] = ['email' => $bccEmail, 'type' => 'BCC'];
                    } catch (Exception $e) {
                        $failedEmails[] = ['email' => $bccEmail, 'type' => 'BCC', 'reason' => $e->getMessage()];
                    }
                } else {
                    $failedEmails[] = ['email' => $bccEmail, 'type' => 'BCC', 'reason' => 'Invalid email format'];
                }
            }
        }

        // --- Handle Multiple File Attachments ---
        if (isset($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
            $fileCount = count($_FILES['attachments']['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['attachments']['error'][$i] == UPLOAD_ERR_OK) {
                    $mail->addAttachment(
                        $_FILES['attachments']['tmp_name'][$i],
                        $_FILES['attachments']['name'][$i]
                    );
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
                $mail->Body = $messageBody;
                $mail->AltBody = strip_tags($messageBody);
            } else {
                $mail->Body = nl2br(htmlspecialchars($messageBody));
                $mail->AltBody = $messageBody;
            }
        }
        
        $mail->send();
        
        // Generate response HTML
        showResultPage($subject, $successEmails, $failedEmails);

    } catch (Exception $e) {
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
 * Show result page with sidebar navigation
 */
function showResultPage($subject, $successEmails, $failedEmails) {
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
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            
            body { 
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
                background-color: #fafafa;
                color: #222;
                display: flex;
                height: 100vh;
                overflow: hidden;
            }

            .main-content {
                flex: 1;
                display: flex;
                overflow: hidden;
            }

            .content-area {
                flex: 1;
                padding: 40px 60px;
                overflow-y: auto;
            }

            .success-card {
                background: white;
                border-radius: 10px;
                border: 1px solid #e5e5e5;
                padding: 40px;
                max-width: 800px;
                margin: 0 auto;
            }

            .success-icon {
                width: 80px;
                height: 80px;
                background: #34a853;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 24px;
            }

            .success-icon i {
                font-size: 40px;
                color: white;
            }

            h1 {
                font-size: 28px;
                font-weight: 600;
                text-align: center;
                margin-bottom: 12px;
                color: #1a1a1a;
            }

            .timestamp {
                text-align: center;
                color: #666;
                font-size: 14px;
                margin-bottom: 32px;
            }

            .email-details {
                background: #fafafa;
                border-radius: 8px;
                padding: 24px;
                margin-bottom: 24px;
            }

            .detail-row {
                display: flex;
                justify-content: space-between;
                padding: 12px 0;
                border-bottom: 1px solid #e5e5e5;
            }

            .detail-row:last-child {
                border-bottom: none;
            }

            .detail-label {
                font-weight: 500;
                color: #666;
            }

            .detail-value {
                color: #1a1a1a;
                font-weight: 500;
            }

            .recipient-list {
                margin-top: 24px;
            }

            .recipient-item {
                padding: 12px 16px;
                background: white;
                border: 1px solid #e5e5e5;
                border-radius: 6px;
                margin-bottom: 8px;
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .recipient-badge {
                background: #34a853;
                color: white;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 11px;
                font-weight: 600;
            }

            .recipient-email {
                flex: 1;
                color: #1a1a1a;
            }

            .actions {
                display: flex;
                gap: 12px;
                justify-content: center;
                margin-top: 32px;
            }

            .btn {
                padding: 12px 24px;
                border-radius: 7px;
                cursor: pointer;
                font-size: 15px;
                font-weight: 500;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                transition: all 0.2s;
            }

            .btn-primary {
                background: #1a73e8;
                color: white;
                border: none;
            }

            .btn-primary:hover {
                background: #1557b0;
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(26, 115, 232, 0.3);
            }

            .btn-secondary {
                background: white;
                color: #1a1a1a;
                border: 1px solid #d0d0d0;
            }

            .btn-secondary:hover {
                background: #f5f5f5;
                border-color: #1a73e8;
            }
        </style>
    </head>
    <body>
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <div class="content-area">
                <div class="success-card">
                    <div class="success-icon">
                        <i class="fa-solid fa-check"></i>
                    </div>
                    
                    <h1>Email Sent Successfully!</h1>
                    <p class="timestamp">Sent on <?= $timestamp ?></p>

                    <div class="email-details">
                        <div class="detail-row">
                            <span class="detail-label">Subject:</span>
                            <span class="detail-value"><?= htmlspecialchars($subject) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Total Recipients:</span>
                            <span class="detail-value"><?= $successCount ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Status:</span>
                            <span class="detail-value" style="color: #34a853;">Delivered</span>
                        </div>
                    </div>

                    <?php if (!empty($successEmails)): ?>
                    <div class="recipient-list">
                        <h3 style="font-size: 16px; margin-bottom: 12px; color: #666;">Recipients:</h3>
                        <?php foreach ($successEmails as $email): ?>
                        <div class="recipient-item">
                            <span class="recipient-badge"><?= $email['type'] ?></span>
                            <span class="recipient-email"><?= htmlspecialchars($email['email']) ?></span>
                            <i class="fa-solid fa-check-circle" style="color: #34a853;"></i>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div class="actions">
                        <a href="index.php" class="btn btn-primary">
                            <i class="fa-solid fa-paper-plane"></i>
                            Compose New Email
                        </a>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fa-solid fa-home"></i>
                            Back to Home
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
    $userEmail = $_SESSION['smtp_user'];
    $userInitial = strtoupper(substr($userEmail, 0, 1));
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>SXC MDTS</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            
            body { 
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
                background-color: #fafafa;
                color: #222;
                display: flex;
                height: 100vh;
                overflow: hidden;
            }

            .main-content {
                flex: 1;
                display: flex;
                overflow: hidden;
            }

            .content-area {
                flex: 1;
                padding: 40px 60px;
                overflow-y: auto;
            }

            .error-card {
                background: white;
                border-radius: 10px;
                border: 1px solid #e5e5e5;
                padding: 40px;
                max-width: 800px;
                margin: 0 auto;
            }

            .error-icon {
                width: 80px;
                height: 80px;
                background: #d32f2f;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 24px;
            }

            .error-icon i {
                font-size: 40px;
                color: white;
            }

            h1 {
                font-size: 28px;
                font-weight: 600;
                text-align: center;
                margin-bottom: 24px;
                color: #1a1a1a;
            }

            .error-box {
                background: #ffebee;
                border: 1px solid #ffcdd2;
                border-radius: 8px;
                padding: 20px;
                margin-bottom: 24px;
            }

            .error-message {
                color: #c62828;
                line-height: 1.6;
            }

            .actions {
                display: flex;
                gap: 12px;
                justify-content: center;
            }

            .btn {
                padding: 12px 24px;
                border-radius: 7px;
                cursor: pointer;
                font-size: 15px;
                font-weight: 500;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                transition: all 0.2s;
            }

            .btn-primary {
                background: #1a73e8;
                color: white;
                border: none;
            }

            .btn-primary:hover {
                background: #1557b0;
            }
        </style>
    </head>
    <body>
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <div class="content-area">
                <div class="error-card">
                    <div class="error-icon">
                        <i class="fa-solid fa-exclamation-triangle"></i>
                    </div>
                    
                    <h1>Email Sending Failed</h1>

                    <div class="error-box">
                        <div class="error-message"><?= htmlspecialchars($errorMessage) ?></div>
                    </div>

                    <div class="actions">
                        <a href="index.php" class="btn btn-primary">
                            <i class="fa-solid fa-arrow-left"></i>
                            Return to Compose
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>