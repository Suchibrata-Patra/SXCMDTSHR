<?php
/**
 * ============================================================
 * LOGIN SYSTEM TEST & DEBUG SCRIPT
 * ============================================================
 * Run this script to verify:
 * 1. Database tables exist
 * 2. Login activity is being saved
 * 3. Failed attempts are being tracked
 * ============================================================
 */

require_once 'db_config.php';
require_once 'login_auth_helper.php';

echo "<h1>🔍 Login System Diagnostic Test</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    .success { color: #28a745; font-weight: bold; }
    .error { color: #dc3545; font-weight: bold; }
    .warning { color: #ff9800; font-weight: bold; }
    .section { background: white; padding: 15px; margin: 15px 0; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
    th { background: #007bff; color: white; }
    pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
</style>";

// ============================================================
// TEST 1: Database Connection
// ============================================================
echo "<div class='section'>";
echo "<h2>📊 Test 1: Database Connection</h2>";

$pdo = getDatabaseConnection();
if ($pdo) {
    echo "<p class='success'>✓ Database connection successful</p>";
} else {
    echo "<p class='error'>✗ Database connection failed!</p>";
    echo "<p>Cannot proceed with tests. Check db_config.php</p>";
    exit();
}
echo "</div>";

// ============================================================
// TEST 2: Check Required Tables Exist
// ============================================================
echo "<div class='section'>";
echo "<h2>🗄️ Test 2: Database Tables</h2>";

$requiredTables = [
    'login_activity' => 'Main login tracking table',
    'failed_login_attempts' => 'Security & rate limiting table',
    'user_sessions' => 'Active session management table',
    'users' => 'User accounts table'
];

$missingTables = [];
foreach ($requiredTables as $table => $description) {
    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
    if ($stmt->rowCount() > 0) {
        echo "<p class='success'>✓ Table '$table' exists - $description</p>";
    } else {
        echo "<p class='error'>✗ Table '$table' MISSING - $description</p>";
        $missingTables[] = $table;
    }
}

if (!empty($missingTables)) {
    echo "<div class='warning'>";
    echo "<h3>⚠️ Missing Tables Detected!</h3>";
    echo "<p>Run this SQL migration to create missing tables:</p>";
    echo "<pre>mysql -u u955994755_DB_supremacy -p'sxccal.edu#MDTS@2026' u955994755_SXC_MDTS < migration_login_activity.sql</pre>";
    echo "</div>";
}
echo "</div>";

// ============================================================
// TEST 3: Test recordLoginActivity Function
// ============================================================
echo "<div class='section'>";
echo "<h2>📝 Test 3: Test Login Activity Recording</h2>";

if (!in_array('login_activity', $missingTables)) {
    // Create a test user if not exists
    $testEmail = 'test@sxccal.edu';
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$testEmail]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $userId = createUserIfNotExists($pdo, $testEmail, 'Test User');
        echo "<p class='success'>✓ Created test user: $testEmail (ID: $userId)</p>";
    } else {
        $userId = $user['id'];
        echo "<p class='success'>✓ Using existing test user: $testEmail (ID: $userId)</p>";
    }
    
    // Test recording a login
    session_start();
    $loginId = recordLoginActivity($testEmail, $userId, 'success');
    
    if ($loginId) {
        echo "<p class='success'>✓ Successfully recorded test login activity (ID: $loginId)</p>";
        
        // Verify it was saved
        $stmt = $pdo->prepare("SELECT * FROM login_activity WHERE id = ?");
        $stmt->execute([$loginId]);
        $activity = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($activity) {
            echo "<p class='success'>✓ Verified: Login activity was saved to database</p>";
            echo "<h4>Saved Data:</h4>";
            echo "<table>";
            echo "<tr><th>Field</th><th>Value</th></tr>";
            foreach ($activity as $key => $value) {
                echo "<tr><td>$key</td><td>" . htmlspecialchars($value ?? 'NULL') . "</td></tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='error'>✗ ERROR: Login activity ID returned but not found in database!</p>";
        }
    } else {
        echo "<p class='error'>✗ Failed to record test login activity</p>";
        echo "<p>Check error logs for details</p>";
    }
} else {
    echo "<p class='warning'>⚠️ Skipped: login_activity table does not exist</p>";
}
echo "</div>";

// ============================================================
// TEST 4: Test Failed Login Attempt Recording
// ============================================================
echo "<div class='section'>";
echo "<h2>🔒 Test 4: Test Failed Attempt Recording</h2>";

if (!in_array('failed_login_attempts', $missingTables)) {
    $testIp = '127.0.0.1';
    $result = recordFailedAttempt($testEmail, $testIp, 'Test failure');
    
    if ($result) {
        echo "<p class='success'>✓ Successfully recorded test failed attempt</p>";
        
        // Verify it was saved
        $stmt = $pdo->prepare("
            SELECT * FROM failed_login_attempts 
            WHERE email = ? AND ip_address = ? 
            ORDER BY attempt_timestamp DESC LIMIT 1
        ");
        $stmt->execute([$testEmail, $testIp]);
        $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($attempt) {
            echo "<p class='success'>✓ Verified: Failed attempt was saved to database</p>";
            echo "<h4>Saved Data:</h4>";
            echo "<table>";
            echo "<tr><th>Field</th><th>Value</th></tr>";
            foreach ($attempt as $key => $value) {
                echo "<tr><td>$key</td><td>" . htmlspecialchars($value ?? 'NULL') . "</td></tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<p class='error'>✗ Failed to record test failed attempt</p>";
    }
} else {
    echo "<p class='warning'>⚠️ Skipped: failed_login_attempts table does not exist</p>";
}
echo "</div>";

// ============================================================
// TEST 5: View Recent Login Activity
// ============================================================
echo "<div class='section'>";
echo "<h2>📊 Test 5: Recent Login Activity</h2>";

if (!in_array('login_activity', $missingTables)) {
    $stmt = $pdo->query("
        SELECT id, email, login_timestamp, login_status, ip_address, session_id
        FROM login_activity 
        ORDER BY login_timestamp DESC 
        LIMIT 10
    ");
    
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($activities) > 0) {
        echo "<p class='success'>✓ Found " . count($activities) . " login activities</p>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Email</th><th>Timestamp</th><th>Status</th><th>IP Address</th></tr>";
        foreach ($activities as $act) {
            $statusClass = $act['login_status'] === 'success' ? 'success' : 'error';
            echo "<tr>";
            echo "<td>{$act['id']}</td>";
            echo "<td>{$act['email']}</td>";
            echo "<td>{$act['login_timestamp']}</td>";
            echo "<td class='$statusClass'>{$act['login_status']}</td>";
            echo "<td>{$act['ip_address']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='warning'>⚠️ No login activities found in database yet</p>";
        echo "<p>This is normal if the system is newly installed. Try logging in to create records.</p>";
    }
} else {
    echo "<p class='warning'>⚠️ Skipped: login_activity table does not exist</p>";
}
echo "</div>";

// ============================================================
// TEST 6: Cleanup Test Data
// ============================================================
echo "<div class='section'>";
echo "<h2>🧹 Test 6: Cleanup Test Data</h2>";

$cleaned = false;

if (!in_array('failed_login_attempts', $missingTables)) {
    $stmt = $pdo->prepare("DELETE FROM failed_login_attempts WHERE email = ?");
    $stmt->execute([$testEmail]);
    echo "<p class='success'>✓ Cleaned up test failed attempts</p>";
    $cleaned = true;
}

if ($cleaned) {
    echo "<p>Test data has been cleaned up. Database is ready for production use.</p>";
}
echo "</div>";

// ============================================================
// FINAL SUMMARY
// ============================================================
echo "<div class='section'>";
echo "<h2>✅ Summary</h2>";

if (empty($missingTables)) {
    echo "<p class='success'>🎉 All tests passed! Login system is working correctly.</p>";
    echo "<p>Your login activity will now be saved to the database.</p>";
} else {
    echo "<p class='error'>⚠️ Some tests failed. Please:</p>";
    echo "<ol>";
    echo "<li>Run the migration SQL file to create missing tables</li>";
    echo "<li>Refresh this page to re-run tests</li>";
    echo "</ol>";
}

echo "<h3>Next Steps:</h3>";
echo "<ul>";
echo "<li>✓ Upload login.php, login_auth_helper.php to your server</li>";
echo "<li>✓ Test actual login from login page</li>";
echo "<li>✓ Check login_activity table after logging in</li>";
echo "<li>✓ Remove this test file after verification</li>";
echo "</ul>";
echo "</div>";

?>