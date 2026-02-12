<?php
/**
 * SXC MDTS - ULTRA PRO (Apple Event Edition)
 */
ini_set('display_errors', 0);
require_once 'vendor/autoload.php';
require_once 'config.php';
require_once 'db_config.php';
require_once 'login_auth_helper.php';

initializeSecureSession();
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    header("Location: index.php"); exit();
}

$error = $_GET['error'] ?? ""; 
// Logic for POST goes here (same as previous robust backend)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Sign in to SXC</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #ffffff;
            --secondary: rgba(255, 255, 255, 0.7);
            --glass: rgba(255, 255, 255, 0.08);
            --border: rgba(255, 255, 255, 0.15);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-font-smoothing: antialiased; }

        body {
            font-family: 'Inter', -apple-system, system-ui, sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #000;
            overflow: hidden;
            color: var(--primary);
        }

        /* Animated Apple Mesh Gradient */
        .mesh-gradient {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            z-index: -1;
            background: linear-gradient(45deg, #000000, #1a1a1a);
        }

        .orb {
            position: absolute;
            filter: blur(80px);
            opacity: 0.5;
            border-radius: 50%;
            animation: move 20s infinite alternate;
        }

        .orb-1 { width: 500px; height: 500px; background: #4338ca; top: -10%; left: -10%; }
        .orb-2 { width: 400px; height: 400px; background: #7c3aed; bottom: -5%; right: -5%; animation-delay: -5s; }
        .orb-3 { width: 300px; height: 300px; background: #2563eb; top: 40%; left: 30%; animation-duration: 15s; }

        @keyframes move {
            from { transform: translate(0, 0) scale(1); }
            to { transform: translate(100px, 50px) scale(1.2); }
        }

        /* Cinematic Container */
        .login-card {
            width: 100%;
            max-width: 420px;
            padding: 48px;
            background: var(--glass);
            backdrop-filter: blur(40px) saturate(180%);
            -webkit-backdrop-filter: blur(40px) saturate(180%);
            border-radius: 32px;
            border: 1px solid var(--border);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            text-align: center;
            transform: translateY(0);
            transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .logo {
            width: 72px; height: 72px;
            margin-bottom: 24px;
            filter: drop-shadow(0 0 15px rgba(255,255,255,0.2));
        }

        h1 {
            font-size: 24px;
            font-weight: 500;
            letter-spacing: -0.015em;
            margin-bottom: 32px;
            color: var(--primary);
        }

        /* Invisible but sleek inputs */
        .input-group {
            position: relative;
            margin-bottom: 12px;
        }

        input {
            width: 100%;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 16px 20px;
            color: white;
            font-size: 16px;
            outline: none;
            transition: all 0.3s;
        }

        input:focus {
            border-color: rgba(255,255,255,0.5);
            background: rgba(0, 0, 0, 0.4);
            box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.05);
        }

        /* The Apple Blue Primary Button */
        .btn-primary {
            width: 100%;
            padding: 16px;
            margin-top: 24px;
            border-radius: 14px;
            border: none;
            background: #fff;
            color: #000;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-primary:hover {
            transform: scale(1.02);
            background: #f5f5f7;
        }

        .btn-primary:active {
            transform: scale(0.98);
        }

        .footer {
            margin-top: 32px;
            font-size: 13px;
            color: var(--secondary);
        }

        .error-shake {
            animation: shake 0.4s cubic-bezier(.36,.07,.19,.97) both;
            border-color: #ff453a !important;
        }

        @keyframes shake {
            10%, 90% { transform: translate3d(-1px, 0, 0); }
            20%, 80% { transform: translate3d(2px, 0, 0); }
            30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
            40%, 60% { transform: translate3d(4px, 0, 0); }
        }
    </style>
</head>
<body>

    <div class="mesh-gradient">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
    </div>

    <main class="login-card" id="card">
        <img src="https://upload.wikimedia.org/wikipedia/en/b/b0/St._Xavier%27s_College%2C_Kolkata_logo.jpg" class="logo" alt="SXC Logo">
        <h1>Sign in with Institutional ID</h1>

        <form action="login.php" method="POST" id="authForm">
            <div class="input-group">
                <input type="email" name="email" placeholder="Email" required autocomplete="username">
            </div>
            <div class="input-group">
                <input type="password" name="app_password" placeholder="Password" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn-primary" id="btn">Continue</button>
        </form>

        <div class="footer">
            Your login is encrypted and secure.
        </div>
    </main>

    <script>
        const form = document.getElementById('authForm');
        const card = document.getElementById('card');
        const btn = document.getElementById('btn');

        // Handle error shake from PHP
        <?php if ($error): ?>
            card.classList.add('error-shake');
            setTimeout(() => card.classList.remove('error-shake'), 500);
        <?php endif; ?>

        form.onsubmit = () => {
            btn.innerHTML = 'Verifying...';
            btn.style.opacity = '0.7';
            btn.style.cursor = 'wait';
        };
    </script>
</body>
</html>