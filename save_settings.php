<?php
/**
 * SAVE SETTINGS - UPDATED VERSION
 * Now separates IMAP settings from regular settings
 * IMAP settings should be saved via imap_settings.php
 */

session_start();
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass']) || !isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

require_once 'db_config.php';
require_once 'settings_helper.php';

$userEmail = $_SESSION['smtp_user'];

// Define regular settings (non-IMAP)
$regularSettings = [
    // Identity & Authority
    'display_name', 'designation', 'dept', 'hod_email', 'staff_id', 'room_no', 'ext_no',
    
    // Automation & Compliance
    'auto_bcc_hod', 'archive_sent', 'read_receipts', 'delayed_send', 'attach_size_limit',
    'auto_label_sent', 'priority_level', 'mandatory_subject',
    
    // Editor & Composition
    'font_family', 'font_size', 'spell_check', 'auto_correct', 'smart_reply', 'rich_text',
    'default_cc', 'default_bcc', 'undo_send_delay', 'signature',
    
    // Interface Personalization
    'sidebar_color', 'compact_mode', 'dark_mode', 'show_avatars', 'anim_speed',
    'blur_effects', 'density', 'font_weight',
    
    // Notifications & Security
    'push_notif', 'sound_alerts', 'browser_notif', 'two_factor', 'session_timeout',
    'ip_lock', 'debug_logs', 'activity_report'
];

// IMAP settings - should NOT be saved through this script
$imapSettings = [
    'imap_server', 'imap_port', 'imap_encryption', 'imap_username'
];

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Check if user is trying to modify IMAP settings through this script
    $hasImapSettings = false;
    foreach ($imapSettings as $key) {
        if (isset($_POST[$key])) {
            $hasImapSettings = true;
            break;
        }
    }
    
    if ($hasImapSettings) {
        echo json_encode([
            'success' => false,
            'message' => 'IMAP settings must be configured through the dedicated IMAP Settings page.',
            'redirect' => 'imap_settings.php'
        ]);
        exit();
    }
    
    // Handle unlock request (super admin only)
    if (isset($_POST['settings_locked']) && $_POST['settings_locked'] === 'false') {
        if (!isSuperAdmin()) {
            echo json_encode([
                'success' => false,
                'message' => 'Unauthorized: Super admin access required'
            ]);
            exit();
        }
        
        unlockSettings($userEmail);
        
        logSuperAdminAction(
            $_SESSION['smtp_user'],
            'SETTINGS_UNLOCK',
            $userEmail,
            ['timestamp' => date('Y-m-d H:i:s')]
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Settings unlocked successfully'
        ]);
        exit();
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Prepare statement for insert/update
    $stmt = $pdo->prepare("
        INSERT INTO user_settings (user_email, setting_key, setting_value, updated_at)
        VALUES (:email, :key, :value, NOW())
        ON DUPLICATE KEY UPDATE 
            setting_value = VALUES(setting_value),
            updated_at = NOW()
    ");
    
    $savedCount = 0;
    
    // Process each POST parameter
    foreach ($_POST as $key => $value) {
        // Skip if it's an IMAP setting
        if (in_array($key, $imapSettings)) {
            continue;
        }
        
        // Check if it's a valid regular setting
        if (!in_array($key, $regularSettings)) {
            continue;
        }
        
        // Convert boolean values
        if ($value === 'on') {
            $value = 'true';
        } elseif ($value === 'false') {
            $value = 'false';
        }
        
        // Sanitize value
        $value = trim($value);
        
        // Additional validation for specific fields
        if ($key === 'hod_email' || $key === 'default_cc' || $key === 'default_bcc') {
            if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $pdo->rollBack();
                echo json_encode([
                    'success' => false,
                    'message' => "Invalid email format for $key"
                ]);
                exit();
            }
        }
        
        // Validate numeric fields
        if (in_array($key, ['font_size', 'session_timeout', 'attach_size_limit', 'undo_send_delay'])) {
            if (!empty($value) && !is_numeric($value)) {
                $pdo->rollBack();
                echo json_encode([
                    'success' => false,
                    'message' => "$key must be a number"
                ]);
                exit();
            }
        }
        
        // Execute insert/update
        $stmt->execute([
            ':email' => $userEmail,
            ':key' => $key,
            ':value' => $value
        ]);
        
        $savedCount++;
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Log the activity
    error_log("Regular settings saved for user: $userEmail ($savedCount settings updated)");
    
    echo json_encode([
        'success' => true,
        'message' => 'Settings saved successfully',
        'count' => $savedCount
    ]);
    
} catch (PDOException $e) {
    // Rollback on error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Database error saving settings: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    
} catch (Exception $e) {
    error_log("Error saving settings: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>