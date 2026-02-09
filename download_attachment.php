<?php
/**
 * DOWNLOAD ATTACHMENT
 * Serves attachment files for download from inbox messages
 */

session_start();

// Check authentication
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('HTTP/1.1 403 Forbidden');
    exit('Not authenticated');
}

require_once 'db_config.php';

$userEmail = $_SESSION['smtp_user'];
$messageId = isset($_GET['message_id']) ? intval($_GET['message_id']) : 0;
$filename = isset($_GET['filename']) ? $_GET['filename'] : '';

if ($messageId === 0 || empty($filename)) {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid request parameters');
}

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        header('HTTP/1.1 500 Internal Server Error');
        exit('Database connection failed');
    }
    
    // Verify user owns this message and get attachment data
    $stmt = $pdo->prepare("
        SELECT attachment_data, has_attachments 
        FROM inbox_messages 
        WHERE id = :id 
        AND user_email = :user_email 
        AND is_deleted = 0
    ");
    
    $stmt->execute([
        ':id' => $messageId,
        ':user_email' => $userEmail
    ]);
    
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$message) {
        header('HTTP/1.1 404 Not Found');
        exit('Message not found or access denied');
    }
    
    if (!$message['has_attachments'] || empty($message['attachment_data'])) {
        header('HTTP/1.1 404 Not Found');
        exit('No attachments found for this message');
    }
    
    // Parse attachments and find the requested file
    $attachments = json_decode($message['attachment_data'], true);
    
    if (!is_array($attachments)) {
        header('HTTP/1.1 500 Internal Server Error');
        exit('Invalid attachment data format');
    }
    
    $attachment = null;
    foreach ($attachments as $att) {
        if ($att['filename'] === $filename) {
            $attachment = $att;
            break;
        }
    }
    
    if (!$attachment) {
        header('HTTP/1.1 404 Not Found');
        exit('Attachment not found in message');
    }
    
    // Build file path based on your storage structure
    // Option 1: Stored by message ID
    $filePath = __DIR__ . '/attachments/' . $messageId . '/' . $filename;
    
    // Option 2: If you're using a different structure, adjust accordingly
    // $filePath = __DIR__ . '/attachments/' . $userEmail . '/' . $messageId . '/' . $filename;
    
    // Option 3: If stored in user-specific folders
    // $sanitizedEmail = str_replace(['@', '.'], ['_', '_'], $userEmail);
    // $filePath = __DIR__ . '/attachments/' . $sanitizedEmail . '/' . $messageId . '/' . $filename;
    
    if (!file_exists($filePath)) {
        error_log("Attachment file not found: " . $filePath);
        header('HTTP/1.1 404 Not Found');
        exit('File not found on server. Path checked: attachments/' . $messageId . '/' . basename($filename));
    }
    
    // Validate file size matches
    $actualSize = filesize($filePath);
    if (isset($attachment['size']) && $actualSize != $attachment['size']) {
        error_log("File size mismatch for attachment: expected {$attachment['size']}, got {$actualSize}");
    }
    
    // Determine MIME type
    $mimeType = 'application/octet-stream'; // Default
    
    if (isset($attachment['mime_type']) && !empty($attachment['mime_type'])) {
        $mimeType = $attachment['mime_type'];
    } else {
        // Fallback: Guess from extension
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'txt' => 'text/plain',
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed'
        ];
        
        if (isset($mimeTypes[$extension])) {
            $mimeType = $mimeTypes[$extension];
        }
    }
    
    // Clear any previous output
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set headers for file download
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
    header('Content-Length: ' . $actualSize);
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Output file
    readfile($filePath);
    exit();
    
} catch (Exception $e) {
    error_log("Download attachment error: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    exit('Server error occurred while downloading attachment');
}
?>