<?php
// htdocs/send.php
session_start();

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $recipientEmail = $_POST['email'] ?? '';
    $cc = $_POST['cc'] ?? '';
    $bcc = $_POST['bcc'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $articletitle = $_POST['articletitle'] ?? '';
    $message = $_POST['message'] ?? '';
    $messageIsHTML = isset($_POST['message_is_html']) && $_POST['message_is_html'] === 'true';
    
    // Get SMTP credentials from session
    $smtpUser = $_SESSION['smtp_user'] ?? '';
    $smtpPass = $_SESSION['smtp_pass'] ?? '';
    $userSettings = $_SESSION['user_settings'] ?? [];
    
    // Get settings with defaults
    $smtpHost = $userSettings['smtp_host'] ?? 'smtp.gmail.com';
    $smtpPort = $userSettings['smtp_port'] ?? 587;
    $displayName = $userSettings['display_name'] ?? '';
    
    if (empty($smtpUser) || empty($smtpPass)) {
        die("Error: SMTP credentials not found. Please log in again.");
    }
    
    // Process template
    $templatePath = 'templates/template1.html';
    $emailBody = '';
    
    if (file_exists($templatePath)) {
        $templateContent = file_get_contents($templatePath);
        
        // Replace placeholders
        // The message is already HTML, so we just insert it directly
        $emailBody = str_replace('{{MESSAGE}}', $message, $templateContent);
        $emailBody = str_replace('{{articletitle}}', htmlspecialchars($articletitle), $emailBody);
        $emailBody = str_replace('{{YEAR}}', date('Y'), $emailBody);
    } else {
        die("Error: Email template not found at $templatePath");
    }
    
    // Initialize PHPMailer
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $smtpPort;
        
        // Sender
        $fromName = !empty($displayName) ? $displayName : $smtpUser;
        $mail->setFrom($smtpUser, $fromName);
        
        // Recipients
        $recipients = array_map('trim', explode(',', $recipientEmail));
        foreach ($recipients as $recipient) {
            if (!empty($recipient)) {
                $mail->addAddress($recipient);
            }
        }
        
        // CC
        if (!empty($cc)) {
            $ccRecipients = array_map('trim', explode(',', $cc));
            foreach ($ccRecipients as $ccRecipient) {
                if (!empty($ccRecipient)) {
                    $mail->addCC($ccRecipient);
                }
            }
        }
        
        // BCC
        if (!empty($bcc)) {
            $bccRecipients = array_map('trim', explode(',', $bcc));
            foreach ($bccRecipients as $bccRecipient) {
                if (!empty($bccRecipient)) {
                    $mail->addBCC($bccRecipient);
                }
            }
        }
        
        // Attachments
        if (isset($_FILES['attachments'])) {
            $fileCount = count($_FILES['attachments']['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                    $fileTmpPath = $_FILES['attachments']['tmp_name'][$i];
                    $fileName = $_FILES['attachments']['name'][$i];
                    $mail->addAttachment($fileTmpPath, $fileName);
                }
            }
        }
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $emailBody;
        $mail->AltBody = strip_tags($message);
        
        // Send email
        $mail->send();
        
        // Success - Display detailed confirmation
        $attachmentsList = [];
        if (isset($_FILES['attachments'])) {
            $fileCount = count($_FILES['attachments']['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                    $attachmentsList[] = [
                        'name' => $_FILES['attachments']['name'][$i],
                        'size' => $_FILES['attachments']['size'][$i]
                    ];
                }
            }
        }
        
        displaySuccessPage($recipientEmail, $cc, $bcc, $subject, $articletitle, $message, $attachmentsList, $emailBody);
        
    } catch (Exception $e) {
        displayErrorPage($e->getMessage());
    }
    
} else {
    header("Location: index.php");
    exit();
}

function displaySuccessPage($to, $cc, $bcc, $subject, $articletitle, $message, $attachments, $fullEmailHTML) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Email Sent Successfully - SXC MDTS</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                padding: 40px 20px;
            }
            
            .container {
                max-width: 1000px;
                margin: 0 auto;
            }
            
            .success-card {
                background: white;
                border-radius: 16px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                overflow: hidden;
                margin-bottom: 30px;
            }
            
            .success-header {
                background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
                padding: 40px;
                text-align: center;
                color: white;
            }
            
            .success-icon {
                font-size: 64px;
                margin-bottom: 20px;
                animation: scaleIn 0.5s ease-out;
            }
            
            @keyframes scaleIn {
                from { transform: scale(0); }
                to { transform: scale(1); }
            }
            
            .success-header h1 {
                font-size: 32px;
                font-weight: 600;
                margin-bottom: 8px;
            }
            
            .success-header p {
                font-size: 16px;
                opacity: 0.9;
            }
            
            .email-details {
                padding: 40px;
            }
            
            .detail-section {
                margin-bottom: 32px;
            }
            
            .detail-section:last-child {
                margin-bottom: 0;
            }
            
            .detail-label {
                font-size: 13px;
                font-weight: 600;
                color: #666;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-bottom: 8px;
            }
            
            .detail-value {
                font-size: 15px;
                color: #1a1a1a;
                background: #f8f9fa;
                padding: 12px 16px;
                border-radius: 8px;
                border-left: 4px solid #11998e;
            }
            
            .detail-value.multi-line {
                white-space: pre-wrap;
                line-height: 1.6;
            }
            
            .attachment-list {
                display: flex;
                flex-wrap: wrap;
                gap: 12px;
                margin-top: 8px;
            }
            
            .attachment-item {
                background: #f8f9fa;
                padding: 12px 16px;
                border-radius: 8px;
                display: flex;
                align-items: center;
                gap: 12px;
                border: 1px solid #e5e5e5;
            }
            
            .attachment-icon {
                font-size: 24px;
                color: #11998e;
            }
            
            .attachment-info {
                flex: 1;
            }
            
            .attachment-name {
                font-size: 14px;
                font-weight: 500;
                color: #1a1a1a;
            }
            
            .attachment-size {
                font-size: 12px;
                color: #666;
            }
            
            .preview-section {
                margin-top: 40px;
                padding-top: 40px;
                border-top: 2px solid #e5e5e5;
            }
            
            .preview-label {
                font-size: 18px;
                font-weight: 600;
                color: #1a1a1a;
                margin-bottom: 16px;
            }
            
            .preview-container {
                border: 2px solid #e5e5e5;
                border-radius: 8px;
                overflow: hidden;
                max-height: 600px;
                overflow-y: auto;
            }
            
            .action-buttons {
                display: flex;
                gap: 12px;
                justify-content: center;
            }
            
            .btn {
                padding: 14px 32px;
                border-radius: 8px;
                border: none;
                font-size: 16px;
                font-weight: 500;
                cursor: pointer;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 10px;
                transition: all 0.3s;
            }
            
            .btn-primary {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            }
            
            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
            }
            
            .btn-secondary {
                background: white;
                color: #667eea;
                border: 2px solid #667eea;
            }
            
            .btn-secondary:hover {
                background: #f8f9ff;
            }
            
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                margin-top: 24px;
            }
            
            .stat-card {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 8px;
                text-align: center;
            }
            
            .stat-icon {
                font-size: 32px;
                color: #11998e;
                margin-bottom: 12px;
            }
            
            .stat-value {
                font-size: 24px;
                font-weight: 600;
                color: #1a1a1a;
                margin-bottom: 4px;
            }
            
            .stat-label {
                font-size: 13px;
                color: #666;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="success-card">
                <div class="success-header">
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h1>Email Sent Successfully!</h1>
                    <p>Your email has been delivered to all recipients</p>
                </div>
                
                <div class="email-details">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="stat-value"><?php echo count(array_filter(array_map('trim', explode(',', $to)))); ?></div>
                            <div class="stat-label">Recipients</div>
                        </div>
                        
                        <?php if (!empty($attachments)): ?>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-paperclip"></i>
                            </div>
                            <div class="stat-value"><?php echo count($attachments); ?></div>
                            <div class="stat-label">Attachments</div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-value"><?php echo date('H:i'); ?></div>
                            <div class="stat-label">Sent At</div>
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <div class="detail-label">To</div>
                        <div class="detail-value"><?php echo htmlspecialchars($to); ?></div>
                    </div>
                    
                    <?php if (!empty($cc)): ?>
                    <div class="detail-section">
                        <div class="detail-label">CC</div>
                        <div class="detail-value"><?php echo htmlspecialchars($cc); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($bcc)): ?>
                    <div class="detail-section">
                        <div class="detail-label">BCC</div>
                        <div class="detail-value"><?php echo htmlspecialchars($bcc); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="detail-section">
                        <div class="detail-label">Subject</div>
                        <div class="detail-value"><?php echo htmlspecialchars($subject); ?></div>
                    </div>
                    
                    <div class="detail-section">
                        <div class="detail-label">Article Title</div>
                        <div class="detail-value"><?php echo htmlspecialchars($articletitle); ?></div>
                    </div>
                    
                    <?php if (!empty($attachments)): ?>
                    <div class="detail-section">
                        <div class="detail-label">Attachments</div>
                        <div class="attachment-list">
                            <?php foreach ($attachments as $attachment): ?>
                            <div class="attachment-item">
                                <div class="attachment-icon">
                                    <i class="fas fa-file"></i>
                                </div>
                                <div class="attachment-info">
                                    <div class="attachment-name"><?php echo htmlspecialchars($attachment['name']); ?></div>
                                    <div class="attachment-size"><?php echo formatFileSize($attachment['size']); ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="preview-section">
                        <div class="preview-label">
                            <i class="fas fa-eye"></i> Email Preview
                        </div>
                        <div class="preview-container">
                            <?php echo $fullEmailHTML; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="action-buttons">
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Compose New Email
                </a>
                <a href="javascript:window.print()" class="btn btn-secondary">
                    <i class="fas fa-print"></i>
                    Print Details
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
}

function displayErrorPage($errorMessage) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error - SXC MDTS</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            
            .error-card {
                background: white;
                border-radius: 16px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                max-width: 600px;
                overflow: hidden;
            }
            
            .error-header {
                background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                padding: 40px;
                text-align: center;
                color: white;
            }
            
            .error-icon {
                font-size: 64px;
                margin-bottom: 20px;
            }
            
            .error-header h1 {
                font-size: 28px;
                margin-bottom: 8px;
            }
            
            .error-body {
                padding: 40px;
            }
            
            .error-message {
                background: #fff3cd;
                border-left: 4px solid #f5576c;
                padding: 16px;
                border-radius: 8px;
                margin-bottom: 24px;
                color: #856404;
            }
            
            .btn {
                display: inline-block;
                padding: 14px 32px;
                background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                color: white;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 500;
                transition: transform 0.3s;
            }
            
            .btn:hover {
                transform: translateY(-2px);
            }
        </style>
    </head>
    <body>
        <div class="error-card">
            <div class="error-header">
                <div class="error-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h1>Email Sending Failed</h1>
                <p>We encountered an error while sending your email</p>
            </div>
            <div class="error-body">
                <div class="error-message">
                    <strong>Error Details:</strong><br>
                    <?php echo htmlspecialchars($errorMessage); ?>
                </div>
                <a href="index.php" class="btn">
                    <i class="fas fa-arrow-left"></i>
                    Back to Composer
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
}

function formatFileSize($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
?>