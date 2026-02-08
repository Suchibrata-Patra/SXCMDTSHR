<?php
/**
 * IMAP Helper Functions
 * Fetches emails from IMAP server and stores them in database
 */

require_once 'db_config.php';
require_once 'settings_helper.php';

/**
 * Connect to IMAP server
 * 
 * @param string $server IMAP server address
 * @param int $port IMAP port (usually 993 for SSL)
 * @param string $email User email
 * @param string $password User password
 * @return resource|false IMAP connection or false on failure
 */
function connectToIMAP($server, $port, $email, $password) {
    $mailbox = "{" . $server . ":" . $port . "/imap/ssl}INBOX";
    
    try {
        // Suppress IMAP warnings and handle them manually
        $connection = @imap_open($mailbox, $email, $password);
        
        if (!$connection) {
            $error = imap_last_error();
            error_log("IMAP connection failed: " . $error);
            return false;
        }
        
        return $connection;
    } catch (Exception $e) {
        error_log("IMAP connection exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Fetch new messages from IMAP server
 * 
 * @param string $userEmail User's email address
 * @param array $imapConfig IMAP configuration (server, port, password)
 * @param int $limit Maximum messages to fetch per sync (default 50)
 * @return array Result with status and message count
 */
function fetchNewMessages($userEmail, $imapConfig, $limit = 50) {
    // Get IMAP connection details
    $server = $imapConfig['imap_server'] ?? 'imap.gmail.com';
    $port = $imapConfig['imap_port'] ?? 993;
    $password = $imapConfig['imap_password'] ?? '';
    
    if (empty($password)) {
        return [
            'success' => false,
            'error' => 'IMAP password not configured',
            'count' => 0
        ];
    }
    
    // Connect to IMAP
    $connection = connectToIMAP($server, $port, $userEmail, $password);
    
    if (!$connection) {
        return [
            'success' => false,
            'error' => 'Could not connect to IMAP server',
            'count' => 0
        ];
    }
    
    try {
        // Get total number of messages
        $totalMessages = imap_num_msg($connection);
        
        if ($totalMessages === 0) {
            imap_close($connection);
            return [
                'success' => true,
                'message' => 'No messages in inbox',
                'count' => 0
            ];
        }
        
        // Get last sync date to determine which messages to fetch
        $lastSyncDate = getLastSyncDate($userEmail);
        
        // Fetch most recent messages (up to limit)
        $startMsg = max(1, $totalMessages - $limit + 1);
        $endMsg = $totalMessages;
        
        $newMessagesCount = 0;
        $lastMessageId = null;
        
        // Fetch messages in reverse order (newest first)
        for ($msgNum = $endMsg; $msgNum >= $startMsg; $msgNum--) {
            try {
                // Get message overview
                $overview = imap_fetch_overview($connection, $msgNum, 0);
                
                if (empty($overview)) {
                    continue;
                }
                
                $info = $overview[0];
                
                // Get unique message ID
                $messageId = $info->message_id ?? 'msg-' . $msgNum . '-' . time();
                $lastMessageId = $messageId;
                
                // Skip if message date is before last sync (optimization)
                if ($lastSyncDate && isset($info->date)) {
                    $messageDate = strtotime($info->date);
                    $lastSync = strtotime($lastSyncDate);
                    
                    if ($messageDate <= $lastSync) {
                        continue; // Skip older messages
                    }
                }
                
                // Extract sender information
                $from = $info->from ?? 'Unknown';
                $senderEmail = extractEmail($from);
                $senderName = extractName($from);
                
                // Get subject
                $subject = isset($info->subject) ? imap_utf8($info->subject) : '(No Subject)';
                
                // Get message body
                $body = getMessageBody($connection, $msgNum);
                
                // Check for attachments
                $hasAttachments = hasAttachments($connection, $msgNum);
                
                // Get received date
                $receivedDate = isset($info->date) ? date('Y-m-d H:i:s', strtotime($info->date)) : date('Y-m-d H:i:s');
                
                // Save to database
                $messageData = [
                    'message_id' => $messageId,
                    'user_email' => $userEmail,
                    'sender_email' => $senderEmail,
                    'sender_name' => $senderName,
                    'subject' => $subject,
                    'body' => $body,
                    'received_date' => $receivedDate,
                    'has_attachments' => $hasAttachments ? 1 : 0
                ];
                
                if (saveInboxMessage($messageData)) {
                    $newMessagesCount++;
                }
                
            } catch (Exception $e) {
                error_log("Error processing message $msgNum: " . $e->getMessage());
                continue;
            }
        }
        
        // Update last sync timestamp
        updateLastSyncDate($userEmail, $lastMessageId);
        
        imap_close($connection);
        
        return [
            'success' => true,
            'message' => "Fetched $newMessagesCount new messages",
            'count' => $newMessagesCount,
            'total' => $totalMessages
        ];
        
    } catch (Exception $e) {
        error_log("Error fetching messages: " . $e->getMessage());
        imap_close($connection);
        
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'count' => 0
        ];
    }
}

/**
 * Get message body (prioritizes plain text, falls back to HTML)
 * 
 * @param resource $connection IMAP connection
 * @param int $msgNum Message number
 * @return string Message body
 */
function getMessageBody($connection, $msgNum) {
    $body = '';
    
    try {
        $structure = imap_fetchstructure($connection, $msgNum);
        
        // Check if multipart message
        if (isset($structure->parts) && count($structure->parts)) {
            // Multipart message
            for ($i = 0; $i < count($structure->parts); $i++) {
                $part = $structure->parts[$i];
                
                // Plain text
                if ($part->subtype === 'PLAIN') {
                    $body = imap_fetchbody($connection, $msgNum, $i + 1);
                    $body = decodeBody($body, $part->encoding);
                    break;
                }
                
                // HTML (fallback)
                if ($part->subtype === 'HTML' && empty($body)) {
                    $body = imap_fetchbody($connection, $msgNum, $i + 1);
                    $body = decodeBody($body, $part->encoding);
                    $body = strip_tags($body); // Convert HTML to plain text
                }
            }
        } else {
            // Simple message
            $body = imap_body($connection, $msgNum);
            if (isset($structure->encoding)) {
                $body = decodeBody($body, $structure->encoding);
            }
        }
        
    } catch (Exception $e) {
        error_log("Error getting message body: " . $e->getMessage());
        $body = imap_body($connection, $msgNum);
    }
    
    return trim($body);
}

/**
 * Decode message body based on encoding type
 * 
 * @param string $body Encoded body
 * @param int $encoding Encoding type
 * @return string Decoded body
 */
function decodeBody($body, $encoding) {
    switch ($encoding) {
        case 0: // 7BIT
        case 1: // 8BIT
        case 2: // BINARY
            return $body;
        case 3: // BASE64
            return base64_decode($body);
        case 4: // QUOTED-PRINTABLE
            return quoted_printable_decode($body);
        case 5: // OTHER
            return $body;
        default:
            return $body;
    }
}

/**
 * Check if message has attachments
 * 
 * @param resource $connection IMAP connection
 * @param int $msgNum Message number
 * @return bool True if has attachments
 */
function hasAttachments($connection, $msgNum) {
    try {
        $structure = imap_fetchstructure($connection, $msgNum);
        
        if (!isset($structure->parts) || !count($structure->parts)) {
            return false;
        }
        
        foreach ($structure->parts as $part) {
            if (isset($part->disposition) && 
                (strtolower($part->disposition) === 'attachment' || 
                 strtolower($part->disposition) === 'inline')) {
                return true;
            }
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Error checking attachments: " . $e->getMessage());
        return false;
    }
}

/**
 * Extract email address from "From" header
 * 
 * @param string $from From header string
 * @return string Email address
 */
function extractEmail($from) {
    if (preg_match('/<([^>]+)>/', $from, $matches)) {
        return $matches[1];
    }
    return $from;
}

/**
 * Extract sender name from "From" header
 * 
 * @param string $from From header string
 * @return string Sender name
 */
function extractName($from) {
    if (preg_match('/^([^<]+)</', $from, $matches)) {
        return trim($matches[1], ' "');
    }
    return '';
}

/**
 * Quick sync check - returns unread count without full fetch
 * 
 * @param string $userEmail User's email
 * @param array $imapConfig IMAP configuration
 * @return array Status with unread count
 */
function quickSyncCheck($userEmail, $imapConfig) {
    $server = $imapConfig['imap_server'] ?? 'imap.gmail.com';
    $port = $imapConfig['imap_port'] ?? 993;
    $password = $imapConfig['imap_password'] ?? '';
    
    if (empty($password)) {
        return ['success' => false, 'unread' => 0];
    }
    
    $connection = connectToIMAP($server, $port, $userEmail, $password);
    
    if (!$connection) {
        return ['success' => false, 'unread' => 0];
    }
    
    try {
        $check = imap_check($connection);
        $unread = imap_num_recent($connection);
        
        imap_close($connection);
        
        return [
            'success' => true,
            'total' => $check->Nmsgs,
            'unread' => $unread
        ];
    } catch (Exception $e) {
        imap_close($connection);
        return ['success' => false, 'unread' => 0];
    }
}
?>
