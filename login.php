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
            $blockUntilTime = strtotime($rateLimit['block_until']);
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
            background: radial-gradient(circle at 30% 50%, rgba(79, 93, 115, 0.03) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
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
            background: white;
            padding: 40px 35px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            width: 100%;
            max-width: 420px;
            position: relative;
        }

        /* Brand Header */
        .brand-header {
            text-align: center;
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f0f0f0;
            display:flex;
        }

        .brand-logo {
            width: 70px;
            height: 70px;
            margin-bottom: 12px;
            object-fit: contain;
        }

        .brand-details {
    font-size: 0.68rem;
    color: #888;
    line-height: 1.5;
    letter-spacing: 0.3px;

    display: flex;
    align-items: center;      /* vertical center */
    justify-content: flex-start; /* horizontal left */
}


        /* Title */
        h2 {
            font-size: 1.75rem;
            margin-bottom: 8px;
            color: rgb(79, 93, 115);
            font-weight: 600;
            text-align: left !important;
            letter-spacing: -0.5px;
        }

        .subtitle {
            font-size: 0.9rem;
            color: #888;
            text-align: center;
            margin-bottom: 25px;
            font-weight: 400;
        }

        /* Error/Warning Messages */
        .error-toast {
            background: linear-gradient(135deg, #fee 0%, #fdd 100%);
            color: #c33;
            padding: 14px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            border-left: 3px solid #c33;
            animation: slideDown 0.3s ease;
        }

        .warning-toast {
            background: linear-gradient(135deg, #fff4e5 0%, #ffe8cc 100%);
            color: #d68000;
            padding: 14px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            border-left: 3px solid #ff9800;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
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
            gap: 20px;
        }

        .input-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        label {
            font-size: 0.8rem;
            color: #555;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
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