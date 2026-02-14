#!/usr/bin/php
<?php
/**
 * process_deletion_queue.php
 * 
 * Cron job script to process deletion queue
 * - Deletes emails from IMAP server using UID
 * - Removes attachment files when reference count reaches 0
 * - Permanently deletes email records from database
 * 
 * Usage: Add to crontab to run every hour or as needed
 * Example: 0 * * * * /usr/bin/php /path/to/process_deletion_queue.php >> /var/log/deletion_queue.log 2>&1
 */
require_once(__DIR__ . '/db_config.php');
require_once(__DIR__ . '/config.php'); // For IMAP credentials

// Logging function
function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] [{$level}] {$message}\n";
}

// IMAP connection cache
$imapConnections = [];

/**
 * Get or create IMAP connection
 */
function getImapConnection($userEmail, $imapServer, $imapPort, $password) {
    global $imapConnections;
    
    $key = $userEmail;
    
    if (isset($imapConnections[$key]) && $imapConnections[$key]) {
        // Check if connection is still alive
        if (imap_ping($imapConnections[$key])) {
            return $imapConnections[$key];
        }
    }
    
    // Create new connection
    $mailbox = "{{$imapServer}:{$imapPort}/imap/ssl}INBOX";
    
    try {
        $connection = imap_open($mailbox, $userEmail, $password);
        if ($connection) {
            $imapConnections[$key] = $connection;
            logMessage("IMAP connection established for {$userEmail}");
            return $connection;
        }
    } catch (Exception $e) {
        logMessage("Failed to connect to IMAP for {$userEmail}: " . $e->getMessage(), 'ERROR');
        return false;
    }
    
    return false;
}

/**
 * Delete email from IMAP server using UID
 */
function deleteEmailFromImap($userEmail, $imapUid, $mailbox = 'INBOX') {
    // IMAP credentials - you should configure these per user or globally
    $imapServer = 'mail.holidayseva.com'; // Change to your IMAP server
    $imapPort = 993;
    
    // Get password from user settings or config
    // For security, you might want to store encrypted passwords or use app passwords
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        logMessage("Database connection failed", 'ERROR');
        return false;
    }
    
    $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_email = :email AND setting_key = 'imap_password'");
    $stmt->execute([':email' => $userEmail]);
    $result = $stmt->fetch();
    
    if (!$result) {
        logMessage("No IMAP password found for {$userEmail}", 'ERROR');
        return false;
    }
    
    $password = $result['setting_value'];
    
    $imap = getImapConnection($userEmail, $imapServer, $imapPort, $password);
    
    if (!$imap) {
        return false;
    }
    
    try {
        // Switch to correct mailbox if needed
        $currentMailbox = "{{$imapServer}:{$imapPort}/imap/ssl}{$mailbox}";
        imap_reopen($imap, $currentMailbox);
        
        // Delete by UID
        $result = imap_delete($imap, $imapUid, FT_UID);
        
        if ($result) {
            // Expunge to permanently delete
            imap_expunge($imap);
            logMessage("Successfully deleted email UID {$imapUid} from IMAP for {$userEmail}");
            return true;
        } else {
            logMessage("Failed to delete email UID {$imapUid} from IMAP: " . imap_last_error(), 'ERROR');
            return false;
        }
        
    } catch (Exception $e) {
        logMessage("Error deleting from IMAP: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Delete attachment file from filesystem
 */
function deleteAttachmentFile($filePath) {
    $fullPath = '/var/www/html/' . $filePath;
    
    if (file_exists($fullPath)) {
        if (unlink($fullPath)) {
            logMessage("Successfully deleted file: {$filePath}");
            
            // Try to remove parent directory if empty
            $parentDir = dirname($fullPath);
            if (is_dir($parentDir) && count(scandir($parentDir)) == 2) { // . and ..
                rmdir($parentDir);
                logMessage("Removed empty directory: " . dirname($filePath));
            }
            
            return true;
        } else {
            logMessage("Failed to delete file: {$filePath}", 'ERROR');
            return false;
        }
    } else {
        logMessage("File not found: {$filePath}", 'WARNING');
        return true; // Consider it deleted if it doesn't exist
    }
}

/**
 * Process email deletion from queue
 */
function processEmailDeletion($queueItem) {
    logMessage("Processing email deletion: Queue ID {$queueItem['id']}, Email ID {$queueItem['item_id']}");
    
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return false;
    }
    
    // Update status to processing
    updateDeletionQueueStatus($queueItem['id'], 'processing');
    
    try {
        // Step 1: Delete from IMAP if UID is present
        if ($queueItem['imap_uid'] && $queueItem['user_email']) {
            $imapDeleted = deleteEmailFromImap(
                $queueItem['user_email'],
                $queueItem['imap_uid'],
                $queueItem['imap_mailbox'] ?? 'INBOX'
            );
            
            if (!$imapDeleted) {
                // IMAP deletion failed, but we'll still try database deletion
                logMessage("IMAP deletion failed for UID {$queueItem['imap_uid']}, continuing with database deletion", 'WARNING');
            }
        }
        
        // Step 2: Get all attachments for this email before deleting
        $stmt = $pdo->prepare(
            "SELECT a.id, a.storage_path, a.reference_count 
             FROM attachments a
             INNER JOIN email_attachments ea ON a.id = ea.attachment_id
             WHERE ea.email_id = :email_id"
        );
        $stmt->execute([':email_id' => $queueItem['item_id']]);
        $attachments = $stmt->fetchAll();
        
        // Step 3: Delete email from database (cascades to related tables)
        if (permanentlyDeleteEmail($queueItem['item_id'])) {
            logMessage("Successfully deleted email {$queueItem['item_id']} from database");
            
            // Step 4: Check if any attachments need to be deleted
            foreach ($attachments as $attachment) {
                // Re-check reference count (might have changed)
                $stmt = $pdo->prepare("SELECT reference_count FROM attachments WHERE id = :id");
                $stmt->execute([':id' => $attachment['id']]);
                $current = $stmt->fetch();
                
                if ($current && $current['reference_count'] == 0) {
                    // Queue attachment for deletion
                    $stmt = $pdo->prepare(
                        "INSERT INTO deletion_queue 
                        (item_type, item_id, file_path, scheduled_for) 
                        VALUES ('attachment', :item_id, :file_path, NOW())"
                    );
                    $stmt->execute([
                        ':item_id' => $attachment['id'],
                        ':file_path' => $attachment['storage_path']
                    ]);
                    logMessage("Queued attachment {$attachment['id']} for deletion");
                }
            }
            
            // Mark queue item as completed
            updateDeletionQueueStatus($queueItem['id'], 'completed');
            return true;
            
        } else {
            throw new Exception("Failed to delete email from database");
        }
        
    } catch (Exception $e) {
        logMessage("Error processing email deletion: " . $e->getMessage(), 'ERROR');
        updateDeletionQueueStatus($queueItem['id'], 'failed', $e->getMessage());
        return false;
    }
}

/**
 * Process attachment deletion from queue
 */
function processAttachmentDeletion($queueItem) {
    logMessage("Processing attachment deletion: Queue ID {$queueItem['id']}, Attachment ID {$queueItem['item_id']}");
    
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return false;
    }
    
    // Update status to processing
    updateDeletionQueueStatus($queueItem['id'], 'processing');
    
    try {
        // Step 1: Verify reference count is still 0
        $stmt = $pdo->prepare("SELECT reference_count, storage_path FROM attachments WHERE id = :id");
        $stmt->execute([':id' => $queueItem['item_id']]);
        $attachment = $stmt->fetch();
        
        if (!$attachment) {
            logMessage("Attachment {$queueItem['item_id']} not found in database, marking as completed");
            updateDeletionQueueStatus($queueItem['id'], 'completed');
            return true;
        }
        
        if ($attachment['reference_count'] > 0) {
            logMessage("Attachment {$queueItem['item_id']} still has {$attachment['reference_count']} references, skipping deletion");
            updateDeletionQueueStatus($queueItem['id'], 'completed', 'Reference count is not zero');
            return true;
        }
        
        // Step 2: Delete file from filesystem
        $filePath = $queueItem['file_path'] ?? $attachment['storage_path'];
        $fileDeleted = deleteAttachmentFile($filePath);
        
        // Step 3: Delete attachment record from database
        $stmt = $pdo->prepare("DELETE FROM attachments WHERE id = :id");
        if ($stmt->execute([':id' => $queueItem['item_id']])) {
            logMessage("Successfully deleted attachment {$queueItem['item_id']} from database");
            updateDeletionQueueStatus($queueItem['id'], 'completed');
            return true;
        } else {
            throw new Exception("Failed to delete attachment from database");
        }
        
    } catch (Exception $e) {
        logMessage("Error processing attachment deletion: " . $e->getMessage(), 'ERROR');
        updateDeletionQueueStatus($queueItem['id'], 'failed', $e->getMessage());
        return false;
    }
}

/**
 * Main execution
 */
function main() {
    logMessage("=== Deletion Queue Processing Started ===");
    
    $batchSize = 50; // Process 50 items per run
    $queueItems = getPendingDeletionQueueItems($batchSize);
    
    if (empty($queueItems)) {
        logMessage("No items in deletion queue");
        return;
    }
    
    logMessage("Found " . count($queueItems) . " items to process");
    
    $successCount = 0;
    $failCount = 0;
    
    foreach ($queueItems as $item) {
        // Check for failed attempts limit
        if ($item['attempts'] >= 5) {
            logMessage("Queue item {$item['id']} has failed {$item['attempts']} times, marking as failed permanently", 'WARNING');
            updateDeletionQueueStatus($item['id'], 'failed', 'Max retry attempts reached');
            $failCount++;
            continue;
        }
        
        $success = false;
        
        if ($item['item_type'] === 'email') {
            $success = processEmailDeletion($item);
        } elseif ($item['item_type'] === 'attachment') {
            $success = processAttachmentDeletion($item);
        } else {
            logMessage("Unknown item type: {$item['item_type']}", 'ERROR');
            updateDeletionQueueStatus($item['id'], 'failed', 'Unknown item type');
        }
        
        if ($success) {
            $successCount++;
        } else {
            $failCount++;
        }
        
        // Small delay to prevent overwhelming the server
        usleep(100000); // 0.1 second
    }
    
    // Close all IMAP connections
    global $imapConnections;
    foreach ($imapConnections as $conn) {
        if ($conn) {
            imap_close($conn);
        }
    }
    
    logMessage("Processing completed: {$successCount} succeeded, {$failCount} failed");
    logMessage("=== Deletion Queue Processing Finished ===\n");
}

// Run the script
main();

?>