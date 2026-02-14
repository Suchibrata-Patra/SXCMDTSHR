<?php
/**
 * ========================================================================
 * SECURITY HANDLER v2.3 - FIXED HTTP 500 ERROR
 * Ultra-Secure Protection Layer for PHP Applications
 * ========================================================================
 * 
 * FIXED: Gracefully handles missing config.php to prevent HTTP 500 errors
 * 
 * USAGE: Include this at the TOP of every PHP file:
 * require_once __DIR__ . '/security_handler.php';
 * ========================================================================
 */

// Start output buffering to prevent header issues
if (!headers_sent()) {
    ob_start();
}

// ════════════════════════════════════════════════════════════════════════
// CONFIGURATION
// ════════════════════════════════════════════════════════════════════════

define('SECURITY_LOG_FILE', __DIR__ . '/logs/security.log');
define('SECURITY_LOG_DIR', __DIR__ . '/logs');
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes in seconds
define('SESSION_TIMEOUT', 3600); // 1 hour
define('RATE_LIMIT_REQUESTS', 100); // Max requests per time window
define('RATE_LIMIT_WINDOW', 60); // Time window in seconds
define('UPLOAD_MAX_SIZE', 10485760); // 10MB in bytes
define('ALLOWED_UPLOAD_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'txt', 'csv']);

// Public pages that don't require authentication
define('PUBLIC_PAGES', [
    'login.php',
    'debug_login.php',
    'security_handler.php',
    'change_password.php'
]);

// Pages exempt from CSRF protection (AJAX endpoints and login)
define('CSRF_EXEMPT_PAGES', [
    'login.php',
    'upload_handler.php',  // ← FIXED: Added this to allow AJAX file uploads
    'send.php',            // ← FIXED: Added this for email sending
    'process_bulk_mail.php' // ← FIXED: Added this for bulk email processing
]);

// Pages exempt from SQL injection detection (login pages handle their own validation)
define('SQL_DETECTION_EXEMPT_PAGES', [
    'login.php',
    'change_password.php'
]);

// Create logs directory if it doesn't exist
if (!is_dir(SECURITY_LOG_DIR)) {
    @mkdir(SECURITY_LOG_DIR, 0750, true);
}

// Ensure log file exists
if (!file_exists(SECURITY_LOG_FILE)) {
    @file_put_contents(SECURITY_LOG_FILE, "");
    @chmod(SECURITY_LOG_FILE, 0640);
}

// ════════════════════════════════════════════════════════════════════════
// LOAD CONFIG.PHP IF EXISTS (Graceful Fallback)
// ════════════════════════════════════════════════════════════════════════

// Define env() function if not already defined
if (!function_exists('env')) {
    function env($key, $default = null) {
        // Try $_ENV first
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        
        // Try getenv
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }
        
        // Return default
        return $default;
    }
}

// Try to include config.php if it exists
if (file_exists(__DIR__ . '/config.php')) {
    try {
        require_once __DIR__ . '/config.php';
    } catch (Exception $e) {
        // Log error but continue - don't crash the application
        error_log("Warning: config.php could not be loaded: " . $e->getMessage());
    }
}

// Define encryption constants with fallback values
if (!defined('ENCRYPTION_KEY')) {
    define('ENCRYPTION_KEY', env('ENCRYPTION_KEY', 'default-key-change-in-production-' . substr(md5(__DIR__), 0, 16)));
}
if (!defined('ENCRYPTION_METHOD')) {
    define('ENCRYPTION_METHOD', 'AES-256-CBC');
}

// ════════════════════════════════════════════════════════════════════════
// CLASS: SecurityHandler
// ════════════════════════════════════════════════════════════════════════

class SecurityHandler {
    
    private static $instance = null;
    private $requestStartTime;
    private $clientIP;
    private $userAgent;
    private $requestMethod;
    private $requestUri;
    private $currentPage;
    
    private function __construct() {
        $this->requestStartTime = microtime(true);
        $this->clientIP = $this->getClientIP();
        $this->userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $this->requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $this->currentPage = basename($_SERVER['PHP_SELF']);
        
        // Initialize security measures
        $this->initSession();
        $this->setSecurityHeaders();
        $this->checkRateLimit();
        $this->checkIPBlacklist();
        $this->preventPathTraversal();
        $this->sanitizeInputs();
        $this->checkAuthentication();
        $this->checkCSRF();
        $this->preventSessionHijacking();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // ────────────────────────────────────────────────────────────────────
    // SESSION MANAGEMENT
    // ────────────────────────────────────────────────────────────────────
    
    private function initSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // Secure session configuration
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_secure', '0'); // Set to '1' if using HTTPS
            ini_set('session.cookie_samesite', 'Lax');
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.use_trans_sid', '0');
            
            session_name('SECURE_SESSION_ID');
            session_start();
            
            // Regenerate session ID periodically
            if (!isset($_SESSION['created_at'])) {
                $_SESSION['created_at'] = time();
            } elseif (time() - $_SESSION['created_at'] > 1800) { // 30 minutes
                session_regenerate_id(true);
                $_SESSION['created_at'] = time();
            }
        }
    }
    
    private function preventSessionHijacking() {
        // Skip session hijacking check for public pages during login
        if (in_array($this->currentPage, PUBLIC_PAGES) && !isset($_SESSION['authenticated'])) {
            return;
        }
        
        // Check session timeout only for authenticated users
        if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
            if (isset($_SESSION['last_activity'])) {
                if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
                    $this->logSecurity('SESSION_TIMEOUT', 'Session expired due to inactivity');
                    session_destroy();
                    $this->redirectToLogin();
                }
            }
            $_SESSION['last_activity'] = time();
            
            // Validate session fingerprint for authenticated users
            $fingerprint = $this->generateFingerprint();
            
            if (!isset($_SESSION['fingerprint'])) {
                $_SESSION['fingerprint'] = $fingerprint;
            } elseif ($_SESSION['fingerprint'] !== $fingerprint) {
                $this->logSecurity('SESSION_HIJACK_ATTEMPT', 'Session fingerprint mismatch');
                session_destroy();
                $this->blockAccess('Session validation failed');
            }
        }
    }
    
    private function generateFingerprint() {
        $data = $this->userAgent . $this->clientIP;
        return hash('sha256', $data);
    }
    
    // ────────────────────────────────────────────────────────────────────
    // AUTHENTICATION
    // ────────────────────────────────────────────────────────────────────
    
    private function checkAuthentication() {
        // Skip authentication for public pages
        if (in_array($this->currentPage, PUBLIC_PAGES)) {
            return;
        }
        
        // Check if user is authenticated
        if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
            // Check for alternative authentication markers (backward compatibility)
            if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
                $this->logSecurity('UNAUTHORIZED_ACCESS', 'Attempted access without authentication');
                $this->redirectToLogin();
            }
        }
        
        // Validate user email exists in session
        if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
            // Try fallback to smtp_user for backward compatibility
            if (!isset($_SESSION['smtp_user']) || empty($_SESSION['smtp_user'])) {
                $this->logSecurity('INVALID_SESSION', 'Missing user email in session');
                session_destroy();
                $this->redirectToLogin();
            }
        }
    }
    
    // ────────────────────────────────────────────────────────────────────
    // CSRF PROTECTION
    // ────────────────────────────────────────────────────────────────────
    
    private function checkCSRF() {
        // Skip CSRF check for GET requests and exempt pages
        if ($this->requestMethod === 'GET' || in_array($this->currentPage, CSRF_EXEMPT_PAGES)) {
            return;
        }
        
        // Generate CSRF token if not exists
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = $this->generateCSRFToken();
            $_SESSION['csrf_token_time'] = time();
        }
        
        // Regenerate token after 1 hour
        if (time() - ($_SESSION['csrf_token_time'] ?? 0) > 3600) {
            $_SESSION['csrf_token'] = $this->generateCSRFToken();
            $_SESSION['csrf_token_time'] = time();
        }
        
        // Validate CSRF token for POST/PUT/DELETE requests
        if (in_array($this->requestMethod, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            
            if (!$token || !hash_equals($_SESSION['csrf_token'], $token)) {
                $this->logSecurity('CSRF_ATTACK', 'Invalid or missing CSRF token');
                $this->blockAccess('Invalid request token');
            }
        }
    }
    
    private function generateCSRFToken() {
        return bin2hex(random_bytes(32));
    }
    
    public static function getCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function csrfField() {
        $token = self::getCSRFToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
    
    // ────────────────────────────────────────────────────────────────────
    // SECURITY HEADERS
    // ────────────────────────────────────────────────────────────────────
    
    private function setSecurityHeaders() {
        if (!headers_sent()) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('X-XSS-Protection: 1; mode=block');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
            
            // Don't set CSP for public pages as they might need inline scripts for login forms
            if (!in_array($this->currentPage, PUBLIC_PAGES)) {
                header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:;");
            }
        }
    }
    
    // ────────────────────────────────────────────────────────────────────
    // RATE LIMITING
    // ────────────────────────────────────────────────────────────────────
    
    private function checkRateLimit() {
        $key = 'rate_limit_' . $this->clientIP;
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'count' => 1,
                'start_time' => time()
            ];
            return;
        }
        
        $elapsed = time() - $_SESSION[$key]['start_time'];
        
        // Reset counter if time window has passed
        if ($elapsed > RATE_LIMIT_WINDOW) {
            $_SESSION[$key] = [
                'count' => 1,
                'start_time' => time()
            ];
            return;
        }
        
        // Increment request count
        $_SESSION[$key]['count']++;
        
        // Check if limit exceeded
        if ($_SESSION[$key]['count'] > RATE_LIMIT_REQUESTS) {
            $this->logSecurity('RATE_LIMIT_EXCEEDED', "Exceeded {$_SESSION[$key]['count']} requests in {$elapsed} seconds");
            $this->blockAccess('Rate limit exceeded. Please try again later.');
        }
    }
    
    // ────────────────────────────────────────────────────────────────────
    // IP BLACKLIST
    // ────────────────────────────────────────────────────────────────────
    
    private function checkIPBlacklist() {
        if (!isset($_SESSION['ip_blacklist'])) {
            $_SESSION['ip_blacklist'] = [];
        }
        
        foreach ($_SESSION['ip_blacklist'] as $ip => $data) {
            if ($ip === $this->clientIP) {
                if (time() < $data['blocked_until']) {
                    $this->logSecurity('BLACKLISTED_IP_ACCESS', 'Attempt from blacklisted IP');
                    $this->blockAccess('Your IP has been blocked due to suspicious activity');
                }
            }
        }
        
        // Clean up expired blocks
        foreach ($_SESSION['ip_blacklist'] as $ip => $data) {
            if (time() >= $data['blocked_until']) {
                unset($_SESSION['ip_blacklist'][$ip]);
            }
        }
    }
    
    public static function blacklistIP($ip, $reason = '', $duration = 3600) {
        if (!isset($_SESSION['ip_blacklist'])) {
            $_SESSION['ip_blacklist'] = [];
        }
        
        $_SESSION['ip_blacklist'][$ip] = [
            'blocked_until' => time() + $duration,
            'reason' => $reason,
            'timestamp' => time()
        ];
        
        self::getInstance()->logSecurity('IP_BLACKLISTED', "IP: $ip | Reason: $reason");
    }
    
    // ────────────────────────────────────────────────────────────────────
    // PATH TRAVERSAL PREVENTION
    // ────────────────────────────────────────────────────────────────────
    
    private function preventPathTraversal() {
        $dangerous_patterns = [
            '../',
            '..\\',
            '%2e%2e%2f',
            '%2e%2e/',
            '..%2f',
            '%2e%2e%5c'
        ];
        
        $uri = strtolower($this->requestUri);
        
        foreach ($dangerous_patterns as $pattern) {
            if (strpos($uri, $pattern) !== false) {
                $this->logSecurity('PATH_TRAVERSAL_ATTEMPT', "Detected pattern: $pattern in URI: {$this->requestUri}");
                $this->blockAccess('Invalid request path');
            }
        }
    }
    
    // ────────────────────────────────────────────────────────────────────
    // INPUT SANITIZATION
    // ────────────────────────────────────────────────────────────────────
    
    private function sanitizeInputs() {
        // SQL Injection Detection (Skip for exempt pages)
        if (!in_array($this->currentPage, SQL_DETECTION_EXEMPT_PAGES)) {
            $this->detectSQLInjection($_GET);
            $this->detectSQLInjection($_POST);
        }
        
        // XSS Detection
        $this->detectXSS($_GET);
        $this->detectXSS($_POST);
    }
    
    private function detectSQLInjection($data) {
        $sql_patterns = [
            '/(\bUNION\b.*\bSELECT\b)/i',
            '/(\bSELECT\b.*\bFROM\b)/i',
            '/(\bINSERT\b.*\bINTO\b)/i',
            '/(\bUPDATE\b.*\bSET\b)/i',
            '/(\bDELETE\b.*\bFROM\b)/i',
            '/(\bDROP\b.*\bTABLE\b)/i',
            '/(;|\-\-|\/\*|\*\/)/i',
            '/(\bOR\b.*=.*)/i',
            '/(\bAND\b.*=.*)/i'
        ];
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->detectSQLInjection($value);
            } else {
                foreach ($sql_patterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $this->logSecurity('SQL_INJECTION_ATTEMPT', "Pattern: $pattern | Value: " . substr($value, 0, 100));
                        $this->blockAccess('Malicious input detected');
                    }
                }
            }
        }
    }
    
    private function detectXSS($data) {
        $xss_patterns = [
            '/<script[^>]*>.*?<\/script>/is',
            '/<iframe[^>]*>.*?<\/iframe>/is',
            '/javascript:/i',
            '/on\w+\s*=/i', // onclick, onload, etc.
            '/<embed[^>]*>/i',
            '/<object[^>]*>/i'
        ];
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->detectXSS($value);
            } else {
                foreach ($xss_patterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $this->logSecurity('XSS_ATTEMPT', "Pattern: $pattern | Value: " . substr($value, 0, 100));
                        $this->blockAccess('Malicious input detected');
                    }
                }
            }
        }
    }
    
    public static function sanitize($input, $type = 'string') {
        if (is_array($input)) {
            return array_map(function($item) use ($type) {
                return self::sanitize($item, $type);
            }, $input);
        }
        
        switch ($type) {
            case 'email':
                return filter_var($input, FILTER_SANITIZE_EMAIL);
            
            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            
            case 'float':
                return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            
            case 'url':
                return filter_var($input, FILTER_SANITIZE_URL);
            
            case 'html':
                return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
            
            case 'string':
            default:
                return htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8');
        }
    }
    
    // ────────────────────────────────────────────────────────────────────
    // FILE UPLOAD VALIDATION
    // ────────────────────────────────────────────────────────────────────
    
    public static function validateFileUpload($file) {
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['success' => false, 'error' => 'Invalid file upload'];
        }
        
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ['success' => false, 'error' => 'File exceeds maximum size limit'];
            case UPLOAD_ERR_NO_FILE:
                return ['success' => false, 'error' => 'No file was uploaded'];
            default:
                return ['success' => false, 'error' => 'Unknown upload error'];
        }
        
        // Check file size
        if ($file['size'] > UPLOAD_MAX_SIZE) {
            return ['success' => false, 'error' => 'File size exceeds ' . (UPLOAD_MAX_SIZE / 1024 / 1024) . 'MB limit'];
        }
        
        // Check file extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_UPLOAD_EXTENSIONS)) {
            return ['success' => false, 'error' => 'File type not allowed'];
        }
        
        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowed_mimes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp',
            'application/pdf',
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain', 'text/csv',
            'application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed'
        ];
        
        if (!in_array($mime, $allowed_mimes)) {
            return ['success' => false, 'error' => 'Invalid file type'];
        }
        
        return ['success' => true];
    }
    
    // ────────────────────────────────────────────────────────────────────
    // CLIENT IP DETECTION
    // ────────────────────────────────────────────────────────────────────
    
    private function getClientIP() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER)) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    // ────────────────────────────────────────────────────────────────────
    // LOGIN ATTEMPT TRACKING
    // ────────────────────────────────────────────────────────────────────
    
    public static function recordLoginAttempt($email, $success = false) {
        $key = 'login_attempts_' . md5($email);
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'count' => 0,
                'locked_until' => 0
            ];
        }
        
        // Check if account is locked
        if ($_SESSION[$key]['locked_until'] > time()) {
            $remaining = ceil(($_SESSION[$key]['locked_until'] - time()) / 60);
            return [
                'success' => false,
                'locked' => true,
                'message' => "Account is locked. Try again in $remaining minutes."
            ];
        }
        
        if ($success) {
            // Reset on successful login
            $_SESSION[$key] = [
                'count' => 0,
                'locked_until' => 0
            ];
            
            self::getInstance()->logSecurity('LOGIN_SUCCESS', "User: $email");
            
            return ['success' => true];
        } else {
            // Increment failed attempts
            $_SESSION[$key]['count']++;
            
            if ($_SESSION[$key]['count'] >= MAX_LOGIN_ATTEMPTS) {
                $_SESSION[$key]['locked_until'] = time() + LOGIN_LOCKOUT_TIME;
                
                self::getInstance()->logSecurity('BRUTE_FORCE_DETECTED', "Account locked for $email");
                
                return [
                    'success' => false,
                    'locked' => true,
                    'message' => 'Too many failed login attempts. Account locked for ' . (LOGIN_LOCKOUT_TIME / 60) . ' minutes.'
                ];
            }
            
            $attemptsLeft = MAX_LOGIN_ATTEMPTS - $_SESSION[$key]['count'];
            return [
                'success' => false,
                'locked' => false,
                'attempts_left' => $attemptsLeft,
                'message' => "Invalid credentials. $attemptsLeft attempts remaining."
            ];
        }
    }
    
    // ────────────────────────────────────────────────────────────────────
    // UTILITY FUNCTIONS
    // ────────────────────────────────────────────────────────────────────
    
    private function isAjaxRequest() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    // ────────────────────────────────────────────────────────────────────
    // LOGGING
    // ────────────────────────────────────────────────────────────────────
    
    private function logSecurity($event, $details = '') {
        $timestamp = date('Y-m-d H:i:s');
        $user = $_SESSION['user_email'] ?? $_SESSION['smtp_user'] ?? 'anonymous';
        
        $logEntry = sprintf(
            "[%s] %s | IP: %s | User: %s | Page: %s | Details: %s | User-Agent: %s\n",
            $timestamp,
            $event,
            $this->clientIP,
            $user,
            $this->currentPage,
            $details,
            substr($this->userAgent, 0, 100)
        );
        
        @error_log($logEntry, 3, SECURITY_LOG_FILE);
        
        // Also log to PHP error log for critical events
        $criticalEvents = [
            'SQL_INJECTION_ATTEMPT',
            'XSS_ATTEMPT',
            'CSRF_ATTACK',
            'SESSION_HIJACK_ATTEMPT',
            'BRUTE_FORCE_DETECTED'
        ];
        
        if (in_array($event, $criticalEvents)) {
            @error_log("CRITICAL SECURITY EVENT: $event - $details");
        }
    }
    
    // ────────────────────────────────────────────────────────────────────
    // RESPONSE HANDLERS
    // ────────────────────────────────────────────────────────────────────
    
    private function blockAccess($message = 'Access denied') {
        http_response_code(403);
        
        // If AJAX request, return JSON
        if ($this->isAjaxRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $message,
                'code' => 'SECURITY_VIOLATION'
            ]);
        } else {
            // Regular request, show error page
            echo '<!DOCTYPE html>
<html>
<head>
    <title>Access Denied</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
        .error-box { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 500px; margin: 0 auto; }
        h1 { color: #dc2626; }
        p { color: #666; }
    </style>
</head>
<body>
    <div class="error-box">
        <h1>🚫 Access Denied</h1>
        <p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>
        <p><a href="login.php">Return to Login</a></p>
    </div>
</body>
</html>';
        }
        
        exit;
    }
    
    private function redirectToLogin() {
        if (headers_sent()) {
            echo '<script>window.location.href = "login.php";</script>';
        } else {
            header('Location: login.php');
        }
        exit;
    }
}

// ════════════════════════════════════════════════════════════════════════
// INITIALIZE SECURITY HANDLER
// ════════════════════════════════════════════════════════════════════════

SecurityHandler::getInstance();

// ════════════════════════════════════════════════════════════════════════
// HELPER FUNCTIONS (Global Access)
// ════════════════════════════════════════════════════════════════════════

function secure_input($input, $type = 'string') {
    return SecurityHandler::sanitize($input, $type);
}

function csrf_token() {
    return SecurityHandler::getCSRFToken();
}

function csrf_field() {
    return SecurityHandler::csrfField();
}

function validate_upload($file) {
    return SecurityHandler::validateFileUpload($file);
}

function record_login($email, $success = false) {
    return SecurityHandler::recordLoginAttempt($email, $success);
}

function blacklist_ip($ip, $reason = '') {
    SecurityHandler::blacklistIP($ip, $reason);
}

// ════════════════════════════════════════════════════════════════════════
// FILE ENCRYPTION FUNCTIONS (for upload_handler.php compatibility)
// ════════════════════════════════════════════════════════════════════════

function encryptFileId($fileId) {
    try {
        $key = substr(hash('sha256', ENCRYPTION_KEY), 0, 32);
        $iv = openssl_random_pseudo_bytes(16);
        
        $encrypted = openssl_encrypt(
            (string)$fileId,
            ENCRYPTION_METHOD,
            $key,
            0,
            $iv
        );
        
        if ($encrypted === false) {
            error_log("Encryption failed for file ID: $fileId");
            return false;
        }
        
        $result = base64_encode($iv . $encrypted);
        $result = strtr($result, '+/', '-_');
        $result = rtrim($result, '=');
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Encryption error: " . $e->getMessage());
        return false;
    }
}

function decryptFileId($encryptedId) {
    try {
        $encryptedId = strtr($encryptedId, '-_', '+/');
        $encryptedId .= str_repeat('=', (4 - strlen($encryptedId) % 4) % 4);
        
        $data = base64_decode($encryptedId);
        
        if ($data === false) {
            return false;
        }
        
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        $key = substr(hash('sha256', ENCRYPTION_KEY), 0, 32);
        
        $decrypted = openssl_decrypt(
            $encrypted,
            ENCRYPTION_METHOD,
            $key,
            0,
            $iv
        );
        
        return $decrypted !== false ? (int)$decrypted : false;
        
    } catch (Exception $e) {
        error_log("Decryption error: " . $e->getMessage());
        return false;
    }
}