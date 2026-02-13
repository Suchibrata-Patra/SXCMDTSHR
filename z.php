<?php
/**
 * Debug script to test drive file loading
 * Upload this to your server and access it directly
 */

// Database configuration (adjust as needed)
define('DRIVE_DIR', '/home/u955994755/domains/holidayseva.com/public_html/SXC_MDTS/File_Drive');

header('Content-Type: application/json');

echo "=== Drive Files Debug ===\n\n";

// 1. Check if directory exists
echo "1. Directory check:\n";
echo "   Path: " . DRIVE_DIR . "\n";
echo "   Exists: " . (is_dir(DRIVE_DIR) ? "YES ✓" : "NO ✗") . "\n";
echo "   Readable: " . (is_readable(DRIVE_DIR) ? "YES ✓" : "NO ✗") . "\n\n";

if (!is_dir(DRIVE_DIR)) {
    echo "ERROR: Directory does not exist!\n";
    echo "Create the directory or check the path in bulk_mail_backend.php\n";
    exit;
}

// 2. Try to read directory
echo "2. Directory contents:\n";
try {
    $items = scandir(DRIVE_DIR);
    echo "   Total items: " . count($items) . "\n";
    
    $files = [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        
        $fullPath = DRIVE_DIR . '/' . $item;
        
        if (is_file($fullPath)) {
            $size = filesize($fullPath);
            echo "   - " . $item . " (" . formatBytes($size) . ")\n";
            
            $files[] = [
                'name' => $item,
                'path' => $fullPath,
                'size' => $size,
                'formatted_size' => formatBytes($size),
                'extension' => strtolower(pathinfo($item, PATHINFO_EXTENSION))
            ];
        } else {
            echo "   - " . $item . " [DIRECTORY]\n";
        }
    }
    
    echo "\n3. Files found: " . count($files) . "\n\n";
    
    // 4. JSON output (what the backend returns)
    echo "4. JSON Response:\n";
    $response = [
        'success' => true,
        'files' => $files,
        'directory' => DRIVE_DIR,
        'count' => count($files)
    ];
    echo json_encode($response, JSON_PRETTY_PRINT) . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

function formatBytes($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
?>