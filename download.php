<?php
// download.php - Secure File Download Handler
session_start();

// Security check
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    http_response_code(401);
    die('Unauthorized access');
}

require 'db_config.php';

// Encryption configuration (must match upload_handler.php)
define('ENCRYPTION_KEY', 'your-32-char-secret-key-here!!'); // Change this!
define('ENCRYPTION_METHOD', 'AES-256-CBC');
define('UPLOAD_DIR', 'uploads/attachments/');

/**
 * Decrypt file ID from download link
 */
function decryptFileId($encryptedId) {
    $data = base64_decode($encryptedId);
    $iv_length = openssl_cipher_iv_length(ENCRYPTION_METHOD);
    $iv = substr($data, 0, $iv_length);
    $encrypted = substr($data, $iv_length);
    
    return openssl_decrypt(
        $encrypted,
        ENCRYPTION_METHOD,
        ENCRYPTION_KEY,
        0,
        $iv
    );
}

/**
 * Get file extension icon class
 */
function getFileIcon($extension) {
    $icons = [
        'pdf' => 'fa-file-pdf',
        'doc' => 'fa-file-word',
        'docx' => 'fa-file-word',
        'xls' => 'fa-file-excel',
        'xlsx' => 'fa-file-excel',
        'ppt' => 'fa-file-powerpoint',
        'pptx' => 'fa-file-powerpoint',
        'jpg' => 'fa-file-image',
        'jpeg' => 'fa-file-image',
        'png' => 'fa-file-image',
        'gif' => 'fa-file-image',
        'webp' => 'fa-file-image',
        'zip' => 'fa-file-archive',
        'rar' => 'fa-file-archive',
        '7z' => 'fa-file-archive',
        'txt' => 'fa-file-alt',
        'csv' => 'fa-file-csv',
        'json' => 'fa-file-code',
        'xml' => 'fa-file-code'
    ];
    
    return $icons[$extension] ?? 'fa-file';
}

// Get encrypted file ID from URL
if (!isset($_GET['id'])) {
    http_response_code(400);
    die('File ID required');
}

$encryptedId = $_GET['id'];

try {
    // Decrypt the file ID
    $fileId = decryptFileId($encryptedId);
    
    if (!$fileId) {
        throw new Exception('Invalid file ID');
    }
    
    // Get database connection
    $pdo = getDatabaseConnection();
    
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Fetch file information from database
    $stmt = $pdo->prepare("
        SELECT * FROM attachments 
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        throw new Exception('File not found');
    }
    
    // Build full file path
    $filePath = UPLOAD_DIR . $file['storage_path'];
    
    if (!file_exists($filePath)) {
        throw new Exception('File does not exist on disk');
    }
    
    // Update last accessed timestamp
    $stmt = $pdo->prepare("UPDATE attachments SET last_accessed = NOW() WHERE id = ?");
    $stmt->execute([$fileId]);
    
    // Set headers for file download
    header('Content-Type: ' . $file['mime_type']);
    header('Content-Disposition: attachment; filename="' . $file['original_filename'] . '"');
    header('Content-Length: ' . $file['file_size']);
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: public');
    
    // Output file
    readfile($filePath);
    exit();
    
} catch (Exception $e) {
    http_response_code(404);
    error_log('Download error: ' . $e->getMessage());
    die('Error: ' . htmlspecialchars($e->getMessage()));
}
?>