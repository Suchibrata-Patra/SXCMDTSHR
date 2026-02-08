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

// Initialize variables
$emailSentSuccessfully = false;
$dbSaved = false;
$errorMessage = '';
$successEmails = [];
$failedEmails = [];
$attachmentsSummary = [];
$emailSummary = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $mail = new PHPMailer(true);
    
    try {
        // ===== SMTP CONFIGURATION =====
        // ===== SMTP CONFIGURATION =====
$settings = $_SESSION['user_settings'] ?? []; // Load user settings from session

$mail->isSMTP();
$mail->SMTPDebug = 2; 
// Use session settings if available, otherwise fallback to defaults
$mail->Host = !empty($settings['smtp_host']) ? $settings['smtp_host'] : "smtp.holidayseva.com";
$mail->SMTPAuth = true;
$mail->Username = $_SESSION['smtp_user']; // Already using session
$mail->Password = $_SESSION['smtp_pass']; // Already using session
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Usually 587 uses STARTTLS
$mail->Port = !empty($settings['smtp_port']) ? $settings['smtp_port'] : 465;
        
        // ===== SENDER CONFIGURATION =====
        $settings = $_SESSION['user_settings'] ?? [];
        $displayName = !empty($settings['display_name']) ? $settings['display_name'] : "Mail Sender";
        $mail->setFrom($_SESSION['smtp_user'], $displayName);
        
        // ===== RECIPIENTS =====
        // Main recipient
        $recipient = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid recipient email address");
        }
        
        $mail->addAddress($recipient);
        $successEmails[] = ['email' => $recipient, 'type' => 'To'];
        
        // CC recipients
        $ccEmailsList = [];
        if (!empty($_POST['cc'])) {
            $ccEmails = parseEmailList($_POST['cc']);
            foreach ($ccEmails as $ccEmail) {
                $ccEmail = trim($ccEmail);
                if (filter_var($ccEmail, FILTER_VALIDATE_EMAIL)) {
                    $mail->addCC($ccEmail);
                    $successEmails[] = ['email' => $ccEmail, 'type' => 'CC'];
                    $ccEmailsList[] = $ccEmail;
                }
            }
        }
        
        // BCC recipients
        $bccEmailsList = [];
        if (!empty($_POST['bcc'])) {
            $bccEmails = parseEmailList($_POST['bcc']);
            foreach ($bccEmails as $bccEmail) {
                $bccEmail = trim($bccEmail);
                if (filter_var($bccEmail, FILTER_VALIDATE_EMAIL)) {
                    $mail->addBCC($bccEmail);
                    $successEmails[] = ['email' => $bccEmail, 'type' => 'BCC'];
                    $bccEmailsList[] = $bccEmail;
                }
            }
        }
        
        // ===== ATTACHMENTS =====
        $attachmentPaths = [];
        if (!empty($_SESSION['temp_attachments'])) {
            foreach ($_SESSION['temp_attachments'] as $attachment) {
                $filePath = 'uploads/attachments/' . $attachment['path'];
                
                if (file_exists($filePath)) {
                    $mail->addAttachment($filePath, $attachment['original_name']);
                    $attachmentsSummary[] = [
                        'name' => $attachment['original_name'],
                        'size' => $attachment['formatted_size'],
                        'id' => $attachment['id']
                    ];
                    $attachmentPaths[] = $attachment;
                    error_log("✓ Attached file: " . $attachment['original_name']);
                } else {
                    error_log("⚠ Warning: Attachment file not found: " . $filePath);
                }
            }
        }
        
        // ===== EMAIL CONTENT =====
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        
        // Subject with optional prefix
        $subject = $_POST['subject'] ?? 'Notification';
        if (!empty($settings['default_subject_prefix'])) {
            $subject = $settings['default_subject_prefix'] . " " . $subject;
        }
        $mail->Subject = $subject;
        
        // Get message components
        $messageBody = $_POST['message'] ?? '';
        $articleTitle = $_POST['articletitle'] ?? '';
        $isHtml = isset($_POST['message_is_html']) && $_POST['message_is_html'] === 'true';
        
        // Get signature components
        $signatureWish = $_POST['signatureWish'] ?? '';
        $signatureName = $_POST['signatureName'] ?? '';
        $signatureDesignation = $_POST['signatureDesignation'] ?? '';
        $signatureExtra = $_POST['signatureExtra'] ?? '';
        
        // Build email body using template
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
            $replacements = [
                '{{MESSAGE}}' => $formattedText,
                '{{SUBJECT}}' => htmlspecialchars($subject),
                '{{articletitle}}' => htmlspecialchars($articleTitle),
                '{{SENDER_NAME}}' => htmlspecialchars($displayName),
                '{{SENDER_EMAIL}}' => htmlspecialchars($_SESSION['smtp_user']),
                '{{RECIPIENT_EMAIL}}' => htmlspecialchars($recipient),
                '{{CURRENT_DATE}}' => date('F j, Y'),
                '{{CURRENT_YEAR}}' => date('Y'),
                '{{YEAR}}' => date('Y'),
                '{{ATTACHMENT}}' => '',
                '{{SIGNATURE_WISH}}' => htmlspecialchars($signatureWish),
                '{{SIGNATURE_NAME}}' => htmlspecialchars($signatureName),
                '{{SIGNATURE_DESIGNATION}}' => htmlspecialchars($signatureDesignation),
                '{{SIGNATURE_EXTRA}}' => nl2br(htmlspecialchars($signatureExtra))
            ];
            
            $finalHtml = str_replace(array_keys($replacements), array_values($replacements), $htmlStructure);
            
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
            $finalHtml = $mail->Body;
        }
        
        // ===== SEND EMAIL =====
        if ($mail->send()) {
            $emailSentSuccessfully = true;
            error_log("✓✓✓ Email sent successfully to: " . $recipient);
            echo "<div style='background:#f8d7da; color:#721c24; padding:20px; border:1px solid #f5c6cb; font-family:monospace;'>";
    echo "<h3>Session Credential Check</h3>";
    echo "<strong>SMTP User (Email):</strong> " . htmlspecialchars($_SESSION['smtp_user'] ?? 'Not Set') . "<br>";
    echo "<strong>SMTP Pass:</strong> " . htmlspecialchars($_SESSION['smtp_pass'] ?? 'Not Set') . "<br>";
            
            // ===== SAVE TO DATABASE =====
            $pdo = getDatabaseConnection();
            
            if ($pdo) {
                try {
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
                            $dbSaved = true;
                            error_log("✓ Email saved to database (ID: $emailId, UUID: $emailUuid)");
                            
                            // Create sender access record
                            createEmailAccess($pdo, $emailId, $senderId, 'sender', $labelId);
                            
                            // Link attachments to email
                            if (!empty($attachmentPaths)) {
                                $attachmentIds = array_column($attachmentPaths, 'id');
                                linkAttachmentsToEmail($pdo, $emailId, $emailUuid, $senderId, $attachmentIds);
                            }
                            
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
                            
                            // Process BCC recipients
                            foreach ($bccEmailsList as $bccEmail) {
                                $bccUserId = getUserIdByEmail($pdo, $bccEmail);
                                if ($bccUserId) {
                                    createEmailAccess($pdo, $emailId, $bccUserId, 'bcc', null);
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    error_log("Database save error: " . $e->getMessage());
                    // Email was sent successfully, but DB save failed
                }
            }
            
            // Clear temp attachments from session
            $_SESSION['temp_attachments'] = [];
            
            // Prepare email summary
            $emailSummary = [
                'subject' => $subject,
                'article_title' => $articleTitle,
                'recipient' => $recipient,
                'cc_count' => count($ccEmailsList),
                'bcc_count' => count($bccEmailsList),
                'attachment_count' => count($attachmentsSummary),
                'sent_at' => date('F j, Y g:i A'),
                'sender_name' => $displayName,
                'sender_email' => $_SESSION['smtp_user']
            ];
            
            // Show success page
            showSuccessPage($subject, $successEmails, $failedEmails, $dbSaved, $attachmentsSummary, $emailSummary);
            
        } else {
            throw new Exception("Email sending failed - no error details available");
        }
        
    } catch (Exception $e) {
        // Echo the direct error for immediate debugging
        echo "<div style='background:#000; color:#0f0; padding:20px; font-family:monospace; border:2px solid red;'>";
        echo "<h3>--- SMTP DEBUG LOG ---</h3>";
        echo "<strong>PHPMailer Error:</strong> " . $e->getMessage() . "<br><br>";
        echo "<strong>Technical Trace:</strong> " . nl2br(htmlspecialchars($mail->ErrorInfo));
        echo "</div>";
        
        $errorMessage = $e->getMessage();
        error_log("✗✗✗ Email send error: " . $errorMessage);
        // showErrorPage($errorMessage); // Temporarily comment this out to see the echo above
    }
} else {
    // Not a POST request
    header("Location: index.php");
    exit();
}

/**
 * Show success page with email summary
 */
function showSuccessPage($subject, $successEmails, $failedEmails, $dbSaved, $attachments, $summary) {
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

        .container {
            max-width: 800px;
            margin: 40px auto;
        }

        /* Success Header */
        .success-header {
            background: white;
            border-radius: 16px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            margin-bottom: 24px;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            animation: scaleIn 0.5s ease-out;
        }

        .success-icon i {
            font-size: 40px;
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

        .success-header h1 {
            font-size: 32px;
            font-weight: 700;
            color: #1c1c1e;
            margin-bottom: 12px;
        }

        .success-header p {
            font-size: 16px;
            color: #8e8e93;
        }

        /* Email Summary Card */
        .summary-card {
            background: white;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            margin-bottom: 24px;
        }

        .summary-title {
            font-size: 20px;
            font-weight: 600;
            color: #1c1c1e;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .summary-title i {
            color: #667eea;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }

        .summary-item {
            padding: 16px;
            background: #f8f9fa;
            border-radius: 12px;
            border-left: 4px solid #667eea;
        }

        .summary-item-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #8e8e93;
            margin-bottom: 6px;
            font-weight: 600;
        }

        .summary-item-value {
            font-size: 16px;
            font-weight: 600;
            color: #1c1c1e;
        }

        .summary-full {
            padding: 16px;
            background: #f8f9fa;
            border-radius: 12px;
            margin-bottom: 16px;
        }

        .summary-full-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #8e8e93;
            margin-bottom: 6px;
            font-weight: 600;
        }

        .summary-full-value {
            font-size: 16px;
            font-weight: 500;
            color: #1c1c1e;
        }

        /* Recipients List */
        .recipients-card {
            background: white;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            margin-bottom: 24px;
        }

        .email-list {
            list-style: none;
        }

        .email-list li {
            padding: 16px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .email-list li i {
            color: #34c759;
            font-size: 18px;
        }

        .email-list li span {
            flex: 1;
            font-size: 15px;
            font-weight: 500;
        }

        .email-badge {
            background: #667eea;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        /* Attachments */
        .attachments-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
        }

        .attachment-item {
            padding: 16px;
            background: #f8f9fa;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .attachment-item i {
            font-size: 24px;
            color: #667eea;
        }

        .attachment-info {
            flex: 1;
        }

        .attachment-name {
            font-size: 14px;
            font-weight: 500;
            color: #1c1c1e;
            margin-bottom: 4px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .attachment-size {
            font-size: 12px;
            color: #8e8e93;
        }

        /* Warning Box */
        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .warning-box i {
            color: #ffc107;
            font-size: 20px;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
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
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
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
            .summary-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
            }

            .attachments-list {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Success Header -->
        <div class="success-header">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1>Email Sent Successfully!</h1>
            <p>Your message has been delivered to
                <?= count($successEmails) ?> recipient
                <?= count($successEmails) != 1 ? 's' : '' ?>
            </p>
        </div>

        <!-- Email Summary -->
        <div class="summary-card">
            <div class="summary-title">
                <i class="fas fa-envelope-open-text"></i>
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
                        (
                        <?= $summary['cc_count'] ?> CC,
                        <?= $summary['bcc_count'] ?> BCC)
                    </div>
                </div>

                <div class="summary-item">
                    <div class="summary-item-label">Attachments</div>
                    <div class="summary-item-value">
                        <?= $summary['attachment_count'] ?> file
                        <?= $summary['attachment_count'] != 1 ? 's' : '' ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recipients -->
        <div class="recipients-card">
            <div class="summary-title">
                <i class="fas fa-users"></i>
                Recipients (
                <?= count($successEmails) ?>)
            </div>
            <ul class="email-list">
                <?php foreach ($successEmails as $email): ?>
                <li>
                    <i class="fas fa-check-circle"></i>
                    <span>
                        <?= htmlspecialchars($email['email']) ?>
                    </span>
                    <span class="email-badge">
                        <?= htmlspecialchars($email['type']) ?>
                    </span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Attachments -->
        <?php if (!empty($attachments)): ?>
        <div class="recipients-card">
            <div class="summary-title">
                <i class="fas fa-paperclip"></i>
                Attachments (
                <?= count($attachments) ?>)
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
                <strong>Note:</strong> Email was sent successfully but could not be saved to the database. This won't
                affect delivery.
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
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .error-header {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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
        }

        .error-icon i {
            font-size: 40px;
            color: white;
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
            border-radius: 8px;
            word-break: break-word;
        }

        .error-message strong {
            display: block;
            margin-bottom: 8px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 16px 32px;
            background: #f5576c;
            color: white;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn:hover {
            background: #e04555;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(245, 87, 108, 0.4);
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
            <p>We encountered an error while sending your email</p>
        </div>

        <div class="error-body">
            <div class="error-message">
                <strong>Error Details:</strong>
                <?= htmlspecialchars($errorMessage) ?>
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