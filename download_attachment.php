<?php
/**
 * DOWNLOAD ATTACHMENT - OPTIMIZED WITH CACHING
 * Downloads attachment from IMAP on first request and caches it for future downloads
 */
require_once __DIR__ . '/security_handler.php';
session_start();

// Check authentication
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('HTTP/1.1 403 Forbidden');
    exit('Not authenticated');
}

require_once 'db_config.php';
require_once 'imap_helper.php';

// Configuration
define('ATTACHMENTS_DIR', __DIR__ . '/attachments/');

$userEmail = $_SESSION['smtp_user'];
$messageId = isset($_GET['message_id']) ? intval($_GET['message_id']) : 0;
$filename = isset($_GET['filename']) ? $_GET['filename'] : '';

if ($messageId === 0 || empty($filename)) {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid request parameters');
}

/**
 * Ensure attachments directory exists
 */
function ensureAttachmentsDir($messageId) {
    $messageDir = ATTACHMENTS_DIR . $messageId . '/';
    
    if (!is_dir(ATTACHMENTS_DIR)) {
        mkdir(ATTACHMENTS_DIR, 0755, true);
    }
    
    if (!is_dir($messageDir)) {
        mkdir($messageDir, 0755, true);
    }
    
    return $messageDir;
}

/**
 * Fetch attachment from IMAP server
 */
function fetchAttachmentFromIMAP($connection, $messageIdHeader, $attachmentIndex) {
    try {
        // Search for all messages
        $searchResults = imap_search($connection, 'ALL');
        
        if (!$searchResults) {
            return null;
        }
        
        $imapMsgNum = null;
        
        // Find the message by comparing message IDs
        foreach ($searchResults as $msgNum) {
            $overview = imap_fetch_overview($connection, $msgNum, 0);
            if (!empty($overview)) {
                $msgId = $overview[0]->message_id ?? '';
                if ($msgId === $messageIdHeader) {
                    $imapMsgNum = $msgNum;
                    break;
                }
            }
        }
        
        if (!$imapMsgNum) {
            return null;
        }
        
        // Get message structure
        $structure = imap_fetchstructure($connection, $imapMsgNum);
        
        if (!isset($structure->parts) || !count($structure->parts)) {
            return null;
        }
        
        // Find the attachment part
        $partNum = null;
        $currentAttachmentIndex = 0;
        $partInfo = null;
        
        foreach ($structure->parts as $index => $part) {
            if (isset($part->disposition) && 
                (strtolower($part->disposition) === 'attachment' || 
                 strtolower($part->disposition) === 'inline')) {
                
                if ($currentAttachmentIndex === $attachmentIndex) {
                    $partNum = $index + 1;
                    $partInfo = $part;
                    break;
                }
                $currentAttachmentIndex++;
            }
        }
        
        if (!$partNum || !$partInfo) {
            return null;
        }
        
        // Fetch the attachment data
        $attachmentData = imap_fetchbody($connection, $imapMsgNum, $partNum);
        
        // Decode based on encoding type
        switch ($partInfo->encoding) {
            case 0: case 1: case 2: case 5:
                // 7BIT, 8BIT, BINARY, OTHER - no decoding needed
                break;
            case 3:
                // BASE64
                $attachmentData = base64_decode($attachmentData);
                break;
            case 4:
                // QUOTED-PRINTABLE
                $attachmentData = quoted_printable_decode($attachmentData);
                break;
        }
        
        // Determine MIME type
        $mimeType = 'application/octet-stream';
        if (isset($partInfo->subtype)) {
            $type = isset($partInfo->type) ? $partInfo->type : 0;
            $typeNames = ['TEXT', 'MULTIPART', 'MESSAGE', 'APPLICATION', 'AUDIO', 'IMAGE', 'VIDEO', 'MODEL', 'OTHER'];
            $primaryType = isset($typeNames[$type]) ? $typeNames[$type] : 'APPLICATION';
            $mimeType = strtolower($primaryType . '/' . $partInfo->subtype);
        }
        
        return [
            'data' => $attachmentData,
            'mime_type' => $mimeType
        ];
        
    } catch (Exception $e) {
        error_log("Error fetching attachment from IMAP: " . $e->getMessage());
        return null;
    }
}

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        header('HTTP/1.1 500 Internal Server Error');
        exit('Database connection failed');
    }
    
    // Get message details
    $stmt = $pdo->prepare("
        SELECT message_id, attachment_data, has_attachments 
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
    
    // Parse attachments
    $attachments = json_decode($message['attachment_data'], true);
    
    if (!is_array($attachments)) {
        header('HTTP/1.1 500 Internal Server Error');
        exit('Invalid attachment data format');
    }
    
    // Find the requested attachment
    $attachmentIndex = null;
    $attachmentInfo = null;
    
    foreach ($attachments as $index => $att) {
        if ($att['filename'] === $filename) {
            $attachmentIndex = $index;
            $attachmentInfo = $att;
            break;
        }
    }
    
    if ($attachmentIndex === null) {
        header('HTTP/1.1 404 Not Found');
        exit('Attachment not found in message');
    }
    
    // Check if file is already cached
    $messageDir = ensureAttachmentsDir($messageId);
    $cachedFilePath = $messageDir . $filename;
    
    if (file_exists($cachedFilePath)) {
        // Serve cached file
        $mimeType = $attachmentInfo['mime_type'] ?? 'application/octet-stream';
        
        // Fallback mime type detection
        if ($mimeType === 'application/octet-stream') {
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $mimeTypes = [
                'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
                'gif' => 'image/gif', 'pdf' => 'application/pdf',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'txt' => 'text/plain', 'zip' => 'application/zip'
            ];
            $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';
        }
        
        // Clear output buffer
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Send headers
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Length: ' . filesize($cachedFilePath));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Output file
        readfile($cachedFilePath);
        exit();
    }
    
    // File not cached - fetch from IMAP
    $connection = connectToIMAPFromSession();
    
    if (!$connection) {
        header('HTTP/1.1 500 Internal Server Error');
        exit('Could not connect to mail server');
    }
    
    try {
        $result = fetchAttachmentFromIMAP($connection, $message['message_id'], $attachmentIndex);
        
        imap_close($connection);
        
        if (!$result) {
            header('HTTP/1.1 404 Not Found');
            exit('Could not fetch attachment from mail server');
        }
        
        // Save to cache
        file_put_contents($cachedFilePath, $result['data']);
        
        // Clear output buffer
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Send headers
        header('Content-Type: ' . $result['mime_type']);
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Length: ' . strlen($result['data']));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Output file
        echo $result['data'];
        exit();
        
    } catch (Exception $e) {
        if (isset($connection)) {
            imap_close($connection);
        }
        error_log("IMAP attachment fetch error: " . $e->getMessage());
        header('HTTP/1.1 500 Internal Server Error');
        exit('Error fetching attachment from mail server');
    }
    
} catch (Exception $e) {
    error_log("Download attachment error: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    exit('Server error: ' . $e->getMessage());
}
?>