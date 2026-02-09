<?php
/**
 * ENHANCED IMAP Helper Functions
 * Fetches emails from IMAP server with attachment handling and HTML stripping
 */

require_once 'db_config.php';
require_once 'settings_helper.php';

/**
 * Connect to IMAP server using session configuration
 */
function connectToIMAPFromSession() {
    $config = getImapConfigFromSession();
    
    if (!$config) {
        error_log("IMAP configuration not found in session");
        return false;
    }
    
    return connectToIMAP(
        $config['imap_server'],
        $config['imap_port'],
        $config['imap_username'],
        $config['imap_password']
    );
}

/**
 * Connect to IMAP server
 */
function connectToIMAP($server, $port, $email, $password) {
    $mailbox = "{" . $server . ":" . $port . "/imap/ssl}INBOX";
    
    try {
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
 * Fetch new messages from IMAP server with attachment handling
 */
function fetchNewMessagesFromSession($userEmail, $limit = 50, $forceRefresh = false) {
    $connection = connectToIMAPFromSession();
    
    if (!$connection) {
        return [
            'success' => false,
            'error' => 'Could not connect to IMAP server. Please check your settings.',
            'count' => 0
        ];
    }
    
    try {
        $totalMessages = imap_num_msg($connection);
        
        if ($totalMessages === 0) {
            imap_close($connection);
            return [
                'success' => true,
                'message' => 'No messages in inbox',
                'count' => 0
            ];
        }
        
        // If force refresh, clear existing messages
        if ($forceRefresh) {
            clearInboxMessages($userEmail);
        }
        
        $lastSyncDate = $forceRefresh ? null : getLastSyncDate($userEmail);
        
        $startMsg = max(1, $totalMessages - $limit + 1);
        $endMsg = $totalMessages;
        
        $newMessagesCount = 0;
        $lastMessageId = null;
        
        // Fetch messages in reverse order (newest first)
        for ($msgNum = $endMsg; $msgNum >= $startMsg; $msgNum--) {
            try {
                $overview = imap_fetch_overview($connection, $msgNum, 0);
                
                if (empty($overview)) {
                    continue;
                }
                
                $info = $overview[0];
                $messageId = $info->message_id ?? 'msg-' . $msgNum . '-' . time();
                $lastMessageId = $messageId;
                
                // Skip if already exists (unless force refresh)
                if (!$forceRefresh && messageExists($userEmail, $messageId)) {
                    continue;
                }
                
                // Extract sender information
                $from = $info->from ?? 'Unknown';
                $senderEmail = extractEmail($from);
                $senderName = extractName($from);
                
                // Get subject
                $subject = isset($info->subject) ? imap_utf8($info->subject) : '(No Subject)';
                
                // Get message body (with HTML stripping)
                $body = getMessageBody($connection, $msgNum);
                $cleanBody = stripHtmlFromBody($body);
                
                // Get attachments with metadata
                $attachments = getAttachmentMetadata($connection, $msgNum);
                $hasAttachments = !empty($attachments);
                $attachmentData = $hasAttachments ? json_encode($attachments) : null;
                
                // Get received date
                $receivedDate = isset($info->date) ? date('Y-m-d H:i:s', strtotime($info->date)) : date('Y-m-d H:i:s');
                
                // Save to database
                $messageData = [
                    'message_id' => $messageId,
                    'user_email' => $userEmail,
                    'sender_email' => $senderEmail,
                    'sender_name' => $senderName,
                    'subject' => $subject,
                    'body' => $cleanBody,
                    'received_date' => $receivedDate,
                    'has_attachments' => $hasAttachments ? 1 : 0,
                    'attachment_data' => $attachmentData
                ];
                
                if (saveInboxMessage($messageData)) {
                    $newMessagesCount++;
                }
                
            } catch (Exception $e) {
                error_log("Error processing message $msgNum: " . $e->getMessage());
                continue;
            }
        }
        
        updateLastSyncDate($userEmail, $lastMessageId);
        
        imap_close($connection);
        
        return [
            'success' => true,
            'message' => $forceRefresh 
                ? "Refreshed inbox with $newMessagesCount messages" 
                : "Fetched $newMessagesCount new messages",
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
 * Strip HTML tags and return clean text
 */
function stripHtmlFromBody($body) {
    // Remove HTML tags
    $text = strip_tags($body);
    
    // Decode HTML entities
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Remove excessive whitespace
    $text = preg_replace('/\s+/', ' ', $text);
    
    // Trim
    $text = trim($text);
    
    return $text;
}

/**
 * Get attachment metadata (names, types, sizes)
 */
function getAttachmentMetadata($connection, $msgNum) {
    $attachments = [];
    
    try {
        $structure = imap_fetchstructure($connection, $msgNum);
        
        if (!isset($structure->parts) || !count($structure->parts)) {
            return $attachments;
        }
        
        foreach ($structure->parts as $partNum => $part) {
            // Check if this part is an attachment
            if (isset($part->disposition) && 
                (strtolower($part->disposition) === 'attachment' || 
                 strtolower($part->disposition) === 'inline')) {
                
                $filename = 'attachment';
                
                // Get filename
                if (isset($part->dparameters)) {
                    foreach ($part->dparameters as $param) {
                        if (strtolower($param->attribute) === 'filename') {
                            $filename = $param->value;
                            break;
                        }
                    }
                }
                
                // Fallback to parameters
                if ($filename === 'attachment' && isset($part->parameters)) {
                    foreach ($part->parameters as $param) {
                        if (strtolower($param->attribute) === 'name') {
                            $filename = $param->value;
                            break;
                        }
                    }
                }
                
                // Get file extension
                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                // Estimate size (approximate)
                $size = isset($part->bytes) ? $part->bytes : 0;
                
                // Determine file type category
                $fileType = getFileTypeCategory($extension);
                
                $attachments[] = [
                    'filename' => $filename,
                    'extension' => $extension,
                    'size' => $size,
                    'type' => $fileType,
                    'icon' => getFileIcon($extension)
                ];
            }
        }
        
    } catch (Exception $e) {
        error_log("Error getting attachment metadata: " . $e->getMessage());
    }
    
    return $attachments;
}

/**
 * Get file type category based on extension
 */
function getFileTypeCategory($extension) {
    $categories = [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp'],
        'pdf' => ['pdf'],
        'document' => ['doc', 'docx', 'txt', 'rtf', 'odt'],
        'spreadsheet' => ['xls', 'xlsx', 'csv', 'ods'],
        'presentation' => ['ppt', 'pptx', 'odp'],
        'archive' => ['zip', 'rar', '7z', 'tar', 'gz'],
        'video' => ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv'],
        'audio' => ['mp3', 'wav', 'ogg', 'flac', 'aac']
    ];
    
    foreach ($categories as $category => $extensions) {
        if (in_array($extension, $extensions)) {
            return $category;
        }
    }
    
    return 'file';
}

/**
 * Get file icon emoji based on extension
 */
function getFileIcon($extension) {
    $icons = [
        'pdf' => '📄',
        'doc' => '📝',
        'docx' => '📝',
        'txt' => '📝',
        'xls' => '📊',
        'xlsx' => '📊',
        'csv' => '📊',
        'ppt' => '📽️',
        'pptx' => '📽️',
        'jpg' => '🖼️',
        'jpeg' => '🖼️',
        'png' => '🖼️',
        'gif' => '🖼️',
        'zip' => '🗜️',
        'rar' => '🗜️',
        '7z' => '🗜️',
        'mp4' => '🎥',
        'avi' => '🎥',
        'mp3' => '🎵',
        'wav' => '🎵'
    ];
    
    return $icons[$extension] ?? '📎';
}

/**
 * Get message body (prioritizes plain text, falls back to HTML)
 */
function getMessageBody($connection, $msgNum) {
    $body = '';
    
    try {
        $structure = imap_fetchstructure($connection, $msgNum);
        
        if (isset($structure->parts) && count($structure->parts)) {
            // Multipart message
            for ($i = 0; $i < count($structure->parts); $i++) {
                $part = $structure->parts[$i];
                
                // Plain text (preferred)
                if ($part->subtype === 'PLAIN') {
                    $body = imap_fetchbody($connection, $msgNum, $i + 1);
                    $body = decodeBody($body, $part->encoding);
                    break;
                }
                
                // HTML (fallback)
                if ($part->subtype === 'HTML' && empty($body)) {
                    $body = imap_fetchbody($connection, $msgNum, $i + 1);
                    $body = decodeBody($body, $part->encoding);
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
 */
function extractEmail($from) {
    if (preg_match('/<([^>]+)>/', $from, $matches)) {
        return $matches[1];
    }
    return $from;
}

/**
 * Extract sender name from "From" header
 */
function extractName($from) {
    if (preg_match('/^([^<]+)</', $from, $matches)) {
        return trim($matches[1], ' "');
    }
    return '';
}

/**
 * Check if message already exists in database
 */
function messageExists($userEmail, $messageId) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;
        
        $stmt = $pdo->prepare("SELECT id FROM inbox_messages WHERE user_email = :email AND message_id = :message_id");
        $stmt->execute([':email' => $userEmail, ':message_id' => $messageId]);
        
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        error_log("Error checking message existence: " . $e->getMessage());
        return false;
    }
}

/**
 * Clear all inbox messages for user (for force refresh)
 */
function clearInboxMessages($userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;
        
        $stmt = $pdo->prepare("DELETE FROM inbox_messages WHERE user_email = :email");
        return $stmt->execute([':email' => $userEmail]);
    } catch (Exception $e) {
        error_log("Error clearing inbox messages: " . $e->getMessage());
        return false;
    }
}

/**
 * Quick sync check
 */
function quickSyncCheckFromSession($userEmail) {
    $connection = connectToIMAPFromSession();
    
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

// Legacy function for backward compatibility
function fetchNewMessages($userEmail, $imapConfig = [], $limit = 50) {
    error_log("DEPRECATED: fetchNewMessages() called. Using session config instead.");
    return fetchNewMessagesFromSession($userEmail, $limit);
}

function quickSyncCheck($userEmail, $imapConfig = []) {
    error_log("DEPRECATED: quickSyncCheck() called. Using session config instead.");
    return quickSyncCheckFromSession($userEmail);
}

?>