<?php
/**
 * Bulk Mail Backend
 * 
 * Handles CSV file uploads, analysis, email queue population, and drive file management
 * Uses bulk_mail_queue table from u955994755_SXC_MDTS database
 */

session_start();
require_once 'db_config.php';

// Set JSON header
header('Content-Type: application/json');

// Drive directory configuration
define('DRIVE_DIR', '/files/public_html/SXC_MDTS/File_Drive');
echo("DRIVE_DIR resolved to: " . DRIVE_DIR);
echo("Directory exists: " . (is_dir(DRIVE_DIR) ? 'yes' : 'no'));
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
        case 'list_drive_files':
            // List files from /SXCMDTSHR/File_Drive directory
            if (!is_dir(DRIVE_DIR)) {
                throw new Exception('Drive directory not found: ' . DRIVE_DIR);
            }
            
            $files = [];
            $items = scandir(DRIVE_DIR);
            
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                
                $fullPath = DRIVE_DIR . '/' . $item;
                
                // Only include files, not directories
                if (is_file($fullPath)) {
                    $size = filesize($fullPath);
                    $files[] = [
                        'name' => $item,
                        'path' => $fullPath,
                        'size' => $size,
                        'formatted_size' => formatBytes($size),
                        'extension' => strtolower(pathinfo($item, PATHINFO_EXTENSION))
                    ];
                }
            }
            
            // Sort files by name
            usort($files, function($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
            
            echo json_encode([
                'success' => true,
                'files' => $files,
                'directory' => DRIVE_DIR,
                'count' => count($files)
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
            
            // Save temporary file path to session for later parsing
            $tempDir = sys_get_temp_dir();
            $tempFile = tempnam($tempDir, 'csv_');
            copy($file['tmp_name'], $tempFile);
            $_SESSION['temp_csv_file'] = $tempFile;
            
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
                } elseif (strpos($columnLower, 'designation') !== false) {
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
            
        case 'parse_full_csv':
            // Parse full CSV file for processing
            if (!isset($_SESSION['temp_csv_file'])) {
                throw new Exception('No CSV file in session');
            }
            
            $csvFile = $_SESSION['temp_csv_file'];
            
            if (!file_exists($csvFile)) {
                throw new Exception('CSV file not found');
            }
            
            // Parse all rows
            $handle = fopen($csvFile, 'r');
            if ($handle === false) {
                throw new Exception('Failed to read CSV file');
            }
            
            // Get headers
            $headers = fgetcsv($handle);
            if (!$headers) {
                fclose($handle);
                throw new Exception('CSV file is empty or invalid');
            }
            
            $headers = array_map('trim', $headers);
            
            // Read all data rows
            $rows = [];
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) === count($headers)) {
                    $rowData = array_combine($headers, $row);
                    $rows[] = $rowData;
                }
            }
            
            fclose($handle);
            
            echo json_encode([
                'success' => true,
                'rows' => $rows,
                'total' => count($rows)
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
            $driveFilePath = $postData['drive_file_path'] ?? null;
            
            // Register drive file as attachment if provided
            $attachmentId = null;
            if ($driveFilePath && file_exists($driveFilePath)) {
                $attachmentId = registerDriveFileAsAttachment($pdo, $user_id, $driveFilePath);
            }
            
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
                    
                    // Prepare data
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
                            drive_file_path,
                            status,
                            created_at
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW()
                        )
                    ");
                    
                    $stmt->execute([
                        $user_id,
                        $batchUuid,
                        $recipientEmail,
                        $email['recipient_name'] ?? '',
                        $email['subject'] ?? 'Bulk Email',
                        $email['article_title'] ?? '',
                        $email['message_content'] ?? '',
                        $email['closing_wish'] ?? '',
                        $email['sender_name'] ?? '',
                        $email['sender_designation'] ?? '',
                        $email['additional_info'] ?? '',
                        $attachmentId,
                        $driveFilePath
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
                'attachment_id' => $attachmentId,
                'errors' => $errors
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

/**
 * Register a drive file as an attachment in the database
 */
function registerDriveFileAsAttachment($pdo, $userId, $filePath) {
    try {
        if (!file_exists($filePath)) {
            throw new Exception('Drive file not found: ' . $filePath);
        }
        
        $fileName = basename($filePath);
        $fileSize = filesize($filePath);
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $fileHash = hash_file('sha256', $filePath);
        
        // Check if this file is already registered
        $stmt = $pdo->prepare("SELECT id FROM attachments WHERE file_hash = ? LIMIT 1");
        $stmt->execute([$fileHash]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            return $existing['id'];
        }
        
        // Register new attachment
        $fileUuid = generateUuidV4();
        
        $stmt = $pdo->prepare("
            INSERT INTO attachments (
                file_uuid, file_hash, original_filename, 
                file_extension, mime_type, file_size, 
                storage_path, storage_type, reference_count, uploaded_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'drive', 1, NOW())
        ");
        
        $mimeType = getMimeType($extension);
        
        $stmt->execute([
            $fileUuid,
            $fileHash,
            $fileName,
            $extension,
            $mimeType,
            $fileSize,
            $filePath  // Store full path for drive files
        ]);
        
        $attachmentId = $pdo->lastInsertId();
        
        // Create access record
        $stmt = $pdo->prepare("
            INSERT INTO user_attachment_access (user_id, attachment_id, created_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE created_at = NOW()
        ");
        $stmt->execute([$userId, $attachmentId]);
        
        return $attachmentId;
        
    } catch (Exception $e) {
        error_log("Error registering drive file: " . $e->getMessage());
        return null;
    }
}

/**
 * Get MIME type based on file extension
 */
function getMimeType($extension) {
    $mimeTypes = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'zip' => 'application/zip',
        'txt' => 'text/plain',
        'csv' => 'text/csv'
    ];
    
    return $mimeTypes[$extension] ?? 'application/octet-stream';
}

/**
 * Format bytes to human readable
 */
function formatBytes($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
?>