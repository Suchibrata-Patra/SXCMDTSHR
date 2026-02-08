<?php
// bulk_trash_actions.php - Handle bulk restore and permanent delete operations
session_start();
require 'config.php';
require 'db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$userEmail = $_SESSION['smtp_user'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$action = $_POST['action'] ?? '';
$emailIds = json_decode($_POST['email_ids'] ?? '[]', true);

if (empty($emailIds) || !is_array($emailIds)) {
    echo json_encode(['success' => false, 'message' => 'No email IDs provided']);
    exit();
}


// Sanitize email IDs
$emailIds = array_map('intval', $emailIds);
$emailIds = array_filter($emailIds, function($id) { return $id > 0; });

if (empty($emailIds)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email IDs']);
    exit();
}

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    switch ($action) {
        case 'bulk_restore':
            // Restore emails: Change current_status from 0 to 1
            $placeholders = implode(',', array_fill(0, count($emailIds), '?'));
            $sql = "UPDATE sent_emails 
                    SET current_status = 1 
                    WHERE id IN ($placeholders) 
                    AND sender_email = ? 
                    AND current_status = 0";
            
            $stmt = $pdo->prepare($sql);
            $params = array_merge($emailIds, [$userEmail]);
            $stmt->execute($params);
            
            $affectedRows = $stmt->rowCount();
            
            echo json_encode([
                'success' => true,
                'message' => "Successfully restored $affectedRows email(s)",
                'affected_rows' => $affectedRows
            ]);
            break;

        case 'bulk_delete_forever':
            // Permanent delete: Change current_status from 0 to 2
            $placeholders = implode(',', array_fill(0, count($emailIds), '?'));
            $sql = "UPDATE sent_emails 
                    SET current_status = 2 
                    WHERE id IN ($placeholders) 
                    AND sender_email = ? 
                    AND current_status = 0";
            
            $stmt = $pdo->prepare($sql);
            $params = array_merge($emailIds, [$userEmail]);
            $stmt->execute($params);
            
            $affectedRows = $stmt->rowCount();
            
            echo json_encode([
                'success' => true,
                'message' => "Successfully deleted $affectedRows email(s) forever",
                'affected_rows' => $affectedRows
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }

} catch (Exception $e) {
    error_log("Bulk trash action error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}
?>