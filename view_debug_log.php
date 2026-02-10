<?php
/**
 * View Debug Log
 * Returns the last N lines of tracking_debug.log
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['smtp_user'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$logFile = __DIR__ . '/tracking_debug.log';

try {
    if (!file_exists($logFile)) {
        echo json_encode([
            'success' => true,
            'log_content' => 'Log file does not exist yet. It will be created when the tracking pixel is accessed.'
        ]);
        exit;
    }
    
    // Read last 100 lines
    $lines = file($logFile);
    $lastLines = array_slice($lines, -100);
    $logContent = implode('', $lastLines);
    
    echo json_encode([
        'success' => true,
        'log_content' => $logContent,
        'total_lines' => count($lines)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>