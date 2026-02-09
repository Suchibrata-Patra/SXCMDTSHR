<?php
/**
 * Migration Status Checker
 * Returns stats about sent emails and tracking status
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['smtp_user'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once 'db_config.php';

$userEmail = $_SESSION['smtp_user'];

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit;
    }
    
    // Get total sent emails
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM sent_emails 
        WHERE sender_email = ? 
        AND current_status = 1
    ");
    $stmt->execute([$userEmail]);
    $totalResult = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalEmails = $totalResult['count'] ?? 0;
    
    // Get emails without tracking
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM sent_emails 
        WHERE sender_email = ? 
        AND current_status = 1 
        AND tracking_token IS NULL
    ");
    $stmt->execute([$userEmail]);
    $withoutResult = $stmt->fetch(PDO::FETCH_ASSOC);
    $withoutTracking = $withoutResult['count'] ?? 0;
    
    // Get emails with tracking
    $withTracking = $totalEmails - $withoutTracking;
    
    echo json_encode([
        'success' => true,
        'total_emails' => $totalEmails,
        'with_tracking' => $withTracking,
        'without_tracking' => $withoutTracking,
        'completion_percentage' => $totalEmails > 0 ? round(($withTracking / $totalEmails) * 100, 2) : 0
    ]);
    
} catch (PDOException $e) {
    error_log("Error getting migration status: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>