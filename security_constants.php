<?php
/**
 * ============================================================
 * SECURITY CONSTANTS
 * Define all security-related constants used across the application
 * ============================================================
 * 
 * This file should be included in login_auth_helper.php and 
 * security_handler.php to ensure consistency
 */

// ════════════════════════════════════════════════════════════════════════
// LOGIN & AUTHENTICATION CONSTANTS
// ════════════════════════════════════════════════════════════════════════

// Maximum number of failed login attempts before account lockout
if (!defined('MAX_LOGIN_ATTEMPTS')) {
    define('MAX_LOGIN_ATTEMPTS', 5);
}

// Account lockout duration in seconds (15 minutes)
if (!defined('ACCOUNT_LOCK_DURATION')) {
    define('ACCOUNT_LOCK_DURATION', 900); // 15 minutes
}

// Alternative name (for backward compatibility)
if (!defined('LOGIN_LOCKOUT_TIME')) {
    define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes
}

// Session timeout in seconds (1 hour)
if (!defined('SESSION_TIMEOUT')) {
    define('SESSION_TIMEOUT', 3600); // 1 hour
}

// Session regeneration interval in seconds (30 minutes)
if (!defined('SESSION_REGENERATE_INTERVAL')) {
    define('SESSION_REGENERATE_INTERVAL', 1800); // 30 minutes
}

// ════════════════════════════════════════════════════════════════════════
// RATE LIMITING CONSTANTS
// ════════════════════════════════════════════════════════════════════════

// Maximum requests per time window
if (!defined('RATE_LIMIT_REQUESTS')) {
    define('RATE_LIMIT_REQUESTS', 100);
}

// Rate limit time window in seconds
if (!defined('RATE_LIMIT_WINDOW')) {
    define('RATE_LIMIT_WINDOW', 60); // 1 minute
}

// Maximum failed attempts per IP before temporary block
if (!defined('MAX_FAILED_ATTEMPTS_PER_IP')) {
    define('MAX_FAILED_ATTEMPTS_PER_IP', 10);
}

// IP block duration in seconds
if (!defined('IP_BLOCK_DURATION')) {
    define('IP_BLOCK_DURATION', 1800); // 30 minutes
}

// ════════════════════════════════════════════════════════════════════════
// FILE UPLOAD CONSTANTS
// ════════════════════════════════════════════════════════════════════════

// Maximum file upload size in bytes (10MB)
if (!defined('UPLOAD_MAX_SIZE')) {
    define('UPLOAD_MAX_SIZE', 10485760); // 10MB
}

// Allowed file extensions
if (!defined('ALLOWED_UPLOAD_EXTENSIONS')) {
    define('ALLOWED_UPLOAD_EXTENSIONS', [
        'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp',     // Images
        'pdf',                                           // PDF
        'doc', 'docx', 'odt',                           // Documents
        'xls', 'xlsx', 'ods', 'csv',                    // Spreadsheets
        'ppt', 'pptx', 'odp',                           // Presentations
        'txt', 'rtf',                                   // Text
        'zip', 'rar', '7z', 'tar', 'gz'                 // Archives
    ]);
}

// ════════════════════════════════════════════════════════════════════════
// LOGGING CONSTANTS
// ════════════════════════════════════════════════════════════════════════

// Security log file location
if (!defined('SECURITY_LOG_FILE')) {
    define('SECURITY_LOG_FILE', __DIR__ . '/logs/security.log');
}

// Security log directory
if (!defined('SECURITY_LOG_DIR')) {
    define('SECURITY_LOG_DIR', __DIR__ . '/logs');
}

// Login activity log file
if (!defined('LOGIN_LOG_FILE')) {
    define('LOGIN_LOG_FILE', __DIR__ . '/logs/login_activity.log');
}

// Error log file
if (!defined('ERROR_LOG_FILE')) {
    define('ERROR_LOG_FILE', __DIR__ . '/logs/errors.log');
}

// ════════════════════════════════════════════════════════════════════════
// PUBLIC PAGES (No Authentication Required)
// ════════════════════════════════════════════════════════════════════════

if (!defined('PUBLIC_PAGES')) {
    define('PUBLIC_PAGES', [
        'login.php',
        'debug_login.php',
        'security_handler.php',
        'change_password.php',
        'forgot_password.php',
        'reset_password.php'
    ]);
}

// ════════════════════════════════════════════════════════════════════════
// CSRF EXEMPT PAGES
// ════════════════════════════════════════════════════════════════════════

if (!defined('CSRF_EXEMPT_PAGES')) {
    define('CSRF_EXEMPT_PAGES', [
        'login.php',
        'forgot_password.php'
    ]);
}

// ════════════════════════════════════════════════════════════════════════
// SQL DETECTION EXEMPT PAGES
// ════════════════════════════════════════════════════════════════════════

if (!defined('SQL_DETECTION_EXEMPT_PAGES')) {
    define('SQL_DETECTION_EXEMPT_PAGES', [
        'login.php',
        'change_password.php',
        'reset_password.php'
    ]);
}

// ════════════════════════════════════════════════════════════════════════
// PASSWORD POLICY CONSTANTS
// ════════════════════════════════════════════════════════════════════════

// Minimum password length
if (!defined('MIN_PASSWORD_LENGTH')) {
    define('MIN_PASSWORD_LENGTH', 8);
}

// Maximum password length
if (!defined('MAX_PASSWORD_LENGTH')) {
    define('MAX_PASSWORD_LENGTH', 128);
}

// Require uppercase in password
if (!defined('PASSWORD_REQUIRE_UPPERCASE')) {
    define('PASSWORD_REQUIRE_UPPERCASE', false);
}

// Require lowercase in password
if (!defined('PASSWORD_REQUIRE_LOWERCASE')) {
    define('PASSWORD_REQUIRE_LOWERCASE', false);
}

// Require numbers in password
if (!defined('PASSWORD_REQUIRE_NUMBERS')) {
    define('PASSWORD_REQUIRE_NUMBERS', false);
}

// Require special characters in password
if (!defined('PASSWORD_REQUIRE_SPECIAL')) {
    define('PASSWORD_REQUIRE_SPECIAL', false);
}

// ════════════════════════════════════════════════════════════════════════
// SESSION CONSTANTS
// ════════════════════════════════════════════════════════════════════════

// Session name
if (!defined('SESSION_NAME')) {
    define('SESSION_NAME', 'SECURE_SESSION_ID');
}

// Session cookie lifetime (0 = until browser closes)
if (!defined('SESSION_COOKIE_LIFETIME')) {
    define('SESSION_COOKIE_LIFETIME', 0);
}

// ════════════════════════════════════════════════════════════════════════
// ENVIRONMENT CONSTANTS
// ════════════════════════════════════════════════════════════════════════

// Application environment (development, production)
if (!defined('APP_ENV')) {
    define('APP_ENV', 'production');
}

// Debug mode
if (!defined('DEBUG_MODE')) {
    define('DEBUG_MODE', APP_ENV === 'development');
}

// ════════════════════════════════════════════════════════════════════════
// HELPER FUNCTIONS
// ════════════════════════════════════════════════════════════════════════

/**
 * Get account lock duration in a human-readable format
 */
function getAccountLockDurationFormatted() {
    $minutes = ACCOUNT_LOCK_DURATION / 60;
    return $minutes . ' minute' . ($minutes != 1 ? 's' : '');
}

/**
 * Get session timeout in a human-readable format
 */
function getSessionTimeoutFormatted() {
    $minutes = SESSION_TIMEOUT / 60;
    return $minutes . ' minute' . ($minutes != 1 ? 's' : '');
}

/**
 * Check if a constant is defined and return its value or default
 */
function getConstant($name, $default = null) {
    return defined($name) ? constant($name) : $default;
}

// ════════════════════════════════════════════════════════════════════════
// ENSURE LOGS DIRECTORY EXISTS
// ════════════════════════════════════════════════════════════════════════

if (!is_dir(SECURITY_LOG_DIR)) {
    @mkdir(SECURITY_LOG_DIR, 0750, true);
}

// Create log files if they don't exist
$logFiles = [SECURITY_LOG_FILE, LOGIN_LOG_FILE, ERROR_LOG_FILE];
foreach ($logFiles as $logFile) {
    if (!file_exists($logFile)) {
        @file_put_contents($logFile, "");
        @chmod($logFile, 0640);
    }
}

?>