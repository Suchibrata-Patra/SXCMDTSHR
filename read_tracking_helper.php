<?php
/**
 * Gmail-Safe Tracking Helper
 * Simplified implementation that passes Gmail's spam filters
 */

/**
 * Generate tracking token
 */
function generateTrackingToken() {
    return bin2hex(random_bytes(32));
}

/**
 * Get base URL - USE SUBDOMAIN FOR BETTER DELIVERABILITY
 */
function getBaseUrl() {
    // OPTION 1: Subdomain (HIGHLY RECOMMENDED)
    // Create DNS: track.holidayseva.com → Your Server IP
    return 'https://hr.holidayseva.com';
    
    // OPTION 2: Main domain (if subdomain not possible)
    // return 'https://hr.holidayseva.com';
}

/**
 * Build Gmail-safe tracking pixel URL
 * Uses simple, short parameters
 */
function buildTrackingPixelUrl($trackingToken) {
    $baseUrl = getBaseUrl();
    
    // SHORT filename and MINIMAL parameters
    // Gmail likes simple URLs
    $params = [
        'id' => $trackingToken,
        'v' => substr(md5(microtime()), 0, 6)  // Short random (6 chars)
    ];
    
    // Use short filename: p.php instead of track_pixel.php
    return $baseUrl . '/p.php?' . http_build_query($params);
}

/**
 * Inject Gmail-safe tracking pixel
 * Minimalist approach to avoid spam filters
 */
function injectTrackingPixel($emailBody, $trackingToken) {
    // Check if already has tracking
    if (preg_match('/\/p\.php\?/i', $emailBody)) {
        error_log("Tracking pixel already exists - skipping");
        return $emailBody;
    }
    
    $pixelUrl = buildTrackingPixelUrl($trackingToken);
    
    // MINIMALIST PIXEL - No extra attributes that might trigger filters
    $trackingPixel = sprintf(
        '<img src="%s" width="1" height="1" alt="">',
        htmlspecialchars($pixelUrl, ENT_QUOTES, 'UTF-8')
    );
    
    // Inject at very end, after all content
    if (stripos($emailBody, '</body>') !== false) {
        // Add after content but before closing tag
        return str_ireplace('</body>', $trackingPixel . "\n</body>", $emailBody);
    }
    
    if (stripos($emailBody, '</html>') !== false) {
        return str_ireplace('</html>', $trackingPixel . "\n</html>", $emailBody);
    }
    
    // Plain text fallback (append)
    return $emailBody . "\n" . $trackingPixel;
}

/**
 * Initialize tracking
 */
function initializeEmailTracking($emailId, $senderEmail, $recipientEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;
        
        $stmt = $pdo->prepare("SELECT tracking_token FROM email_read_tracking WHERE email_id = ?");
        $stmt->execute([$emailId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            return $existing['tracking_token'];
        }
        
        $trackingToken = generateTrackingToken();
        
        $stmt = $pdo->prepare("
            INSERT INTO email_read_tracking (
                email_id, tracking_token, sender_email, recipient_email,
                is_read, total_opens, valid_opens, created_at
            ) VALUES (?, ?, ?, ?, 0, 0, 0, NOW())
        ");
        
        $stmt->execute([$emailId, $trackingToken, $senderEmail, $recipientEmail]);
        
        return $trackingToken;
        
    } catch (PDOException $e) {
        error_log("Tracking init error: " . $e->getMessage());
        return false;
    }
}

/**
 * Legacy tracking
 */
function initializeLegacyEmailTracking($sentEmailId, $senderEmail, $recipientEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;
        
        // Check if already has token
        $stmt = $pdo->prepare("SELECT tracking_token FROM sent_emails WHERE id = ?");
        $stmt->execute([$sentEmailId]);
        $sent = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($sent && $sent['tracking_token']) {
            $stmt = $pdo->prepare("SELECT id FROM email_read_tracking WHERE tracking_token = ?");
            $stmt->execute([$sent['tracking_token']]);
            if ($stmt->fetch()) {
                return $sent['tracking_token'];
            }
        }
        
        $trackingToken = generateTrackingToken();
        
        $stmt = $pdo->prepare("
            INSERT INTO email_read_tracking (
                email_id, tracking_token, sender_email, recipient_email,
                is_read, total_opens, valid_opens, created_at
            ) VALUES (0, ?, ?, ?, 0, 0, 0, NOW())
        ");
        
        $stmt->execute([$trackingToken, $senderEmail, $recipientEmail]);
        
        $stmt = $pdo->prepare("UPDATE sent_emails SET tracking_token = ? WHERE id = ?");
        $stmt->execute([$trackingToken, $sentEmailId]);
        
        return $trackingToken;
        
    } catch (PDOException $e) {
        error_log("Legacy tracking error: " . $e->getMessage());
        return false;
    }
}

/**
 * Parse user agent
 */
function parseUserAgent($userAgent) {
    $data = ['browser' => 'Unknown', 'os' => 'Unknown', 'device_type' => 'unknown'];
    
    if (empty($userAgent)) return $data;
    
    if (preg_match('/Windows/i', $userAgent)) {
        $data['os'] = 'Windows';
        $data['device_type'] = 'desktop';
    } elseif (preg_match('/Mac OS X/i', $userAgent)) {
        $data['os'] = 'macOS';
        $data['device_type'] = 'desktop';
    } elseif (preg_match('/iPhone/i', $userAgent)) {
        $data['os'] = 'iOS';
        $data['device_type'] = 'mobile';
    } elseif (preg_match('/Android/i', $userAgent)) {
        $data['os'] = 'Android';
        $data['device_type'] = 'mobile';
    }
    
    if (preg_match('/Chrome/i', $userAgent)) $data['browser'] = 'Chrome';
    elseif (preg_match('/Firefox/i', $userAgent)) $data['browser'] = 'Firefox';
    elseif (preg_match('/Safari/i', $userAgent)) $data['browser'] = 'Safari';
    
    if (preg_match('/bot|crawler|spider|imageproxy/i', $userAgent)) {
        $data['device_type'] = 'bot';
    }
    
    return $data;
}

/**
 * Check if sender open
 */
function isSenderOpen($senderEmail, $ipAddress, $userAgent) {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    return isset($_SESSION['smtp_user']) && $_SESSION['smtp_user'] === $senderEmail;
}

/**
 * Check if bot
 */
function isBotOpen($userAgent, $openDelay = null) {
    $botPatterns = ['/bot/i', '/crawler/i', '/spider/i', '/imageproxy/i', '/ggpht/i'];
    
    foreach ($botPatterns as $pattern) {
        if (preg_match($pattern, $userAgent)) return true;
    }
    
    return $openDelay !== null && $openDelay < 2;
}

/**
 * Check if proxy
 */
function isLikelyProxy($ipAddress) {
    return !filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
}
?>