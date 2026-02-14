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
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Text:wght@400;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <style>
        :root {
            /* Academic Color Palette */
            --primary-navy:    #1a2332;
            --secondary-navy:  #2d3748;
            --accent-gold:     #c49c5a;
            --accent-burgundy: #8b3a3a;
            --accent-teal:     #2c7a7b;
            --text-primary:    #1a1a1a;
            --text-secondary:  #4a5568;
            --text-muted:      #718096;
            --bg-primary:      #f7f9fc;
            --bg-secondary:    #e2e8f0;
            --surface:         #ffffff;
            --border-light:    #cbd5e0;
            --border-medium:   #a0aec0;
            --shadow-sm:       0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md:       0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg:       0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl:       0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        *,*::before,*::after { 
            box-sizing: border-box; 
            margin: 0; 
            padding: 0; 
        }

        body, html {
            height: 100%;
            font-family: 'Inter', -apple-system, sans-serif;
            background: linear-gradient(135deg, #f7f9fc 0%, #e2e8f0 100%);
            color: var(--text-primary);
            -webkit-font-smoothing: antialiased;
            position: relative;
        }

        /* Academic Background Pattern */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(90deg, rgba(203, 213, 224, 0.1) 1px, transparent 1px),
                linear-gradient(rgba(203, 213, 224, 0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            pointer-events: none;
            z-index: 0;
        }

        .page-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 24px;
            position: relative;
            z-index: 1;
        }

        .login-card {
            background: var(--surface);
            border: 1px solid var(--border-light);
            border-top: 4px solid var(--primary-navy);
            padding: 48px 44px;
            width: 100%;
            max-width: 480px;
            box-shadow: var(--shadow-xl);
            position: relative;
        }

        .login-card::after {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(to bottom, var(--accent-gold) 0%, var(--accent-burgundy) 50%, var(--accent-teal) 100%);
        }

        /* Brand Header */
        .brand-header {
            text-align: center;
            margin-bottom: 36px;
            padding-bottom: 28px;
            border-bottom: 2px solid var(--bg-secondary);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .brand-logo {
            width: 90px;
            height: 90px;
            margin-bottom: 16px;
            object-fit: contain;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
        }

        .brand-details {
            font-size: 0.7rem;
            color: var(--text-muted);
            line-height: 1.7;
            letter-spacing: 0.3px;
            max-width: 380px;
        }

        /* Title */
        h2 {
            font-size: 1.875rem;
            font-weight: 600;
            color: var(--primary-navy);
            text-align: center;
            margin-bottom: 20px;
            letter-spacing: -0.5px;
            font-family: 'Crimson Text', Georgia, serif;
            position: relative;
        }

        h2::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background: linear-gradient(to right, var(--accent-gold), var(--accent-burgundy));
        }

        .subtitle {
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 32px;
        }

        /* Error & Warning Toasts */
        .error-toast, .warning-toast {
            padding: 14px 18px;
            margin-bottom: 20px;
            font-size: 0.875rem;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            border-left: 4px solid;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .error-toast {
            background: #fef2f2;
            border-color: var(--accent-burgundy);
            color: #7f1d1d;
        }

        .error-toast::before {
            content: '⚠';
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .warning-toast {
            background: #fffbeb;
            border-color: #d97706;
            color: #78350f;
        }

        .warning-toast::before {
            content: '⚡';
            font-size: 1.2rem;
            flex-shrink: 0;
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
            font-size: 0.8125rem;
            color: var(--text-primary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            height: 44px;
            border: 2px solid var(--border-light);
            padding: 0 16px;
            font-family: 'Inter', -apple-system, sans-serif;
            font-size: 0.9375rem;
            color: var(--text-primary);
            background: var(--surface);
            outline: none;
            transition: all 0.2s ease;
        }

        input[type="email"]::placeholder,
        input[type="password"]::placeholder {
            color: var(--text-muted);
        }

        input:focus {
            border-color: var(--primary-navy);
            box-shadow: 0 0 0 3px rgba(26, 35, 50, 0.1);
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
            margin-top: 8px;
            font-size: 0.8125rem;
            color: var(--text-secondary);
            cursor: pointer;
            user-select: none;
        }

        .checkbox-container input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: var(--primary-navy);
        }

        /* Button */
        button {
            width: 100%;
            height: 48px;
            padding: 0 24px;
            background: linear-gradient(135deg, var(--primary-navy) 0%, var(--secondary-navy) 100%);
            color: white;
            border: none;
            font-family: 'Inter', -apple-system, sans-serif;
            font-size: 0.9375rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            cursor: pointer;
            margin-top: 8px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
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
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
            transition: left 0.6s;
        }

        button:hover:not(:disabled) {
            background: linear-gradient(135deg, var(--secondary-navy) 0%, var(--primary-navy) 100%);
            box-shadow: var(--shadow-lg);
            transform: translateY(-1px);
        }

        button:hover:not(:disabled)::before {
            left: 100%;
        }

        button:active:not(:disabled) {
            transform: translateY(0);
        }

        button:disabled {
            background: var(--border-medium);
            cursor: not-allowed;
            transform: none;
            opacity: 0.7;
        }

        /* Security Info */
        .security-info {
            margin-top: 24px;
            padding: 14px 16px;
            background: #f0f9ff;
            border-left: 4px solid var(--accent-teal);
            font-size: 0.75rem;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .security-info strong {
            color: var(--accent-teal);
            font-weight: 600;
        }

        /* Footer */
        footer {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 2px solid var(--bg-secondary);
            font-size: 0.75rem;
            color: var(--text-muted);
            text-align: center;
            line-height: 1.8;
        }

        footer span {
            font-size: 0.8125rem;
            color: var(--primary-navy);
            font-weight: 600;
            display: block;
            margin-top: 10px;
            letter-spacing: 0.5px;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-card {
                padding: 36px 28px;
            }
            
            h2 {
                font-size: 1.625rem;
            }

            .brand-logo {
                width: 75px;
                height: 75px;
            }

            button {
                height: 44px;
                font-size: 0.875rem;
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
                <span style="font-size:15px;font-weight:600;color:#4f5d73;">Secure Database Authentication</span>
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