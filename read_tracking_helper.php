<?php
/**
 * Read Tracking Helper Functions - FIXED VERSION
 * CRITICAL FIX: Prevents double pixel injection
 */

/**
 * Generate unique tracking token
 */
function generateTrackingToken() {
    return bin2hex(random_bytes(32)); // 64 character hex string
}

/**
 * Get base URL for tracking pixel
 */
function getBaseUrl() {
    // HARDCODED URL - SET THIS TO YOUR ACTUAL DOMAIN
    return 'https://hr.holidayseva.com';
    
    // Alternative auto-detect (comment out above line to use this)
    /*
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    if (($protocol === 'https' && strpos($host, ':443') !== false) ||
        ($protocol === 'http' && strpos($host, ':80') !== false)) {
        $host = preg_replace('/:\d+$/', '', $host);
    }
    
    return $protocol . '://' . $host;
    */
}

/**
 * Build tracking pixel URL
 */
function buildTrackingPixelUrl($trackingToken) {
    $baseUrl = getBaseUrl();
    return $baseUrl . '/track_pixel.php?t=' . urlencode($trackingToken);
}

/**
 * FIXED: Inject tracking pixel into email body
 * PREVENTS DOUBLE INJECTION
 */
function injectTrackingPixel($emailBody, $trackingToken) {
    // First check if a tracking pixel already exists
    if (preg_match('/track_pixel\.php\?t=/i', $emailBody)) {
        error_log("WARNING: Tracking pixel already exists in email body - skipping injection");
        return $emailBody; // Already has tracking pixel, don't add another
    }
    
    $pixelUrl = buildTrackingPixelUrl($trackingToken);
    
    // Build the tracking pixel HTML
    // Use proper HTML attributes and make it truly invisible
    $trackingPixel = sprintf(
        '<img src="%s" alt="" width="1" height="1" border="0" style="display:block !important; width:1px !important; height:1px !important; min-width:1px !important; max-width:1px !important; min-height:1px !important; max-height:1px !important; opacity:0 !important; visibility:hidden !important; border:0 !important; padding:0 !important; margin:0 !important;" />',
        htmlspecialchars($pixelUrl, ENT_QUOTES, 'UTF-8')
    );
    
    // Try to inject before closing body tag (best practice)
    if (stripos($emailBody, '</body>') !== false) {
        return str_ireplace('</body>', $trackingPixel . "\n</body>", $emailBody);
    }
    
    // Try to inject before closing html tag
    if (stripos($emailBody, '</html>') !== false) {
        return str_ireplace('</html>', $trackingPixel . "\n</html>", $emailBody);
    }
    
    // No HTML structure found, append to end
    return $emailBody . "\n" . $trackingPixel;
}

/**
 * Initialize email tracking record for EMAILS table
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
            error_log("Tracking already exists for email ID $emailId, returning existing token");
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
        
        error_log("Initialized tracking for email ID: $emailId, token: $trackingToken");
        
        return $trackingToken;
        
    } catch (PDOException $e) {
        error_log("Error initializing email tracking: " . $e->getMessage());
        return false;
    }
}

/**
 * SIMPLIFIED: Initialize tracking for SENT_EMAILS table
 * Uses email_id = 0 for legacy/test emails
 * 
 * NOTE: This requires that your email_read_tracking table does NOT have 
 * a strict foreign key constraint on email_id, OR email_id allows 0/NULL
 */
function initializeLegacyEmailTracking($sentEmailId, $senderEmail, $recipientEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            error_log("Failed to get database connection in initializeLegacyEmailTracking");
            return false;
        }
        
        // Check if sent_email exists
        $stmt = $pdo->prepare("SELECT id FROM sent_emails WHERE id = ?");
        $stmt->execute([$sentEmailId]);
        if (!$stmt->fetch()) {
            error_log("Sent email ID $sentEmailId not found in sent_emails table");
            return false;
        }
        
        // Check if tracking already exists (using email_id = 0 for legacy)
        // Each sent_email gets unique tracking_token, so we check by sent_email reference
        $stmt = $pdo->prepare("
            SELECT tracking_token 
            FROM email_read_tracking 
            WHERE sender_email = ? 
            AND recipient_email = ?
            AND email_id = 0
            AND created_at >= NOW() - INTERVAL 5 MINUTE
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$senderEmail, $recipientEmail]);
        $recent = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If we just created one in last 5 minutes for same sender/recipient, reuse it
        // This prevents double-creation during send process
        if ($recent) {
            error_log("Reusing recent tracking token for sent_email ID $sentEmailId");
            
            // Update sent_emails table
            $stmt = $pdo->prepare("UPDATE sent_emails SET tracking_token = ? WHERE id = ?");
            $stmt->execute([$recent['tracking_token'], $sentEmailId]);
            
            return $recent['tracking_token'];
        }
        
        // Generate new tracking token
        $trackingToken = generateTrackingToken();
        
        // Insert tracking record with email_id = 0 (legacy/test marker)
        $stmt = $pdo->prepare("
            INSERT INTO email_read_tracking (
                email_id, tracking_token, sender_email, recipient_email,
                is_read, total_opens, valid_opens, created_at
            ) VALUES (0, ?, ?, ?, 0, 0, 0, NOW())
        ");
        
        $stmt->execute([$trackingToken, $senderEmail, $recipientEmail]);
        
        // Update sent_emails table with tracking token
        $stmt = $pdo->prepare("UPDATE sent_emails SET tracking_token = ? WHERE id = ?");
        $stmt->execute([$trackingToken, $sentEmailId]);
        
        error_log("Initialized legacy tracking for sent_email ID: $sentEmailId, token: $trackingToken");
        
        return $trackingToken;
        
    } catch (PDOException $e) {
        error_log("Error initializing legacy email tracking: " . $e->getMessage());
        error_log("SQL Error: " . print_r($pdo->errorInfo(), true));
        return false;
    }
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
 * Get read status by tracking token
 */
function getReadStatusByToken($trackingToken) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return null;
        
        $stmt = $pdo->prepare("
            SELECT * FROM email_read_tracking WHERE tracking_token = ?
        ");
        $stmt->execute([$trackingToken]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error getting read status by token: " . $e->getMessage());
        return null;
    }
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
    if (preg_match('/Windows NT 10/i', $userAgent)) {
        $data['os'] = 'Windows 10/11';
        $data['device_type'] = 'desktop';
    } elseif (preg_match('/Windows NT/i', $userAgent)) {
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
    if (preg_match('/Edge/i', $userAgent)) {
        $data['browser'] = 'Edge';
    } elseif (preg_match('/Chrome/i', $userAgent)) {
        $data['browser'] = 'Chrome';
    } elseif (preg_match('/Firefox/i', $userAgent)) {
        $data['browser'] = 'Firefox';
    } elseif (preg_match('/Safari/i', $userAgent)) {
        $data['browser'] = 'Safari';
    } elseif (preg_match('/MSIE|Trident/i', $userAgent)) {
        $data['browser'] = 'Internet Explorer';
    } elseif (preg_match('/Outlook/i', $userAgent)) {
        $data['browser'] = 'Outlook';
    }
    
    // Detect bots
    if (preg_match('/bot|crawler|spider|scraper|imageproxy/i', $userAgent)) {
        $data['device_type'] = 'bot';
    }
    
    return $data;
}

/**
 * Check if open is from sender (testing their own email)
 */
function isSenderOpen($senderEmail, $ipAddress, $userAgent) {
    // Check if session matches sender
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    
    if (isset($_SESSION['smtp_user']) && $_SESSION['smtp_user'] === $senderEmail) {
        return true;
    }
    
    return false;
}

/**
 * Check if open is from bot/email scanner
 */
function isBotOpen($userAgent, $openDelay = null) {
    // Check user agent for bot patterns
    $botPatterns = [
        '/bot/i',
        '/crawler/i',
        '/spider/i',
        '/scanner/i',
        '/preview/i',
        '/prefetch/i',
        '/preload/i',
        '/curl/i',
        '/wget/i',
        '/python-requests/i',
        '/java\//i',
        '/imageproxy/i',
        '/ggpht\.com/i',
        '/yahoo.*slurp/i',
        '/microsoft.*office/i'
    ];
    
    foreach ($botPatterns as $pattern) {
        if (preg_match($pattern, $userAgent)) {
            return true;
        }
    }
    
    // Check if opened too quickly (less than 2 seconds = likely prefetch)
    if ($openDelay !== null && $openDelay < 2) {
        return true;
    }
    
    return false;
}

/**
 * Check if IP is likely a proxy/VPN
 */
function isLikelyProxy($ipAddress) {
    // Check for private/local IPs
    if (!filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return true;
    }
    
    return false;
}
?>