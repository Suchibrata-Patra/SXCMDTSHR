<?php
/**
 * test_env.php - Test if .env file is loading correctly
 */

require_once 'config.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Environment Variables Test</title>
    <style>
        body {
            font-family: monospace;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 {
            color: #333;
            border-bottom: 2px solid #4285f4;
            padding-bottom: 10px;
        }
        .status {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #4285f4;
            color: white;
        }
        tr:hover {
            background: #f5f5f5;
        }
        .value-set {
            color: #28a745;
            font-weight: bold;
        }
        .value-missing {
            color: #dc3545;
            font-weight: bold;
        }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>🔍 Environment Variables Test</h2>
        
        <?php
        $envFilePath = __DIR__ . '/.env';
        $envExists = file_exists($envFilePath);
        ?>
        
        <div class="status <?php echo $envExists ? 'success' : 'error'; ?>">
            <?php if ($envExists): ?>
                ✓ .env file found at: <?php echo $envFilePath; ?>
            <?php else: ?>
                ✗ .env file NOT FOUND at: <?php echo $envFilePath; ?>
            <?php endif; ?>
        </div>
        
        <?php if ($envExists): ?>
            <h3>.env File Contents:</h3>
            <pre><?php echo htmlspecialchars(file_get_contents($envFilePath)); ?></pre>
        <?php endif; ?>
        
        <h3>Environment Variables Status:</h3>
        <table>
            <thead>
                <tr>
                    <th>Variable</th>
                    <th>Status</th>
                    <th>Value</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $requiredVars = [
                    // Database
                    'DB_HOST' => false,
                    'DB_NAME' => false,
                    'DB_USERNAME' => true, // true means hide value
                    'DB_PASSWORD' => true,
                    // SMTP
                    'SMTP_HOST' => false,
                    'SMTP_PORT' => false,
                    'SMTP_ENCRYPTION' => false,
                    'SMTP_USERNAME' => true,
                    'SMTP_PASSWORD' => true,
                    'FROM_NAME' => false,
                    'FROM_EMAIL' => false,
                    // IMAP
                    'IMAP_HOST' => false,
                    'IMAP_PORT' => false,
                    'IMAP_ENCRYPTION' => false,
                    'IMAP_VALIDATE_CERT' => false,
                ];
                
                foreach ($requiredVars as $var => $hideValue) {
                    $value = env($var);
                    $isSet = !empty($value);
                    $displayValue = $isSet ? ($hideValue ? '***SET***' : htmlspecialchars($value)) : 'NOT SET';
                    $statusClass = $isSet ? 'value-set' : 'value-missing';
                    $statusIcon = $isSet ? '✓' : '✗';
                    
                    echo "<tr>";
                    echo "<td><code>$var</code></td>";
                    echo "<td class='$statusClass'>$statusIcon " . ($isSet ? 'SET' : 'MISSING') . "</td>";
                    echo "<td>$displayValue</td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
        
        <h3>Test Database Configuration:</h3>
        <?php
        $dbHost = env('DB_HOST');
        $dbName = env('DB_NAME');
        $dbUser = env('DB_USERNAME');
        $dbPass = env('DB_PASSWORD');
        
        $dbConfigured = !empty($dbHost) && !empty($dbName) && !empty($dbUser) && !empty($dbPass);
        ?>
        
        <div class="status <?php echo $dbConfigured ? 'success' : 'error'; ?>">
            <?php if ($dbConfigured): ?>
                ✓ All database credentials are configured correctly!
            <?php else: ?>
                ✗ Some database credentials are missing. Please check your .env file.
            <?php endif; ?>
        </div>
        
        <?php if ($dbConfigured): ?>
            <h3>Database Configuration Preview:</h3>
            <ul>
                <li><strong>Host:</strong> <?php echo htmlspecialchars($dbHost); ?></li>
                <li><strong>Database:</strong> <?php echo htmlspecialchars($dbName); ?></li>
                <li><strong>Username:</strong> <?php echo htmlspecialchars($dbUser); ?></li>
                <li><strong>Password:</strong> ***SET*** (<?php echo strlen($dbPass); ?> characters)</li>
            </ul>
            
            <?php
            // Test actual database connection
            try {
                require_once 'db_config.php';
                $pdo = getDatabaseConnection();
                if ($pdo) {
                    echo '<div class="status success">✓ Database connection test SUCCESSFUL!</div>';
                } else {
                    echo '<div class="status error">✗ Database connection test FAILED - check credentials</div>';
                }
            } catch (Exception $e) {
                echo '<div class="status error">✗ Database connection error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            ?>
        <?php endif; ?>
        
        <h3>Test SMTP Configuration:</h3>
        <?php
        $smtpHost = env('SMTP_HOST');
        $smtpPort = env('SMTP_PORT');
        $smtpUser = env('SMTP_USERNAME');
        $smtpPass = env('SMTP_PASSWORD');
        
        $allSet = !empty($smtpHost) && !empty($smtpPort) && !empty($smtpUser) && !empty($smtpPass);
        ?>
        
        <div class="status <?php echo $allSet ? 'success' : 'error'; ?>">
            <?php if ($allSet): ?>
                ✓ All SMTP credentials are configured correctly!
            <?php else: ?>
                ✗ Some SMTP credentials are missing. Please check your .env file.
            <?php endif; ?>
        </div>
        
        <?php if ($allSet): ?>
            <h3>SMTP Configuration Preview:</h3>
            <ul>
                <li><strong>Host:</strong> <?php echo htmlspecialchars($smtpHost); ?></li>
                <li><strong>Port:</strong> <?php echo htmlspecialchars($smtpPort); ?></li>
                <li><strong>Username:</strong> <?php echo htmlspecialchars($smtpUser); ?></li>
                <li><strong>Password:</strong> ***SET*** (<?php echo strlen($smtpPass); ?> characters)</li>
                <li><strong>Encryption:</strong> <?php echo htmlspecialchars(env('SMTP_ENCRYPTION', 'Not Set')); ?></li>
            </ul>
        <?php endif; ?>
        
        <h3>Debugging Info:</h3>
        <ul>
            <li><strong>PHP Version:</strong> <?php echo phpversion(); ?></li>
            <li><strong>Current Directory:</strong> <?php echo __DIR__; ?></li>
            <li><strong>Config File:</strong> <?php echo file_exists(__DIR__ . '/config.php') ? '✓ Found' : '✗ Not Found'; ?></li>
        </ul>
        
        <?php if (!$allSet): ?>
            <div class="status warning">
                <strong>⚠️ Action Required:</strong><br>
                1. Make sure your .env file exists in the root directory<br>
                2. Make sure all SMTP variables are set (SMTP_USERNAME and SMTP_PASSWORD are critical)<br>
                3. Check file permissions - PHP must be able to read the .env file<br>
                4. Refresh this page after making changes
            </div>
        <?php endif; ?>
    </div>
</body>
</html>