<?php
/**
 * GET MESSAGE API - OPTIMIZED
 * Fetches a single message by ID with FULL body
 */

session_start();
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

require_once 'db_config.php';
require_once 'inbox_functions.php';

$userEmail = $_SESSION['smtp_user'];
$messageId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($messageId === 0) {
    echo json_encode(['error' => 'Invalid message ID']);
    exit();
}

try {
    // Use the optimized function to get full message
    $message = getInboxMessageById($messageId, $userEmail);
    
    if ($message) {
        echo json_encode($message);
    } else {
        echo json_encode(['error' => 'Message not found']);
    }
    
} catch (Exception $e) {
    error_log("Error fetching message: " . $e->getMessage());
    echo json_encode(['error' => 'Server error occurred']);
}
?>