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
        // SMTP Configuration
        $mail->isSMTP();
        $mail->SMTPDebug = 0;
        
        $settings = $_SESSION['user_settings'] ?? [];

        // SMTP Host - Hostinger's SMTP server
        $mail->Host = "smtp.hostinger.com";
        $mail->SMTPAuth = true;
        $mail->Username = $_SESSION['smtp_user'];
        $mail->Password = $_SESSION['smtp_pass'];

        // Port and Security Configuration
        $mail->Port = 465;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        
        // Sender
        $displayName = !empty($settings['display_name']) ? $settings['display_name'] : "St. Xavier's College";
        $mail->setFrom($_SESSION['smtp_user'], $displayName);
        
        // Capture all form data
        $recipient = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $subject = trim($_POST['subject'] ?? 'Official Communication');
        $messageContent = $_POST['message'] ?? '';
        
        // Additional template fields
        $articleTitle = trim($_POST['article_title'] ?? 'Official Communication');
        $signatureWish = trim($_POST['signature_wish'] ?? 'Best Regards,');
        $signatureName = trim($_POST['signature_name'] ?? 'Dr. Durba Bhattacharya');
        $signatureDesignation = trim($_POST['signature_designation'] ?? 'Head of Department, Data Science');
        $signatureExtra = trim($_POST['signature_extra'] ?? 'St. Xavier\'s College (Autonomous), Kolkata');
        
        // CC and BCC (comma-separated)
        $ccEmails = !empty($_POST['cc_emails']) ? explode(',', $_POST['cc_emails']) : [];
        $bccEmails = !empty($_POST['bcc_emails']) ? explode(',', $_POST['bcc_emails']) : [];
        
        if (!$recipient) {
            throw new Exception("Invalid email address");
        }
        
        $mail->addAddress($recipient);
        
        // Add CC recipients
        foreach ($ccEmails as $cc) {
            $cc = filter_var(trim($cc), FILTER_SANITIZE_EMAIL);
            if ($cc && filter_var($cc, FILTER_VALIDATE_EMAIL)) {
                $mail->addCC($cc);
            }
        }
        
        // Add BCC recipients
        foreach ($bccEmails as $bcc) {
            $bcc = filter_var(trim($bcc), FILTER_SANITIZE_EMAIL);
            if ($bcc && filter_var($bcc, FILTER_VALIDATE_EMAIL)) {
                $mail->addBCC($bcc);
            }
        }
        
        // Handle file attachments
        $attachments = [];
        if (!empty($_FILES['attachments']['name'][0])) {
            foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
                if (!empty($tmp_name) && $_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                    $fileName = $_FILES['attachments']['name'][$key];
                    $fileSize = $_FILES['attachments']['size'][$key];
                    
                    $mail->addAttachment($tmp_name, $fileName);
                    $attachments[] = [
                        'name' => $fileName,
                        'size' => formatFileSize($fileSize)
                    ];
                }
            }
        }
        
        // Load the email template
        $templatePath = __DIR__ . '/templates/template1.html';
        
        if (!file_exists($templatePath)) {
            throw new Exception("Email template not found");
        }
        
        $emailTemplate = file_get_contents($templatePath);
        
        // Replace placeholders in template
        $emailBody = str_replace([
            '{{articletitle}}',
            '{{MESSAGE}}',
            '{{SIGNATURE_WISH}}',
            '{{SIGNATURE_NAME}}',
            '{{SIGNATURE_DESIGNATION}}',
            '{{SIGNATURE_EXTRA}}'
        ], [
            htmlspecialchars($articleTitle),
            nl2br(htmlspecialchars($messageContent)),
            htmlspecialchars($signatureWish),
            htmlspecialchars($signatureName),
            htmlspecialchars($signatureDesignation),
            nl2br(htmlspecialchars($signatureExtra))
        ], $emailTemplate);
        
        // Set email content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $emailBody;
        
        // Plain text alternative
        $mail->AltBody = strip_tags($messageContent);

        // Send email
        if ($mail->send()) {
            // Prepare success data
            $successEmails = [['email' => $recipient, 'type' => 'TO']];
            
            foreach ($ccEmails as $cc) {
                $cc = trim($cc);
                if ($cc) {
                    $successEmails[] = ['email' => $cc, 'type' => 'CC'];
                }
            }
            
            foreach ($bccEmails as $bcc) {
                $bcc = trim($bcc);
                if ($bcc) {
                    $successEmails[] = ['email' => $bcc, 'type' => 'BCC'];
                }
            }
            
            $summary = [
                'subject' => $subject,
                'article_title' => $articleTitle,
                'sent_at' => date('M d, Y h:i A'),
                'sender_name' => $displayName,
                'cc_count' => count($ccEmails),
                'bcc_count' => count($bccEmails),
                'attachment_count' => count($attachments)
            ];
            
            // Try to save to database
            $dbSaved = false;
            try {
                if (isset($pdo)) {
                    $stmt = $pdo->prepare("INSERT INTO sent_emails (sender_email, recipient_email, subject, message, article_title, sent_at, status) VALUES (?, ?, ?, ?, ?, NOW(), 'sent')");
                    $stmt->execute([
                        $_SESSION['smtp_user'],
                        $recipient,
                        $subject,
                        $messageContent,
                        $articleTitle
                    ]);
                    $dbSaved = true;
                }
            } catch (Exception $e) {
                // Database save failed, but email was sent successfully
                $dbSaved = false;
            }
            
            showSuccessPage($successEmails, $attachments, $summary, $dbSaved);
        }
        
    } catch (Exception $e) {
        showErrorPage($e->getMessage());
    }
} else {
    header("Location: index.php");
    exit();
}

/**
 * Format file size to human readable format
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Show success page
 */
function showSuccessPage($successEmails, $attachments, $summary, $dbSaved) {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Sent Successfully - SXC MDTS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #1c1c1e;
            min-height: 100vh;
            padding: 20px;
        }

        .success-container {
            max-width: 900px;
            margin: 40px auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .success-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 50px 40px;
            text-align: center;
        }

        .success-icon {
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            animation: scaleIn 0.6s ease-out;
        }

        .success-icon i {
            font-size: 50px;
            color: white;
        }

        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }

        h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .success-subtitle {
            font-size: 16px;
            opacity: 0.95;
        }

        .success-body {
            padding: 40px;
        }

        .summary-card {
            background: #f8f9fa;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
        }

        .summary-title {
            font-size: 18px;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .summary-title i {
            color: #667eea;
        }

        .summary-full {
            background: white;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 16px;
            border-left: 4px solid #667eea;
        }

        .summary-full-label {
            font-size: 12px;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .summary-full-value {
            font-size: 16px;
            font-weight: 600;
            color: #1c1c1e;
            word-break: break-word;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }

        .summary-item {
            background: white;
            padding: 16px;
            border-radius: 12px;
            transition: transform 0.2s;
        }

        .summary-item:hover {
            transform: translateY(-2px);
        }

        .summary-item-label {
            font-size: 12px;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .summary-item-value {
            font-size: 18px;
            font-weight: 700;
            color: #667eea;
        }

        .recipients-card {
            background: #f8f9fa;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
        }

        .email-list {
            list-style: none;
            margin-top: 20px;
        }

        .email-list li {
            background: white;
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.2s;
        }

        .email-list li:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .email-list li i {
            color: #28a745;
            font-size: 16px;
        }

        .email-badge {
            margin-left: auto;
            background: #667eea;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .attachments-list {
            margin-top: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 12px;
        }

        .attachment-item {
            background: white;
            padding: 16px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.2s;
        }

        .attachment-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .attachment-item i {
            font-size: 24px;
            color: #667eea;
        }

        .attachment-info {
            flex: 1;
            min-width: 0;
        }

        .attachment-name {
            font-weight: 600;
            font-size: 14px;
            color: #333;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .attachment-size {
            font-size: 12px;
            color: #888;
            margin-top: 2px;
        }

        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }

        .warning-box i {
            color: #856404;
            font-size: 20px;
            margin-top: 2px;
        }

        .warning-box strong {
            color: #856404;
        }

        .action-buttons {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 30px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 16px 32px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn-secondary:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .success-header {
                padding: 40px 20px;
            }

            .success-body {
                padding: 30px 20px;
            }

            h1 {
                font-size: 26px;
            }

            .summary-card,
            .recipients-card {
                padding: 20px;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-header">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1>Email Sent Successfully!</h1>
            <p class="success-subtitle">Your message has been delivered to all recipients</p>
        </div>

        <div class="success-body">
            <!-- Summary Card -->
            <div class="summary-card">
                <div class="summary-title">
                    <i class="fas fa-info-circle"></i>
                    Email Summary
                </div>

                <div class="summary-full">
                    <div class="summary-full-label">Subject</div>
                    <div class="summary-full-value">
                        <?= htmlspecialchars($summary['subject']) ?>
                    </div>
                </div>

                <?php if (!empty($summary['article_title'])): ?>
                <div class="summary-full">
                    <div class="summary-full-label">Article Title</div>
                    <div class="summary-full-value">
                        <?= htmlspecialchars($summary['article_title']) ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="summary-grid">
                    <div class="summary-item">
                        <div class="summary-item-label">Sent At</div>
                        <div class="summary-item-value">
                            <?= $summary['sent_at'] ?>
                        </div>
                    </div>

                    <div class="summary-item">
                        <div class="summary-item-label">From</div>
                        <div class="summary-item-value">
                            <?= htmlspecialchars($summary['sender_name']) ?>
                        </div>
                    </div>

                    <div class="summary-item">
                        <div class="summary-item-label">Total Recipients</div>
                        <div class="summary-item-value">
                            <?= count($successEmails) ?>
                            <?php if ($summary['cc_count'] > 0 || $summary['bcc_count'] > 0): ?>
                            <span style="font-size: 12px; color: #666;">
                                (<?= $summary['cc_count'] ?> CC, <?= $summary['bcc_count'] ?> BCC)
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="summary-item">
                        <div class="summary-item-label">Attachments</div>
                        <div class="summary-item-value">
                            <?= $summary['attachment_count'] ?> file<?= $summary['attachment_count'] != 1 ? 's' : '' ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recipients -->
            <div class="recipients-card">
                <div class="summary-title">
                    <i class="fas fa-users"></i>
                    Recipients (<?= count($successEmails) ?>)
                </div>
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

            <!-- Attachments -->
            <?php if (!empty($attachments)): ?>
            <div class="recipients-card">
                <div class="summary-title">
                    <i class="fas fa-paperclip"></i>
                    Attachments (<?= count($attachments) ?>)
                </div>
                <div class="attachments-list">
                    <?php foreach ($attachments as $att): ?>
                    <div class="attachment-item">
                        <i class="fas fa-file"></i>
                        <div class="attachment-info">
                            <div class="attachment-name" title="<?= htmlspecialchars($att['name']) ?>">
                                <?= htmlspecialchars($att['name']) ?>
                            </div>
                            <div class="attachment-size">
                                <?= $att['size'] ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Warning if DB not saved -->
            <?php if (!$dbSaved): ?>
            <div class="warning-box">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Note:</strong> Email was sent successfully but could not be saved to the database. This won't affect delivery.
                </div>
            </div>
            <?php endif; ?>

            <!-- Action Buttons -->
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: #1c1c1e;
            min-height: 100vh;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .error-container {
            max-width: 600px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .error-header {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 50px 40px;
            text-align: center;
        }

        .error-icon {
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            animation: shake 0.5s ease-out;
        }

        .error-icon i {
            font-size: 50px;
            color: white;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .error-subtitle {
            font-size: 16px;
            opacity: 0.95;
        }

        .error-body {
            padding: 40px;
        }

        .error-message {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px 24px;
            margin-bottom: 30px;
            border-radius: 12px;
            word-break: break-word;
        }

        .error-message strong {
            display: block;
            margin-bottom: 10px;
            color: #856404;
            font-size: 15px;
        }

        .error-message p {
            color: #856404;
            line-height: 1.6;
            font-size: 14px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 16px 32px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s;
            width: 100%;
            justify-content: center;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(245, 87, 108, 0.4);
        }

        @media (max-width: 768px) {
            .error-header {
                padding: 40px 20px;
            }

            .error-body {
                padding: 30px 20px;
            }

            h1 {
                font-size: 26px;
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-header">
            <div class="error-icon">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <h1>Email Sending Failed</h1>
            <p class="error-subtitle">We encountered an error while sending your email</p>
        </div>

        <div class="error-body">
            <div class="error-message">
                <strong>Error Details:</strong>
                <p><?= htmlspecialchars($errorMessage) ?></p>
            </div>

            <a href="index.php" class="btn">
                <i class="fas fa-arrow-left"></i>
                Try Again
            </a>
        </div>
    </div>
</body>
</html>
<?php
}
?>