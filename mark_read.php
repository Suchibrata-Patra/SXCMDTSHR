<?php
/**
 * Mark Message Read/Unread API
 * Updates message read status instantly
 */

session_start();
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized'
    ]);
    exit;
}

require_once 'db_config.php';

$userEmail = $_SESSION['smtp_user'];

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['message_id']) || !isset($input['action'])) {
        echo json_encode([
            'success' => false,
            'error' => 'Missing required parameters'
        ]);
        exit;
    }
    
    $messageId = intval($input['message_id']);
    $action = $input['action']; // 'read' or 'unread'
    
    // Validate action
    if (!in_array($action, ['read', 'unread'])) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid action'
        ]);
        exit;
    }
    
    // Update message status
    if ($action === 'read') {
        $result = markMessageAsRead($messageId, $userEmail);
        $message = 'Message marked as read';
    } else {
        $result = markMessageAsUnread($messageId, $userEmail);
        $message = 'Message marked as unread';
    }
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => $message
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Could not update message status'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error in mark_read.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while updating message'
    ]);
}
?>
