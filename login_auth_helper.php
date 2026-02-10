<?php
/**
 * ============================================================
 * LOGIN AUTHENTICATION HELPER - SECURE & OPTIMIZED
 * ============================================================
 * Features:
 * - Rate limiting & brute force protection
 * - Login activity tracking (separate from inbox)
 * - Session management with security
 * - IP & device fingerprinting
 * - Fast SMTP validation (connection pooling ready)
 * ============================================================
 */

require_once 'db_config.php';

// ============================================================
// SECURITY CONFIGURATION
// ============================================================

// Rate limiting settings
define('MAX_LOGIN_ATTEMPTS', 5);           // Max attempts before block
define('RATE_LIMIT_WINDOW', 900);         // 15 minutes in seconds
define('ACCOUNT_LOCK_DURATION', 1800);    // 30 minutes in seconds
define('SESSION_TIMEOUT', 3600);           // 1 hour in seconds
define('REMEMBER_ME_DURATION', 2592000);   // 30 days in seconds

// Security headers
define('SECURE_COOKIE', true);             // Use secure cookies (HTTPS only)
define('HTTPONLY_COOKIE', true);           // Prevent XSS cookie access
define('SAMESITE_COOKIE', 'Strict');       // CSRF protection

// ============================================================
// RATE LIMITING & BRUTE FORCE PROTECTION
// ============================================================

/**
 * Check if IP/email combination is rate limited
 * @return array ['allowed' => bool, 'attempts' => int, 'block_until' => timestamp|null]
 */
function checkRateLimit($email, $ipAddress) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            error_log("Rate limit check failed: No DB connection");
            return ['allowed' => true, 'attempts' => 0, 'block_until' => null];
        }
        
        // Check for active block
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as blocked_count, MAX(block_until) as block_until
            FROM failed_login_attempts
            WHERE email = :email 
            AND ip_address = :ip
            AND is_blocked = 1
            AND block_until > NOW()
        ");
        
        $stmt->execute([
            ':email' => $email,
            ':ip' => $ipAddress
        ]);
        
        $blockCheck = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($blockCheck['blocked_count'] > 0) {
            return [
                'allowed' => false,
                'attempts' => MAX_LOGIN_ATTEMPTS,
                'block_until' => $blockCheck['block_until']
            ];
        }
        
        // Count recent failed attempts
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as attempt_count
            FROM failed_login_attempts
            WHERE email = :email 
            AND ip_address = :ip
            AND attempt_timestamp > DATE_SUB(NOW(), INTERVAL :window SECOND)
        ");
        
        $stmt->execute([
            ':email' => $email,
            ':ip' => $ipAddress,
            ':window' => RATE_LIMIT_WINDOW
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $attempts = $result['attempt_count'] ?? 0;
        
        return [
            'allowed' => $attempts < MAX_LOGIN_ATTEMPTS,
            'attempts' => $attempts,
            'block_until' => null
        ];
        
    } catch (PDOException $e) {
        error_log("Rate limit check error: " . $e->getMessage());
        return ['allowed' => true, 'attempts' => 0, 'block_until' => null];
    }
}

/**
 * Record failed login attempt
 */
function recordFailedAttempt($email, $ipAddress, $reason = 'Invalid credentials') {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            error_log("CRITICAL: Cannot record failed attempt - no DB connection");
            return false;
        }
        
        // Check if failed_login_attempts table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'failed_login_attempts'");
        if ($stmt->rowCount() === 0) {
            error_log("ERROR: failed_login_attempts table does not exist! Run migration_login_activity.sql");
            return false;
        }
        
        // Get current attempt count
        $rateLimit = checkRateLimit($email, $ipAddress);
        $newAttemptCount = $rateLimit['attempts'] + 1;
        
        // Determine if should block
        $shouldBlock = $newAttemptCount >= MAX_LOGIN_ATTEMPTS;
        $blockUntil = $shouldBlock ? date('Y-m-d H:i:s', time() + ACCOUNT_LOCK_DURATION) : null;
        
        // Record the attempt
        $stmt = $pdo->prepare("
            INSERT INTO failed_login_attempts 
            (email, ip_address, attempt_timestamp, failure_reason, is_blocked, block_until)
            VALUES (:email, :ip, NOW(), :reason, :blocked, :block_until)
        ");
        
        $success = $stmt->execute([
            ':email' => $email,
            ':ip' => $ipAddress,
            ':reason' => $reason,
            ':blocked' => $shouldBlock ? 1 : 0,
            ':block_until' => $blockUntil
        ]);
        
        if ($success) {
            error_log("✓ Failed attempt recorded: Email=$email, IP=$ipAddress, Attempt=$newAttemptCount, Blocked=" . ($shouldBlock ? 'YES' : 'NO'));
            
            if ($shouldBlock) {
                error_log("🚨 SECURITY: Account $email LOCKED from IP $ipAddress until $blockUntil");
            }
        } else {
            error_log("✗ Failed to record failed attempt for $email");
        }
        
        return $success;
        
    } catch (PDOException $e) {
        error_log("EXCEPTION in recordFailedAttempt: " . $e->getMessage());
        return false;
    }
}

/**
 * Clear failed attempts after successful login
 */
function clearFailedAttempts($email, $ipAddress) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;
        
        $stmt = $pdo->prepare("
            DELETE FROM failed_login_attempts
            WHERE email = :email AND ip_address = :ip
        ");
        
        return $stmt->execute([
            ':email' => $email,
            ':ip' => $ipAddress
        ]);
        
    } catch (PDOException $e) {
        error_log("Failed to clear attempts: " . $e->getMessage());
        return false;
    }
}

// ============================================================
// LOGIN ACTIVITY TRACKING
// ============================================================

/**
 * Record successful login activity
 * @return int|null Login activity ID
 */
function recordLoginActivity($email, $userId, $status = 'success', $failureReason = null) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            error_log("CRITICAL: Cannot record login activity - no DB connection");
            return null;
        }
        
        $ipAddress = getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $sessionId = session_id();
        $deviceFingerprint = generateDeviceFingerprint();
        
        // Optional: Get geolocation (requires external API)
        // $location = getGeoLocation($ipAddress);
        
        // Check if login_activity table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'login_activity'");
        if ($stmt->rowCount() === 0) {
            error_log("ERROR: login_activity table does not exist! Run migration_login_activity.sql");
            return null;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO login_activity 
            (user_id, email, login_timestamp, login_status, failure_reason,
             ip_address, user_agent, session_id, device_fingerprint)
            VALUES (:user_id, :email, NOW(), :status, :failure_reason,
                    :ip, :user_agent, :session_id, :fingerprint)
        ");
        
        $success = $stmt->execute([
            ':user_id' => $userId,
            ':email' => $email,
            ':status' => $status,
            ':failure_reason' => $failureReason,
            ':ip' => $ipAddress,
            ':user_agent' => $userAgent,
            ':session_id' => $sessionId,
            ':fingerprint' => $deviceFingerprint
        ]);
        
        if (!$success) {
            error_log("ERROR: Failed to insert login activity for $email");
            return null;
        }
        
        $loginActivityId = $pdo->lastInsertId();
        
        if ($loginActivityId) {
            error_log("✓ Login activity recorded: ID=$loginActivityId, Email=$email, Status=$status, IP=$ipAddress");
        } else {
            error_log("WARNING: Login activity executed but no ID returned for $email");
        }
        
        // Check for suspicious activity only on success
        if ($status === 'success') {
            checkSuspiciousLogin($loginActivityId, $email, $ipAddress);
        }
        
        return $loginActivityId;
        
    } catch (PDOException $e) {
        error_log("EXCEPTION in recordLoginActivity: " . $e->getMessage());
        error_log("SQL Error Code: " . $e->getCode());
        return null;
    }
}

/**
 * Record logout activity
 */
function recordLogoutActivity($sessionId, $reason = 'user_logout') {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;
        
        // Update login activity
        $stmt = $pdo->prepare("
            UPDATE login_activity
            SET logout_timestamp = NOW(),
                session_duration = TIMESTAMPDIFF(SECOND, login_timestamp, NOW())
            WHERE session_id = :session_id
            AND logout_timestamp IS NULL
            ORDER BY login_timestamp DESC
            LIMIT 1
        ");
        
        $stmt->execute([':session_id' => $sessionId]);
        
        // Update user_sessions if exists
        $stmt = $pdo->prepare("
            UPDATE user_sessions
            SET is_active = 0, logout_reason = :reason
            WHERE session_id = :session_id
        ");
        
        $stmt->execute([
            ':session_id' => $sessionId,
            ':reason' => $reason
        ]);
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Failed to record logout: " . $e->getMessage());
        return false;
    }
}

// ============================================================
// SESSION MANAGEMENT
// ============================================================

/**
 * Create user session with security
 */
function createUserSession($userId, $loginActivityId) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            error_log("CRITICAL: Cannot create session - no DB connection");
            return false;
        }
        
        $sessionId = session_id();
        $ipAddress = getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        // Fixed session timeout - always 1 hour
        $expiresAt = date('Y-m-d H:i:s', time() + SESSION_TIMEOUT);
        
        // Check if user_sessions table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'user_sessions'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO user_sessions
                (user_id, session_id, login_activity_id, ip_address, user_agent, 
                 created_at, last_activity, expires_at, is_active)
                VALUES (:user_id, :session_id, :login_id, :ip, :user_agent,
                        NOW(), NOW(), :expires_at, 1)
            ");
            
            $success = $stmt->execute([
                ':user_id' => $userId,
                ':session_id' => $sessionId,
                ':login_id' => $loginActivityId,
                ':ip' => $ipAddress,
                ':user_agent' => $userAgent,
                ':expires_at' => $expiresAt
            ]);
            
            if ($success) {
                error_log("✓ Session created for user $userId (session: $sessionId)");
            } else {
                error_log("✗ Failed to create session for user $userId");
            }
            
            return $success;
        } else {
            error_log("WARNING: user_sessions table does not exist - session not tracked");
            return true; // Don't fail login if table missing
        }
        
    } catch (PDOException $e) {
        error_log("Failed to create session: " . $e->getMessage());
        return false; // Don't fail login on session creation error
    }
}

/**
 * Validate session is still active
 */
function validateSession($sessionId) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;
        
        $stmt = $pdo->prepare("
            SELECT user_id, expires_at
            FROM user_sessions
            WHERE session_id = :session_id
            AND is_active = 1
        ");
        
        $stmt->execute([':session_id' => $sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            return false;
        }
        
        // Check expiration
        if (strtotime($session['expires_at']) < time()) {
            recordLogoutActivity($sessionId, 'timeout');
            return false;
        }
        
        // Update last activity
        $stmt = $pdo->prepare("
            UPDATE user_sessions
            SET last_activity = NOW()
            WHERE session_id = :session_id
        ");
        $stmt->execute([':session_id' => $sessionId]);
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Session validation error: " . $e->getMessage());
        return false;
    }
}

// ============================================================
// SECURITY UTILITIES
// ============================================================

/**
 * Get client IP address (supports proxy/CDN)
 */
function getClientIP() {
    $ipHeaders = [
        'HTTP_CF_CONNECTING_IP',    // Cloudflare
        'HTTP_X_FORWARDED_FOR',     // Proxy
        'HTTP_X_REAL_IP',           // Nginx
        'REMOTE_ADDR'               // Direct
    ];
    
    foreach ($ipHeaders as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            // Handle comma-separated IPs (take first one)
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            // Validate IP
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    
    return '0.0.0.0';
}

/**
 * Generate device fingerprint for tracking
 */
function generateDeviceFingerprint() {
    $components = [
        $_SERVER['HTTP_USER_AGENT'] ?? '',
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
        $_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''
    ];
    
    return hash('sha256', implode('|', $components));
}

/**
 * Check for suspicious login patterns
 */
function checkSuspiciousLogin($loginActivityId, $email, $ipAddress) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;
        
        // Check for multiple IPs in short time
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT ip_address) as ip_count
            FROM login_activity
            WHERE email = :email
            AND login_timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            AND login_status = 'success'
        ");
        
        $stmt->execute([':email' => $email]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['ip_count'] > 3) {
            // Flag as suspicious
            $stmt = $pdo->prepare("
                UPDATE login_activity
                SET is_suspicious = 1
                WHERE id = :id
            ");
            $stmt->execute([':id' => $loginActivityId]);
            
            error_log("SECURITY WARNING: Multiple IPs detected for $email");
        }
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Suspicious login check error: " . $e->getMessage());
        return false;
    }
}

/**
 * Secure session initialization
 */
function initializeSecureSession() {
    // Prevent session fixation
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', HTTPONLY_COOKIE);
        ini_set('session.cookie_secure', SECURE_COOKIE);
        ini_set('session.cookie_samesite', SAMESITE_COOKIE);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.use_only_cookies', 1);
        
        session_start();
        
        // Regenerate session ID on login
        if (!isset($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
            $_SESSION['created_at'] = time();
        }
        
        // Session timeout check
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
            session_unset();
            session_destroy();
            session_start();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
    }
    
    return true;
}

/**
 * Clean up expired sessions (run via cron)
 */
function cleanupExpiredSessions() {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;
        
        // Mark expired sessions as inactive
        $stmt = $pdo->prepare("
            UPDATE user_sessions
            SET is_active = 0, logout_reason = 'timeout'
            WHERE expires_at < NOW()
            AND is_active = 1
        ");
        $stmt->execute();
        
        // Delete old failed login attempts
        $stmt = $pdo->prepare("
            DELETE FROM failed_login_attempts
            WHERE attempt_timestamp < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stmt->execute();
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Session cleanup error: " . $e->getMessage());
        return false;
    }
}

?>