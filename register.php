<?php
/**
 * register.php - User Registration Page
 * SXC Mail Delivery & Tracking System
 */
require_once __DIR__ . '/security_handler.php';
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
    <title>Register - SXC Mail Delivery & Tracking System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .register-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 450px;
            padding: 40px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 8px;
        }
        
        .header p {
            color: #666;
            font-size: 14px;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background-color: #fff3f3;
            border: 1px solid #ffcdd2;
            color: #c62828;
        }
        
        .alert-success {
            background-color: #f1f8f4;
            border: 1px solid #c8e6c9;
            color: #2e7d32;
        }
        
        .alert ul {
            margin: 8px 0 0 20px;
        }
        
        .alert li {
            margin: 4px 0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #4285f4;
        }
        
        .password-requirements {
            font-size: 12px;
            color: #666;
            margin-top: 6px;
        }
        
        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
            font-size: 14px;
            color: #666;
            cursor: pointer;
            user-select: none;
        }
        
        .checkbox-container input[type="checkbox"] {
            width: auto;
            cursor: pointer;
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            background: #4285f4;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .btn:hover {
            background: #3367d6;
        }
        
        .btn:active {
            background: #2b56c4;
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            font-size: 14px;
            color: #666;
        }
        
        .login-link a {
            color: #4285f4;
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 480px) {
            .register-container {
                padding: 30px 20px;
            }
            
            .header h1 {
                font-size: 22px;
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
                <strong>✓ Registration Successful!</strong><br>
                Your account has been created. You can now <a href="login.php">login</a> with your credentials.
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <strong>⚠ Please correct the following errors:</strong>
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
                    <span>Show Password</span>
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
            
            <button type="submit" class="btn">Create Account</button>
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