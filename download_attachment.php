<?php
/**
 * download_attachment.php - Secure Attachment Download Handler
 * Handles downloading attachments from both sent and received emails
 */

session_start();

// Security check
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['authenticated'])) {
    http_response_code(401);
    die('Unauthorized');
}

require_once 'db_config.php';

// Get attachment identifier
$encryptedId = $_GET['id'] ?? null;
$inboxAttachmentId = $_GET['inbox_id'] ?? null;

if (!$encryptedId && !$inboxAttachmentId) {
    http_response_code(400);
    die('Invalid request - no attachment identifier provided');
}

try {
    $pdo = getDatabaseConnection();
    
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    $userId = getUserId($pdo, $_SESSION['smtp_user']);
    
    if (!$userId) {
        throw new Exception('User not found');
    }
    
    // Handle encrypted attachment ID (from sent emails / upload system)
    if ($encryptedId) {
        $attachmentId = decryptFileId($encryptedId);
        
        if (!$attachmentId) {
            throw new Exception('Invalid attachment ID');
        }
        
        // Get attachment details
        $stmt = $pdo->prepare("
            SELECT a.*, uaa.user_id 
            FROM attachments a
            LEFT JOIN user_attachment_access uaa ON a.id = uaa.attachment_id AND uaa.user_id = ?
            WHERE a.id = ?
        ");
        
        $stmt->execute([$userId, $attachmentId]);
        $attachment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$attachment) {
            throw new Exception('Attachment not found or access denied');
        }
        
        // Construct file path
        $filePath = 'uploads/attachments/' . $attachment['storage_path'];
        
        if (!file_exists($filePath)) {
            throw new Exception('File not found on server');
        }
        
        // Update last accessed
        $stmt = $pdo->prepare("UPDATE attachments SET last_accessed = NOW() WHERE id = ?");
        $stmt->execute([$attachmentId]);
        
        // Send file
        sendFile($filePath, $attachment['original_filename'], $attachment['mime_type']);
    }
    
    // Handle inbox attachment (from received emails stored in inbox_messages)
    if ($inboxAttachmentId) {
        // Parse the inbox attachment identifier: messageId-attachmentIndex
        $parts = explode('-', $inboxAttachmentId);
        
        if (count($parts) !== 2) {
            throw new Exception('Invalid inbox attachment identifier');
        }
        
        $messageId = intval($parts[0]);
        $attachmentIndex = intval($parts[1]);
        
        // Get message
        $stmt = $pdo->prepare("
            SELECT attachment_data 
            FROM inbox_messages 
            WHERE id = ? AND user_email = ?
        ");
        
        $stmt->execute([$messageId, $_SESSION['smtp_user']]);
        $message = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$message) {
            throw new Exception('Message not found or access denied');
        }
        
        // Parse attachments
        $attachments = json_decode($message['attachment_data'], true);
        
        if (!$attachments || !isset($attachments[$attachmentIndex])) {
            throw new Exception('Attachment not found in message');
        }
        
        $attachment = $attachments[$attachmentIndex];
        
        // Construct file path (assuming inbox attachments are stored separately)
        $filePath = 'uploads/inbox_attachments/' . $attachment['stored_filename'];
        
        if (!file_exists($filePath)) {
            // Try alternative path
            $filePath = 'attachments/' . $attachment['stored_filename'];
            
            if (!file_exists($filePath)) {
                throw new Exception('Attachment file not found on server');
            }
        }
        
        // Send file
        sendFile($filePath, $attachment['filename'], $attachment['mime_type'] ?? 'application/octet-stream');
    }
    
} catch (Exception $e) {
    error_log('Download error: ' . $e->getMessage());
    http_response_code(404);
    die('Error: ' . $e->getMessage());
}

/**
 * Send file to browser with proper headers
 */
function sendFile($filePath, $fileName, $mimeType) {
    // Clear output buffer
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set headers
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    // Output file
    readfile($filePath);
    exit();
}
?>