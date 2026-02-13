<?php
/**
 * ============================================================
 * SECURE LOGIN PAGE - TWO-STEP AUTHENTICATION
 * ============================================================
 * Features:
 * - Two-step login: Email first, then password
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
$currentStep = 'email'; // Default step
$userEmail = '';

// Handle step tracking
if (isset($_POST['step'])) {
    $currentStep = $_POST['step'];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // ============================================================
    // STEP 1: EMAIL VERIFICATION
    // ============================================================
    if ($currentStep === 'email') {
        $userEmail = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        
        if (empty($userEmail)) {
            $error = "Please enter your email address.";
        } elseif (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            // Check if email exists in allowed domain (optional)
            $allowedDomain = 'sxccal.edu';
            $emailDomain = substr(strrchr($userEmail, "@"), 1);
            
            if ($emailDomain !== $allowedDomain) {
                $error = "Please use your institutional email address (@$allowedDomain).";
            } else {
                // Email is valid - proceed to password step
                $currentStep = 'password';
                // Store email in session temporarily
                $_SESSION['temp_login_email'] = $userEmail;
            }
        }
    }
    
    // ============================================================
    // STEP 2: PASSWORD VERIFICATION
    // ============================================================
    elseif ($currentStep === 'password') {
        $userEmail = $_SESSION['temp_login_email'] ?? '';
        $userPass = $_POST['app_password'] ?? '';
        
        if (empty($userEmail)) {
            // Session expired or tampered
            $error = "Session expired. Please start again.";
            $currentStep = 'email';
            unset($_SESSION['temp_login_email']);
        } elseif (empty($userPass)) {
            $error = "Please enter your password.";
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
                    
                    // Clear temporary session
                    unset($_SESSION['temp_login_email']);
                    
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
                        $error = "Incorrect password. Please try again. ($remaining attempts remaining)";
                    }
                    
                    error_log("LOGIN FAILED: $userEmail from IP $ipAddress - {$authResult['error']}");
                }
            }
        }
    }
}

// Retrieve email from session for password step
if ($currentStep === 'password' && isset($_SESSION['temp_login_email'])) {
    $userEmail = $_SESSION['temp_login_email'];
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
            --primary-accent: #000000;
            --nature-green: #2d5a27;
            --soft-white: #f8f9fa;
            --error-red: #dc3545;
            --warning-orange: #ff9800;
            --primary-blue: #4f5d73;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body, html {
            height: 100%;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: #ebebf0;
            background-image: radial-gradient(#e5e7eb 1px, transparent 1px);
            background-size: 40px 40px;
            position: relative;
        }

        /* Subtle radial gradient overlay */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 50% 30%, rgba(0, 0, 0, 0.02), transparent 60%);
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

        .login-card {
            background: white;
            padding: 45px 40px;
            border-radius: 22px;
            box-shadow: 
                0 8px 24px rgba(0, 0, 0, 0.06),
                0 2px 6px rgba(0, 0, 0, 0.04);
            width: 100%;
            max-width: 420px;
            opacity: 0;
            transform: translateY(8px);
            animation: cardFadeIn 350ms ease-out forwards;
            animation-delay: 100ms;
            border: 0.1px solid rgb(202, 210, 223);
        }

        @keyframes cardFadeIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Brand Header */
        .brand-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 1px solid #f0f0f0;
        }

        .brand-logo {
            width: 52px;
            height: 52px;
            object-fit: contain;
            flex-shrink: 0;
        }

        .brand-details {
            font-size: 0.78rem;
            line-height: 1.15;
            font-weight: 400;
            color: #727fa4;
            opacity: 0.65;
            letter-spacing: 0.2px;
        }

        /* Heading */
        h2 {
            font-size: 1.73rem;
            font-weight: 500;
            margin-bottom: 10px;
            color: #727fa4;
            letter-spacing: 0.3px;
        }

        .subtitle {
            font-size: 0.95rem;
            color: #666;
            margin-bottom: 25px;
            line-height: 1.5;
        }

        /* Email Display (Step 2) */
        .email-display {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #e0e0e0;
        }

        .email-display .email-text {
            flex: 1;
            font-size: 0.95rem;
            color: #333;
            font-weight: 500;
        }

        .email-display .change-link {
            color: var(--primary-blue);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: color 0.2s;
        }

        .email-display .change-link:hover {
            color: #3a4a5e;
            text-decoration: underline;
        }

        /* Alert Messages */
        .error-toast, .warning-toast {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            line-height: 1.5;
            opacity: 0;
            animation: slideInRight 0.4s ease-out forwards;
        }

        .error-toast {
            background: #fef2f2;
            border-left: 3px solid var(--error-red);
            color: #991b1b;
        }

        .warning-toast {
            background: #fff7ed;
            border-left: 3px solid var(--warning-orange);
            color: #9a3412;
        }

        @keyframes slideInRight {
            from { 
                opacity: 0; 
                transform: translateX(10px); 
            }
            to { 
                opacity: 1; 
                transform: translateX(0); 
            }
        }

        /* Form Elements */
        .input-group {
            margin-bottom: 20px;
            opacity: 0;
            animation: fadeInUp 0.4s ease-out forwards;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        label {
            display: block;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #999;
            margin-bottom: 8px;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 5px;
            border: none;
            border-bottom: 2px solid #a0a8b6;
            background: transparent;
            font-size: 1rem;
            transition: border-color 180ms ease, transform 150ms ease;
            outline: none;
        }

        input:focus {
            border-bottom-color: #4f5d73;
            transform: scaleY(1.02);
            transform-origin: bottom;
        }

        input:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 12px;
            font-size: 0.85rem;
            color: #666;
            cursor: pointer;
            user-select: none;
        }

        .checkbox-container input[type="checkbox"] {
            width: auto;
            cursor: pointer;
        }

        /* Button */
        button {
            width: 100%;
            padding: 16px;
            background: rgb(79, 93, 115);
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 2.5px;
            cursor: pointer;
            margin-top: 25px;
            transition: all 180ms ease;
            font-size: 0.9rem;
        }

        button:hover:not(:disabled) {
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.15);
        }

        button:active:not(:disabled) {
            transform: translateY(0);
        }

        button:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        /* Back Button */
        .back-button {
            background: transparent;
            color: var(--primary-blue);
            border: 1px solid #ddd;
            padding: 12px;
            margin-top: 12px;
            text-transform: none;
            letter-spacing: 0.5px;
        }

        .back-button:hover:not(:disabled) {
            background: #f8f9fa;
            border-color: var(--primary-blue);
            box-shadow: none;
        }

        /* Security Info */
        .security-info {
            margin-top: 20px;
            padding: 12px;
            background: #f0f7ff;
            border-left: 3px solid #2196f3;
            border-radius: 4px;
            font-size: 0.75rem;
            color: #555;
        }

        .security-info strong {
            color: #2196f3;
        }

        /* Footer */
        footer {
            margin-top: 22px;
            padding-top: 20px;
            border-top: 1px solid #f0f0f0;
            font-size: 0.65rem;
            color: #bbb;
            opacity: 0.6;
            text-align: center;
        }

        footer span {
            font-size: 0.95rem;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-card {
                padding: 30px 20px;
            }
            
            h2 {
                font-size: 1.5rem;
            }

            .brand-details {
                font-size: 0.7rem;
            }
        }

        /* Step indicator */
        .step-indicator {
            font-size: 0.75rem;
            color: #999;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
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
            
            <?php if ($currentStep === 'email'): ?>
                <p class="subtitle">Enter your institutional email to continue.</p>
                <div class="step-indicator">Step 1 of 2</div>
            <?php else: ?>
                <div class="step-indicator">Step 2 of 2</div>
            <?php endif; ?>

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

            <?php if ($loginAttempts > 0 && $loginAttempts < MAX_LOGIN_ATTEMPTS && $currentStep === 'password'): ?>
                <div class="warning-toast">
                    ⚠️ Failed attempts: <?php echo $loginAttempts; ?>/<?php echo MAX_LOGIN_ATTEMPTS; ?>
                </div>
            <?php endif; ?>

            <?php if ($currentStep === 'email'): ?>
                <!-- STEP 1: EMAIL INPUT -->
                <form method="POST" id="emailForm">
                    <input type="hidden" name="step" value="email">
                    
                    <div class="input-group">
                        <label for="email">User ID / Email</label>
                        <input 
                            type="email" 
                            name="email" 
                            id="email" 
                            placeholder="user@sxccal.edu" 
                            required
                            autocomplete="email"
                            autofocus
                            value="<?php echo htmlspecialchars($userEmail); ?>"
                        >
                    </div>

                    <button type="submit" id="nextBtn">
                        Next →
                    </button>
                </form>

            <?php else: ?>
                <!-- STEP 2: PASSWORD INPUT -->
                <div class="email-display">
                    <div class="email-text"><?php echo htmlspecialchars($userEmail); ?></div>
                    <a href="?step=email" class="change-link">Change</a>
                </div>

                <form method="POST" id="passwordForm">
                    <input type="hidden" name="step" value="password">
                    
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
                            autofocus
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
                        <?php echo ($blockUntil ? 'Account Locked' : 'Verify & Sign In'); ?>
                    </button>

                    <button type="button" class="back-button" onclick="goBack()">
                        ← Back to Email
                    </button>
                </form>
            <?php endif; ?>

            <footer>
                <br>
                <span style="font-size:18px;color:#a3abc3;">Made with ♥︎ by MDTS Students</span>
            </footer>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passInput = document.getElementById("app_password");
            passInput.type = passInput.type === "password" ? "text" : "password";
        }

        function goBack() {
            window.location.href = '?step=email';
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

        // Form validation for email step
        <?php if ($currentStep === 'email'): ?>
        document.getElementById('emailForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('nextBtn');
            btn.disabled = true;
            btn.textContent = 'Validating...';
        });
        <?php endif; ?>

        // Form validation for password step
        <?php if ($currentStep === 'password'): ?>
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.textContent = 'Authenticating...';
        });
        <?php endif; ?>

        // Auto-focus on input fields
        window.addEventListener('load', function() {
            <?php if ($currentStep === 'email'): ?>
                document.getElementById('email').focus();
            <?php else: ?>
                document.getElementById('app_password').focus();
            <?php endif; ?>
        });
    </script>
</body>
</html>