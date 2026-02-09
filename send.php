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
        $displayName = !empty($settings['display_name']) ? $settings['display_name'] : "`";
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
            overflow-y: auto;
        }

        .success-wrapper {
            max-width: 900px;
            margin: 0 auto;
        }

        /* Success Header */
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
            animation: scaleIn 0.5s ease-out 0.2s both;
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

        /* Cards */
        .card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 24px;
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

        .card-title {
            font-size: 18px;
            font-weight: 700;
            color: #1c1c1e;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-title i {
            color: #007AFF;
            font-size: 20px;
        }

        /* Summary Details */
        .summary-item {
            background: #f8f9fa;
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 12px;
            border-left: 4px solid #007AFF;
        }

        .summary-label {
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .summary-value {
            font-size: 15px;
            font-weight: 600;
            color: #1c1c1e;
            word-break: break-word;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 20px;
        }

        .stat-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-box:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .stat-label {
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #007AFF;
        }

        .stat-subtext {
            font-size: 11px;
            color: #9ca3af;
            margin-top: 4px;
        }

        /* Recipients List */
        .recipient-list {
            list-style: none;
        }

        .recipient-item {
            background: #f8f9fa;
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.2s;
        }

        .recipient-item:hover {
            background: #e5e7eb;
            transform: translateX(4px);
        }

        .recipient-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .recipient-icon {
            width: 36px;
            height: 36px;
            background: #007AFF;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 14px;
        }

        .recipient-email {
            font-size: 14px;
            font-weight: 500;
            color: #1c1c1e;
        }

        .recipient-badge {
            background: #007AFF;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Attachments */
        .attachments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 12px;
            margin-top: 16px;
        }

        .attachment-card {
            background: #f8f9fa;
            padding: 16px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.2s;
        }

        .attachment-card:hover {
            background: #e5e7eb;
            transform: translateY(-2px);
        }

        .attachment-icon {
            width: 44px;
            height: 44px;
            background: #007AFF;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            flex-shrink: 0;
        }

        .attachment-info {
            flex: 1;
            min-width: 0;
        }

        .attachment-name {
            font-weight: 600;
            font-size: 14px;
            color: #1c1c1e;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 4px;
        }

        .attachment-size {
            font-size: 12px;
            color: #6b7280;
        }

        /* Warning Box */
        .warning-box {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 16px 20px;
            border-radius: 10px;
            display: flex;
            gap: 12px;
            align-items: flex-start;
            margin-bottom: 24px;
        }

        .warning-box i {
            color: #d97706;
            font-size: 20px;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .warning-content {
            flex: 1;
        }

        .warning-content strong {
            color: #92400e;
            display: block;
            margin-bottom: 4px;
        }

        .warning-content p {
            color: #78350f;
            font-size: 14px;
            line-height: 1.5;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            margin-top: 40px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 28px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
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
            background: white;
            color: #1c1c1e;
            border: 2px solid #e5e7eb;
        }

        .btn-secondary:hover {
            background: #f8f9fa;
            border-color: #d1d5db;
            transform: translateY(-2px);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .main-content {
                padding: 30px 20px;
            }
        }

        @media (max-width: 768px) {
            .success-title {
                font-size: 26px;
            }

            .stats-grid {
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
    <div class="app-container">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <div class="success-wrapper">
                <!-- Success Header -->
                <div class="success-header">
                    <div class="success-icon-wrapper">
                        <i class="fas fa-check"></i>
                    </div>
                    <h1 class="success-title">Email Sent Successfully!</h1>
                    <p class="success-subtitle">Your message has been delivered to all recipients</p>
                </div>

                <!-- Email Summary Card -->
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-info-circle"></i>
                        Email Summary
                    </div>

                    <div class="summary-item">
                        <div class="summary-label">Subject</div>
                        <div class="summary-value"><?= htmlspecialchars($summary['subject']) ?></div>
                    </div>

                    <?php if (!empty($summary['article_title'])): ?>
                    <div class="summary-item">
                        <div class="summary-label">Article Title</div>
                        <div class="summary-value"><?= htmlspecialchars($summary['article_title']) ?></div>
                    </div>
                    <?php endif; ?>

                    <div class="stats-grid">
                        <div class="stat-box">
                            <div class="stat-label">Sent At</div>
                            <div class="stat-value" style="font-size: 16px;"><?= $summary['sent_at'] ?></div>
                        </div>

                        <div class="stat-box">
                            <div class="stat-label">From</div>
                            <div class="stat-value" style="font-size: 16px; word-break: break-word;"><?= htmlspecialchars($summary['sender_name']) ?></div>
                        </div>

                        <div class="stat-box">
                            <div class="stat-label">Recipients</div>
                            <div class="stat-value"><?= count($successEmails) ?></div>
                            <?php if ($summary['cc_count'] > 0 || $summary['bcc_count'] > 0): ?>
                            <div class="stat-subtext"><?= $summary['cc_count'] ?> CC • <?= $summary['bcc_count'] ?> BCC</div>
                            <?php endif; ?>
                        </div>

                        <div class="stat-box">
                            <div class="stat-label">Attachments</div>
                            <div class="stat-value"><?= $summary['attachment_count'] ?></div>
                            <div class="stat-subtext">file<?= $summary['attachment_count'] != 1 ? 's' : '' ?></div>
                        </div>
                    </div>
                </div>

                <!-- Recipients Card -->
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-users"></i>
                        Recipients (<?= count($successEmails) ?>)
                    </div>
                    <ul class="recipient-list">
                        <?php foreach ($successEmails as $email): ?>
                        <li class="recipient-item">
                            <div class="recipient-info">
                                <div class="recipient-icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <span class="recipient-email"><?= htmlspecialchars($email['email']) ?></span>
                            </div>
                            <span class="recipient-badge"><?= htmlspecialchars($email['type']) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Attachments Card -->
                <?php if (!empty($attachments)): ?>
                <div class="card">
                    <div class="card-title">
                        <i class="fas fa-paperclip"></i>
                        Attachments (<?= count($attachments) ?>)
                    </div>
                    <div class="attachments-grid">
                        <?php foreach ($attachments as $att): ?>
                        <div class="attachment-card">
                            <div class="attachment-icon">
                                <i class="fas fa-file"></i>
                            </div>
                            <div class="attachment-info">
                                <div class="attachment-name" title="<?= htmlspecialchars($att['name']) ?>">
                                    <?= htmlspecialchars($att['name']) ?>
                                </div>
                                <div class="attachment-size"><?= $att['size'] ?></div>
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
                    <div class="warning-content">
                        <strong>Note:</strong>
                        <p>Email was sent successfully but could not be saved to the database. This won't affect delivery.</p>
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