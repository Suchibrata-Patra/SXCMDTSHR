<?php
// bulk_actions.php - Handle bulk operations on emails
session_start();
require 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$userEmail = $_SESSION['smtp_user'];
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'bulk_delete':
        handleBulkDelete($userEmail);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        break;
}

/**
 * Handle bulk delete operation by setting current_status = 0
 */
function handleBulkDelete($userEmail) {
    try {
        $emailIdsJson = $_POST['email_ids'] ?? '[]';
        $emailIds = json_decode($emailIdsJson, true);
        
        if (!is_array($emailIds) || empty($emailIds)) {
            echo json_encode(['success' => false, 'message' => 'No emails selected']);
            return;
        }
        
        // Validate all IDs are integers
        $emailIds = array_map('intval', $emailIds);
        $emailIds = array_filter($emailIds, function($id) { return $id > 0; });
        
        if (empty($emailIds)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email IDs']);
            return;
        }
        
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
            return;
        }
        
        // Update current_status to 0 (deleted) for selected emails
        // Only allow deletion of emails belonging to the current user
        $placeholders = str_repeat('?,', count($emailIds) - 1) . '?';
        $sql = "UPDATE sent_emails 
                SET current_status = 0 
                WHERE id IN ($placeholders) 
                AND sender_email = ?";
        
        $params = array_merge($emailIds, [$userEmail]);
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);
        
        if ($result) {
            $affectedRows = $stmt->rowCount();
            echo json_encode([
                'success' => true, 
                'message' => "$affectedRows email(s) moved to trash",
                'affected_rows' => $affectedRows
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete emails']);
        }
        
    } catch (PDOException $e) {
        error_log("Bulk delete error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    } catch (Exception $e) {
        error_log("Bulk delete error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred']);
    }
}
?>