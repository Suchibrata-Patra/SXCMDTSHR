<?php
/**
 * FETCH EMAILS API ENDPOINT
 * Fetches emails from IMAP server and saves them to database
 */

session_start();
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

require_once 'db_config.php';
require_once 'settings_helper.php';
require_once 'imap_helper.php';

$userEmail = $_SESSION['smtp_user'];
$forceRefresh = isset($_POST['force_refresh']) && $_POST['force_refresh'] === '1';

// Ensure IMAP config is in session
if (!isset($_SESSION['imap_config'])) {
    $settings = getSettingsWithDefaults($userEmail);
    $_SESSION['imap_config'] = [
        'imap_server' => $settings['imap_server'] ?? 'imap.hostinger.com',
        'imap_port' => $settings['imap_port'] ?? '993',
        'imap_encryption' => $settings['imap_encryption'] ?? 'ssl',
        'imap_username' => $settings['imap_username'] ?? $userEmail,
        'imap_password' => $_SESSION['smtp_pass']
    ];
}

try {
    // Fetch messages
    $result = fetchNewMessagesFromSession($userEmail, 50, $forceRefresh);
    
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
            'error' => $result['error'] ?? 'Unknown error occurred',
            'count' => 0
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error in fetch_emails.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage(),
        'count' => 0
    ]);
}
?>