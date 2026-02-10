<?php
session_start();
require 'vendor/autoload.php';
require 'config.php';
require 'db_config.php';

// Security check
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    header("Location: login.php");
    exit();
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $mail = new PHPMailer(true);
    
    try {
        // ==================== SMTP Configuration ====================
        $mail->isSMTP();
        $mail->SMTPDebug = 0;
        
        $settings = $_SESSION['user_settings'] ?? [];

        $mail->Host = "smtp.hostinger.com";
        $mail->SMTPAuth = true;
        $mail->Username = $_SESSION['smtp_user'];
        $mail->Password = $_SESSION['smtp_pass'];
        $mail->Port = 465;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        
        $displayName = !empty($settings['display_name']) ? $settings['display_name'] : "St. Xavier's College";
        $mail->setFrom($_SESSION['smtp_user'], $displayName);
        
        // ==================== Capture Form Fields ====================
        $recipient = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $subject = trim($_POST['subject'] ?? 'Official Communication');
        $articleTitle = trim($_POST['articletitle'] ?? 'Official Communication');
        $messageContent = $_POST['message'] ?? '';
        
        $ccEmails = !empty($_POST['ccEmails']) ? array_map('trim', explode(',', $_POST['ccEmails'])) : [];
        $bccEmails = !empty($_POST['bccEmails']) ? array_map('trim', explode(',', $_POST['bccEmails'])) : [];
        
        $signatureWish = trim($_POST['signatureWish'] ?? 'Best Regards,');
        $signatureName = trim($_POST['signatureName'] ?? 'Dr. Durba Bhattacharya');
        $signatureDesignation = trim($_POST['signatureDesignation'] ?? 'Head of Department, Data Science');
        $signatureExtra = trim($_POST['signatureExtra'] ?? 'St. Xavier\'s College (Autonomous), Kolkata');
        
        // Label information (optional)
        $labelId = !empty($_POST['label_id']) ? intval($_POST['label_id']) : null;
        $labelName = !empty($_POST['label_name']) ? trim($_POST['label_name']) : null;
        $labelColor = !empty($_POST['label_color']) ? trim($_POST['label_color']) : null;
        
        $attachmentIds = !empty($_POST['attachment_ids']) ? explode(',', $_POST['attachment_ids']) : [];
        
        // ==================== Validate Required Fields ====================
        if (!$recipient || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid or missing recipient email address");
        }
        
        if (empty($subject)) {
            throw new Exception("Subject is required");
        }
        
        if (empty($articleTitle)) {
            throw new Exception("Article title is required");
        }
        
        if (empty($messageContent)) {
            throw new Exception("Message content is required");
        }
        
        // ==================== Add Recipients ====================
        $mail->addAddress($recipient);
        
        $validCCs = [];
        foreach ($ccEmails as $cc) {
            $cc = filter_var($cc, FILTER_SANITIZE_EMAIL);
            if ($cc && filter_var($cc, FILTER_VALIDATE_EMAIL)) {
                $mail->addCC($cc);
                $validCCs[] = $cc;
            }
        }
        
        $validBCCs = [];
        foreach ($bccEmails as $bcc) {
            $bcc = filter_var($bcc, FILTER_SANITIZE_EMAIL);
            if ($bcc && filter_var($bcc, FILTER_VALIDATE_EMAIL)) {
                $mail->addBCC($bcc);
                $validBCCs[] = $bcc;
            }
        }
        
        // ==================== Attachment Handling ====================
        $attachments = [];
        $attachmentIdsForDB = [];
        $uploadDir = 'uploads/attachments/';
        
        error_log("Processing attachments - IDs received: " . implode(',', $attachmentIds));
        
        if (!empty($attachmentIds) && isset($_SESSION['temp_attachments'])) {
            $sessionAttachments = $_SESSION['temp_attachments'];
            
            foreach ($attachmentIds as $attachmentId) {
                $attachmentId = trim($attachmentId);
                
                foreach ($sessionAttachments as $attachment) {
                    if (isset($attachment['id']) && $attachment['id'] == $attachmentId) {
                        $relativePath = $attachment['path'] ?? '';
                        $fullFilePath = $uploadDir . $relativePath;
                        $fileName = $attachment['original_name'] ?? 'attachment';
                        
                        if (file_exists($fullFilePath)) {
                            $mail->addAttachment($fullFilePath, $fileName);
                            
                            $attachments[] = [
                                'id' => $attachmentId,
                                'name' => $fileName,
                                'size' => formatFileSize($attachment['file_size'] ?? 0),
                                'extension' => $attachment['extension'] ?? 'file',
                                'mime_type' => $attachment['mime_type'] ?? 'application/octet-stream'
                            ];
                            
                            $attachmentIdsForDB[] = $attachmentId;
                            
                            error_log("✓ Successfully attached: $fullFilePath as $fileName");
                        } else {
                            error_log("✗ CRITICAL ERROR: Attachment file not found: $fullFilePath");
                            throw new Exception("Attachment file not found: $fileName. Please re-upload the file.");
                        }
                        break;
                    }
                }
            }
        }
        
        error_log("Total attachments added to email: " . count($attachments));
        
        // ==================== Load Email Template ====================
        $templatePath = __DIR__ . '/templates/template1.html';
        
        if (!file_exists($templatePath)) {
            throw new Exception("Email template not found at: " . $templatePath);
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
            $messageContent,
            htmlspecialchars($signatureWish, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($signatureName, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($signatureDesignation, ENT_QUOTES, 'UTF-8'),
            nl2br(htmlspecialchars($signatureExtra, ENT_QUOTES, 'UTF-8'))
        ], $emailTemplate);
        
        // ==================== Set Email Content ====================
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $emailBody;
        
        $plainTextMessage = strip_tags($messageContent);
        $mail->AltBody = "Article: " . $articleTitle . "\n\n" . $plainTextMessage . "\n\n" . 
                         $signatureWish . "\n" . $signatureName . "\n" . 
                         $signatureDesignation . "\n" . $signatureExtra;

        // ==================== Send Email ====================
        if ($mail->send()) {
            error_log("✓ Email sent successfully with " . count($attachments) . " attachments");
            
            // ==================== DATABASE REGISTRATION ====================
            try {
                $pdo = getDatabaseConnection();
                
                if (!$pdo) {
                    error_log("✗ Database connection failed - email sent but not recorded");
                } else {
                    // Generate email UUID and message ID
                    $emailUuid = generateUuidV4();
                    $messageId = '<' . $emailUuid . '@' . parse_url($_SESSION['smtp_user'], PHP_URL_HOST) . '>';
                    
                    // Prepare email data for simplified structure
                    $emailData = [
                        'email_uuid' => $emailUuid,
                        'message_id' => $messageId,
                        'sender_email' => $_SESSION['smtp_user'],
                        'sender_name' => $displayName,
                        'recipient_email' => $recipient,
                        'cc_list' => !empty($validCCs) ? implode(',', $validCCs) : null,
                        'bcc_list' => !empty($validBCCs) ? implode(',', $validBCCs) : null,
                        'reply_to' => $_SESSION['smtp_user'],
                        'subject' => $subject,
                        'article_title' => $articleTitle,
                        'body_text' => $plainTextMessage,
                        'body_html' => $emailBody,
                        'label_id' => $labelId,
                        'label_name' => $labelName,
                        'label_color' => $labelColor,
                        'has_attachments' => count($attachmentIdsForDB) > 0 ? 1 : 0,
                        'email_type' => 'sent'
                    ];
                    
                    // Save email to database using new simplified function
                    $emailId = saveSentEmail($pdo, $emailData);
                    
                    if ($emailId) {
                        error_log("✓ Sent email saved to database (ID: $emailId, UUID: $emailUuid)");
                        
                        // Link attachments to email if any
                        if (!empty($attachmentIdsForDB)) {
                            $attachmentsLinked = linkAttachmentsToSentEmail($pdo, $emailId, $emailUuid, $attachmentIdsForDB);
                            
                            if ($attachmentsLinked) {
                                error_log("✓ Successfully linked " . count($attachmentIdsForDB) . " attachments to email");
                            } else {
                                error_log("✗ Failed to link attachments to email");
                            }
                        }
                        
                    } else {
                        error_log("✗ Failed to save sent email to database");
                    }
                }
                
            } catch (Exception $e) {
                error_log("Database error during email registration: " . $e->getMessage());
                // Don't fail - email was sent successfully
            }
            
            // Clear session attachments after successful send
            unset($_SESSION['temp_attachments']);
            
            // Prepare success data for display
            $successEmails = [
                ['email' => $recipient, 'type' => 'TO']
            ];
            
            foreach ($validCCs as $cc) {
                $successEmails[] = ['email' => $cc, 'type' => 'CC'];
            }
            
            foreach ($validBCCs as $bcc) {
                $successEmails[] = ['email' => $bcc, 'type' => 'BCC'];
            }
            
            $summary = [
                'subject' => $subject,
                'article_title' => $articleTitle,
                'sent_at' => date('M d, Y h:i A'),
                'sender_name' => $displayName,
                'sender_email' => $_SESSION['smtp_user'],
                'cc_count' => count($validCCs),
                'bcc_count' => count($validBCCs),
                'attachment_count' => count($attachments),
                'signature_name' => $signatureName,
                'signature_designation' => $signatureDesignation
            ];
            
            showSuccessPage($successEmails, $summary, $attachments);
            
        } else {
            throw new Exception("Failed to send email");
        }
        
    } catch (Exception $e) {
        error_log("Email send error: " . $e->getMessage());
        showErrorPage($e->getMessage());
    }
}
/**
 * Save sent email to sent_emails_new table
 */
function saveSentEmail($pdo, $emailData) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO sent_emails_new (
                email_uuid, message_id, sender_email, sender_name,
                recipient_email, cc_list, bcc_list, reply_to,
                subject, article_title, body_text, body_html,
                label_id, label_name, label_color,
                has_attachments, email_type, sent_at, created_at
            ) VALUES (
                :email_uuid, :message_id, :sender_email, :sender_name,
                :recipient_email, :cc_list, :bcc_list, :reply_to,
                :subject, :article_title, :body_text, :body_html,
                :label_id, :label_name, :label_color,
                :has_attachments, :email_type, NOW(), NOW()
            )
        ");
        
        $stmt->execute([
            'email_uuid' => $emailData['email_uuid'],
            'message_id' => $emailData['message_id'] ?? null,
            'sender_email' => $emailData['sender_email'],
            'sender_name' => $emailData['sender_name'] ?? null,
            'recipient_email' => $emailData['recipient_email'],
            'cc_list' => $emailData['cc_list'] ?? null,
            'bcc_list' => $emailData['bcc_list'] ?? null,
            'reply_to' => $emailData['reply_to'] ?? null,
            'subject' => $emailData['subject'],
            'article_title' => $emailData['article_title'] ?? null,
            'body_text' => $emailData['body_text'] ?? null,
            'body_html' => $emailData['body_html'] ?? null,
            'label_id' => $emailData['label_id'] ?? null,
            'label_name' => $emailData['label_name'] ?? null,
            'label_color' => $emailData['label_color'] ?? null,
            'has_attachments' => $emailData['has_attachments'] ?? 0,
            'email_type' => $emailData['email_type'] ?? 'sent'
        ]);
        
        $emailId = $pdo->lastInsertId();
        
        if ($emailId) {
            error_log("✓ Sent email saved to database (ID: $emailId, UUID: {$emailData['email_uuid']})");
        } else {
            error_log("✗ Failed to save sent email to database");
        }
        
        return $emailId;
        
    } catch (PDOException $e) {
        error_log("Error saving sent email: " . $e->getMessage());
        error_log("SQL Error Details: " . print_r($e->errorInfo, true));
        return null;
    }
}

/**
 * Show success page
 */
function showSuccessPage($emails, $summary, $attachments) {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Sent Successfully - SXC MDTS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f8f9fa;
            color: #1c1c1e;
            min-height: 100vh;
            display: flex;
        }

        .app-container {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            padding: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .success-wrapper {
            max-width: 800px;
            width: 100%;
        }

        .success-header {
            text-align: center;
            margin-bottom: 40px;
            animation: fadeInDown 0.6s ease-out;
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .success-icon-wrapper {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
            animation: scaleIn 0.5s ease-out;
        }

        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }

        .success-icon-wrapper i {
            font-size: 40px;
            color: white;
        }

        .success-title {
            font-size: 32px;
            font-weight: 700;
            color: #1c1c1e;
            margin-bottom: 10px;
        }

        .success-subtitle {
            font-size: 16px;
            color: #6b7280;
            font-weight: 400;
        }

        .success-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            border: 1px solid #e5e7eb;
            margin-bottom: 20px;
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .summary-item {
            padding: 16px;
            background: #f9fafb;
            border-radius: 10px;
        }

        .summary-item label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .summary-item .value {
            font-size: 14px;
            color: #1c1c1e;
            font-weight: 500;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 14px 28px;
            background: #007AFF;
            color: white;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s;
            width: 100%;
            margin-top: 10px;
        }

        .btn:hover {
            background: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 122, 255, 0.3);
        }

        @media (max-width: 768px) {
            .summary-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <div class="success-wrapper">
                <div class="success-header">
                    <div class="success-icon-wrapper">
                        <i class="fas fa-check"></i>
                    </div>
                    <h1 class="success-title">Email Sent Successfully!</h1>
                    <p class="success-subtitle">Your email has been delivered to all recipients</p>
                </div>

                <div class="success-card">
                    <div class="summary-grid">
                        <div class="summary-item">
                            <label>Subject</label>
                            <div class="value"><?= htmlspecialchars($summary['subject']) ?></div>
                        </div>
                        <div class="summary-item">
                            <label>Article Title</label>
                            <div class="value"><?= htmlspecialchars($summary['article_title']) ?></div>
                        </div>
                        <div class="summary-item">
                            <label>Sent At</label>
                            <div class="value"><?= htmlspecialchars($summary['sent_at']) ?></div>
                        </div>
                        <div class="summary-item">
                            <label>Recipients</label>
                            <div class="value">
                                <?= count($emails) ?> 
                                (<?= $summary['cc_count'] ?> CC, <?= $summary['bcc_count'] ?> BCC)
                            </div>
                        </div>
                        <?php if ($summary['attachment_count'] > 0): ?>
                        <div class="summary-item">
                            <label>Attachments</label>
                            <div class="value"><?= $summary['attachment_count'] ?> file(s)</div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <a href="index.php" class="btn">
                        <i class="fas fa-paper-plane"></i>
                        Send Another Email
                    </a>
                    <a href="sent_history.php" class="btn" style="background: #6b7280; margin-top: 10px;">
                        <i class="fas fa-history"></i>
                        View Sent Emails
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f8f9fa;
            color: #1c1c1e;
            min-height: 100vh;
            display: flex;
        }

        .app-container {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            padding: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .error-wrapper {
            max-width: 600px;
            width: 100%;
        }

        .error-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .error-icon-wrapper {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            box-shadow: 0 10px 30px rgba(239, 68, 68, 0.3);
        }

        .error-icon-wrapper i {
            font-size: 40px;
            color: white;
        }

        .error-title {
            font-size: 32px;
            font-weight: 700;
            color: #1c1c1e;
            margin-bottom: 10px;
        }

        .error-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            border: 1px solid #e5e7eb;
        }

        .error-message-box {
            background: #fef2f2;
            border-left: 4px solid #ef4444;
            padding: 20px 24px;
            border-radius: 10px;
            margin-bottom: 30px;
        }

        .error-message-box strong {
            display: block;
            margin-bottom: 10px;
            color: #991b1b;
            font-size: 15px;
            font-weight: 600;
        }

        .error-message-box p {
            color: #7f1d1d;
            line-height: 1.6;
            font-size: 14px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 14px 28px;
            background: #007AFF;
            color: white;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s;
            width: 100%;
        }

        .btn:hover {
            background: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 122, 255, 0.3);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <div class="error-wrapper">
                <div class="error-header">
                    <div class="error-icon-wrapper">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <h1 class="error-title">Email Sending Failed</h1>
                </div>

                <div class="error-card">
                    <div class="error-message-box">
                        <strong>Error Details:</strong>
                        <p><?= htmlspecialchars($errorMessage) ?></p>
                    </div>

                    <a href="index.php" class="btn">
                        <i class="fas fa-arrow-left"></i>
                        Try Again
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