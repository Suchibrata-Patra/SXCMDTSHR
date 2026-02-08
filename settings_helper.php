<?php
/**
 * Settings Helper Functions
 * 
 * Provides reusable functions for loading and managing user settings
 * throughout the SXC MDTS application.
 */

/**
 * Get all settings for a user from database
 * 
 * @param string $email User's email address
 * @return array Associative array of settings
 */
function getUserSettings($email) {
    $pdo = getDatabaseConnection();
    if (!$pdo) return [];
    
    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM user_settings WHERE user_email = :email");
        $stmt->execute([':email' => $email]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $settings = [];
        foreach ($results as $row) {
            $value = $row['setting_value'];
            // Convert string booleans to actual booleans
            if ($value === 'true') $value = true;
            if ($value === 'false') $value = false;
            $settings[$row['setting_key']] = $value;
        }
        return $settings;
    } catch (PDOException $e) {
        error_log("Error loading settings: " . $e->getMessage());
        return [];
    }
}

/**
 * Get a specific setting for a user
 * 
 * @param string $email User's email address
 * @param string $key Setting key
 * @param mixed $default Default value if setting not found
 * @return mixed Setting value or default
 */
function getUserSetting($email, $key, $default = null) {
    $pdo = getDatabaseConnection();
    if (!$pdo) return $default;
    
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM user_settings WHERE user_email = :email AND setting_key = :key");
        $stmt->execute([':email' => $email, ':key' => $key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $value = $result['setting_value'];
            // Convert string booleans
            if ($value === 'true') return true;
            if ($value === 'false') return false;
            return $value;
        }
        
        return $default;
    } catch (PDOException $e) {
        error_log("Error loading setting: " . $e->getMessage());
        return $default;
    }
}

/**
 * Get default settings
 * 
 * @return array Default settings for all users
 */
function getDefaultSettings() {
    return [
        // Identity & Authority
        'display_name' => '',
        'designation' => '',
        'dept' => 'CS',
        'hod_email' => '',
        'staff_id' => '',
        'room_no' => '',
        'ext_no' => '',
        
        // Automation & Compliance
        'auto_bcc_hod' => true,
        'archive_sent' => true,
        'read_receipts' => false,
        'delayed_send' => '0',
        'attach_size_limit' => '25',
        'auto_label_sent' => true,
        'priority_level' => 'normal',
        'mandatory_subject' => true,
        
        // Editor & Composition
        'font_family' => 'Inter',
        'font_size' => '14',
        'spell_check' => true,
        'auto_correct' => true,
        'smart_reply' => false,
        'rich_text' => true,
        'default_cc' => '',
        'default_bcc' => '',
        'undo_send_delay' => '10',
        'signature' => '',
        
        // Interface Personalization
        'sidebar_color' => 'white',
        'compact_mode' => false,
        'dark_mode' => 'auto',
        'show_avatars' => true,
        'anim_speed' => 'normal',
        'blur_effects' => true,
        'density' => 'relaxed',
        'font_weight' => 'medium',
        
        // Notifications & Security
        'push_notif' => true,
        'sound_alerts' => 'tink',
        'browser_notif' => true,
        'two_factor' => false,
        'session_timeout' => '60',
        'ip_lock' => false,
        'debug_logs' => false,
        'activity_report' => 'weekly'
    ];
}

/**
 * Get settings with defaults fallback
 * 
 * @param string $email User's email address
 * @return array Complete settings array with defaults for missing values
 */
function getSettingsWithDefaults($email) {
    $defaults = getDefaultSettings();
    $userSettings = getUserSettings($email);
    return array_merge($defaults, $userSettings);
}

/**
 * Save a single setting for a user
 * 
 * @param string $email User's email address
 * @param string $key Setting key
 * @param mixed $value Setting value
 * @return bool Success status
 */
function saveSetting($email, $key, $value) {
    $pdo = getDatabaseConnection();
    if (!$pdo) return false;
    
    try {
        // Convert boolean values to strings
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO user_settings (user_email, setting_key, setting_value, updated_at)
            VALUES (:email, :key, :value, NOW())
            ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value),
                updated_at = NOW()
        ");
        
        return $stmt->execute([
            ':email' => $email,
            ':key' => $key,
            ':value' => $value
        ]);
    } catch (PDOException $e) {
        error_log("Error saving setting: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete a setting for a user
 * 
 * @param string $email User's email address
 * @param string $key Setting key
 * @return bool Success status
 */
function deleteSetting($email, $key) {
    $pdo = getDatabaseConnection();
    if (!$pdo) return false;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM user_settings WHERE user_email = :email AND setting_key = :key");
        return $stmt->execute([':email' => $email, ':key' => $key]);
    } catch (PDOException $e) {
        error_log("Error deleting setting: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete all settings for a user
 * 
 * @param string $email User's email address
 * @return bool Success status
 */
function deleteAllSettings($email) {
    $pdo = getDatabaseConnection();
    if (!$pdo) return false;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM user_settings WHERE user_email = :email");
        return $stmt->execute([':email' => $email]);
    } catch (PDOException $e) {
        error_log("Error deleting all settings: " . $e->getMessage());
        return false;
    }
}

/**
 * Export user settings as JSON
 * 
 * @param string $email User's email address
 * @return string JSON string of settings
 */
function exportSettings($email) {
    $settings = getUserSettings($email);
    return json_encode($settings, JSON_PRETTY_PRINT);
}

/**
 * Import user settings from JSON
 * 
 * @param string $email User's email address
 * @param string $json JSON string of settings
 * @return bool Success status
 */
function importSettings($email, $json) {
    $settings = json_decode($json, true);
    if (!$settings) return false;
    
    $pdo = getDatabaseConnection();
    if (!$pdo) return false;
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO user_settings (user_email, setting_key, setting_value, updated_at)
            VALUES (:email, :key, :value, NOW())
            ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value),
                updated_at = NOW()
        ");
        
        foreach ($settings as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            
            $stmt->execute([
                ':email' => $email,
                ':key' => $key,
                ':value' => $value
            ]);
        }
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error importing settings: " . $e->getMessage());
        return false;
    }
}
?>