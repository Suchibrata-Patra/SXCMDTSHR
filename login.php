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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lora:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Professional Monochromatic Palette */
            --primary-dark:    #1a1d23;
            --secondary-dark:  #2d3137;
            --tertiary-dark:   #3a3f47;
            --accent-blue:     #4a5a6a;
            --text-primary:    #1a1d23;
            --text-secondary:  #4a5568;
            --text-muted:      #6b7280;
            --text-light:      #9ca3af;
            --bg-primary:      #fafbfc;
            --bg-secondary:    #f4f5f7;
            --surface:         #ffffff;
            --border-primary:  #e5e7eb;
            --border-secondary:#d1d5db;
            --shadow-sm:       0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md:       0 4px 6px -1px rgba(0, 0, 0, 0.08), 0 2px 4px -1px rgba(0, 0, 0, 0.04);
            --shadow-lg:       0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.04);
            --shadow-xl:       0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 10px 10px -5px rgba(0, 0, 0, 0.03);
        }

        *,*::before,*::after { 
            box-sizing: border-box; 
            margin: 0; 
            padding: 0; 
        }

        body, html {
            height: 100%;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .page-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .login-card {
            background: var(--surface);
            border: 1px solid var(--border-primary);
            padding: 52px 48px;
            width: 100%;
            max-width: 460px;
            box-shadow: var(--shadow-lg);
            position: relative;
        }

        .login-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 2px;
            height: 100%;
            background: var(--primary-dark);
        }

        /* Brand Header */
        .brand-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 32px;
            border-bottom: 1px solid var(--border-primary);
        }

        .brand-logo {
            width: 85px;
            height: 85px;
            margin-bottom: 18px;
            object-fit: contain;
            opacity: 0.95;
        }

        .brand-details {
            font-size: 0.6875rem;
            color: var(--text-muted);
            line-height: 1.75;
            letter-spacing: 0.2px;
            max-width: 380px;
            margin: 0 auto;
        }

        /* Title */
        h2 {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--primary-dark);
            text-align: center;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
            font-family: 'Lora', Georgia, serif;
        }

        .subtitle {
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: 36px;
            font-weight: 400;
        }

        /* Error & Warning Messages */
        .error-toast, .warning-toast {
            padding: 13px 16px;
            margin-bottom: 20px;
            font-size: 0.8125rem;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            border: 1px solid;
            background: var(--surface);
        }

        .error-toast {
            border-color: #e5e5e5;
            color: var(--text-primary);
            border-left-width: 3px;
            border-left-color: var(--primary-dark);
        }

        .error-toast::before {
            content: '!';
            font-size: 1rem;
            flex-shrink: 0;
            font-weight: 700;
            width: 20px;
            height: 20px;
            border: 2px solid var(--primary-dark);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        .warning-toast {
            border-color: #e5e5e5;
            color: var(--text-primary);
            border-left-width: 3px;
            border-left-color: var(--accent-blue);
        }

        .warning-toast::before {
            content: 'i';
            font-size: 0.875rem;
            flex-shrink: 0;
            font-weight: 700;
            width: 20px;
            height: 20px;
            border: 2px solid var(--accent-blue);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        /* Form */
        form {
            display: flex;
            flex-direction: column;
            gap: 26px;
        }

        .input-group {
            display: flex;
            flex-direction: column;
            gap: 9px;
        }

        label {
            font-size: 0.8125rem;
            color: var(--text-primary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            height: 46px;
            border: 1px solid var(--border-secondary);
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
            color: var(--text-light);
            font-weight: 400;
        }

        input:focus {
            border-color: var(--primary-dark);
            box-shadow: 0 0 0 1px var(--primary-dark);
        }

        input:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background: var(--bg-secondary);
        }

        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 9px;
            margin-top: 10px;
            font-size: 0.8125rem;
            color: var(--text-secondary);
            cursor: pointer;
            user-select: none;
        }

        .checkbox-container input[type="checkbox"] {
            width: 15px;
            height: 15px;
            cursor: pointer;
            accent-color: var(--primary-dark);
        }

        /* Button */
        button {
            width: 100%;
            height: 50px;
            padding: 0 28px;
            background: var(--primary-dark);
            color: white;
            border: none;
            font-family: 'Inter', -apple-system, sans-serif;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            margin-top: 10px;
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        button:hover:not(:disabled) {
            background: var(--secondary-dark);
            box-shadow: var(--shadow-md);
        }

        button:active:not(:disabled) {
            transform: translateY(1px);
            box-shadow: var(--shadow-sm);
        }

        button:disabled {
            background: var(--border-secondary);
            cursor: not-allowed;
            opacity: 0.6;
        }

        /* Security Info */
        .security-info {
            margin-top: 28px;
            padding: 14px 16px;
            background: var(--bg-secondary);
            border-left: 2px solid var(--accent-blue);
            font-size: 0.75rem;
            color: var(--text-secondary);
            line-height: 1.7;
        }

        .security-info strong {
            color: var(--text-primary);
            font-weight: 600;
        }

        /* Footer */
        footer {
            margin-top: 36px;
            padding-top: 28px;
            border-top: 1px solid var(--border-primary);
            font-size: 0.75rem;
            color: var(--text-muted);
            text-align: center;
            line-height: 1.9;
        }

        footer span {
            font-size: 0.8125rem;
            color: var(--primary-dark);
            font-weight: 600;
            display: block;
            margin-top: 12px;
            letter-spacing: 0.3px;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-card {
                padding: 40px 32px;
            }
            
            h2 {
                font-size: 1.5rem;
            }

            .brand-logo {
                width: 75px;
                height: 75px;
            }

            button {
                height: 46px;
                font-size: 0.8125rem;
            }

            input[type="email"],
            input[type="password"] {
                height: 44px;
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