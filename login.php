<?php
/**
 * SXC MDTS - PRO VERSION (Apple Design Language)
 */
ini_set('display_errors', 0); // Hide raw errors for pro look
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

initializeSecureSession();

// Redirect if already logged in
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    header("Location: index.php");
    exit();
}

$error = "";
$blockUntil = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $userEmail = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $userPass = $_POST['app_password'] ?? '';
    
    if (empty($userEmail) || empty($userPass)) {
        $error = "Credentials required.";
    } else {
        $ipAddress = getClientIP();
        $rateLimit = checkRateLimit($userEmail, $ipAddress);
        
        if (!$rateLimit['allowed']) {
            $blockUntil = $rateLimit['block_until'];
            $error = "Security Lock: Try later.";
        } else {
            $authResult = authenticateWithSMTP($userEmail, $userPass);
            if ($authResult['success']) {
                clearFailedAttempts($userEmail, $ipAddress);
                $pdo = getDatabaseConnection();
                $userId = createUserIfNotExists($pdo, $userEmail, null);
                
                if ($userId) {
                    recordLoginActivity($userEmail, $userId, 'success');
                    $_SESSION['smtp_user'] = $userEmail;
                    $_SESSION['smtp_pass'] = $userPass;
                    $_SESSION['authenticated'] = true;
                    $_SESSION['user_id'] = $userId;
                    session_regenerate_id(true);
                    loadImapConfigToSession($userEmail, $userPass);
                    header("Location: index.php");
                    exit();
                }
            } else {
                recordFailedAttempt($userEmail, $ipAddress, $authResult['error']);
                $error = "Incorrect Apple ID or Password."; // Styled like Apple error
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
        $mail->Timeout = 8;
        $mail->smtpConnect();
        $mail->smtpClose();
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Auth Fail'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Sign In - SXC MDTS</title>
    <style>
        :root {
            --sf-font: -apple-system, BlinkMacSystemFont, "SF Pro Text", "SF Pro Display", "Helvetica Neue", Arial, sans-serif;
            --blue: #007AFF;
            --gray: #8e8e93;
            --input-bg: rgba(255, 255, 255, 0.8);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-font-smoothing: antialiased; }

        body {
            font-family: var(--sf-font);
            background: #ffffff;
            height: 100dvh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #1d1d1f;
        }

        /* Pro Entrance Animation */
        .stage {
            width: 100%;
            max-width: 380px;
            padding: 40px 20px;
            text-align: center;
            animation: fadeIn 1.2s cubic-bezier(0.2, 0.8, 0.2, 1);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.98) translateY(10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .logo {
            width: 88px;
            height: 88px;
            margin-bottom: 24px;
            border-radius: 20px;
            /* Apple App Icon style */
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        h1 {
            font-size: 32px;
            font-weight: 600;
            letter-spacing: -0.022em;
            margin-bottom: 30px;
        }

        /* Pro Floating Input System */
        .field {
            position: relative;
            margin-bottom: 1px;
            width: 100%;
        }

        input {
            width: 100%;
            height: 52px;
            padding: 12px 16px;
            font-size: 17px;
            font-family: var(--sf-font);
            border: 1px solid #d2d2d7;
            background: var(--input-bg);
            outline: none;
            transition: all 0.2s ease;
        }

        .top-field { border-radius: 12px 12px 0 0; border-bottom: none; }
        .bottom-field { border-radius: 0 0 12px 12px; }

        input:focus {
            z-index: 2;
            border-color: var(--blue);
            box-shadow: 0 0 0 4px rgba(0, 122, 255, 0.15);
        }

        /* Shake Animation for Errors */
        .shake { animation: shake 0.4s cubic-bezier(.36,.07,.19,.97) both; }
        @keyframes shake {
            10%, 90% { transform: translate3d(-1px, 0, 0); }
            20%, 80% { transform: translate3d(2px, 0, 0); }
            30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
            40%, 60% { transform: translate3d(4px, 0, 0); }
        }

        .error-hint {
            color: #ff3b30;
            font-size: 13px;
            margin-top: 15px;
            font-weight: 400;
            display: <?php echo $error ? 'block' : 'none'; ?>;
        }

        /* Pro Button: The "Blue Orb" */
        button#submitBtn {
            margin-top: 40px;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: none;
            background: #e8e8ed;
            color: #86868b;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: auto;
            margin-right: auto;
        }

        /* Activates button look when input is filled */
        input:valid ~ button#submitBtn, .ready button#submitBtn {
            background: #1d1d1f;
            color: white;
            transform: scale(1.1);
        }

        button#submitBtn:active { transform: scale(0.9); }

        .footer {
            position: fixed;
            bottom: 40px;
            font-size: 12px;
            color: var(--gray);
            text-align: center;
            width: 100%;
        }

        .arrow-icon {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }
    </style>
</head>
<body class="<?php echo $error ? 'shake' : ''; ?>">

    <div class="stage">
        <img src="https://upload.wikimedia.org/wikipedia/en/b/b0/St._Xavier%27s_College%2C_Kolkata_logo.jpg" class="logo" alt="SXC">
        <h1>Sign In</h1>

        <form method="POST" id="loginForm">
            <div class="field">
                <input type="email" name="email" class="top-field" placeholder="Email" required autofocus value="<?php echo htmlspecialchars($userEmail ?? ''); ?>">
            </div>
            <div class="field">
                <input type="password" name="app_password" id="pw" class="bottom-field" placeholder="Password" required>
            </div>

            <?php if ($error): ?>
                <p class="error-hint"><?php echo $error; ?></p>
            <?php endif; ?>

            <button type="submit" id="submitBtn">
                <svg class="arrow-icon" viewBox="0 0 24 24">
                    <path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"/>
                </svg>
            </button>
        </form>
    </div>

    <div class="footer">
        St. Xavier's College (Autonomous)<br>
        <span style="color: #c1c1c6; margin-top: 8px; display: block;">NIRF 2025: 8th Position</span>
    </div>

    <script>
        // High-end interaction: Button glows when both fields have content
        const form = document.getElementById('loginForm');
        const inputs = form.querySelectorAll('input');
        const btn = document.getElementById('submitBtn');

        const checkInputs = () => {
            let allFilled = true;
            inputs.forEach(i => { if(!i.value) allFilled = false; });
            btn.style.background = allFilled ? "#1d1d1f" : "#e8e8ed";
            btn.style.color = allFilled ? "#ffffff" : "#86868b";
        };

        inputs.forEach(i => i.addEventListener('input', checkInputs));

        form.onsubmit = () => {
            btn.innerHTML = `<svg width="20" height="20" viewBox="0 0 38 38" stroke="#fff"><g fill="none" fill-rule="evenodd"><g transform="translate(1 1)" stroke-width="2"><circle stroke-opacity=".5" cx="18" cy="18" r="18"/><path d="M36 18c0-9.94-8.06-18-18-18"><animateTransform attributeName="transform" type="rotate" from="0 18 18" to="360 18 18" dur="1s" repeatCount="indefinite"/></path></g></g></svg>`;
            btn.style.transform = "scale(0.9)";
        };
    </script>
</body>
</html>