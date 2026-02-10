<?php
/**
 * TRACKING PIXEL - CACHE-PROOF VERSION
 * Prevents Gmail Image Proxy from caching the pixel
 * 
 * URL: track_pixel.php?t={tracking_token}&r={random}&ts={timestamp}
 */

// ============================================
// CONFIGURATION
// ============================================
define('TESTING_MODE', false);  // Set to TRUE to bypass filtering
define('DEBUG_LOG_FILE', __DIR__ . '/tracking_debug.log');
define('ENABLE_CONSOLE_DEBUG', true);

// Critical: Disable all output buffering and error display
ini_set('display_errors', 0);
error_reporting(0);
ob_end_clean();

// ============================================
// PREVENT CACHING - CRITICAL!
// ============================================
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

// Additional anti-cache headers for Gmail/Outlook proxies
header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
header('Vary: *');

// Content type MUST be set early
header('Content-Type: image/gif');

// Start logging
debugLog("=== TRACKING PIXEL REQUEST STARTED ===");
debugLog("Request Time: " . date('Y-m-d H:i:s'));
debugLog("Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
debugLog("Query String: " . ($_SERVER['QUERY_STRING'] ?? 'N/A'));

require_once 'db_config.php';
require_once 'read_tracking_helper.php';

// ============================================
// COLLECT REQUEST DATA
// ============================================
$trackingToken = $_GET['t'] ?? null;
$randomParam = $_GET['r'] ?? null; // Anti-cache random value
$timestampParam = $_GET['ts'] ?? null; // Timestamp to prevent caching

debugLog("Tracking Token: " . ($trackingToken ?? 'NULL'));
debugLog("Random Param: " . ($randomParam ?? 'NULL'));
debugLog("Timestamp Param: " . ($timestampParam ?? 'NULL'));

if (!$trackingToken) {
    debugLog("ERROR: No tracking token provided");
    outputTransparentPixel("No tracking token");
    exit;
}

// Validate token format (should be 64 hex characters)
if (!preg_match('/^[a-f0-9]{64}$/i', $trackingToken)) {
    debugLog("ERROR: Invalid token format - expected 64 hex chars, got: " . strlen($trackingToken) . " chars");
    debugLog("Token value: " . substr($trackingToken, 0, 100) . (strlen($trackingToken) > 100 ? '...' : ''));
    outputTransparentPixel("Invalid token format");
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
 * Process tracking event
 */
function processTrackingEvent($trackingToken, $ipAddress, $userAgent, $referer) {
    debugLog("--- Processing Tracking Event ---");
    
    try {
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
        
        // Check session
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if (isset($_SESSION['smtp_user'])) {
            debugLog("  - Session User: " . $_SESSION['smtp_user']);
            debugLog("  - Matches Sender: " . ($_SESSION['smtp_user'] === $tracking['sender_email'] ? 'YES' : 'NO'));
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
        
        // Always increment total opens
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
        
        // If first valid open, mark as read
        if ($isValidOpen && !$tracking['is_read']) {
            debugLog("*** THIS IS THE FIRST VALID OPEN - MARKING AS READ ***");
            
            $location = getLocationFromIP($ipAddress);
            
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
            
            $result = $stmt->execute([
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
            ]);
            
            debugLog($result ? "✓✓✓ EMAIL SUCCESSFULLY MARKED AS READ ✓✓✓" : "✗✗✗ UPDATE FAILED ✗✗✗");
            
        } elseif ($isValidOpen) {
            debugLog("Email already read, incrementing valid_opens...");
            $stmt = $pdo->prepare("
                UPDATE email_read_tracking
                SET valid_opens = valid_opens + 1,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$tracking['id']]);
        } else {
            debugLog("Open was filtered out - no read status update");
        }
        
    } catch (PDOException $e) {
        debugLog("✗✗✗ DATABASE ERROR ✗✗✗");
        debugLog("PDO Exception: " . $e->getMessage());
    }
}

/**
 * Get real IP address
 */
function getRealIpAddress() {
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR'
    ];
    
    foreach ($headers as $header) {
        if (isset($_SERVER[$header]) && !empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            if (strpos($ip, ',') !== false) {
                $ips = explode(',', $ip);
                $ip = trim($ips[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Output transparent 1x1 GIF pixel
 * Using GIF instead of PNG - smaller and more compatible
 */
function outputTransparentPixel($debugMessage = '') {
    // 1x1 transparent GIF (43 bytes) - smallest possible
    // This is the same pixel used by Facebook, Google Analytics, etc.
    $pixel = base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw==');
    
    echo $pixel;
}

/**
 * Debug logging
 */
function debugLog($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}\n";
    @file_put_contents(DEBUG_LOG_FILE, $logMessage, FILE_APPEND);
}

/**
 * Get location from IP
 */
function getLocationFromIP($ip) {
    $location = ['country' => null, 'city' => null];
    
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return $location;
    }
    
    try {
        $apiUrl = "http://ip-api.com/json/{$ip}?fields=status,country,city";
        $context = stream_context_create(['http' => ['timeout' => 2]]);
        $response = @file_get_contents($apiUrl, false, $context);
        
        if ($response) {
            $data = json_decode($response, true);
            if ($data && $data['status'] === 'success') {
                $location['country'] = $data['country'] ?? null;
                $location['city'] = $data['city'] ?? null;
            }
        }
    } catch (Exception $e) {
        // Silently fail
    }
    
    return $location;
}
?>