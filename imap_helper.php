<?php
/**
 * IMAP Helper Functions
 * Handles IMAP connection and message fetching with duplicate prevention
 */

/**
 * Get IMAP configuration from session
 */
function getImapConfigFromSession() {
    if (!isset($_SESSION['imap_config'])) {
        return null;
    }
    
    $config = $_SESSION['imap_config'];
    
    // Validate required fields
    if (empty($config['imap_server']) || empty($config['imap_username']) || empty($config['imap_password'])) {
        return null;
    }
    
    return $config;
}

/**
 * Connect to IMAP server using session configuration
 */
function connectToImapFromSession() {
    $config = getImapConfigFromSession();
    
    if (!$config) {
        return ['success' => false, 'error' => 'IMAP configuration not found'];
    }
    
    try {
        $server = $config['imap_server'];
        $port = $config['imap_port'] ?? '993';
        $encryption = $config['imap_encryption'] ?? 'ssl';
        $username = $config['imap_username'];
        $password = $config['imap_password'];
        
        // Build mailbox string
        $mailbox = '{' . $server . ':' . $port . '/imap/' . $encryption . '}INBOX';
        
        // Attempt connection
        $connection = @imap_open($mailbox, $username, $password);
        
        if (!$connection) {
            $error = imap_last_error();
            error_log("IMAP connection failed: " . $error);
            return ['success' => false, 'error' => 'Could not connect to mail server: ' . $error];
        }
        
        return ['success' => true, 'connection' => $connection];
        
    } catch (Exception $e) {
        error_log("IMAP connection exception: " . $e->getMessage());
        return ['success' => false, 'error' => 'Connection error: ' . $e->getMessage()];
    }
}

/**
 * Get the last fetched message date from database for a user
 */
function getLastFetchedDate($userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            return null;
        }
        
        $stmt = $pdo->prepare("
            SELECT MAX(received_date) as last_date
            FROM inbox_messages
            WHERE user_email = :email
        ");
        $stmt->execute([':email' => $userEmail]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['last_date'] ?? null;
        
    } catch (Exception $e) {
        error_log("Error getting last fetched date: " . $e->getMessage());
        return null;
    }
}

/**
 * Check if message already exists in database
 */
function messageExists($userEmail, $messageId) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            return true; // Assume exists to prevent duplicates
        }
        
        $stmt = $pdo->prepare("
            SELECT id FROM inbox_messages
            WHERE user_email = :email AND message_id = :message_id
            LIMIT 1
        ");
        $stmt->execute([
            ':email' => $userEmail,
            ':message_id' => $messageId
        ]);
        
        return $stmt->fetch() !== false;
        
    } catch (Exception $e) {
        error_log("Error checking message existence: " . $e->getMessage());
        return true; // Assume exists to prevent duplicates
    }
}

/**
 * Save inbox message to database
 */
function saveInboxMessage($userEmail, $messageData) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) {
            return false;
        }
        
        // Check if message already exists
        if (messageExists($userEmail, $messageData['message_id'])) {
            return false; // Skip duplicate
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO inbox_messages (
                message_id,
                user_email,
                sender_email,
                sender_name,
                subject,
                body,
                received_date,
                fetched_at,
                is_read,
                has_attachments,
                attachment_data,
                is_starred,
                is_important,
                is_deleted
            ) VALUES (
                :message_id,
                :user_email,
                :sender_email,
                :sender_name,
                :subject,
                :body,
                :received_date,
                NOW(),
                0,
                :has_attachments,
                :attachment_data,
                0,
                0,
                0
            )
        ");
        
        $result = $stmt->execute([
            ':message_id' => $messageData['message_id'],
            ':user_email' => $userEmail,
            ':sender_email' => $messageData['sender_email'],
            ':sender_name' => $messageData['sender_name'],
            ':subject' => $messageData['subject'],
            ':body' => $messageData['body'],
            ':received_date' => $messageData['received_date'],
            ':has_attachments' => $messageData['has_attachments'] ?? 0,
            ':attachment_data' => $messageData['attachment_data'] ?? null
        ]);
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Error saving inbox message: " . $e->getMessage());
        return false;
    }
}

/**
 * Parse email address from header
 */
function parseEmailAddress($addressString) {
    if (empty($addressString)) {
        return ['email' => '', 'name' => ''];
    }
    
    // Try to parse email and name
    if (preg_match('/<(.+?)>/', $addressString, $matches)) {
        $email = $matches[1];
        $name = trim(str_replace('<' . $email . '>', '', $addressString));
        $name = trim($name, '"');
    } else {
        $email = trim($addressString);
        $name = '';
    }
    
    return ['email' => $email, 'name' => $name];
}

/**
 * Get message body (prefer plain text, fallback to HTML)
 */
function getMessageBody($connection, $messageNumber) {
    $body = '';
    
    // Try to get plain text body
    $structure = imap_fetchstructure($connection, $messageNumber);
    
    if (!isset($structure->parts)) {
        // Simple message (not multipart)
        $body = imap_body($connection, $messageNumber);
        
        // Decode if needed
        if ($structure->encoding == 3) { // Base64
            $body = base64_decode($body);
        } elseif ($structure->encoding == 4) { // Quoted-printable
            $body = quoted_printable_decode($body);
        }
    } else {
        // Multipart message
        foreach ($structure->parts as $partNum => $part) {
            // Look for text/plain
            if ($part->subtype == 'PLAIN') {
                $partBody = imap_fetchbody($connection, $messageNumber, $partNum + 1);
                
                if ($part->encoding == 3) {
                    $partBody = base64_decode($partBody);
                } elseif ($part->encoding == 4) {
                    $partBody = quoted_printable_decode($partBody);
                }
                
                $body = $partBody;
                break;
            }
        }
        
        // If no plain text, try HTML
        if (empty($body)) {
            foreach ($structure->parts as $partNum => $part) {
                if ($part->subtype == 'HTML') {
                    $partBody = imap_fetchbody($connection, $messageNumber, $partNum + 1);
                    
                    if ($part->encoding == 3) {
                        $partBody = base64_decode($partBody);
                    } elseif ($part->encoding == 4) {
                        $partBody = quoted_printable_decode($partBody);
                    }
                    
                    // Strip HTML tags
                    $body = strip_tags($partBody);
                    break;
                }
            }
        }
    }
    
    return $body;
}

/**
 * Check if message has attachments
 */
function hasAttachments($structure) {
    if (!isset($structure->parts)) {
        return false;
    }
    
    foreach ($structure->parts as $part) {
        if (isset($part->disposition) && strtolower($part->disposition) == 'attachment') {
            return true;
        }
        
        if (isset($part->parts)) {
            if (hasAttachments($part)) {
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Get attachment metadata
 */
function getAttachmentMetadata($connection, $messageNumber, $structure) {
    $attachments = [];
    
    if (!isset($structure->parts)) {
        return $attachments;
    }
    
    foreach ($structure->parts as $partNum => $part) {
        if (isset($part->disposition) && strtolower($part->disposition) == 'attachment') {
            if (isset($part->dparameters)) {
                foreach ($part->dparameters as $param) {
                    if (strtolower($param->attribute) == 'filename') {
                        $attachments[] = [
                            'filename' => $param->value,
                            'size' => $part->bytes ?? 0,
                            'type' => $part->subtype ?? 'UNKNOWN'
                        ];
                    }
                }
            }
        }
    }
    
    return $attachments;
}

/**
 * Fetch new messages from IMAP server using session configuration
 */
function fetchNewMessagesFromSession($userEmail, $limit = 50) {
    try {
        // Connect to IMAP
        $result = connectToImapFromSession();
        
        if (!$result['success']) {
            return $result;
        }
        
        $connection = $result['connection'];
        
        // Get message count
        $totalMessages = imap_num_msg($connection);
        
        if ($totalMessages == 0) {
            imap_close($connection);
            return [
                'success' => true,
                'message' => 'No messages in inbox',
                'count' => 0,
                'total' => 0
            ];
        }
        
        // Get last fetched date to avoid re-fetching old messages
        $lastFetchedDate = getLastFetchedDate($userEmail);
        
        // Start from most recent messages
        $startMessage = max(1, $totalMessages - $limit + 1);
        $newMessagesCount = 0;
        $skippedCount = 0;
        
        // Fetch messages in reverse order (newest first)
        for ($i = $totalMessages; $i >= $startMessage; $i--) {
            try {
                // Get message header
                $header = imap_headerinfo($connection, $i);
                
                if (!$header) {
                    continue;
                }
                
                // Get message ID
                $messageId = $header->message_id ?? 'msg_' . $i . '_' . time();
                
                // Check if already exists
                if (messageExists($userEmail, $messageId)) {
                    $skippedCount++;
                    continue;
                }
                
                // Parse sender
                $from = $header->from[0] ?? null;
                if (!$from) {
                    continue;
                }
                
                $senderEmail = isset($from->mailbox) && isset($from->host) 
                    ? $from->mailbox . '@' . $from->host 
                    : '';
                $senderName = $from->personal ?? $senderEmail;
                
                // Decode sender name if needed
                if (!empty($senderName)) {
                    $decoded = imap_mime_header_decode($senderName);
                    if (!empty($decoded)) {
                        $senderName = $decoded[0]->text;
                    }
                }
                
                // Get subject
                $subject = $header->subject ?? '(No Subject)';
                $decoded = imap_mime_header_decode($subject);
                if (!empty($decoded)) {
                    $subject = '';
                    foreach ($decoded as $element) {
                        $subject .= $element->text;
                    }
                }
                
                // Get received date
                $receivedDate = date('Y-m-d H:i:s', strtotime($header->date));
                
                // Skip if older than last fetched (optimization)
                if ($lastFetchedDate && $receivedDate <= $lastFetchedDate) {
                    continue;
                }
                
                // Get message body
                $body = getMessageBody($connection, $i);
                
                // Get structure for attachments
                $structure = imap_fetchstructure($connection, $i);
                $hasAttachmentsFlag = hasAttachments($structure);
                $attachmentData = null;
                
                if ($hasAttachmentsFlag) {
                    $attachments = getAttachmentMetadata($connection, $i, $structure);
                    if (!empty($attachments)) {
                        $attachmentData = json_encode($attachments);
                    }
                }
                
                // Prepare message data
                $messageData = [
                    'message_id' => $messageId,
                    'sender_email' => $senderEmail,
                    'sender_name' => $senderName,
                    'subject' => $subject,
                    'body' => $body,
                    'received_date' => $receivedDate,
                    'has_attachments' => $hasAttachmentsFlag ? 1 : 0,
                    'attachment_data' => $attachmentData
                ];
                
                // Save to database
                if (saveInboxMessage($userEmail, $messageData)) {
                    $newMessagesCount++;
                }
                
            } catch (Exception $e) {
                error_log("Error processing message $i: " . $e->getMessage());
                continue;
            }
        }
        
        // Close connection
        imap_close($connection);
        
        return [
            'success' => true,
            'message' => "Fetched $newMessagesCount new messages" . ($skippedCount > 0 ? " (skipped $skippedCount duplicates)" : ""),
            'count' => $newMessagesCount,
            'total' => $totalMessages
        ];
        
    } catch (Exception $e) {
        error_log("Error in fetchNewMessagesFromSession: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Failed to fetch messages: ' . $e->getMessage()
        ];
    }
}

/**
 * Get settings with defaults (stub - implement based on your settings system)
 */
function getSettingsWithDefaults($userEmail) {
    // This should be implemented based on your settings storage
    // For now, return empty array to use session config
    return [];
}
?>