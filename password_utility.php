<?php
/**
 * ============================================================
 * PASSWORD MANAGEMENT UTILITY
 * ============================================================
 * Use this script to:
 * - Generate password hashes
 * - Create/update user accounts
 * - Reset user passwords
 * ============================================================
 */

require_once 'config.php';
require_once 'db_config.php';

// Command-line usage check
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

/**
 * Generate a password hash
 */
function generatePasswordHash($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Create a new user with password
 */
function createUser($email, $password, $fullName = null, $isAdmin = false) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            echo "❌ Database connection failed!\n";
            return false;
        }
        
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            echo "❌ User with email $email already exists!\n";
            return false;
        }
        
        // Generate UUID
        $uuid = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        
        // Hash password
        $passwordHash = generatePasswordHash($password);
        
        // Create user
        $stmt = $pdo->prepare("
            INSERT INTO users 
            (user_uuid, email, full_name, password_hash, password_updated_at, is_active, is_admin, created_at, updated_at)
            VALUES (:uuid, :email, :name, :hash, NOW(), 1, :admin, NOW(), NOW())
        ");
        
        $success = $stmt->execute([
            ':uuid' => $uuid,
            ':email' => $email,
            ':name' => $fullName,
            ':hash' => $passwordHash,
            ':admin' => $isAdmin ? 1 : 0
        ]);
        
        if ($success) {
            $userId = $pdo->lastInsertId();
            echo "✅ User created successfully!\n";
            echo "   ID: $userId\n";
            echo "   Email: $email\n";
            echo "   Full Name: " . ($fullName ?? 'N/A') . "\n";
            echo "   Admin: " . ($isAdmin ? 'Yes' : 'No') . "\n";
            return true;
        } else {
            echo "❌ Failed to create user!\n";
            return false;
        }
        
    } catch (PDOException $e) {
        echo "❌ Database error: " . $e->getMessage() . "\n";
        return false;
    }
}

/**
 * Update user password
 */
function updateUserPassword($email, $newPassword) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            echo "❌ Database connection failed!\n";
            return false;
        }
        
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo "❌ User with email $email not found!\n";
            return false;
        }
        
        // Hash new password
        $passwordHash = generatePasswordHash($newPassword);
        
        // Update password
        $stmt = $pdo->prepare("
            UPDATE users 
            SET password_hash = :hash,
                password_updated_at = NOW(),
                failed_login_count = 0,
                account_locked_until = NULL,
                require_password_change = 0,
                updated_at = NOW()
            WHERE email = :email
        ");
        
        $success = $stmt->execute([
            ':hash' => $passwordHash,
            ':email' => $email
        ]);
        
        if ($success) {
            echo "✅ Password updated successfully for $email!\n";
            echo "   Failed login attempts reset.\n";
            echo "   Account unlocked.\n";
            return true;
        } else {
            echo "❌ Failed to update password!\n";
            return false;
        }
        
    } catch (PDOException $e) {
        echo "❌ Database error: " . $e->getMessage() . "\n";
        return false;
    }
}

/**
 * List all users
 */
function listUsers() {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            echo "❌ Database connection failed!\n";
            return false;
        }
        
        $stmt = $pdo->query("
            SELECT 
                id, 
                email, 
                full_name, 
                is_active, 
                is_admin,
                password_hash IS NOT NULL as has_password,
                failed_login_count,
                account_locked_until,
                created_at
            FROM users
            ORDER BY id
        ");
        
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($users)) {
            echo "No users found.\n";
            return true;
        }
        
        echo "\n" . str_repeat("=", 100) . "\n";
        echo "USER LIST\n";
        echo str_repeat("=", 100) . "\n\n";
        
        foreach ($users as $user) {
            echo "ID: {$user['id']}\n";
            echo "Email: {$user['email']}\n";
            echo "Name: " . ($user['full_name'] ?? 'N/A') . "\n";
            echo "Active: " . ($user['is_active'] ? 'Yes' : 'No') . "\n";
            echo "Admin: " . ($user['is_admin'] ? 'Yes' : 'No') . "\n";
            echo "Has Password: " . ($user['has_password'] ? 'Yes' : 'No') . "\n";
            echo "Failed Attempts: {$user['failed_login_count']}\n";
            echo "Locked Until: " . ($user['account_locked_until'] ?? 'Not locked') . "\n";
            echo "Created: {$user['created_at']}\n";
            echo str_repeat("-", 100) . "\n";
        }
        
        return true;
        
    } catch (PDOException $e) {
        echo "❌ Database error: " . $e->getMessage() . "\n";
        return false;
    }
}

/**
 * Show menu
 */
function showMenu() {
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "PASSWORD MANAGEMENT UTILITY\n";
    echo str_repeat("=", 60) . "\n";
    echo "1. Generate password hash\n";
    echo "2. Create new user\n";
    echo "3. Update user password\n";
    echo "4. List all users\n";
    echo "5. Exit\n";
    echo str_repeat("=", 60) . "\n";
    echo "Choose an option (1-5): ";
}

/**
 * Read input from stdin
 */
function readInput($prompt) {
    echo $prompt;
    return trim(fgets(STDIN));
}

/**
 * Read password (hidden input)
 */
function readPassword($prompt) {
    echo $prompt;
    
    // Try to disable echo
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        system('stty -echo');
    }
    
    $password = trim(fgets(STDIN));
    
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        system('stty echo');
    }
    
    echo "\n";
    return $password;
}

// ============================================================
// MAIN PROGRAM
// ============================================================

echo "\n";
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║         PASSWORD MANAGEMENT UTILITY                      ║\n";
echo "║         SXC Mail Delivery & Tracking System              ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n";

// Interactive mode
while (true) {
    showMenu();
    $choice = trim(fgets(STDIN));
    
    switch ($choice) {
        case '1':
            // Generate password hash
            echo "\n--- Generate Password Hash ---\n";
            $password = readPassword("Enter password: ");
            
            if (empty($password)) {
                echo "❌ Password cannot be empty!\n";
                break;
            }
            
            $hash = generatePasswordHash($password);
            echo "✅ Password hash generated:\n";
            echo "$hash\n";
            echo "\nYou can use this hash in SQL:\n";
            echo "UPDATE users SET password_hash = '$hash' WHERE email = 'user@example.com';\n";
            break;
            
        case '2':
            // Create new user
            echo "\n--- Create New User ---\n";
            $email = readInput("Enter email: ");
            $fullName = readInput("Enter full name (optional): ");
            $isAdmin = strtolower(readInput("Is admin? (y/n): ")) === 'y';
            $password = readPassword("Enter password: ");
            $confirmPassword = readPassword("Confirm password: ");
            
            if ($password !== $confirmPassword) {
                echo "❌ Passwords do not match!\n";
                break;
            }
            
            if (empty($email) || empty($password)) {
                echo "❌ Email and password are required!\n";
                break;
            }
            
            createUser($email, $password, $fullName ?: null, $isAdmin);
            break;
            
        case '3':
            // Update user password
            echo "\n--- Update User Password ---\n";
            $email = readInput("Enter user email: ");
            $password = readPassword("Enter new password: ");
            $confirmPassword = readPassword("Confirm new password: ");
            
            if ($password !== $confirmPassword) {
                echo "❌ Passwords do not match!\n";
                break;
            }
            
            if (empty($email) || empty($password)) {
                echo "❌ Email and password are required!\n";
                break;
            }
            
            updateUserPassword($email, $password);
            break;
            
        case '4':
            // List users
            echo "\n--- List All Users ---\n";
            listUsers();
            break;
            
        case '5':
            // Exit
            echo "\nGoodbye!\n\n";
            exit(0);
            
        default:
            echo "❌ Invalid option. Please choose 1-5.\n";
    }
    
    echo "\nPress Enter to continue...";
    fgets(STDIN);
}

?>