<?php
// send.php - Improved version with proper attachment handling
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

// Encryption configuration (must match upload_handler.php)
define('ENCRYPTION_KEY', 'your-32-char-secret-key-here!!');
define('ENCRYPTION_METHOD', 'AES-256-CBC');
define('UPLOAD_DIR', 'uploads/attachments/');

/**
 * Decrypt file ID
 */
function decryptFileId($encryptedId) {
    $data = base64_decode($encryptedId);
    $iv_length = openssl_cipher_iv_length(ENCRYPTION_METHOD);
    $iv = substr($data, 0, $iv_length);
    $encrypted = substr($data, $iv_length);
    
    return openssl_decrypt($encrypted, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $mail = new PHPMailer(true);
    
    $successEmails = [];
    $failedEmails = [];
    $attachedFiles = [];

    try {
        // Get database connection
        $pdo = getDatabaseConnection();
        
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

        // --- Handle Attachments from Database ---
        $attachmentNames = [];
        $attachmentIds = [];
        
        if (!empty($_POST['attachment_ids'])) {
            $encryptedIds = explode(',', $_POST['attachment_ids']);
            
            foreach ($encryptedIds as $encryptedId) {
                $encryptedId = trim($encryptedId);
                if (empty($encryptedId)) continue;
                
                try {
                    // Decrypt the file ID
                    $fileId = decryptFileId($encryptedId);
                    
                    // Fetch file from database
                    $stmt = $pdo->prepare("SELECT * FROM attachments WHERE id = ? LIMIT 1");
                    $stmt->execute([$fileId]);
                    $fileData = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($fileData) {
                        $filePath = UPLOAD_DIR . $fileData['storage_path'];
                        
                        if (file_exists($filePath)) {
                            // Add to PHPMailer
                            $mail->addAttachment($filePath, $fileData['original_filename']);
                            $attachmentNames[] = $fileData['original_filename'];
                            $attachmentIds[] = $fileData['id'];
                            
                            error_log("Attached file: " . $fileData['original_filename']);
                        } else {
                            error_log("File not found: " . $filePath);
                        }
                    }
                } catch (Exception $e) {
                    error_log("Error processing attachment: " . $e->getMessage());
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
        
        // Get the label ID if provided
        $labelId = !empty($_POST['label_id']) ? (int)$_POST['label_id'] : null;
        
        // --- DATABASE LOGGING: Save sent email to database ---
        error_log("=== ATTEMPTING TO SAVE EMAIL TO DATABASE ===");
        error_log("Sender: " . $_SESSION['smtp_user']);
        error_log("Recipient: " . $recipient);
        error_log("Subject: " . $subject);
        error_log("Attachments: " . count($attachmentIds));
        
        $emailData = [
            'sender_email' => $_SESSION['smtp_user'],
            'recipient_email' => $recipient,
            'cc_list' => !empty($ccEmailsList) ? implode(', ', $ccEmailsList) : '',
            'bcc_list' => !empty($bccEmailsList) ? implode(', ', $bccEmailsList) : '',
            'subject' => $subject,
            'article_title' => $articleTitle,
            'message_body' => $finalHtml,
            'attachment_names' => !empty($attachmentNames) ? implode(', ', $attachmentNames) : '',
            'label_id' => $labelId
        ];
        
        // Attempt to save to database
        $dbSaved = saveSentEmail($emailData);
        
        if ($dbSaved) {
            $sentEmailId = $pdo->lastInsertId();
            error_log("Email saved with ID: " . $sentEmailId);
            
            // Link attachments to this email
            if (!empty($attachmentIds) && $sentEmailId > 0) {
                linkAttachmentsToEmail($pdo, $sentEmailId, $attachmentIds);
            }
            
            error_log("=== DATABASE SAVE SUCCESS ===");
        } else {
            error_log("=== DATABASE SAVE FAILED ===");
        }
        
        // Clear temp attachments after successful send
        $_SESSION['temp_attachments'] = [];
        
        // Generate response HTML
        showResultPage($subject, $successEmails, $failedEmails, $dbSaved, $attachmentNames);

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
 * Link attachments to sent email
 */
function linkAttachmentsToEmail($pdo, $emailId, $attachmentIds) {
    try {
        $order = 0;
        foreach ($attachmentIds as $attachmentId) {
            $stmt = $pdo->prepare("
                INSERT INTO email_attachments (email_id, attachment_id, attachment_order)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$emailId, $attachmentId, $order]);
            $order++;
        }
        error_log("Linked " . count($attachmentIds) . " attachments to email ID " . $emailId);
        return true;
    } catch (Exception $e) {
        error_log("Error linking attachments: " . $e->getMessage());
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
 * Show result page
 */
function showResultPage($subject, $successEmails, $failedEmails, $dbSaved = true, $attachments = []) {
    $totalEmails = count($successEmails) + count($failedEmails);
    $successCount = count($successEmails);
    $failureCount = count($failedEmails);
    $timestamp = date('d F Y, H:i');
    $attachmentCount = count($attachments);
    
    $userEmail = $_SESSION['smtp_user'];
    $userInitial = strtoupper(substr($userEmail, 0, 1));
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Email Sent Successfully - SXC MDTS</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            
            body {
                font-family: 'Inter', sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            
            .success-container {
                background: white;
                border-radius: 20px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                max-width: 600px;
                width: 100%;
                padding: 40px;
                animation: slideIn 0.5s ease-out;
            }
            
            @keyframes slideIn {
                from { opacity: 0; transform: translateY(-20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            .success-icon {
                width: 80px;
                height: 80px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 20px;
                color: white;
                font-size: 40px;
            }
            
            h1 {
                text-align: center;
                color: #333;
                margin-bottom: 10px;
                font-size: 28px;
            }
            
            .subtitle {
                text-align: center;
                color: #666;
                margin-bottom: 30px;
                font-size: 14px;
            }
            
            .info-grid {
                display: grid;
                gap: 15px;
                margin-bottom: 30px;
            }
            
            .info-item {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 10px;
                display: flex;
                align-items: flex-start;
                gap: 12px;
            }
            
            .info-item i {
                color: #667eea;
                font-size: 18px;
                margin-top: 2px;
            }
            
            .info-content {
                flex: 1;
            }
            
            .info-label {
                font-size: 12px;
                color: #999;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-bottom: 4px;
            }
            
            .info-value {
                color: #333;
                font-weight: 500;
                word-break: break-word;
            }
            
            .attachment-list {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }
            
            .attachment-item {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 8px;
                background: white;
                border-radius: 6px;
                font-size: 13px;
            }
            
            .attachment-item i {
                color: #667eea;
                font-size: 14px;
            }
            
            .btn-primary {
                width: 100%;
                padding: 15px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 10px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: transform 0.2s;
                text-decoration: none;
                display: block;
                text-align: center;
            }
            
            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
            }
            
            .stats {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 15px;
                margin-bottom: 30px;
            }
            
            .stat-card {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 10px;
                text-align: center;
            }
            
            .stat-number {
                font-size: 24px;
                font-weight: 700;
                color: #667eea;
                margin-bottom: 5px;
            }
            
            .stat-label {
                font-size: 12px;
                color: #666;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
        </style>
    </head>
    <body>
        <div class="success-container">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            
            <h1>Email Sent Successfully!</h1>
            <p class="subtitle"><?php echo $timestamp; ?></p>
            
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $successCount; ?></div>
                    <div class="stat-label">Recipients</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $attachmentCount; ?></div>
                    <div class="stat-label">Attachments</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $dbSaved ? '✓' : '✗'; ?></div>
                    <div class="stat-label">Saved</div>
                </div>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <i class="fas fa-envelope"></i>
                    <div class="info-content">
                        <div class="info-label">Subject</div>
                        <div class="info-value"><?php echo htmlspecialchars($subject); ?></div>
                    </div>
                </div>
                
                <div class="info-item">
                    <i class="fas fa-users"></i>
                    <div class="info-content">
                        <div class="info-label">Recipients</div>
                        <div class="info-value">
                            <?php foreach ($successEmails as $email): ?>
                                <div><?php echo htmlspecialchars($email['email']) . ' (' . $email['type'] . ')'; ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($attachments)): ?>
                <div class="info-item">
                    <i class="fas fa-paperclip"></i>
                    <div class="info-content">
                        <div class="info-label">Attachments (<?php echo count($attachments); ?>)</div>
                        <div class="attachment-list">
                            <?php foreach ($attachments as $file): ?>
                                <div class="attachment-item">
                                    <i class="fas fa-file"></i>
                                    <span><?php echo htmlspecialchars($file); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <a href="index.php" class="btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Compose
            </a>
        </div>
    </body>
    </html>
    <?php
}

/**
 * Show error page
 */
function showErrorPage($message) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error - SXC MDTS</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: 'Inter', sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .error-container {
                background: white;
                border-radius: 20px;
                padding: 40px;
                max-width: 500px;
                text-align: center;
            }
            .error-icon {
                font-size: 60px;
                color: #e74c3c;
                margin-bottom: 20px;
            }
            h1 { color: #333; margin-bottom: 15px; }
            p { color: #666; margin-bottom: 30px; line-height: 1.6; }
            a {
                display: inline-block;
                padding: 12px 30px;
                background: #667eea;
                color: white;
                text-decoration: none;
                border-radius: 8px;
                transition: transform 0.2s;
            }
            a:hover { transform: translateY(-2px); }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon">⚠️</div>
            <h1>Email Sending Failed</h1>
            <p><?php echo htmlspecialchars($message); ?></p>
            <a href="index.php">← Back to Compose</a>
        </div>
    </body>
    </html>
    <?php
}
?>