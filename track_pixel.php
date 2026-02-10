<?php
/**
 * TRACKING PIXEL ENDPOINT - DEBUG VERSION
 * Comprehensive logging for troubleshooting
 * 
 * URL: track_pixel.php?t={tracking_token}
 * 
 * IMPORTANT: Set TESTING_MODE = true to bypass all filters
 */

// ============================================
// DEBUG CONFIGURATION
// ============================================
define('TESTING_MODE', true);  // Set to TRUE to bypass all filtering
define('DEBUG_LOG_FILE', __DIR__ . '/tracking_debug.log');
define('ENABLE_CONSOLE_DEBUG', true); // Adds HTML comments to pixel response

// Disable error display (pixel must always return valid image)
ini_set('display_errors', 0);
error_reporting(0);

// Start logging
debugLog("=== TRACKING PIXEL REQUEST STARTED ===");
debugLog("Request Time: " . date('Y-m-d H:i:s'));
debugLog("Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));

require_once 'db_config.php';
require_once 'read_tracking_helper.php';

// ============================================
// COLLECT REQUEST DATA
// ============================================
$trackingToken = $_GET['t'] ?? null;
debugLog("Tracking Token from URL: " . ($trackingToken ?? 'NULL'));

if (!$trackingToken) {
    debugLog("ERROR: No tracking token provided");
    outputTransparentPixel("No tracking token");
    exit;
}

// Collect analytics data
$ipAddress = getRealIpAddress();
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$referer = $_SERVER['HTTP_REFERER'] ?? '';

debugLog("IP Address: " . $ipAddress);
debugLog("User Agent: " . $userAgent);
debugLog("Referer: " . $referer);

// ============================================
// PROCESS TRACKING EVENT
// ============================================
try {
    processTrackingEvent($trackingToken, $ipAddress, $userAgent, $referer);
} catch (Exception $e) {
    debugLog("EXCEPTION in processTrackingEvent: " . $e->getMessage());
    debugLog("Stack trace: " . $e->getTraceAsString());
}

// Always output pixel
debugLog("=== TRACKING PIXEL REQUEST COMPLETED ===\n");
outputTransparentPixel();
exit;

/**
 * Process tracking event with comprehensive debugging
 */
function processTrackingEvent($trackingToken, $ipAddress, $userAgent, $referer) {
    debugLog("--- Processing Tracking Event ---");
    
    try {
        // Get database connection
        debugLog("Attempting database connection...");
        $pdo = getDatabaseConnection();
        
        if (!$pdo) {
            debugLog("ERROR: Database connection failed!");
            return;
        }
        debugLog("✓ Database connected successfully");
        
        // Get tracking record
        debugLog("Querying tracking record for token: " . $trackingToken);
        $stmt = $pdo->prepare("
            SELECT 
                id, email_id, sender_email, recipient_email, 
                is_read, created_at, total_opens, valid_opens
            FROM email_read_tracking
            WHERE tracking_token = ?
        ");
        
        $stmt->execute([$trackingToken]);
        $tracking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$tracking) {
            debugLog("ERROR: Tracking token not found in database!");
            debugLog("Token searched: " . $trackingToken);
            
            // Check if token exists in emails table
            $stmt = $pdo->prepare("SELECT id, tracking_token FROM emails WHERE tracking_token = ?");
            $stmt->execute([$trackingToken]);
            $emailCheck = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($emailCheck) {
                debugLog("WARNING: Token found in emails table but NOT in email_read_tracking table!");
                debugLog("Email ID: " . $emailCheck['id']);
            } else {
                debugLog("Token not found in emails table either");
            }
            
            return;
        }
        
        debugLog("✓ Tracking record found:");
        debugLog("  - Tracking ID: " . $tracking['id']);
        debugLog("  - Email ID: " . $tracking['email_id']);
        debugLog("  - Sender: " . $tracking['sender_email']);
        debugLog("  - Recipient: " . $tracking['recipient_email']);
        debugLog("  - Currently Read: " . ($tracking['is_read'] ? 'YES' : 'NO'));
        debugLog("  - Total Opens: " . $tracking['total_opens']);
        debugLog("  - Valid Opens: " . $tracking['valid_opens']);
        debugLog("  - Created At: " . $tracking['created_at']);
        
        // Parse user agent
        $uaData = parseUserAgent($userAgent);
        debugLog("User Agent Parsed:");
        debugLog("  - Browser: " . $uaData['browser']);
        debugLog("  - OS: " . $uaData['os']);
        debugLog("  - Device: " . $uaData['device_type']);
        
        // Calculate open delay
        $sentTime = strtotime($tracking['created_at']);
        $openDelay = time() - $sentTime;
        debugLog("Open Delay: " . $openDelay . " seconds");
        
        // ============================================
        // FILTERING LOGIC
        // ============================================
        debugLog("--- Applying Filters ---");
        
        $isSender = isSenderOpen($tracking['sender_email'], $ipAddress, $userAgent);
        $isBot = isBotOpen($userAgent, $openDelay);
        $isProxy = isLikelyProxy($ipAddress);
        
        debugLog("Filter Results:");
        debugLog("  - Is Sender Open: " . ($isSender ? 'YES (BLOCKED)' : 'NO'));
        debugLog("  - Is Bot Open: " . ($isBot ? 'YES (BLOCKED)' : 'NO'));
        debugLog("  - Is Proxy: " . ($isProxy ? 'YES (WARNING)' : 'NO'));
        
        // Check session for sender detection
        @session_start();
        if (isset($_SESSION['smtp_user'])) {
            debugLog("  - Session User: " . $_SESSION['smtp_user']);
            debugLog("  - Matches Sender: " . ($_SESSION['smtp_user'] === $tracking['sender_email'] ? 'YES' : 'NO'));
        } else {
            debugLog("  - Session User: Not logged in");
        }
        
        // Determine if valid open
        $isValidOpen = TESTING_MODE || (!$isSender && !$isBot);
        
        if (TESTING_MODE) {
            debugLog("⚠️  TESTING MODE ENABLED - All filters bypassed!");
        }
        
        debugLog("Final Decision: " . ($isValidOpen ? 'VALID OPEN ✓' : 'BLOCKED ✗'));
        
        $flaggedReason = null;
        if ($isSender) {
            $flaggedReason = 'sender_open';
        } elseif ($isBot) {
            $flaggedReason = 'bot_or_prefetch';
        } elseif ($isProxy) {
            $flaggedReason = 'proxy_detected';
        }
        
        if ($flaggedReason) {
            debugLog("Flagged Reason: " . $flaggedReason);
        }
        
        // ============================================
        // DATABASE UPDATES
        // ============================================
        debugLog("--- Updating Database ---");
        
        // Increment total opens
        debugLog("Incrementing total_opens...");
        $stmt = $pdo->prepare("
            UPDATE email_read_tracking
            SET total_opens = total_opens + 1,
                updated_at = NOW()
            WHERE id = ?
        ");
        $result = $stmt->execute([$tracking['id']]);
        debugLog($result ? "✓ Total opens incremented" : "✗ Failed to increment total opens");
        
        // Log this open event
        debugLog("Logging open event...");
        $stmt = $pdo->prepare("
            INSERT INTO email_read_events (
                tracking_id, opened_at, ip_address, user_agent,
                is_valid, flagged_reason
            ) VALUES (?, NOW(), ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $tracking['id'],
            $ipAddress,
            $userAgent,
            $isValidOpen ? 1 : 0,
            $flaggedReason
        ]);
        debugLog($result ? "✓ Open event logged" : "✗ Failed to log open event");
        
        // If this is the FIRST VALID open, mark as read
        if ($isValidOpen && !$tracking['is_read']) {
            debugLog("*** THIS IS THE FIRST VALID OPEN - MARKING AS READ ***");
            
            // Get country/city from IP
            $location = getLocationFromIP($ipAddress);
            debugLog("Location Data:");
            debugLog("  - Country: " . ($location['country'] ?? 'N/A'));
            debugLog("  - City: " . ($location['city'] ?? 'N/A'));
            
            debugLog("Executing UPDATE query to mark as read...");
            $stmt = $pdo->prepare("
                UPDATE email_read_tracking
                SET 
                    is_read = 1,
                    first_read_at = NOW(),
                    valid_opens = valid_opens + 1,
                    ip_address = ?,
                    user_agent = ?,
                    browser = ?,
                    os = ?,
                    device_type = ?,
                    country = ?,
                    city = ?,
                    is_sender_open = ?,
                    is_bot_open = ?,
                    is_proxy = ?,
                    open_delay_seconds = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $params = [
                $ipAddress,
                $userAgent,
                $uaData['browser'],
                $uaData['os'],
                $uaData['device_type'],
                $location['country'],
                $location['city'],
                $isSender ? 1 : 0,
                $isBot ? 1 : 0,
                $isProxy ? 1 : 0,
                $openDelay,
                $tracking['id']
            ];
            
            debugLog("Update parameters: " . json_encode($params));
            
            try {
                $result = $stmt->execute($params);
                $rowsAffected = $stmt->rowCount();
                
                debugLog("Update executed: " . ($result ? 'SUCCESS' : 'FAILED'));
                debugLog("Rows affected: " . $rowsAffected);
                
                if ($rowsAffected > 0) {
                    debugLog("✓✓✓ EMAIL SUCCESSFULLY MARKED AS READ ✓✓✓");
                } else {
                    debugLog("⚠️  WARNING: Update succeeded but 0 rows affected");
                    debugLog("This might indicate a WHERE clause mismatch");
                }
                
                // Verify the update
                $stmt = $pdo->prepare("SELECT is_read, first_read_at FROM email_read_tracking WHERE id = ?");
                $stmt->execute([$tracking['id']]);
                $verify = $stmt->fetch(PDO::FETCH_ASSOC);
                
                debugLog("Verification query result:");
                debugLog("  - is_read: " . $verify['is_read']);
                debugLog("  - first_read_at: " . $verify['first_read_at']);
                
            } catch (PDOException $e) {
                debugLog("✗✗✗ UPDATE QUERY FAILED ✗✗✗");
                debugLog("PDO Error: " . $e->getMessage());
                debugLog("Error Code: " . $e->getCode());
            }
            
            // Also update legacy sent_emails if applicable
            if ($tracking['email_id'] < 0) {
                debugLog("Updating legacy sent_emails table...");
                $legacyId = abs($tracking['email_id']);
                $stmt = $pdo->prepare("
                    UPDATE sent_emails
                    SET is_read = 1, first_read_at = NOW()
                    WHERE id = ?
                ");
                $result = $stmt->execute([$legacyId]);
                debugLog($result ? "✓ Legacy table updated" : "✗ Legacy update failed");
            }
            
        } elseif ($isValidOpen) {
            debugLog("Email already marked as read, incrementing valid_opens counter...");
            
            $stmt = $pdo->prepare("
                UPDATE email_read_tracking
                SET valid_opens = valid_opens + 1,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $result = $stmt->execute([$tracking['id']]);
            debugLog($result ? "✓ Valid opens incremented" : "✗ Failed to increment valid opens");
            
        } else {
            debugLog("Open was filtered out - no read status update");
        }
        
    } catch (PDOException $e) {
        debugLog("✗✗✗ DATABASE ERROR ✗✗✗");
        debugLog("PDO Exception: " . $e->getMessage());
        debugLog("Error Code: " . $e->getCode());
        debugLog("SQL State: " . ($e->errorInfo[0] ?? 'N/A'));
        debugLog("Stack Trace: " . $e->getTraceAsString());
    } catch (Exception $e) {
        debugLog("✗✗✗ GENERAL ERROR ✗✗✗");
        debugLog("Exception: " . $e->getMessage());
        debugLog("Stack Trace: " . $e->getTraceAsString());
    }
}

/**
 * Get real IP address (handles proxies)
 */
function getRealIpAddress() {
    $headers = [
        'HTTP_CF_CONNECTING_IP', // Cloudflare
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR'
    ];
    
    foreach ($headers as $header) {
        if (isset($_SERVER[$header]) && !empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            
            // Handle comma-separated IPs (proxy chain)
            if (strpos($ip, ',') !== false) {
                $ips = explode(',', $ip);
                $ip = trim($ips[0]);
            }
            
            // Validate IP
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Get location from IP address
 */
function getLocationFromIP($ip) {
    $location = [
        'country' => null,
        'city' => null
    ];
    
    // Skip for local/private IPs
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        debugLog("Skipping geolocation for private IP: " . $ip);
        return $location;
    }
    
    try {
        debugLog("Fetching geolocation for IP: " . $ip);
        
        // Using free ip-api.com service (100 requests/minute limit)
        $apiUrl = "http://ip-api.com/json/{$ip}?fields=status,country,city";
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 2,
                'ignore_errors' => true
            ]
        ]);
        
        $response = @file_get_contents($apiUrl, false, $context);
        
        if ($response) {
            $data = json_decode($response, true);
            
            if ($data && isset($data['status']) && $data['status'] === 'success') {
                $location['country'] = $data['country'] ?? null;
                $location['city'] = $data['city'] ?? null;
                debugLog("Geolocation: " . $location['country'] . ", " . $location['city']);
            } else {
                debugLog("Geolocation API returned non-success status");
            }
        } else {
            debugLog("Geolocation API request failed");
        }
        
    } catch (Exception $e) {
        debugLog("Geolocation error: " . $e->getMessage());
    }
    
    return $location;
}

/**
 * Output transparent 1x1 pixel PNG
 */
function outputTransparentPixel($debugMessage = '') {
    // Set headers
    header('Content-Type: image/png');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    
    // Add debug info as HTML comment (invisible in images but shows in raw response)
    if (ENABLE_CONSOLE_DEBUG && !empty($debugMessage)) {
        echo "<!-- DEBUG: " . htmlspecialchars($debugMessage) . " -->\n";
    }
    
    // Output 1x1 transparent PNG (43 bytes)
    echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
}

/**
 * Debug logging function
 */
function debugLog($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}\n";
    
    // Write to log file
    @file_put_contents(DEBUG_LOG_FILE, $logMessage, FILE_APPEND);
    
    // Also log to PHP error log
    error_log("TRACKING DEBUG: " . $message);
}
?>