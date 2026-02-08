<?php
/**
 * upload_handler.php - AJAX File Upload Handler with Encryption
 * Handles file uploads with deduplication and encrypted download links
 */

session_start();

// Security check
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require_once 'db_config.php';

header('Content-Type: application/json');

// Configuration
define('MAX_FILE_SIZE', 20 * 1024 * 1024); // 20MB per file
define('MAX_TOTAL_SIZE', 25 * 1024 * 1024); // 25MB total
define('UPLOAD_DIR', 'uploads/attachments/');

class UploadHandler {
    private $pdo;
    private $uploadDir;
    private $userId;
    private $userEmail;
    
    public function __construct($pdo, $userId, $userEmail) {
        $this->pdo = $pdo;
        $this->uploadDir = UPLOAD_DIR;
        $this->userId = $userId;
        $this->userEmail = $userEmail;
        $this->ensureUploadDirectory();
    }
    
    /**
     * Process uploaded file
     */
    public function processUpload($uploadedFile) {
        try {
            // Validate upload
            if (!isset($uploadedFile['tmp_name']) || !is_uploaded_file($uploadedFile['tmp_name'])) {
                throw new Exception("Invalid file upload");
            }
            
            // Check file size
            if ($uploadedFile['size'] > MAX_FILE_SIZE) {
                throw new Exception("File exceeds maximum size of " . $this->formatBytes(MAX_FILE_SIZE));
            }
            
            // Validate file type (basic security)
            $allowedExtensions = [
                'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
                'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
                'zip', 'rar', '7z',
                'txt', 'csv', 'json', 'xml', 'md'
            ];
            
            $extension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
            
            if (!in_array($extension, $allowedExtensions)) {
                throw new Exception("File type not allowed: .$extension");
            }
            
            // Calculate SHA256 hash for deduplication
            $fileHash = hash_file('sha256', $uploadedFile['tmp_name']);
            
            // Check if this exact file already exists
            $existing = $this->findByHash($fileHash);
            
            if ($existing) {
                // File already exists - check if user already has access
                $hasAccess = $this->checkUserAccess($this->userId, $existing['id']);
                
                if (!$hasAccess) {
                    // Create new access record
                    $this->createAccessRecord($existing['id'], null);
                    
                    // Store metadata
                    $this->storeMetadata($existing['id'], $uploadedFile['name'], $extension, $uploadedFile['type']);
                }
                
                // Generate encrypted ID for download
                $encryptedId = encryptFileId($existing['id']);
                
                return [
                    'success' => true,
                    'id' => $existing['id'],
                    'encrypted_id' => $encryptedId,
                    'path' => $existing['storage_path'],
                    'original_name' => $uploadedFile['name'],
                    'file_size' => $uploadedFile['size'],
                    'formatted_size' => $this->formatBytes($uploadedFile['size']),
                    'extension' => $extension,
                    'mime_type' => $uploadedFile['type'] ?? 'application/octet-stream',
                    'deduplicated' => true
                ];
            }
            
            // New file - generate UUID and save
            $fileUuid = $this->generateUuid();
            
            // Organize by year/month for better file management
            $storagePath = date('Y') . '/' . date('m') . '/' . $fileUuid . '.' . $extension;
            $fullPath = $this->uploadDir . $storagePath;
            
            // Ensure directory exists
            $dir = dirname($fullPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            // Move uploaded file to permanent storage
            if (!move_uploaded_file($uploadedFile['tmp_name'], $fullPath)) {
                throw new Exception("Failed to save file");
            }
            
            // Insert into attachments table
            $stmt = $this->pdo->prepare("
                INSERT INTO attachments (
                    file_uuid, file_hash, original_filename, 
                    file_extension, mime_type, file_size, 
                    storage_path, storage_type, uploaded_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'local', NOW())
            ");
            
            $stmt->execute([
                $fileUuid,
                $fileHash,
                $uploadedFile['name'],
                $extension,
                $uploadedFile['type'] ?? 'application/octet-stream',
                $uploadedFile['size'],
                $storagePath
            ]);
            
            $attachmentId = $this->pdo->lastInsertId();
            
            // Create access record
            $this->createAccessRecord($attachmentId, null);
            
            // Store metadata
            $this->storeMetadata($attachmentId, $uploadedFile['name'], $extension, $uploadedFile['type']);
            
            // Generate encrypted ID for download
            $encryptedId = encryptFileId($attachmentId);
            
            return [
                'success' => true,
                'id' => $attachmentId,
                'encrypted_id' => $encryptedId,
                'path' => $storagePath,
                'original_name' => $uploadedFile['name'],
                'file_size' => $uploadedFile['size'],
                'formatted_size' => $this->formatBytes($uploadedFile['size']),
                'extension' => $extension,
                'mime_type' => $uploadedFile['type'] ?? 'application/octet-stream',
                'deduplicated' => false
            ];
            
        } catch (Exception $e) {
            error_log("Upload error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create simplified access record
     */
    private function createAccessRecord($attachmentId, $emailUuid = null) {
        try {
            // Check what columns exist in user_attachment_access
            $stmt = $this->pdo->query("SHOW COLUMNS FROM user_attachment_access");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Build INSERT based on available columns
            if (in_array('sender_id', $columns) && in_array('receiver_id', $columns)) {
                // New schema with sender_id and receiver_id
                $stmt = $this->pdo->prepare("
                    INSERT INTO user_attachment_access (
                        user_id, attachment_id, sender_id, receiver_id,
                        email_uuid, access_type, created_at
                    ) VALUES (?, ?, ?, NULL, ?, 'upload', NOW())
                ");
                
                $stmt->execute([
                    $this->userId,
                    $attachmentId,
                    $this->userId, // sender_id
                    $emailUuid
                ]);
            } else {
                // Old schema without sender_id/receiver_id
                $stmt = $this->pdo->prepare("
                    INSERT INTO user_attachment_access (
                        user_id, attachment_id, email_uuid, 
                        access_type, created_at
                    ) VALUES (?, ?, ?, 'upload', NOW())
                ");
                
                $stmt->execute([
                    $this->userId,
                    $attachmentId,
                    $emailUuid
                ]);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Error creating access record: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Store file metadata
     */
    private function storeMetadata($attachmentId, $filename, $extension, $mimeType) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO attachment_metadata (
                    attachment_id, user_id, original_filename, 
                    file_extension, mime_type, created_at
                ) VALUES (?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    original_filename = VALUES(original_filename),
                    file_extension = VALUES(file_extension),
                    mime_type = VALUES(mime_type)
            ");
            
            return $stmt->execute([
                $attachmentId,
                $this->userId,
                $filename,
                $extension,
                $mimeType ?? 'application/octet-stream'
            ]);
        } catch (Exception $e) {
            error_log("Error storing metadata: " . $e->getMessage());
            // Don't fail upload if metadata fails
            return true;
        }
    }
    
    /**
     * Check if user already has access to this attachment
     */
    private function checkUserAccess($userId, $attachmentId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count 
            FROM user_attachment_access 
            WHERE user_id = ? AND attachment_id = ?
        ");
        $stmt->execute([$userId, $attachmentId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] > 0;
    }
    
    /**
     * Find existing file by hash
     */
    private function findByHash($hash) {
        $stmt = $this->pdo->prepare("SELECT * FROM attachments WHERE file_hash = ? LIMIT 1");
        $stmt->execute([$hash]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Generate UUID v4
     */
    private function generateUuid() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    /**
     * Format bytes to human readable
     */
    private function formatBytes($bytes) {
        if ($bytes === 0) return '0 Bytes';
        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
    
    private function ensureUploadDirectory() {
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }
}

// Main upload processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    try {
        $pdo = getDatabaseConnection();
        
        if (!$pdo) {
            throw new Exception("Database connection failed");
        }
        
        // Get or create user ID
        $userId = getUserId($pdo, $_SESSION['smtp_user']);
        
        if (!$userId) {
            // User doesn't exist - create them
            $userId = createUserIfNotExists($pdo, $_SESSION['smtp_user'], null);
            
            if (!$userId) {
                throw new Exception("Could not create user in database");
            }
            
            error_log("Created user in database during upload: " . $_SESSION['smtp_user'] . " (ID: $userId)");
        }
        
        // Initialize session attachments array if needed
        if (!isset($_SESSION['temp_attachments'])) {
            $_SESSION['temp_attachments'] = [];
        }
        
        // Calculate current total size
        $currentTotalSize = 0;
        foreach ($_SESSION['temp_attachments'] as $att) {
            $currentTotalSize += $att['file_size'];
        }
        
        $newFileSize = $_FILES['file']['size'];
        
        // Check total size limit
        if (($currentTotalSize + $newFileSize) > MAX_TOTAL_SIZE) {
            echo json_encode([
                'success' => false,
                'error' => 'Total file size exceeds 25MB limit. Current: ' . 
                          number_format($currentTotalSize / 1024 / 1024, 2) . 'MB'
            ]);
            exit();
        }
        
        $handler = new UploadHandler($pdo, $userId, $_SESSION['smtp_user']);
        $result = $handler->processUpload($_FILES['file']);
        
        if ($result['success']) {
            // Store in session for later use when sending
            $_SESSION['temp_attachments'][] = $result;
            
            error_log("File uploaded successfully: " . $result['original_name'] . " (ID: " . $result['id'] . ", Encrypted: " . $result['encrypted_id'] . ")");
        }
        
        echo json_encode($result);
        
    } catch (Exception $e) {
        error_log("Upload handler error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'No file uploaded'
    ]);
}
?>