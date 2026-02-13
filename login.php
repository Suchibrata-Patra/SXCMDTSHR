<?php
/**
 * ============================================================
 * SECURE LOGIN PAGE - OPTIMIZED & FAST
 * ============================================================
 * Features:
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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $userEmail = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $userPass = $_POST['app_password'] ?? '';
    
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
            // Attempt SMTP authentication (FAST - no email sending)
            $authResult = authenticateWithSMTP($userEmail, $userPass);
            
            if ($authResult['success']) {
                // ============================================================
                // SUCCESSFUL LOGIN
                // ============================================================
                
                // Clear failed attempts
                clearFailedAttempts($userEmail, $ipAddress);
                
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
                    
                    // Set success flag for animation
                    $_SESSION['login_success_animation'] = true;
                    
                    // Success - don't redirect immediately, let JS handle it
                    // header("Location: index.php");
                    // exit();
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
                    $error = "Authentication failed. Please verify your credentials. ($remaining attempts remaining)";
                }
                
                error_log("LOGIN FAILED: $userEmail from IP $ipAddress - {$authResult['error']}");
            }
        }
    }
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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body, html {
            height: 100%;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: #f5f5f7;
            position: relative;
            overflow: hidden;
        }

        /* Animated Background Canvas */
        #techBackground {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            opacity: 0.4;
        }

        /* Floating Code Snippets */
        .code-float {
            position: fixed;
            font-family: 'Courier New', monospace;
            font-size: 11px;
            color: rgba(45, 90, 39, 0.3);
            pointer-events: none;
            white-space: nowrap;
            z-index: 0;
            animation: floatCode linear infinite;
        }

        @keyframes floatCode {
            from {
                transform: translateY(100vh) translateX(var(--start-x));
                opacity: 0;
            }
            10% {
                opacity: 0.6;
            }
            90% {
                opacity: 0.6;
            }
            to {
                transform: translateY(-100px) translateX(var(--end-x));
                opacity: 0;
            }
        }

        /* Neural Network Pulses */
        .neural-pulse {
            position: fixed;
            width: 4px;
            height: 4px;
            background: radial-gradient(circle, rgba(45, 90, 39, 0.8), transparent);
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
            animation: pulse 3s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                opacity: 0.4;
            }
            50% {
                transform: scale(1.5);
                opacity: 0.8;
            }
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
            font-size: 0.68rem;
            line-height: 1.55;
            color: #666;
            opacity: 0.65;
            letter-spacing: 0.2px;
        }

        /* Heading */
        h2 {
            font-size: 1.73rem;
            font-weight: 500;
            margin-bottom: 28px;
            color: #1a1a1a;
            letter-spacing: 0.3px;
        }

        .subtitle {
            font-size: 0.95rem;
            color: #666;
            margin-bottom: 25px;
            line-height: 1.5;
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
            border-bottom: 2px solid #dadada;
            background: transparent;
            font-size: 1rem;
            transition: border-color 180ms ease, transform 150ms ease;
            outline: none;
        }

        input:focus {
            border-bottom-color: #1f2a44;
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
            background: linear-gradient(180deg, #1a1a1a 0%, #0e0e0e 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 2.5px;
            cursor: pointer;
            margin-top: 25px;
            transition: all 180ms ease;
            font-size: 0.9rem;
        }

        button:hover:not(:disabled) {
            background: linear-gradient(180deg, #2a2a2a 0%, #1a1a1a 100%);
            transform: translateY(-1px);
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

        /* Success Animation */
        .success-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.95);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            opacity: 0;
            pointer-events: none;
            transition: opacity 300ms ease;
        }

        .success-overlay.active {
            opacity: 1;
            pointer-events: all;
        }

        .success-checkmark {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #2d5a27 0%, #3d7a37 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 40px rgba(45, 90, 39, 0.3);
            transform: scale(0);
            animation: checkmarkPop 500ms cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }

        .success-checkmark svg {
            width: 40px;
            height: 40px;
            stroke: white;
            stroke-width: 3;
            stroke-linecap: round;
            stroke-linejoin: round;
            fill: none;
            stroke-dasharray: 50;
            stroke-dashoffset: 50;
            animation: checkmarkDraw 400ms ease-out 200ms forwards;
        }

        @keyframes checkmarkPop {
            0% {
                transform: scale(0);
                opacity: 0;
            }
            50% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        @keyframes checkmarkDraw {
            to {
                stroke-dashoffset: 0;
            }
        }

        .login-card.success-exit {
            animation: cardExitSuccess 600ms cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }

        @keyframes cardExitSuccess {
            0% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
            100% {
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
            }
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-card {
                padding: 30px 20px;
            }
            
            h2 {
                font-size: 1.5rem;
            }

            .success-checkmark {
                width: 70px;
                height: 70px;
            }

            .success-checkmark svg {
                width: 35px;
                height: 35px;
            }
        }
    </style>
</head>
<body>
    <!-- Animated Tech Background -->
    <canvas id="techBackground"></canvas>
    
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
            <!-- <p class="subtitle">Enter institutional credentials to continue.</p> -->

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
                    <label for="email">User ID / Email</label>
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
                    <label for="app_password">App Password</label>
                    <input 
                        type="password" 
                        name="app_password" 
                        id="app_password" 
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
                    <?php echo ($blockUntil ? 'Account Locked' : 'Verify & Proceed'); ?>
                </button>
            </form>
            <footer>
                <!-- St. Xavier's College (Autonomous), Kolkata<br>
                Mail Delivery & Tracking System v2.0 -->
                <br>
                <span style="font-size:18px;">Made with ♥︎ by MDTS Students</span>
            </footer>
        </div>
    </div>

    <!-- Success Animation Overlay -->
    <div class="success-overlay" id="successOverlay">
        <div class="success-checkmark">
            <svg viewBox="0 0 52 52">
                <polyline points="14 27 22 35 38 19"/>
            </svg>
        </div>
    </div>

    <script>
        // ============================================================
        // ANIMATED TECH BACKGROUND - AI/ML INSPIRED
        // ============================================================
        
        const canvas = document.getElementById('techBackground');
        const ctx = canvas.getContext('2d');
        
        // Set canvas size
        function resizeCanvas() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        }
        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);
        
        // Particle system for neural network effect
        class Particle {
            constructor() {
                this.reset();
            }
            
            reset() {
                this.x = Math.random() * canvas.width;
                this.y = Math.random() * canvas.height;
                this.vx = (Math.random() - 0.5) * 0.5;
                this.vy = (Math.random() - 0.5) * 0.5;
                this.radius = Math.random() * 2 + 1;
            }
            
            update() {
                this.x += this.vx;
                this.y += this.vy;
                
                // Wrap around edges
                if (this.x < 0) this.x = canvas.width;
                if (this.x > canvas.width) this.x = 0;
                if (this.y < 0) this.y = canvas.height;
                if (this.y > canvas.height) this.y = 0;
            }
            
            draw() {
                ctx.fillStyle = 'rgba(45, 90, 39, 0.4)';
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
                ctx.fill();
            }
        }
        
        // Create particles
        const particles = [];
        const particleCount = Math.min(Math.floor(canvas.width * canvas.height / 15000), 80);
        
        for (let i = 0; i < particleCount; i++) {
            particles.push(new Particle());
        }
        
        // Draw connections between nearby particles (neural network effect)
        function drawConnections() {
            const maxDistance = 150;
            
            for (let i = 0; i < particles.length; i++) {
                for (let j = i + 1; j < particles.length; j++) {
                    const dx = particles[i].x - particles[j].x;
                    const dy = particles[i].y - particles[j].y;
                    const distance = Math.sqrt(dx * dx + dy * dy);
                    
                    if (distance < maxDistance) {
                        const opacity = (1 - distance / maxDistance) * 0.15;
                        ctx.strokeStyle = `rgba(45, 90, 39, ${opacity})`;
                        ctx.lineWidth = 0.5;
                        ctx.beginPath();
                        ctx.moveTo(particles[i].x, particles[i].y);
                        ctx.lineTo(particles[j].x, particles[j].y);
                        ctx.stroke();
                    }
                }
            }
        }
        
        // Draw grid pattern
        function drawGrid() {
            ctx.strokeStyle = 'rgba(229, 231, 235, 0.3)';
            ctx.lineWidth = 0.5;
            const gridSize = 40;
            
            // Vertical lines
            for (let x = 0; x < canvas.width; x += gridSize) {
                ctx.beginPath();
                ctx.moveTo(x, 0);
                ctx.lineTo(x, canvas.height);
                ctx.stroke();
            }
            
            // Horizontal lines
            for (let y = 0; y < canvas.height; y += gridSize) {
                ctx.beginPath();
                ctx.moveTo(0, y);
                ctx.lineTo(canvas.width, y);
                ctx.stroke();
            }
        }
        
        // Animation loop
        function animate() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            // Draw grid
            drawGrid();
            
            // Update and draw particles
            particles.forEach(particle => {
                particle.update();
                particle.draw();
            });
            
            // Draw neural network connections
            drawConnections();
            
            requestAnimationFrame(animate);
        }
        
        animate();
        
        // Create floating code snippets
        const codeSnippets = [
            'def neural_net(x): return activation(W @ x + b)',
            'import tensorflow as tf',
            'model.fit(X_train, y_train, epochs=100)',
            'np.random.seed(42)',
            'from sklearn.ensemble import RandomForestClassifier',
            'optimizer = Adam(learning_rate=0.001)',
            'loss = categorical_crossentropy',
            'y_pred = model.predict(X_test)',
            'accuracy = np.mean(y_pred == y_true)',
            'layer = Dense(128, activation="relu")',
            'dropout = Dropout(0.5)',
            'conv2d = Conv2D(32, (3,3), activation="relu")',
            'batch_size = 32',
            'import pandas as pd',
            'df = pd.read_csv("data.csv")',
            'X_train, X_test = train_test_split(X, y)',
            'scaler = StandardScaler()',
            'X_scaled = scaler.fit_transform(X)',
            'plt.plot(history.history["loss"])',
            'model.save("model.h5")'
        ];
        
        function createFloatingCode() {
            const codeDiv = document.createElement('div');
            codeDiv.className = 'code-float';
            codeDiv.textContent = codeSnippets[Math.floor(Math.random() * codeSnippets.length)];
            
            const startX = Math.random() * 100 - 50;
            const endX = Math.random() * 100 - 50;
            const duration = 15 + Math.random() * 15;
            const delay = Math.random() * 5;
            
            codeDiv.style.left = Math.random() * 100 + '%';
            codeDiv.style.setProperty('--start-x', startX + 'px');
            codeDiv.style.setProperty('--end-x', endX + 'px');
            codeDiv.style.animationDuration = duration + 's';
            codeDiv.style.animationDelay = delay + 's';
            
            document.body.appendChild(codeDiv);
            
            // Remove after animation
            setTimeout(() => {
                codeDiv.remove();
            }, (duration + delay) * 1000);
        }
        
        // Create initial code snippets
        for (let i = 0; i < 8; i++) {
            setTimeout(() => createFloatingCode(), i * 2000);
        }
        
        // Continuously create new snippets
        setInterval(() => {
            if (document.querySelectorAll('.code-float').length < 10) {
                createFloatingCode();
            }
        }, 3000);
        
        // ============================================================
        // LOGIN FORM FUNCTIONALITY
        // ============================================================
        
        function togglePassword() {
            const passInput = document.getElementById("app_password");
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

        // Form validation and success animation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.textContent = 'Authenticating...';
        });

        // Trigger success animation if login was successful
        <?php 
        $showSuccessAnimation = false;
        if (isset($_SESSION['login_success_animation']) && $_SESSION['login_success_animation'] === true) {
            $showSuccessAnimation = true;
            unset($_SESSION['login_success_animation']); // Clear the flag
        }
        ?>
        
        <?php if ($showSuccessAnimation): ?>
        window.addEventListener('DOMContentLoaded', function() {
            showSuccessAnimation();
        });
        
        function showSuccessAnimation() {
            const overlay = document.getElementById('successOverlay');
            const card = document.querySelector('.login-card');
            
            // Show overlay with checkmark
            setTimeout(() => {
                overlay.classList.add('active');
            }, 100);
            
            // Animate card exit
            setTimeout(() => {
                card.classList.add('success-exit');
            }, 200);
            
            // Redirect after animation completes
            setTimeout(() => {
                window.location.href = 'index.php';
            }, 1400);
        }
        <?php endif; ?>
    </script>
</body>
</html>