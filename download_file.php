<?php
/**
 * download_file.php - Secure file download with encrypted IDs
 */
require_once __DIR__ . '/security_handler.php';
session_start();

// Security check
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    http_response_code(401);
    die('Unauthorized - Please login');
}

require_once 'db_config.php';

/**
 * Verify user has access to this file
 */
function verifyFileAccess($pdo, $userId, $attachmentId) {
    // Check if user uploaded this file or has access to an email with this attachment
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as has_access 
        FROM user_attachment_access 
        WHERE user_id = ? AND attachment_id = ?
    ");
    $stmt->execute([$userId, $attachmentId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['has_access'] > 0;
}

// Main download logic
if (isset($_GET['fid'])) {
    $encryptedId = $_GET['fid'];
    $fileId = decryptFileId($encryptedId);
    
    if (!$fileId) {
        http_response_code(400);
        die('Invalid file ID - decryption failed');
    }
    
    try {
        $pdo = getDatabaseConnection();
        
        if (!$pdo) {
            http_response_code(500);
            die('Database connection failed');
        }
        
        // Get user ID
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$_SESSION['smtp_user']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            http_response_code(403);
            die('User not found');
        }
        
        $userId = $user['id'];
        
        // Verify access
        if (!verifyFileAccess($pdo, $userId, $fileId)) {
            http_response_code(403);
            die('Access denied - You do not have permission to download this file');
        }
        
        // Get file details
        $stmt = $pdo->prepare("SELECT * FROM attachments WHERE id = ?");
        $stmt->execute([$fileId]);
        $attachment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$attachment) {
            http_response_code(404);
            die('File not found in database');
        }
        
        $filePath = 'uploads/attachments/' . $attachment['storage_path'];
        
        if (!file_exists($filePath)) {
            http_response_code(404);
            die('File not found on disk: ' . $attachment['storage_path']);
        }
        
        // Update last accessed
        $stmt = $pdo->prepare("UPDATE attachments SET last_accessed = NOW() WHERE id = ?");
        $stmt->execute([$fileId]);
        
        // Determine MIME type
        $mimeType = $attachment['mime_type'] ?? 'application/octet-stream';
        
        // Send file
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $attachment['original_filename'] . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Clear output buffer to prevent corruption
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        readfile($filePath);
        exit();
        
    } catch (Exception $e) {
        error_log("Download error: " . $e->getMessage());
        http_response_code(500);
        die('Download failed: ' . $e->getMessage());
    }
} else {
    http_response_code(400);
    die('Missing file ID parameter');
}
?>