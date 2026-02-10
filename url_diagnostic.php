<?php
/**
 * URL Diagnostic Tool
 * Tests if tracking pixel URL is accessible from outside
 */

session_start();
require_once 'db_config.php';

// Security check
if (!isset($_SESSION['smtp_user'])) {
    die("Please log in first");
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tracking URL Diagnostic</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1c1c1e;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }
        .section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .section h3 {
            margin-top: 0;
            color: #007AFF;
        }
        .url-display {
            background: white;
            border: 2px solid #e0e0e0;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            word-break: break-all;
            margin: 10px 0;
        }
        .status {
            padding: 10px 15px;
            border-radius: 6px;
            margin: 10px 0;
            font-weight: 600;
        }
        .status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .status.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        button {
            background: #007AFF;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            margin: 10px 10px 10px 0;
        }
        button:hover {
            background: #0051D5;
        }
        button.secondary {
            background: #6c757d;
        }
        button.secondary:hover {
            background: #5a6268;
        }
        .code-block {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            overflow-x: auto;
            margin: 15px 0;
        }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            font-family: 'Courier New', monospace;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        .test-result {
            display: none;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Tracking URL Diagnostic</h1>
        <p class="subtitle">Test if your tracking pixel URLs are accessible</p>

        <!-- Current URL Detection -->
        <div class="section">
            <h3>1. Auto-Detected Base URL</h3>
            <p>This is what the system detects as your base URL:</p>
            <?php
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $autoDetectedUrl = $protocol . '://' . $host;
            ?>
            <div class="url-display"><?php echo htmlspecialchars($autoDetectedUrl); ?></div>
            
            <div class="info-box">
                <strong>⚠️ Common Issues:</strong><br>
                • If behind a load balancer, this might be an internal IP<br>
                • If in a subfolder, the path might be missing<br>
                • HTTP vs HTTPS might be wrong if behind a proxy
            </div>
        </div>

        <!-- Custom URL Configuration -->
        <div class="section">
            <h3>2. Configure Custom Base URL</h3>
            <p>If auto-detection is wrong, set a hardcoded URL in <code>read_tracking_helper.php</code>:</p>
            
            <label for="customUrl">Your Public Domain:</label>
            <input type="text" id="customUrl" placeholder="https://yourdomain.com" value="<?php echo htmlspecialchars($autoDetectedUrl); ?>">
            
            <button onclick="generateConfig()">Generate Configuration Code</button>
            
            <div id="configCode" style="display:none;">
                <p><strong>Add this to read_tracking_helper.php in the getBaseUrl() function:</strong></p>
                <div class="code-block" id="codeDisplay"></div>
            </div>
        </div>

        <!-- Test Tracking Pixel -->
        <div class="section">
            <h3>3. Test Tracking Pixel</h3>
            <p>Generate a test tracking token and verify the pixel loads:</p>
            
            <button onclick="generateTestUrl()">Generate Test Pixel URL</button>
            <button class="secondary" onclick="testPixelLoad()">Test Pixel Load</button>
            
            <div id="testUrlDisplay" style="display:none; margin-top:15px;">
                <p><strong>Test Pixel URL:</strong></p>
                <div class="url-display" id="testUrlText"></div>
                
                <p><strong>Test this URL:</strong></p>
                <ol>
                    <li>Open it in a new browser tab (incognito mode recommended)</li>
                    <li>You should see a blank page (the 1x1 pixel)</li>
                    <li>Check the tracking_debug.log file for detailed logs</li>
                </ol>
            </div>
            
            <div class="test-result" id="pixelTestResult"></div>
        </div>

        <!-- Debug Log Viewer -->
        <div class="section">
            <h3>4. View Debug Log</h3>
            <p>Check the last 50 lines of tracking_debug.log:</p>
            
            <button onclick="viewDebugLog()">View Debug Log</button>
            <button class="secondary" onclick="clearDebugLog()">Clear Log</button>
            
            <div id="debugLogDisplay" style="display:none; margin-top:15px;">
                <div class="code-block" id="logContent" style="max-height: 400px; overflow-y: auto;"></div>
            </div>
        </div>

        <!-- Testing Mode Toggle -->
        <div class="section">
            <h3>5. Testing Mode</h3>
            <p>Enable testing mode to bypass all filters (sender check, bot check, etc.)</p>
            
            <div class="info-box">
                <strong>📝 Instructions:</strong><br>
                1. Edit <code>track_pixel_debug.php</code><br>
                2. Find the line: <code>define('TESTING_MODE', false);</code><br>
                3. Change to: <code>define('TESTING_MODE', true);</code><br>
                4. Save the file<br>
                5. Test your email tracking again<br>
                6. Remember to turn it back to <code>false</code> when done!
            </div>
        </div>
    </div>

    <script>
        function generateConfig() {
            const url = document.getElementById('customUrl').value;
            const code = `// In read_tracking_helper.php, find getBaseUrl() function
// Replace with:

function getBaseUrl() {
    // Hardcoded URL (RECOMMENDED for production)
    return '${url}';
}`;
            
            document.getElementById('codeDisplay').textContent = code;
            document.getElementById('configCode').style.display = 'block';
        }

        async function generateTestUrl() {
            try {
                const response = await fetch('generate_test_token.php');
                const data = await response.json();
                
                if (data.success) {
                    const testUrl = data.pixel_url;
                    document.getElementById('testUrlText').textContent = testUrl;
                    document.getElementById('testUrlDisplay').style.display = 'block';
                } else {
                    alert('Error: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                alert('Network error: ' + error.message);
            }
        }

        async function testPixelLoad() {
            const testUrl = document.getElementById('testUrlText').textContent;
            
            if (!testUrl) {
                alert('Please generate a test URL first');
                return;
            }
            
            const resultDiv = document.getElementById('pixelTestResult');
            resultDiv.innerHTML = '<div class="status warning">Testing pixel load...</div>';
            resultDiv.style.display = 'block';
            
            try {
                // Try to load the pixel
                const img = new Image();
                
                img.onload = function() {
                    resultDiv.innerHTML = '<div class="status success">✓ Pixel loaded successfully! Check debug log for details.</div>';
                    setTimeout(() => viewDebugLog(), 1000);
                };
                
                img.onerror = function() {
                    resultDiv.innerHTML = '<div class="status error">✗ Pixel failed to load. Check if track_pixel_debug.php is accessible.</div>';
                };
                
                img.src = testUrl;
                
            } catch (error) {
                resultDiv.innerHTML = '<div class="status error">✗ Error: ' + error.message + '</div>';
            }
        }

        async function viewDebugLog() {
            try {
                const response = await fetch('view_debug_log.php');
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('logContent').textContent = data.log_content || 'Log file is empty';
                    document.getElementById('debugLogDisplay').style.display = 'block';
                } else {
                    alert('Error: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                alert('Network error: ' + error.message);
            }
        }

        async function clearDebugLog() {
            if (!confirm('Are you sure you want to clear the debug log?')) {
                return;
            }
            
            try {
                const response = await fetch('clear_debug_log.php', { method: 'POST' });
                const data = await response.json();
                
                if (data.success) {
                    alert('Debug log cleared');
                    document.getElementById('logContent').textContent = '';
                } else {
                    alert('Error: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                alert('Network error: ' + error.message);
            }
        }
    </script>
</body>
</html>