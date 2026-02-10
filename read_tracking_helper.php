<?php
/**
 * Read Tracking Helper Functions - FIXED VERSION
 * Handles both emails and sent_emails tables
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
    // return 'https://yourdomain.com';
    
    // OPTION 2: Auto-detect (may fail behind load balancers/proxies)
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Remove port if it's standard
    if (($protocol === 'https' && strpos($host, ':443') !== false) ||
        ($protocol === 'http' && strpos($host, ':80') !== false)) {
        $host = preg_replace('/:\d+$/', '', $host);
    }
    
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
 * Initialize email tracking record for EMAILS table
 * Creates a tracking record in email_read_tracking table
 */
function initializeEmailTracking($emailId, $senderEmail, $recipientEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            error_log("Failed to get database connection in initializeEmailTracking");
            return false;
        }
        
        // Verify the email exists in emails table
        $stmt = $pdo->prepare("SELECT id FROM emails WHERE id = ?");
        $stmt->execute([$emailId]);
        if (!$stmt->fetch()) {
            error_log("Email ID $emailId does not exist in emails table - cannot create tracking record");
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
 * Initialize tracking for SENT_EMAILS table (legacy)
 * This creates a dummy email record in emails table first to satisfy foreign key
 */
function initializeLegacyEmailTracking($sentEmailId, $senderEmail, $recipientEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            error_log("Failed to get database connection in initializeLegacyEmailTracking");
            return false;
        }
        
        // Check if sent_email exists
        $stmt = $pdo->prepare("SELECT id, subject, message_body FROM sent_emails WHERE id = ?");
        $stmt->execute([$sentEmailId]);
        $sentEmail = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$sentEmail) {
            error_log("Sent email ID $sentEmailId not found in sent_emails table");
            return false;
        }
        
        // Check if we already created a corresponding email record
        $stmt = $pdo->prepare("
            SELECT e.id, ert.tracking_token 
            FROM emails e
            LEFT JOIN email_read_tracking ert ON e.id = ert.email_id
            WHERE e.message_id = ?
        ");
        
        $legacyMessageId = "legacy_sent_email_{$sentEmailId}";
        $stmt->execute([$legacyMessageId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Already have tracking for this sent_email
            if ($existing['tracking_token']) {
                return $existing['tracking_token'];
            }
            // Has email record but no tracking, create tracking
            return initializeEmailTracking($existing['id'], $senderEmail, $recipientEmail);
        }
        
        // Create a corresponding record in emails table
        $stmt = $pdo->prepare("
            INSERT INTO emails (
                email_uuid,
                tracking_token,
                message_id,
                sender_email,
                recipient_email,
                subject,
                body_html,
                email_type,
                email_date,
                sent_at,
                created_at
            ) VALUES (
                UUID(),
                NULL,
                ?,
                ?,
                ?,
                ?,
                ?,
                'sent',
                NOW(),
                NOW(),
                NOW()
            )
        ");
        
        $stmt->execute([
            $legacyMessageId,
            $senderEmail,
            $recipientEmail,
            $sentEmail['subject'] ?? 'Legacy Email',
            $sentEmail['message_body'] ?? ''
        ]);
        
        $newEmailId = $pdo->lastInsertId();
        
        error_log("Created email record ID $newEmailId for legacy sent_email ID $sentEmailId");
        
        // Now create tracking for this new email record
        $trackingToken = initializeEmailTracking($newEmailId, $senderEmail, $recipientEmail);
        
        // Update sent_emails table with tracking token
        if ($trackingToken) {
            $stmt = $pdo->prepare("UPDATE sent_emails SET tracking_token = ? WHERE id = ?");
            $stmt->execute([$trackingToken, $sentEmailId]);
        }
        
        return $trackingToken;
        
    } catch (PDOException $e) {
        error_log("Error initializing legacy email tracking: " . $e->getMessage());
        return false;
    }
}

/**
 * ALTERNATIVE: Initialize tracking WITHOUT foreign key requirement
 * This modifies the tracking record to use a special email_id = 0 for legacy emails
 * 
 * WARNING: This requires removing or modifying the foreign key constraint!
 */
function initializeLegacyEmailTrackingNoFK($sentEmailId, $senderEmail, $recipientEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            error_log("Failed to get database connection");
            return false;
        }
        
        // Check if tracking already exists for this sent_email
        // Using negative email_id to indicate it's from sent_emails table
        $legacyEmailId = -abs($sentEmailId);
        
        $stmt = $pdo->prepare("SELECT tracking_token FROM email_read_tracking WHERE email_id = ?");
        $stmt->execute([$legacyEmailId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            return $existing['tracking_token'];
        }
        
        // Generate new tracking token
        $trackingToken = generateTrackingToken();
        
        // Insert tracking record with negative ID (requires FK to be removed/modified)
        $stmt = $pdo->prepare("
            INSERT INTO email_read_tracking (
                email_id, tracking_token, sender_email, recipient_email,
                is_read, total_opens, valid_opens, created_at
            ) VALUES (?, ?, ?, ?, 0, 0, 0, NOW())
        ");
        
        $stmt->execute([$legacyEmailId, $trackingToken, $senderEmail, $recipientEmail]);
        
        // Update sent_emails table with tracking token
        $stmt = $pdo->prepare("UPDATE sent_emails SET tracking_token = ? WHERE id = ?");
        $stmt->execute([$trackingToken, $sentEmailId]);
        
        error_log("Initialized legacy tracking for sent_email ID: $sentEmailId, token: $trackingToken");
        
        return $trackingToken;
        
    } catch (PDOException $e) {
        error_log("Error initializing legacy tracking (no FK): " . $e->getMessage());
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
 * Legacy support - get by sent_emails ID
 */
function getLegacyEmailReadStatus($sentEmailId) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return null;
        
        // First try to find via message_id
        $legacyMessageId = "legacy_sent_email_{$sentEmailId}";
        $stmt = $pdo->prepare("
            SELECT ert.* 
            FROM email_read_tracking ert
            JOIN emails e ON ert.email_id = e.id
            WHERE e.message_id = ?
        ");
        $stmt->execute([$legacyMessageId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return $result;
        }
        
        // If not found, try negative ID approach
        return getEmailReadStatus(-abs($sentEmailId));
        
    } catch (PDOException $e) {
        error_log("Error getting legacy read status: " . $e->getMessage());
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
    
    return false;
}
?>