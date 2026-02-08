<?php
// download_file.php - Secure file download with encrypted IDs
session_start();

// Security check
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    http_response_code(401);
    die('Unauthorized');
}

require 'db_config.php';

// Encryption key - store this in environment variable in production
define('ENCRYPTION_KEY', 'your-32-char-secret-key-here!!'); // Change this!
define('ENCRYPTION_METHOD', 'AES-256-CBC');

/**
 * Decrypt file ID
 */
function decryptFileId($encrypted) {
    try {
        $data = base64_decode($encrypted);
        $iv_length = openssl_cipher_iv_length(ENCRYPTION_METHOD);
        $iv = substr($data, 0, $iv_length);
        $encrypted_data = substr($data, $iv_length);
        
        $decrypted = openssl_decrypt(
            $encrypted_data,
            ENCRYPTION_METHOD,
            ENCRYPTION_KEY,
            0,
            $iv
        );
        
        return $decrypted !== false ? (int)$decrypted : null;
    } catch (Exception $e) {
        return null;
    }
}

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
        die('Invalid file ID');
    }
    
    try {
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
            die('Access denied');
        }
        
        // Get file details
        $stmt = $pdo->prepare("SELECT * FROM attachments WHERE id = ?");
        $stmt->execute([$fileId]);
        $attachment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$attachment) {
            http_response_code(404);
            die('File not found');
        }
        
        $filePath = 'uploads/attachments/' . $attachment['storage_path'];
        
        if (!file_exists($filePath)) {
            http_response_code(404);
            die('File not found on disk');
        }
        
        // Update last accessed
        $stmt = $pdo->prepare("UPDATE attachments SET last_accessed = NOW() WHERE id = ?");
        $stmt->execute([$fileId]);
        
        // Send file
        header('Content-Type: ' . $attachment['mime_type']);
        header('Content-Disposition: attachment; filename="' . $attachment['original_filename'] . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        
        readfile($filePath);
        exit();
        
    } catch (Exception $e) {
        error_log("Download error: " . $e->getMessage());
        http_response_code(500);
        die('Download failed');
    }
} else {
    http_response_code(400);
    die('Missing file ID');
}
?>