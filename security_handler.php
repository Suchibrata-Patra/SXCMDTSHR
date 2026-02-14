<?php
/**
 * ========================================================================
 * SECURITY HANDLER v2.1 - FIXED VERSION
 * Ultra-Secure Protection Layer for PHP Applications
 * ========================================================================
 * 
 * FIXED: Will not block legitimate login attempts
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

// Pages exempt from CSRF protection (e.g., API endpoints with other auth)
define('CSRF_EXEMPT_PAGES', [
    'login.php'
]);

// Pages exempt from SQL injection detection (login pages handle their own validation)
define('SQL_DETECTION_EXEMPT_PAGES', [
    'login.php',
    'change_password.php'
]);

// ════════════════════════════════════════════════════════════════════════
// INITIALIZATION
// ════════════════════════════════════════════════════════════════════════

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
            ini_set('session.cookie_samesite', 'Lax'); // Changed from Strict to Lax for better compatibility
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
            $this->logSecurity('UNAUTHORIZED_ACCESS', 'Attempted access without authentication');
            $this->redirectToLogin();
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
                $this->logSecurity('CSRF_ATTACK', 'Invalid CSRF token');
                $this->blockAccess('Invalid security token');
            }
        }
    }
    
    public static function generateCSRFToken() {
        return bin2hex(random_bytes(32));
    }
    
    public static function getCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = self::generateCSRFToken();
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
        if (headers_sent()) {
            return;
        }
        
        // Prevent XSS attacks
        header("X-XSS-Protection: 1; mode=block");
        
        // Prevent clickjacking
        header("X-Frame-Options: SAMEORIGIN");
        
        // Prevent MIME type sniffing
        header("X-Content-Type-Options: nosniff");
        
        // Referrer policy
        header("Referrer-Policy: strict-origin-when-cross-origin");
        
        // Content Security Policy - UPDATED for better compatibility
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdn.quilljs.com https://cdnjs.cloudflare.com https://www.google.com https://www.gstatic.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.quilljs.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' https://www.google.com;");
        
        // Strict Transport Security (only if using HTTPS)
        // Uncomment this line when you enable HTTPS
        // if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        //     header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
        // }
        
        // Permissions Policy
        header("Permissions-Policy: geolocation=(), camera=(), microphone=()");
        
        // Remove server signature
        header_remove("X-Powered-By");
    }
    
    // ────────────────────────────────────────────────────────────────────
    // INPUT SANITIZATION
    // ────────────────────────────────────────────────────────────────────
    
    private function sanitizeInputs() {
        $_GET = $this->sanitizeArray($_GET);
        $_POST = $this->sanitizeArray($_POST);
        $_COOKIE = $this->sanitizeArray($_COOKIE);
        
        // Check for suspicious patterns (only if not on exempt pages)
        if (!in_array($this->currentPage, SQL_DETECTION_EXEMPT_PAGES)) {
            $this->detectSQLInjection();
        }
        $this->detectXSS();
    }
    
    private function sanitizeArray($array) {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->sanitizeArray($value);
            } else {
                // Remove null bytes
                $value = str_replace("\0", '', $value);
                $array[$key] = $value;
            }
        }
        return $array;
    }
    
    public static function sanitize($input, $type = 'string') {
        if (is_array($input)) {
            return array_map(function($item) use ($type) {
                return self::sanitize($item, $type);
            }, $input);
        }
        
        // Remove null bytes
        $input = str_replace("\0", '', $input);
        
        switch ($type) {
            case 'email':
                return filter_var($input, FILTER_SANITIZE_EMAIL);
            
            case 'url':
                return filter_var($input, FILTER_SANITIZE_URL);
            
            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            
            case 'float':
                return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            
            case 'html':
                return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            
            case 'sql':
                // Basic SQL sanitization - use prepared statements instead
                return addslashes($input);
            
            case 'filename':
                // Remove path traversal attempts
                $input = basename($input);
                // Remove dangerous characters
                $input = preg_replace('/[^a-zA-Z0-9._-]/', '', $input);
                return $input;
            
            case 'string':
            default:
                return trim($input);
        }
    }
    
    // ────────────────────────────────────────────────────────────────────
    // ATTACK DETECTION
    // ────────────────────────────────────────────────────────────────────
    
    private function detectSQLInjection() {
        // IMPROVED: More precise SQL injection detection
        // Only detect obvious SQL injection attempts, not legitimate queries
        $patterns = [
            '/(\bUNION\b\s+\bALL\b\s+\bSELECT\b)/i',      // UNION ALL SELECT
            '/(\bUNION\b\s+\bSELECT\b.*\bFROM\b)/i',       // UNION SELECT ... FROM
            '/(;\s*DROP\b)/i',                              // ; DROP
            '/(;\s*DELETE\b\s+\bFROM\b)/i',                // ; DELETE FROM
            '/(;\s*UPDATE\b\s+\w+\s+\bSET\b)/i',           // ; UPDATE ... SET
            '/(\bEXEC\b\s*\(|\bEXECUTE\b\s*\()/i',         // EXEC( or EXECUTE(
            '/(\/\*.*\*\/)/i',                              // /* comment */
            '/(\b1\s*=\s*1\b|\b1\s*=\s*1\b--)/i',          // 1=1 or 1=1--
            "/('+\s*OR\s*'+\s*=\s*'+)/i",                  // ' OR '' = '
            '/(\bOR\b\s+\d+\s*=\s*\d+)/i'                   // OR 1=1
        ];
        
        $inputs = array_merge($_GET, $_POST);
        
        // Skip password and email fields as they can contain special characters
        $skipFields = ['password', 'email', 'user_pass', 'userPass'];
        
        foreach ($inputs as $key => $value) {
            // Skip certain fields
            if (in_array($key, $skipFields)) {
                continue;
            }
            
            if (is_string($value) && strlen($value) > 10) { // Only check strings longer than 10 chars
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $this->logSecurity('SQL_INJECTION_ATTEMPT', "Detected in $key: " . substr($value, 0, 100));
                        $this->blockAccess('Suspicious input detected');
                    }
                }
            }
        }
    }
    
    private function detectXSS() {
        // IMPROVED: More precise XSS detection
        $patterns = [
            '/<script[^>]*>.*?<\/script>/is',
            '/javascript\s*:/i',
            '/<iframe[^>]*>/i',
            '/<object[^>]*>/i',
            '/<embed[^>]*>/i',
            '/on(load|error|click|mouse)\s*=/i'
        ];
        
        $inputs = array_merge($_GET, $_POST);
        
        // Skip certain fields that might contain HTML (like email body)
        $skipFields = ['body', 'message', 'content', 'html', 'description'];
        
        foreach ($inputs as $key => $value) {
            // Skip HTML content fields
            if (in_array($key, $skipFields)) {
                continue;
            }
            
            if (is_string($value)) {
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $this->logSecurity('XSS_ATTEMPT', "Detected in $key: " . substr($value, 0, 100));
                        $this->blockAccess('Suspicious input detected');
                    }
                }
            }
        }
    }
    
    // ────────────────────────────────────────────────────────────────────
    // PATH TRAVERSAL PREVENTION
    // ────────────────────────────────────────────────────────────────────
    
    private function preventPathTraversal() {
        $inputs = array_merge($_GET, $_POST);
        
        foreach ($inputs as $key => $value) {
            if (is_string($value)) {
                // Check for path traversal attempts
                if (preg_match('/(\.\.(\/|\\\\))|(\.\.(\/|%2f|%5c))/i', $value)) {
                    $this->logSecurity('PATH_TRAVERSAL_ATTEMPT', "Detected in $key: $value");
                    $this->blockAccess('Invalid path detected');
                }
                
                // Check for null byte injection
                if (strpos($value, "\0") !== false) {
                    $this->logSecurity('NULL_BYTE_INJECTION', "Detected in $key");
                    $this->blockAccess('Invalid input detected');
                }
            }
        }
    }
    
    // ────────────────────────────────────────────────────────────────────
    // FILE UPLOAD SECURITY
    // ────────────────────────────────────────────────────────────────────
    
    public static function validateFileUpload($file) {
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['success' => false, 'error' => 'Invalid file parameters'];
        }
        
        // Check for upload errors
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ['success' => false, 'error' => 'File exceeds maximum size'];
            case UPLOAD_ERR_PARTIAL:
                return ['success' => false, 'error' => 'File was partially uploaded'];
            case UPLOAD_ERR_NO_FILE:
                return ['success' => false, 'error' => 'No file was uploaded'];
            default:
                return ['success' => false, 'error' => 'Upload error occurred'];
        }
        
        // Check file size
        if ($file['size'] > UPLOAD_MAX_SIZE) {
            return ['success' => false, 'error' => 'File too large (max ' . (UPLOAD_MAX_SIZE / 1048576) . 'MB)'];
        }
        
        // Get file extension
        $filename = $file['name'];
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // Check allowed extensions
        if (!in_array($extension, ALLOWED_UPLOAD_EXTENSIONS)) {
            return ['success' => false, 'error' => 'File type not allowed'];
        }
        
        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimes = [
            'image/jpeg', 'image/png', 'image/gif',
            'application/pdf',
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip', 'application/x-zip-compressed',
            'text/plain', 'text/csv'
        ];
        
        if (!in_array($mimeType, $allowedMimes)) {
            return ['success' => false, 'error' => 'Invalid file type (MIME check failed)'];
        }
        
        // Check for PHP code in files (only for text-based files)
        if (in_array($extension, ['txt', 'csv', 'html', 'htm'])) {
            $content = file_get_contents($file['tmp_name'], false, null, 0, 1024);
            if (preg_match('/<\?php/i', $content)) {
                return ['success' => false, 'error' => 'File contains prohibited content'];
            }
        }
        
        return ['success' => true];
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
        
        // Reset counter if window expired
        if ($elapsed > RATE_LIMIT_WINDOW) {
            $_SESSION[$key] = [
                'count' => 1,
                'start_time' => time()
            ];
            return;
        }
        
        // Increment counter
        $_SESSION[$key]['count']++;
        
        // Check if limit exceeded
        if ($_SESSION[$key]['count'] > RATE_LIMIT_REQUESTS) {
            $this->logSecurity('RATE_LIMIT_EXCEEDED', "Too many requests: {$_SESSION[$key]['count']} in {$elapsed}s");
            $this->blockAccess('Too many requests. Please try again later.');
        }
    }
    
    // ────────────────────────────────────────────────────────────────────
    // IP MANAGEMENT
    // ────────────────────────────────────────────────────────────────────
    
    private function getClientIP() {
        $ipKeys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($ipKeys as $key) {
            if (isset($_SERVER[$key]) && filter_var($_SERVER[$key], FILTER_VALIDATE_IP)) {
                return $_SERVER[$key];
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    }
    
    private function checkIPBlacklist() {
        $blacklistFile = SECURITY_LOG_DIR . '/ip_blacklist.txt';
        
        if (!file_exists($blacklistFile)) {
            return;
        }
        
        $blacklist = file($blacklistFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if (in_array($this->clientIP, $blacklist)) {
            $this->logSecurity('BLOCKED_IP_ACCESS', 'Blacklisted IP attempted access');
            $this->blockAccess('Access denied');
        }
    }
    
    public static function blacklistIP($ip, $reason = '') {
        $blacklistFile = SECURITY_LOG_DIR . '/ip_blacklist.txt';
        $entry = date('Y-m-d H:i:s') . " | $ip | $reason\n";
        file_put_contents($blacklistFile, $entry, FILE_APPEND | LOCK_EX);
    }
    
    // ────────────────────────────────────────────────────────────────────
    // BRUTE FORCE PROTECTION (Optional - your login.php has its own)
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
            $minutesLeft = ceil(($_SESSION[$key]['locked_until'] - time()) / 60);
            return [
                'success' => false,
                'locked' => true,
                'message' => "Account temporarily locked. Try again in $minutesLeft minutes."
            ];
        }
        
        if ($success) {
            // Reset on successful login
            $_SESSION[$key] = [
                'count' => 0,
                'locked_until' => 0
            ];
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
        
        error_log($logEntry, 3, SECURITY_LOG_FILE);
        
        // Also log to PHP error log for critical events
        $criticalEvents = [
            'SQL_INJECTION_ATTEMPT',
            'XSS_ATTEMPT',
            'CSRF_ATTACK',
            'SESSION_HIJACK_ATTEMPT',
            'BRUTE_FORCE_DETECTED'
        ];
        
        if (in_array($event, $criticalEvents)) {
            error_log("CRITICAL SECURITY EVENT: $event - $details");
        }
    }
    
    // ────────────────────────────────────────────────────────────────────
    // RESPONSE HANDLERS
    // ────────────────────────────────────────────────────────────────────
    
    private function blockAccess($message = 'Access denied') {
        http_response_code(403);
        
        // If AJAX request, return JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
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