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
        $mail->Host       = env("SMTP_HOST", "smtp.gmail.com"); 
        $mail->SMTPAuth   = true;
        $mail->Username   = $_SESSION['smtp_user']; 
        $mail->Password   = $_SESSION['smtp_pass']; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
        $mail->Port       = env("SMTP_PORT", 587);

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

        // --- Extract Company and Drive Information ---
        $companyName = $_POST['company_name'] ?? 'Esteemed Organization';
        $contactPerson = $_POST['contact_person'] ?? 'Sir/Madam';
        $designation = $_POST['designation'] ?? '';
        $driveDate = $_POST['drive_date'] ?? '';
        $program = $_POST['program'] ?? 'our programs';
        $jobPosition = $_POST['job_position'] ?? '';

        // Format drive date
        $formattedDriveDate = '';
        if (!empty($driveDate)) {
            $dateObj = DateTime::createFromFormat('Y-m-d', $driveDate);
            if ($dateObj) {
                $formattedDriveDate = $dateObj->format('F j, Y');
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
        
        // Apply signature if set
        if (!empty($settings['signature'])) {
            $messageBody .= "\n\n" . $settings['signature'];
        }
        
        $templatePath = 'templates/template1.html';

        if (file_exists($templatePath)) {
            $htmlStructure = file_get_contents($templatePath);
            
            // Format message with line breaks
            $formattedText = nl2br(htmlspecialchars($messageBody));
            
            // Replace all placeholders
            $finalHtml = str_replace('{{MESSAGE}}', $formattedText, $htmlStructure);
            $finalHtml = str_replace('{{SUBJECT}}', htmlspecialchars($subject), $finalHtml);
            $finalHtml = str_replace('{{SENDER_NAME}}', htmlspecialchars($displayName), $finalHtml);
            $finalHtml = str_replace('{{SENDER_EMAIL}}', htmlspecialchars($_SESSION['smtp_user']), $finalHtml);
            $finalHtml = str_replace('{{RECIPIENT_EMAIL}}', htmlspecialchars($recipient), $finalHtml);
            $finalHtml = str_replace('{{CURRENT_DATE}}', date('F j, Y'), $finalHtml);
            $finalHtml = str_replace('{{CURRENT_YEAR}}', date('Y'), $finalHtml);
            
            // Company and Drive specific placeholders
            $finalHtml = str_replace('{{COMPANY_NAME}}', htmlspecialchars($companyName), $finalHtml);
            $finalHtml = str_replace('{{CONTACT_PERSON}}', htmlspecialchars($contactPerson), $finalHtml);
            $finalHtml = str_replace('{{DESIGNATION}}', htmlspecialchars($designation), $finalHtml);
            $finalHtml = str_replace('{{DRIVE_DATE}}', htmlspecialchars($formattedDriveDate), $finalHtml);
            $finalHtml = str_replace('{{PROGRAM}}', htmlspecialchars($program), $finalHtml);
            $finalHtml = str_replace('{{JOB_POSITION}}', htmlspecialchars($jobPosition), $finalHtml);
            
            // Greeting customization
            $greeting = "Dear";
            if (!empty($contactPerson)) {
                $greeting .= " " . htmlspecialchars($contactPerson);
            } else {
                $greeting .= " Esteemed Corporate Partner";
            }
            $finalHtml = str_replace('Dear Esteemed Corporate Partner', $greeting, $finalHtml);
            
            $mail->Body = $finalHtml;
            $mail->AltBody = strip_tags($messageBody);
        } else {
            $mail->Body = nl2br(htmlspecialchars($messageBody));
        }

        $mail->send();
        
        // Generate response HTML
        showResultPage($subject, $successEmails, $failedEmails, $companyName);

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
 * Show success result page
 */
function showResultPage($subject, $successEmails, $failedEmails, $companyName = '') {
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
        <title>Email Sent Successfully</title>
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
                max-width: 800px;
                margin: 0 auto;
                overflow: hidden;
            }

            .success-header {
                background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
                color: white;
                padding: 40px;
                text-align: center;
            }

            .success-icon {
                width: 80px;
                height: 80px;
                background: rgba(255,255,255,0.2);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 20px;
            }

            .success-icon i {
                font-size: 40px;
            }

            .success-header h1 {
                font-size: 28px;
                font-weight: 600;
                margin-bottom: 8px;
            }

            .success-header p {
                font-size: 16px;
                opacity: 0.9;
            }

            .success-body {
                padding: 40px;
            }

            .info-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 24px;
                margin-bottom: 32px;
            }

            .info-item {
                background: #f5f5f5;
                padding: 20px;
                border-radius: 8px;
            }

            .info-label {
                font-size: 12px;
                color: #666;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-bottom: 8px;
                font-weight: 600;
            }

            .info-value {
                font-size: 16px;
                color: #1a1a1a;
                font-weight: 500;
            }

            .recipients-section {
                margin-top: 32px;
                padding-top: 32px;
                border-top: 1px solid #e5e5e5;
            }

            .recipients-header {
                font-size: 18px;
                font-weight: 600;
                margin-bottom: 16px;
                color: #1a1a1a;
            }

            .recipient-item {
                background: #f8f9fa;
                padding: 12px 16px;
                border-radius: 6px;
                margin-bottom: 8px;
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .recipient-icon {
                width: 32px;
                height: 32px;
                background: #4caf50;
                color: white;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 14px;
            }

            .recipient-email {
                flex: 1;
                font-size: 14px;
                color: #1a1a1a;
            }

            .recipient-type {
                background: #e8f5e9;
                color: #2e7d32;
                padding: 4px 12px;
                border-radius: 12px;
                font-size: 12px;
                font-weight: 600;
            }

            .actions {
                margin-top: 32px;
                display: flex;
                gap: 12px;
            }

            .btn {
                flex: 1;
                padding: 14px 24px;
                border: none;
                border-radius: 6px;
                font-size: 15px;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                text-decoration: none;
            }

            .btn-primary {
                background: #1a1a1a;
                color: white;
            }

            .btn-primary:hover {
                background: #000;
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            }

            .btn-secondary {
                background: white;
                color: #1a1a1a;
                border: 1px solid #d0d0d0;
            }

            .btn-secondary:hover {
                background: #f5f5f5;
                border-color: #1a1a1a;
            }

            @media (max-width: 768px) {
                .info-grid {
                    grid-template-columns: 1fr;
                }
                .actions {
                    flex-direction: column;
                }
            }
        </style>
    </head>
    <body>
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <div class="content-area">
                <div class="success-card">
                    <div class="success-header">
                        <div class="success-icon">
                            <i class="fa-solid fa-check"></i>
                        </div>
                        <h1>Email Sent Successfully!</h1>
                        <p>Your placement drive invitation has been delivered</p>
                    </div>

                    <div class="success-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Subject</div>
                                <div class="info-value"><?= htmlspecialchars($subject) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Sent At</div>
                                <div class="info-value"><?= $timestamp ?></div>
                            </div>
                            <?php if (!empty($companyName)): ?>
                            <div class="info-item">
                                <div class="info-label">Company</div>
                                <div class="info-value"><?= htmlspecialchars($companyName) ?></div>
                            </div>
                            <?php endif; ?>
                            <div class="info-item">
                                <div class="info-label">Total Recipients</div>
                                <div class="info-value"><?= $successCount ?> recipient(s)</div>
                            </div>
                        </div>

                        <div class="recipients-section">
                            <div class="recipients-header">Email Recipients</div>
                            <?php foreach ($successEmails as $email): ?>
                            <div class="recipient-item">
                                <div class="recipient-icon">
                                    <i class="fa-solid fa-envelope"></i>
                                </div>
                                <div class="recipient-email"><?= htmlspecialchars($email['email']) ?></div>
                                <div class="recipient-type"><?= htmlspecialchars($email['type']) ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($failureCount > 0): ?>
                        <div class="recipients-section">
                            <div class="recipients-header" style="color: #d32f2f;">Failed Recipients</div>
                            <?php foreach ($failedEmails as $email): ?>
                            <div class="recipient-item" style="background: #ffebee;">
                                <div class="recipient-icon" style="background: #d32f2f;">
                                    <i class="fa-solid fa-xmark"></i>
                                </div>
                                <div class="recipient-email"><?= htmlspecialchars($email['email']) ?></div>
                                <div class="recipient-type" style="background: #ffcdd2; color: #c62828;">Failed</div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <div class="actions">
                            <a href="index.php" class="btn btn-primary">
                                <i class="fa-solid fa-plus"></i>
                                Send Another Email
                            </a>
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="fa-solid fa-home"></i>
                                Back to Dashboard
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

            .error-card {
                background: white;
                border-radius: 10px;
                border: 1px solid #e5e5e5;
                max-width: 700px;
                margin: 0 auto;
                overflow: hidden;
            }

            .error-header {
                background: linear-gradient(135deg, #d32f2f 0%, #c62828 100%);
                color: white;
                padding: 40px;
                text-align: center;
            }

            .error-icon {
                width: 80px;
                height: 80px;
                background: rgba(255,255,255,0.2);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 20px;
            }

            .error-icon i {
                font-size: 40px;
            }

            .error-header h1 {
                font-size: 28px;
                font-weight: 600;
                margin-bottom: 8px;
            }

            .error-body {
                padding: 40px;
            }

            .error-message {
                background: #ffebee;
                border-left: 4px solid #d32f2f;
                padding: 20px;
                border-radius: 4px;
                margin-bottom: 24px;
                font-size: 14px;
                line-height: 1.6;
                color: #333;
            }

            .actions {
                display: flex;
                gap: 12px;
            }

            .btn {
                flex: 1;
                padding: 14px 24px;
                border: none;
                border-radius: 6px;
                font-size: 15px;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s;
                text-decoration: none;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
            }

            .btn-primary {
                background: #1a1a1a;
                color: white;
            }

            .btn-primary:hover {
                background: #000;
            }
        </style>
    </head>
    <body>
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <div class="content-area">
                <div class="error-card">
                    <div class="error-header">
                        <div class="error-icon">
                            <i class="fa-solid fa-exclamation-triangle"></i>
                        </div>
                        <h1>Email Transmission Failed</h1>
                    </div>

                    <div class="error-body">
                        <div class="error-message">
                            <?= htmlspecialchars($errorMessage) ?>
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
        </div>
    </body>
    </html>
    <?php
}
?>