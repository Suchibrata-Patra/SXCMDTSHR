<?php
/**
 * Tracking Pixel Endpoint
 * Handles email read receipt tracking
 * URL: track_pixel.php?t={tracking_token}
 */

require_once 'db_config.php';
require_once 'read_tracking_helper.php';

// Disable error display (pixel must always return valid image)
ini_set('display_errors', 0);
error_reporting(0);

// Get tracking token
$trackingToken = $_GET['t'] ?? null;

if (!$trackingToken) {
    outputTransparentPixel();
    exit;
}

// Collect analytics data
$ipAddress = getRealIpAddress();
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$referer = $_SERVER['HTTP_REFERER'] ?? '';

// Process the tracking event
processTrackingEvent($trackingToken, $ipAddress, $userAgent, $referer);

// Always output transparent pixel
outputTransparentPixel();
exit;

/**
 * Process tracking event with filtering
 */
function processTrackingEvent($trackingToken, $ipAddress, $userAgent, $referer) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return;
        
        // Get tracking record
        $stmt = $pdo->prepare("
            SELECT 
                id, email_id, sender_email, recipient_email, 
                is_read, created_at
            FROM email_read_tracking
            WHERE tracking_token = ?
        ");
        
        $stmt->execute([$trackingToken]);
        $tracking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$tracking) {
            error_log("Tracking token not found: " . $trackingToken);
            return;
        }
        
        // Parse user agent
        $uaData = parseUserAgent($userAgent);
        
        // Calculate open delay
        $sentTime = strtotime($tracking['created_at']);
        $openDelay = time() - $sentTime;
        
        // Determine if this is a valid read
        $isSender = isSenderOpen($tracking['sender_email'], $ipAddress, $userAgent);
        $isBot = isBotOpen($userAgent, $openDelay);
        $isProxy = isLikelyProxy($ipAddress);
        
        // Flag determination
        $isValidOpen = !$isSender && !$isBot;
        $flaggedReason = null;
        
        if ($isSender) {
            $flaggedReason = 'sender_open';
        } elseif ($isBot) {
            $flaggedReason = 'bot_or_prefetch';
        } elseif ($isProxy) {
            $flaggedReason = 'proxy_detected';
        }
        
        // Increment total opens
        $stmt = $pdo->prepare("
            UPDATE email_read_tracking
            SET total_opens = total_opens + 1,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$tracking['id']]);
        
        // Log this open event
        $stmt = $pdo->prepare("
            INSERT INTO email_read_events (
                tracking_id, opened_at, ip_address, user_agent,
                is_valid, flagged_reason
            ) VALUES (?, NOW(), ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $tracking['id'],
            $ipAddress,
            $userAgent,
            $isValidOpen ? 1 : 0,
            $flaggedReason
        ]);
        
        // If this is the FIRST VALID open, mark as read
        if ($isValidOpen && !$tracking['is_read']) {
            // Get country/city from IP (simplified - use API for production)
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
            
            $stmt->execute([
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
            
            // Also update legacy sent_emails if applicable
            if ($tracking['email_id'] < 0) {
                $legacyId = abs($tracking['email_id']);
                $stmt = $pdo->prepare("
                    UPDATE sent_emails
                    SET is_read = 1, first_read_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$legacyId]);
            }
            
            error_log("Email marked as read: token={$trackingToken}, recipient={$tracking['recipient_email']}");
            
        } elseif ($isValidOpen) {
            // Increment valid opens count for already-read emails
            $stmt = $pdo->prepare("
                UPDATE email_read_tracking
                SET valid_opens = valid_opens + 1,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$tracking['id']]);
        }
        
    } catch (PDOException $e) {
        error_log("Error processing tracking event: " . $e->getMessage());
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
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Get location from IP address
 * Basic implementation - use ip-api.com or similar for production
 */
function getLocationFromIP($ip) {
    $location = [
        'country' => null,
        'city' => null
    ];
    
    // Skip for local/private IPs
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return $location;
    }
    
    try {
        // Using free ip-api.com service (100 requests/minute limit)
        $apiUrl = "http://ip-api.com/json/{$ip}?fields=status,country,city";
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 2, // 2 second timeout
                'ignore_errors' => true
            ]
        ]);
        
        $response = @file_get_contents($apiUrl, false, $context);
        
        if ($response) {
            $data = json_decode($response, true);
            
            if ($data && isset($data['status']) && $data['status'] === 'success') {
                $location['country'] = $data['country'] ?? null;
                $location['city'] = $data['city'] ?? null;
            }
        }
        
    } catch (Exception $e) {
        error_log("Error getting location from IP: " . $e->getMessage());
    }
    
    return $location;
}

/**
 * Output transparent 1x1 pixel PNG
 */
function outputTransparentPixel() {
    // Set headers
    header('Content-Type: image/png');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    
    // Output 1x1 transparent PNG (43 bytes)
    echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
}
?>