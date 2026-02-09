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
        
        // ==================== Capture ALL Form Fields ====================
        
        // Primary recipient (required)
        $recipient = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        
        // Subject (required)
        $subject = trim($_POST['subject'] ?? 'Official Communication');
        
        // Article title for template (required)
        $articleTitle = trim($_POST['articletitle'] ?? 'Official Communication');
        
        // Message content (HTML from Quill editor)
        $messageContent = $_POST['message'] ?? '';
        
        // CC and BCC emails (comma-separated, optional)
        $ccEmails = !empty($_POST['ccEmails']) ? array_map('trim', explode(',', $_POST['ccEmails'])) : [];
        $bccEmails = !empty($_POST['bccEmails']) ? array_map('trim', explode(',', $_POST['bccEmails'])) : [];
        
        // Signature fields (optional with defaults)
        $signatureWish = trim($_POST['signatureWish'] ?? 'Best Regards,');
        $signatureName = trim($_POST['signatureName'] ?? 'Dr. Durba Bhattacharya');
        $signatureDesignation = trim($_POST['signatureDesignation'] ?? 'Head of Department, Data Science');
        $signatureExtra = trim($_POST['signatureExtra'] ?? 'St. Xavier\'s College (Autonomous), Kolkata');
        
        // Attachment IDs from session-based upload system
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
        
        // Add CC recipients
        $validCCs = [];
        foreach ($ccEmails as $cc) {
            $cc = filter_var($cc, FILTER_SANITIZE_EMAIL);
            if ($cc && filter_var($cc, FILTER_VALIDATE_EMAIL)) {
                $mail->addCC($cc);
                $validCCs[] = $cc;
            }
        }
        
        // Add BCC recipients
        $validBCCs = [];
        foreach ($bccEmails as $bcc) {
            $bcc = filter_var($bcc, FILTER_SANITIZE_EMAIL);
            if ($bcc && filter_var($bcc, FILTER_VALIDATE_EMAIL)) {
                $mail->addBCC($bcc);
                $validBCCs[] = $bcc;
            }
        }
        
        // ==================== Handle Attachments from Session - FIXED VERSION ====================
        $attachments = [];
        $uploadDir = 'uploads/attachments/'; // Base directory for attachments
        
        if (!empty($attachmentIds) && isset($_SESSION['temp_attachments'])) {
            $sessionAttachments = $_SESSION['temp_attachments'];
            
            foreach ($attachmentIds as $attachmentId) {
                $attachmentId = trim($attachmentId);
                
                // Find attachment in session
                foreach ($sessionAttachments as $attachment) {
                    if (isset($attachment['id']) && $attachment['id'] == $attachmentId) {
                        // FIXED: Construct the full file path correctly
                        $relativePath = $attachment['path'] ?? '';
                        $fullFilePath = $uploadDir . $relativePath;
                        $fileName = $attachment['original_name'] ?? 'attachment';
                        
                        // Check if file exists
                        if (file_exists($fullFilePath)) {
                            // Add attachment to email
                            $mail->addAttachment($fullFilePath, $fileName);
                            
                            $attachments[] = [
                                'name' => $fileName,
                                'size' => formatFileSize($attachment['file_size'] ?? 0),
                                'extension' => $attachment['extension'] ?? 'file'
                            ];
                            
                            error_log("Attached file: $fullFilePath as $fileName");
                        } else {
                            error_log("WARNING: Attachment file not found: $fullFilePath");
                            // Continue processing - don't fail the whole email if one attachment is missing
                        }
                        break;
                    }
                }
            }
        }
        
        // ==================== Load and Process Email Template ====================
        $templatePath = __DIR__ . '/templates/template1.html';
        
        if (!file_exists($templatePath)) {
            throw new Exception("Email template not found at: " . $templatePath);
        }
        
        $emailTemplate = file_get_contents($templatePath);
        
        // Replace ALL placeholders in template with actual values
        $emailBody = str_replace([
            '{{articletitle}}',
            '{{MESSAGE}}',
            '{{SIGNATURE_WISH}}',
            '{{SIGNATURE_NAME}}',
            '{{SIGNATURE_DESIGNATION}}',
            '{{SIGNATURE_EXTRA}}'
        ], [
            htmlspecialchars($articleTitle, ENT_QUOTES, 'UTF-8'),
            $messageContent, // Already HTML from Quill
            htmlspecialchars($signatureWish, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($signatureName, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($signatureDesignation, ENT_QUOTES, 'UTF-8'),
            nl2br(htmlspecialchars($signatureExtra, ENT_QUOTES, 'UTF-8'))
        ], $emailTemplate);
        
        // ==================== Set Email Content ====================
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $emailBody;
        
        // Create plain text alternative (strip HTML tags from message)
        $plainTextMessage = strip_tags($messageContent);
        $mail->AltBody = "Article: " . $articleTitle . "\n\n" . $plainTextMessage . "\n\n" . 
                         $signatureWish . "\n" . $signatureName . "\n" . 
                         $signatureDesignation . "\n" . $signatureExtra;

        // ==================== Send Email ====================
        if ($mail->send()) {
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
            
            // Try to save to database
            $dbSaved = false;
            try {
                $pdo = getDatabaseConnection();
                if ($pdo) {
                    // First, try to get the actual table structure
                    // Use a simpler insert that works with most schemas
                    $stmt = $pdo->prepare("
                        INSERT INTO sent_emails 
                        (sender_email, recipient_email, subject, article_title, sent_at, current_status) 
                        VALUES (?, ?, ?, ?, NOW(), 'sent')
                    ");
                    
                    $stmt->execute([
                        $_SESSION['smtp_user'],
                        $recipient,
                        $subject,
                        $articleTitle
                    ]);
                    $dbSaved = true;
                }
            } catch (Exception $e) {
                // Database save failed, but email was sent successfully
                error_log("Database save failed: " . $e->getMessage());
                $dbSaved = false;
            }
            
            // Clear temp attachments after successful send
            $_SESSION['temp_attachments'] = [];
            
            // Show success page
            showSuccessPage($successEmails, $attachments, $summary, $dbSaved);
        }
        
    } catch (Exception $e) {
        // Show error page
        showErrorPage($e->getMessage());
    }
} else {
    header("Location: index.php");
    exit();
}

/**
 * Format file size helper
 */
function formatFileSize($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

/**
 * Show success page
 */
function showSuccessPage($successEmails, $attachments, $summary, $dbSaved = false) {
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
            max-width: 700px;
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
            animation: scaleIn 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
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
            padding: 0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            border: 1px solid #e5e7eb;
            overflow: hidden;
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

        .summary-section {
            padding: 30px;
            border-bottom: 1px solid #e5e7eb;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-label {
            font-size: 14px;
            color: #6b7280;
            font-weight: 500;
        }

        .summary-value {
            font-size: 14px;
            color: #1c1c1e;
            font-weight: 600;
            text-align: right;
        }

        .recipients-section {
            padding: 30px;
            border-bottom: 1px solid #e5e7eb;
        }

        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: #1c1c1e;
            margin-bottom: 16px;
        }

        .recipient-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: #f9fafb;
            border-radius: 10px;
            margin-bottom: 10px;
        }

        .recipient-item:last-child {
            margin-bottom: 0;
        }

        .recipient-email {
            font-size: 14px;
            color: #1c1c1e;
            font-weight: 500;
        }

        .recipient-type {
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 600;
        }

        .recipient-type.to {
            background: #dbeafe;
            color: #1e40af;
        }

        .recipient-type.cc {
            background: #fef3c7;
            color: #92400e;
        }

        .recipient-type.bcc {
            background: #e0e7ff;
            color: #3730a3;
        }

        .attachments-section {
            padding: 30px;
            border-bottom: 1px solid #e5e7eb;
        }

        .attachment-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            background: #f9fafb;
            border-radius: 10px;
            margin-bottom: 10px;
        }

        .attachment-item:last-child {
            margin-bottom: 0;
        }

        .attachment-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            border-radius: 8px;
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
            font-size: 14px;
            color: #1c1c1e;
            font-weight: 500;
            margin-bottom: 2px;
        }

        .attachment-size {
            font-size: 12px;
            color: #6b7280;
        }

        .action-buttons {
            padding: 30px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 14px 24px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #007AFF;
            color: white;
        }

        .btn-primary:hover {
            background: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 122, 255, 0.3);
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #1c1c1e;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
            transform: translateY(-2px);
        }

        .db-warning {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 16px 20px;
            border-radius: 10px;
            margin: 20px 30px;
        }

        .db-warning p {
            color: #92400e;
            font-size: 13px;
            line-height: 1.5;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }

            .success-title {
                font-size: 26px;
            }

            .action-buttons {
                grid-template-columns: 1fr;
            }

            .summary-section,
            .recipients-section,
            .attachments-section {
                padding: 24px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
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
                    <!-- Summary Section -->
                    <div class="summary-section">
                        <div class="summary-row">
                            <span class="summary-label">Subject</span>
                            <span class="summary-value"><?= htmlspecialchars($summary['subject']) ?></span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Article Title</span>
                            <span class="summary-value"><?= htmlspecialchars($summary['article_title']) ?></span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Sent At</span>
                            <span class="summary-value"><?= $summary['sent_at'] ?></span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">From</span>
                            <span class="summary-value"><?= htmlspecialchars($summary['sender_name']) ?></span>
                        </div>
                    </div>

                    <!-- Recipients Section -->
                    <div class="recipients-section">
                        <h3 class="section-title">Recipients (<?= count($successEmails) ?>)</h3>
                        <?php foreach ($successEmails as $email): ?>
                        <div class="recipient-item">
                            <span class="recipient-email"><?= htmlspecialchars($email['email']) ?></span>
                            <span class="recipient-type <?= strtolower($email['type']) ?>"><?= $email['type'] ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Attachments Section -->
                    <?php if (!empty($attachments)): ?>
                    <div class="attachments-section">
                        <h3 class="section-title">Attachments (<?= count($attachments) ?>)</h3>
                        <?php foreach ($attachments as $attachment): ?>
                        <div class="attachment-item">
                            <div class="attachment-icon">
                                <i class="fas fa-file"></i>
                            </div>
                            <div class="attachment-details">
                                <div class="attachment-name"><?= htmlspecialchars($attachment['name']) ?></div>
                                <div class="attachment-size"><?= $attachment['size'] ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Database Warning -->
                    <?php if (!$dbSaved): ?>
                    <div class="db-warning">
                        <p><strong>Note:</strong> Email was sent successfully, but could not be saved to the database. This doesn't affect delivery.</p>
                    </div>
                    <?php endif; ?>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i>
                            Send Another Email
                        </a>
                        <a href="inbox.php" class="btn btn-secondary">
                            <i class="fas fa-inbox"></i>
                            Go to Inbox
                        </a>
                    </div>
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
            animation: shake 0.5s ease-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
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

        .error-subtitle {
            font-size: 16px;
            color: #6b7280;
            font-weight: 400;
        }

        .error-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            border: 1px solid #e5e7eb;
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
            word-break: break-word;
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

        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }

            .error-title {
                font-size: 26px;
            }

            .error-card {
                padding: 24px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <div class="error-wrapper">
                <div class="error-header">
                    <div class="error-icon-wrapper">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <h1 class="error-title">Email Sending Failed</h1>
                    <p class="error-subtitle">We encountered an error while sending your email</p>
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