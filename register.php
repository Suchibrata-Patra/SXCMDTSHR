<?php
/**
 * register.php - User Registration Page
 * SXC Mail Delivery & Tracking System
 */
session_start();
require_once 'config.php';
require_once 'db_config.php';

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Initialize variables
$errors = [];
$success = false;
$formData = [
    'email' => '',
    'full_name' => ''
];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize input
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $fullName = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Store form data for repopulation
    $formData['email'] = $email;
    $formData['full_name'] = $fullName;
    
    // Validation
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    
    if (empty($fullName)) {
        $errors[] = "Full name is required.";
    } elseif (strlen($fullName) < 2) {
        $errors[] = "Full name must be at least 2 characters long.";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter.";
    } elseif (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter.";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number.";
    }
    
    if (empty($confirmPassword)) {
        $errors[] = "Please confirm your password.";
    } elseif ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match.";
    }
    
    // If validation passes, attempt registration
    if (empty($errors)) {
        try {
            $pdo = getDatabaseConnection();
            
            if (!$pdo) {
                $errors[] = "Database connection failed. Please try again later.";
            } else {
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
                $stmt->execute([':email' => $email]);
                
                if ($stmt->fetch()) {
                    $errors[] = "An account with this email address already exists.";
                } else {
                    // Generate UUID for the user
                    $uuid = sprintf(
                        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                        mt_rand(0, 0xffff),
                        mt_rand(0, 0x0fff) | 0x4000,
                        mt_rand(0, 0x3fff) | 0x8000,
                        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                    );
                    
                    // Hash the password
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Debug logging
                    error_log("=== REGISTRATION DEBUG ===");
                    error_log("Email: $email");
                    error_log("Password Length: " . strlen($password));
                    error_log("Password Hash: $passwordHash");
                    error_log("Hash Algorithm: " . PASSWORD_DEFAULT);
                    
                    // Insert new user
                    $stmt = $pdo->prepare("
                        INSERT INTO users 
                        (user_uuid, email, full_name, password_hash, password_updated_at, is_active, is_admin, created_at, updated_at)
                        VALUES (:uuid, :email, :full_name, :password_hash, NOW(), 1, 0, NOW(), NOW())
                    ");
                    
                    $result = $stmt->execute([
                        ':uuid' => $uuid,
                        ':email' => $email,
                        ':full_name' => $fullName,
                        ':password_hash' => $passwordHash
                    ]);
                    
                    if ($result) {
                        $userId = $pdo->lastInsertId();
                        error_log("✓ User registered successfully with ID: $userId");
                        error_log("=========================");
                        
                        $success = true;
                        // Clear form data on success
                        $formData = ['email' => '', 'full_name' => ''];
                    } else {
                        error_log("✗ Failed to insert user into database");
                        $errors[] = "Registration failed. Please try again.";
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            $errors[] = "An error occurred during registration. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - SXC Mail Delivery & Tracking System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <style>
        /* ══════════════════════════════════════════════════════════
           DRIVE UI DESIGN SYSTEM
           ══════════════════════════════════════════════════════════ */
        
        :root {
            /* Foundation Colors */
            --ink:       #1a1a2e;
            --ink-2:     #2d2d44;
            --ink-3:     #6b6b8a;
            --ink-4:     #a8a8c0;
            --bg:        #f0f0f7;
            --surface:   #ffffff;
            --surface-2: #f7f7fc;
            --border:    rgba(100,100,160,0.12);
            --border-2:  rgba(100,100,160,0.22);
            
            /* Accent Colors */
            --blue:      #5781a9;
            --blue-2:    #c6d3ea;
            --blue-glow: rgba(79,70,229,0.15);
            --red:       #ef4444;
            --green:     #10b981;
            --amber:     #f59e0b;
            
            /* System */
            --r:         10px;
            --r-lg:      16px;
            --shadow:    0 1px 3px rgba(79,70,229,0.08), 0 4px 16px rgba(79,70,229,0.06);
            --shadow-lg: 0 8px 32px rgba(79,70,229,0.14), 0 2px 8px rgba(0,0,0,0.06);
            --ease:      cubic-bezier(.4,0,.2,1);
            --ease-spring: cubic-bezier(.34,1.56,.64,1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--ink);
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            line-height: 1.6;
        }
        
        .register-container {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 480px;
            padding: 48px;
            transition: transform .18s var(--ease), box-shadow .18s var(--ease);
        }
        
        .register-container:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 48px rgba(79,70,229,0.18), 0 4px 12px rgba(0,0,0,0.08);
        }
        
        .header {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .header h1 {
            color: var(--ink);
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        
        .header p {
            color: var(--ink-3);
            font-size: 14px;
            font-weight: 400;
        }
        
        .alert {
            padding: 14px 16px;
            border-radius: var(--r);
            margin-bottom: 24px;
            font-size: 13px;
            line-height: 1.5;
            animation: slideIn .3s var(--ease-spring);
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: var(--ink-2);
        }
        
        .alert-error strong {
            color: var(--red);
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: var(--ink-2);
        }
        
        .alert-success strong {
            color: var(--green);
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
        }
        
        .alert-success a {
            color: var(--blue);
            text-decoration: none;
            font-weight: 600;
            transition: color .14s var(--ease);
        }
        
        .alert-success a:hover {
            color: var(--ink);
        }
        
        .alert ul {
            margin: 10px 0 0 0;
            padding-left: 20px;
        }
        
        .alert li {
            margin: 6px 0;
            color: var(--ink-2);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--ink-2);
            font-weight: 600;
            font-size: 13px;
            letter-spacing: 0.2px;
        }
        
        .form-group input {
            width: 100%;
            height: 42px;
            padding: 0 14px;
            background: var(--surface);
            border: 1px solid var(--border-2);
            border-radius: var(--r);
            font-family: inherit;
            font-size: 14px;
            color: var(--ink);
            transition: all .18s var(--ease);
        }
        
        .form-group input:hover {
            border-color: var(--border-2);
            background: var(--surface-2);
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--blue);
            background: var(--surface);
            box-shadow: 0 0 0 3px var(--blue-glow);
        }
        
        .form-group input::placeholder {
            color: var(--ink-4);
        }
        
        .password-requirements {
            font-size: 12px;
            color: var(--ink-3);
            margin-top: 8px;
            padding: 8px 12px;
            background: var(--surface-2);
            border-radius: var(--r);
            border-left: 2px solid var(--blue-2);
        }
        
        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
            font-size: 13px;
            color: var(--ink-3);
            cursor: pointer;
            user-select: none;
            transition: color .14s var(--ease);
        }
        
        .checkbox-container:hover {
            color: var(--ink-2);
        }
        
        .checkbox-container input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: var(--blue);
        }
        
        .btn {
            width: 100%;
            height: 44px;
            padding: 0 20px;
            background: var(--blue);
            color: white;
            border: none;
            border-radius: var(--r);
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all .18s var(--ease);
            box-shadow: 0 2px 8px rgba(87, 129, 169, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn:hover {
            background: #4a6e93;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(87, 129, 169, 0.3);
        }
        
        .btn:active {
            transform: translateY(0);
            box-shadow: 0 1px 4px rgba(87, 129, 169, 0.2);
        }
        
        .btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px var(--blue-glow), 0 2px 8px rgba(87, 129, 169, 0.2);
        }
        
        .login-link {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid var(--border);
            font-size: 13px;
            color: var(--ink-3);
        }
        
        .login-link a {
            color: var(--blue);
            text-decoration: none;
            font-weight: 600;
            transition: color .14s var(--ease);
        }
        
        .login-link a:hover {
            color: var(--ink);
        }
        
        .material-icons-round {
            font-size: 18px;
            vertical-align: middle;
        }
        
        @media (max-width: 520px) {
            .register-container {
                padding: 32px 24px;
            }
            
            .header h1 {
                font-size: 24px;
            }
        }
        
        @media (max-width: 380px) {
            .register-container {
                padding: 24px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="header">
            <h1>Create Account</h1>
            <p>SXC Mail Delivery & Tracking System</p>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <strong>
                    <span class="material-icons-round" style="font-size: 16px;">check_circle</span>
                    Registration Successful!
                </strong><br>
                Your account has been created. You can now <a href="login.php">sign in</a> with your credentials.
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <strong>
                    <span class="material-icons-round" style="font-size: 16px;">error</span>
                    Please correct the following errors:
                </strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="register.php" id="registerForm">
            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input 
                    type="text" 
                    id="full_name" 
                    name="full_name" 
                    placeholder="Enter your full name"
                    value="<?php echo htmlspecialchars($formData['full_name']); ?>"
                    required
                    autocomplete="name"
                >
            </div>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    placeholder="Enter your email"
                    value="<?php echo htmlspecialchars($formData['email']); ?>"
                    required
                    autocomplete="email"
                >
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    placeholder="Create a password"
                    required
                    autocomplete="new-password"
                >
                <label class="checkbox-container">
                    <input type="checkbox" id="showPassword" onclick="togglePassword()">
                    <span>Show password</span>
                </label>
                <div class="password-requirements">
                    Must be 8+ characters with uppercase, lowercase, and number
                </div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input 
                    type="password" 
                    id="confirm_password" 
                    name="confirm_password" 
                    placeholder="Re-enter your password"
                    required
                    autocomplete="new-password"
                >
            </div>
            
            <button type="submit" class="btn">
                <span class="material-icons-round">person_add</span>
                Create Account
            </button>
        </form>
        
        <div class="login-link">
            Already have an account? <a href="login.php">Sign in</a>
        </div>
    </div>
    
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const checkbox = document.getElementById('showPassword');
            
            const type = checkbox.checked ? 'text' : 'password';
            passwordInput.type = type;
            confirmPasswordInput.type = type;
        }
        
        // Log password on form submit for debugging
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            console.log('Password being submitted:', password);
            console.log('Password length:', password.length);
            console.log('Has uppercase:', /[A-Z]/.test(password));
            console.log('Has lowercase:', /[a-z]/.test(password));
            console.log('Has number:', /[0-9]/.test(password));
        });
    </script>
</body>
</html>