<?php
/**
 * login.php - SECURE VERSION with Security Handler
 * This is an example of how to integrate the security handler
 */

// STEP 1: Include security handler FIRST
require_once __DIR__ . '/security_handler.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db_config.php';

// Login is a public page, so no authentication required
// But we still get CSRF protection and other security features

$error = '';
$success = '';

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Input sanitization (automatic, but we can be explicit)
    $email = secure_input($_POST['email'] ?? '', 'email');
    $password = $_POST['password'] ?? '';
    
    // Validate inputs
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password';
    } else {
        // Check brute force protection BEFORE attempting login
        $loginAttempt = record_login($email, false);
        
        if ($loginAttempt['locked']) {
            $error = $loginAttempt['message'];
        } else {
            try {
                $pdo = getDatabaseConnection();
                
                if (!$pdo) {
                    $error = 'Database connection failed';
                } else {
                    // Get user from database
                    $stmt = $pdo->prepare("
                        SELECT 
                            id,
                            email,
                            password_hash,
                            is_active,
                            account_locked_until
                        FROM users
                        WHERE email = :email
                        LIMIT 1
                    ");
                    
                    $stmt->execute([':email' => $email]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$user) {
                        // Don't reveal if user exists or not
                        $loginAttempt = record_login($email, false);
                        $error = $loginAttempt['message'] ?? 'Invalid credentials';
                    } elseif (!$user['is_active']) {
                        $error = 'Account is inactive. Please contact administrator.';
                    } elseif ($user['account_locked_until'] && strtotime($user['account_locked_until']) > time()) {
                        $minutesLeft = ceil((strtotime($user['account_locked_until']) - time()) / 60);
                        $error = "Account locked. Try again in $minutesLeft minutes.";
                    } elseif (password_verify($password, $user['password_hash'])) {
                        // SUCCESS! Login successful
                        record_login($email, true);
                        
                        // Set session variables
                        $_SESSION['authenticated'] = true;
                        $_SESSION['smtp_user'] = $email;
                        $_SESSION['smtp_pass'] = $password; // For IMAP/SMTP
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['login_time'] = time();
                        
                        // Update last login in database
                        $stmt = $pdo->prepare("
                            UPDATE users 
                            SET last_login = NOW(),
                                failed_login_count = 0,
                                account_locked_until = NULL
                            WHERE id = :id
                        ");
                        $stmt->execute([':id' => $user['id']]);
                        
                        // Redirect to dashboard
                        header('Location: index.php');
                        exit;
                    } else {
                        // Wrong password
                        // Update failed login count in database
                        $stmt = $pdo->prepare("
                            UPDATE users 
                            SET failed_login_count = failed_login_count + 1,
                                account_locked_until = CASE 
                                    WHEN failed_login_count + 1 >= 5 
                                    THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                                    ELSE NULL
                                END
                            WHERE id = :id
                        ");
                        $stmt->execute([':id' => $user['id']]);
                        
                        $loginAttempt = record_login($email, false);
                        $error = $loginAttempt['message'] ?? 'Invalid credentials';
                    }
                }
            } catch (PDOException $e) {
                error_log("Login error: " . $e->getMessage());
                $error = 'An error occurred. Please try again later.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once 'header.php'; ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
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
            max-width: 400px;
            width: 100%;
        }

        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 30px;
            text-align: center;
            color: white;
        }

        .login-header img {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            margin-bottom: 20px;
            background: white;
            padding: 10px;
        }

        .login-header h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .login-header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .login-body {
            padding: 40px 30px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .security-badge {
            background: #f3f4f6;
            border-radius: 8px;
            padding: 16px;
            margin-top: 20px;
            text-align: center;
        }

        .security-badge-title {
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .security-features {
            display: flex;
            gap: 8px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .security-feature {
            background: white;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            color: #6b7280;
            border: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcT_Zqk8uEqyydQiO-nuKyebrvbWubamLz_E3Q&s" alt="SXC Logo">
            <h1>SXC MDTS</h1>
            <p>Mail Delivery & Tracking System</p>
        </div>

        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    🚫 <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    ✅ <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <!-- CSRF Protection Token -->
                <?php echo csrf_field(); ?>

                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-input"
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                        placeholder="Enter your email"
                        required
                        autofocus
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-input"
                        placeholder="Enter your password"
                        required
                    >
                </div>

                <button type="submit" class="btn-login">
                    Sign In Securely
                </button>
            </form>

            <div class="security-badge">
                <div class="security-badge-title">
                    🔒 Protected by Advanced Security
                </div>
                <div class="security-features">
                    <span class="security-feature">CSRF Protection</span>
                    <span class="security-feature">Brute Force Shield</span>
                    <span class="security-feature">Session Security</span>
                    <span class="security-feature">XSS Prevention</span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>