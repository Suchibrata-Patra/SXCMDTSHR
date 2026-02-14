<?php
/**
 * debug_login.php - Debug script to check login issues
 * Run this to diagnose why login is not working
 */

require_once 'config.php';
require_once 'db_config.php';

// Test email
$testEmail = 'info.official@holidayseva.com';

echo "<h2>Login Debug Report</h2>";
echo "<pre>";

try {
    $pdo = getDatabaseConnection();
    
    if (!$pdo) {
        echo "❌ DATABASE CONNECTION FAILED!\n";
        exit;
    }
    
    echo "✓ Database connection successful\n\n";
    
    // Get user from database
    $stmt = $pdo->prepare("
        SELECT 
            id,
            user_uuid,
            email,
            full_name,
            password_hash,
            is_active,
            is_admin,
            require_password_change,
            failed_login_count,
            account_locked_until,
            created_at,
            password_updated_at
        FROM users
        WHERE email = :email
    ");
    
    $stmt->execute([':email' => $testEmail]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "❌ USER NOT FOUND: $testEmail\n";
        echo "Please check if the email is correct.\n";
        exit;
    }
    
    echo "✓ User found in database\n\n";
    echo "=== USER DETAILS ===\n";
    echo "ID: {$user['id']}\n";
    echo "UUID: {$user['user_uuid']}\n";
    echo "Email: {$user['email']}\n";
    echo "Full Name: " . ($user['full_name'] ?? 'NULL') . "\n";
    echo "Is Active: " . ($user['is_active'] ? 'YES' : 'NO') . "\n";
    echo "Is Admin: " . ($user['is_admin'] ? 'YES' : 'NO') . "\n";
    echo "Require Password Change: " . ($user['require_password_change'] ? 'YES' : 'NO') . "\n";
    echo "Failed Login Count: {$user['failed_login_count']}\n";
    echo "Account Locked Until: " . ($user['account_locked_until'] ?? 'Not Locked') . "\n";
    echo "Created At: {$user['created_at']}\n";
    echo "Password Updated At: " . ($user['password_updated_at'] ?? 'NULL') . "\n";
    echo "\n";
    
    // Check password hash
    if (empty($user['password_hash'])) {
        echo "❌ NO PASSWORD HASH SET!\n";
        echo "This user needs a password. Use password_utility.php to set one.\n";
        exit;
    }
    
    echo "✓ Password hash exists\n";
    echo "Password Hash: {$user['password_hash']}\n\n";
    
    // Check if account is active
    if (!$user['is_active']) {
        echo "❌ ACCOUNT IS INACTIVE\n";
        echo "Activate the account to allow login.\n\n";
    } else {
        echo "✓ Account is active\n\n";
    }
    
    // Check if account is locked
    if ($user['account_locked_until'] && strtotime($user['account_locked_until']) > time()) {
        $minutesLeft = ceil((strtotime($user['account_locked_until']) - time()) / 60);
        echo "⚠️  ACCOUNT IS LOCKED\n";
        echo "Locked until: {$user['account_locked_until']}\n";
        echo "Minutes remaining: $minutesLeft\n\n";
        
        echo "To unlock the account, run this SQL:\n";
        echo "UPDATE users SET failed_login_count = 0, account_locked_until = NULL WHERE email = '$testEmail';\n\n";
    } else {
        echo "✓ Account is not locked\n\n";
    }
    
    // Test password verification
    echo "=== PASSWORD TEST ===\n";
    echo "Enter the password you used when registering to test:\n";
    echo "(If you know the password, test it manually using password_verify())\n\n";
    
    // Test with common passwords
    $testPasswords = ['password', 'Password123', 'Suchi123', 'Test1234'];
    
    echo "Testing common passwords:\n";
    foreach ($testPasswords as $testPass) {
        $isValid = password_verify($testPass, $user['password_hash']);
        $status = $isValid ? '✓ MATCH' : '✗ No match';
        echo "$status - '$testPass'\n";
        if ($isValid) {
            echo "\n🎉 PASSWORD MATCH FOUND: '$testPass'\n";
            echo "Use this password to login.\n";
        }
    }
    
    echo "\n";
    echo "=== RECOMMENDATIONS ===\n";
    
    if ($user['failed_login_count'] >= 3) {
        echo "1. Reset failed login count:\n";
        echo "   UPDATE users SET failed_login_count = 0, account_locked_until = NULL WHERE email = '$testEmail';\n\n";
    }
    
    if (empty($user['password_hash'])) {
        echo "2. Set a password using password_utility.php:\n";
        echo "   php password_utility.php\n\n";
    }
    
    echo "3. If you forgot the password, reset it:\n";
    echo "   php password_utility.php\n";
    echo "   Choose option 3 (Update user password)\n\n";
    
    echo "4. Create a new test user:\n";
    echo "   php password_utility.php\n";
    echo "   Choose option 2 (Create new user)\n\n";
    
} catch (PDOException $e) {
    echo "❌ DATABASE ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>