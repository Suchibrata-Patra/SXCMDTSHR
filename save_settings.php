<?php
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

// Define all possible settings keys
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
];

// IMAP-related settings that can only be changed once (or by super admin)
$imapSettings = [
    'imap_server', 'imap_port', 'imap_encryption', 'imap_username'
];

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Check if settings are locked
    $settingsLocked = areSettingsLocked($userEmail);
    
    // Check if user is trying to modify IMAP settings
    $isModifyingImapSettings = false;
    foreach ($imapSettings as $imapKey) {
        if (isset($_POST[$imapKey])) {
            $isModifyingImapSettings = true;
            break;
        }
    }
    
    // If settings are locked and user is trying to modify IMAP settings
    if ($settingsLocked && $isModifyingImapSettings) {
        // Check if user is super admin
        if (!isSuperAdmin()) {
            echo json_encode([
                'success' => false,
                'message' => 'IMAP settings are locked. Super admin authorization required to modify.',
                'locked' => true
            ]);
            exit();
        }
        
        // Log super admin override
        logSuperAdminAction(
            $userEmail,
            'IMAP_SETTINGS_OVERRIDE',
            $userEmail,
            ['action' => 'Modified locked IMAP settings', 'timestamp' => date('Y-m-d H:i:s')]
        );
    }
    
    // If user is saving IMAP settings for the first time, validate them
    if ($isModifyingImapSettings && !$settingsLocked) {
        $imapData = [];
        foreach ($imapSettings as $key) {
            if (isset($_POST[$key])) {
                $imapData[$key] = $_POST[$key];
            }
        }
        
        $validation = validateImapSettings($imapData);
        if (!$validation['valid']) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid IMAP settings: ' . implode(', ', $validation['errors']),
                'errors' => $validation['errors']
            ]);
            exit();
        }
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
    $imapSettingsSaved = false;
    
    // Process each POST parameter
    foreach ($_POST as $key => $value) {
        // Check if it's a valid setting
        if (!in_array($key, $validSettings) && !in_array($key, $imapSettings)) {
            continue;
        }
        
        // Track if IMAP settings are being saved
        if (in_array($key, $imapSettings)) {
            $imapSettingsSaved = true;
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
        if ($key === 'imap_port') {
            if (!is_numeric($value) || $value < 1 || $value > 65535) {
                $pdo->rollBack();
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid IMAP port number'
                ]);
                exit();
            }
        }
        
        if ($key === 'imap_username' || $key === 'hod_email' || $key === 'default_cc' || $key === 'default_bcc') {
            if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $pdo->rollBack();
                echo json_encode([
                    'success' => false,
                    'message' => "Invalid email format for $key"
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
    
    // If IMAP settings were saved for the first time and settings weren't already locked,
    // lock the settings
    if ($imapSettingsSaved && !$settingsLocked && !isSuperAdmin()) {
        $stmt->execute([
            ':email' => $userEmail,
            ':key' => 'settings_locked',
            ':value' => 'true'
        ]);
        
        error_log("Settings locked for user: $userEmail");
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Update session IMAP config if IMAP settings were changed
    if ($imapSettingsSaved) {
        loadImapConfigToSession($userEmail, $_SESSION['smtp_pass']);
    }
    
    // Log the activity
    error_log("Settings saved for user: $userEmail ($savedCount settings updated)");
    
    echo json_encode([
        'success' => true,
        'message' => 'Settings saved successfully',
        'count' => $savedCount,
        'locked' => $imapSettingsSaved && !isSuperAdmin()
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
