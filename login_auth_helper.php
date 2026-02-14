<?php
/**
 * ============================================================
 * LOGIN AUTHENTICATION HELPER - DATABASE AUTH VERSION
 * ============================================================
 * Features:
 * - Database-based password authentication
 * - Rate limiting & brute force protection
 * - Login activity tracking (separate from inbox)
 * - Session management with security
 * - IP & device fingerprinting
 * - SMTP credentials stored in ENV for email sending
 * ============================================================
 */
require_once 'db_config.php';

// ============================================================
// SECURITY CONFIGURATION
// ============================================================

// Rate limiting settings
// define('MAX_LOGIN_ATTEMPTS', 5);           // Max attempts before block
// define('RATE_LIMIT_WINDOW', 900);         // 15 minutes in seconds
// define('ACCOUNT_LOCK_DURATION', 1800);    // 30 minutes in seconds
// define('SESSION_TIMEOUT', 3600);           // 1 hour in seconds
define('REMEMBER_ME_DURATION', 2592000);   // 30 days in seconds

// Password policy
define('MIN_PASSWORD_LENGTH', 8);
define('REQUIRE_UPPERCASE', true);
define('REQUIRE_LOWERCASE', true);
define('REQUIRE_NUMBER', true);
define('REQUIRE_SPECIAL', true);

// Security headers
define('SECURE_COOKIE', true);             // Use secure cookies (HTTPS only)
define('HTTPONLY_COOKIE', true);           // Prevent XSS cookie access
define('SAMESITE_COOKIE', 'Strict');       // CSRF protection
define('ACCOUNT_LOCK_DURATION',10);
// ============================================================
// DATABASE AUTHENTICATION
// ============================================================

/**
 * Authenticate user with database credentials
 * @param string $email User email
 * @param string $password User password (plain text)
 * @return array ['success' => bool, 'user' => array|null, 'error' => string|null]
 */
function authenticateWithDatabase($email, $password) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            return [
                'success' => false,
                'user' => null,
                'error' => 'Database connection failed'
            ];
        }
        
        // Get user from database
        $stmt = $pdo->prepare("
            SELECT 
                id,
                user_uuid,
                email,
                full_name,
                password_hash,
                is_active,
                is_admin,
                require_password_change,
                failed_login_count,
                account_locked_until
            FROM users
            WHERE email = :email
        ");
        
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Check if user exists
        if (!$user) {
            error_log("LOGIN FAILED: User not found - $email");
            return [
                'success' => false,
                'user' => null,
                'error' => 'Invalid credentials'
            ];
        }
        
        // Check if account is active
        if (!$user['is_active']) {
            error_log("LOGIN FAILED: Account inactive - $email");
            return [
                'success' => false,
                'user' => null,
                'error' => 'Account is inactive'
            ];
        }
        
        // Check if account is locked
        if ($user['account_locked_until'] && strtotime($user['account_locked_until']) > time()) {
            $minutesLeft = ceil((strtotime($user['account_locked_until']) - time()) / 60);
            error_log("LOGIN FAILED: Account locked - $email (locked for $minutesLeft minutes)");
            return [
                'success' => false,
                'user' => null,
                'error' => "Account locked for $minutesLeft minutes"
            ];
        }
        
        // Check if password is set
        if (empty($user['password_hash'])) {
            error_log("LOGIN FAILED: No password set - $email");
            return [
                'success' => false,
                'user' => null,
                'error' => 'No password set for this account'
            ];
        }
        
        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            // Increment failed login count
            incrementFailedLoginCount($pdo, $user['id']);
            
            error_log("LOGIN FAILED: Invalid password - $email");
            return [
                'success' => false,
                'user' => null,
                'error' => 'Invalid credentials'
            ];
        }
        
        // Check if password needs rehashing (if algorithm changed)
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                UPDATE users 
                SET password_hash = :hash, 
                    password_updated_at = NOW(),
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':hash' => $newHash,
                ':id' => $user['id']
            ]);
        }
        
        // Reset failed login count and unlock account
        resetFailedLoginCount($pdo, $user['id']);
        
        // Remove sensitive data before returning
        unset($user['password_hash']);
        
        error_log("✓ LOGIN SUCCESS: $email (User ID: {$user['id']})");
        
        return [
            'success' => true,
            'user' => $user,
            'error' => null
        ];
        
    } catch (PDOException $e) {
        error_log("DATABASE AUTH ERROR: " . $e->getMessage());
        return [
            'success' => false,
            'user' => null,
            'error' => 'Authentication system error'
        ];
    }
}

/**
 * Increment failed login count and lock account if needed
 */
function incrementFailedLoginCount($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET failed_login_count = failed_login_count + 1,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([':id' => $userId]);
        
        // Get updated count
        $stmt = $pdo->prepare("SELECT failed_login_count FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $failedCount = $result['failed_login_count'] ?? 0;
        
        // Lock account if max attempts reached
        if ($failedCount >= MAX_LOGIN_ATTEMPTS) {
            $lockUntil = date('Y-m-d H:i:s', time() + ACCOUNT_LOCK_DURATION);
            $stmt = $pdo->prepare("
                UPDATE users 
                SET account_locked_until = :lock_until,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':lock_until' => $lockUntil,
                ':id' => $userId
            ]);
            
            error_log("🚨 ACCOUNT LOCKED: User ID $userId locked until $lockUntil");
        }
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Failed to increment login count: " . $e->getMessage());
        return false;
    }
}

/**
 * Reset failed login count after successful login
 */
function resetFailedLoginCount($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET failed_login_count = 0,
                account_locked_until = NULL,
                updated_at = NOW()
            WHERE id = :id
        ");
        return $stmt->execute([':id' => $userId]);
        
    } catch (PDOException $e) {
        error_log("Failed to reset login count: " . $e->getMessage());
        return false;
    }
}

/**
 * Get SMTP credentials from environment for email sending
 * These are used ONLY for sending emails, not for authentication
 */
function getSmtpCredentials() {
    return [
        'host' => env('SMTP_HOST'),
        'port' => env('SMTP_PORT'),
        'username' => env('SMTP_USERNAME'),
        'password' => env('SMTP_PASSWORD'),
        'encryption' => env('SMTP_ENCRYPTION', 'ssl')
    ];
}

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
            error_log("ERROR: Login activity inserted but no ID returned for $email");
        }
        
        // Check for suspicious patterns
        if ($status === 'success' && $loginActivityId) {
            checkSuspiciousLogin($loginActivityId, $email, $ipAddress);
        }
        
        return $loginActivityId;
        
    } catch (PDOException $e) {
        error_log("Failed to record login activity: " . $e->getMessage());
        return null;
    }
}

/**
 * Get user ID by email
 */
// function getUserId($pdo, $email) {
//     try {
//         $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
//         $stmt->execute([':email' => $email]);
//         $result = $stmt->fetch(PDO::FETCH_ASSOC);
//         return $result ? $result['id'] : null;
//     } catch (PDOException $e) {
//         error_log("Failed to get user ID: " . $e->getMessage());
//         return null;
//     }
// }

/**
 * Record logout activity
 */
function recordLogoutActivity($sessionId, $reason = 'user_logout') {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;
        
        $stmt = $pdo->prepare("
            UPDATE user_sessions
            SET is_active = 0,
                logout_reason = :reason
            WHERE session_id = :session_id
        ");
        
        return $stmt->execute([
            ':reason' => $reason,
            ':session_id' => $sessionId
        ]);
        
    } catch (PDOException $e) {
        error_log("Failed to record logout: " . $e->getMessage());
        return false;
    }
}

// ============================================================
// SESSION MANAGEMENT
// ============================================================

/**
 * Create user session record in database
 */
function createUserSession($userId, $loginActivityId = null) {
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
 * Load IMAP configuration to session
 * Uses system SMTP credentials from ENV
 */
// function loadImapConfigToSession($userEmail, $userPassword = null) {
//     // Get system credentials from environment
//     $smtpCreds = getSmtpCredentials();
    
//     $_SESSION['imap_config'] = [
//         'host' => env('IMAP_HOST', $smtpCreds['host']),
//         'port' => env('IMAP_PORT', 993),
//         'username' => $userEmail,
//         'password' => $userPassword ?? $smtpCreds['password'],
//         'encryption' => env('IMAP_ENCRYPTION', 'ssl'),
//         'validate_cert' => env('IMAP_VALIDATE_CERT', false)
//     ];
    
//     return true;
// }

/**
 * Create user if not exists
 */
// function createUserIfNotExists($pdo, $email, $fullName = null) {
//     try {
//         // Check if user exists
//         $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
//         $stmt->execute([':email' => $email]);
//         $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
//         if ($user) {
//             return $user['id'];
//         }
        
//         // Create new user
//         $uuid = sprintf(
//             '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
//             mt_rand(0, 0xffff), mt_rand(0, 0xffff),
//             mt_rand(0, 0xffff),
//             mt_rand(0, 0x0fff) | 0x4000,
//             mt_rand(0, 0x3fff) | 0x8000,
//             mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
//         );
        
//         $stmt = $pdo->prepare("
//             INSERT INTO users (user_uuid, email, full_name, is_active, created_at, updated_at)
//             VALUES (:uuid, :email, :name, 1, NOW(), NOW())
//         ");
        
//         $stmt->execute([
//             ':uuid' => $uuid,
//             ':email' => $email,
//             ':name' => $fullName
//         ]);
        
//         return $pdo->lastInsertId();
        
//     } catch (PDOException $e) {
//         error_log("Failed to create user: " . $e->getMessage());
//         return null;
//     }
// }

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