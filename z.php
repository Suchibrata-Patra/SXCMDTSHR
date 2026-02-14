<?php
/**
 * TEST FILE - Use this to diagnose the HTTP 500 error
 * 
 * Instructions:
 * 1. Upload this file as test.php to your server
 * 2. Visit http://hr.holidayseva.com/test.php in your browser
 * 3. This will tell us exactly where the error is happening
 */

// Turn on error display temporarily for testing
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

echo "<!DOCTYPE html><html><head><title>Diagnostic Test</title><style>
body { font-family: monospace; padding: 20px; background: #f0f0f0; }
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.info { color: blue; }
pre { background: white; padding: 10px; border: 1px solid #ccc; }
</style></head><body>";

echo "<h1>🔍 Diagnostic Test Results</h1>";

// Test 1: Basic PHP
echo "<h2>Test 1: Basic PHP</h2>";
echo "<p class='success'>✓ PHP is working</p>";
echo "<p class='info'>PHP Version: " . phpversion() . "</p>";

// Test 2: File paths
echo "<h2>Test 2: File Paths</h2>";
echo "<p class='info'>Current directory: " . __DIR__ . "</p>";
echo "<p class='info'>Current file: " . __FILE__ . "</p>";

// Test 3: Check if security_handler.php exists
echo "<h2>Test 3: Security Handler File</h2>";
$securityFile = __DIR__ . '/security_handler.php';
if (file_exists($securityFile)) {
    echo "<p class='success'>✓ security_handler.php exists</p>";
    echo "<p class='info'>File size: " . filesize($securityFile) . " bytes</p>";
} else {
    echo "<p class='error'>✗ security_handler.php NOT FOUND</p>";
}

// Test 4: Check if config.php exists
echo "<h2>Test 4: Config File</h2>";
$configFile = __DIR__ . '/config.php';
if (file_exists($configFile)) {
    echo "<p class='success'>✓ config.php exists</p>";
    echo "<p class='info'>File size: " . filesize($configFile) . " bytes</p>";
} else {
    echo "<p class='info'>config.php not found (optional)</p>";
}

// Test 5: Check if login.php exists
echo "<h2>Test 5: Login File</h2>";
$loginFile = __DIR__ . '/login.php';
if (file_exists($loginFile)) {
    echo "<p class='success'>✓ login.php exists</p>";
    echo "<p class='info'>File size: " . filesize($loginFile) . " bytes</p>";
} else {
    echo "<p class='error'>✗ login.php NOT FOUND - This is your problem!</p>";
}

// Test 6: Try to include security_handler.php
echo "<h2>Test 6: Load Security Handler</h2>";
try {
    require_once __DIR__ . '/security_handler.php';
    echo "<p class='success'>✓ security_handler.php loaded successfully</p>";
    echo "<p class='info'>No errors during load</p>";
} catch (Exception $e) {
    echo "<p class='error'>✗ ERROR loading security_handler.php:</p>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

// Test 7: Check session
echo "<h2>Test 7: Session Test</h2>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    echo "<p class='success'>✓ Session started</p>";
} else {
    echo "<p class='success'>✓ Session already active</p>";
}
echo "<p class='info'>Session ID: " . session_id() . "</p>";

// Test 8: Check logs directory
echo "<h2>Test 8: Logs Directory</h2>";
$logsDir = __DIR__ . '/logs';
if (is_dir($logsDir)) {
    echo "<p class='success'>✓ Logs directory exists</p>";
    
    // Check for debug log
    $debugLog = $logsDir . '/security_debug.log';
    if (file_exists($debugLog)) {
        echo "<p class='success'>✓ Debug log exists</p>";
        echo "<h3>Last 20 lines of debug log:</h3>";
        echo "<pre>" . htmlspecialchars(shell_exec("tail -20 " . escapeshellarg($debugLog))) . "</pre>";
    } else {
        echo "<p class='info'>No debug log yet</p>";
    }
    
    // Check for PHP errors log
    $phpLog = $logsDir . '/php_errors.log';
    if (file_exists($phpLog)) {
        echo "<p class='error'>⚠ PHP Errors log exists - there may be errors!</p>";
        echo "<h3>Last 20 lines of PHP errors:</h3>";
        echo "<pre>" . htmlspecialchars(shell_exec("tail -20 " . escapeshellarg($phpLog))) . "</pre>";
    }
} else {
    echo "<p class='info'>Logs directory doesn't exist yet</p>";
}

// Test 9: List all PHP files in directory
echo "<h2>Test 9: PHP Files in Directory</h2>";
$phpFiles = glob(__DIR__ . '/*.php');
echo "<ul>";
foreach ($phpFiles as $file) {
    echo "<li>" . basename($file) . " (" . filesize($file) . " bytes)</li>";
}
echo "</ul>";

// Test 10: Check PHP configuration
echo "<h2>Test 10: PHP Configuration</h2>";
echo "<p class='info'>display_errors: " . ini_get('display_errors') . "</p>";
echo "<p class='info'>error_reporting: " . error_reporting() . "</p>";
echo "<p class='info'>max_execution_time: " . ini_get('max_execution_time') . "</p>";
echo "<p class='info'>memory_limit: " . ini_get('memory_limit') . "</p>";

echo "<hr>";
echo "<h2>📋 Summary</h2>";
echo "<p>If you see this page, PHP is working fine.</p>";
echo "<p>Check the test results above to identify the issue:</p>";
echo "<ul>";
echo "<li>If security_handler.php loads successfully, the issue is in login.php</li>";
echo "<li>If login.php doesn't exist, you need to upload it</li>";
echo "<li>Check the debug logs and error logs for specific error messages</li>";
echo "</ul>";

echo "</body></html>";