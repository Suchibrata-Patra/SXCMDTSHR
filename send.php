<?php
// /Applications/XAMPP/xamppfiles/htdocs/send.php
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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $mail = new PHPMailer(true);
    
    $successEmails = [];
    $failedEmails = [];

    try {
        // --- SMTP Configuration ---
        $mail->isSMTP();
        $mail->SMTPDebug = 0; // Disable debug output for production
        $mail->Host = "smtp.hostinger.com";
        $mail->SMTPAuth = true;
        $mail->Username = $_SESSION['smtp_user'];
        $mail->Password = $_SESSION['smtp_pass'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        // --- Recipients ---
        $settings = $_SESSION['user_settings'] ?? [];
        $displayName = !empty($settings['display_name']) ? $settings['display_name'] : "Holiday Seva";
        
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
        $attachmentDetails = [];
        if (isset($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
            $fileCount = count($_FILES['attachments']['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['attachments']['error'][$i] == UPLOAD_ERR_OK) {
                    $mail->addAttachment(
                        $_FILES['attachments']['tmp_name'][$i],
                        $_FILES['attachments']['name'][$i]
                    );
                    $attachmentNames[] = $_FILES['attachments']['name'][$i];
                    $attachmentDetails[] = [
                        'name' => $_FILES['attachments']['name'][$i],
                        'size' => formatFileSize($_FILES['attachments']['size'][$i])
                    ];
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
        
        // --- DATABASE LOGGING: Save sent email to database ---
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
        
        // Prepare summary data
        $summary = [
            'subject' => $subject,
            'article_title' => $articleTitle,
            'sent_at' => date('M d, Y h:i A'),
            'sender_name' => $displayName,
            'cc_count' => count($ccEmailsList),
            'bcc_count' => count($bccEmailsList),
            'attachment_count' => count($attachmentDetails)
        ];
        
        // Generate response HTML
        showResultPage($subject, $successEmails, $failedEmails, $dbSaved, $attachmentDetails, $summary);

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
 * Format file size
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
 * Show result page with Nature.com-inspired design
 */
function showResultPage($subject, $successEmails, $failedEmails, $dbSaved = true, $attachments = [], $summary = []) {
    $totalEmails = count($successEmails) + count($failedEmails);
    $successCount = count($successEmails);
    $failureCount = count($failedEmails);
    $timestamp = $summary['sent_at'] ?? date('d F Y, H:i');
    
    $userEmail = $_SESSION['smtp_user'];
    $userInitial = strtoupper(substr($userEmail, 0, 1));
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