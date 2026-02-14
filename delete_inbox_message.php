<?php
/**
 * DELETE INBOX MESSAGE API
 * Marks a message as deleted (soft delete)
 */
require_once __DIR__ . '/security_handler.php';
session_start();
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

require_once 'db_config.php';

$userEmail = $_SESSION['smtp_user'];

// Get JSON input
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
    
    // Soft delete: Update is_deleted flag
    $stmt = $pdo->prepare("
        UPDATE inbox_messages 
        SET is_deleted = 1, 
            deleted_at = NOW()
        WHERE id = :id 
        AND user_email = :user_email 
        AND is_deleted = 0
    ");
    
    $result = $stmt->execute([
        ':id' => $messageId,
        ':user_email' => $userEmail
    ]);
    
    if ($result && $stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true, 
            'message' => 'Message deleted successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'error' => 'Message not found or already deleted'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error deleting message: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Server error occurred'
    ]);
}
?>