<?php
/**
 * Settings Helper Functions
 * Handles user settings and configurations
 */

/**
 * Get user settings with default values
 */
function getSettingsWithDefaults($userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            return getDefaultSettings();
        }
        
        // Try to fetch from settings table
        $stmt = $pdo->prepare("
            SELECT * FROM settings 
            WHERE user_email = :email 
            LIMIT 1
        ");
        $stmt->execute([':email' => $userEmail]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($settings) {
            return array_merge(getDefaultSettings(), $settings);
        }
        
        return getDefaultSettings();
        
    } catch (Exception $e) {
        error_log("Error fetching settings: " . $e->getMessage());
        return getDefaultSettings();
    }
}

/**
 * Get default settings
 */
function getDefaultSettings() {
    return [
        'imap_server' => 'imap.hostinger.com',
        'imap_port' => '993',
        'imap_encryption' => 'ssl',
        'imap_username' => '',
        'smtp_server' => 'smtp.hostinger.com',
        'smtp_port' => '465',
        'smtp_encryption' => 'ssl'
    ];
}

/**
 * Save user settings
 */
function saveSettings($userEmail, $settings) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            return false;
        }
        
        // Check if settings exist
        $stmt = $pdo->prepare("
            SELECT id FROM settings 
            WHERE user_email = :email 
            LIMIT 1
        ");
        $stmt->execute([':email' => $userEmail]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing
            $stmt = $pdo->prepare("
                UPDATE settings SET
                    imap_server = :imap_server,
                    imap_port = :imap_port,
                    imap_encryption = :imap_encryption,
                    imap_username = :imap_username,
                    smtp_server = :smtp_server,
                    smtp_port = :smtp_port,
                    smtp_encryption = :smtp_encryption,
                    updated_at = NOW()
                WHERE user_email = :email
            ");
        } else {
            // Insert new
            $stmt = $pdo->prepare("
                INSERT INTO settings (
                    user_email,
                    imap_server,
                    imap_port,
                    imap_encryption,
                    imap_username,
                    smtp_server,
                    smtp_port,
                    smtp_encryption,
                    created_at,
                    updated_at
                ) VALUES (
                    :email,
                    :imap_server,
                    :imap_port,
                    :imap_encryption,
                    :imap_username,
                    :smtp_server,
                    :smtp_port,
                    :smtp_encryption,
                    NOW(),
                    NOW()
                )
            ");
        }
        
        return $stmt->execute([
            ':email' => $userEmail,
            ':imap_server' => $settings['imap_server'] ?? 'imap.hostinger.com',
            ':imap_port' => $settings['imap_port'] ?? '993',
            ':imap_encryption' => $settings['imap_encryption'] ?? 'ssl',
            ':imap_username' => $settings['imap_username'] ?? $userEmail,
            ':smtp_server' => $settings['smtp_server'] ?? 'smtp.hostinger.com',
            ':smtp_port' => $settings['smtp_port'] ?? '465',
            ':smtp_encryption' => $settings['smtp_encryption'] ?? 'ssl'
        ]);
        
    } catch (Exception $e) {
        error_log("Error saving settings: " . $e->getMessage());
        return false;
    }
}
?>