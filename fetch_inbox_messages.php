<?php
/**
 * Fetch Inbox Messages API
 * Syncs new emails from IMAP server to database
 * 
 * UPDATED: Now uses session-based IMAP configuration
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
require_once 'settings_helper.php';
require_once 'imap_helper.php';

$userEmail = $_SESSION['smtp_user'];

try {
    // Check if IMAP is configured in session
    $imapConfig = getImapConfigFromSession();
    
    if (!$imapConfig) {
        echo json_encode([
            'success' => false,
            'error' => 'IMAP not configured. Please configure your mail settings first.'
        ]);
        exit;
    }
    
    // Fetch new messages using session configuration (limit to 50 per sync to prevent timeout)
    $result = fetchNewMessagesFromSession($userEmail, 50);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => $result['message'],
            'count' => $result['count'],
            'total' => $result['total'] ?? 0
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => $result['error'] ?? 'Could not fetch messages'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error in fetch_inbox_messages.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while syncing messages'
    ]);
}
?>
