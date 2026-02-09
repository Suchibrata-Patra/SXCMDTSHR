<?php
/**
 * MARK AS READ API
 * Marks a message as read
 */

session_start();
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

require_once 'db_config.php';

$userEmail = $_SESSION['smtp_user'];

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$messageId = isset($input['message_id']) ? intval($input['message_id']) : 0;

if ($messageId === 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid message ID']);
    exit();
}

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit();
    }
    
    $stmt = $pdo->prepare("
        UPDATE inbox_messages 
        SET is_read = 1 
        WHERE id = :id AND user_email = :user_email
    ");
    
    $success = $stmt->execute([
        ':id' => $messageId,
        ':user_email' => $userEmail
    ]);
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Message marked as read']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update message']);
    }
    
} catch (Exception $e) {
    error_log("Error marking message as read: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error occurred']);
}
?>