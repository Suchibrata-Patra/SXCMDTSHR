<?php
/**
 * Email Sending with Tracking Pixel Injection
 * Wrapper around PHPMailer to add read receipt tracking
 */

require_once 'db_config.php';
require_once 'read_tracking_helper.php';

/**
 * Send email with tracking pixel
 * Call this AFTER your existing PHPMailer send logic
 * 
 * @param int $sentEmailId - ID from sent_emails table
 * @param string $senderEmail - Sender's email address
 * @param string $recipientEmail - Recipient's email address
 * @param string $emailBody - HTML email body
 * @param PHPMailer $mail - PHPMailer instance (optional, for modifying before send)
 * @return string|false - Returns tracking token on success, false on failure
 */
function sendEmailWithTracking($sentEmailId, $senderEmail, $recipientEmail, $emailBody, &$mail = null) {
    try {
        // Initialize tracking record
        $trackingToken = initializeLegacyEmailTracking($sentEmailId, $senderEmail, $recipientEmail);
        
        if (!$trackingToken) {
            error_log("Failed to initialize tracking for email ID: $sentEmailId");
            return false;
        }
        
        // Inject tracking pixel into email body
        $trackedBody = injectTrackingPixel($emailBody, $trackingToken);
        
        // If PHPMailer instance provided, update its body
        if ($mail !== null && is_object($mail)) {
            $mail->Body = $trackedBody;
        }
        
        return [
            'tracking_token' => $trackingToken,
            'tracked_body' => $trackedBody
        ];
        
    } catch (Exception $e) {
        error_log("Error in sendEmailWithTracking: " . $e->getMessage());
        return false;
    }
}

/**
 * Add tracking to already-sent email
 * Use this to retrofit tracking on existing sent_emails records
 * 
 * @param int $sentEmailId
 * @param string $senderEmail
 * @param string $recipientEmail
 * @return string|false
 */
function addTrackingToSentEmail($sentEmailId, $senderEmail, $recipientEmail) {
    try {
        // Check if tracking already exists
        $existingTracking = getLegacyEmailReadStatus($sentEmailId);
        
        if ($existingTracking && !empty($existingTracking['tracking_token'])) {
            return $existingTracking['tracking_token'];
        }
        
        // Initialize tracking
        $trackingToken = initializeLegacyEmailTracking($sentEmailId, $senderEmail, $recipientEmail);
        
        return $trackingToken;
        
    } catch (Exception $e) {
        error_log("Error adding tracking to sent email: " . $e->getMessage());
        return false;
    }
}

/**
 * Batch add tracking to existing sent emails
 * Run this ONCE to add tracking to all previously sent emails
 */
function batchAddTrackingToSentEmails($senderEmail = null, $limit = 100) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return ['success' => false, 'error' => 'Database connection failed'];
        
        // Query sent emails without tracking
        $sql = "SELECT id, sender_email, recipient_email 
                FROM sent_emails 
                WHERE tracking_token IS NULL 
                AND current_status = 1";
        
        if ($senderEmail) {
            $sql .= " AND sender_email = :email";
        }
        
        $sql .= " LIMIT :limit";
        
        $stmt = $pdo->prepare($sql);
        
        if ($senderEmail) {
            $stmt->bindValue(':email', $senderEmail, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        
        $stmt->execute();
        $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $successCount = 0;
        $failCount = 0;
        
        foreach ($emails as $email) {
            $result = addTrackingToSentEmail(
                $email['id'],
                $email['sender_email'],
                $email['recipient_email']
            );
            
            if ($result) {
                $successCount++;
            } else {
                $failCount++;
            }
        }
        
        return [
            'success' => true,
            'processed' => count($emails),
            'success_count' => $successCount,
            'fail_count' => $failCount
        ];
        
    } catch (PDOException $e) {
        error_log("Error in batch tracking: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Example integration with existing PHPMailer code:
 * 
 * BEFORE (existing code):
 * -----------------------
 * $mail = new PHPMailer();
 * $mail->setFrom($senderEmail);
 * $mail->addAddress($recipientEmail);
 * $mail->Subject = $subject;
 * $mail->Body = $emailBody;
 * $mail->send();
 * 
 * // Save to database
 * $sentEmailId = saveSentEmail($senderEmail, $recipientEmail, $subject, $emailBody);
 * 
 * 
 * AFTER (with tracking):
 * ----------------------
 * $mail = new PHPMailer();
 * $mail->setFrom($senderEmail);
 * $mail->addAddress($recipientEmail);
 * $mail->Subject = $subject;
 * 
 * // First save email to get ID
 * $sentEmailId = saveSentEmail($senderEmail, $recipientEmail, $subject, $emailBody);
 * 
 * // Add tracking pixel
 * $tracking = sendEmailWithTracking($sentEmailId, $senderEmail, $recipientEmail, $emailBody, $mail);
 * 
 * if ($tracking) {
 *     // Body now has tracking pixel
 *     $mail->send();
 * }
 * 
 */
?>