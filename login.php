<?php
require_once 'config.php';
require_once 'db_config.php';
require_once 'login_auth_helper.php';

// Load environment variables
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ============================================================
// SECURITY HEADERS - Set before any output
// ============================================================
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");

// Content Security Policy
$csp = "default-src 'none'; ";
$csp .= "script-src 'self' 'unsafe-inline'; ";
$csp .= "style-src 'self' 'unsafe-inline'; ";
$csp .= "img-src 'self' data:; ";
$csp .= "font-src 'self'; ";
$csp .= "connect-src 'self'; ";
$csp .= "base-uri 'self'; ";
$csp .= "form-action 'self'; ";
$csp .= "frame-ancestors 'none';";
header("Content-Security-Policy: $csp");

// Initialize secure session
initializeSecureSession();

// Redirect if already logged in
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    header("Location: index.php");
    exit();
}

// ============================================================
// CSRF TOKEN GENERATION
// ============================================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if (empty($_SESSION['csrf_token_time'])) {
    $_SESSION['csrf_token_time'] = time();
}

// Regenerate CSRF token every 15 minutes
if (time() - $_SESSION['csrf_token_time'] > 900) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();
}

$error = "";
$loginAttempts = 0;
$blockUntil = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // ============================================================
    // ENHANCED SECURITY VALIDATION
    // ============================================================
    
    // 1. CSRF Token Validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        error_log("SECURITY: CSRF token validation failed from IP " . getClientIP());
        $error = "Security validation failed. Please refresh and try again.";
        http_response_code(403);
    }
    // 2. Honeypot Check (anti-bot)
    elseif (!empty($_POST['website'])) {
        error_log("SECURITY: Honeypot triggered from IP " . getClientIP());
        sleep(2); // Delay to waste bot's time
        $error = "Invalid request detected.";
    }
    // 3. Request timing check (prevent replay attacks)
    elseif (!isset($_POST['timestamp']) || abs(time() - intval($_POST['timestamp'])) > 300) {
        $error = "Request expired. Please try again.";
    }
    else {
        $userEmail = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $userPass = $_POST['app_password'] ?? '';
        
        // Validate email format
        if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        }
        // Validate input
        elseif (empty($userEmail) || empty($userPass)) {
            $error = "Email and password are required.";
        }
        // Check password length (basic validation)
        elseif (strlen($userPass) < 8 || strlen($userPass) > 128) {
            $error = "Invalid password format.";
        }
        else {
            // Get client IP with proxy support
            $ipAddress = getClientIP();
            
            // Check rate limiting
            $rateLimit = checkRateLimit($userEmail, $ipAddress);
            
            if (!$rateLimit['allowed']) {
                $blockUntilTime = strtotime($rateLimit['block_until']);
                $remainingMinutes = ceil(($blockUntilTime - time()) / 60);
                
                $error = "Too many failed attempts. Account temporarily locked. Please try again in $remainingMinutes minutes.";
                $loginAttempts = $rateLimit['attempts'];
                $blockUntil = $rateLimit['block_until'];
                
                error_log("SECURITY: Login blocked for $userEmail from IP $ipAddress");
                http_response_code(429); // Too Many Requests
            } else {
                // Attempt SMTP authentication (FAST - no email sending)
                $authResult = authenticateWithSMTP($userEmail, $userPass);
                
                if ($authResult['success']) {
                    // ============================================================
                    // SUCCESSFUL LOGIN
                    // ============================================================
                    
                    // Clear failed attempts
                    clearFailedAttempts($userEmail, $ipAddress);
                    
                    // Create/get user in database
                    $pdo = getDatabaseConnection();
                    $userId = createUserIfNotExists($pdo, $userEmail, null);
                    
                    if (!$userId) {
                        $error = "System error. Please contact administrator.";
                        error_log("CRITICAL: Failed to create user for $userEmail");
                    } else {
                        // Record login activity (NO EMAIL SENT)
                        $loginActivityId = recordLoginActivity($userEmail, $userId, 'success');
                        
                        // Set session variables with secure attributes
                        $_SESSION['smtp_user'] = $userEmail;
                        $_SESSION['smtp_pass'] = $userPass;
                        $_SESSION['authenticated'] = true;
                        $_SESSION['user_id'] = $userId;
                        $_SESSION['login_time'] = time();
                        $_SESSION['ip_address'] = $ipAddress;
                        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
                        $_SESSION['last_activity'] = time();
                        
                        // Regenerate session ID for security (prevent session fixation)
                        session_regenerate_id(true);
                        
                        // Create session record
                        createUserSession($userId, $loginActivityId);
                        
                        // Load IMAP config
                        loadImapConfigToSession($userEmail, $userPass);
                        
                        // Check user role
                        $superAdmins = ['admin@sxccal.edu', 'hod@sxccal.edu'];
                        $_SESSION['user_role'] = in_array($userEmail, $superAdmins) ? 'super_admin' : 'user';
                        
                        // Load user settings
                        if (file_exists('settings_helper.php')) {
                            require_once 'settings_helper.php';
                        }
                        
                        // Success - redirect
                        header("Location: index.php");
                        exit();
                    }
                } else {
                    // ============================================================
                    // FAILED LOGIN
                    // ============================================================
                    
                    // Add delay to prevent timing attacks and brute force
                    usleep(rand(100000, 500000)); // Random delay 100-500ms
                    
                    // Record failed attempt
                    recordFailedAttempt($userEmail, $ipAddress, $authResult['error']);
                    
                    // Record in login activity
                    $pdo = getDatabaseConnection();
                    $userId = getUserId($pdo, $userEmail);
                    recordLoginActivity($userEmail, $userId, 'failed', $authResult['error']);
                    
                    // Check if now blocked
                    $rateLimit = checkRateLimit($userEmail, $ipAddress);
                    $loginAttempts = $rateLimit['attempts'];
                    
                    if (!$rateLimit['allowed']) {
                        $blockUntilTime = strtotime($rateLimit['block_until']);
                        $remainingMinutes = ceil(($blockUntilTime - time()) / 60);
                        $error = "Too many failed attempts. Account locked for $remainingMinutes minutes.";
                    } else {
                        $remaining = MAX_LOGIN_ATTEMPTS - $loginAttempts;
                        $error = "Authentication failed. Please verify your credentials. ($remaining attempts remaining)";
                    }
                    
                    error_log("LOGIN FAILED: $userEmail from IP $ipAddress - {$authResult['error']}");
                }
            }
        }
    }
}

/**
 * Fast SMTP authentication (NO EMAIL SENDING)
 * Only validates credentials - optimized for speed
 */
function authenticateWithSMTP($email, $password) {
    $mail = new PHPMailer(true);
    
    try {
        // Configure SMTP with optimizations
        $mail->isSMTP();
        $mail->Host = env("SMTP_HOST");
        $mail->SMTPAuth = true;
        $mail->Username = $email;
        $mail->Password = $password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = env("SMTP_PORT");
        $mail->Timeout = 8; // Fast timeout
        $mail->SMTPDebug = 0; // No debug output
        $mail->SMTPKeepAlive = false; // Don't keep connection
        $mail->SMTPAutoTLS = false; // Disable auto TLS
        
        // Test connection (doesn't send email)
        $mail->smtpConnect();
        $mail->smtpClose();
        
        return [
            'success' => true,
            'error' => null
        ];
        
    } catch (Exception $e) {
        $errorMsg = $mail->ErrorInfo;
        
        // Categorize error
        if (strpos($errorMsg, 'authenticate') !== false || 
            strpos($errorMsg, 'credentials') !== false ||
            strpos($errorMsg, 'password') !== false) {
            $category = 'Invalid credentials';
        } elseif (strpos($errorMsg, 'connect') !== false || 
                  strpos($errorMsg, 'timeout') !== false) {
            $category = 'Connection failed';
        } else {
            $category = 'SMTP error';
        }
        
        return [
            'success' => false,
            'error' => $category
        ];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>SXC MDTS | Secure Login</title>
    
    <!-- PERFORMANCE OPTIMIZATIONS -->
    <!-- DNS Prefetch for faster external resource loading -->
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link rel="preconnect" href="//fonts.gstatic.com" crossorigin>
    
    <!-- Preload critical assets -->
    <link rel="preload" href="Assets/image/sxc_logo.png" as="image" type="image/png">
    
    <!-- Security -->
    <meta name="referrer" content="strict-origin-when-cross-origin">
    
    <!-- Critical CSS - Inlined for fastest render -->
    <style>
        /* CRITICAL CSS - Loads immediately for instant render */
        :root{--primary:#4f5d73;--error:#dc3545;--warning:#ff9800;--success:#2d5a27}*{margin:0;padding:0;box-sizing:border-box}body,html{height:100%;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#ebebf0;background-image:radial-gradient(#e5e7eb 1px,transparent 1px);background-size:40px 40px;position:relative}body::before{content:'';position:fixed;top:0;left:0;width:100%;height:100%;background:radial-gradient(circle at 50% 30%,rgba(0,0,0,.02),transparent 60%);pointer-events:none;z-index:0}.page-wrapper{display:flex;justify-content:center;align-items:center;min-height:100vh;padding:20px;position:relative;z-index:1}.login-card{background:#fff;padding:45px 40px;border-radius:22px;box-shadow:0 8px 24px rgba(0,0,0,.06),0 2px 6px rgba(0,0,0,.04);width:100%;max-width:420px;opacity:0;transform:translateY(8px);animation:cardFadeIn 350ms ease-out forwards;animation-delay:100ms;border:.1px solid #cad2df}@keyframes cardFadeIn{to{opacity:1;transform:translateY(0)}}.brand-header{display:flex;align-items:center;gap:14px;margin-bottom:32px;padding-bottom:24px;border-bottom:1px solid #f0f0f0}.brand-logo{width:52px;height:52px;object-fit:contain;flex-shrink:0}.brand-details{font-size:.78rem;line-height:1.15;font-weight:400;color:#727fa4;opacity:.65;letter-spacing:.2px}h2{font-size:1.73rem;font-weight:500;margin-bottom:28px;color:#727fa4;letter-spacing:.3px}.error-toast,.warning-toast{padding:14px 16px;border-radius:10px;margin-bottom:20px;font-size:.85rem;line-height:1.5;opacity:0;animation:slideInRight .4s ease-out forwards}.error-toast{background:#fef2f2;border-left:3px solid var(--error);color:#991b1b}.warning-toast{background:#fff7ed;border-left:3px solid var(--warning);color:#9a3412}@keyframes slideInRight{from{opacity:0;transform:translateX(10px)}to{opacity:1;transform:translateX(0)}}.input-group{margin-bottom:20px}label{display:block;font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:1.2px;color:#999;margin-bottom:8px}input[type=email],input[type=password],input[type=text]{width:100%;padding:12px 5px;border:none;border-bottom:2px solid #a0a8b6;background:0 0;font-size:1rem;transition:border-color 180ms ease,transform 150ms ease;outline:0}input:focus{border-bottom-color:#4f5d73;transform:scaleY(1.02);transform-origin:bottom}input:disabled{opacity:.6;cursor:not-allowed}.checkbox-container{display:flex;align-items:center;gap:8px;margin-top:12px;font-size:.85rem;color:#666;cursor:pointer;user-select:none}.checkbox-container input[type=checkbox]{width:auto;cursor:pointer}button{width:100%;padding:16px;background:#4f5d73;color:#fff;border:none;border-radius:6px;font-weight:500;text-transform:uppercase;letter-spacing:2.5px;cursor:pointer;margin-top:25px;transition:all 180ms ease;font-size:.9rem}button:hover:not(:disabled){box-shadow:0 6px 18px rgba(0,0,0,.15)}button:active:not(:disabled){transform:translateY(0)}button:disabled{background:#ccc;cursor:not-allowed;transform:none}footer{margin-top:22px;padding-top:20px;border-top:1px solid #f0f0f0;font-size:.65rem;color:#bbb;opacity:.6;text-align:center}footer span{font-size:.95rem}@media (max-width:480px){.login-card{padding:30px 20px}h2{font-size:1.5rem}}
    </style>
    
    <!-- Deferred non-critical CSS -->
    <noscript>
        <link rel="stylesheet" href="styles/login.css">
    </noscript>
</head>
<body>
    <div class="page-wrapper">
        <div class="login-card">
            <div class="brand-header">
                <img src="Assets/image/sxc_logo.png" alt="SXC Logo" class="brand-logo" width="52" height="52" loading="eager">
                <div class="brand-details">
                    Autonomous College (2006) | CPE (2006) |
                    CE (2014), NAAC A++ | 4th Cycle (2024) |
                    ISO 9001:2015 | NIRF 2025: 8th Position
                </div>
            </div>

            <h2>Authentication</h2>

            <?php if ($error): ?>
                <div class="error-toast" role="alert">
                    🔒 <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error']) && $_GET['error'] === 'session_expired'): ?>
                <div class="warning-toast" role="alert">
                    ⏱️ Your session has expired. Please login again.
                </div>
            <?php endif; ?>

            <?php if ($loginAttempts > 0 && $loginAttempts < MAX_LOGIN_ATTEMPTS): ?>
                <div class="warning-toast" role="alert">
                    ⚠️ Failed attempts: <?php echo $loginAttempts; ?>/<?php echo MAX_LOGIN_ATTEMPTS; ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="loginForm" autocomplete="on">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                
                <!-- Timestamp for replay attack prevention -->
                <input type="hidden" name="timestamp" id="timestamp" value="<?php echo time(); ?>">
                
                <!-- Honeypot field (hidden from users, but visible to bots) -->
                <div style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden" aria-hidden="true">
                    <label for="website">Website</label>
                    <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
                </div>

                <div class="input-group">
                    <label for="email">User ID / Email</label>
                    <input 
                        type="email" 
                        name="email" 
                        id="email" 
                        placeholder="user@sxccal.edu" 
                        required
                        <?php echo ($blockUntil ? 'disabled' : ''); ?>
                        autocomplete="email"
                        autofocus
                        maxlength="100"
                        pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}"
                        aria-label="Email address"
                    >
                </div>

                <div class="input-group">
                    <label for="app_password">App Password</label>
                    <input 
                        type="password" 
                        name="app_password" 
                        id="app_password" 
                        placeholder="••••••••••••" 
                        required
                        <?php echo ($blockUntil ? 'disabled' : ''); ?>
                        autocomplete="current-password"
                        minlength="8"
                        maxlength="128"
                        aria-label="Password"
                    >
                    
                    <label class="checkbox-container">
                        <input type="checkbox" id="toggleCheck" aria-label="Show password">
                        <span>Show Password</span>
                    </label>
                </div>

                <button 
                    type="submit" 
                    id="submitBtn"
                    <?php echo ($blockUntil ? 'disabled' : ''); ?>
                    aria-label="Submit login form"
                >
                    <?php echo ($blockUntil ? 'Account Locked' : 'Verify & Proceed'); ?>
                </button>
            </form>
            
            <footer>
                <br>
                <span style="font-size:18px;color:#a3abc3;">Made with ♥︎ by MDTS Students</span>
            </footer>
        </div>
    </div>

    <!-- OPTIMIZED JAVASCRIPT - Deferred for faster page load -->
    <script>
        // Password toggle with event delegation
        (function() {
            'use strict';
            
            const toggleCheck = document.getElementById('toggleCheck');
            const passInput = document.getElementById('app_password');
            
            if (toggleCheck && passInput) {
                toggleCheck.addEventListener('change', function() {
                    passInput.type = this.checked ? 'text' : 'password';
                }, { passive: true });
            }
            
            // Update timestamp before form submission
            const form = document.getElementById('loginForm');
            const submitBtn = document.getElementById('submitBtn');
            
            if (form && submitBtn) {
                form.addEventListener('submit', function(e) {
                    // Update timestamp
                    document.getElementById('timestamp').value = Math.floor(Date.now() / 1000);
                    
                    // Disable button to prevent double submission
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Authenticating...';
                }, { once: true });
            }
            
            // Auto-unlock countdown if blocked
            <?php if ($blockUntil): ?>
            const blockUntil = new Date("<?php echo $blockUntil; ?>").getTime();
            
            const countdown = setInterval(function() {
                const now = Date.now();
                const distance = blockUntil - now;
                
                if (distance < 0) {
                    clearInterval(countdown);
                    location.reload();
                } else {
                    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                    submitBtn.textContent = `Locked (${minutes}m ${seconds}s)`;
                }
            }, 1000);
            <?php endif; ?>
            
            // Security: Clear password from memory on page unload
            window.addEventListener('beforeunload', function() {
                if (passInput) passInput.value = '';
            });
            
            // Prevent context menu on password field (optional security measure)
            if (passInput) {
                passInput.addEventListener('contextmenu', function(e) {
                    e.preventDefault();
                    return false;
                });
            }
            
        })();
    </script>
    
    <!-- Service Worker for offline capabilities and caching (optional) -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                // Register service worker for faster subsequent loads
                // navigator.serviceWorker.register('/sw.js').catch(function() {});
            });
        }
    </script>
</body>
</html>