<?php
/**
 * p.php - Gmail-Safe Tracking Pixel
 * Short filename, minimal code, maximum compatibility
 */

// Config
define('TESTING_MODE', false);
define('LOG_FILE', __DIR__ . '/tracking.log');

// Prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: image/gif');

// No errors in output
ini_set('display_errors', 0);
error_reporting(0);

require_once 'db_config.php';
require_once 'read_tracking_helper.php';

// Get token (accept both 'id' and 't' parameters)
$token = $_GET['id'] ?? $_GET['t'] ?? null;

if (!$token || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
    log_event("Invalid token");
    output_pixel();
    exit;
}

// Get data
$ip = get_ip();
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

log_event("Request: token=" . substr($token, 0, 16) . "..., ip=$ip");

// Process
try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        log_event("DB connection failed");
        output_pixel();
        exit;
    }
    
    // Get tracking record
    $stmt = $pdo->prepare("SELECT * FROM email_read_tracking WHERE tracking_token = ?");
    $stmt->execute([$token]);
    $tracking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tracking) {
        log_event("Token not found: $token");
        output_pixel();
        exit;
    }
    
    log_event("Found: ID=" . $tracking['id'] . ", read=" . $tracking['is_read']);
    
    // Parse UA
    $uaData = parseUserAgent($ua);
    $openDelay = time() - strtotime($tracking['created_at']);
    
    // Check filters
    $isSender = isSenderOpen($tracking['sender_email'], $ip, $ua);
    $isBot = isBotOpen($ua, $openDelay);
    
    $isValid = TESTING_MODE || (!$isSender && !$isBot);
    
    log_event("Filters: sender=$isSender, bot=$isBot, valid=$isValid");
    
    // Update total opens
    $stmt = $pdo->prepare("UPDATE email_read_tracking SET total_opens = total_opens + 1 WHERE id = ?");
    $stmt->execute([$tracking['id']]);
    
    // Log event
    $stmt = $pdo->prepare("
        INSERT INTO email_read_events (tracking_id, opened_at, ip_address, user_agent, is_valid, flagged_reason)
        VALUES (?, NOW(), ?, ?, ?, ?)
    ");
    $stmt->execute([
        $tracking['id'],
        $ip,
        $ua,
        $isValid ? 1 : 0,
        $isSender ? 'sender' : ($isBot ? 'bot' : null)
    ]);
    
    // Mark as read if first valid open
    if ($isValid && !$tracking['is_read']) {
        log_event("Marking as read");
        
        $stmt = $pdo->prepare("
            UPDATE email_read_tracking
            SET is_read = 1, first_read_at = NOW(), valid_opens = valid_opens + 1,
                ip_address = ?, user_agent = ?, browser = ?, os = ?, device_type = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $ip, $ua,
            $uaData['browser'],
            $uaData['os'],
            $uaData['device_type'],
            $tracking['id']
        ]);
        
        log_event("SUCCESS: Marked as read");
    } elseif ($isValid) {
        $stmt = $pdo->prepare("UPDATE email_read_tracking SET valid_opens = valid_opens + 1 WHERE id = ?");
        $stmt->execute([$tracking['id']]);
    }
    
} catch (Exception $e) {
    log_event("Error: " . $e->getMessage());
}

output_pixel();
exit;

/**
 * Get real IP
 */
function get_ip() {
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($headers as $h) {
        if (isset($_SERVER[$h]) && !empty($_SERVER[$h])) {
            $ip = $_SERVER[$h];
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

/**
 * Output 1x1 transparent GIF
 */
function output_pixel() {
    echo base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw==');
}

/**
 * Simple logging
 */
function log_event($msg) {
    $line = date('Y-m-d H:i:s') . " | " . $msg . "\n";
    @file_put_contents(LOG_FILE, $line, FILE_APPEND);
}
?>