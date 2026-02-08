<?php
/**
 * Delete Inbox Message API
 * Soft deletes message (moves to trash)
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
    
    if (!isset($input['message_id'])) {
        echo json_encode([
            'success' => false,
            'error' => 'Missing message ID'
        ]);
        exit;
    }
    
    $messageId = intval($input['message_id']);
    
    // Delete message (soft delete)
    $result = deleteInboxMessage($messageId, $userEmail);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Message deleted successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Could not delete message'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error in delete_inbox_message.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while deleting message'
    ]);
}
?>
