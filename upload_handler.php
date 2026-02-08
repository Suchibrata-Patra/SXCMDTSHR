<?php
// upload_handler.php - AJAX File Upload Handler with Progress Tracking
session_start();

// Security check
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

require 'db_config.php';

header('Content-Type: application/json');

// Configuration
define('MAX_FILE_SIZE', 20 * 1024 * 1024); // 20MB per file
define('MAX_TOTAL_SIZE', 25 * 1024 * 1024); // 25MB total
define('UPLOAD_DIR', 'uploads/attachments/');

class UploadHandler {
    private $pdo;
    private $uploadDir;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->uploadDir = UPLOAD_DIR;
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
                'jpg', 'jpeg', 'png', 'gif', 'webp',
                'zip', 'rar', '7z',
                'txt', 'csv', 'json', 'xml'
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
                // File already exists - just increment reference count
                $this->incrementReference($existing['id']);
                
                return [
                    'success' => true,
                    'id' => $existing['id'],
                    'path' => $this->uploadDir . $existing['storage_path'],
                    'original_name' => $uploadedFile['name'],
                    'file_size' => $uploadedFile['size'],
                    'formatted_size' => $this->formatBytes($uploadedFile['size']),
                    'extension' => $extension,
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
                    storage_path, storage_type, reference_count
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'local', 1)
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
            
            return [
                'success' => true,
                'id' => $attachmentId,
                'path' => $storagePath,
                'original_name' => $uploadedFile['name'],
                'file_size' => $uploadedFile['size'],
                'formatted_size' => $this->formatBytes($uploadedFile['size']),
                'extension' => $extension,
                'deduplicated' => false
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
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
     * Increment reference count
     */
    private function incrementReference($attachmentId) {
        $stmt = $this->pdo->prepare("
            UPDATE attachments 
            SET reference_count = reference_count + 1,
                last_accessed = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$attachmentId]);
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
        // Check if we already have session attachments array
        if (!isset($_SESSION['temp_attachments'])) {
            $_SESSION['temp_attachments'] = [];
        }
        
        // Calculate current total size
        $currentTotalSize = array_sum(array_column($_SESSION['temp_attachments'], 'file_size'));
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
        
        $handler = new UploadHandler($pdo);
        $result = $handler->processUpload($_FILES['file']);
        
        if ($result['success']) {
            // Store in session for later use
            $_SESSION['temp_attachments'][] = $result;
        }
        
        echo json_encode($result);
        
    } catch (Exception $e) {
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