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
        // --- SMTP Configuration with Debugging ---
        $mail->SMTPDebug = 2; // Enable verbose debug output
        $mail->Debugoutput = 'html'; // Output format
        
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

        // --- Handle File Attachment ---
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == UPLOAD_ERR_OK) {
            $mail->addAttachment(
                $_FILES['attachment']['tmp_name'], 
                $_FILES['attachment']['name']
            );
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
        
        // Apply signature if set
        if (!empty($settings['signature'])) {
            $messageBody .= "\n\n" . $settings['signature'];
        }
        
        $templatePath = 'templates/template1.html';

        if (file_exists($templatePath)) {
            $htmlStructure = file_get_contents($templatePath);
            $formattedText = nl2br(htmlspecialchars($messageBody));
            $finalHtml = str_replace('{{MESSAGE}}', $formattedText, $htmlStructure);
            $finalHtml = str_replace('{{SUBJECT}}', htmlspecialchars($subject), $finalHtml);
            $finalHtml = str_replace('{{articletitle}}', htmlspecialchars($articleTitle), $finalHtml);
            $finalHtml = str_replace('{{SENDER_NAME}}', htmlspecialchars($displayName), $finalHtml);
            $finalHtml = str_replace('{{SENDER_EMAIL}}', htmlspecialchars($_SESSION['smtp_user']), $finalHtml);
            $finalHtml = str_replace('{{RECIPIENT_EMAIL}}', htmlspecialchars($recipient), $finalHtml);
            $finalHtml = str_replace('{{CURRENT_DATE}}', date('F j, Y'), $finalHtml);
            $finalHtml = str_replace('{{CURRENT_YEAR}}', date('Y'), $finalHtml);
            $finalHtml = str_replace('{{ATTACHMENT}}', '', $finalHtml);
            $mail->Body = $finalHtml;
            $mail->AltBody = strip_tags($messageBody);
        } else {
            $mail->Body = nl2br(htmlspecialchars($messageBody));
        }
        
        // CRITICAL: Actually send the email
        if (!$mail->send()) {
            throw new Exception("Email sending failed: " . $mail->ErrorInfo);
        }
        
        // Generate response HTML
        showResultPage($subject, $successEmails, $failedEmails);

    } catch (Exception $e) {
        // Log the error for debugging
        error_log("Email Error: " . $e->getMessage());
        error_log("Mail ErrorInfo: " . $mail->ErrorInfo);
        
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
 * Show result page with sidebar navigation - Nature style
 */
function showResultPage($subject, $successEmails, $failedEmails) {
    $totalEmails = count($successEmails) + count($failedEmails);
    $successCount = count($successEmails);
    $failureCount = count($failedEmails);
    $timestamp = date('d F Y, H:i');
    
    // Get user initial for avatar
    $userEmail = $_SESSION['smtp_user'];
    $userInitial = strtoupper(substr($userEmail, 0, 1));
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Transmission Report</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Harding:wght@400;600;700&family=Inter:wght@400;500;600&display=swap">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            
            body { 
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
                background-color: #ffffff;
                color: #222;
                display: flex;
                height: 100vh;
                overflow: hidden;
                font-size: 14px;
                line-height: 1.6;
            }

            .main-content {
                flex: 1;
                display: flex;
                overflow: hidden;
                background-color: #fafafa;
            }

            .content-area {
                flex: 1;
                padding: 40px 60px;
                overflow-y: auto;
            }

            .success-header {
                background: #fff;
                border: 1px solid #e0e0e0;
                border-radius: 4px;
                padding: 32px;
                margin-bottom: 24px;
            }

            .success-type {
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                color: #2d5a27;
                font-weight: 600;
                margin-bottom: 12px;
            }

            h1 {
                font-family: 'Harding', Georgia, serif;
                font-size: 28px;
                font-weight: 700;
                line-height: 1.3;
                color: #222;
                margin-bottom: 24px;
            }

            .success-box {
                background: #fff;
                border: 1px solid #e0e0e0;
                border-left: 3px solid #2d5a27;
                border-radius: 4px;
                padding: 24px;
                margin-bottom: 24px;
            }

            .actions {
                background: #fff;
                border: 1px solid #e0e0e0;
                border-radius: 4px;
                padding: 24px;
            }

            .btn {
                padding: 10px 20px;
                border: 1px solid #222;
                background: #222;
                color: #fff;
                text-decoration: none;
                font-size: 13px;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s;
                border-radius: 3px;
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }

            .btn:hover { background: #000; }
        </style>
    </head>
    <body>
    <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <div class="content-area">
                <div class="success-header">
                    <div class="success-type">✓ Success Report</div>
                    <h1>Email Sent Successfully</h1>
                    <p>Subject: <?= htmlspecialchars($subject) ?></p>
                    <p>Sent at: <?= $timestamp ?></p>
                    <p>Recipients: <?= $successCount ?></p>
                </div>

                <?php if ($successCount > 0): ?>
                <div class="success-box">
                    <h3>Successfully sent to:</h3>
                    <ul>
                        <?php foreach ($successEmails as $email): ?>
                            <li><?= htmlspecialchars($email['email']) ?> (<?= htmlspecialchars($email['type']) ?>)</li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if ($failureCount > 0): ?>
                <div class="success-box" style="border-left-color: #c5221f;">
                    <h3>Failed to send to:</h3>
                    <ul>
                        <?php foreach ($failedEmails as $email): ?>
                            <li><?= htmlspecialchars($email['email']) ?> - <?= htmlspecialchars($email['reason']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <div class="actions">
                    <a href="index.php" class="btn">
                        <i class="fa-solid fa-arrow-left"></i>
                        Compose New Email
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}

/**
 * Show error page - THIS WAS MISSING!
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
        <title>Transmission Error</title>
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

            .error-header {
                background: #fff;
                border: 1px solid #e0e0e0;
                border-radius: 4px;
                padding: 32px;
                margin-bottom: 24px;
            }

            .error-type {
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                color: #c5221f;
                font-weight: 600;
                margin-bottom: 12px;
            }

            h1 {
                font-size: 28px;
                font-weight: 700;
                color: #222;
                margin-bottom: 24px;
            }

            .error-box {
                background: #fff;
                border: 1px solid #e0e0e0;
                border-left: 3px solid #c5221f;
                border-radius: 4px;
                padding: 24px;
                margin-bottom: 24px;
            }

            .error-message {
                color: #222;
                line-height: 1.6;
                font-size: 14px;
                white-space: pre-wrap;
            }

            .actions {
                background: #fff;
                border: 1px solid #e0e0e0;
                border-radius: 4px;
                padding: 24px;
            }

            .btn {
                padding: 10px 20px;
                border: 1px solid #222;
                background: #222;
                color: #fff;
                text-decoration: none;
                font-size: 13px;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s;
                border-radius: 3px;
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }

            .btn:hover { background: #000; }
        </style>
    </head>
    <body>
    <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <div class="content-area">
                <div class="error-header">
                    <div class="error-type">✗ Error Report</div>
                    <h1>Email Transmission Failed</h1>
                </div>

                <div class="error-box">
                    <h3>Error Details:</h3>
                    <div class="error-message"><?= htmlspecialchars($errorMessage) ?></div>
                </div>

                <div class="actions">
                    <a href="index.php" class="btn">
                        <i class="fa-solid fa-arrow-left"></i>
                        Return to Compose
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}
?>