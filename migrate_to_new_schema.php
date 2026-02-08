<?php
/**
 * migrate_to_new_schema.php
 * 
 * Migration script to convert data from old schema to new redesigned schema
 * 
 * IMPORTANT: 
 * 1. Backup your database before running this script
 * 2. Run this script only once
 * 3. Test on a staging environment first
 * 
 * Usage: php migrate_to_new_schema.php
 */

require_once(__DIR__ . '/db_config.php'); // Old config
require_once(__DIR__ . '/db_config_REDESIGNED.php'); // New config

// Configuration
define('BATCH_SIZE', 100);
define('DRY_RUN', false); // Set to true to test without making changes

// Logging
function logMigration($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] [{$level}] {$message}\n";
    
    // Also log to file
    file_put_contents(__DIR__ . '/migration.log', "[{$timestamp}] [{$level}] {$message}\n", FILE_APPEND);
}

/**
 * Step 1: Create users from inbox_messages and sent_emails
 */
function migrateUsers() {
    logMigration("=== Step 1: Migrating Users ===");
    
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        logMigration("Database connection failed", 'ERROR');
        return false;
    }
    
    try {
        // Get unique emails from both tables
        $sql = "
            SELECT DISTINCT user_email as email, NULL as full_name FROM inbox_messages
            UNION
            SELECT DISTINCT sender_email as email, NULL as full_name FROM sent_emails
            ORDER BY email
        ";
        
        $stmt = $pdo->query($sql);
        $emails = $stmt->fetchAll();
        
        logMigration("Found " . count($emails) . " unique users");
        
        $created = 0;
        foreach ($emails as $row) {
            $email = $row['email'];
            
            if (DRY_RUN) {
                logMigration("[DRY RUN] Would create user: {$email}");
                continue;
            }
            
            $user = getOrCreateUser($email);
            if ($user) {
                $created++;
                logMigration("Created user: {$email} (UUID: {$user['user_uuid']})");
            }
        }
        
        logMigration("Users migration completed: {$created} users created/verified");
        return true;
        
    } catch (Exception $e) {
        logMigration("Error migrating users: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Step 2: Migrate inbox_messages to emails table
 */
function migrateInboxMessages() {
    logMigration("=== Step 2: Migrating Inbox Messages ===");
    
    $pdo = getDatabaseConnection();
    if (!$pdo) return false;
    
    try {
        // Count total messages
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM inbox_messages");
        $total = $stmt->fetch()['count'];
        
        logMigration("Total inbox messages to migrate: {$total}");
        
        $offset = 0;
        $migrated = 0;
        $errors = 0;
        
        while ($offset < $total) {
            $stmt = $pdo->prepare("SELECT * FROM inbox_messages LIMIT :limit OFFSET :offset");
            $stmt->bindValue(':limit', BATCH_SIZE, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $messages = $stmt->fetchAll();
            
            if (empty($messages)) {
                break;
            }
            
            foreach ($messages as $msg) {
                if (DRY_RUN) {
                    logMigration("[DRY RUN] Would migrate inbox message ID: {$msg['id']}");
                    continue;
                }
                
                try {
                    $emailData = [
                        'email_type' => 'received',
                        'message_id' => $msg['message_id'],
                        'imap_uid' => null, // Will need to be updated by re-sync
                        'imap_mailbox' => 'INBOX',
                        'sender_email' => $msg['sender_email'],
                        'sender_name' => $msg['sender_name'],
                        'recipient_email' => $msg['user_email'],
                        'subject' => $msg['subject'],
                        'body_text' => $msg['body'],
                        'body_html' => null,
                        'is_read' => $msg['is_read'],
                        'is_starred' => $msg['is_starred'],
                        'is_important' => $msg['is_important'],
                        'has_attachments' => $msg['has_attachments'],
                        'email_date' => $msg['received_date'],
                        'received_at' => $msg['fetched_at']
                    ];
                    
                    $result = saveEmail($emailData);
                    
                    if ($result) {
                        // Mark as deleted if it was deleted in old system
                        if ($msg['is_deleted']) {
                            markEmailAsDeleted($msg['user_email'], $result['email_id']);
                        }
                        
                        $migrated++;
                        if ($migrated % 50 == 0) {
                            logMigration("Migrated {$migrated} / {$total} inbox messages...");
                        }
                    } else {
                        $errors++;
                        logMigration("Failed to migrate inbox message ID: {$msg['id']}", 'ERROR');
                    }
                    
                } catch (Exception $e) {
                    $errors++;
                    logMigration("Error migrating inbox message ID {$msg['id']}: " . $e->getMessage(), 'ERROR');
                }
            }
            
            $offset += BATCH_SIZE;
        }
        
        logMigration("Inbox messages migration completed: {$migrated} migrated, {$errors} errors");
        return true;
        
    } catch (Exception $e) {
        logMigration("Error migrating inbox messages: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Step 3: Migrate sent_emails to emails table
 */
function migrateSentEmails() {
    logMigration("=== Step 3: Migrating Sent Emails ===");
    
    $pdo = getDatabaseConnection();
    if (!$pdo) return false;
    
    try {
        // Count total sent emails
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM sent_emails");
        $total = $stmt->fetch()['count'];
        
        logMigration("Total sent emails to migrate: {$total}");
        
        $offset = 0;
        $migrated = 0;
        $errors = 0;
        
        while ($offset < $total) {
            $stmt = $pdo->prepare("SELECT * FROM sent_emails LIMIT :limit OFFSET :offset");
            $stmt->bindValue(':limit', BATCH_SIZE, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $sentEmails = $stmt->fetchAll();
            
            if (empty($sentEmails)) {
                break;
            }
            
            foreach ($sentEmails as $sent) {
                if (DRY_RUN) {
                    logMigration("[DRY RUN] Would migrate sent email ID: {$sent['id']}");
                    continue;
                }
                
                try {
                    $emailData = [
                        'email_type' => 'sent',
                        'sender_email' => $sent['sender_email'],
                        'recipient_email' => $sent['recipient_email'],
                        'cc_list' => $sent['cc_list'],
                        'bcc_list' => $sent['bcc_list'],
                        'subject' => $sent['subject'],
                        'body_text' => $sent['message_body'],
                        'article_title' => $sent['article_title'],
                        'has_attachments' => !empty($sent['attachment_names']),
                        'email_date' => $sent['sent_at'],
                        'sent_at' => $sent['sent_at'],
                        'label_id' => $sent['label_id'],
                        'is_internal' => isInternalEmail($sent['sender_email'], $sent['recipient_email'])
                    ];
                    
                    $result = saveEmail($emailData);
                    
                    if ($result) {
                        // Mark as deleted if current_status = 0
                        if (isset($sent['current_status']) && $sent['current_status'] == 0) {
                            markEmailAsDeleted($sent['sender_email'], $result['email_id']);
                        }
                        
                        $migrated++;
                        if ($migrated % 50 == 0) {
                            logMigration("Migrated {$migrated} / {$total} sent emails...");
                        }
                    } else {
                        $errors++;
                        logMigration("Failed to migrate sent email ID: {$sent['id']}", 'ERROR');
                    }
                    
                } catch (Exception $e) {
                    $errors++;
                    logMigration("Error migrating sent email ID {$sent['id']}: " . $e->getMessage(), 'ERROR');
                }
            }
            
            $offset += BATCH_SIZE;
        }
        
        logMigration("Sent emails migration completed: {$migrated} migrated, {$errors} errors");
        return true;
        
    } catch (Exception $e) {
        logMigration("Error migrating sent emails: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Step 4: Migrate labels
 */
function migrateLabels() {
    logMigration("=== Step 4: Migrating Labels ===");
    
    $pdo = getDatabaseConnection();
    if (!$pdo) return false;
    
    try {
        $stmt = $pdo->query("SELECT * FROM labels ORDER BY id");
        $oldLabels = $stmt->fetchAll();
        
        logMigration("Total labels to migrate: " . count($oldLabels));
        
        $migrated = 0;
        $errors = 0;
        
        foreach ($oldLabels as $label) {
            if (DRY_RUN) {
                logMigration("[DRY RUN] Would migrate label: {$label['label_name']}");
                continue;
            }
            
            try {
                // Get user_id from email (old schema used user_email)
                $user = getUserByEmail($label['user_email'] ?? '');
                
                if (!$user) {
                    // If user_email doesn't exist, skip or create default
                    logMigration("User not found for label '{$label['label_name']}', skipping", 'WARNING');
                    continue;
                }
                
                // Check if label already exists
                $stmt = $pdo->prepare("SELECT id FROM labels WHERE user_id = :user_id AND label_name = :label_name");
                $stmt->execute([
                    ':user_id' => $user['id'],
                    ':label_name' => $label['label_name']
                ]);
                
                if (!$stmt->fetch()) {
                    $stmt = $pdo->prepare(
                        "INSERT INTO labels (user_id, label_name, label_color, created_at) 
                         VALUES (:user_id, :label_name, :label_color, :created_at)"
                    );
                    
                    $stmt->execute([
                        ':user_id' => $user['id'],
                        ':label_name' => $label['label_name'],
                        ':label_color' => $label['label_color'] ?? '#0973dc',
                        ':created_at' => $label['created_at'] ?? date('Y-m-d H:i:s')
                    ]);
                    
                    $migrated++;
                    logMigration("Migrated label: {$label['label_name']}");
                }
                
            } catch (Exception $e) {
                $errors++;
                logMigration("Error migrating label ID {$label['id']}: " . $e->getMessage(), 'ERROR');
            }
        }
        
        logMigration("Labels migration completed: {$migrated} migrated, {$errors} errors");
        return true;
        
    } catch (Exception $e) {
        logMigration("Error migrating labels: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Step 5: Update label references in user_email_access
 */
function updateLabelReferences() {
    logMigration("=== Step 5: Updating Label References ===");
    
    // This is handled automatically during email migration
    // Labels are assigned when emails are created
    
    logMigration("Label references updated during email migration");
    return true;
}

/**
 * Step 6: Verify migration
 */
function verifyMigration() {
    logMigration("=== Step 6: Verifying Migration ===");
    
    $pdo = getDatabaseConnection();
    if (!$pdo) return false;
    
    try {
        // Count records in new tables
        $counts = [
            'users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
            'emails' => $pdo->query("SELECT COUNT(*) FROM emails")->fetchColumn(),
            'user_email_access' => $pdo->query("SELECT COUNT(*) FROM user_email_access")->fetchColumn(),
            'labels' => $pdo->query("SELECT COUNT(*) FROM labels")->fetchColumn()
        ];
        
        logMigration("Migration verification:");
        foreach ($counts as $table => $count) {
            logMigration("  {$table}: {$count} records");
        }
        
        // Verify no orphaned records
        $orphanedAccess = $pdo->query(
            "SELECT COUNT(*) FROM user_email_access uea 
             LEFT JOIN users u ON uea.user_id = u.id 
             LEFT JOIN emails e ON uea.email_id = e.id 
             WHERE u.id IS NULL OR e.id IS NULL"
        )->fetchColumn();
        
        if ($orphanedAccess > 0) {
            logMigration("WARNING: Found {$orphanedAccess} orphaned user_email_access records", 'WARNING');
        } else {
            logMigration("No orphaned records found");
        }
        
        return true;
        
    } catch (Exception $e) {
        logMigration("Error verifying migration: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Main migration execution
 */
function runMigration() {
    logMigration("========================================");
    logMigration("=== DATABASE MIGRATION STARTED ===");
    logMigration("========================================");
    
    if (DRY_RUN) {
        logMigration("*** RUNNING IN DRY RUN MODE ***");
        logMigration("No changes will be made to the database");
    }
    
    $startTime = microtime(true);
    
    // Execute migration steps
    $steps = [
        'migrateUsers',
        'migrateInboxMessages',
        'migrateSentEmails',
        'migrateLabels',
        'updateLabelReferences',
        'verifyMigration'
    ];
    
    foreach ($steps as $step) {
        if (!$step()) {
            logMigration("Migration failed at step: {$step}", 'ERROR');
            logMigration("Please review errors and try again");
            return false;
        }
    }
    
    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);
    
    logMigration("========================================");
    logMigration("=== MIGRATION COMPLETED SUCCESSFULLY ===");
    logMigration("Total time: {$duration} seconds");
    logMigration("========================================");
    
    if (!DRY_RUN) {
        logMigration("");
        logMigration("NEXT STEPS:");
        logMigration("1. Verify your data in the new schema");
        logMigration("2. Update your application code to use db_config_REDESIGNED.php");
        logMigration("3. Set up cron job: cron_process_deletion_queue.php");
        logMigration("4. Re-sync IMAP to populate imap_uid fields");
        logMigration("5. Once verified, you can drop old tables: inbox_messages, sent_emails");
    }
    
    return true;
}

// Execute migration
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line\n");
}

// Confirm before running
if (!DRY_RUN) {
    echo "\n*** WARNING ***\n";
    echo "This will migrate your data to the new schema.\n";
    echo "Make sure you have backed up your database!\n";
    echo "\nType 'yes' to continue: ";
    
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    
    if (trim($line) !== 'yes') {
        echo "Migration cancelled\n";
        exit(0);
    }
}

runMigration();

?>