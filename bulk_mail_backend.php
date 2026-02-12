<?php
/**
 * Bulk Mail Backend
 * 
 * Handles CSV file uploads, analysis, and email queue population
 * Uses bulk_mail_queue table from u955994755_SXC_MDTS database
 */

session_start();
require_once 'db_config.php';

// Set JSON header
header('Content-Type: application/json');

// Get action from request
$action = $_GET['action'] ?? '';

try {
    $pdo = getDatabaseConnection();
    
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Get current user from session
    $user_email = $_SESSION['smtp_user'] ?? null;
    
    // Get user ID if user is logged in
    $user_id = null;
    if ($user_email) {
        $user_id = getUserId($pdo, $user_email);
    }
    
    switch ($action) {
        case 'get_expected_fields':
            // Return expected email fields for mapping
            echo json_encode([
                'success' => true,
                'expected_fields' => [
                    'recipient_email' => 'Recipient Email Address (required)',
                    'recipient_name' => 'Recipient Name (optional)',
                    'subject' => 'Email Subject (optional)',
                    'article_title' => 'Article Title (optional)',
                    'message_content' => 'Message Content (optional)',
                    'closing_wish' => 'Closing Wish (optional)',
                    'sender_name' => 'Sender Name (optional)',
                    'sender_designation' => 'Sender Designation (optional)',
                    'additional_info' => 'Additional Info (optional)'
                ]
            ]);
            break;
            
        case 'analyze':
            // Analyze uploaded CSV file
            if (!isset($_FILES['csv_file'])) {
                throw new Exception('No file uploaded');
            }
            
            $file = $_FILES['csv_file'];
            
            // Validate file
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('File upload error: ' . $file['error']);
            }
            
            if ($file['size'] > 10 * 1024 * 1024) { // 10MB limit
                throw new Exception('File size exceeds 10MB limit');
            }
            
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext !== 'csv') {
                throw new Exception('Only CSV files are allowed');
            }
            
            // Parse CSV file
            $csvData = [];
            $handle = fopen($file['tmp_name'], 'r');
            
            if ($handle === false) {
                throw new Exception('Failed to read CSV file');
            }
            
            // Get headers (first row)
            $headers = fgetcsv($handle);
            if (!$headers) {
                fclose($handle);
                throw new Exception('CSV file is empty or invalid');
            }
            
            // Clean headers
            $headers = array_map('trim', $headers);
            
            // Read data rows (up to 100 for preview)
            $rowCount = 0;
            $previewRows = [];
            
            while (($row = fgetcsv($handle)) !== false && $rowCount < 100) {
                if (count($row) === count($headers)) {
                    $rowData = array_combine($headers, $row);
                    $csvData[] = $rowData;
                    
                    if ($rowCount < 5) {
                        $previewRows[] = $rowData;
                    }
                    $rowCount++;
                }
            }
            
            // Count total rows
            while (fgetcsv($handle) !== false) {
                $rowCount++;
            }
            
            fclose($handle);
            
            // Auto-suggest mapping based on column names
            $suggestedMapping = [];
            foreach ($headers as $column) {
                $columnLower = strtolower($column);
                
                if (strpos($columnLower, 'email') !== false || strpos($columnLower, 'e-mail') !== false) {
                    $suggestedMapping[$column] = 'recipient_email';
                } elseif (strpos($columnLower, 'name') !== false && strpos($columnLower, 'sender') === false) {
                    $suggestedMapping[$column] = 'recipient_name';
                } elseif (strpos($columnLower, 'subject') !== false) {
                    $suggestedMapping[$column] = 'subject';
                } elseif (strpos($columnLower, 'article') !== false || strpos($columnLower, 'title') !== false) {
                    $suggestedMapping[$column] = 'article_title';
                } elseif (strpos($columnLower, 'message') !== false || strpos($columnLower, 'content') !== false || strpos($columnLower, 'body') !== false) {
                    $suggestedMapping[$column] = 'message_content';
                } elseif (strpos($columnLower, 'wish') !== false || strpos($columnLower, 'closing') !== false) {
                    $suggestedMapping[$column] = 'closing_wish';
                } elseif (strpos($columnLower, 'sender') !== false && strpos($columnLower, 'name') !== false) {
                    $suggestedMapping[$column] = 'sender_name';
                } elseif (strpos($columnLower, 'designation') !== false || strpos($columnLower, 'title') !== false) {
                    $suggestedMapping[$column] = 'sender_designation';
                }
            }
            
            echo json_encode([
                'success' => true,
                'filename' => $file['name'],
                'total_rows' => $rowCount,
                'csv_columns' => $headers,
                'preview_rows' => $previewRows,
                'suggested_mapping' => $suggestedMapping
            ]);
            break;
            
        case 'add_to_queue':
            // Add emails from CSV to queue
            if (!$user_id) {
                throw new Exception('User not logged in or user not found');
            }
            
            $postData = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($postData['emails']) || !is_array($postData['emails'])) {
                throw new Exception('Invalid email data');
            }
            
            $emails = $postData['emails'];
            $defaultSubject = $postData['subject'] ?? 'Bulk Email';
            $defaultArticleTitle = $postData['article_title'] ?? '';
            $defaultMessageContent = $postData['message_content'] ?? '';
            $defaultClosingWish = $postData['closing_wish'] ?? '';
            $defaultSenderName = $postData['sender_name'] ?? '';
            $defaultSenderDesignation = $postData['sender_designation'] ?? '';
            $defaultAdditionalInfo = $postData['additional_info'] ?? '';
            $attachmentId = $postData['attachment_id'] ?? null;
            
            // Generate a batch UUID for this bulk upload
            $batchUuid = generateUuidV4();
            
            $added = 0;
            $errors = [];
            
            foreach ($emails as $email) {
                try {
                    // Validate email address
                    $recipientEmail = $email['recipient_email'] ?? $email['email'] ?? '';
                    
                    if (empty($recipientEmail) || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = "Invalid email: " . ($recipientEmail ?: 'empty');
                        continue;
                    }
                    
                    // Prepare data with fallback to defaults
                    $stmt = $pdo->prepare("
                        INSERT INTO bulk_mail_queue (
                            user_id,
                            batch_uuid,
                            recipient_email,
                            recipient_name,
                            subject,
                            article_title,
                            message_content,
                            closing_wish,
                            sender_name,
                            sender_designation,
                            additional_info,
                            attachment_id,
                            status,
                            created_at
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW()
                        )
                    ");
                    
                    $stmt->execute([
                        $user_id,
                        $batchUuid,
                        $recipientEmail,
                        $email['recipient_name'] ?? $email['name'] ?? '',
                        $email['subject'] ?? $defaultSubject,
                        $email['article_title'] ?? $defaultArticleTitle,
                        $email['message_content'] ?? $defaultMessageContent,
                        $email['closing_wish'] ?? $defaultClosingWish,
                        $email['sender_name'] ?? $defaultSenderName,
                        $email['sender_designation'] ?? $defaultSenderDesignation,
                        $email['additional_info'] ?? $defaultAdditionalInfo,
                        $attachmentId
                    ]);
                    
                    $added++;
                    
                } catch (Exception $e) {
                    $errors[] = "Error adding " . $recipientEmail . ": " . $e->getMessage();
                }
            }
            
            echo json_encode([
                'success' => true,
                'batch_uuid' => $batchUuid,
                'added' => $added,
                'total' => count($emails),
                'errors' => $errors
            ]);
            break;
            
        case 'test_connection':
            // Test database connection
            echo json_encode([
                'success' => true,
                'message' => 'Database connection successful',
                'database' => 'u955994755_SXC_MDTS',
                'user_id' => $user_id,
                'user_email' => $user_email
            ]);
            break;
            
        default:
            throw new Exception('Invalid action: ' . $action);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>