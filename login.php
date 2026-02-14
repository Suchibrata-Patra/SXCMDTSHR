<?php
/**
 * ============================================================
 * LOGIN PAGE - DATABASE AUTHENTICATION VERSION
 * ============================================================
 * Features:
 * - Database-based password authentication
 * - Rate limiting & brute force protection
 * - Login activity tracking
 * - Secure session management
 * - SMTP credentials from ENV for email sending only
 * ============================================================
 */
require_once __DIR__ . '/security_handler.php';
require_once 'vendor/autoload.php';
require_once 'config.php';
require_once 'db_config.php';
require_once 'login_auth_helper.php';

// Load environment variables
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

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
    $userPass = $_POST['password'] ?? '';
    
    // Validate input
    if (empty($userEmail) || empty($userPass)) {
        $error = "Email and password are required.";
    } else {
        // Get client IP
        $ipAddress = getClientIP();
        
        // Check rate limiting
        $rateLimit = checkRateLimit($userEmail, $ipAddress);
        
        if (!$rateLimit['allowed']) {
            $blockUntilTime = !empty($rateLimit['block_until']) ? strtotime($rateLimit['block_until']) : time();
            $remainingMinutes = ceil(($blockUntilTime - time()) / 60);
            
            $error = "Too many failed attempts. Account temporarily locked. Please try again in $remainingMinutes minutes.";
            $loginAttempts = $rateLimit['attempts'];
            $blockUntil = $rateLimit['block_until'];
            
            error_log("SECURITY: Login blocked for $userEmail from IP $ipAddress");
        } else {
            // Attempt database authentication
            $authResult = authenticateWithDatabase($userEmail, $userPass);
            
            if ($authResult['success']) {
                // ============================================================
                // SUCCESSFUL LOGIN
                // ============================================================
                
                $user = $authResult['user'];
                
                // Clear failed attempts
                clearFailedAttempts($userEmail, $ipAddress);
                
                // Record login activity
                $loginActivityId = recordLoginActivity($userEmail, $user['id'], 'success');
                
                // Get SMTP credentials from environment for email sending
                $smtpCreds = getSmtpCredentials();
                
                // Set session variables
                $_SESSION['user_email'] = $userEmail;
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_uuid'] = $user['user_uuid'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['authenticated'] = true;
                $_SESSION['login_time'] = time();
                $_SESSION['ip_address'] = $ipAddress;
                
                // Store SMTP credentials for email sending (from ENV)
                $_SESSION['smtp_user'] = $smtpCreds['username'];
                $_SESSION['smtp_pass'] = $smtpCreds['password'];
                $_SESSION['smtp_host'] = $smtpCreds['host'];
                $_SESSION['smtp_port'] = $smtpCreds['port'];
                
                // Regenerate session ID for security
                session_regenerate_id(true);
                
                // Create session record
                createUserSession($user['id'], $loginActivityId);
                
                // Load IMAP config (using system credentials)
                loadImapConfigToSession($smtpCreds['username'], $smtpCreds['password']);
                
                // Check user role
                $superAdmins = ['admin@sxccal.edu', 'hod@sxccal.edu'];
                $_SESSION['user_role'] = (in_array($userEmail, $superAdmins) || $user['is_admin']) ? 'super_admin' : 'user';
                $_SESSION['is_admin'] = $user['is_admin'];
                
                // Check if password change required
                if ($user['require_password_change']) {
                    $_SESSION['require_password_change'] = true;
                    header("Location: change_password.php");
                    exit();
                }
                
                // Load user settings
                if (file_exists('settings_helper.php')) {
                    require_once 'settings_helper.php';
                }
                
                // Success - redirect
                header("Location: index.php");
                exit();
                
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
                    $blockUntilTime = !empty($rateLimit['block_until']) ? strtotime($rateLimit['block_until']) : time();
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
        <?php
        define('PAGE_TITLE', 'SXC MDTS | Dashboard');
        include 'header.php';
    ?>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #1a0b2e 0%, #0d0d1a 50%, #1a1a2e 100%);
            --card-bg: rgba(26, 11, 46, 0.7);
            --card-border: rgba(129, 140, 248, 0.2);
            --input-bg: rgba(15, 23, 42, 0.6);
            --input-border: rgba(148, 163, 184, 0.3);
            --input-focus: #818cf8;
            --text-primary: #e2e8f0;
            --text-secondary: #94a3b8;
            --accent-purple: #818cf8;
            --accent-light: #a5b4fc;
            --error-red: #ef4444;
            --warning-orange: #f59e0b;
            --success-green: #10b981;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body, html {
            height: 100%;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #1a0b2e 0%, #0d0d1a 50%, #1a1a2e 100%);
            position: relative;
            overflow-x: hidden;
        }

        /* Animated gradient background */
        body::before {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: 
                radial-gradient(circle at 20% 50%, rgba(129, 140, 248, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(165, 180, 252, 0.08) 0%, transparent 50%),
                radial-gradient(circle at 40% 20%, rgba(99, 102, 241, 0.05) 0%, transparent 50%);
            animation: gradientShift 20s ease infinite;
            pointer-events: none;
            z-index: 0;
        }

        @keyframes gradientShift {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(5%, 5%) rotate(1deg); }
            66% { transform: translate(-5%, 5%) rotate(-1deg); }
        }

        .page-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            z-index: 1;
        }

        .login-card {
            background: rgba(26, 11, 46, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(129, 140, 248, 0.2);
            padding: 45px 40px;
            border-radius: 24px;
            box-shadow: 
                0 8px 32px rgba(0, 0, 0, 0.4),
                0 0 80px rgba(129, 140, 248, 0.1);
            width: 100%;
            max-width: 440px;
            position: relative;
            animation: cardFloat 6s ease-in-out infinite;
        }

        @keyframes cardFloat {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-8px); }
        }

        /* Glow effect on hover */
        .login-card::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(135deg, rgba(129, 140, 248, 0.3), rgba(165, 180, 252, 0.2));
            border-radius: 24px;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: -1;
        }

        .login-card:hover::before {
            opacity: 1;
        }

        /* Brand Header */
        .brand-header {
            text-align: center;
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 1px solid rgba(129, 140, 248, 0.2);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .brand-logo {
            width: 80px;
            height: 80px;
            margin-bottom: 16px;
            filter: drop-shadow(0 4px 12px rgba(129, 140, 248, 0.3));
            animation: logoGlow 3s ease-in-out infinite;
            object-fit: contain;
        }

        @keyframes logoGlow {
            0%, 100% { filter: drop-shadow(0 4px 12px rgba(129, 140, 248, 0.3)); }
            50% { filter: drop-shadow(0 4px 20px rgba(165, 180, 252, 0.5)); }
        }

        .brand-details {
            font-size: 0.7rem;
            color: var(--text-secondary);
            line-height: 1.6;
            letter-spacing: 0.3px;
            max-width: 350px;
        }


        /* Title */
        h2 {
            font-size: 1.85rem;
            font-weight: 700;
            color: var(--text-primary);
            text-align: center;
            margin-bottom: 10px;
            letter-spacing: -0.5px;
            text-shadow: 0 0 30px rgba(129, 140, 248, 0.3);
        }

        .subtitle {
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 28px;
        }

        /* Error & Warning Toasts */
        .error-toast, .warning-toast {
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .error-toast {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }

        .error-toast::before {
            content: '⚠';
            font-size: 1.2rem;
        }

        .warning-toast {
            background: rgba(245, 158, 11, 0.15);
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: #fcd34d;
        }

        .warning-toast::before {
            content: '⚡';
            font-size: 1.2rem;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Form */
        form {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .input-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        label {
            font-size: 0.8rem;
            color: var(--accent-light);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid var(--input-border);
            border-radius: 12px;
            background: var(--input-bg);
            backdrop-filter: blur(10px);
            color: var(--text-primary);
            font-size: 1rem;
            transition: all 0.3s ease;
            outline: none;
        }

        input[type="email"]::placeholder,
        input[type="password"]::placeholder {
            color: var(--text-secondary);
        }

        input:focus {
            border-color: var(--input-focus);
            background: rgba(15, 23, 42, 0.8);
            box-shadow: 0 0 0 3px rgba(129, 140, 248, 0.1);
            transform: translateY(-2px);
        }


        input:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
            font-size: 0.85rem;
            color: var(--text-secondary);
            cursor: pointer;
            user-select: none;
        }

        .checkbox-container input[type="checkbox"] {
            width: auto;
            cursor: pointer;
            accent-color: var(--accent-purple);
        }

        /* Button */
        button {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--accent-purple) 0%, var(--accent-light) 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 2px;
            cursor: pointer;
            margin-top: 10px;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            box-shadow: 0 4px 20px rgba(129, 140, 248, 0.3);
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
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 30px rgba(129, 140, 248, 0.5);
        }

        button:hover:not(:disabled)::before {
            left: 100%;
        }

        button:active:not(:disabled) {
            transform: translateY(0);
        }

        button:disabled {
            background: rgba(71, 85, 105, 0.5);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Security Info */
        .security-info {
            margin-top: 24px;
            padding: 14px;
            background: rgba(99, 102, 241, 0.1);
            border-left: 3px solid var(--accent-purple);
            border-radius: 8px;
            font-size: 0.75rem;
            color: var(--text-secondary);
            backdrop-filter: blur(10px);
        }

        .security-info strong {
            color: var(--accent-light);
        }

        /* Footer */
        footer {
            margin-top: 28px;
            padding-top: 24px;
            border-top: 1px solid rgba(129, 140, 248, 0.2);
            font-size: 0.7rem;
            color: var(--text-secondary);
            text-align: center;
            line-height: 1.6;
        }

        footer span {
            font-size: 0.85rem;
            color: var(--accent-light);
            display: block;
            margin-top: 8px;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-card {
                padding: 35px 25px;
                border-radius: 20px;
            }
            
            h2 {
                font-size: 1.6rem;
            }

            .brand-logo {
                width: 70px;
                height: 70px;
            }

            button {
                padding: 14px;
                font-size: 0.85rem;
            }
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
            <!-- <p class="subtitle">Enter your credentials to continue.</p> -->

            <?php if ($error): ?>
                <div class="error-toast">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error']) && $_GET['error'] === 'session_expired'): ?>
                <div class="warning-toast">
                    Your session has expired. Please login again.
                </div>
            <?php endif; ?>

            <?php if ($loginAttempts > 0 && $loginAttempts < MAX_LOGIN_ATTEMPTS): ?>
                <div class="warning-toast">
                    ⚠️ Failed attempts: <?php echo $loginAttempts; ?>/<?php echo MAX_LOGIN_ATTEMPTS; ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="loginForm">
                <div class="input-group">
                    <label for="email">Email Address</label>
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
                    <label for="password">Password</label>
                    <input 
                        type="password" 
                        name="password" 
                        id="password" 
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
                    <?php echo ($blockUntil ? 'Account Locked' : 'Login'); ?>
                </button>
            </form>

            <footer>
                St. Xavier's College (Autonomous), Kolkata<br>
                Mail Delivery & Tracking System v2.0
                <br><br>
                <span>Secure Database Authentication</span>
            </footer>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passInput = document.getElementById("password");
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