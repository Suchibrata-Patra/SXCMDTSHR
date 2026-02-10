<?php
/**
 * Read Tracking Helper - CACHE-PROOF VERSION
 * Adds cache-busting parameters to prevent Gmail Image Proxy caching
 */

/**
 * Generate unique tracking token
 */
function generateTrackingToken() {
    return bin2hex(random_bytes(32)); // 64 character hex string
}

/**
 * Get base URL
 */
function getBaseUrl() {
    // HARDCODED - CHANGE THIS TO YOUR DOMAIN
    return 'https://hr.holidayseva.com';
    
    /* Auto-detect alternative:
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . '://' . $host;
    */
}

/**
 * Build tracking pixel URL with cache-busting parameters
 * CRITICAL: Adds random and timestamp params to prevent caching
 */
function buildTrackingPixelUrl($trackingToken) {
    $baseUrl = getBaseUrl();
    
    // Add cache-busting parameters
    $params = [
        't' => $trackingToken,              // Tracking token
        'r' => bin2hex(random_bytes(8)),    // Random string (16 chars)
        'ts' => time()                       // Current timestamp
    ];
    
    $queryString = http_build_query($params);
    
    return $baseUrl . '/track_pixel.php?' . $queryString;
}

/**
 * Inject tracking pixel with CACHE-PROOF URL
 * This pixel will NOT be cached by Gmail Image Proxy
 */
function injectTrackingPixel($emailBody, $trackingToken) {
    // Check if pixel already exists
    if (preg_match('/track_pixel\.php\?/i', $emailBody)) {
        error_log("WARNING: Tracking pixel already exists in email - skipping injection");
        return $emailBody;
    }
    
    $pixelUrl = buildTrackingPixelUrl($trackingToken);
    
    // Build pixel HTML
    // Using style attribute for maximum email client compatibility
    $trackingPixel = sprintf(
        '<img src="%s" alt="" width="1" height="1" border="0" style="display:block;width:1px;height:1px;border:0;padding:0;margin:0;opacity:0;visibility:hidden;" />',
        htmlspecialchars($pixelUrl, ENT_QUOTES, 'UTF-8')
    );
    
    // Inject before closing body tag
    if (stripos($emailBody, '</body>') !== false) {
        return str_ireplace('</body>', $trackingPixel . "\n</body>", $emailBody);
    }
    
    // Inject before closing html tag
    if (stripos($emailBody, '</html>') !== false) {
        return str_ireplace('</html>', $trackingPixel . "\n</html>", $emailBody);
    }
    
    // Append to end
    return $emailBody . "\n" . $trackingPixel;
}

/**
 * Initialize email tracking
 */
function initializeEmailTracking($emailId, $senderEmail, $recipientEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            error_log("Database connection failed in initializeEmailTracking");
            return false;
        }
        
        // Check if tracking exists
        $stmt = $pdo->prepare("SELECT tracking_token FROM email_read_tracking WHERE email_id = ?");
        $stmt->execute([$emailId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            error_log("Tracking already exists for email $emailId");
            return $existing['tracking_token'];
        }
        
        // Generate token
        $trackingToken = generateTrackingToken();
        
        // Insert tracking record
        $stmt = $pdo->prepare("
            INSERT INTO email_read_tracking (
                email_id, tracking_token, sender_email, recipient_email,
                is_read, total_opens, valid_opens, created_at
            ) VALUES (?, ?, ?, ?, 0, 0, 0, NOW())
        ");
        
        $stmt->execute([$emailId, $trackingToken, $senderEmail, $recipientEmail]);
        
        error_log("Tracking initialized for email $emailId: token=$trackingToken");
        
        return $trackingToken;
        
    } catch (PDOException $e) {
        error_log("Error initializing tracking: " . $e->getMessage());
        return false;
    }
}

/**
 * Initialize tracking for sent_emails table (legacy)
 * Uses email_id = 0 for test/legacy emails
 */
function initializeLegacyEmailTracking($sentEmailId, $senderEmail, $recipientEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            error_log("Database connection failed in initializeLegacyEmailTracking");
            return false;
        }
        
        // Verify sent_email exists
        $stmt = $pdo->prepare("SELECT id FROM sent_emails WHERE id = ?");
        $stmt->execute([$sentEmailId]);
        if (!$stmt->fetch()) {
            error_log("Sent email $sentEmailId not found");
            return false;
        }
        
        // Check if sent_emails already has a tracking_token
        $stmt = $pdo->prepare("SELECT tracking_token FROM sent_emails WHERE id = ?");
        $stmt->execute([$sentEmailId]);
        $sentEmail = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($sentEmail && $sentEmail['tracking_token']) {
            // Check if this token exists in email_read_tracking
            $stmt = $pdo->prepare("SELECT id FROM email_read_tracking WHERE tracking_token = ?");
            $stmt->execute([$sentEmail['tracking_token']]);
            if ($stmt->fetch()) {
                error_log("Reusing existing tracking token for sent_email $sentEmailId");
                return $sentEmail['tracking_token'];
            }
        }
        
        // Generate new token
        $trackingToken = generateTrackingToken();
        
        // Insert into email_read_tracking with email_id = 0 (legacy marker)
        $stmt = $pdo->prepare("
            INSERT INTO email_read_tracking (
                email_id, tracking_token, sender_email, recipient_email,
                is_read, total_opens, valid_opens, created_at
            ) VALUES (0, ?, ?, ?, 0, 0, 0, NOW())
        ");
        
        $stmt->execute([$trackingToken, $senderEmail, $recipientEmail]);
        
        // Update sent_emails with token
        $stmt = $pdo->prepare("UPDATE sent_emails SET tracking_token = ? WHERE id = ?");
        $stmt->execute([$trackingToken, $sentEmailId]);
        
        error_log("Legacy tracking initialized for sent_email $sentEmailId: token=$trackingToken");
        
        return $trackingToken;
        
    } catch (PDOException $e) {
        error_log("Error in initializeLegacyEmailTracking: " . $e->getMessage());
        return false;
    }
}

/**
 * Get read status
 */
function getEmailReadStatus($emailId) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return null;
        
        $stmt = $pdo->prepare("SELECT * FROM email_read_tracking WHERE email_id = ?");
        $stmt->execute([$emailId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting read status: " . $e->getMessage());
        return null;
    }
}

/**
 * Parse user agent
 */
function parseUserAgent($userAgent) {
    $data = [
        'browser' => 'Unknown',
        'os' => 'Unknown',
        'device_type' => 'unknown'
    ];
    
    if (empty($userAgent)) return $data;
    
    // OS detection
    if (preg_match('/Windows NT 10/i', $userAgent)) {
        $data['os'] = 'Windows 10/11';
        $data['device_type'] = 'desktop';
    } elseif (preg_match('/Windows/i', $userAgent)) {
        $data['os'] = 'Windows';
        $data['device_type'] = 'desktop';
    } elseif (preg_match('/Mac OS X/i', $userAgent)) {
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
    
    // Browser detection
    if (preg_match('/Edge/i', $userAgent)) {
        $data['browser'] = 'Edge';
    } elseif (preg_match('/Chrome/i', $userAgent)) {
        $data['browser'] = 'Chrome';
    } elseif (preg_match('/Firefox/i', $userAgent)) {
        $data['browser'] = 'Firefox';
    } elseif (preg_match('/Safari/i', $userAgent)) {
        $data['browser'] = 'Safari';
    } elseif (preg_match('/Outlook/i', $userAgent)) {
        $data['browser'] = 'Outlook';
    }
    
    // Bot detection
    if (preg_match('/bot|crawler|spider|imageproxy/i', $userAgent)) {
        $data['device_type'] = 'bot';
    }
    
    return $data;
}

/**
 * Check if sender is opening own email
 */
function isSenderOpen($senderEmail, $ipAddress, $userAgent) {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    
    return isset($_SESSION['smtp_user']) && $_SESSION['smtp_user'] === $senderEmail;
}

/**
 * Check if bot/prefetch
 */
function isBotOpen($userAgent, $openDelay = null) {
    $botPatterns = [
        '/bot/i', '/crawler/i', '/spider/i', '/scanner/i',
        '/preview/i', '/prefetch/i', '/preload/i',
        '/imageproxy/i', '/ggpht\.com/i'
    ];
    
    foreach ($botPatterns as $pattern) {
        if (preg_match($pattern, $userAgent)) {
            return true;
        }
    }
    
    if ($openDelay !== null && $openDelay < 2) {
        return true;
    }
    
    return false;
}

/**
 * Check if proxy
 */
function isLikelyProxy($ipAddress) {
    return !filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
}
?>