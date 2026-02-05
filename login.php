<?php
session_start();
require 'vendor/autoload.php';
require 'config.php';

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_email = $_POST['email'];
    $user_pass = $_POST['app_password'];

    $mail = new PHPMailer(true);
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = 'html';
    try {
        $mail->isSMTP();
        $mail->Host       = env("SMTP_HOST"); 
        $mail->SMTPAuth   = true;
        $mail->Username   = $user_email;
        $mail->Password   = $user_pass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = env("SMTP_PORT");

        $mail->setFrom($user_email, 'NoReply Security');
        $mail->addAddress($user_email); 
        $mail->Subject = "Login Verification";
        $mail->isHTML(true);
        $mail->Body    = "<b>Login Successful!</b><br>If this wasn't you, please revoke your App Password.";

        if ($mail->send()) {
            $_SESSION['smtp_user'] = $user_email;
            $_SESSION['smtp_pass'] = $user_pass;
            header("Location: index.php");
            exit();
        }
    } catch (Exception $e) {
        $error = "Authentication Failed. Please verify credentials.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Access | SXC Data Science</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&family=Playfair+Display:ital,wght@0,700;1,700&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --primary-accent: #000000;
            --nature-green: #2d5a27;
            --soft-white: #f8f9fa;
        }

        body,
        html {
            margin: 0;
            padding: 0;
            height: 100%;
            font-family: 'Inter', sans-serif;
            background-color: #f5f5f7;
            background-image: radial-gradient(#e5e7eb 1px, transparent 1px);
            background-size: 40px 40px;
        }

        .page-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            width: 100vw;
            padding: 20px;
            box-sizing: border-box;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            /* Original width restored */
            padding: 40px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(20, 40, 80, 0.35);
            animation: fadeIn 0.8s ease-out;
            box-sizing: border-box;
        }

        /* Institutional Header */
        .brand-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f0f0f0;
        }

        .brand-logo {
            width: 60px;
            /* Sized to fit narrow container */
            height: auto;
            flex-shrink: 0;
        }

        .brand-details {
            font-size: 0.6rem;
            /* Scaled down for the 420px width */
            line-height: 1.3;
            color: #666;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }

        h2 {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            color: var(--primary-accent);
            margin: 0 0 5px 0;
        }

        .subtitle {
            color: #777;
            font-size: 0.9rem;
            margin-bottom: 30px;
        }

        .input-group {
            margin-bottom: 20px;
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
        input[type="password"],
        input[type="text"] {
            width: 100%;
            padding: 12px 5px;
            border: none;
            border-bottom: 1px solid #e0e0e0;
            background: transparent;
            font-size: 1rem;
            transition: border-color 0.3s;
            outline: none;
            box-sizing: border-box;
        }

        input:focus {
            border-bottom-color: var(--nature-green);
        }

        .show-pass-container {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 12px;
            font-size: 0.8rem;
            color: #666;
            cursor: pointer;
            user-select: none;
        }

        button {
            width: 100%;
            padding: 16px;
            background: var(--primary-accent);
            color: white;
            border: none;
            border-radius: 4px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 2px;
            cursor: pointer;
            margin-top: 25px;
            transition: all 0.3s ease;
        }

        button:hover {
            background: #222;
            transform: translateY(-1px);
        }

        .error-toast {
            background: #fff5f5;
            color: #c53030;
            padding: 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            margin-bottom: 20px;
            border-left: 4px solid #c53030;
        }

        footer {
            margin-top: 35px;
            font-size: 0.7rem;
            color: #bbb;
            text-align: center;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
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
            <p class="subtitle">Enter institutional credentials to continue.</p>

            <?php if ($error): ?>
            <div class="error-toast">
                <?php echo $error; ?>
            </div>
            <?php endif; ?>

            <form method="POST">
                <div class="input-group">
                    <label for="email">User ID</label>
                    <input type="email" name="email" id="email" placeholder="user@sxccal.edu" required>
                </div>

                <div class="input-group">
                    <label for="app_password">App Password</label>
                    <input type="password" name="app_password" id="app_password" placeholder="enter_password" required>

                    <label class="show-pass-container">
                        <input type="checkbox" id="toggleCheck" onclick="togglePassword()">
                        <span>Show Password</span>
                    </label>
                </div>

                <button type="submit">Verify & Update</button>
            </form>

            <!-- <footer>
                &copy;
                <?php echo date("Y"); ?> Dept. of Data Science | SXC
            </footer> -->
        </div>
    </div>

    <script>
        function togglePassword() {
            const passInput = document.getElementById("app_password");
            passInput.type = passInput.type === "password" ? "text" : "password";
        }
    </script>
</body>

</html>