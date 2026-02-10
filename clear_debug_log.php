<?php
/**
 * Clear Debug Log
 * Empties the tracking_debug.log file
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['smtp_user'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$logFile = __DIR__ . '/tracking_debug.log';

try {
    if (file_exists($logFile)) {
        file_put_contents($logFile, '');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Debug log cleared'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>