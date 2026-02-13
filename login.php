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
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap');

        :root {
            --academic-navy: #1e3a5f;
            --academic-navy-dark: #152a45;
            --academic-gold: #b8935e;
            --text-primary: #1a1d29;
            --text-secondary: #6b7280;
            --text-tertiary: #9ca3af;
            --pearl-white: #fdfcfa;
            --soft-white: #ffffff;
            --glass-bg: rgba(255, 255, 255, 0.65);
            --input-inset: rgba(0, 0, 0, 0.03);
            --focus-glow: rgba(30, 58, 95, 0.15);
            --error-red: #c5374d;
            --warning-amber: #d97706;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.02);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.04);
            --shadow-lg: 0 12px 32px rgba(0, 0, 0, 0.06);
            --shadow-xl: 0 20px 48px rgba(0, 0, 0, 0.08);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body, html {
            height: 100%;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif;
            background: linear-gradient(135deg, #fdfcfa 0%, #f7f5f2 50%, #faf8f5 100%);
            color: var(--text-primary);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
            overflow-x: hidden;
        }

        /* Subtle grain texture */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('data:image/svg+xml;utf8,<svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"><filter id="noise"><feTurbulence type="fractalNoise" baseFrequency="0.9" numOctaves="4" stitchTiles="stitch"/></filter><rect width="100%" height="100%" filter="url(%23noise)" opacity="0.015"/></svg>');
            pointer-events: none;
            z-index: 0;
        }

        /* Ambient gradient */
        body::after {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(ellipse at 30% 40%, rgba(30, 58, 95, 0.04) 0%, transparent 50%),
                        radial-gradient(ellipse at 70% 60%, rgba(184, 147, 94, 0.03) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
            animation: ambientShift 20s ease-in-out infinite;
        }

        @keyframes ambientShift {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(-5%, -5%); }
        }

        .page-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 40px 20px;
            position: relative;
            z-index: 1;
            animation: pageEntry 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes pageEntry {
            from {
                opacity: 0;
                transform: scale(0.98);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        /* Frosted Glass Card */
        .login-card {
            width: 100%;
            max-width: 440px;
            background: var(--glass-bg);
            backdrop-filter: blur(25px) saturate(180%);
            -webkit-backdrop-filter: blur(25px) saturate(180%);
            border-radius: 20px;
            padding: 56px 48px 48px;
            box-shadow: var(--shadow-xl),
                        0 0 0 1px rgba(255, 255, 255, 0.5) inset;
            border: 1px solid rgba(255, 255, 255, 0.8);
            position: relative;
            overflow: visible;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .login-card:hover {
            box-shadow: 0 24px 56px rgba(0, 0, 0, 0.1),
                        0 0 0 1px rgba(255, 255, 255, 0.6) inset;
        }

        /* Brand Header */
        .brand-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .brand-logo {
            width: 72px;
            height: 72px;
            margin-bottom: 20px;
            opacity: 0.96;
            filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.06));
            transition: all 0.3s ease;
        }

        .brand-logo:hover {
            opacity: 1;
            transform: scale(1.02);
        }

        .brand-details {
            font-size: 10px;
            font-weight: 400;
            color: var(--text-tertiary);
            line-height: 1.6;
            letter-spacing: 0.4px;
            opacity: 0.7;
        }

        /* Typography */
        h2 {
            font-size: 28px;
            font-weight: 500;
            letter-spacing: -0.02em;
            color: var(--text-primary);
            text-align: center;
            margin-bottom: 12px;
        }

        .subtitle {
            font-size: 15px;
            font-weight: 400;
            color: var(--text-secondary);
            text-align: center;
            margin-bottom: 36px;
            letter-spacing: 0.01em;
        }

        /* Alert Messages */
        .error-toast, .warning-toast {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
            font-weight: 400;
            letter-spacing: 0.01em;
            line-height: 1.5;
            backdrop-filter: blur(10px);
            animation: toastSlideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .error-toast {
            background: rgba(197, 55, 77, 0.08);
            color: var(--error-red);
            border: 1px solid rgba(197, 55, 77, 0.15);
        }

        .warning-toast {
            background: rgba(217, 119, 6, 0.08);
            color: var(--warning-amber);
            border: 1px solid rgba(217, 119, 6, 0.15);
        }

        @keyframes toastSlideIn {
            from {
                opacity: 0;
                transform: translateY(-8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Form Elements with Floating Labels */
        .input-group {
            margin-bottom: 24px;
            position: relative;
        }

        label {
            position: absolute;
            left: 16px;
            top: 15px;
            font-size: 15px;
            font-weight: 400;
            color: var(--text-tertiary);
            pointer-events: none;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            letter-spacing: 0.01em;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 18px 16px 8px;
            border: none;
            background: var(--soft-white);
            box-shadow: inset 0 1px 3px var(--input-inset);
            border-radius: 12px;
            font-size: 16px;
            font-weight: 400;
            color: var(--text-primary);
            letter-spacing: 0.01em;
            transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
            outline: none;
            font-family: 'Inter', sans-serif;
            -webkit-appearance: none;
        }

        input[type="email"]::placeholder,
        input[type="password"]::placeholder {
            opacity: 0;
        }

        /* Floating label on focus or when filled */
        input:focus + label,
        input:not(:placeholder-shown) + label {
            top: 6px;
            font-size: 11px;
            font-weight: 500;
            color: var(--academic-navy);
            letter-spacing: 0.03em;
        }

        input:focus {
            background: var(--soft-white);
            box-shadow: inset 0 1px 3px var(--input-inset),
                        0 0 0 3px var(--focus-glow);
        }

        input:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: rgba(255, 255, 255, 0.5);
        }

        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 14px;
            font-size: 14px;
            font-weight: 400;
            color: var(--text-secondary);
            cursor: pointer;
            user-select: none;
            letter-spacing: 0.01em;
            transition: color 0.15s ease;
        }

        .checkbox-container:hover {
            color: var(--text-primary);
        }

        .checkbox-container input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--academic-navy);
        }

        /* Premium Button */
        button {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--academic-navy) 0%, var(--academic-navy-dark) 100%);
            color: var(--soft-white);
            border: none;
            border-radius: 14px;
            font-size: 16px;
            font-weight: 500;
            letter-spacing: 0.02em;
            cursor: pointer;
            margin-top: 32px;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            font-family: 'Inter', sans-serif;
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }

        /* Glass highlight on button */
        button::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 50%;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.15) 0%, transparent 100%);
            border-radius: 14px 14px 0 0;
            pointer-events: none;
        }

        button:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(30, 58, 95, 0.2);
            background: linear-gradient(135deg, #244466 0%, #1a3352 100%);
        }

        button:active:not(:disabled) {
            transform: translateY(0);
            box-shadow: var(--shadow-md);
        }

        button:disabled {
            background: #d1d5db;
            color: #9ca3af;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        button:disabled::before {
            display: none;
        }

        /* Security Info */
        .security-info {
            margin-top: 28px;
            padding: 16px 18px;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
            border-radius: 10px;
            font-size: 12px;
            font-weight: 400;
            color: var(--text-secondary);
            line-height: 1.6;
            letter-spacing: 0.02em;
            border: 1px solid rgba(0, 0, 0, 0.04);
        }

        .security-info strong {
            color: var(--academic-navy);
            font-weight: 500;
        }

        /* Footer - Minimalist */
        footer {
            margin-top: 36px;
            padding-top: 24px;
            border-top: 1px solid rgba(0, 0, 0, 0.06);
            font-size: 11px;
            font-weight: 400;
            color: var(--text-tertiary);
            text-align: center;
            line-height: 1.6;
            letter-spacing: 0.02em;
            opacity: 0.7;
        }

        footer span {
            font-size: 13px;
            color: var(--text-secondary);
            display: block;
            margin-top: 8px;
            opacity: 0.8;
        }

        /* Success Animation - Refined */
        .success-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(253, 252, 250, 0.85);
            backdrop-filter: blur(30px) saturate(120%);
            -webkit-backdrop-filter: blur(30px) saturate(120%);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            opacity: 0;
            transition: opacity 0.4s ease;
        }

        .success-overlay.active {
            display: flex;
            animation: overlayFadeIn 0.4s ease forwards;
        }

        @keyframes overlayFadeIn {
            to { opacity: 1; }
        }

        .success-content {
            text-align: center;
            padding: 56px 48px;
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(30px) saturate(150%);
            -webkit-backdrop-filter: blur(30px) saturate(150%);
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.12),
                        0 0 0 1px rgba(255, 255, 255, 0.8) inset;
            border: 1px solid rgba(255, 255, 255, 0.9);
            animation: successContentEntry 0.5s cubic-bezier(0.16, 1, 0.3, 1) 0.1s both;
            max-width: 360px;
        }

        @keyframes successContentEntry {
            from {
                opacity: 0;
                transform: scale(0.92);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .success-checkmark {
            width: 80px;
            height: 80px;
            margin: 0 auto 28px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--academic-navy) 0%, var(--academic-navy-dark) 100%);
            box-shadow: 0 8px 24px rgba(30, 58, 95, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            animation: checkmarkScale 0.5s cubic-bezier(0.16, 1, 0.3, 1) 0.2s both;
        }

        /* Subtle glow ring */
        .success-checkmark::before {
            content: '';
            position: absolute;
            inset: -8px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(30, 58, 95, 0.15) 0%, transparent 70%);
            animation: glowPulse 2s ease-in-out infinite;
        }

        @keyframes glowPulse {
            0%, 100% { opacity: 0.5; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.05); }
        }

        @keyframes checkmarkScale {
            from {
                transform: scale(0);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        .success-checkmark svg {
            width: 44px;
            height: 44px;
            stroke: var(--soft-white);
            stroke-width: 3;
            stroke-linecap: round;
            stroke-linejoin: round;
            fill: none;
            position: relative;
            z-index: 1;
            animation: checkmarkDraw 0.4s ease 0.5s both;
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
            font-size: 26px;
            font-weight: 500;
            letter-spacing: -0.02em;
            color: var(--text-primary);
            margin-bottom: 10px;
        }

        .success-subtext {
            font-size: 15px;
            font-weight: 400;
            color: var(--text-secondary);
            letter-spacing: 0.01em;
        }

        .loading-dots {
            display: inline-flex;
            gap: 4px;
            margin-left: 4px;
        }

        .loading-dots span {
            width: 4px;
            height: 4px;
            background: var(--text-secondary);
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

        /* Responsive */
        @media (max-width: 520px) {
            .login-card {
                padding: 48px 36px 40px;
                border-radius: 18px;
                max-width: 100%;
            }

            h2 {
                font-size: 25px;
            }

            .brand-logo {
                width: 64px;
                height: 64px;
            }

            .success-content {
                padding: 48px 36px;
                max-width: calc(100% - 40px);
                border-radius: 20px;
            }
        }

        /* Remove all default appearances */
        input, button {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }

        /* Smooth focus behavior */
        *:focus {
            outline: none;
        }

        /* Prevent text selection on UI elements */
        button, label {
            -webkit-user-select: none;
            user-select: none;
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
                    St. Xavier's College (Autonomous) · Kolkata
                </div>
            </div>

            <h2>Secure Sign In</h2>
            <p class="subtitle">Access your institutional account</p>

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
                    <input 
                        type="email" 
                        name="email" 
                        id="email" 
                        placeholder=" " 
                        required
                        <?php echo ($blockUntil ? 'disabled' : ''); ?>
                        autocomplete="email"
                        autofocus
                    >
                    <label for="email">Institutional Email</label>
                </div>

                <div class="input-group">
                    <input 
                        type="password" 
                        name="app_password" 
                        id="app_password" 
                        placeholder=" " 
                        required
                        <?php echo ($blockUntil ? 'disabled' : ''); ?>
                        autocomplete="current-password"
                    >
                    <label for="app_password">Application Password</label>
                    
                    <label class="checkbox-container">
                        <input type="checkbox" id="toggleCheck" onclick="togglePassword()">
                        <span>Show password</span>
                    </label>
                </div>

                <button 
                    type="submit" 
                    id="submitBtn"
                    <?php echo ($blockUntil ? 'disabled' : ''); ?>
                >
                    <?php echo ($blockUntil ? 'Account Locked' : 'Sign In'); ?>
                </button>
            </form>
            <footer>
                NAAC A++ Accredited · ISO 9001:2015 · NIRF Rank 8 (2025)
                <span>Mail Delivery & Tracking System</span>
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