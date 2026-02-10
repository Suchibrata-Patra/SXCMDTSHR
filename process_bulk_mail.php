<?php
/**
 * process_bulk_mail.php - Enhanced Backend processor for bulk email sending
 * Features: CSV preview, queue management, and sequential email processing
 */

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_log("=== Bulk Mail Processor Started ===");

session_start();

// Check if files exist
if (!file_exists('db_config.php')) {
    error_log("CRITICAL: db_config.php not found");
    echo json_encode(['success' => false, 'error' => 'System configuration error: db_config.php not found']);
    exit();
}

if (!file_exists('vendor/autoload.php')) {
    error_log("CRITICAL: vendor/autoload.php not found - Composer dependencies missing");
    echo json_encode(['success' => false, 'error' => 'System configuration error: PHPMailer not installed']);
    exit();
}

require_once 'db_config.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Security check
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    error_log("Unauthorized access attempt");
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized - Please login first']);
    exit();
}

header('Content-Type: application/json');

try {
    $pdo = getDatabaseConnection();
    
    if (!$pdo) {
        throw new Exception("Database connection failed - check db_config.php settings");
    }
    
    $userEmail = $_SESSION['smtp_user'];
    $userId = getUserId($pdo, $userEmail);
    
    if (!$userId) {
        error_log("User not found in database, creating: $userEmail");
        $userId = createUserIfNotExists($pdo, $userEmail, null);
        if (!$userId) {
            throw new Exception("Could not create user in database");
        }
    }
    
    $action = $_GET['action'] ?? 'status';
    
    error_log("Processing action: $action for user: $userEmail (ID: $userId)");
    
    switch ($action) {
        case 'preview':
            handleCSVPreview($pdo, $userId);
            break;
            
        case 'upload':
            handleCSVUpload($pdo, $userId);
            break;
        
        case 'process':
            processNextEmail($pdo, $userId);
            break;
        
        case 'status':
            getQueueStatus($pdo, $userId);
            break;
            
        case 'queue_list':
            getQueueList($pdo, $userId);
            break;
        
        case 'clear':
            clearQueue($pdo, $userId);
            break;
        
        case 'test':
            // Test endpoint to verify everything is working
            echo json_encode([
                'success' => true,
                'message' => 'Bulk mail processor is working',
                'user_id' => $userId,
                'user_email' => $userEmail,
                'php_version' => phpversion(),
                'session_active' => session_status() === PHP_SESSION_ACTIVE
            ]);
            break;
        
        default:
            throw new Exception('Invalid action: ' . $action);
    }
    
} catch (Exception $e) {
    error_log("CRITICAL ERROR: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage(),
        'debug_info' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ]);
}

/**
 * Preview CSV contents (first 5-10 rows)
 */
function handleCSVPreview($pdo, $userId) {
    try {
        error_log("=== CSV Preview Started ===");
        error_log("POST data: " . print_r($_POST, true));
        error_log("FILES data: " . print_r($_FILES, true));
        
        if (!isset($_FILES['csv_file'])) {
            throw new Exception('No CSV file uploaded. Please select a CSV file and try again.');
        }

        $csvFile = $_FILES['csv_file'];
        
        error_log("CSV file details: " . print_r($csvFile, true));
        
        if ($csvFile['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
            ];
            $errorMsg = $errorMessages[$csvFile['error']] ?? "Upload error code: {$csvFile['error']}";
            throw new Exception($errorMsg);
        }
        
        if (!file_exists($csvFile['tmp_name'])) {
            throw new Exception('Uploaded file not found on server');
        }
        
        // Validate file extension
        $fileExt = strtolower(pathinfo($csvFile['name'], PATHINFO_EXTENSION));
        if ($fileExt !== 'csv') {
            throw new Exception('Invalid file type. Please upload a CSV file. Received: ' . $fileExt);
        }

        error_log("Opening CSV file: {$csvFile['tmp_name']}");
        
        // Read CSV file
        $handle = fopen($csvFile['tmp_name'], 'r');
        if (!$handle) {
            throw new Exception('Could not open CSV file for reading. Please check file permissions.');
        }

        // Read header row
        $header = fgetcsv($handle);
        error_log("Raw header: " . print_r($header, true));
        
        if (!$header || empty($header)) {
            fclose($handle);
            throw new Exception('CSV file is empty or has no header row');
        }
        
        // Clean headers (remove BOM and whitespace)
        $header = array_map(function($h) {
            // Remove UTF-8 BOM if present
            $h = str_replace("\xEF\xBB\xBF", '', $h);
            return trim($h);
        }, $header);

        error_log("Cleaned CSV columns: " . implode(', ', $header));

        // Validate required columns
        $requiredColumns = [
            'mail_id', 
            'receiver_name', 
            'Mail_Subject', 
            'Article_Title',
            'Personalised_message', 
            'closing_wish', 
            'Name', 
            'Designation',
            'Additional_information', 
            'Attachments'
        ];

        $missingColumns = [];
        foreach ($requiredColumns as $col) {
            if (!in_array($col, $header)) {
                $missingColumns[] = $col;
            }
        }

        if (!empty($missingColumns)) {
            fclose($handle);
            throw new Exception("CSV is missing required columns: " . implode(', ', $missingColumns));
        }

        // Get column indices
        $columnMap = array_flip($header);
        
        // Read preview rows (5-10 rows)
        $previewRows = [];
        $totalRows = 0;
        $maxPreview = 10;
        
        while (($row = fgetcsv($handle)) !== false) {
            $totalRows++;
            
            if (count($previewRows) < $maxPreview) {
                $previewRows[] = [
                    'row_number' => $totalRows,
                    'mail_id' => $row[$columnMap['mail_id']] ?? '',
                    'receiver_name' => $row[$columnMap['receiver_name']] ?? '',
                    'subject' => $row[$columnMap['Mail_Subject']] ?? '',
                    'article_title' => $row[$columnMap['Article_Title']] ?? '',
                    'message_preview' => substr($row[$columnMap['Personalised_message']] ?? '', 0, 100) . '...',
                    'closing_wish' => $row[$columnMap['closing_wish']] ?? '',
                    'sender_name' => $row[$columnMap['Name']] ?? '',
                    'sender_designation' => $row[$columnMap['Designation']] ?? ''
                ];
            }
        }
        
        fclose($handle);
        
        // Store file temporarily in session for actual upload
        $_SESSION['preview_csv'] = [
            'name' => $csvFile['name'],
            'tmp_name' => $csvFile['tmp_name'],
            'size' => $csvFile['size']
        ];
        
        echo json_encode([
            'success' => true,
            'preview_rows' => $previewRows,
            'total_rows' => $totalRows,
            'headers' => $header,
            'message' => "Found $totalRows email(s) in CSV file. Preview showing first " . count($previewRows) . " rows."
        ]);
        
    } catch (Exception $e) {
        error_log("CSV Preview Error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Handle CSV upload and populate queue
 */
function handleCSVUpload($pdo, $userId) {
    try {
        error_log("=== CSV Upload Started ===");
        error_log("User ID: $userId");
        
        if (!isset($_FILES['csv_file'])) {
            throw new Exception('No CSV file uploaded. Please select a CSV file and try again.');
        }

        $csvFile = $_FILES['csv_file'];
        
        error_log("CSV file info: name={$csvFile['name']}, size={$csvFile['size']}, type={$csvFile['type']}, error={$csvFile['error']}");
        
        if ($csvFile['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive in php.ini',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive in HTML form',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder on server',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'Upload stopped by PHP extension'
            ];
            $errorMsg = $errorMessages[$csvFile['error']] ?? "Unknown upload error (code: {$csvFile['error']})";
            throw new Exception($errorMsg);
        }
        
        // Validate file size
        if ($csvFile['size'] > 10 * 1024 * 1024) {
            throw new Exception('CSV file is too large. Maximum size is 10MB.');
        }
        
        // Validate file extension
        $fileExt = strtolower(pathinfo($csvFile['name'], PATHINFO_EXTENSION));
        if ($fileExt !== 'csv') {
            throw new Exception('Invalid file type. Please upload a CSV file.');
        }

        // Read CSV file
        $handle = fopen($csvFile['tmp_name'], 'r');
        if (!$handle) {
            throw new Exception('Could not open CSV file for reading. File may be corrupted.');
        }

        // Read header row
        $header = fgetcsv($handle);
        if (!$header || empty($header)) {
            fclose($handle);
            throw new Exception('CSV file is empty or has no header row');
        }

        error_log("CSV columns found: " . implode(', ', $header));

        // Validate required columns
        $requiredColumns = [
            'mail_id', 
            'receiver_name', 
            'Mail_Subject', 
            'Article_Title',
            'Personalised_message', 
            'closing_wish', 
            'Name', 
            'Designation',
            'Additional_information', 
            'Attachments'
        ];

        $missingColumns = [];
        foreach ($requiredColumns as $col) {
            if (!in_array($col, $header)) {
                $missingColumns[] = $col;
            }
        }

        if (!empty($missingColumns)) {
            fclose($handle);
            $missing = implode(', ', $missingColumns);
            error_log("Missing columns: $missing");
            throw new Exception("CSV is missing required columns: $missing. Please check your CSV format.");
        }

        // Get column indices
        $columnMap = array_flip($header);

        // Get attachment ID if provided
        $attachmentId = !empty($_POST['attachment_id']) ? intval($_POST['attachment_id']) : null;
        
        if ($attachmentId) {
            error_log("Common attachment ID: $attachmentId");
            
            // Verify attachment exists
            $stmt = $pdo->prepare("SELECT id FROM attachments WHERE id = ?");
            $stmt->execute([$attachmentId]);
            if (!$stmt->fetch()) {
                error_log("WARNING: Attachment ID $attachmentId not found in database");
                $attachmentId = null;
            }
        }

        // Ensure queue table exists
        createQueueTable($pdo);

        // Generate batch UUID
        $batchUuid = generateUuidV4();
        error_log("Generated batch UUID: $batchUuid");

        // Clear existing pending/failed entries for this user
        $stmt = $pdo->prepare("DELETE FROM bulk_mail_queue WHERE user_id = ? AND status IN ('pending', 'failed')");
        $stmt->execute([$userId]);
        $cleared = $stmt->rowCount();
        
        error_log("Cleared $cleared old pending/failed queue entries");

        // Prepare insert statement
        $insertStmt = $pdo->prepare("
            INSERT INTO bulk_mail_queue (
                user_id, batch_uuid, recipient_email, recipient_name, 
                subject, article_title, message_content, 
                closing_wish, sender_name, sender_designation, 
                additional_info, attachment_id, status, created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW()
            )
        ");

        // Insert CSV rows into queue
        $insertCount = 0;
        $rowNum = 0;
        
        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            
            // Skip empty rows
            if (empty(array_filter($row))) {
                error_log("Skipping empty row $rowNum");
                continue;
            }
            
            // Extract email from mail_id field
            $mailId = trim($row[$columnMap['mail_id']] ?? '');
            $recipientEmail = filter_var($mailId, FILTER_VALIDATE_EMAIL) ? $mailId : '';
            
            if (empty($recipientEmail)) {
                error_log("Row $rowNum: Invalid or missing email address: '$mailId'");
                continue;
            }
            
            $recipientName = trim($row[$columnMap['receiver_name']] ?? '');
            $subject = trim($row[$columnMap['Mail_Subject']] ?? 'No Subject');
            $articleTitle = trim($row[$columnMap['Article_Title']] ?? '');
            $messageContent = trim($row[$columnMap['Personalised_message']] ?? '');
            $closingWish = trim($row[$columnMap['closing_wish']] ?? 'Best Regards');
            $senderName = trim($row[$columnMap['Name']] ?? '');
            $senderDesignation = trim($row[$columnMap['Designation']] ?? '');
            $additionalInfo = trim($row[$columnMap['Additional_information']] ?? '');
            
            // Insert into queue
            try {
                $insertStmt->execute([
                    $userId,
                    $batchUuid,
                    $recipientEmail,
                    $recipientName,
                    $subject,
                    $articleTitle,
                    $messageContent,
                    $closingWish,
                    $senderName,
                    $senderDesignation,
                    $additionalInfo,
                    $attachmentId
                ]);
                
                $insertCount++;
                error_log("Row $rowNum: Queued email to $recipientEmail");
                
            } catch (Exception $e) {
                error_log("Row $rowNum: Error inserting into queue: " . $e->getMessage());
            }
        }
        
        fclose($handle);
        
        error_log("Successfully queued $insertCount emails out of $rowNum CSV rows");
        
        echo json_encode([
            'success' => true,
            'message' => "Successfully queued $insertCount emails",
            'queued_count' => $insertCount,
            'total_rows' => $rowNum,
            'batch_uuid' => $batchUuid
        ]);
        
    } catch (Exception $e) {
        error_log("CSV Upload Error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Process next email in queue
 */
function processNextEmail($pdo, $userId) {
    try {
        // Get next pending email
        $stmt = $pdo->prepare("
            SELECT * FROM bulk_mail_queue 
            WHERE user_id = ? AND status = 'pending' 
            ORDER BY created_at ASC 
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $queueItem = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$queueItem) {
            // No more pending emails
            $status = getQueueStatusData($pdo, $userId);
            echo json_encode([
                'success' => true,
                'has_more' => false,
                'message' => 'All emails processed',
                'pending' => $status['pending'],
                'processing' => $status['processing'],
                'completed' => $status['completed'],
                'failed' => $status['failed'],
                'total' => $status['total']
            ]);
            return;
        }
        
        // Mark as processing
        $updateStmt = $pdo->prepare("
            UPDATE bulk_mail_queue 
            SET status = 'processing', processing_started_at = NOW() 
            WHERE id = ?
        ");
        $updateStmt->execute([$queueItem['id']]);
        
        error_log("Processing queue item #{$queueItem['id']} to {$queueItem['recipient_email']}");
        
        // Send the email
        $result = sendBulkEmail($pdo, $queueItem);
        
        if ($result['success']) {
            // Mark as completed
            $updateStmt = $pdo->prepare("
                UPDATE bulk_mail_queue 
                SET status = 'completed', 
                    completed_at = NOW(),
                    sent_email_id = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$result['email_id'], $queueItem['id']]);
            
            error_log("✓ Successfully sent email to {$queueItem['recipient_email']}");
        } else {
            // Mark as failed
            $updateStmt = $pdo->prepare("
                UPDATE bulk_mail_queue 
                SET status = 'failed', 
                    completed_at = NOW(),
                    error_message = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$result['error'], $queueItem['id']]);
            
            error_log("✗ Failed to send email to {$queueItem['recipient_email']}: {$result['error']}");
        }
        
        // Get updated status
        $status = getQueueStatusData($pdo, $userId);
        
        echo json_encode([
            'success' => true,
            'has_more' => $status['pending'] > 0,
            'current_email' => [
                'recipient' => $queueItem['recipient_email'],
                'recipient_name' => $queueItem['recipient_name'],
                'subject' => $queueItem['subject']
            ],
            'last_result' => [
                'recipient' => $queueItem['recipient_email'],
                'status' => $result['success'] ? 'completed' : 'failed',
                'error_message' => $result['error'] ?? null
            ],
            'pending' => $status['pending'],
            'processing' => $status['processing'],
            'completed' => $status['completed'],
            'failed' => $status['failed'],
            'total' => $status['total']
        ]);
        
    } catch (Exception $e) {
        error_log("Process Email Error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Send bulk email using same logic as send.php
 */
function sendBulkEmail($pdo, $queueItem) {
    try {
        $mail = new PHPMailer(true);
        
        // SMTP Configuration (same as send.php)
        $mail->isSMTP();
        $mail->SMTPDebug = 0;
        $mail->Host = "smtp.hostinger.com";
        $mail->SMTPAuth = true;
        $mail->Username = $_SESSION['smtp_user'];
        $mail->Password = $_SESSION['smtp_pass'];
        $mail->Port = 465;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        
        $settings = $_SESSION['user_settings'] ?? [];
        $displayName = !empty($settings['display_name']) ? $settings['display_name'] : "St. Xavier's College";
        
        $mail->setFrom($_SESSION['smtp_user'], $displayName);
        
        // Set recipient
        $recipient = filter_var(trim($queueItem['recipient_email']), FILTER_SANITIZE_EMAIL);
        if (!$recipient || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid recipient email address");
        }
        
        $mail->addAddress($recipient, $queueItem['recipient_name'] ?? '');
        
        // Handle attachment
        if (!empty($queueItem['attachment_id'])) {
            $attachmentId = intval($queueItem['attachment_id']);
            $stmt = $pdo->prepare("SELECT * FROM attachments WHERE id = ?");
            $stmt->execute([$attachmentId]);
            $attachment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($attachment) {
                $uploadDir = 'uploads/attachments/';
                $fullFilePath = $uploadDir . $attachment['storage_path'];
                
                if (file_exists($fullFilePath)) {
                    $mail->addAttachment($fullFilePath, $attachment['original_filename']);
                    error_log("Attached file: " . $attachment['original_filename']);
                } else {
                    error_log("WARNING: Attachment file not found: $fullFilePath");
                }
            }
        }
        
        // Load email template (same as send.php)
        $templatePath = __DIR__ . '/templates/template1.html';
        
        if (!file_exists($templatePath)) {
            throw new Exception("Email template not found. Please ensure templates/template1.html exists.");
        }
        
        $emailTemplate = file_get_contents($templatePath);
        
        // Replace placeholders (same as send.php)
        $emailBody = str_replace([
            '{{articletitle}}',
            '{{MESSAGE}}',
            '{{SIGNATURE_WISH}}',
            '{{SIGNATURE_NAME}}',
            '{{SIGNATURE_DESIGNATION}}',
            '{{SIGNATURE_EXTRA}}'
        ], [
            htmlspecialchars($queueItem['article_title'], ENT_QUOTES, 'UTF-8'),
            nl2br(htmlspecialchars($queueItem['message_content'], ENT_QUOTES, 'UTF-8')),
            htmlspecialchars($queueItem['closing_wish'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($queueItem['sender_name'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($queueItem['sender_designation'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($queueItem['additional_info'], ENT_QUOTES, 'UTF-8')
        ], $emailTemplate);
        
        // Set email content
        $mail->isHTML(true);
        $mail->Subject = $queueItem['subject'];
        $mail->Body = $emailBody;
        $mail->AltBody = strip_tags($queueItem['message_content']);
        
        // Send email
        if (!$mail->send()) {
            throw new Exception("Mailer Error: " . $mail->ErrorInfo);
        }
        
        // Save to database (same as send.php)
        $emailUuid = generateUuidV4();
        $emailData = [
            'email_uuid' => $emailUuid,
            'sender_email' => $_SESSION['smtp_user'],
            'sender_name' => $displayName,
            'recipient_email' => $recipient,
            'cc_list' => null,
            'bcc_list' => null,
            'subject' => $queueItem['subject'],
            'body_text' => strip_tags($queueItem['message_content']),
            'body_html' => $emailBody,
            'article_title' => $queueItem['article_title'],
            'email_type' => 'sent',
            'has_attachments' => !empty($queueItem['attachment_id']) ? 1 : 0
        ];
        
        $emailId = saveEmailToDatabase($pdo, $emailData);
        
        if ($emailId) {
            createEmailAccess($pdo, $emailId, $queueItem['user_id'], 'sender', null);
        }
        
        return [
            'success' => true,
            'email_id' => $emailId
        ];
        
    } catch (Exception $e) {
        error_log("Error sending bulk email: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Get queue status
 */
function getQueueStatus($pdo, $userId) {
    $status = getQueueStatusData($pdo, $userId);
    echo json_encode([
        'success' => true,
        'pending' => $status['pending'],
        'processing' => $status['processing'],
        'completed' => $status['completed'],
        'failed' => $status['failed'],
        'total' => $status['total']
    ]);
}

/**
 * Get queue status data
 */
function getQueueStatusData($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
            COUNT(*) as total
        FROM bulk_mail_queue
        WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'pending' => intval($result['pending'] ?? 0),
        'processing' => intval($result['processing'] ?? 0),
        'completed' => intval($result['completed'] ?? 0),
        'failed' => intval($result['failed'] ?? 0),
        'total' => intval($result['total'] ?? 0)
    ];
}

/**
 * Get queue list with details
 */
function getQueueList($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                id, recipient_email, recipient_name, subject, 
                article_title, status, error_message, 
                created_at, completed_at
            FROM bulk_mail_queue
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 100
        ");
        $stmt->execute([$userId]);
        $queue = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'queue' => $queue
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Clear queue
 */
function clearQueue($pdo, $userId) {
    $stmt = $pdo->prepare("DELETE FROM bulk_mail_queue WHERE user_id = ? AND status IN ('pending', 'failed')");
    $stmt->execute([$userId]);
    $deleted = $stmt->rowCount();
    
    error_log("Cleared $deleted pending/failed queue items for user $userId");
    
    echo json_encode([
        'success' => true,
        'message' => "Cleared $deleted emails from queue"
    ]);
}

/**
 * Create queue table if it doesn't exist
 */
function createQueueTable($pdo) {
    try {
        $sql = "CREATE TABLE IF NOT EXISTS `bulk_mail_queue` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) NOT NULL,
            `batch_uuid` VARCHAR(36) NOT NULL,
            `recipient_email` VARCHAR(255) NOT NULL,
            `recipient_name` VARCHAR(255) DEFAULT NULL,
            `subject` VARCHAR(500) NOT NULL,
            `article_title` VARCHAR(500) NOT NULL,
            `message_content` TEXT NOT NULL,
            `closing_wish` VARCHAR(255) DEFAULT NULL,
            `sender_name` VARCHAR(255) DEFAULT NULL,
            `sender_designation` VARCHAR(255) DEFAULT NULL,
            `additional_info` VARCHAR(500) DEFAULT NULL,
            `attachment_id` INT(11) DEFAULT NULL,
            `status` ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
            `error_message` TEXT DEFAULT NULL,
            `sent_email_id` INT(11) DEFAULT NULL,
            `created_at` DATETIME NOT NULL,
            `processing_started_at` DATETIME DEFAULT NULL,
            `completed_at` DATETIME DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_user_status` (`user_id`, `status`),
            KEY `idx_batch` (`batch_uuid`),
            KEY `idx_created` (`created_at`),
            KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        error_log("Queue table verified/created successfully");
        
    } catch (Exception $e) {
        error_log("Error creating queue table: " . $e->getMessage());
        throw new Exception("Could not create queue table: " . $e->getMessage());
    }
}

/**
 * Generate UUID v4
 */
function generateUuidV4() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}
?>