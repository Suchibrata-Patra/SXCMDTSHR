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

// Check if we should show success animation
$showSuccessAnimation = false;
if (isset($_SESSION['show_success_animation']) && $_SESSION['show_success_animation'] === true) {
    $showSuccessAnimation = true;
    unset($_SESSION['show_success_animation']); // Clear the flag
}

// Redirect if already logged in (unless showing animation)
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true && !$showSuccessAnimation) {
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
                    $_SESSION['show_success_animation'] = true; // Flag for animation
                    
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
                    
                    // Reload page to show animation, then redirect
                    header("Location: " . $_SERVER['PHP_SELF']);
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
            --primary-accent: #1a237e;
            --primary-dark: #0d1642;
            --accent-gold: #c5a572;
            --soft-white: #ffffff;
            --error-red: #d32f2f;
            --warning-orange: #f57c00;
            --bg-primary: #fafbfc;
            --bg-secondary: #f5f7fa;
            --text-primary: #1a1a1a;
            --text-secondary: #64748b;
            --text-tertiary: #94a3b8;
            --input-bg: #ffffff;
            --input-border: #e2e8f0;
            --input-focus: #1a237e;
            --card-bg: rgba(255, 255, 255, 0.98);
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.04);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.08);
            --shadow-xl: 0 12px 40px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body, html {
            height: 100%;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #fafbfc 0%, #f0f4f8 100%);
            color: var(--text-primary);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            overflow-x: hidden;
        }

        /* Subtle background pattern */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 25% 25%, rgba(26, 35, 126, 0.03) 0%, transparent 50%),
                radial-gradient(circle at 75% 75%, rgba(197, 165, 114, 0.04) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        /* Decorative elements */
        body::after {
            content: '';
            position: fixed;
            top: -50%;
            right: -20%;
            width: 60%;
            height: 60%;
            background: radial-gradient(circle, rgba(26, 35, 126, 0.05) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
        }

        .page-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            position: relative;
            z-index: 1;
        }

        /* Login Card */
        .login-card {
            width: 100%;
            max-width: 420px;
            background: var(--card-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 12px;
            padding: 36px 32px;
            box-shadow: var(--shadow-xl);
            border: 1px solid rgba(26, 35, 126, 0.08);
            animation: cardEntry 0.5s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-accent), var(--accent-gold), var(--primary-accent));
            opacity: 0.8;
        }

        @keyframes cardEntry {
            from { 
                opacity: 0; 
                transform: translateY(20px);
            }
            to { 
                opacity: 1; 
                transform: translateY(0);
            }
        }

        /* Brand Header */
        .brand-header {
            text-align: center;
            margin-bottom: 28px;
            animation: fadeInDown 0.6s ease;
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .brand-logo {
            width: 68px;
            height: 68px;
            margin-bottom: 14px;
            filter: drop-shadow(0 2px 8px rgba(26, 35, 126, 0.15));
            transition: transform 0.3s ease;
        }

        .brand-logo:hover {
            transform: scale(1.05);
        }

        .brand-details {
            font-size: 9.5px;
            font-weight: 500;
            color: var(--text-tertiary);
            line-height: 1.5;
            letter-spacing: 0.3px;
            max-width: 340px;
            margin: 0 auto;
        }

        /* Typography */
        h2 {
            font-size: 26px;
            font-weight: 700;
            letter-spacing: -0.5px;
            margin-bottom: 6px;
            text-align: center;
            color: var(--primary-accent);
            position: relative;
        }

        .subtitle {
            font-size: 14px;
            color: var(--text-secondary);
            text-align: center;
            margin-bottom: 28px;
            font-weight: 400;
        }

        /* Alert Messages */
        .error-toast, .warning-toast {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            font-weight: 500;
            animation: slideIn 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            align-items: center;
            gap: 10px;
            border-left: 3px solid;
        }

        .error-toast {
            background: #fef2f2;
            border-left-color: var(--error-red);
            color: #991b1b;
        }

        .warning-toast {
            background: #fffbeb;
            border-left-color: var(--warning-orange);
            color: #92400e;
        }

        @keyframes slideIn {
            from { 
                opacity: 0; 
                transform: translateX(-15px);
            }
            to { 
                opacity: 1; 
                transform: translateX(0);
            }
        }

        /* Form Elements */
        .input-group {
            margin-bottom: 20px;
            position: relative;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 7px;
            letter-spacing: 0.2px;
            transition: color 0.2s ease;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid var(--input-border);
            background: var(--input-bg);
            border-radius: 8px;
            font-size: 15px;
            color: var(--text-primary);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            outline: none;
            font-weight: 400;
        }

        input[type="email"]::placeholder,
        input[type="password"]::placeholder {
            color: var(--text-tertiary);
        }

        input:focus {
            border-color: var(--input-focus);
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(26, 35, 126, 0.08);
        }

        input:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            background: var(--bg-secondary);
        }

        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
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
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: var(--primary-accent);
        }

        /* Button */
        button {
            width: 100%;
            padding: 14px;
            background: var(--primary-accent);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            letter-spacing: 0.3px;
            cursor: pointer;
            margin-top: 24px;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 15px;
            box-shadow: 0 2px 8px rgba(26, 35, 126, 0.2);
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
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 16px rgba(26, 35, 126, 0.25);
        }

        button:hover:not(:disabled)::before {
            left: 100%;
        }

        button:active:not(:disabled) {
            transform: translateY(0);
        }

        button:disabled {
            background: #cbd5e1;
            color: #64748b;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Security Info */
        .security-info {
            margin-top: 20px;
            padding: 12px 14px;
            background: #f1f5f9;
            border-left: 3px solid var(--primary-accent);
            border-radius: 6px;
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
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            font-size: 11px;
            color: var(--text-tertiary);
            text-align: center;
            line-height: 1.6;
        }

        footer span {
            font-size: 15px;
            color: var(--text-secondary);
            display: inline-block;
            margin-top: 6px;
        }

        /* Success animation overlay */
        .success-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(26, 35, 126, 0.95) 0%, rgba(13, 22, 66, 0.98) 100%);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .success-overlay.active {
            display: flex;
            animation: overlayFadeIn 0.3s ease forwards;
        }

        @keyframes overlayFadeIn {
            to { opacity: 1; }
        }

        .success-content {
            text-align: center;
            color: white;
            animation: successContentEntry 0.6s cubic-bezier(0.16, 1, 0.3, 1) 0.2s both;
        }

        @keyframes successContentEntry {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .success-checkmark {
            width: 80px;
            height: 80px;
            margin: 0 auto 24px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: center;
            animation: checkmarkPulse 1s ease infinite;
        }

        @keyframes checkmarkPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .success-checkmark svg {
            width: 48px;
            height: 48px;
            stroke: white;
            stroke-width: 3;
            stroke-linecap: round;
            stroke-linejoin: round;
            fill: none;
            animation: checkmarkDraw 0.6s cubic-bezier(0.16, 1, 0.3, 1) 0.4s both;
        }

        @keyframes checkmarkDraw {
            from {
                stroke-dasharray: 100;
                stroke-dashoffset: 100;
            }
            to {
                stroke-dasharray: 100;
                stroke-dashoffset: 0;
            }
        }

        .success-text {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .success-subtext {
            font-size: 15px;
            opacity: 0.8;
            font-weight: 400;
        }

        .loading-dots {
            display: inline-flex;
            gap: 4px;
            margin-left: 4px;
        }

        .loading-dots span {
            width: 4px;
            height: 4px;
            background: white;
            border-radius: 50%;
            animation: dotPulse 1.4s ease-in-out infinite;
        }

        .loading-dots span:nth-child(2) {
            animation-delay: 0.2s;
        }

        .loading-dots span:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes dotPulse {
            0%, 80%, 100% { opacity: 0.3; transform: scale(0.8); }
            40% { opacity: 1; transform: scale(1); }
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-card {
                padding: 28px 24px;
                border-radius: 10px;
            }
            
            h2 {
                font-size: 23px;
            }

            .brand-logo {
                width: 60px;
                height: 60px;
            }

            button {
                padding: 13px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <!-- Success Animation Overlay -->
    <div class="success-overlay" id="successOverlay">
        <div class="success-content">
            <div class="success-checkmark">
                <svg viewBox="0 0 52 52">
                    <path d="M14 27l9 9 19-19" />
                </svg>
            </div>
            <div class="success-text">Authentication Successful</div>
            <div class="success-subtext">
                Redirecting to dashboard<span class="loading-dots"><span></span><span></span><span></span></span>
            </div>
        </div>
    </div>

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

        // Show success animation if login was successful
        <?php if ($showSuccessAnimation): ?>
        window.addEventListener('DOMContentLoaded', function() {
            const overlay = document.getElementById('successOverlay');
            overlay.classList.add('active');
            
            // Redirect after animation (2 seconds)
            setTimeout(function() {
                window.location.href = 'index.php';
            }, 2000);
        });
        <?php endif; ?>
    </script>
</body>
</html>