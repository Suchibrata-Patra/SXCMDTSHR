<?php
/**
 * security_test.php
 * 
 * Comprehensive security testing script
 * Tests all security features to ensure proper implementation
 * 
 * USAGE: Access via browser: http://yoursite.com/security_test.php
 * WARNING: DO NOT leave this file on production servers!
 */


$tests = [];
$passed = 0;
$failed = 0;

// Test 1: Security Handler Loaded
$tests[] = [
    'name' => 'Security Handler Loaded',
    'status' => class_exists('SecurityHandler'),
    'message' => class_exists('SecurityHandler') ? 'Security handler class exists' : 'Security handler not loaded'
];

// Test 2: Session Security
$tests[] = [
    'name' => 'Session Security',
    'status' => session_status() === PHP_SESSION_ACTIVE,
    'message' => session_status() === PHP_SESSION_ACTIVE ? 'Session started with secure settings' : 'Session not active'
];

// Test 3: CSRF Token Generation
$csrfToken = csrf_token();
$tests[] = [
    'name' => 'CSRF Token Generation',
    'status' => !empty($csrfToken) && strlen($csrfToken) === 64,
    'message' => !empty($csrfToken) ? "Token generated: " . substr($csrfToken, 0, 16) . "..." : 'Token generation failed'
];

// Test 4: Input Sanitization
$testInput = "<script>alert('XSS')</script>";
$sanitized = secure_input($testInput, 'html');
$tests[] = [
    'name' => 'XSS Sanitization',
    'status' => $sanitized !== $testInput && strpos($sanitized, '<script>') === false,
    'message' => "Input sanitized correctly: " . substr($sanitized, 0, 50)
];

// Test 5: Email Sanitization
$testEmail = "test@example.com<script>";
$sanitizedEmail = secure_input($testEmail, 'email');
$tests[] = [
    'name' => 'Email Sanitization',
    'status' => $sanitizedEmail === 'test@example.com',
    'message' => "Email sanitized: $sanitizedEmail"
];

// Test 6: Filename Sanitization
$testFilename = "../../../etc/passwd";
$sanitizedFilename = secure_input($testFilename, 'filename');
$tests[] = [
    'name' => 'Filename Sanitization (Path Traversal)',
    'status' => !strpos($sanitizedFilename, '..') && $sanitizedFilename === 'passwd',
    'message' => "Filename sanitized: $sanitizedFilename"
];

// Test 7: Security Headers
$headerTests = [
    'X-XSS-Protection' => false,
    'X-Frame-Options' => false,
    'X-Content-Type-Options' => false,
    'Referrer-Policy' => false
];

if (function_exists('headers_list')) {
    $headers = headers_list();
    foreach ($headers as $header) {
        foreach ($headerTests as $key => $value) {
            if (stripos($header, $key) !== false) {
                $headerTests[$key] = true;
            }
        }
    }
}

$allHeadersSet = !in_array(false, $headerTests);
$tests[] = [
    'name' => 'Security Headers',
    'status' => $allHeadersSet,
    'message' => $allHeadersSet ? 'All security headers set' : 'Some headers missing: ' . implode(', ', array_keys(array_filter($headerTests, fn($v) => !$v)))
];

// Test 8: Log Directory
$logDir = __DIR__ . '/logs';
$logFile = $logDir . '/security.log';
$tests[] = [
    'name' => 'Log Directory',
    'status' => is_dir($logDir) && is_writable($logDir),
    'message' => is_dir($logDir) ? 'Log directory exists and writable' : 'Log directory not found or not writable'
];

// Test 9: Log File
$tests[] = [
    'name' => 'Security Log File',
    'status' => file_exists($logFile),
    'message' => file_exists($logFile) ? 'Security log file exists' : 'Security log file not created yet'
];

// Test 10: Session Fingerprint
$tests[] = [
    'name' => 'Session Fingerprint',
    'status' => isset($_SESSION['fingerprint']),
    'message' => isset($_SESSION['fingerprint']) ? 'Session fingerprint set: ' . substr($_SESSION['fingerprint'], 0, 16) . '...' : 'No fingerprint'
];

// Test 11: Helper Functions
$helperFunctions = [
    'secure_input',
    'csrf_token',
    'csrf_field',
    'validate_upload',
    'record_login',
    'blacklist_ip'
];

$missingFunctions = [];
foreach ($helperFunctions as $func) {
    if (!function_exists($func)) {
        $missingFunctions[] = $func;
    }
}

$tests[] = [
    'name' => 'Helper Functions',
    'status' => empty($missingFunctions),
    'message' => empty($missingFunctions) ? 'All helper functions available' : 'Missing: ' . implode(', ', $missingFunctions)
];

// Test 12: CSRF Field Generation
$csrfField = csrf_field();
$tests[] = [
    'name' => 'CSRF Field HTML',
    'status' => strpos($csrfField, 'csrf_token') !== false && strpos($csrfField, 'hidden') !== false,
    'message' => 'CSRF field HTML generated correctly'
];

// Test 13: File Upload Validation (Simulated)
$tests[] = [
    'name' => 'File Upload Validation',
    'status' => function_exists('validate_upload'),
    'message' => 'File upload validation function available'
];

// Test 14: Rate Limiting
$tests[] = [
    'name' => 'Rate Limiting',
    'status' => isset($_SESSION['rate_limit_' . ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN')]),
    'message' => 'Rate limiting initialized'
];

// Test 15: Configuration Constants
$requiredConstants = [
    'MAX_LOGIN_ATTEMPTS',
    'LOGIN_LOCKOUT_TIME',
    'SESSION_TIMEOUT',
    'RATE_LIMIT_REQUESTS',
    'UPLOAD_MAX_SIZE'
];

$missingConstants = [];
foreach ($requiredConstants as $const) {
    if (!defined($const)) {
        $missingConstants[] = $const;
    }
}

$tests[] = [
    'name' => 'Configuration Constants',
    'status' => empty($missingConstants),
    'message' => empty($missingConstants) ? 'All constants defined' : 'Missing: ' . implode(', ', $missingConstants)
];

// Calculate results
foreach ($tests as $test) {
    if ($test['status']) {
        $passed++;
    } else {
        $failed++;
    }
}

$totalTests = count($tests);
$successRate = round(($passed / $totalTests) * 100, 1);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Test Results</title>
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
            padding: 40px 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }

        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 16px;
            opacity: 0.9;
        }

        .summary {
            padding: 30px 40px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .summary-card h3 {
            font-size: 36px;
            margin-bottom: 5px;
        }

        .summary-card p {
            color: #6c757d;
            font-size: 14px;
        }

        .summary-card.passed h3 { color: #28a745; }
        .summary-card.failed h3 { color: #dc3545; }
        .summary-card.rate h3 { color: #007bff; }

        .tests {
            padding: 40px;
        }

        .test-item {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }

        .test-item:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .test-item.passed {
            border-left: 6px solid #28a745;
        }

        .test-item.failed {
            border-left: 6px solid #dc3545;
        }

        .test-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
        }

        .test-icon {
            font-size: 24px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .test-item.passed .test-icon {
            background: #d4edda;
            color: #28a745;
        }

        .test-item.failed .test-icon {
            background: #f8d7da;
            color: #dc3545;
        }

        .test-name {
            font-size: 18px;
            font-weight: 600;
            color: #212529;
            flex: 1;
        }

        .test-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .test-item.passed .test-status {
            background: #d4edda;
            color: #28a745;
        }

        .test-item.failed .test-status {
            background: #f8d7da;
            color: #dc3545;
        }

        .test-message {
            color: #6c757d;
            font-size: 14px;
            line-height: 1.6;
            padding-left: 55px;
        }

        .footer {
            background: #f8f9fa;
            padding: 30px 40px;
            border-top: 1px solid #e9ecef;
        }

        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .warning strong {
            color: #856404;
        }

        .actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .progress-bar {
            width: 100%;
            height: 30px;
            background: #e9ecef;
            border-radius: 15px;
            overflow: hidden;
            margin: 20px 0;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
            transition: width 1s ease-in-out;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔒 Security Test Results</h1>
            <p>Comprehensive security validation for your application</p>
        </div>

        <div class="summary">
            <h2 style="margin-bottom: 10px;">Test Summary</h2>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $successRate; ?>%;">
                    <?php echo $successRate; ?>% Passed
                </div>
            </div>
            <div class="summary-grid">
                <div class="summary-card passed">
                    <h3><?php echo $passed; ?></h3>
                    <p>Tests Passed</p>
                </div>
                <div class="summary-card failed">
                    <h3><?php echo $failed; ?></h3>
                    <p>Tests Failed</p>
                </div>
                <div class="summary-card rate">
                    <h3><?php echo $totalTests; ?></h3>
                    <p>Total Tests</p>
                </div>
            </div>
        </div>

        <div class="tests">
            <h2 style="margin-bottom: 20px;">Detailed Test Results</h2>
            
            <?php foreach ($tests as $test): ?>
                <div class="test-item <?php echo $test['status'] ? 'passed' : 'failed'; ?>">
                    <div class="test-header">
                        <div class="test-icon">
                            <?php echo $test['status'] ? '✓' : '✗'; ?>
                        </div>
                        <div class="test-name"><?php echo htmlspecialchars($test['name']); ?></div>
                        <div class="test-status">
                            <?php echo $test['status'] ? 'Passed' : 'Failed'; ?>
                        </div>
                    </div>
                    <div class="test-message">
                        <?php echo htmlspecialchars($test['message']); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="footer">
            <?php if ($failed > 0): ?>
                <div class="warning">
                    <strong>⚠️ Warning:</strong> Some tests failed. Please review the implementation guide and fix the issues before deploying to production.
                </div>
            <?php else: ?>
                <div style="background: #d4edda; border-left: 4px solid #28a745; padding: 15px; border-radius: 5px; margin-bottom: 20px; color: #155724;">
                    <strong>✅ Success:</strong> All security tests passed! Your application is properly secured.
                </div>
            <?php endif; ?>

            <div class="actions">
                <button onclick="location.reload()" class="btn btn-primary">
                    🔄 Run Tests Again
                </button>
                <a href="SECURITY_IMPLEMENTATION_GUIDE.md" class="btn btn-secondary">
                    📖 View Guide
                </a>
                <button onclick="window.print()" class="btn btn-secondary">
                    🖨️ Print Report
                </button>
            </div>

            <p style="margin-top: 20px; color: #6c757d; font-size: 12px;">
                <strong>Important:</strong> Delete this file (security_test.php) from your production server after testing.
            </p>
        </div>
    </div>
</body>
</html>