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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <style>
        :root {
            --ink:       #1a1a2e;
            --ink-2:     #2d2d44;
            --ink-3:     #6b6b8a;
            --ink-4:     #a8a8c0;
            --bg:        #f0f0f7;
            --surface:   #ffffff;
            --surface-2: #f7f7fc;
            --border:    rgba(100,100,160,0.12);
            --border-2:  rgba(100,100,160,0.22);
            --blue:      #5781a9;
            --blue-2:    #c6d3ea;
            --blue-glow: rgba(79,70,229,0.15);
            --red:       #ef4444;
            --green:     #10b981;
            --amber:     #f59e0b;
            --r:         10px;
            --r-lg:      16px;
            --shadow:    0 1px 3px rgba(79,70,229,0.08), 0 4px 16px rgba(79,70,229,0.06);
            --shadow-lg: 0 8px 32px rgba(79,70,229,0.14), 0 2px 8px rgba(0,0,0,0.06);
            --ease:      cubic-bezier(.4,0,.2,1);
            --ease-spring: cubic-bezier(.34,1.56,.64,1);
        }

        *,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }

        body, html {
            height: 100%;
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg);
            color: var(--ink);
            -webkit-font-smoothing: antialiased;
        }

        .page-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .login-card {
            background: var(--surface);
            border: 1.5px solid var(--border);
            border-radius: var(--r-lg);
            padding: 40px;
            width: 100%;
            max-width: 440px;
            box-shadow: var(--shadow-lg);
            position: relative;
            animation: cardIn 0.4s var(--ease-spring) forwards;
        }

        @keyframes cardIn {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: none; }
        }

        .login-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(79,70,229,.04), transparent);
            opacity: 0;
            transition: opacity 0.3s;
            border-radius: var(--r-lg);
            pointer-events: none;
        }

        .login-card:hover::before {
            opacity: 1;
        }

        /* Brand Header */
        .brand-header {
            text-align: center;
            margin-bottom: 28px;
            padding-bottom: 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .brand-logo {
            width: 80px;
            height: 80px;
            margin-bottom: 12px;
            object-fit: contain;
        }

        .brand-details {
            font-size: 0.7rem;
            color: var(--ink-3);
            line-height: 1.6;
            letter-spacing: 0.3px;
            max-width: 360px;
        }


        /* Title */
        h2 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--ink);
            text-align: center;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .subtitle {
            text-align: center;
            color: var(--ink-3);
            font-size: 0.875rem;
            margin-bottom: 24px;
        }

        /* Error & Warning Toasts */
        .error-toast, .warning-toast {
            padding: 12px 16px;
            border-radius: var(--r);
            margin-bottom: 16px;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 8px;
            animation: toastIn 0.25s var(--ease-spring) forwards;
        }

        @keyframes toastIn {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: none; }
        }

        .error-toast {
            background: rgba(239, 68, 68, 0.1);
            border: 1.5px solid rgba(239, 68, 68, 0.3);
            color: var(--red);
        }

        .error-toast::before {
            content: '⚠';
            font-size: 1.1rem;
        }

        .warning-toast {
            background: rgba(245, 158, 11, 0.1);
            border: 1.5px solid rgba(245, 158, 11, 0.3);
            color: var(--amber);
        }

        .warning-toast::before {
            content: '⚡';
            font-size: 1.1rem;
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
            color: var(--ink-2);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            height: 40px;
            border: 1.5px solid var(--border-2);
            border-radius: 8px;
            padding: 0 12px;
            font-family: inherit;
            font-size: 14px;
            color: var(--ink);
            background: var(--surface);
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input[type="email"]::placeholder,
        input[type="password"]::placeholder {
            color: var(--ink-4);
        }

        input:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px var(--blue-glow);
        }


        input:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: var(--surface-2);
        }

        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
            font-size: 0.8rem;
            color: var(--ink-3);
            cursor: pointer;
            user-select: none;
        }

        .checkbox-container input[type="checkbox"] {
            width: auto;
            cursor: pointer;
            accent-color: var(--blue);
        }

        /* Button */
        button {
            width: 100%;
            height: 40px;
            padding: 0 16px;
            background: var(--blue);
            color: white;
            border: none;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 8px;
            transition: all 0.18s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        button:hover:not(:disabled) {
            background: var(--blue-2);
            transform: translateY(-1px);
            box-shadow: var(--shadow);
        }

        button:active:not(:disabled) {
            transform: scale(0.98);
        }

        button:disabled {
            background: var(--ink-4);
            cursor: not-allowed;
            transform: none;
            opacity: 0.6;
        }

        /* Security Info */
        .security-info {
            margin-top: 20px;
            padding: 12px 14px;
            background: var(--surface-2);
            border-left: 3px solid var(--blue);
            border-radius: var(--r);
            font-size: 0.75rem;
            color: var(--ink-3);
        }

        .security-info strong {
            color: var(--blue);
        }

        /* Footer */
        footer {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            font-size: 0.7rem;
            color: var(--ink-3);
            text-align: center;
            line-height: 1.6;
        }

        footer span {
            font-size: 0.8rem;
            color: var(--ink-2);
            font-weight: 600;
            display: block;
            margin-top: 8px;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-card {
                padding: 32px 24px;
            }
            
            h2 {
                font-size: 1.5rem;
            }

            .brand-logo {
                width: 70px;
                height: 70px;
            }

            button {
                height: 38px;
                font-size: 13px;
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