<?php
/**
 * Standalone Email Test Script
 * 
 * This script sends a test email to verify your SMTP configuration
 * Upload this file to your server and access it via browser
 * 
 * Usage: test_email_send.php?email=your-email@example.com
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    die(json_encode([
        'success' => false,
        'error' => 'Not logged in. Please log in to your email system first.'
    ]));
}

require_once 'db_config.php';

// Load PHPMailer
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    require_once __DIR__ . '/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

// Get test email from URL parameter
$testEmail = $_GET['email'] ?? '';

if (empty($testEmail)) {
    echo json_encode([
        'success' => false,
        'error' => 'Please provide an email address. Usage: ?email=your-email@example.com'
    ]);
    exit;
}

if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid email address provided: ' . htmlspecialchars($testEmail)
    ]);
    exit;
}

// Get SMTP credentials from session
$smtpUser = $_SESSION['smtp_user'];
$smtpPass = $_SESSION['smtp_pass'];
$settings = $_SESSION['user_settings'] ?? [];
$displayName = !empty($settings['display_name']) ? $settings['display_name'] : "St. Xavier's College";

// Create debug log array
$debugLog = [];

try {
    $mail = new PHPMailer(true);
    
    // SMTP Configuration
    $mail->isSMTP();
    $mail->Host = 'smtp.hostinger.com';
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUser;
    $mail->Password = $smtpPass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;
    
    // SSL Options (for Hostinger shared hosting)
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ],
    ];
    
    // Enable debug output
    $mail->SMTPDebug = 4;
    $mail->Debugoutput = function($str) use (&$debugLog) {
        $debugLog[] = rtrim($str);
    };
    
    // Content settings
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->XMailer = ' '; // Suppress X-Mailer header
    
    // Set sender
    $mail->setFrom($smtpUser, $displayName);
    
    // Set recipient
    $mail->addAddress($testEmail);
    
    // Email content
    $mail->isHTML(true);
    $mail->Subject = '🧪 Test Email - ' . date('Y-m-d H:i:s');
    
    $mail->Body = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #0071e3; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
            .content { background: #f5f5f7; padding: 30px; border-radius: 0 0 8px 8px; }
            .success { background: #e6f4ea; border-left: 4px solid #1db954; padding: 15px; margin: 20px 0; }
            .info { background: #fff; border: 1px solid #d2d2d7; padding: 15px; margin: 20px 0; border-radius: 6px; }
            .footer { text-align: center; color: #6e6e73; font-size: 12px; margin-top: 30px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1 style="margin: 0;">🧪 Email System Test</h1>
            </div>
            <div class="content">
                <div class="success">
                    <h2 style="margin-top: 0;">✅ Success!</h2>
                    <p>If you\'re reading this email, your bulk mail system is working correctly!</p>
                </div>
                
                <div class="info">
                    <h3>Test Details:</h3>
                    <ul>
                        <li><strong>Sent At:</strong> ' . date('Y-m-d H:i:s T') . '</li>
                        <li><strong>From:</strong> ' . htmlspecialchars($smtpUser) . '</li>
                        <li><strong>To:</strong> ' . htmlspecialchars($testEmail) . '</li>
                        <li><strong>SMTP Server:</strong> smtp.hostinger.com:465</li>
                        <li><strong>Encryption:</strong> SSL/TLS</li>
                    </ul>
                </div>
                
                <h3>What This Means:</h3>
                <p>✅ Your SMTP credentials are correct<br>
                   ✅ Your server can connect to the SMTP server<br>
                   ✅ Emails are being sent successfully<br>
                   ✅ Your bulk mail system is operational</p>
                
                <p><strong>Note:</strong> If this email landed in your spam folder, you may need to:</p>
                <ul>
                    <li>Set up SPF, DKIM, and DMARC records for your domain</li>
                    <li>Warm up your email sending domain gradually</li>
                    <li>Mark emails from ' . htmlspecialchars($smtpUser) . ' as "Not Spam"</li>
                </ul>
                
                <div class="footer">
                    <p>This is an automated test email from your bulk mail system.<br>
                    Sent via Hostinger SMTP</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ';
    
    $mail->AltBody = "
    🧪 Email System Test
    
    SUCCESS! If you're reading this email, your bulk mail system is working correctly!
    
    Test Details:
    - Sent At: " . date('Y-m-d H:i:s T') . "
    - From: $smtpUser
    - To: $testEmail
    - SMTP Server: smtp.hostinger.com:465
    - Encryption: SSL/TLS
    
    What This Means:
    ✅ Your SMTP credentials are correct
    ✅ Your server can connect to the SMTP server
    ✅ Emails are being sent successfully
    ✅ Your bulk mail system is operational
    
    Note: If this email landed in your spam folder, you may need to set up 
    SPF, DKIM, and DMARC records for your domain.
    ";
    
    // Send the email
    $sendResult = $mail->send();
    
    // Log to database if connection available
    $loggedToDatabase = false;
    try {
        $pdo = getDatabaseConnection();
        if ($pdo) {
            $userId = getUserId($pdo, $smtpUser);
            if ($userId) {
                $emailUuid = generateUuidV4();
                
                $stmt = $pdo->prepare("
                    INSERT INTO sent_emails_new (
                        email_uuid, sender_email, sender_name, recipient_email,
                        cc_list, bcc_list, subject, article_title,
                        body_text, body_html, has_attachments, email_type,
                        is_deleted, sent_at, created_at
                    ) VALUES (
                        ?, ?, ?, ?, '', '', ?, 'Test Email', ?, ?, 0, 'sent', 0, NOW(), NOW()
                    )
                ");
                
                $stmt->execute([
                    $emailUuid,
                    $smtpUser,
                    $displayName,
                    $testEmail,
                    $mail->Subject,
                    $mail->AltBody,
                    $mail->Body
                ]);
                
                $loggedToDatabase = true;
            }
        }
    } catch (Exception $e) {
        // Logging failed, but email was sent - not critical
        $debugLog[] = "Database logging failed (non-critical): " . $e->getMessage();
    }
    
    // Success response
    echo json_encode([
        'success' => true,
        'sent' => true,
        'recipient' => $testEmail,
        'sender' => $smtpUser,
        'subject' => $mail->Subject,
        'logged_to_database' => $loggedToDatabase,
        'message' => '✅ Test email sent successfully! Check your inbox (and spam folder).',
        'smtp_debug' => $debugLog,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // Error response
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'smtp_error' => $mail->ErrorInfo ?? 'N/A',
        'recipient' => $testEmail,
        'sender' => $smtpUser,
        'smtp_debug' => $debugLog,
        'message' => '❌ Failed to send test email. Check the error details above.',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}
?>