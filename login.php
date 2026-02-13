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
            --sf-pro: -apple-system, BlinkMacSystemFont, "SF Pro Display", "SF Pro Text", "Helvetica Neue", Arial, sans-serif;
            --apple-blue: #007aff;
            --apple-blue-dark: #0051d5;
            --apple-gray: #86868b;
            --apple-gray-light: #f5f5f7;
            --apple-gray-medium: #e8e8ed;
            --apple-text: #1d1d1f;
            --apple-text-secondary: #86868b;
            --apple-red: #ff3b30;
            --apple-orange: #ff9500;
            --apple-white: #ffffff;
            --apple-border: rgba(0, 0, 0, 0.04);
            --apple-shadow: 0 2px 16px rgba(0, 0, 0, 0.06);
            --apple-shadow-hover: 0 4px 24px rgba(0, 0, 0, 0.08);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body, html {
            height: 100%;
            font-family: var(--sf-pro);
            background: var(--apple-gray-light);
            color: var(--apple-text);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
        }

        .page-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        /* Login Card - Pure Apple Style */
        .login-card {
            width: 100%;
            max-width: 380px;
            background: var(--apple-white);
            border-radius: 18px;
            padding: 44px 40px 40px;
            box-shadow: var(--apple-shadow);
            transition: box-shadow 0.3s ease;
        }

        .login-card:hover {
            box-shadow: var(--apple-shadow-hover);
        }

        /* Brand Header - Apple Minimalism */
        .brand-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .brand-logo {
            width: 64px;
            height: 64px;
            margin-bottom: 16px;
            transition: transform 0.2s ease;
        }

        .brand-logo:hover {
            transform: scale(1.02);
        }

        .brand-details {
            font-size: 11px;
            font-weight: 400;
            color: var(--apple-text-secondary);
            line-height: 1.4;
            letter-spacing: -0.01em;
        }

        /* Typography - Apple Style */
        h2 {
            font-size: 32px;
            font-weight: 600;
            letter-spacing: -0.02em;
            color: var(--apple-text);
            text-align: center;
            margin-bottom: 8px;
        }

        .subtitle {
            font-size: 17px;
            font-weight: 400;
            color: var(--apple-text-secondary);
            text-align: center;
            margin-bottom: 32px;
            letter-spacing: -0.01em;
        }

        /* Alert Messages - Apple Notifications */
        .error-toast, .warning-toast {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 15px;
            font-weight: 400;
            letter-spacing: -0.01em;
            line-height: 1.4;
        }

        .error-toast {
            background: rgba(255, 59, 48, 0.08);
            color: var(--apple-red);
        }

        .warning-toast {
            background: rgba(255, 149, 0, 0.08);
            color: var(--apple-orange);
        }

        /* Form Elements - Apple Input Style */
        .input-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 400;
            color: var(--apple-text-secondary);
            margin-bottom: 8px;
            letter-spacing: -0.01em;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid var(--apple-gray-medium);
            background: var(--apple-white);
            border-radius: 10px;
            font-size: 17px;
            font-weight: 400;
            color: var(--apple-text);
            letter-spacing: -0.01em;
            transition: all 0.2s ease;
            outline: none;
            -webkit-appearance: none;
            font-family: var(--sf-pro);
        }

        input[type="email"]::placeholder,
        input[type="password"]::placeholder {
            color: #c7c7cc;
        }

        input:focus {
            border-color: var(--apple-blue);
            box-shadow: 0 0 0 4px rgba(0, 122, 255, 0.1);
        }

        input:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: var(--apple-gray-light);
        }

        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 12px;
            font-size: 15px;
            font-weight: 400;
            color: var(--apple-text);
            cursor: pointer;
            user-select: none;
            letter-spacing: -0.01em;
        }

        .checkbox-container input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: var(--apple-blue);
        }

        /* Button - Apple Blue Button */
        button {
            width: 100%;
            padding: 14px;
            background: var(--apple-blue);
            color: var(--apple-white);
            border: none;
            border-radius: 10px;
            font-size: 17px;
            font-weight: 500;
            letter-spacing: -0.01em;
            cursor: pointer;
            margin-top: 24px;
            transition: all 0.2s ease;
            font-family: var(--sf-pro);
            -webkit-appearance: none;
        }

        button:hover:not(:disabled) {
            background: var(--apple-blue-dark);
        }

        button:active:not(:disabled) {
            transform: scale(0.98);
        }

        button:disabled {
            background: var(--apple-gray-medium);
            color: var(--apple-gray);
            cursor: not-allowed;
        }

        /* Security Info - Apple Info Box */
        .security-info {
            margin-top: 24px;
            padding: 16px;
            background: var(--apple-gray-light);
            border-radius: 12px;
            font-size: 13px;
            font-weight: 400;
            color: var(--apple-text-secondary);
            line-height: 1.5;
            letter-spacing: -0.01em;
        }

        .security-info strong {
            color: var(--apple-text);
            font-weight: 500;
        }

        /* Footer - Apple Footer Style */
        footer {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid var(--apple-border);
            font-size: 12px;
            font-weight: 400;
            color: var(--apple-text-secondary);
            text-align: center;
            line-height: 1.5;
            letter-spacing: -0.01em;
        }

        footer span {
            font-size: 15px;
            color: var(--apple-text-secondary);
            display: block;
            margin-top: 8px;
        }

        /* Success Animation Overlay - Apple Style */
        .success-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
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
            color: var(--apple-white);
            padding: 48px 40px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(40px);
            -webkit-backdrop-filter: blur(40px);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
            animation: successContentEntry 0.4s cubic-bezier(0.16, 1, 0.3, 1) 0.1s both;
            max-width: 320px;
        }

        @keyframes successContentEntry {
            from {
                opacity: 0;
                transform: scale(0.94);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .success-checkmark {
            width: 72px;
            height: 72px;
            margin: 0 auto 24px;
            border-radius: 50%;
            background: var(--apple-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            animation: checkmarkScale 0.4s cubic-bezier(0.16, 1, 0.3, 1) 0.2s both;
        }

        @keyframes checkmarkScale {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }

        .success-checkmark svg {
            width: 40px;
            height: 40px;
            stroke: var(--apple-white);
            stroke-width: 3;
            stroke-linecap: round;
            stroke-linejoin: round;
            fill: none;
            animation: checkmarkDraw 0.3s ease 0.4s both;
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
            font-weight: 600;
            letter-spacing: -0.02em;
            color: var(--apple-text);
            margin-bottom: 8px;
        }

        .success-subtext {
            font-size: 15px;
            font-weight: 400;
            color: var(--apple-text-secondary);
            letter-spacing: -0.01em;
        }

        .loading-dots {
            display: inline-flex;
            gap: 4px;
            margin-left: 4px;
        }

        .loading-dots span {
            width: 4px;
            height: 4px;
            background: var(--apple-text-secondary);
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
            0%, 80%, 100% { 
                opacity: 0.3; 
                transform: scale(0.8); 
            }
            40% { 
                opacity: 1; 
                transform: scale(1); 
            }
        }

        /* Responsive - Apple Breakpoints */
        @media (max-width: 480px) {
            .login-card {
                padding: 40px 32px 36px;
                border-radius: 16px;
                max-width: 100%;
            }
            
            h2 {
                font-size: 28px;
            }

            .brand-logo {
                width: 56px;
                height: 56px;
            }

            input[type="email"],
            input[type="password"],
            button {
                font-size: 16px;
            }

            .success-content {
                padding: 40px 32px;
                max-width: calc(100% - 40px);
            }
        }

        /* Remove iOS input styling */
        input[type="email"],
        input[type="password"],
        button {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }

        /* Apple-style focus states */
        *:focus {
            outline: none;
        }

        /* Smooth scrolling like Apple */
        html {
            scroll-behavior: smooth;
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