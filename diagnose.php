<?php
/**
 * diagnose.php - System Diagnostic Tool
 * 
 * This script checks if all components are working correctly
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Bulk Mailer System Diagnostics</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        .test {
            margin: 20px 0;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #ddd;
        }
        .test h3 {
            margin-top: 0;
            color: #555;
        }
        .success {
            background: #e8f5e9;
            border-left-color: #4caf50;
        }
        .error {
            background: #ffebee;
            border-left-color: #f44336;
        }
        .warning {
            background: #fff3e0;
            border-left-color: #ff9800;
        }
        .info {
            background: #e3f2fd;
            border-left-color: #2196f3;
        }
        code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        pre {
            background: #263238;
            color: #aed581;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
        .badge-success { background: #4caf50; color: white; }
        .badge-error { background: #f44336; color: white; }
        .badge-warning { background: #ff9800; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Bulk Mailer System Diagnostics</h1>
        <p>Running comprehensive system checks...</p>
        
        <?php
        
        // Test 1: PHP Version
        echo '<div class="test ' . (version_compare(PHP_VERSION, '7.0.0') >= 0 ? 'success' : 'error') . '">';
        echo '<h3>1. PHP Version';
        echo version_compare(PHP_VERSION, '7.0.0') >= 0 ? '<span class="badge badge-success">✓ OK</span>' : '<span class="badge badge-error">✗ FAIL</span>';
        echo '</h3>';
        echo '<p>Current: <code>' . PHP_VERSION . '</code></p>';
        echo '<p>Required: PHP 7.0 or higher</p>';
        echo '</div>';
        
        // Test 2: Required PHP Extensions
        $required_extensions = ['pdo', 'pdo_mysql', 'mbstring', 'json'];
        $missing_extensions = [];
        
        foreach ($required_extensions as $ext) {
            if (!extension_loaded($ext)) {
                $missing_extensions[] = $ext;
            }
        }
        
        echo '<div class="test ' . (empty($missing_extensions) ? 'success' : 'error') . '">';
        echo '<h3>2. PHP Extensions';
        echo empty($missing_extensions) ? '<span class="badge badge-success">✓ OK</span>' : '<span class="badge badge-error">✗ FAIL</span>';
        echo '</h3>';
        if (empty($missing_extensions)) {
            echo '<p>All required extensions loaded</p>';
        } else {
            echo '<p>Missing extensions: <code>' . implode(', ', $missing_extensions) . '</code></p>';
        }
        echo '<p>Loaded extensions: ' . implode(', ', array_map(function($e) { return "<code>$e</code>"; }, $required_extensions)) . '</p>';
        echo '</div>';
        
        // Test 3: Session
        session_start();
        echo '<div class="test ' . (session_status() === PHP_SESSION_ACTIVE ? 'success' : 'error') . '">';
        echo '<h3>3. Session Status';
        echo session_status() === PHP_SESSION_ACTIVE ? '<span class="badge badge-success">✓ OK</span>' : '<span class="badge badge-error">✗ FAIL</span>';
        echo '</h3>';
        echo '<p>Session ID: <code>' . session_id() . '</code></p>';
        
        if (isset($_SESSION['smtp_user'])) {
            echo '<p>Logged in as: <code>' . htmlspecialchars($_SESSION['smtp_user']) . '</code> <span class="badge badge-success">✓ Authenticated</span></p>';
        } else {
            echo '<p><span class="badge badge-warning">⚠ Not logged in</span></p>';
            echo '<p><small>This is normal if you haven\'t logged in yet. Session will be created after login.</small></p>';
        }
        echo '</div>';
        
        // Test 4: Database Connection
        $db_test = 'info';
        $db_message = '';
        
        if (file_exists('db_config.php')) {
            try {
                require_once 'db_config.php';
                $pdo = getDatabaseConnection();
                
                if ($pdo) {
                    $db_test = 'success';
                    $db_message = 'Database connection successful';
                    
                    // Test query
                    $stmt = $pdo->query("SELECT DATABASE() as db_name");
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($result) {
                        $db_message .= '<br>Database: <code>' . $result['db_name'] . '</code>';
                    }
                } else {
                    $db_test = 'error';
                    $db_message = 'Database connection failed';
                }
            } catch (Exception $e) {
                $db_test = 'error';
                $db_message = 'Database error: ' . htmlspecialchars($e->getMessage());
            }
        } else {
            $db_test = 'error';
            $db_message = 'db_config.php not found';
        }
        
        echo '<div class="test ' . $db_test . '">';
        echo '<h3>4. Database Connection';
        echo $db_test === 'success' ? '<span class="badge badge-success">✓ OK</span>' : '<span class="badge badge-error">✗ FAIL</span>';
        echo '</h3>';
        echo '<p>' . $db_message . '</p>';
        echo '</div>';
        
        // Test 5: Required Files
        $required_files = [
            'db_config.php' => 'Database configuration',
            'process_bulk_mail.php' => 'Email processor',
            'bulk_mail_backend.php' => 'Backend API',
            'bulk_mailer.html' => 'Frontend interface'
        ];
        
        $missing_files = [];
        foreach ($required_files as $file => $desc) {
            if (!file_exists($file)) {
                $missing_files[] = "$file ($desc)";
            }
        }
        
        echo '<div class="test ' . (empty($missing_files) ? 'success' : 'warning') . '">';
        echo '<h3>5. Required Files';
        echo empty($missing_files) ? '<span class="badge badge-success">✓ OK</span>' : '<span class="badge badge-warning">⚠ WARNING</span>';
        echo '</h3>';
        
        if (empty($missing_files)) {
            echo '<p>All required files present</p>';
            echo '<ul>';
            foreach ($required_files as $file => $desc) {
                echo '<li><code>' . $file . '</code> - ' . $desc . '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>Missing files:</p><ul>';
            foreach ($missing_files as $file) {
                echo '<li>' . $file . '</li>';
            }
            echo '</ul>';
        }
        echo '</div>';
        
        // Test 6: Database Tables
        if (isset($pdo) && $pdo) {
            $required_tables = [
                'bulk_mail_queue' => 'Email queue',
                'users' => 'User management',
                'emails' => 'Sent emails',
                'attachments' => 'File attachments'
            ];
            
            $missing_tables = [];
            foreach ($required_tables as $table => $desc) {
                try {
                    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                    if ($stmt->rowCount() === 0) {
                        $missing_tables[] = "$table ($desc)";
                    }
                } catch (Exception $e) {
                    $missing_tables[] = "$table (error checking)";
                }
            }
            
            echo '<div class="test ' . (empty($missing_tables) ? 'success' : 'warning') . '">';
            echo '<h3>6. Database Tables';
            echo empty($missing_tables) ? '<span class="badge badge-success">✓ OK</span>' : '<span class="badge badge-warning">⚠ WARNING</span>';
            echo '</h3>';
            
            if (empty($missing_tables)) {
                echo '<p>All required tables exist</p>';
                
                // Count records in bulk_mail_queue
                try {
                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM bulk_mail_queue");
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    echo '<p>Queue records: <code>' . $result['total'] . '</code></p>';
                } catch (Exception $e) {
                    // Ignore
                }
            } else {
                echo '<p>Missing tables:</p><ul>';
                foreach ($missing_tables as $table) {
                    echo '<li>' . $table . '</li>';
                }
                echo '</ul>';
                echo '<p><small>Tables will be created automatically when needed.</small></p>';
            }
            echo '</div>';
        }
        
        // Test 7: File Permissions
        $upload_dir = 'uploads/attachments/';
        $writable_dirs = [$upload_dir];
        $permission_issues = [];
        
        foreach ($writable_dirs as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            
            if (!is_writable($dir)) {
                $permission_issues[] = $dir;
            }
        }
        
        echo '<div class="test ' . (empty($permission_issues) ? 'success' : 'warning') . '">';
        echo '<h3>7. File Permissions';
        echo empty($permission_issues) ? '<span class="badge badge-success">✓ OK</span>' : '<span class="badge badge-warning">⚠ WARNING</span>';
        echo '</h3>';
        
        if (empty($permission_issues)) {
            echo '<p>All directories writable</p>';
        } else {
            echo '<p>Not writable:</p><ul>';
            foreach ($permission_issues as $dir) {
                echo '<li><code>' . $dir . '</code></li>';
            }
            echo '</ul>';
            echo '<p>Fix with: <code>chmod 755 ' . implode(' ', $permission_issues) . '</code></p>';
        }
        echo '</div>';
        
        // Test 8: PHP Configuration
        $php_settings = [
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit')
        ];
        
        echo '<div class="test info">';
        echo '<h3>8. PHP Configuration <span class="badge badge-success">ℹ INFO</span></h3>';
        echo '<ul>';
        foreach ($php_settings as $setting => $value) {
            echo '<li><strong>' . $setting . ':</strong> <code>' . $value . '</code></li>';
        }
        echo '</ul>';
        echo '</div>';
        
        // Summary
        $all_ok = ($db_test === 'success' && empty($missing_extensions) && empty($missing_files));
        
        echo '<div class="test ' . ($all_ok ? 'success' : 'warning') . '">';
        echo '<h3>📊 Summary</h3>';
        
        if ($all_ok) {
            echo '<p><strong>✓ System is ready to use!</strong></p>';
            echo '<p>You can now:</p>';
            echo '<ul>';
            echo '<li>Open <a href="bulk_mailer.html">bulk_mailer.html</a> to start using the system</li>';
            echo '<li>Run <a href="test_bulk_mailer.html">test_bulk_mailer.html</a> for API tests</li>';
            echo '</ul>';
        } else {
            echo '<p><strong>⚠ Some issues detected</strong></p>';
            echo '<p>Please fix the errors above before using the system.</p>';
        }
        
        echo '</div>';
        
        ?>
        
        <div style="margin-top: 30px; padding: 20px; background: #f5f5f5; border-radius: 6px;">
            <h3>🔧 Quick Fixes</h3>
            
            <h4>If session not working:</h4>
            <pre>// In your login script, set:
$_SESSION['smtp_user'] = 'user@example.com';
$_SESSION['smtp_pass'] = 'password';</pre>
            
            <h4>If database not connecting:</h4>
            <pre>// Check db_config.php settings:
$host = 'localhost';
$dbname = 'your_database';
$username = 'your_username';
$password = 'your_password';</pre>
            
            <h4>If upload directory not writable:</h4>
            <pre>chmod 755 uploads/attachments/
chown www-data:www-data uploads/attachments/</pre>
        </div>
        
        <div style="margin-top: 20px; text-align: center; color: #666;">
            <p>Diagnostic run at: <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
    </div>
</body>
</html>