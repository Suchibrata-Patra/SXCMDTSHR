<?php
/**
 * ============================================================
 * SXC MDTS - PREMIUM AUTHENTICATION INTERFACE
 * ============================================================
 */

// 1. DEBUGGING (Turn off in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 2. DEPENDENCY CHECK
$required_files = ['vendor/autoload.php', 'config.php', 'db_config.php', 'login_auth_helper.php'];
foreach ($required_files as $file) {
    if (!file_exists($file)) {
        die("<div style='font-family:sans-serif; padding:20px; background:#fff5f5; color:#c53030; border-radius:8px;'>
                <strong>System Error:</strong> Missing critical file: <code>$file</code>. 
                <br>Please ensure all backend files are uploaded to the server.
             </div>");
    }
}

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
    
    if (empty($userEmail) || empty($userPass)) {
        $error = "Email and password are required.";
    } else {
        $ipAddress = getClientIP();
        $rateLimit = checkRateLimit($userEmail, $ipAddress);
        
        if (!$rateLimit['allowed']) {
            $blockUntilTime = strtotime($rateLimit['block_until']);
            $remainingMinutes = ceil(($blockUntilTime - time()) / 60);
            $error = "Too many failed attempts. Try again in $remainingMinutes minutes.";
            $blockUntil = $rateLimit['block_until'];
        } else {
            $authResult = authenticateWithSMTP($userEmail, $userPass);
            
            if ($authResult['success']) {
                clearFailedAttempts($userEmail, $ipAddress);
                $pdo = getDatabaseConnection();
                $userId = createUserIfNotExists($pdo, $userEmail, null);
                
                if (!$userId) {
                    $error = "Database sync failed. Contact Admin.";
                } else {
                    $loginActivityId = recordLoginActivity($userEmail, $userId, 'success');
                    $_SESSION['smtp_user'] = $userEmail;
                    $_SESSION['smtp_pass'] = $userPass;
                    $_SESSION['authenticated'] = true;
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['login_time'] = time();
                    $_SESSION['ip_address'] = $ipAddress;
                    
                    session_regenerate_id(true);
                    createUserSession($userId, $loginActivityId);
                    loadImapConfigToSession($userEmail, $userPass);
                    
                    $superAdmins = ['admin@sxccal.edu', 'hod@sxccal.edu'];
                    $_SESSION['user_role'] = in_array($userEmail, $superAdmins) ? 'super_admin' : 'user';
                    
                    header("Location: index.php");
                    exit();
                }
            } else {
                recordFailedAttempt($userEmail, $ipAddress, $authResult['error']);
                $pdo = getDatabaseConnection();
                $userId = getUserId($pdo, $userEmail);
                recordLoginActivity($userEmail, $userId, 'failed', $authResult['error']);
                
                $rateLimit = checkRateLimit($userEmail, $ipAddress);
                $loginAttempts = $rateLimit['attempts'];
                
                if (!$rateLimit['allowed']) {
                    $blockUntil = $rateLimit['block_until'];
                    $error = "Account locked due to multiple failures.";
                } else {
                    $remaining = MAX_LOGIN_ATTEMPTS - $loginAttempts;
                    $error = "Invalid credentials. $remaining attempts left.";
                }
            }
        }
    }
}

function authenticateWithSMTP($email, $password) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = env("SMTP_HOST");
        $mail->SMTPAuth = true;
        $mail->Username = $email;
        $mail->Password = $password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = env("SMTP_PORT");
        $mail->Timeout = 10;
        $mail->SMTPDebug = 0;
        
        $mail->smtpConnect();
        $mail->smtpClose();
        return ['success' => true, 'error' => null];
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'SMTP Authentication Failed'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SXC MDTS | Authentication</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --apple-blue: #0071e3;
            --apple-gray: #86868b;
            --apple-bg: #f5f5f7;
            --glass-bg: rgba(255, 255, 255, 0.8);
            --error-red: #ff3b30;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-font-smoothing: antialiased; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--apple-bg);
            color: #1d1d1f;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }

        .bg-gradient {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: radial-gradient(circle at 20% 30%, #ffffff 0%, #f5f5f7 100%);
            z-index: -1;
        }

        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 20px;
            text-align: center;
            animation: appleReveal 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes appleReveal {
            from { opacity: 0; transform: scale(0.95) translateY(20px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .brand-logo { width: 80px; height: auto; margin-bottom: 20px; }

        h1 { font-size: 28px; font-weight: 600; letter-spacing: -0.5px; margin-bottom: 8px; }

        .subtitle { font-size: 16px; color: var(--apple-gray); margin-bottom: 32px; }

        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border-radius: 24px;
            padding: 32px;
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 20px 40px rgba(0,0,0,0.06);
        }

        .input-wrapper { position: relative; margin-bottom: 12px; }

        input {
            width: 100%;
            padding: 16px;
            border-radius: 12px;
            border: 1px solid #d2d2d7;
            background: rgba(255, 255, 255, 0.5);
            font-size: 17px;
            transition: all 0.2s ease;
            outline: none;
        }

        input:focus {
            border-color: var(--apple-blue);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(0, 113, 227, 0.1);
        }

        .error-message {
            background: rgba(255, 59, 48, 0.08);
            color: var(--error-red);
            font-size: 13px;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        button#submitBtn {
            width: 100%;
            padding: 16px;
            border-radius: 12px;
            border: none;
            background: #1d1d1f;
            color: white;
            font-size: 17px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 10px;
        }

        button#submitBtn:hover { opacity: 0.9; transform: translateY(-1px); }
        button#submitBtn:active { transform: scale(0.98); }
        button#submitBtn:disabled { background: #d2d2d7; cursor: not-allowed; }

        .toggle-pass {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--apple-blue);
            font-size: 13px;
            cursor: pointer;
            border: none;
            background: none;
            font-weight: 500;
        }

        .footer-text { margin-top: 32px; font-size: 12px; color: var(--apple-gray); line-height: 1.6; }
    </style>
</head>
<body>
    <div class="bg-gradient"></div>

    <div class="login-container">
        <img src="https://upload.wikimedia.org/wikipedia/en/b/b0/St._Xavier%27s_College%2C_Kolkata_logo.jpg" alt="SXC Logo" class="brand-logo">
        <h1>Verify Identity</h1>
        <p class="subtitle">Use your institutional app password.</p>

        <div class="glass-card">
            <?php if ($error): ?>
                <div class="error-message">
                    ✕ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="loginForm">
                <div class="input-wrapper">
                    <input type="email" name="email" placeholder="Email address" required autofocus
                    <?php echo ($blockUntil ? 'disabled' : ''); ?>>
                </div>

                <div class="input-wrapper">
                    <input type="password" name="app_password" id="app_password" placeholder="Password" required
                    <?php echo ($blockUntil ? 'disabled' : ''); ?>>
                    <button type="button" class="toggle-pass" onclick="togglePassword()">Show</button>
                </div>

                <button type="submit" id="submitBtn" <?php echo ($blockUntil ? 'disabled' : ''); ?>>
                    <?php echo ($blockUntil ? 'Account Locked' : 'Continue'); ?>
                </button>
            </form>
        </div>

        <div class="footer-text">
            St. Xavier's College (Autonomous), Kolkata <br>
            Autonomous College | NIRF 2025: 8th Position
        </div>
    </div>

    <script>
        function togglePassword() {
            const passInput = document.getElementById("app_password");
            const btn = document.querySelector(".toggle-pass");
            const isPass = passInput.type === "password";
            passInput.type = isPass ? "text" : "password";
            btn.textContent = isPass ? "Hide" : "Show";
        }

        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<span style="opacity:0.6">Verifying...</span>';
        });
    </script>
</body>
</html>