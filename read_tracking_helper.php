<?php
/**
 * Email Read Tracking Helper
 * WhatsApp-style read receipts with analytics
 */

require_once 'db_config.php';

/**
 * Generate unique tracking token
 */
function generateTrackingToken() {
    return bin2hex(random_bytes(32)); // 64 character hex string
}

/**
 * Insert tracking record when email is sent
 */
function initializeEmailTracking($emailId, $senderEmail, $recipientEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;
        
        $trackingToken = generateTrackingToken();
        
        // Insert into email_read_tracking
        $stmt = $pdo->prepare("
            INSERT INTO email_read_tracking (
                email_id, tracking_token, sender_email, recipient_email,
                is_read, total_opens, valid_opens, created_at
            ) VALUES (?, ?, ?, ?, 0, 0, 0, NOW())
        ");
        
        $result = $stmt->execute([
            $emailId,
            $trackingToken,
            $senderEmail,
            $recipientEmail
        ]);
        
        if ($result) {
            // Update emails table with tracking token
            $updateStmt = $pdo->prepare("UPDATE emails SET tracking_token = ? WHERE id = ?");
            $updateStmt->execute([$trackingToken, $emailId]);
            
            return $trackingToken;
        }
        
        return false;
        
    } catch (PDOException $e) {
        error_log("Error initializing email tracking: " . $e->getMessage());
        return false;
    }
}

/**
 * Initialize tracking for legacy sent_emails table
 */
function initializeLegacyEmailTracking($sentEmailId, $senderEmail, $recipientEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;
        
        $trackingToken = generateTrackingToken();
        
        // Update sent_emails with tracking token
        $stmt = $pdo->prepare("
            UPDATE sent_emails 
            SET tracking_token = ? 
            WHERE id = ?
        ");
        
        $stmt->execute([$trackingToken, $sentEmailId]);
        
        // Create tracking record (using negative ID to avoid conflicts)
        $stmt = $pdo->prepare("
            INSERT INTO email_read_tracking (
                email_id, tracking_token, sender_email, recipient_email,
                is_read, total_opens, valid_opens, created_at
            ) VALUES (?, ?, ?, ?, 0, 0, 0, NOW())
        ");
        
        $stmt->execute([
            -$sentEmailId, // Negative to distinguish legacy emails
            $trackingToken,
            $senderEmail,
            $recipientEmail
        ]);
        
        return $trackingToken;
        
    } catch (PDOException $e) {
        error_log("Error initializing legacy email tracking: " . $e->getMessage());
        return false;
    }
}

/**
 * Get tracking pixel HTML
 */
function getTrackingPixelHtml($trackingToken) {
    $baseUrl = getBaseUrl();
    $pixelUrl = $baseUrl . "/track_pixel.php?t=" . urlencode($trackingToken);
    
    // Invisible 1x1 transparent pixel
    return '<img src="' . htmlspecialchars($pixelUrl) . '" width="1" height="1" style="display:none !important; visibility:hidden !important; opacity:0 !important; position:absolute !important; width:1px !important; height:1px !important;" alt="" />';
}

/**
 * Inject tracking pixel at end of email body
 */
function injectTrackingPixel($emailBody, $trackingToken) {
    $pixelHtml = getTrackingPixelHtml($trackingToken);
    
    // If HTML email, insert before closing body tag
    if (stripos($emailBody, '</body>') !== false) {
        return str_ireplace('</body>', $pixelHtml . '</body>', $emailBody);
    }
    
    // Otherwise append at end
    return $emailBody . $pixelHtml;
}

/**
 * Get base URL for tracking pixel
 */
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . '://' . $host;
}

/**
 * Parse user agent to detect browser, OS, device
 */
function parseUserAgent($userAgent) {
    $result = [
        'browser' => 'Unknown',
        'os' => 'Unknown',
        'device_type' => 'unknown'
    ];
    
    if (empty($userAgent)) {
        return $result;
    }
    
    // Detect bots
    $botPatterns = [
        'bot', 'crawler', 'spider', 'scraper', 'curl', 'wget',
        'googlebot', 'bingbot', 'facebookexternalhit', 'twitterbot'
    ];
    
    foreach ($botPatterns as $pattern) {
        if (stripos($userAgent, $pattern) !== false) {
            $result['device_type'] = 'bot';
            $result['browser'] = 'Bot/Crawler';
            return $result;
        }
    }
    
    // Detect mobile vs desktop
    if (preg_match('/mobile|android|iphone|ipad|ipod|blackberry|iemobile|opera mini/i', $userAgent)) {
        if (preg_match('/ipad|tablet|playbook/i', $userAgent)) {
            $result['device_type'] = 'tablet';
        } else {
            $result['device_type'] = 'mobile';
        }
    } else {
        $result['device_type'] = 'desktop';
    }
    
    // Detect browser
    if (preg_match('/MSIE|Trident/i', $userAgent)) {
        $result['browser'] = 'Internet Explorer';
    } elseif (preg_match('/Edge/i', $userAgent)) {
        $result['browser'] = 'Microsoft Edge';
    } elseif (preg_match('/Chrome/i', $userAgent)) {
        $result['browser'] = 'Google Chrome';
    } elseif (preg_match('/Safari/i', $userAgent)) {
        $result['browser'] = 'Safari';
    } elseif (preg_match('/Firefox/i', $userAgent)) {
        $result['browser'] = 'Mozilla Firefox';
    } elseif (preg_match('/Opera|OPR/i', $userAgent)) {
        $result['browser'] = 'Opera';
    }
    
    // Detect OS
    if (preg_match('/Windows NT 10/i', $userAgent)) {
        $result['os'] = 'Windows 10/11';
    } elseif (preg_match('/Windows NT 6.3/i', $userAgent)) {
        $result['os'] = 'Windows 8.1';
    } elseif (preg_match('/Windows NT 6.2/i', $userAgent)) {
        $result['os'] = 'Windows 8';
    } elseif (preg_match('/Windows NT 6.1/i', $userAgent)) {
        $result['os'] = 'Windows 7';
    } elseif (preg_match('/Windows/i', $userAgent)) {
        $result['os'] = 'Windows';
    } elseif (preg_match('/Mac OS X/i', $userAgent)) {
        $result['os'] = 'macOS';
    } elseif (preg_match('/iPhone OS/i', $userAgent)) {
        $result['os'] = 'iOS';
    } elseif (preg_match('/Android/i', $userAgent)) {
        $result['os'] = 'Android';
    } elseif (preg_match('/Linux/i', $userAgent)) {
        $result['os'] = 'Linux';
    }
    
    return $result;
}

/**
 * Detect if IP is likely a proxy/VPN
 * Basic detection - can be enhanced with external services
 */
function isLikelyProxy($ip) {
    // Known proxy/VPN IP ranges (simplified)
    $proxyPatterns = [
        '/^10\./',          // Private Class A
        '/^172\.(1[6-9]|2[0-9]|3[0-1])\./', // Private Class B
        '/^192\.168\./',    // Private Class C
        '/^127\./',         // Loopback
    ];
    
    foreach ($proxyPatterns as $pattern) {
        if (preg_match($pattern, $ip)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Check if open is from sender (prevent false reads)
 */
function isSenderOpen($senderEmail, $ipAddress, $userAgent) {
    // Check if there's an active session for sender
    session_start();
    if (isset($_SESSION['smtp_user']) && $_SESSION['smtp_user'] === $senderEmail) {
        return true;
    }
    
    // Additional checks can be added here
    // - Compare IP with sender's known IPs
    // - Check timing (opens within seconds of sending)
    
    return false;
}

/**
 * Detect bot/prefetch opens
 */
function isBotOpen($userAgent, $openDelay) {
    // User agent indicates bot
    $userAgentData = parseUserAgent($userAgent);
    if ($userAgentData['device_type'] === 'bot') {
        return true;
    }
    
    // Instant opens (< 2 seconds) are likely prefetch
    if ($openDelay !== null && $openDelay < 2) {
        return true;
    }
    
    return false;
}

/**
 * Get read status for an email
 */
function getEmailReadStatus($trackingToken) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return null;
        
        $stmt = $pdo->prepare("
            SELECT 
                is_read,
                first_read_at,
                total_opens,
                valid_opens,
                ip_address,
                browser,
                os,
                device_type,
                country,
                city
            FROM email_read_tracking
            WHERE tracking_token = ?
        ");
        
        $stmt->execute([$trackingToken]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error getting read status: " . $e->getMessage());
        return null;
    }
}

/**
 * Get all read statuses for sender's emails
 */
function getSenderEmailsReadStatus($senderEmail, $limit = 100, $offset = 0) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return [];
        
        $stmt = $pdo->prepare("
            SELECT 
                email_id,
                tracking_token,
                recipient_email,
                is_read,
                first_read_at,
                total_opens,
                valid_opens,
                device_type,
                browser,
                os
            FROM email_read_tracking
            WHERE sender_email = ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        
        $stmt->bindValue(1, $senderEmail, PDO::PARAM_STR);
        $stmt->bindValue(2, (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(3, (int)$offset, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error getting sender read statuses: " . $e->getMessage());
        return [];
    }
}

/**
 * Get read status for legacy sent_emails
 */
function getLegacyEmailReadStatus($sentEmailId) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return null;
        
        // First check sent_emails table
        $stmt = $pdo->prepare("
            SELECT is_read, first_read_at, tracking_token
            FROM sent_emails
            WHERE id = ?
        ");
        
        $stmt->execute([$sentEmailId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error getting legacy read status: " . $e->getMessage());
        return null;
    }
}

/**
 * Get unread count for sender
 */
function getUnreadSentCount($senderEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return 0;
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM email_read_tracking
            WHERE sender_email = ? AND is_read = 0
        ");
        
        $stmt->execute([$senderEmail]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] ?? 0;
        
    } catch (PDOException $e) {
        error_log("Error getting unread sent count: " . $e->getMessage());
        return 0;
    }
}
?>