<?php
/**
 * ========================================================================
 * SECURITY HANDLER v2.5 - DIAGNOSTIC VERSION
 * Minimal version that logs all errors for debugging
 * ========================================================================
 */

// Enable error logging to file
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

// Create logs directory
@mkdir(__DIR__ . '/logs', 0750, true);

// Log that security handler is loading
@file_put_contents(__DIR__ . '/logs/security_debug.log', date('Y-m-d H:i:s') . " - Security handler starting to load\n", FILE_APPEND);

// Start output buffering
if (!headers_sent()) {
    @ob_start();
}

// Define env function
if (!function_exists('env')) {
    function env($key, $default = null) {
        if (isset($_ENV[$key])) return $_ENV[$key];
        $value = @getenv($key);
        return ($value !== false) ? $value : $default;
    }
}

// Try to load config.php
if (file_exists(__DIR__ . '/config.php')) {
    @file_put_contents(__DIR__ . '/logs/security_debug.log', date('Y-m-d H:i:s') . " - Attempting to load config.php\n", FILE_APPEND);
    try {
        @include_once __DIR__ . '/config.php';
        @file_put_contents(__DIR__ . '/logs/security_debug.log', date('Y-m-d H:i:s') . " - config.php loaded successfully\n", FILE_APPEND);
    } catch (Exception $e) {
        @file_put_contents(__DIR__ . '/logs/security_debug.log', date('Y-m-d H:i:s') . " - config.php error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
} else {
    @file_put_contents(__DIR__ . '/logs/security_debug.log', date('Y-m-d H:i:s') . " - config.php not found\n", FILE_APPEND);
}

// Define constants
if (!defined('SECURITY_LOG_DIR')) define('SECURITY_LOG_DIR', __DIR__ . '/logs');
if (!defined('SECURITY_LOG_FILE')) define('SECURITY_LOG_FILE', SECURITY_LOG_DIR . '/security.log');
if (!defined('MAX_LOGIN_ATTEMPTS')) define('MAX_LOGIN_ATTEMPTS', 5);
if (!defined('LOGIN_LOCKOUT_TIME')) define('LOGIN_LOCKOUT_TIME', 900);
if (!defined('SESSION_TIMEOUT')) define('SESSION_TIMEOUT', 3600);
if (!defined('RATE_LIMIT_REQUESTS')) define('RATE_LIMIT_REQUESTS', 100);
if (!defined('RATE_LIMIT_WINDOW')) define('RATE_LIMIT_WINDOW', 60);
if (!defined('UPLOAD_MAX_SIZE')) define('UPLOAD_MAX_SIZE', 10485760);
if (!defined('ALLOWED_UPLOAD_EXTENSIONS')) define('ALLOWED_UPLOAD_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'txt', 'csv']);
if (!defined('PUBLIC_PAGES')) {
    define('PUBLIC_PAGES', [
        'login.php', 'debug_login.php', 'security_handler.php', 'change_password.php',
        'forgot_password.php', 'reset_password.php'
    ]);
}
if (!defined('CSRF_EXEMPT_PAGES')) {
    define('CSRF_EXEMPT_PAGES', ['login.php', 'upload_handler.php', 'send.php', 'process_bulk_mail.php', 'forgot_password.php']);
}
if (!defined('SQL_DETECTION_EXEMPT_PAGES')) {
    define('SQL_DETECTION_EXEMPT_PAGES', ['login.php', 'change_password.php', 'reset_password.php']);
}
if (!defined('ENCRYPTION_KEY')) define('ENCRYPTION_KEY', env('ENCRYPTION_KEY', 'default-key-' . md5(__DIR__ . 'secret')));
if (!defined('ENCRYPTION_METHOD')) define('ENCRYPTION_METHOD', 'AES-256-CBC');

@file_put_contents(__DIR__ . '/logs/security_debug.log', date('Y-m-d H:i:s') . " - Constants defined\n", FILE_APPEND);

// Minimal SecurityHandler class
class SecurityHandler {
    private static $instance = null;
    private $currentPage;
    
    private function __construct() {
        @file_put_contents(__DIR__ . '/logs/security_debug.log', date('Y-m-d H:i:s') . " - SecurityHandler __construct starting\n", FILE_APPEND);
        
        try {
            $this->currentPage = basename($_SERVER['PHP_SELF'] ?? 'unknown.php');
            
            @file_put_contents(__DIR__ . '/logs/security_debug.log', date('Y-m-d H:i:s') . " - Current page: {$this->currentPage}\n", FILE_APPEND);
            
            // Only do minimal initialization
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
                @file_put_contents(__DIR__ . '/logs/security_debug.log', date('Y-m-d H:i:s') . " - Session started\n", FILE_APPEND);
            }
            
            // ONLY check authentication for non-public pages
            if (!in_array($this->currentPage, PUBLIC_PAGES)) {
                @file_put_contents(__DIR__ . '/logs/security_debug.log', date('Y-m-d H:i:s') . " - Checking auth (not a public page)\n", FILE_APPEND);
                $this->checkAuthentication();
            } else {
                @file_put_contents(__DIR__ . '/logs/security_debug.log', date('Y-m-d H:i:s') . " - Skipping auth (public page)\n", FILE_APPEND);
            }
            
            @file_put_contents(__DIR__ . '/logs/security_debug.log', date('Y-m-d H:i:s') . " - SecurityHandler __construct completed\n", FILE_APPEND);
            
        } catch (Exception $e) {
            @file_put_contents(__DIR__ . '/logs/security_debug.log', date('Y-m-d H:i:s') . " - ERROR in __construct: " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function checkAuthentication() {
        if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
            if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
                @file_put_contents(__DIR__ . '/logs/security_debug.log', date('Y-m-d H:i:s') . " - Not authenticated, redirecting to login\n", FILE_APPEND);
                $this->redirectToLogin();
            }
        }
    }
    
    private function redirectToLogin() {
        if (headers_sent()) {
            echo '<script>window.location.href="login.php";</script>';
        } else {
            @header('Location: login.php');
        }
        exit;
    }
    
    public static function getCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function csrfField() {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::getCSRFToken(), ENT_QUOTES, 'UTF-8') . '">';
    }
    
    public static function sanitize($input, $type = 'string') {
        if (is_array($input)) return array_map(function($i) use ($type) { return self::sanitize($i, $type); }, $input);
        switch ($type) {
            case 'email': return filter_var($input, FILTER_SANITIZE_EMAIL);
            case 'int': return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            case 'html': return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
            default: return htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8');
        }
    }
    
    public static function validateFileUpload($file) {
        if (!isset($file['error'])) return ['success' => false, 'error' => 'Invalid file'];
        if ($file['error'] !== UPLOAD_ERR_OK) return ['success' => false, 'error' => 'Upload error'];
        if ($file['size'] > UPLOAD_MAX_SIZE) return ['success' => false, 'error' => 'File too large'];
        return ['success' => true];
    }
    
    public static function recordLoginAttempt($email, $success = false) {
        $key = 'login_attempts_' . md5($email);
        if (!isset($_SESSION[$key])) $_SESSION[$key] = ['count' => 0, 'locked_until' => 0];
        if ($_SESSION[$key]['locked_until'] > time()) {
            $remaining = ceil(($_SESSION[$key]['locked_until'] - time()) / 60);
            return ['success' => false, 'locked' => true, 'message' => "Locked for $remaining minutes"];
        }
        if ($success) {
            $_SESSION[$key] = ['count' => 0, 'locked_until' => 0];
            return ['success' => true];
        } else {
            $_SESSION[$key]['count']++;
            if ($_SESSION[$key]['count'] >= MAX_LOGIN_ATTEMPTS) {
                $_SESSION[$key]['locked_until'] = time() + LOGIN_LOCKOUT_TIME;
                return ['success' => false, 'locked' => true, 'message' => 'Account locked'];
            }
            $left = MAX_LOGIN_ATTEMPTS - $_SESSION[$key]['count'];
            return ['success' => false, 'locked' => false, 'attempts_left' => $left];
        }
    }
    
    public static function blacklistIP($ip, $reason = '', $duration = 3600) {
        if (!isset($_SESSION['ip_blacklist'])) $_SESSION['ip_blacklist'] = [];
        $_SESSION['ip_blacklist'][$ip] = ['blocked_until' => time() + $duration, 'reason' => $reason];
    }
}

@file_put_contents(__DIR__ . '/logs/security_debug.log', date('Y-m-d H:i:s') . " - About to initialize SecurityHandler\n", FILE_APPEND);

// Initialize
try {
    SecurityHandler::getInstance();
    @file_put_contents(__DIR__ . '/logs/security_debug.log', date('Y-m-d H:i:s') . " - SecurityHandler initialized successfully\n", FILE_APPEND);
} catch (Exception $e) {
    @file_put_contents(__DIR__ . '/logs/security_debug.log', date('Y-m-d H:i:s') . " - CRITICAL ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    @file_put_contents(__DIR__ . '/logs/security_debug.log', date('Y-m-d H:i:s') . " - Stack trace: " . $e->getTraceAsString() . "\n", FILE_APPEND);
}

// Helper functions
function secure_input($input, $type = 'string') { return SecurityHandler::sanitize($input, $type); }
function csrf_token() { return SecurityHandler::getCSRFToken(); }
function csrf_field() { return SecurityHandler::csrfField(); }
function validate_upload($file) { return SecurityHandler::validateFileUpload($file); }
function record_login($email, $success = false) { return SecurityHandler::recordLoginAttempt($email, $success); }
function blacklist_ip($ip, $reason = '') { SecurityHandler::blacklistIP($ip, $reason); }

// Encryption functions
function encryptFileId($fileId) {
    try {
        $key = substr(hash('sha256', ENCRYPTION_KEY), 0, 32);
        $iv = @openssl_random_pseudo_bytes(16);
        $encrypted = @openssl_encrypt((string)$fileId, ENCRYPTION_METHOD, $key, 0, $iv);
        if ($encrypted === false) return false;
        $result = base64_encode($iv . $encrypted);
        return rtrim(strtr($result, '+/', '-_'), '=');
    } catch (Exception $e) {
        return false;
    }
}

function decryptFileId($encryptedId) {
    try {
        $encryptedId = strtr($encryptedId, '-_', '+/');
        $encryptedId .= str_repeat('=', (4 - strlen($encryptedId) % 4) % 4);
        $data = @base64_decode($encryptedId);
        if ($data === false) return false;
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        $key = substr(hash('sha256', ENCRYPTION_KEY), 0, 32);
        $decrypted = @openssl_decrypt($encrypted, ENCRYPTION_METHOD, $key, 0, $iv);
        return $decrypted !== false ? (int)$decrypted : false;
    } catch (Exception $e) {
        return false;
    }
}

@file_put_contents(__DIR__ . '/logs/security_debug.log', date('Y-m-d H:i:s') . " - Security handler fully loaded\n", FILE_APPEND);