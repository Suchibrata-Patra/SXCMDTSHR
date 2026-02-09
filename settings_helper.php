<?php
/**
 * Settings Helper - Manages user IMAP and other settings
 */

require_once 'db_config.php';

/**
 * Get user settings with defaults
 */
function getSettingsWithDefaults($userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            return getDefaultSettings($userEmail);
        }
        
        // Try to get settings from database
        $stmt = $pdo->prepare("
            SELECT setting_key, setting_value 
            FROM user_settings 
            WHERE user_email = :email
        ");
        $stmt->execute([':email' => $userEmail]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert rows to key-value array
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        // Merge with defaults
        $defaults = getDefaultSettings($userEmail);
        return array_merge($defaults, $settings);
        
    } catch (PDOException $e) {
        error_log("Error getting settings: " . $e->getMessage());
        return getDefaultSettings($userEmail);
    }
}

/**
 * Get default settings
 */
function getDefaultSettings($userEmail) {
    // Detect domain
    $domain = substr(strrchr($userEmail, "@"), 1);
    
    // Default IMAP settings based on domain
    $defaults = [
        'imap_server' => 'imap.hostinger.com',
        'imap_port' => '993',
        'imap_encryption' => 'ssl',
        'imap_username' => $userEmail,
        'settings_locked' => '0'
    ];
    
    // Domain-specific defaults
    if ($domain === 'gmail.com' || $domain === 'sxccal.edu') {
        $defaults['imap_server'] = 'imap.gmail.com';
    } elseif ($domain === 'outlook.com' || $domain === 'hotmail.com') {
        $defaults['imap_server'] = 'outlook.office365.com';
    } elseif ($domain === 'yahoo.com') {
        $defaults['imap_server'] = 'imap.mail.yahoo.com';
    }
    
    return $defaults;
}

/**
 * Save user settings
 */
function saveUserSetting($userEmail, $key, $value) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;
        
        $stmt = $pdo->prepare("
            INSERT INTO user_settings (user_email, setting_key, setting_value, updated_at)
            VALUES (:email, :key, :value, NOW())
            ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value),
                updated_at = NOW()
        ");
        
        return $stmt->execute([
            ':email' => $userEmail,
            ':key' => $key,
            ':value' => $value
        ]);
        
    } catch (PDOException $e) {
        error_log("Error saving setting: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if settings are locked
 */
function areSettingsLocked($userEmail) {
    $settings = getSettingsWithDefaults($userEmail);
    return isset($settings['settings_locked']) && $settings['settings_locked'] === '1';
}

/**
 * Check if user is super admin
 */
function isSuperAdmin() {
    return isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] === true;
}

/**
 * Get IMAP config from session
 */
function getImapConfigFromSession() {
    if (isset($_SESSION['imap_config']) && is_array($_SESSION['imap_config'])) {
        return $_SESSION['imap_config'];
    }
    return false;
}

/**
 * Load IMAP config from database to session
 */
function loadImapConfigFromDatabase($userEmail, $password) {
    $settings = getSettingsWithDefaults($userEmail);
    
    $_SESSION['imap_config'] = [
        'imap_server' => $settings['imap_server'] ?? 'imap.hostinger.com',
        'imap_port' => $settings['imap_port'] ?? '993',
        'imap_encryption' => $settings['imap_encryption'] ?? 'ssl',
        'imap_username' => $settings['imap_username'] ?? $userEmail,
        'imap_password' => $password
    ];
    
    return true;
}

/**
 * Create user_settings table if not exists
 */
function ensureUserSettingsTable() {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS user_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_email VARCHAR(255) NOT NULL,
                setting_key VARCHAR(100) NOT NULL,
                setting_value TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_setting (user_email, setting_key),
                INDEX idx_user_email (user_email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        return true;
    } catch (PDOException $e) {
        error_log("Error creating user_settings table: " . $e->getMessage());
        return false;
    }
}

// Ensure table exists
ensureUserSettingsTable();
?>