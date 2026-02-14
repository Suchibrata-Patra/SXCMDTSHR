<?php
/**
 * login.php - Secure Login Page
 * Handles user authentication with email and password
 */

// Load security handler first
require_once __DIR__ . '/security_handler.php';

// Load database configuration
require_once __DIR__ . '/db_config.php';

// Initialize variables
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Basic validation
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password';
    } else {
        // Sanitize email
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format';
        } else {
            // Check login attempts (brute force protection)
            $attemptCheck = record_login($email, false);
            
            if (isset($attemptCheck['locked']) && $attemptCheck['locked']) {
                $error = $attemptCheck['message'];
            } else {
                // Get database connection
                $pdo = getDatabaseConnection();
                
                if (!$pdo) {
                    $error = 'Database connection failed';
                } else {
                    try {
                        // Check if user exists in database
                        $stmt = $pdo->prepare("SELECT id, email, password_hash, full_name FROM users WHERE email = ? LIMIT 1");
                        $stmt->execute([$email]);
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($user && isset($user['password_hash']) && password_verify($password, $user['password_hash'])) {
                            // Valid credentials - create session
                            record_login($email, true); // Record successful login
                            
                            $_SESSION['authenticated'] = true;
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['user_email'] = $user['email'];
                            $_SESSION['user_name'] = $user['full_name'] ?? $user['email'];
                            $_SESSION['smtp_user'] = $user['email']; // For backward compatibility
                            $_SESSION['smtp_pass'] = $password; // For SMTP operations
                            $_SESSION['login_time'] = time();
                            
                            // Redirect to main page
                            header('Location: index.php');
                            exit;
                            
                        } else {
                            // Invalid credentials
                            $attemptResult = record_login($email, false);
                            
                            if (isset($attemptResult['attempts_left'])) {
                                $error = "Invalid email or password. {$attemptResult['attempts_left']} attempts remaining.";
                            } else {
                                $error = 'Invalid email or password';
                            }
                        }
                        
                    } catch (PDOException $e) {
                        error_log("Login error: " . $e->getMessage());
                        $error = 'An error occurred. Please try again.';
                    }
                }
            }
        }
    }
}

// If already logged in, redirect to index
if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Holiday Seva HR System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .login-header h1 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .login-header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .login-body {
            padding: 40px 30px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
            outline: none;
        }
        
        .form-group input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .error-message {
            background: #fee;
            color: #c33;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #c33;
        }
        
        .success-message {
            background: #efe;
            color: #3c3;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 4px solid #3c3;
        }
        
        .login-button {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .login-button:active {
            transform: translateY(0);
        }
        
        .login-footer {
            text-align: center;
            padding: 20px 30px;
            background: #f8f9fa;
            font-size: 13px;
            color: #666;
        }
        
        .login-footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-footer a:hover {
            text-decoration: underline;
        }
        
        .icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        
        @media (max-width: 480px) {
            .login-container {
                max-width: 100%;
            }
            
            .login-header {
                padding: 30px 20px;
            }
            
            .login-header h1 {
                font-size: 24px;
            }
            
            .login-body {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="icon">🔐</div>
            <h1>Holiday Seva HR</h1>
            <p>Please login to continue</p>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
                <div class="error-message">
                    <strong>⚠️ Error:</strong> <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-message">
                    <strong>✓ Success:</strong> <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="login.php">
                <?php echo csrf_field(); ?>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        placeholder="your.email@example.com"
                        value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                        required
                        autocomplete="email"
                        autofocus
                    >
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Enter your password"
                        required
                        autocomplete="current-password"
                    >
                </div>
                
                <button type="submit" class="login-button">
                    Sign In
                </button>
            </form>
        </div>
        
        <div class="login-footer">
            <p>&copy; <?php echo date('Y'); ?> Holiday Seva. All rights reserved.</p>
        </div>
    </div>
</body>
</html>