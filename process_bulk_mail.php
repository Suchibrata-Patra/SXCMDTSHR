<?php
/**
 * Bulk Mail Queue Processor
 * 
 * Handles queue operations for the bulk email system
 * Uses bulk_mail_queue table from u955994755_SXC_MDTS database
 */

session_start();
require_once 'db_config.php';

// Set JSON header
header('Content-Type: application/json');

// Get action from request
$action = $_GET['action'] ?? '';

try {
    $pdo = getDatabaseConnection();
    
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Get current user from session
    $user_email = $_SESSION['smtp_user'] ?? null;
    
    // Get user ID if user is logged in
    $user_id = null;
    if ($user_email) {
        $user_id = getUserId($pdo, $user_email);
    }
    
    switch ($action) {
        case 'test':
            // Test endpoint - check session and database
            echo json_encode([
                'success' => true,
                'user_email' => $user_email,
                'user_id' => $user_id,
                'session_active' => isset($_SESSION['smtp_user']),
                'php_version' => phpversion(),
                'database_connected' => true,
                'database_name' => 'u955994755_SXC_MDTS',
                'table_name' => 'bulk_mail_queue'
            ]);
            break;
            
        case 'status':
            // Get queue status counts
            if (!$user_id) {
                throw new Exception('User not logged in or user not found');
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    status,
                    COUNT(*) as count
                FROM bulk_mail_queue
                WHERE user_id = ?
                GROUP BY status
            ");
            $stmt->execute([$user_id]);
            $results = $stmt->fetchAll();
            
            $counts = [
                'pending' => 0,
                'processing' => 0,
                'completed' => 0,
                'failed' => 0,
                'total' => 0
            ];
            
            foreach ($results as $row) {
                $counts[$row['status']] = (int)$row['count'];
                $counts['total'] += (int)$row['count'];
            }
            
            echo json_encode([
                'success' => true,
                'pending' => $counts['pending'],
                'processing' => $counts['processing'],
                'completed' => $counts['completed'],
                'failed' => $counts['failed'],
                'total' => $counts['total']
            ]);
            break;
            
        case 'queue_list':
            // Get list of emails in queue
            if (!$user_id) {
                throw new Exception('User not logged in or user not found');
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    id,
                    batch_uuid,
                    recipient_email,
                    recipient_name,
                    subject,
                    article_title,
                    status,
                    error_message,
                    created_at,
                    processing_started_at,
                    completed_at
                FROM bulk_mail_queue
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT 100
            ");
            $stmt->execute([$user_id]);
            $queue = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'queue' => $queue,
                'count' => count($queue)
            ]);
            break;
            
        case 'process':
            // Process next email in queue
            if (!$user_id) {
                throw new Exception('User not logged in or user not found');
            }
            
            // Get next pending email
            $stmt = $pdo->prepare("
                SELECT * FROM bulk_mail_queue
                WHERE user_id = ? AND status = 'pending'
                ORDER BY created_at ASC
                LIMIT 1
            ");
            $stmt->execute([$user_id]);
            $email = $stmt->fetch();
            
            if (!$email) {
                echo json_encode([
                    'success' => false,
                    'error' => 'No pending emails in queue'
                ]);
                break;
            }
            
            // Update status to processing
            $updateStmt = $pdo->prepare("
                UPDATE bulk_mail_queue
                SET status = 'processing',
                    processing_started_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$email['id']]);
            
            // TODO: Integrate with your actual email sending logic
            // For now, this is a placeholder that simulates email sending
            $success = sendBulkEmail($email, $user_email);
            
            // Update final status
            if ($success['success']) {
                $finalStmt = $pdo->prepare("
                    UPDATE bulk_mail_queue
                    SET status = 'completed',
                        sent_email_id = ?,
                        completed_at = NOW()
                    WHERE id = ?
                ");
                $finalStmt->execute([
                    $success['email_id'] ?? null,
                    $email['id']
                ]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Email sent successfully',
                    'email_id' => $success['email_id']
                ]);
            } else {
                $errorStmt = $pdo->prepare("
                    UPDATE bulk_mail_queue
                    SET status = 'failed', 
                        error_message = ?,
                        completed_at = NOW()
                    WHERE id = ?
                ");
                $errorStmt->execute([
                    $success['error'] ?? 'Failed to send email',
                    $email['id']
                ]);
                
                echo json_encode([
                    'success' => false,
                    'error' => $success['error'] ?? 'Failed to send email'
                ]);
            }
            break;
            
        case 'clear':
            // Clear all pending emails from queue
            if (!$user_id) {
                throw new Exception('User not logged in or user not found');
            }
            
            $stmt = $pdo->prepare("
                DELETE FROM bulk_mail_queue
                WHERE user_id = ? AND status = 'pending'
            ");
            $stmt->execute([$user_id]);
            $deleted = $stmt->rowCount();
            
            echo json_encode([
                'success' => true,
                'message' => "Cleared {$deleted} pending emails from queue",
                'deleted_count' => $deleted
            ]);
            break;
            
        case 'delete_batch':
            // Delete all emails from a specific batch
            if (!$user_id) {
                throw new Exception('User not logged in or user not found');
            }
            
            $batch_uuid = $_POST['batch_uuid'] ?? null;
            if (!$batch_uuid) {
                throw new Exception('Batch UUID is required');
            }
            
            $stmt = $pdo->prepare("
                DELETE FROM bulk_mail_queue
                WHERE user_id = ? AND batch_uuid = ?
            ");
            $stmt->execute([$user_id, $batch_uuid]);
            $deleted = $stmt->rowCount();
            
            echo json_encode([
                'success' => true,
                'message' => "Deleted {$deleted} emails from batch",
                'deleted_count' => $deleted
            ]);
            break;
            
        case 'batch_status':
            // Get status for a specific batch
            if (!$user_id) {
                throw new Exception('User not logged in or user not found');
            }
            
            $batch_uuid = $_GET['batch_uuid'] ?? null;
            if (!$batch_uuid) {
                throw new Exception('Batch UUID is required');
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    status,
                    COUNT(*) as count
                FROM bulk_mail_queue
                WHERE user_id = ? AND batch_uuid = ?
                GROUP BY status
            ");
            $stmt->execute([$user_id, $batch_uuid]);
            $results = $stmt->fetchAll();
            
            $counts = [
                'pending' => 0,
                'processing' => 0,
                'completed' => 0,
                'failed' => 0,
                'total' => 0
            ];
            
            foreach ($results as $row) {
                $counts[$row['status']] = (int)$row['count'];
                $counts['total'] += (int)$row['count'];
            }
            
            echo json_encode([
                'success' => true,
                'batch_uuid' => $batch_uuid,
                'pending' => $counts['pending'],
                'processing' => $counts['processing'],
                'completed' => $counts['completed'],
                'failed' => $counts['failed'],
                'total' => $counts['total']
            ]);
            break;
            
        default:
            throw new Exception('Invalid action: ' . $action);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Send email using your existing PHPMailer setup
 * This is a placeholder - integrate with your actual send_email.php logic
 */
function sendBulkEmail($emailData, $userEmail) {
    try {
        // TODO: Replace this with your actual PHPMailer implementation
        // You should integrate with your existing send_email.php logic here
        
        // Simulate email sending for now
        usleep(100000); // Simulate processing time
        
        // You would normally call your PHPMailer code here
        // Example:
        // require_once 'send_email.php';
        // $result = sendEmail($emailData);
        
        // For now, return success with a fake email_id
        return [
            'success' => true,
            'email_id' => null // Would be the ID from saveEmailToDatabase()
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
?>