<?php
/**
 * download.php - Secure File Download via Email UID
 * 
 * Download files using the unique Email UID as the secure token
 * URL Format: download.php?mail_uid=[email_uuid]&attachment_id=[attachment_id]
 * 
 * Security Features:
 * - Verifies user is sender or receiver of the email
 * - Tracks download activity
 * - Validates file access permissions
 */

session_start();

// Security check - user must be logged in
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    http_response_code(401);
    die('Unauthorized: Please log in to download files');
}

require 'db_config.php';

// Configuration
define('UPLOAD_DIR', 'uploads/attachments/');

/**
 * Log download activity
 */
function logDownload($pdo, $attachmentId, $userId, $emailUuid) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO attachment_downloads (
                attachment_id, user_id, email_uuid, 
                ip_address, user_agent, downloaded_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt->execute([
            $attachmentId,
            $userId,
            $emailUuid,
            $ipAddress,
            substr($userAgent, 0, 500) // Limit length
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Error logging download: " . $e->getMessage());
        return false;
    }
}

/**
 * Verify user has access to this attachment via the email
 */
function verifyAccess($pdo, $userId, $emailUuid, $attachmentId) {
    try {
        // Check if user has access to this attachment through this email
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as has_access
            FROM user_attachment_access
            WHERE email_uuid = ?
              AND attachment_id = ?
              AND (sender_id = ? OR receiver_id = ?)
        ");
        
        $stmt->execute([$emailUuid, $attachmentId, $userId, $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['has_access'] > 0;
    } catch (Exception $e) {
        error_log("Error verifying access: " . $e->getMessage());
        return false;
    }
}

/**
 * Get attachment details with metadata
 */
function getAttachmentDetails($pdo, $attachmentId, $userId) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                a.id,
                a.file_uuid,
                a.storage_path,
                a.file_size,
                am.original_filename,
                am.file_extension,
                am.mime_type
            FROM attachments a
            LEFT JOIN attachment_metadata am 
                ON a.id = am.attachment_id 
                AND am.user_id = ?
            WHERE a.id = ?
            LIMIT 1
        ");
        
        $stmt->execute([$userId, $attachmentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting attachment details: " . $e->getMessage());
        return null;
    }
}

// Main download logic
try {
    // Validate required parameters
    if (!isset($_GET['mail_uid']) || !isset($_GET['attachment_id'])) {
        http_response_code(400);
        die('Bad Request: Missing required parameters (mail_uid and attachment_id)');
    }
    
    $emailUuid = $_GET['mail_uid'];
    $attachmentId = (int)$_GET['attachment_id'];
    
    // Validate UUID format
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $emailUuid)) {
        http_response_code(400);
        die('Bad Request: Invalid email UID format');
    }
    
    // Get database connection
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        http_response_code(500);
        die('Internal Server Error: Database connection failed');
    }
    
    // Get current user ID
    $userId = getUserId($pdo, $_SESSION['smtp_user']);
    if (!$userId) {
        http_response_code(403);
        die('Forbidden: User not found');
    }
    
    // Verify user has access to this attachment via this email
    if (!verifyAccess($pdo, $userId, $emailUuid, $attachmentId)) {
        http_response_code(403);
        error_log("Access denied for user $userId to attachment $attachmentId via email $emailUuid");
        die('Forbidden: You do not have permission to download this file');
    }
    
    // Get attachment details
    $attachment = getAttachmentDetails($pdo, $attachmentId, $userId);
    
    if (!$attachment) {
        http_response_code(404);
        die('Not Found: Attachment not found');
    }
    
    // Build full file path
    $filePath = UPLOAD_DIR . $attachment['storage_path'];
    
    // Verify file exists on disk
    if (!file_exists($filePath)) {
        http_response_code(404);
        error_log("File not found on disk: $filePath");
        die('Not Found: File not found on server');
    }
    
    // Log the download
    logDownload($pdo, $attachmentId, $userId, $emailUuid);
    
    // Update last accessed timestamp
    $stmt = $pdo->prepare("UPDATE attachments SET last_accessed = NOW() WHERE id = ?");
    $stmt->execute([$attachmentId]);
    
    // Determine filename to use
    $downloadFilename = $attachment['original_filename'] ?? 
                       ($attachment['file_uuid'] . '.' . $attachment['file_extension']);
    
    // Determine MIME type
    $mimeType = $attachment['mime_type'] ?? 'application/octet-stream';
    
    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Send file headers
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . addslashes($downloadFilename) . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Output file
    readfile($filePath);
    exit();
    
} catch (Exception $e) {
    error_log("Download error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    die('Internal Server Error: Download failed');
}
?>