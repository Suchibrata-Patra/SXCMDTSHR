<?php
/**
 * Run Migration Batch
 * Processes a batch of emails and adds tracking
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['smtp_user'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once 'db_config.php';
require_once 'read_tracking_helper.php';
require_once 'email_tracking_integration.php';

$userEmail = $_SESSION['smtp_user'];

// Get input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$batchSize = isset($data['batch_size']) ? (int)$data['batch_size'] : 100;
$batchSize = min(500, max(10, $batchSize)); // Limit between 10-500

try {
    $result = batchAddTrackingToSentEmails($userEmail, $batchSize);
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Error running migration batch: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>