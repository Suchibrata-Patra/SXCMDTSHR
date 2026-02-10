<?php
/**
 * Generate Test Token
 * Creates a test tracking record for diagnostic purposes
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['smtp_user'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once 'db_config.php';
require_once 'read_tracking_helper.php';

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit;
    }
    
    // Generate test token
    $testToken = generateTrackingToken();
    $userEmail = $_SESSION['smtp_user'];
    
    // Insert test tracking record
    $stmt = $pdo->prepare("
        INSERT INTO email_read_tracking (
            email_id, tracking_token, sender_email, recipient_email,
            is_read, total_opens, valid_opens, created_at
        ) VALUES (?, ?, ?, ?, 0, 0, 0, NOW())
    ");
    
    // Use email_id = 0 for test records
    $stmt->execute([0, $testToken, $userEmail, 'test@example.com']);
    
    // Build pixel URL
    $pixelUrl = buildTrackingPixelUrl($testToken);
    
    echo json_encode([
        'success' => true,
        'tracking_token' => $testToken,
        'pixel_url' => $pixelUrl
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>