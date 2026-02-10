<?php
/**
 * Read Tracking Helper Functions
 * Provides utility functions for email tracking system
 */

/**
 * Generate unique tracking token
 */
function generateTrackingToken() {
    return bin2hex(random_bytes(32)); // 64 character hex string
}

/**
 * Get base URL for tracking pixel
 * IMPORTANT: Configure this for your environment
 */
function getBaseUrl() {
    // OPTION 1: Hardcoded URL (RECOMMENDED for production)
    // Uncomment and set your actual domain:
    return 'https://hr.holidayseva.com';
    
    // // OPTION 2: Auto-detect (may fail behind load balancers/proxies)
    // $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    // $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // // Remove port if it's standard
    // if (($protocol === 'https' && strpos($host, ':443') !== false) ||
    //     ($protocol === 'http' && strpos($host, ':80') !== false)) {
    //     $host = preg_replace('/:\d+$/', '', $host);
    // }
    
    return $protocol . '://' . $host;
}

/**
 * Build tracking pixel URL
 */
function buildTrackingPixelUrl($trackingToken) {
    $baseUrl = getBaseUrl();
    return $baseUrl . '/track_pixel.php?t=' . urlencode($trackingToken);
}

/**
 * Inject tracking pixel into email body
 */
function injectTrackingPixel($emailBody, $trackingToken) {
    $pixelUrl = buildTrackingPixelUrl($trackingToken);
    $trackingPixel = '<img src="' . htmlspecialchars($pixelUrl) . '" width="1" height="1" alt="" style="display:none !important; visibility:hidden !important; opacity:0 !important;" />';
    
    // Insert before closing body tag if HTML email
    if (stripos($emailBody, '</body>') !== false) {
        return str_ireplace('</body>', $trackingPixel . '</body>', $emailBody);
    }
    
    // Otherwise append to end
    return $emailBody . $trackingPixel;
}

/**
 * Initialize email tracking record
 * Creates a tracking record in email_read_tracking table
 */
function initializeEmailTracking($emailId, $senderEmail, $recipientEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            error_log("Failed to get database connection in initializeEmailTracking");
            return false;
        }
        
        // Check if tracking already exists
        $stmt = $pdo->prepare("SELECT tracking_token FROM email_read_tracking WHERE email_id = ?");
        $stmt->execute([$emailId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            return $existing['tracking_token'];
        }
        
        // Generate new tracking token
        $trackingToken = generateTrackingToken();
        
        // Insert tracking record
        $stmt = $pdo->prepare("
            INSERT INTO email_read_tracking (
                email_id, tracking_token, sender_email, recipient_email,
                is_read, total_opens, valid_opens, created_at
            ) VALUES (?, ?, ?, ?, 0, 0, 0, NOW())
        ");
        
        $stmt->execute([$emailId, $trackingToken, $senderEmail, $recipientEmail]);
        
        // Also update the emails table with tracking_token
        $stmt = $pdo->prepare("UPDATE emails SET tracking_token = ? WHERE id = ?");
        $stmt->execute([$trackingToken, $emailId]);
        
        error_log("Initialized tracking for email ID: $emailId, token: $trackingToken");
        
        return $trackingToken;
        
    } catch (PDOException $e) {
        error_log("Error initializing email tracking: " . $e->getMessage());
        return false;
    }
}

/**
 * Legacy support for sent_emails table
 */
function initializeLegacyEmailTracking($sentEmailId, $senderEmail, $recipientEmail) {
    // For backward compatibility with sent_emails table
    // Use negative ID to distinguish from emails table
    return initializeEmailTracking(-abs($sentEmailId), $senderEmail, $recipientEmail);
}

/**
 * Get email read status
 */
function getEmailReadStatus($emailId) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return null;
        
        $stmt = $pdo->prepare("
            SELECT * FROM email_read_tracking WHERE email_id = ?
        ");
        $stmt->execute([$emailId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error getting read status: " . $e->getMessage());
        return null;
    }
}

/**
 * Legacy support
 */
function getLegacyEmailReadStatus($sentEmailId) {
    return getEmailReadStatus(-abs($sentEmailId));
}

/**
 * Parse user agent string
 */
function parseUserAgent($userAgent) {
    $data = [
        'browser' => 'Unknown',
        'os' => 'Unknown',
        'device_type' => 'unknown'
    ];
    
    if (empty($userAgent)) {
        return $data;
    }
    
    // Detect OS
    if (preg_match('/Windows NT/i', $userAgent)) {
        $data['os'] = 'Windows';
        $data['device_type'] = 'desktop';
    } elseif (preg_match('/Macintosh|Mac OS X/i', $userAgent)) {
        $data['os'] = 'macOS';
        $data['device_type'] = 'desktop';
    } elseif (preg_match('/Linux/i', $userAgent)) {
        $data['os'] = 'Linux';
        $data['device_type'] = 'desktop';
    } elseif (preg_match('/iPhone/i', $userAgent)) {
        $data['os'] = 'iOS';
        $data['device_type'] = 'mobile';
    } elseif (preg_match('/iPad/i', $userAgent)) {
        $data['os'] = 'iOS';
        $data['device_type'] = 'tablet';
    } elseif (preg_match('/Android/i', $userAgent)) {
        $data['os'] = 'Android';
        $data['device_type'] = preg_match('/Mobile/i', $userAgent) ? 'mobile' : 'tablet';
    }
    
    // Detect Browser
    if (preg_match('/Firefox/i', $userAgent)) {
        $data['browser'] = 'Firefox';
    } elseif (preg_match('/Chrome/i', $userAgent)) {
        $data['browser'] = 'Chrome';
    } elseif (preg_match('/Safari/i', $userAgent)) {
        $data['browser'] = 'Safari';
    } elseif (preg_match('/Edge/i', $userAgent)) {
        $data['browser'] = 'Edge';
    } elseif (preg_match('/MSIE|Trident/i', $userAgent)) {
        $data['browser'] = 'Internet Explorer';
    } elseif (preg_match('/Outlook/i', $userAgent)) {
        $data['browser'] = 'Outlook';
    }
    
    // Detect bots
    if (preg_match('/bot|crawler|spider|scraper/i', $userAgent)) {
        $data['device_type'] = 'bot';
    }
    
    return $data;
}

/**
 * Check if open is from sender (testing their own email)
 */
function isSenderOpen($senderEmail, $ipAddress, $userAgent) {
    // Check if session matches sender
    @session_start();
    if (isset($_SESSION['smtp_user']) && $_SESSION['smtp_user'] === $senderEmail) {
        return true;
    }
    
    return false;
}

/**
 * Check if open is from bot/email scanner
 */
function isBotOpen($userAgent, $openDelay) {
    // Check user agent for bot patterns
    $botPatterns = [
        '/bot/i',
        '/crawler/i',
        '/spider/i',
        '/scanner/i',
        '/preview/i',
        '/prefetch/i',
        '/curl/i',
        '/wget/i',
        '/python/i',
        '/java/i',
        '/gmail image proxy/i',
        '/yahoo pipes/i',
        '/outlook/i',
        '/microsoft/i'
    ];
    
    foreach ($botPatterns as $pattern) {
        if (preg_match($pattern, $userAgent)) {
            return true;
        }
    }
    
    // Check if opened too quickly (less than 2 seconds = likely prefetch)
    if ($openDelay < 2) {
        return true;
    }
    
    return false;
}

/**
 * Check if IP is likely a proxy/VPN
 */
function isLikelyProxy($ipAddress) {
    // Basic check for common proxy patterns
    // In production, use a proper IP intelligence API
    
    // Check for private/local IPs
    if (!filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return true;
    }
    
    // Could add more sophisticated proxy detection here
    // For now, just flag private IPs
    
    return false;
}

?>