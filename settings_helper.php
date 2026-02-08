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
        'activity_report' => 'weekly',
        
        // IMAP Configuration (defaults)
        'imap_server' => 'imap.hostinger.com',
        'imap_port' => '993',
        'imap_encryption' => 'ssl',
        'imap_username' => '', // Will be set to user's email if not configured
        
        // Settings Lock
        'settings_locked' => false
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
    
    // If imap_username is not set, default to user's email
    if (empty($userSettings['imap_username'])) {
        $userSettings['imap_username'] = $email;
    }
    
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
 * Check if settings are locked for a user
 * 
 * @param string $email User's email address
 * @return bool True if settings are locked
 */
function areSettingsLocked($email) {
    $locked = getUserSetting($email, 'settings_locked', false);
    return $locked === true || $locked === 'true' || $locked === '1';
}

/**
 * Lock settings for a user
 * 
 * @param string $email User's email address
 * @return bool Success status
 */
function lockSettings($email) {
    return saveSetting($email, 'settings_locked', true);
}

/**
 * Unlock settings for a user (super admin only)
 * 
 * @param string $email User's email address
 * @return bool Success status
 */
function unlockSettings($email) {
    return saveSetting($email, 'settings_locked', false);
}

/**
 * Check if current session user is super admin
 * 
 * @return bool True if super admin
 */
function isSuperAdmin() {
    if (!isset($_SESSION['user_role'])) {
        return false;
    }
    return $_SESSION['user_role'] === 'super_admin';
}

/**
 * Get IMAP configuration from session
 * 
 * @return array|null IMAP configuration or null if not set
 */
function getImapConfigFromSession() {
    if (!isset($_SESSION['imap_config'])) {
        return null;
    }
    
    $config = $_SESSION['imap_config'];
    
    // Validate required fields
    if (empty($config['imap_server']) || empty($config['imap_port']) || 
        empty($config['imap_username']) || empty($config['imap_password'])) {
        return null;
    }
    
    return $config;
}

/**
 * Load IMAP configuration into session from database
 * This should be called during login
 * 
 * @param string $email User's email address
 * @param string $password User's email password (from login)
 * @return bool Success status
 */
function loadImapConfigToSession($email, $password) {
    $settings = getSettingsWithDefaults($email);
    
    $_SESSION['imap_config'] = [
        'imap_server' => $settings['imap_server'] ?? 'imap.hostinger.com',
        'imap_port' => intval($settings['imap_port'] ?? 993),
        'imap_encryption' => $settings['imap_encryption'] ?? 'ssl',
        'imap_username' => $settings['imap_username'] ?? $email,
        'imap_password' => $password // From login credentials
    ];
    
    return true;
}

/**
 * Log super admin actions for audit trail
 * 
 * @param string $admin_email Admin's email
 * @param string $action Action performed
 * @param string $target_user User affected by action
 * @param array $details Additional details
 * @return bool Success status
 */
function logSuperAdminAction($admin_email, $action, $target_user, $details = []) {
    $pdo = getDatabaseConnection();
    if (!$pdo) return false;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO admin_audit_log 
            (admin_email, action, target_user, details, ip_address, created_at)
            VALUES 
            (:admin_email, :action, :target_user, :details, :ip_address, NOW())
        ");
        
        return $stmt->execute([
            ':admin_email' => $admin_email,
            ':action' => $action,
            ':target_user' => $target_user,
            ':details' => json_encode($details),
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    } catch (PDOException $e) {
        error_log("Error logging admin action: " . $e->getMessage());
        return false;
    }
}

/**
 * Validate IMAP settings before saving
 * 
 * @param array $settings IMAP settings to validate
 * @return array Array with 'valid' boolean and 'errors' array
 */
function validateImapSettings($settings) {
    $errors = [];
    
    // Required fields
    if (empty($settings['imap_server'])) {
        $errors[] = 'IMAP server is required';
    }
    
    if (empty($settings['imap_port'])) {
        $errors[] = 'IMAP port is required';
    } elseif (!is_numeric($settings['imap_port']) || $settings['imap_port'] < 1 || $settings['imap_port'] > 65535) {
        $errors[] = 'IMAP port must be a valid number between 1 and 65535';
    }
    
    if (empty($settings['imap_encryption'])) {
        $errors[] = 'IMAP encryption is required';
    } elseif (!in_array(strtolower($settings['imap_encryption']), ['ssl', 'tls', 'none'])) {
        $errors[] = 'IMAP encryption must be SSL, TLS, or none';
    }
    
    if (empty($settings['imap_username'])) {
        $errors[] = 'IMAP username is required';
    } elseif (!filter_var($settings['imap_username'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'IMAP username must be a valid email address';
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
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
