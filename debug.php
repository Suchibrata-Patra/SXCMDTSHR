<?php
/**
 * Check Read Status Endpoint
 * Returns read statuses for multiple emails
 * Used by sent_history.php for real-time updates
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once 'db_config.php';
require_once 'read_tracking_helper.php';

$userEmail = $_SESSION['smtp_user'];

// Get input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['tokens']) || !is_array($data['tokens'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$trackingTokens = $data['tokens'];

if (empty($trackingTokens)) {
    echo json_encode(['success' => true, 'read_statuses' => []]);
    exit;
}

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit;
    }
    
    // Build IN clause with placeholders
    $placeholders = str_repeat('?,', count($trackingTokens) - 1) . '?';
    
    // Query read statuses
    $sql = "
        SELECT 
            tracking_token,
            is_read,
            first_read_at,
            total_opens,
            valid_opens,
            device_type,
            browser,
            os,
            ip_address,
            country,
            city
        FROM email_read_tracking
        WHERE tracking_token IN ($placeholders)
        AND sender_email = ?
    ";
    
    $stmt = $pdo->prepare($sql);
    
    // Bind tracking tokens
    $bindParams = $trackingTokens;
    $bindParams[] = $userEmail; // Add sender email
    
    $stmt->execute($bindParams);
    
    $readStatuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'read_statuses' => $readStatuses,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (PDOException $e) {
    error_log("Error checking read statuses: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
}
?>