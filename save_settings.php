<?php
session_start();
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

require_once 'db_config.php';

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

try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
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
        // Only save valid settings
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
    if ($pdo && $pdo->inTransaction()) {
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