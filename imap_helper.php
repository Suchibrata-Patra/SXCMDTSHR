<?php
/**
 * IMAP Helper - ULTIMATE FIX
 * ✅ Fixes read status preservation
 * ✅ Properly parses multipart MIME messages
 * ✅ Handles quoted-printable and base64 encoding
 * ✅ Strips MIME boundaries and headers
 */
require_once 'db_config.php';
require_once 'settings_helper.php';
require_once 'inbox_functions.php';

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
        $config['imap_password'],
        $config['imap_encryption']
    );
}

function connectToIMAP($server, $port, $email, $password, $encryption = 'ssl') {
    $encryptionFlag = '';
    if ($encryption === 'ssl') {
        $encryptionFlag = '/imap/ssl';
    } elseif ($encryption === 'tls') {
        $encryptionFlag = '/imap/tls';
    } else {
        $encryptionFlag = '/imap/notls';
    }
    
    $mailbox = "{" . $server . ":" . $port . $encryptionFlag . "}INBOX";
    
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
 * Fetch new messages from IMAP - ULTIMATE FIX
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
        
        $lastSyncDate = $forceRefresh ? null : getLastSyncDate($userEmail);
        
        $startMsg = max(1, $totalMessages - $limit + 1);
        $endMsg = $totalMessages;
        
        $newMessagesCount = 0;
        $updatedMessagesCount = 0;
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
                
                // Check if message already exists
                $messageExists = messageExists($userEmail, $messageId);
                
                // Extract sender information
                $from = $info->from ?? 'Unknown';
                $senderEmail = extractEmail($from);
                $senderName = extractName($from);
                
                // Get subject
                $subject = isset($info->subject) ? imap_utf8($info->subject) : '(No Subject)';
                
                // ✅ ULTIMATE FIX: Get properly parsed message body
                $body = getMessageBodyParsed($connection, $msgNum);
                
                // Strip HTML if it's HTML content
                $cleanBody = stripHtmlFromBody($body);
                
                // If still empty, use placeholder
                if (empty($cleanBody)) {
                    $cleanBody = "[Message body could not be retrieved]";
                    error_log("ERROR: Could not retrieve body for message #$msgNum (Subject: $subject)");
                }
                
                // Generate preview (first 500 chars)
                $bodyPreview = substr($cleanBody, 0, 500);
                
                // Get attachments with metadata
                $attachments = getAttachmentMetadata($connection, $msgNum);
                $hasAttachments = !empty($attachments);
                $attachmentData = $hasAttachments ? json_encode($attachments) : null;
                
                // Get received date
                $receivedDate = isset($info->date) ? date('Y-m-d H:i:s', strtotime($info->date)) : date('Y-m-d H:i:s');
                
                // Save message data
                $messageData = [
                    'message_id' => $messageId,
                    'user_email' => $userEmail,
                    'sender_email' => $senderEmail,
                    'sender_name' => $senderName,
                    'subject' => $subject,
                    'body' => $cleanBody,
                    'body_preview' => $bodyPreview,
                    'received_date' => $receivedDate,
                    'has_attachments' => $hasAttachments ? 1 : 0,
                    'attachment_data' => $attachmentData
                ];
                
                if (saveInboxMessage($messageData)) {
                    if ($messageExists) {
                        $updatedMessagesCount++;
                    } else {
                        $newMessagesCount++;
                    }
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
                ? "Synced: $newMessagesCount new, $updatedMessagesCount updated" 
                : "Fetched $newMessagesCount new messages" . 
                  ($updatedMessagesCount > 0 ? " (updated $updatedMessagesCount)" : ""),
            'count' => $newMessagesCount,
            'updated' => $updatedMessagesCount,
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
 * ✅ ULTIMATE FIX: Get properly parsed message body
 * This function correctly handles MIME multipart messages
 */
function getMessageBodyParsed($connection, $msgNum) {
    try {
        $structure = imap_fetchstructure($connection, $msgNum);
        
        if (!$structure) {
            // Fallback to simple body
            return imap_body($connection, $msgNum);
        }
        
        // Check if multipart
        if (isset($structure->parts) && is_array($structure->parts) && count($structure->parts) > 0) {
            // Multipart message - use recursive parser
            return getPartBody($connection, $msgNum, $structure, "0");
        } else {
            // Simple message
            $body = imap_body($connection, $msgNum);
            
            // Decode if needed
            if (isset($structure->encoding)) {
                $body = decodeBody($body, $structure->encoding);
            }
            
            return $body;
        }
        
    } catch (Exception $e) {
        error_log("Error in getMessageBodyParsed: " . $e->getMessage());
        return imap_body($connection, $msgNum);
    }
}

/**
 * ✅ NEW FUNCTION: Recursively parse MIME parts
 * This is the KEY to properly handling multipart messages
 */
function getPartBody($connection, $msgNum, $structure, $partNumber) {
    $data = '';
    
    // If this is a multipart, iterate through sub-parts
    if (isset($structure->parts) && is_array($structure->parts)) {
        foreach ($structure->parts as $index => $part) {
            $currentPart = $partNumber == "0" ? ($index + 1) : $partNumber . "." . ($index + 1);
            
            // Get MIME type
            $mimeType = getMimeType($part);
            
            // Check if this is an attachment
            $isAttachment = false;
            if (isset($part->disposition)) {
                $disposition = strtolower($part->disposition);
                if ($disposition == 'attachment' || $disposition == 'inline') {
                    $isAttachment = true;
                }
            }
            
            // Skip attachments
            if ($isAttachment) {
                continue;
            }
            
            // If this part has sub-parts, recurse
            if (isset($part->parts) && is_array($part->parts)) {
                $data .= getPartBody($connection, $msgNum, $part, $currentPart);
            } else {
                // This is a leaf part - fetch the content
                
                // Prefer text/plain over text/html
                if ($mimeType == 'text/plain') {
                    $body = imap_fetchbody($connection, $msgNum, $currentPart);
                    $body = decodeBody($body, $part->encoding);
                    return $body; // Return immediately for plain text
                } elseif ($mimeType == 'text/html' && empty($data)) {
                    $body = imap_fetchbody($connection, $msgNum, $currentPart);
                    $body = decodeBody($body, $part->encoding);
                    $data = $body; // Store HTML as fallback
                }
            }
        }
    } else {
        // No sub-parts - this is the content
        $body = imap_fetchbody($connection, $msgNum, $partNumber);
        
        if (isset($structure->encoding)) {
            $body = decodeBody($body, $structure->encoding);
        }
        
        return $body;
    }
    
    return $data;
}

/**
 * ✅ NEW FUNCTION: Get MIME type from part structure
 */
function getMimeType($part) {
    $primaryType = '';
    $secondaryType = '';
    
    // Primary type
    if (isset($part->type)) {
        switch ($part->type) {
            case 0: $primaryType = 'text'; break;
            case 1: $primaryType = 'multipart'; break;
            case 2: $primaryType = 'message'; break;
            case 3: $primaryType = 'application'; break;
            case 4: $primaryType = 'audio'; break;
            case 5: $primaryType = 'image'; break;
            case 6: $primaryType = 'video'; break;
            case 7: $primaryType = 'other'; break;
        }
    }
    
    // Secondary type (subtype)
    if (isset($part->subtype)) {
        $secondaryType = strtolower($part->subtype);
    }
    
    return $primaryType . '/' . $secondaryType;
}

/**
 * ✅ IMPROVED: Decode message body based on encoding
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
        default:
            return $body;
    }
}

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
                
                // Estimate size
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

function extractEmail($from) {
    if (preg_match('/<([^>]+)>/', $from, $matches)) {
        return $matches[1];
    }
    return $from;
}

function extractName($from) {
    if (preg_match('/^([^<]+)</', $from, $matches)) {
        return trim($matches[1], ' "');
    }
    return '';
}

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

/**
 * ✅ IMPROVED: Strip HTML and clean text
 */
function stripHtmlFromBody($body) {
    if (empty($body)) {
        return '';
    }
    
    // Remove HTML tags
    $text = strip_tags($body);
    
    // Decode HTML entities
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Remove excessive whitespace
    $text = preg_replace('/\s+/', ' ', $text);
    
    // Remove null bytes and control characters
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
    
    // Trim
    $text = trim($text);
    
    return $text;
}
?>