<?php
/**
 * GET MESSAGE API
 * Returns message details as JSON
 */

session_start();
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

require_once 'db_config.php';

$userEmail = $_SESSION['smtp_user'];
$messageId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($messageId === 0) {
    echo json_encode(['error' => 'Invalid message ID']);
    exit();
}

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        echo json_encode(['error' => 'Database connection failed']);
        exit();
    }
    
    $stmt = $pdo->prepare("
        SELECT * FROM inbox_messages 
        WHERE id = :id AND user_email = :user_email
    ");
    $stmt->execute([
        ':id' => $messageId,
        ':user_email' => $userEmail
    ]);
    
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$message) {
        echo json_encode(['error' => 'Message not found']);
        exit();
    }
    
    echo json_encode($message);
    
} catch (Exception $e) {
    error_log("Error fetching message: " . $e->getMessage());
    echo json_encode(['error' => 'Server error occurred']);
}
?>