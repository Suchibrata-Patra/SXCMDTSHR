<?php
/**
 * SAVE SETTINGS - UNIFIED VERSION
 * Handles both regular settings AND IMAP settings
 * No more artificial separation!
 */
require_once __DIR__ . '/security_handler.php';
session_start();
ini_set('display_errors', 0);
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass']) || !isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

require_once 'db_config.php';
require_once 'settings_helper.php';

$userEmail = $_SESSION['smtp_user'];

// Define ALL valid settings (including IMAP)
$validSettings = [
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
    // NOTE: imap_server / imap_port / imap_encryption / imap_username are now
    // sourced exclusively from env() and are NOT user-editable.
];

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Handle unlock request (admin only)
    if (isset($_POST['unlock_settings']) && $_POST['unlock_settings'] === 'true') {
        if (!isSuperAdmin()) {
            echo json_encode([
                'success' => false,
                'message' => 'Unauthorized: Admin access required'
            ]);
            exit();
        }
        
        // Unlock settings
        $stmt = $pdo->prepare("
            INSERT INTO user_settings (user_email, setting_key, setting_value, updated_at)
            VALUES (:email, 'settings_locked', 'false', NOW())
            ON DUPLICATE KEY UPDATE 
                setting_value = 'false',
                updated_at = NOW()
        ");
        $stmt->execute([':email' => $userEmail]);
        
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
        // Check if it's a valid setting
        if (!in_array($key, $validSettings)) {
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
        if (in_array($key, ['hod_email', 'default_cc', 'default_bcc', 'imap_username'])) {
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
        if (in_array($key, ['font_size', 'session_timeout', 'attach_size_limit', 'undo_send_delay', 'imap_port'])) {
            if (!empty($value) && !is_numeric($value)) {
                $pdo->rollBack();
                echo json_encode([
                    'success' => false,
                    'message' => "$key must be a number"
                ]);
                exit();
            }
        }
        
        // Validate IMAP port range
        if ($key === 'imap_port') {
            $port = (int)$value;
            if ($port < 1 || $port > 65535) {
                $pdo->rollBack();
                echo json_encode([
                    'success' => false,
                    'message' => "IMAP port must be between 1 and 65535"
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
    error_log("Settings saved for user: $userEmail ($savedCount settings updated)");
    
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