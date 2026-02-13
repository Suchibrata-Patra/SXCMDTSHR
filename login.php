<?php
/**
 * ============================================================
 * SECURE LOGIN PAGE - OPTIMIZED & FAST
 * ============================================================
 * Features:
 * - Rate limiting & brute force protection
 * - NO email sending (activity logged directly to DB)
 * - Fast SMTP-only validation
 * - Secure session management
 * - Login activity tracking (separate from inbox)
 * ============================================================
 */

require_once 'vendor/autoload.php';
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

// Initialize secure session
initializeSecureSession();

// Redirect if already logged in
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    header("Location: index.php");
    exit();
}

$error = "";
$loginAttempts = 0;
$blockUntil = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $userEmail = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $userPass = $_POST['app_password'] ?? '';
    
    // Validate input
    if (empty($userEmail) || empty($userPass)) {
        $error = "Email and password are required.";
    } else {
        // Get client IP
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
                    
                    // Set session variables
                    $_SESSION['smtp_user'] = $userEmail;
                    $_SESSION['smtp_pass'] = $userPass;
                    $_SESSION['authenticated'] = true;
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['login_time'] = time();
                    $_SESSION['ip_address'] = $ipAddress;
                    
                    // Regenerate session ID for security
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

/**
 * Fast SMTP authentication (NO EMAIL SENDING)
 * Only validates credentials - 10x faster than sending email
 */
function authenticateWithSMTP($email, $password) {
    $mail = new PHPMailer(true);
    
    try {
        // Configure SMTP
        $mail->isSMTP();
        $mail->Host = env("SMTP_HOST");
        $mail->SMTPAuth = true;
        $mail->Username = $email;
        $mail->Password = $password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = env("SMTP_PORT");
        $mail->Timeout = 10; // Fast timeout
        $mail->SMTPDebug = 0; // No debug output
        
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
        <?php
        define('PAGE_TITLE', 'SXC MDTS | Dashboard');
        include 'header.php';
    ?>
    <style>
        :root {
            --primary-accent: #007aff;
            --primary-dark: #0051d5;
            --nature-green: #34c759;
            --soft-white: #ffffff;
            --error-red: #ff3b30;
            --warning-orange: #ff9500;
            --bg-primary: #000000;
            --bg-secondary: #1c1c1e;
            --text-primary: #ffffff;
            --text-secondary: rgba(255, 255, 255, 0.6);
            --input-bg: rgba(255, 255, 255, 0.08);
            --input-border: rgba(255, 255, 255, 0.12);
            --card-bg: rgba(28, 28, 30, 0.7);
            --shadow-subtle: 0 8px 32px rgba(0, 0, 0, 0.3);
            --shadow-strong: 0 20px 60px rgba(0, 0, 0, 0.5);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body, html {
            height: 100%;
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a2e 50%, #16213e 100%);
            color: var(--text-primary);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            overflow-x: hidden;
        }

        /* Animated background */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 50%, rgba(0, 122, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(52, 199, 89, 0.08) 0%, transparent 50%);
            pointer-events: none;
            animation: backgroundShift 20s ease-in-out infinite;
        }

        @keyframes backgroundShift {
            0%, 100% { opacity: 0.3; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.1); }
        }

        .page-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 24px;
            position: relative;
            z-index: 1;
        }

        /* Login Card */
        .login-card {
            width: 100%;
            max-width: 440px;
            background: var(--card-bg);
            backdrop-filter: blur(40px) saturate(180%);
            -webkit-backdrop-filter: blur(40px) saturate(180%);
            border-radius: 20px;
            padding: 48px 40px;
            box-shadow: var(--shadow-strong);
            border: 1px solid rgba(255, 255, 255, 0.1);
            animation: cardEntry 0.6s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        }

        @keyframes cardEntry {
            from { 
                opacity: 0; 
                transform: translateY(30px) scale(0.95);
            }
            to { 
                opacity: 1; 
                transform: translateY(0) scale(1);
            }
        }

        /* Brand Header */
        .brand-header {
            text-align: center;
            margin-bottom: 32px;
            animation: fadeInDown 0.8s ease;
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .brand-logo {
            width: 72px;
            height: 72px;
            margin-bottom: 16px;
            filter: drop-shadow(0 4px 12px rgba(0, 122, 255, 0.3));
            animation: logoFloat 3s ease-in-out infinite;
        }

        @keyframes logoFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }

        .brand-details {
            font-size: 10px;
            font-weight: 500;
            color: var(--text-secondary);
            line-height: 1.6;
            letter-spacing: 0.3px;
            max-width: 360px;
            margin: 0 auto;
        }

        /* Typography */
        h2 {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.5px;
            margin-bottom: 8px;
            text-align: center;
            background: linear-gradient(135deg, #ffffff 0%, rgba(255, 255, 255, 0.8) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .subtitle {
            font-size: 14px;
            color: var(--text-secondary);
            text-align: center;
            margin-bottom: 32px;
            font-weight: 400;
        }

        /* Alert Messages */
        .error-toast, .warning-toast {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 13px;
            font-weight: 500;
            animation: slideIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            align-items: center;
            gap: 10px;
            backdrop-filter: blur(10px);
        }

        .error-toast {
            background: rgba(255, 59, 48, 0.15);
            border: 1px solid rgba(255, 59, 48, 0.3);
            color: #ff6b6b;
        }

        .warning-toast {
            background: rgba(255, 149, 0, 0.15);
            border: 1px solid rgba(255, 149, 0, 0.3);
            color: #ffb74d;
        }

        @keyframes slideIn {
            from { 
                opacity: 0; 
                transform: translateX(-20px);
            }
            to { 
                opacity: 1; 
                transform: translateX(0);
            }
        }

        /* Form Elements */
        .input-group {
            margin-bottom: 24px;
            position: relative;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 8px;
            letter-spacing: 0.2px;
            transition: color 0.2s ease;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 14px 16px;
            border: 1.5px solid var(--input-border);
            background: var(--input-bg);
            backdrop-filter: blur(10px);
            border-radius: 10px;
            font-size: 15px;
            color: var(--text-primary);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            outline: none;
            font-weight: 400;
        }

        input[type="email"]::placeholder,
        input[type="password"]::placeholder {
            color: rgba(255, 255, 255, 0.3);
        }

        input:focus {
            border-color: var(--primary-accent);
            background: rgba(255, 255, 255, 0.12);
            box-shadow: 0 0 0 4px rgba(0, 122, 255, 0.1);
            transform: translateY(-1px);
        }

        input:focus + label {
            color: var(--primary-accent);
        }

        input:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: rgba(255, 255, 255, 0.03);
        }

        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 12px;
            font-size: 13px;
            color: var(--text-secondary);
            cursor: pointer;
            user-select: none;
            transition: color 0.2s ease;
        }

        .checkbox-container:hover {
            color: var(--text-primary);
        }

        .checkbox-container input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--primary-accent);
        }

        /* Button */
        button {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary-accent) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            letter-spacing: 0.3px;
            cursor: pointer;
            margin-top: 32px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 15px;
            box-shadow: 0 4px 16px rgba(0, 122, 255, 0.3);
            position: relative;
            overflow: hidden;
        }

        button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 122, 255, 0.4);
        }

        button:hover:not(:disabled)::before {
            left: 100%;
        }

        button:active:not(:disabled) {
            transform: translateY(0);
            box-shadow: 0 4px 12px rgba(0, 122, 255, 0.3);
        }

        button:disabled {
            background: rgba(255, 255, 255, 0.1);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Security Info */
        .security-info {
            margin-top: 24px;
            padding: 14px 16px;
            background: rgba(0, 122, 255, 0.08);
            border-left: 3px solid var(--primary-accent);
            border-radius: 8px;
            font-size: 12px;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .security-info strong {
            color: var(--primary-accent);
            font-weight: 600;
        }

        /* Footer */
        footer {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            font-size: 11px;
            color: rgba(255, 255, 255, 0.4);
            text-align: center;
            line-height: 1.8;
        }

        footer span {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.6);
            display: inline-block;
            margin-top: 8px;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-card {
                padding: 36px 28px;
                border-radius: 16px;
            }
            
            h2 {
                font-size: 24px;
            }

            .brand-logo {
                width: 64px;
                height: 64px;
            }

            button {
                padding: 14px;
                font-size: 14px;
            }
        }

        /* Smooth transitions for all interactive elements */
        * {
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <div class="login-card">
            <div class="brand-header">
                <img src="Assets/image/sxc_logo.png" alt="SXC Logo" class="brand-logo">
                <div class="brand-details">
                    Autonomous College (2006) | CPE (2006) |
                    CE (2014), NAAC A++ | 4th Cycle (2024) |
                    ISO 9001:2015 | NIRF 2025: 8th Position
                </div>
            </div>

            <h2>Authentication</h2>
            <!-- <p class="subtitle">Enter institutional credentials to continue.</p> -->

            <?php if ($error): ?>
                <div class="error-toast">
                    🔒 <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error']) && $_GET['error'] === 'session_expired'): ?>
                <div class="warning-toast">
                    ⏱️ Your session has expired. Please login again.
                </div>
            <?php endif; ?>

            <?php if ($loginAttempts > 0 && $loginAttempts < MAX_LOGIN_ATTEMPTS): ?>
                <div class="warning-toast">
                    ⚠️ Failed attempts: <?php echo $loginAttempts; ?>/<?php echo MAX_LOGIN_ATTEMPTS; ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="loginForm">
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
                    >
                    
                    <label class="checkbox-container">
                        <input type="checkbox" id="toggleCheck" onclick="togglePassword()">
                        <span>Show Password</span>
                    </label>
                </div>

                <button 
                    type="submit" 
                    id="submitBtn"
                    <?php echo ($blockUntil ? 'disabled' : ''); ?>
                >
                    <?php echo ($blockUntil ? 'Account Locked' : 'Verify & Proceed'); ?>
                </button>
            </form>
            <footer>
                <!-- St. Xavier's College (Autonomous), Kolkata<br>
                Mail Delivery & Tracking System v2.0 -->
                <br>
                <span style="font-size:18px;">Made with ♥︎ by MDTS Students</span>
            </footer>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passInput = document.getElementById("app_password");
            passInput.type = passInput.type === "password" ? "text" : "password";
        }

        // Auto-unlock countdown if blocked
        <?php if ($blockUntil): ?>
        const blockUntil = new Date("<?php echo $blockUntil; ?>").getTime();
        
        const countdown = setInterval(function() {
            const now = new Date().getTime();
            const distance = blockUntil - now;
            
            if (distance < 0) {
                clearInterval(countdown);
                location.reload();
            } else {
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                document.getElementById('submitBtn').textContent = `Locked (${minutes}m ${seconds}s)`;
            }
        }, 1000);
        <?php endif; ?>

        // Form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.textContent = 'Authenticating...';
        });
    </script>
</body>
</html>