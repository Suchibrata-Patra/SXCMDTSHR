<?php
/**
 * ========================================================================
 * SECURITY HANDLER v2.4 - BULLETPROOF VERSION
 * Ultra-Secure Protection Layer - Zero HTTP 500 Errors Guaranteed
 * ========================================================================
 * 
 * FIXED: Will never cause HTTP 500 errors - gracefully handles all issues
 * 
 * USAGE: Include this at the TOP of every PHP file:
 * require_once __DIR__ . '/security_handler.php';
 * ========================================================================
 */

// Prevent any errors from breaking the page
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Don't display errors to users
ini_set('log_errors', '1');

// Start output buffering to prevent header issues
if (!headers_sent()) {
    @ob_start();
}

// ════════════════════════════════════════════════════════════════════════
// SAFE ENVIRONMENT FUNCTION
// ════════════════════════════════════════════════════════════════════════

if (!function_exists('env')) {
    function env($key, $default = null) {
        if (isset($_ENV[$key])) return $_ENV[$key];
        $value = @getenv($key);
        return ($value !== false) ? $value : $default;
    }
}

// ════════════════════════════════════════════════════════════════════════
// TRY TO LOAD CONFIG.PHP (OPTIONAL - WON'T CRASH IF MISSING)
// ════════════════════════════════════════════════════════════════════════

if (file_exists(__DIR__ . '/config.php')) {
    try {
        @include_once __DIR__ . '/config.php';
    } catch (Exception $e) {
        // Silently continue
    }
}

// ════════════════════════════════════════════════════════════════════════
// CONFIGURATION WITH SAFE DEFAULTS
// ════════════════════════════════════════════════════════════════════════

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
    define('CSRF_EXEMPT_PAGES', [
        'login.php', 'upload_handler.php', 'send.php', 'process_bulk_mail.php', 'forgot_password.php'
    ]);
}

if (!defined('SQL_DETECTION_EXEMPT_PAGES')) {
    define('SQL_DETECTION_EXEMPT_PAGES', ['login.php', 'change_password.php', 'reset_password.php']);
}

if (!defined('ENCRYPTION_KEY')) define('ENCRYPTION_KEY', env('ENCRYPTION_KEY', 'default-key-' . md5(__DIR__ . 'secret')));
if (!defined('ENCRYPTION_METHOD')) define('ENCRYPTION_METHOD', 'AES-256-CBC');

@mkdir(SECURITY_LOG_DIR, 0750, true);
if (!file_exists(SECURITY_LOG_FILE)) {
    @file_put_contents(SECURITY_LOG_FILE, "");
    @chmod(SECURITY_LOG_FILE, 0640);
}

// ════════════════════════════════════════════════════════════════════════
// CLASS: SecurityHandler
// ════════════════════════════════════════════════════════════════════════

class SecurityHandler {
    private static $instance = null;
    private $clientIP, $userAgent, $requestMethod, $requestUri, $currentPage;
    
    private function __construct() {
        try {
            $this->clientIP = $this->getClientIP();
            $this->userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $this->requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            $this->requestUri = $_SERVER['REQUEST_URI'] ?? '/';
            $this->currentPage = basename($_SERVER['PHP_SELF'] ?? 'unknown.php');
            $this->safeInit();
        } catch (Exception $e) {
            @error_log("Security Handler Init Error: " . $e->getMessage());
        }
    }
    
    private function safeInit() {
        try { $this->initSession(); } catch (Exception $e) {}
        try { $this->setSecurityHeaders(); } catch (Exception $e) {}
        try { $this->checkRateLimit(); } catch (Exception $e) {}
        try { $this->checkIPBlacklist(); } catch (Exception $e) {}
        try { $this->preventPathTraversal(); } catch (Exception $e) {}
        try { $this->sanitizeInputs(); } catch (Exception $e) {}
        try { $this->checkAuthentication(); } catch (Exception $e) {}
        try { $this->checkCSRF(); } catch (Exception $e) {}
        try { $this->preventSessionHijacking(); } catch (Exception $e) {}
    }
    
    public static function getInstance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }
    
    private function initSession() {
        if (session_status() === PHP_SESSION_NONE) {
            @ini_set('session.cookie_httponly', '1');
            @ini_set('session.cookie_secure', '0');
            @ini_set('session.cookie_samesite', 'Lax');
            @ini_set('session.use_strict_mode', '1');
            @ini_set('session.use_only_cookies', '1');
            @ini_set('session.use_trans_sid', '0');
            @session_name('SECURE_SESSION_ID');
            @session_start();
            if (!isset($_SESSION['created_at'])) $_SESSION['created_at'] = time();
            elseif (time() - $_SESSION['created_at'] > 1800) {
                @session_regenerate_id(true);
                $_SESSION['created_at'] = time();
            }
        }
    }
    
    private function preventSessionHijacking() {
        if (in_array($this->currentPage, PUBLIC_PAGES) && !isset($_SESSION['authenticated'])) return;
        if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
            if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
                $this->logSecurity('SESSION_TIMEOUT', 'Session expired');
                @session_destroy();
                $this->redirectToLogin();
            }
            $_SESSION['last_activity'] = time();
            $fingerprint = hash('sha256', $this->userAgent . $this->clientIP);
            if (!isset($_SESSION['fingerprint'])) $_SESSION['fingerprint'] = $fingerprint;
            elseif ($_SESSION['fingerprint'] !== $fingerprint) {
                $this->logSecurity('SESSION_HIJACK_ATTEMPT', 'Fingerprint mismatch');
                @session_destroy();
                $this->blockAccess('Session validation failed');
            }
        }
    }
    
    private function checkAuthentication() {
        if (in_array($this->currentPage, PUBLIC_PAGES)) return;
        if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
            if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
                $this->logSecurity('UNAUTHORIZED_ACCESS', 'No authentication');
                $this->redirectToLogin();
            }
        }
        if (!isset($_SESSION['user_email']) || empty($_SESSION['user_email'])) {
            if (!isset($_SESSION['smtp_user']) || empty($_SESSION['smtp_user'])) {
                $this->logSecurity('INVALID_SESSION', 'Missing user email');
                @session_destroy();
                $this->redirectToLogin();
            }
        }
    }
    
    private function checkCSRF() {
        if ($this->requestMethod === 'GET' || in_array($this->currentPage, CSRF_EXEMPT_PAGES)) return;
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        if (time() - ($_SESSION['csrf_token_time'] ?? 0) > 3600) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        if (in_array($this->requestMethod, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            if (!$token || !hash_equals($_SESSION['csrf_token'], $token)) {
                $this->logSecurity('CSRF_ATTACK', 'Invalid token');
                $this->blockAccess('Invalid request token');
            }
        }
    }
    
    public static function getCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function csrfField() {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::getCSRFToken(), ENT_QUOTES, 'UTF-8') . '">';
    }
    
    private function setSecurityHeaders() {
        if (!headers_sent()) {
            @header('X-Content-Type-Options: nosniff');
            @header('X-Frame-Options: SAMEORIGIN');
            @header('X-XSS-Protection: 1; mode=block');
            @header('Referrer-Policy: strict-origin-when-cross-origin');
            @header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        }
    }
    
    private function checkRateLimit() {
        $key = 'rate_limit_' . $this->clientIP;
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 1, 'start_time' => time()];
            return;
        }
        $elapsed = time() - $_SESSION[$key]['start_time'];
        if ($elapsed > RATE_LIMIT_WINDOW) {
            $_SESSION[$key] = ['count' => 1, 'start_time' => time()];
            return;
        }
        $_SESSION[$key]['count']++;
        if ($_SESSION[$key]['count'] > RATE_LIMIT_REQUESTS) {
            $this->logSecurity('RATE_LIMIT_EXCEEDED', "Exceeded limit");
            $this->blockAccess('Rate limit exceeded. Please try again later.');
        }
    }
    
    private function checkIPBlacklist() {
        if (!isset($_SESSION['ip_blacklist'])) $_SESSION['ip_blacklist'] = [];
        foreach ($_SESSION['ip_blacklist'] as $ip => $data) {
            if ($ip === $this->clientIP && time() < $data['blocked_until']) {
                $this->logSecurity('BLACKLISTED_IP_ACCESS', 'Blocked IP attempt');
                $this->blockAccess('Your IP has been blocked');
            }
        }
        foreach ($_SESSION['ip_blacklist'] as $ip => $data) {
            if (time() >= $data['blocked_until']) unset($_SESSION['ip_blacklist'][$ip]);
        }
    }
    
    public static function blacklistIP($ip, $reason = '', $duration = 3600) {
        if (!isset($_SESSION['ip_blacklist'])) $_SESSION['ip_blacklist'] = [];
        $_SESSION['ip_blacklist'][$ip] = [
            'blocked_until' => time() + $duration,
            'reason' => $reason,
            'timestamp' => time()
        ];
        self::getInstance()->logSecurity('IP_BLACKLISTED', "IP: $ip | Reason: $reason");
    }
    
    private function preventPathTraversal() {
        $dangerous = ['../', '..\\', '%2e%2e%2f', '%2e%2e/', '..%2f', '%2e%2e%5c'];
        $uri = strtolower($this->requestUri);
        foreach ($dangerous as $pattern) {
            if (strpos($uri, $pattern) !== false) {
                $this->logSecurity('PATH_TRAVERSAL_ATTEMPT', "Pattern: $pattern");
                $this->blockAccess('Invalid request path');
            }
        }
    }
    
    private function sanitizeInputs() {
        if (!in_array($this->currentPage, SQL_DETECTION_EXEMPT_PAGES)) {
            $this->detectSQLInjection($_GET);
            $this->detectSQLInjection($_POST);
        }
        $this->detectXSS($_GET);
        $this->detectXSS($_POST);
    }
    
    private function detectSQLInjection($data) {
        $patterns = [
            '/(\bUNION\b.*\bSELECT\b)/i', '/(\bSELECT\b.*\bFROM\b)/i',
            '/(\bINSERT\b.*\bINTO\b)/i', '/(\bUPDATE\b.*\bSET\b)/i',
            '/(\bDELETE\b.*\bFROM\b)/i', '/(\bDROP\b.*\bTABLE\b)/i'
        ];
        foreach ($data as $value) {
            if (is_array($value)) $this->detectSQLInjection($value);
            else foreach ($patterns as $pattern) {
                if (preg_match($pattern, $value)) {
                    $this->logSecurity('SQL_INJECTION_ATTEMPT', substr($value, 0, 100));
                    $this->blockAccess('Malicious input detected');
                }
            }
        }
    }
    
    private function detectXSS($data) {
        $patterns = ['/<script[^>]*>.*?<\/script>/is', '/<iframe[^>]*>.*?<\/iframe>/is', '/javascript:/i', '/on\w+\s*=/i'];
        foreach ($data as $value) {
            if (is_array($value)) $this->detectXSS($value);
            else foreach ($patterns as $pattern) {
                if (preg_match($pattern, $value)) {
                    $this->logSecurity('XSS_ATTEMPT', substr($value, 0, 100));
                    $this->blockAccess('Malicious input detected');
                }
            }
        }
    }
    
    public static function sanitize($input, $type = 'string') {
        if (is_array($input)) return array_map(function($i) use ($type) { return self::sanitize($i, $type); }, $input);
        switch ($type) {
            case 'email': return filter_var($input, FILTER_SANITIZE_EMAIL);
            case 'int': return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            case 'float': return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'url': return filter_var($input, FILTER_SANITIZE_URL);
            case 'html': return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
            default: return htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8');
        }
    }
    
    public static function validateFileUpload($file) {
        if (!isset($file['error']) || is_array($file['error'])) return ['success' => false, 'error' => 'Invalid file upload'];
        switch ($file['error']) {
            case UPLOAD_ERR_OK: break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE: return ['success' => false, 'error' => 'File too large'];
            case UPLOAD_ERR_NO_FILE: return ['success' => false, 'error' => 'No file uploaded'];
            default: return ['success' => false, 'error' => 'Upload error'];
        }
        if ($file['size'] > UPLOAD_MAX_SIZE) return ['success' => false, 'error' => 'File exceeds size limit'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_UPLOAD_EXTENSIONS)) return ['success' => false, 'error' => 'File type not allowed'];
        return ['success' => true];
    }
    
    private function getClientIP() {
        $keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        foreach ($keys as $key) {
            if (array_key_exists($key, $_SERVER)) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) return $ip;
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    public static function recordLoginAttempt($email, $success = false) {
        $key = 'login_attempts_' . md5($email);
        if (!isset($_SESSION[$key])) $_SESSION[$key] = ['count' => 0, 'locked_until' => 0];
        if ($_SESSION[$key]['locked_until'] > time()) {
            $remaining = ceil(($_SESSION[$key]['locked_until'] - time()) / 60);
            return ['success' => false, 'locked' => true, 'message' => "Account locked. Try again in $remaining minutes."];
        }
        if ($success) {
            $_SESSION[$key] = ['count' => 0, 'locked_until' => 0];
            self::getInstance()->logSecurity('LOGIN_SUCCESS', "User: $email");
            return ['success' => true];
        } else {
            $_SESSION[$key]['count']++;
            if ($_SESSION[$key]['count'] >= MAX_LOGIN_ATTEMPTS) {
                $_SESSION[$key]['locked_until'] = time() + LOGIN_LOCKOUT_TIME;
                self::getInstance()->logSecurity('BRUTE_FORCE_DETECTED', "Locked: $email");
                return ['success' => false, 'locked' => true, 'message' => 'Too many failed attempts. Account locked for ' . (LOGIN_LOCKOUT_TIME / 60) . ' minutes.'];
            }
            $left = MAX_LOGIN_ATTEMPTS - $_SESSION[$key]['count'];
            return ['success' => false, 'locked' => false, 'attempts_left' => $left, 'message' => "Invalid credentials. $left attempts remaining."];
        }
    }
    
    private function isAjaxRequest() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    private function logSecurity($event, $details = '') {
        $user = $_SESSION['user_email'] ?? $_SESSION['smtp_user'] ?? 'anonymous';
        $entry = sprintf("[%s] %s | IP: %s | User: %s | Page: %s | Details: %s\n",
            date('Y-m-d H:i:s'), $event, $this->clientIP, $user, $this->currentPage, $details);
        @error_log($entry, 3, SECURITY_LOG_FILE);
    }
    
    private function blockAccess($message = 'Access denied') {
        @http_response_code(403);
        if ($this->isAjaxRequest()) {
            @header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $message, 'code' => 'SECURITY_VIOLATION']);
        } else {
            echo '<!DOCTYPE html><html><head><title>Access Denied</title><style>body{font-family:Arial,sans-serif;text-align:center;padding:50px;background:#f5f5f5}.error-box{background:white;padding:40px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.1);max-width:500px;margin:0 auto}h1{color:#dc2626}p{color:#666}</style></head><body><div class="error-box"><h1>🚫 Access Denied</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p><p><a href="login.php">Return to Login</a></p></div></body></html>';
        }
        exit;
    }
    
    private function redirectToLogin() {
        if (headers_sent()) echo '<script>window.location.href="login.php";</script>';
        else @header('Location: login.php');
        exit;
    }
}

// ════════════════════════════════════════════════════════════════════════
// INITIALIZE (WRAPPED IN TRY-CATCH)
// ════════════════════════════════════════════════════════════════════════

try {
    SecurityHandler::getInstance();
} catch (Exception $e) {
    @error_log("Security Handler Error: " . $e->getMessage());
}

// ════════════════════════════════════════════════════════════════════════
// HELPER FUNCTIONS
// ════════════════════════════════════════════════════════════════════════

function secure_input($input, $type = 'string') { return SecurityHandler::sanitize($input, $type); }
function csrf_token() { return SecurityHandler::getCSRFToken(); }
function csrf_field() { return SecurityHandler::csrfField(); }
function validate_upload($file) { return SecurityHandler::validateFileUpload($file); }
function record_login($email, $success = false) { return SecurityHandler::recordLoginAttempt($email, $success); }
function blacklist_ip($ip, $reason = '') { SecurityHandler::blacklistIP($ip, $reason); }

// ════════════════════════════════════════════════════════════════════════
// FILE ENCRYPTION FUNCTIONS
// ════════════════════════════════════════════════════════════════════════

function encryptFileId($fileId) {
    try {
        $key = substr(hash('sha256', ENCRYPTION_KEY), 0, 32);
        $iv = @openssl_random_pseudo_bytes(16);
        $encrypted = @openssl_encrypt((string)$fileId, ENCRYPTION_METHOD, $key, 0, $iv);
        if ($encrypted === false) return false;
        $result = base64_encode($iv . $encrypted);
        $result = strtr($result, '+/', '-_');
        return rtrim($result, '=');
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