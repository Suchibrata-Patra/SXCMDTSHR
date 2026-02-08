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

// ============================================================================
// ATTACHMENT MANAGER CLASS - Handles file deduplication and storage
// ============================================================================
class AttachmentManager {
    private $pdo;
    private $uploadDir = 'uploads/attachments/';
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->ensureUploadDirectory();
    }
    
    /**
     * Process uploaded file with hash-based deduplication
     */
    public function processUpload($uploadedFile) {
        // Validate upload
        if (!isset($uploadedFile['tmp_name']) || !is_uploaded_file($uploadedFile['tmp_name'])) {
            throw new Exception("Invalid file upload");
        }
        
        // Calculate SHA256 hash for deduplication
        $fileHash = hash_file('sha256', $uploadedFile['tmp_name']);
        
        // Check if this exact file already exists
        $existing = $this->findByHash($fileHash);
        
        if ($existing) {
            // File already exists - just increment reference count
            $this->incrementReference($existing['id']);
            error_log("File already exists (hash: $fileHash), reusing attachment ID: {$existing['id']}");
            return [
                'id' => $existing['id'],
                'path' => $this->uploadDir . $existing['storage_path'],
                'original_name' => $uploadedFile['name'],
                'deduplicated' => true
            ];
        }
        
        // New file - generate UUID and save
        $fileUuid = $this->generateUuid();
        $extension = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
        
        // Organize by year/month for better file management
        $storagePath = date('Y') . '/' . date('m') . '/' . $fileUuid . '.' . $extension;
        $fullPath = $this->uploadDir . $storagePath;
        
        // Ensure directory exists
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Move uploaded file to permanent storage
        if (!move_uploaded_file($uploadedFile['tmp_name'], $fullPath)) {
            throw new Exception("Failed to save file: " . $uploadedFile['name']);
        }
        
        // Insert into attachments table
        $stmt = $this->pdo->prepare("
            INSERT INTO attachments (
                file_uuid, file_hash, original_filename, 
                file_extension, mime_type, file_size, 
                storage_path, storage_type, reference_count
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'local', 1)
        ");
        
        $stmt->execute([
            $fileUuid,
            $fileHash,
            $uploadedFile['name'],
            $extension,
            $uploadedFile['type'] ?? 'application/octet-stream',
            $uploadedFile['size'],
            $storagePath
        ]);
        
        $attachmentId = $this->pdo->lastInsertId();
        error_log("New file saved (hash: $fileHash), attachment ID: $attachmentId");
        
        return [
            'id' => $attachmentId,
            'path' => $fullPath,
            'original_name' => $uploadedFile['name'],
            'deduplicated' => false
        ];
    }
    
    /**
     * Link attachment to email
     */
    public function linkToEmail($attachmentId, $emailId, $order = 0, $isInline = false, $contentId = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO email_attachments (
                email_id, attachment_id, attachment_order, 
                is_inline, content_id
            ) VALUES (?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $emailId,
            $attachmentId,
            $order,
            $isInline ? 1 : 0,
            $contentId
        ]);
    }
    
    /**
     * Grant user access to attachment
     */
    public function grantUserAccess($userId, $emailId, $attachmentId, $accessType = 'owner') {
        $stmt = $this->pdo->prepare("
            INSERT INTO user_attachment_access (
                user_id, email_id, attachment_id, access_type
            ) VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE access_type = VALUES(access_type)
        ");
        
        return $stmt->execute([$userId, $emailId, $attachmentId, $accessType]);
    }
    
    /**
     * Find existing file by hash
     */
    private function findByHash($hash) {
        $stmt = $this->pdo->prepare("SELECT * FROM attachments WHERE file_hash = ? LIMIT 1");
        $stmt->execute([$hash]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Increment reference count
     */
    private function incrementReference($attachmentId) {
        $stmt = $this->pdo->prepare("
            UPDATE attachments 
            SET reference_count = reference_count + 1,
                last_accessed = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$attachmentId]);
    }
    
    /**
     * Generate UUID v4
     */
    private function generateUuid() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    private function ensureUploadDirectory() {
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }
}

// ============================================================================
// MAIN EMAIL SENDING LOGIC
// ============================================================================

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // DEBUG: Log all POST data to identify missing fields
    error_log("=== EMAIL SEND REQUEST ===");
    error_log("POST data: " . print_r($_POST, true));
    error_log("FILES data: " . print_r(array_keys($_FILES), true));
    
    // Validate critical fields BEFORE processing
    if (empty($_POST['subject'])) {
        error_log("ERROR: Subject field is empty!");
        showErrorPage("Subject field is required. Please provide an email subject.");
        exit();
    }
    
    if (empty($_POST['email'])) {
        error_log("ERROR: Recipient email is empty!");
        showErrorPage("Recipient email is required.");
        exit();
    }
    
    $mail = new PHPMailer(true);
    $successEmails = [];
    $failedEmails = [];
    $processedAttachments = [];

    try {
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
            throw new Exception("Invalid recipient email address");
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

        // --- Handle Multiple File Attachments with Deduplication ---
        $attachmentManager = new AttachmentManager($pdo);
        
        if (isset($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
            $fileCount = count($_FILES['attachments']['name']);
            error_log("Processing $fileCount attachment(s)...");
            
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['attachments']['error'][$i] == UPLOAD_ERR_OK) {
                    try {
                        // Process and store file with deduplication
                        $uploadedFile = [
                            'name' => $_FILES['attachments']['name'][$i],
                            'type' => $_FILES['attachments']['type'][$i],
                            'tmp_name' => $_FILES['attachments']['tmp_name'][$i],
                            'size' => $_FILES['attachments']['size'][$i]
                        ];
                        
                        $fileInfo = $attachmentManager->processUpload($uploadedFile);
                        
                        // Attach to email
                        $mail->addAttachment($fileInfo['path'], $fileInfo['original_name']);
                        
                        // Store for database logging
                        $processedAttachments[] = $fileInfo;
                        
                        error_log("Attachment added: {$fileInfo['original_name']} " . 
                                  ($fileInfo['deduplicated'] ? '(deduplicated)' : '(new file)'));
                        
                    } catch (Exception $e) {
                        error_log("Failed to process attachment {$_FILES['attachments']['name'][$i]}: " . $e->getMessage());
                        $failedEmails[] = [
                            'email' => 'Attachment: ' . $_FILES['attachments']['name'][$i], 
                            'type' => 'File', 
                            'reason' => $e->getMessage()
                        ];
                    }
                }
            }
        }

        // --- Content Processing ---
        $mail->isHTML(true);
        
        // IMPORTANT: Subject must not be empty
        $subject = trim($_POST['subject'] ?? '');
        if (empty($subject)) {
            throw new Exception("Subject cannot be empty");
        }
        
        // Apply subject prefix if configured
        if (!empty($settings['default_subject_prefix'])) {
            $subject = $settings['default_subject_prefix'] . " " . $subject;
        }
        
        $mail->Subject = $subject;
        error_log("Email subject: $subject");
        
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
        error_log("Email sent successfully!");
        
        // --- DATABASE LOGGING: Save to modern email schema ---
        error_log("=== SAVING TO DATABASE ===");
        
        try {
            $pdo->beginTransaction();
            
            // 1. Generate email UUID
            $emailUuid = generateUuid();
            
            // 2. Insert into emails table (modern schema)
            $stmt = $pdo->prepare("
                INSERT INTO emails (
                    email_uuid, sender_email, sender_name, recipient_email,
                    cc_list, bcc_list, subject, body_html, body_text,
                    article_title, email_type, has_attachments, 
                    email_date, sent_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'sent', ?, NOW(), NOW())
            ");
            
            $hasAttachments = count($processedAttachments) > 0 ? 1 : 0;
            
            $stmt->execute([
                $emailUuid,
                $_SESSION['smtp_user'],
                $displayName,
                $recipient,
                implode(', ', $ccEmailsList),
                implode(', ', $bccEmailsList),
                $subject,
                $finalHtml,
                strip_tags($messageBody),
                $articleTitle,
                $hasAttachments
            ]);
            
            $emailId = $pdo->lastInsertId();
            error_log("Email saved to database with ID: $emailId");
            
            // 3. Get user ID
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$_SESSION['smtp_user']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $userId = $user['id'];
                
                // 4. Create user access record (sender)
                $stmt = $pdo->prepare("
                    INSERT INTO user_email_access (
                        user_id, email_id, access_type, is_deleted, user_read
                    ) VALUES (?, ?, 'sender', 0, 1)
                ");
                $stmt->execute([$userId, $emailId]);
                
                // 5. Link attachments and grant access
                foreach ($processedAttachments as $order => $fileInfo) {
                    // Link attachment to email
                    $attachmentManager->linkToEmail($fileInfo['id'], $emailId, $order);
                    
                    // Grant user access to attachment
                    $attachmentManager->grantUserAccess($userId, $emailId, $fileInfo['id'], 'owner');
                    
                    error_log("Linked attachment {$fileInfo['id']} to email $emailId");
                }
            } else {
                error_log("WARNING: User not found in users table: " . $_SESSION['smtp_user']);
            }
            
            $pdo->commit();
            $dbSaved = true;
            error_log("=== DATABASE SAVE SUCCESS ===");
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("=== DATABASE SAVE FAILED ===");
            error_log("Database error: " . $e->getMessage());
            $dbSaved = false;
        }
        
        // Generate response HTML
        showResultPage($subject, $successEmails, $failedEmails, $dbSaved, $processedAttachments);

    } catch (Exception $e) {
        error_log("=== EMAIL SEND FAILED ===");
        error_log("Error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        showErrorPage("Message could not be sent. Error: " . $e->getMessage());
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
 * Generate UUID v4
 */
function generateUuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

/**
 * Display success result page
 */
function showResultPage($subject, $successEmails, $failedEmails, $dbSaved, $attachments = []) {
    $totalSuccess = count($successEmails);
    $totalFailed = count($failedEmails);
    $attachmentCount = count($attachments);
    $deduplicatedCount = count(array_filter($attachments, function($a) { return $a['deduplicated']; }));
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <!-- Success page HTML - same as original, with attachment stats -->
    <head>
        <meta charset="UTF-8">
        <title>Email Sent Successfully</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            body { font-family: Arial, sans-serif; padding: 40px; background: #f5f5f5; }
            .container { max-width: 800px; margin: 0 auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { color: #28a745; margin-bottom: 20px; }
            .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
            .stat-box { padding: 20px; background: #f8f9fa; border-radius: 8px; text-align: center; }
            .stat-box h3 { margin: 0; font-size: 32px; color: #007bff; }
            .stat-box p { margin: 10px 0 0; color: #666; font-size: 14px; }
            .btn { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin-top: 20px; }
            .btn:hover { background: #0056b3; }
            .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <?php include 'sidebar.php'; ?>
        <div class="container">
            <h1><i class="fas fa-check-circle"></i> Email Sent Successfully!</h1>
            <p><strong>Subject:</strong> <?= htmlspecialchars($subject) ?></p>
            
            <div class="stats">
                <div class="stat-box">
                    <h3><?= $totalSuccess ?></h3>
                    <p>Recipients</p>
                </div>
                <?php if ($attachmentCount > 0): ?>
                <div class="stat-box">
                    <h3><?= $attachmentCount ?></h3>
                    <p>Attachments</p>
                </div>
                <?php endif; ?>
                <?php if ($deduplicatedCount > 0): ?>
                <div class="stat-box">
                    <h3><?= $deduplicatedCount ?></h3>
                    <p>Deduplicated Files</p>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (!$dbSaved): ?>
            <div class="warning">
                <i class="fas fa-exclamation-triangle"></i> 
                <strong>Note:</strong> Email was sent successfully but failed to save to database. Your sent items may not reflect this email.
            </div>
            <?php endif; ?>
            
            <a href="index.php" class="btn"><i class="fas fa-arrow-left"></i> Back to Composer</a>
        </div>
    </body>
    </html>
    <?php
}

/**
 * Display error page
 */
function showErrorPage($errorMessage) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Email Send Failed</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            body { font-family: Arial, sans-serif; padding: 40px; background: #f5f5f5; }
            .container { max-width: 800px; margin: 0 auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            h1 { color: #dc3545; margin-bottom: 20px; }
            .error-box { background: #f8d7da; border-left: 4px solid #dc3545; padding: 20px; margin: 20px 0; border-radius: 4px; }
            .btn { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin-top: 20px; }
            .btn:hover { background: #0056b3; }
        </style>
    </head>
    <body>
        <?php include 'sidebar.php'; ?>
        <div class="container">
            <h1><i class="fas fa-times-circle"></i> Email Delivery Failed</h1>
            <div class="error-box">
                <strong>Error:</strong> <?= htmlspecialchars($errorMessage) ?>
            </div>
            <a href="index.php" class="btn"><i class="fas fa-arrow-left"></i> Return to Composer</a>
        </div>
    </body>
    </html>
    <?php
}
?>