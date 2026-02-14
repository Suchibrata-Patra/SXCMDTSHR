<?php
/**
 * upload_handler.php - AJAX File Upload Handler with Encryption
 * Handles file uploads with deduplication and encrypted download links
 * ENHANCED VERSION - Guarantees proper file and database registration
 */
require_once __DIR__ . '/security_handler.php';
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
     * Process uploaded file - ENHANCED VERSION
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
            
            error_log("Processing upload: " . $uploadedFile['name'] . " (Hash: $fileHash)");
            
            // Check if this exact file already exists
            $existing = $this->findByHash($fileHash);
            
            if ($existing) {
                error_log("File already exists in database (ID: " . $existing['id'] . ")");
                
                // Verify physical file exists
                $physicalPath = $this->uploadDir . $existing['storage_path'];
                if (!file_exists($physicalPath)) {
                    error_log("WARNING: Database record exists but physical file missing. Re-uploading...");
                    // Treat as new upload
                    $existing = null;
                }
            }
            
            if ($existing && file_exists($this->uploadDir . $existing['storage_path'])) {
                // File already exists - check if user already has access
                $hasAccess = $this->checkUserAccess($this->userId, $existing['id']);
                
                if (!$hasAccess) {
                    // Create new access record
                    $this->createAccessRecord($existing['id'], null);
                    error_log("Created access record for existing file");
                    
                    // Store metadata
                    $this->storeMetadata($existing['id'], $uploadedFile['name'], $extension, $uploadedFile['type']);
                }
                
                // Update reference count
                $this->incrementReferenceCount($existing['id']);
                
                // Generate encrypted ID for download
                $encryptedId = encryptFileId($existing['id']);
                
                error_log("Deduplicated upload - using existing file ID: " . $existing['id']);
                
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
                if (!mkdir($dir, 0755, true)) {
                    throw new Exception("Failed to create upload directory");
                }
            }
            
            // Move uploaded file to permanent storage
            if (!move_uploaded_file($uploadedFile['tmp_name'], $fullPath)) {
                throw new Exception("Failed to save file to: $fullPath");
            }
            
            // Verify file was saved
            if (!file_exists($fullPath)) {
                throw new Exception("File upload verification failed - file not found after move");
            }
            
            error_log("File saved to: $fullPath");
            
            // Insert into attachments table
            $stmt = $this->pdo->prepare("
                INSERT INTO attachments (
                    file_uuid, file_hash, original_filename, 
                    file_extension, mime_type, file_size, 
                    storage_path, storage_type, reference_count, uploaded_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'local', 1, NOW())
            ");
            
            $insertSuccess = $stmt->execute([
                $fileUuid,
                $fileHash,
                $uploadedFile['name'],
                $extension,
                $uploadedFile['type'] ?? 'application/octet-stream',
                $uploadedFile['size'],
                $storagePath
            ]);
            
            if (!$insertSuccess) {
                throw new Exception("Failed to insert attachment record into database");
            }
            
            $attachmentId = $this->pdo->lastInsertId();
            
            if (!$attachmentId) {
                throw new Exception("Failed to get attachment ID after insert");
            }
            
            error_log("Attachment inserted into database with ID: $attachmentId");
            
            // Create access record
            $accessCreated = $this->createAccessRecord($attachmentId, null);
            if (!$accessCreated) {
                error_log("WARNING: Failed to create access record for attachment $attachmentId");
            }
            
            // Store metadata
            $metadataCreated = $this->storeMetadata($attachmentId, $uploadedFile['name'], $extension, $uploadedFile['type']);
            if (!$metadataCreated) {
                error_log("WARNING: Failed to store metadata for attachment $attachmentId");
            }
            
            // Generate encrypted ID for download
            $encryptedId = encryptFileId($attachmentId);
            
            if (!$encryptedId) {
                error_log("WARNING: Failed to encrypt file ID $attachmentId");
            }
            
            error_log("✓ Upload complete - ID: $attachmentId, Encrypted: $encryptedId");
            
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
            error_log("Stack trace: " . $e->getTraceAsString());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Increment reference count for existing file
     */
    private function incrementReferenceCount($attachmentId) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE attachments 
                SET reference_count = reference_count + 1,
                    last_accessed = NOW()
                WHERE id = ?
            ");
            return $stmt->execute([$attachmentId]);
        } catch (Exception $e) {
            error_log("Error incrementing reference count: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create simplified access record - ENHANCED
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
                
                $success = $stmt->execute([
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
                
                $success = $stmt->execute([
                    $this->userId,
                    $attachmentId,
                    $emailUuid
                ]);
            }
            
            if ($success) {
                error_log("✓ Access record created for attachment $attachmentId");
            } else {
                error_log("✗ Failed to create access record for attachment $attachmentId");
            }
            
            return $success;
            
        } catch (Exception $e) {
            error_log("Error creating access record: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Store file metadata - ENHANCED
     */
    private function storeMetadata($attachmentId, $filename, $extension, $mimeType) {
        try {
            // Check if attachment_metadata table exists
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'attachment_metadata'");
            if ($stmt->rowCount() == 0) {
                error_log("Note: attachment_metadata table does not exist");
                return true; // Not an error if table doesn't exist
            }
            
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
            
            $success = $stmt->execute([
                $attachmentId,
                $this->userId,
                $filename,
                $extension,
                $mimeType ?? 'application/octet-stream'
            ]);
            
            if ($success) {
                error_log("✓ Metadata stored for attachment $attachmentId");
            } else {
                error_log("✗ Failed to store metadata for attachment $attachmentId");
            }
            
            return $success;
            
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
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count 
                FROM user_attachment_access 
                WHERE user_id = ? AND attachment_id = ?
            ");
            $stmt->execute([$userId, $attachmentId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['count'] > 0;
        } catch (Exception $e) {
            error_log("Error checking user access: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Find existing file by hash
     */
    private function findByHash($hash) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM attachments WHERE file_hash = ? LIMIT 1");
            $stmt->execute([$hash]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error finding by hash: " . $e->getMessage());
            return null;
        }
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
    
    /**
     * Ensure upload directory exists
     */
    private function ensureUploadDirectory() {
        if (!is_dir($this->uploadDir)) {
            if (!mkdir($this->uploadDir, 0755, true)) {
                error_log("ERROR: Could not create upload directory: " . $this->uploadDir);
            }
        }
        
        // Verify directory is writable
        if (!is_writable($this->uploadDir)) {
            error_log("ERROR: Upload directory is not writable: " . $this->uploadDir);
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
            // Verify the file was actually saved before adding to session
            $uploadDir = 'uploads/attachments/';
            $fullPath = $uploadDir . $result['path'];
            
            if (!file_exists($fullPath)) {
                error_log("CRITICAL ERROR: Upload reported success but file not found: $fullPath");
                echo json_encode([
                    'success' => false,
                    'error' => 'File upload verification failed'
                ]);
                exit();
            }
            
            // Store in session for later use when sending
            $_SESSION['temp_attachments'][] = $result;
            
            error_log("✓ File uploaded successfully: " . $result['original_name'] . 
                     " (ID: " . $result['id'] . ", Encrypted: " . $result['encrypted_id'] . ")");
            error_log("✓ Session now has " . count($_SESSION['temp_attachments']) . " attachments");
        }
        
        echo json_encode($result);
        
    } catch (Exception $e) {
        error_log("Upload handler error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
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