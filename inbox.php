<?php
/**
 * INBOX PAGE - SPLIT VIEW WITH AUTO-SYNC
 * Sidebar + Message List + Message Preview
 * AUTO-SYNCS emails from IMAP server on every page load
 */

session_start();

// Check authentication
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: login.php');
    exit();
}

require_once 'db_config.php';
require_once 'settings_helper.php';
require_once 'imap_helper.php';
require_once 'inbox_functions.php';

$userEmail = $_SESSION['smtp_user'];
$userPassword = $_SESSION['smtp_pass'] ?? null;

// ==================== AUTO-SYNC: FETCH EMAILS FROM IMAP ====================
$syncStatus = '';
$syncClass = '';
$newMessageCount = 0;

// Load IMAP config to session if not already loaded
if (!isset($_SESSION['imap_config']) && $userPassword) {
    $settings = getSettingsWithDefaults($userEmail);
    $_SESSION['imap_config'] = [
        'imap_server' => $settings['imap_server'] ?? 'imap.hostinger.com',
        'imap_port' => $settings['imap_port'] ?? '993',
        'imap_encryption' => $settings['imap_encryption'] ?? 'ssl',
        'imap_username' => $settings['imap_username'] ?? $userEmail,
        'imap_password' => $userPassword
    ];
}

// AUTO-SYNC: Fetch messages from IMAP server
if (isset($_SESSION['imap_config']) && $userPassword) {
    try {
        $result = fetchNewMessagesFromSession($userEmail, 100);
        
        if ($result['success']) {
            $newMessageCount = $result['count'];
            $syncStatus = $result['message'];
            $syncClass = 'success';
        } else {
            $syncStatus = $result['error'] ?? 'Could not sync emails';
            $syncClass = 'error';
        }
    } catch (Exception $e) {
        error_log("Auto-sync error: " . $e->getMessage());
        $syncStatus = 'Sync error: ' . $e->getMessage();
        $syncClass = 'error';
    }
} else {
    $syncStatus = 'IMAP not configured';
    $syncClass = 'warning';
}

// Fetch messages from database (modified to include is_new calculation)
function getInboxMessages($userEmail, $limit = 100) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return [];
        
        $stmt = $pdo->prepare("
            SELECT *,
                CASE 
                    WHEN is_read = 0 AND TIMESTAMPDIFF(MINUTE, fetched_at, NOW()) <= 5 
                    THEN 1 
                    ELSE 0 
                END as is_new
            FROM inbox_messages 
            WHERE user_email = :email 
            AND is_deleted = 0
            ORDER BY received_date DESC 
            LIMIT :limit
        ");
        $stmt->bindValue(':email', $userEmail, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching inbox messages: " . $e->getMessage());
        return [];
    }
}

$messages = getInboxMessages($userEmail);

// Get counts
$unreadCount = getUnreadCount($userEmail);
$totalNewCount = getNewCount($userEmail);

function formatDate($dateStr) {
    $date = new DateTime($dateStr);
    $now = new DateTime();
    $diff = $now->diff($date);
    
    if ($diff->days === 0) {
        return $date->format('g:i A');
    } elseif ($diff->days === 1) {
        return 'Yesterday';
    } elseif ($diff->days < 7) {
        return $date->format('l');
    } else {
        return $date->format('M j');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inbox - SXC MDTS<?= $unreadCount > 0 ? " ($unreadCount)" : '' ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    
    <style>
        :root {
            --apple-blue: #007AFF;
            --apple-gray: #8E8E93;
            --apple-bg: #F2F2F7;
            --border: #E5E5EA;
            --text-primary: #1c1c1e;
            --text-secondary: #52525b;
            --success: #34C759;
            --warning: #FF9500;
            --error: #FF3B30;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--apple-bg);
            color: var(--text-primary);
            overflow: hidden;
        }

        /* ========== SYNC STATUS BANNER ========== */
        .sync-banner {
            padding: 12px 20px;
            text-align: center;
            font-size: 13px;
            font-weight: 600;
            border-bottom: 1px solid rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .sync-banner.success {
            background: linear-gradient(135deg, var(--success) 0%, #2ea44f 100%);
            color: white;
        }

        .sync-banner.error {
            background: linear-gradient(135deg, var(--error) 0%, #dc2626 100%);
            color: white;
        }

        .sync-banner.warning {
            background: linear-gradient(135deg, var(--warning) 0%, #f59e0b 100%);
            color: white;
        }

        /* ========== MAIN LAYOUT ========== */
        .app-container {
            display: flex;
            height: 100vh;
            max-width: 100%;
        }

        .content-wrapper {
            flex: 1;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* ========== MESSAGE LIST PANEL ========== */
        .message-list-panel {
            width: 380px;
            background: white;
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            height: 100vh;
        }

        .list-header {
            padding: 20px 20px 16px;
            border-bottom: 1px solid var(--border);
            background: white;
        }

        .list-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 16px;
            letter-spacing: -0.5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .inbox-badges {
            display: flex;
            gap: 6px;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge.new {
            background: linear-gradient(135deg, var(--success) 0%, #2ea44f 100%);
            color: white;
        }

        .badge.unread {
            background: var(--apple-blue);
            color: white;
        }

        .search-box {
            position: relative;
            margin-bottom: 12px;
        }

        .search-box input {
            width: 100%;
            padding: 10px 40px 10px 16px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.2s;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--apple-blue);
            box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
        }

        .search-box .material-icons {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--apple-gray);
            font-size: 20px;
        }

        .refresh-btn {
            width: 100%;
            padding: 10px;
            background: var(--apple-blue);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .refresh-btn:hover:not(:disabled) {
            background: #0051d5;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 122, 255, 0.3);
        }

        .refresh-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .message-list {
            flex: 1;
            overflow-y: auto;
            background: white;
        }

        .message-item {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: all 0.15s;
            position: relative;
        }

        .message-item:hover {
            background: #f8f9fa;
        }

        .message-item.active {
            background: #e8f4ff;
            border-left: 3px solid var(--apple-blue);
        }

        .message-item.unread {
            background: #fafbfc;
            font-weight: 600;
        }

        .message-item.unread::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 50%;
            transform: translateY(-50%);
            width: 6px;
            height: 6px;
            background: var(--apple-blue);
            border-radius: 50%;
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 6px;
        }

        .message-sender {
            font-size: 14px;
            color: var(--text-primary);
            font-weight: 600;
        }

        .message-item.unread .message-sender {
            font-weight: 700;
        }

        .message-date {
            font-size: 12px;
            color: var(--text-secondary);
            white-space: nowrap;
        }

        .message-subject {
            font-size: 13px;
            color: var(--text-primary);
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .message-preview {
            font-size: 12px;
            color: var(--text-secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .attachment-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            background: #e8f4ff;
            color: var(--apple-blue);
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-top: 4px;
        }

        .attachment-badge .material-icons {
            font-size: 14px;
        }

        /* ========== MESSAGE VIEWER ========== */
        .message-viewer {
            flex: 1;
            background: white;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        .viewer-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--apple-gray);
        }

        .viewer-placeholder .material-icons {
            font-size: 80px;
            margin-bottom: 16px;
            opacity: 0.3;
        }

        .viewer-placeholder-text {
            font-size: 16px;
            font-weight: 500;
        }

        .viewer-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            background: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .viewer-actions {
            display: flex;
            gap: 8px;
        }

        .icon-btn {
            width: 40px;
            height: 40px;
            border: 1px solid var(--border);
            background: white;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .icon-btn:hover {
            background: var(--apple-bg);
            border-color: var(--apple-gray);
        }

        .icon-btn.delete:hover {
            background: #fee;
            border-color: var(--error);
            color: var(--error);
        }

        .viewer-content {
            padding: 24px;
            flex: 1;
        }

        .message-header-section {
            margin-bottom: 32px;
        }

        .message-subject-display {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 20px;
            line-height: 1.3;
        }

        .message-meta {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .meta-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .meta-label {
            font-size: 13px;
            color: var(--text-secondary);
            min-width: 60px;
            font-weight: 600;
        }

        .meta-value {
            font-size: 14px;
            color: var(--text-primary);
        }

        .sender-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sender-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--apple-blue);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 16px;
        }

        .sender-details {
            flex: 1;
        }

        .sender-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .sender-email {
            font-size: 13px;
            color: var(--text-secondary);
        }

        /* ========== ATTACHMENTS SECTION ========== */
        .attachments-section {
            margin: 24px 0;
            padding: 16px;
            background: #f8f9fa;
            border-radius: 12px;
            border: 1px solid var(--border);
        }

        .attachments-header {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 12px;
        }

        .attachments-header .material-icons {
            font-size: 18px;
            color: var(--apple-blue);
        }

        .attachments-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .attachment-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: white;
            border-radius: 8px;
            border: 1px solid var(--border);
            transition: all 0.2s;
        }

        .attachment-item:hover {
            border-color: var(--apple-blue);
            box-shadow: 0 2px 8px rgba(0, 122, 255, 0.1);
        }

        .attachment-icon {
            font-size: 28px;
            line-height: 1;
        }

        .attachment-info {
            flex: 1;
        }

        .attachment-name {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
            word-break: break-word;
        }

        .attachment-size {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 2px;
        }

        .download-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--apple-blue);
            color: white;
            text-decoration: none;
            transition: all 0.2s;
            flex-shrink: 0;
        }

        .download-btn:hover {
            background: #0051d5;
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(0, 122, 255, 0.3);
        }

        .download-btn .material-icons {
            font-size: 20px;
        }

        .message-body-section {
            font-size: 15px;
            line-height: 1.7;
            color: var(--text-primary);
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        /* ========== TOAST NOTIFICATION ========== */
        .toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 16px 24px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            font-size: 14px;
            font-weight: 500;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.3s;
            z-index: 1000;
            max-width: 400px;
        }

        .toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        .toast.success {
            border-left: 4px solid var(--success);
        }

        .toast.error {
            border-left: 4px solid var(--error);
        }

        /* ========== SYNC STATUS INDICATOR ========== */
        .sync-status {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            font-size: 13px;
            font-weight: 600;
            display: none;
            align-items: center;
            gap: 10px;
            z-index: 999;
        }

        .sync-status.active {
            display: flex;
        }

        .sync-spinner {
            width: 16px;
            height: 16px;
            border: 2px solid var(--border);
            border-top-color: var(--apple-blue);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* ========== MOBILE RESPONSIVE ========== */
        @media (max-width: 768px) {
            .message-list-panel {
                width: 100%;
            }

            .message-viewer {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 100;
            }

            .message-viewer.show {
                display: flex;
            }
        }
    </style>
</head>
<body>
    <?php include('sidebar.php') ?>
    <!-- Sync Status Banner -->
    <?php if ($syncStatus): ?>
    <div class="sync-banner <?= $syncClass ?>">
        <span class="material-icons" style="font-size: 16px;">
            <?= $syncClass === 'success' ? 'check_circle' : ($syncClass === 'error' ? 'error' : 'info') ?>
        </span>
        <span><?= htmlspecialchars($syncStatus) ?></span>
    </div>
    <?php endif; ?>

    <div class="app-container">
        <div class="content-wrapper">
            <!-- Message List Panel -->
            <div class="message-list-panel">
                <div class="list-header">
                    <div class="list-title">
                        Inbox
                        <div class="inbox-badges">
                            <?php if ($totalNewCount > 0): ?>
                            <span class="badge new"><?= $totalNewCount ?> New</span>
                            <?php endif; ?>
                            <?php if ($unreadCount > 0): ?>
                            <span class="badge unread"><?= $unreadCount ?> Unread</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="search-box">
                        <input type="text" placeholder="Search messages..." id="searchInput">
                        <span class="material-icons">search</span>
                    </div>

                    <button class="refresh-btn" id="refreshBtn" onclick="refreshInbox()">
                        <span class="material-icons" style="font-size: 18px;">refresh</span>
                        Refresh
                    </button>
                </div>

                <div class="message-list">
                    <?php if (empty($messages)): ?>
                        <div class="viewer-placeholder">
                            <span class="material-icons">mail_outline</span>
                            <p class="viewer-placeholder-text">No messages yet</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $msg): ?>
                        <div class="message-item <?= $msg['is_read'] == 0 ? 'unread' : '' ?>" 
                             data-message-id="<?= $msg['id'] ?>"
                             onclick="loadMessage(<?= $msg['id'] ?>)">
                            <div class="message-header">
                                <div class="message-sender"><?= htmlspecialchars($msg['sender_name'] ?: $msg['sender_email']) ?></div>
                                <div class="message-date"><?= formatDate($msg['received_date']) ?></div>
                            </div>
                            <div class="message-subject"><?= htmlspecialchars($msg['subject']) ?></div>
                            <div class="message-preview"><?= htmlspecialchars(substr($msg['body'], 0, 100)) ?></div>
                            <?php if ($msg['has_attachments']): ?>
                            <div class="attachment-badge">
                                <span class="material-icons">attach_file</span>
                                <?php 
                                    $attachmentData = json_decode($msg['attachment_data'], true);
                                    $attachmentCount = is_array($attachmentData) ? count($attachmentData) : 0;
                                    echo $attachmentCount . ' attachment' . ($attachmentCount > 1 ? 's' : '');
                                ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Message Viewer -->
            <div class="message-viewer" id="messageViewer">
                <div class="viewer-placeholder">
                    <span class="material-icons">drafts</span>
                    <p class="viewer-placeholder-text">Select a message to read</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Sync Status Indicator -->
    <div class="sync-status" id="syncStatus">
        <div class="sync-spinner"></div>
        <span id="syncText">Syncing emails...</span>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast">
        <span id="toastMessage"></span>
    </div>

    <script>
        let currentMessageId = null;

        // Refresh inbox
        function refreshInbox(forceRefresh = false) {
            const refreshBtn = document.getElementById('refreshBtn');
            const syncStatus = document.getElementById('syncStatus');
            const syncText = document.getElementById('syncText');
            
            refreshBtn.disabled = true;
            refreshBtn.innerHTML = '<span class="material-icons" style="font-size: 18px;">hourglass_empty</span> Syncing...';
            syncStatus.classList.add('active');
            syncText.textContent = forceRefresh ? 'Refreshing all emails...' : 'Fetching new emails...';
            
            fetch('fetch_inbox_messages.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'force_refresh=' + (forceRefresh ? '1' : '0')
            })
            .then(response => response.json())
            .then(result => {
                refreshBtn.disabled = false;
                refreshBtn.innerHTML = '<span class="material-icons" style="font-size: 18px;">refresh</span> Refresh';
                syncStatus.classList.remove('active');
                
                if (result.success) {
                    showToast('✅ ' + result.message, 'success');
                    
                    if (result.count > 0) {
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    }
                } else {
                    showToast('❌ ' + (result.error || result.message), 'error');
                }
            })
            .catch(error => {
                refreshBtn.disabled = false;
                refreshBtn.innerHTML = '<span class="material-icons" style="font-size: 18px;">refresh</span> Refresh';
                syncStatus.classList.remove('active');
                showToast('❌ Network error: ' + error.message, 'error');
            });
        }

        // Load message in viewer
        function loadMessage(messageId) {
            currentMessageId = messageId;
            
            // Update active state
            document.querySelectorAll('.message-item').forEach(item => {
                item.classList.remove('active');
            });
            document.querySelector(`[data-message-id="${messageId}"]`).classList.add('active');
            
            // Show viewer on mobile
            document.getElementById('messageViewer').classList.add('show');
            
            // Fetch message content
            fetch('get_message.php?id=' + messageId)
                .then(response => response.json())
                .then(message => {
                    if (message.error) {
                        showToast('❌ ' + message.error, 'error');
                        return;
                    }
                    
                    displayMessage(message);
                    
                    // Mark as read
                    markAsRead(messageId);
                })
                .catch(error => {
                    showToast('❌ Error loading message', 'error');
                });
        }

        // Display message in viewer
        function displayMessage(message) {
            const viewer = document.getElementById('messageViewer');
            
            // Parse attachments if they exist
            let attachmentsHtml = '';
            if (message.has_attachments && message.attachment_data) {
                try {
                    const attachments = JSON.parse(message.attachment_data);
                    if (Array.isArray(attachments) && attachments.length > 0) {
                        attachmentsHtml = `
                            <div class="attachments-section">
                                <div class="attachments-header">
                                    <span class="material-icons">attach_file</span>
                                    <span>${attachments.length} Attachment${attachments.length > 1 ? 's' : ''}</span>
                                </div>
                                <div class="attachments-list">
                                    ${attachments.map(att => `
                                        <div class="attachment-item">
                                            <span class="attachment-icon">${att.icon || '📎'}</span>
                                            <div class="attachment-info">
                                                <div class="attachment-name">${escapeHtml(att.filename)}</div>
                                                <div class="attachment-size">${formatFileSize(att.size)}</div>
                                            </div>
                                            <a href="download_attachment.php?message_id=${message.id}&filename=${encodeURIComponent(att.filename)}" 
                                               class="download-btn" 
                                               download="${att.filename}"
                                               title="Download ${att.filename}">
                                                <span class="material-icons">download</span>
                                            </a>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        `;
                    }
                } catch (e) {
                    console.error('Error parsing attachments:', e);
                }
            }
            
            viewer.innerHTML = `
                <div class="viewer-header">
                    <div></div>
                    <div class="viewer-actions">
                        <button class="icon-btn" onclick="window.print()" title="Print">
                            <span class="material-icons">print</span>
                        </button>
                        <button class="icon-btn delete" onclick="deleteMessage(${message.id})" title="Delete">
                            <span class="material-icons">delete</span>
                        </button>
                    </div>
                </div>
                <div class="viewer-content">
                    <div class="message-header-section">
                        <h1 class="message-subject-display">${escapeHtml(message.subject)}</h1>
                        <div class="message-meta">
                            <div class="meta-row">
                                <span class="meta-label">From:</span>
                                <div class="sender-info">
                                    <div class="sender-avatar">${message.sender_email.charAt(0).toUpperCase()}</div>
                                    <div class="sender-details">
                                        <div class="sender-name">${escapeHtml(message.sender_name || message.sender_email)}</div>
                                        <div class="sender-email">${escapeHtml(message.sender_email)}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="meta-row">
                                <span class="meta-label">Date:</span>
                                <span class="meta-value">${formatFullDate(message.received_date)}</span>
                            </div>
                            <div class="meta-row">
                                <span class="meta-label">To:</span>
                                <span class="meta-value"><?= htmlspecialchars($userEmail) ?></span>
                            </div>
                        </div>
                    </div>
                    ${attachmentsHtml}
                    <div class="message-body-section">
                        ${escapeHtml(message.body).replace(/\n/g, '<br>')}
                    </div>
                </div>
            `;
        }

        // Mark message as read
        function markAsRead(messageId) {
            fetch('mark_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ message_id: messageId })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    // Remove unread class
                    document.querySelector(`[data-message-id="${messageId}"]`)?.classList.remove('unread');
                }
            });
        }

        // Delete message
        function deleteMessage(messageId) {
            if (!confirm('Are you sure you want to delete this message?')) {
                return;
            }

            fetch('delete_inbox_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ message_id: messageId })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showToast('✅ Message deleted', 'success');
                    
                    // Remove from list
                    document.querySelector(`[data-message-id="${messageId}"]`)?.remove();
                    
                    // Clear viewer
                    document.getElementById('messageViewer').innerHTML = `
                        <div class="viewer-placeholder">
                            <span class="material-icons">drafts</span>
                            <p class="viewer-placeholder-text">Select a message to read</p>
                        </div>
                    `;
                } else {
                    showToast('❌ ' + (result.error || 'Error deleting message'), 'error');
                }
            })
            .catch(error => {
                showToast('❌ Network error', 'error');
                console.error('Delete error:', error);
            });
        }

        // Helper functions
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toastMessage');
            
            toastMessage.textContent = message;
            toast.className = 'toast ' + type + ' show';
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatFullDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function formatFileSize(bytes) {
            if (!bytes || bytes === 0) return '0 B';
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        }

        // Auto-hide sync banner after 5 seconds
        setTimeout(() => {
            const banner = document.querySelector('.sync-banner');
            if (banner) {
                banner.style.transition = 'opacity 0.5s';
                banner.style.opacity = '0';
                setTimeout(() => banner.remove(), 500);
            }
        }, 5000);

        // Search functionality
        document.getElementById('searchInput')?.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const messageItems = document.querySelectorAll('.message-item');
            
            messageItems.forEach(item => {
                const sender = item.querySelector('.message-sender').textContent.toLowerCase();
                const subject = item.querySelector('.message-subject').textContent.toLowerCase();
                const preview = item.querySelector('.message-preview').textContent.toLowerCase();
                
                if (sender.includes(searchTerm) || subject.includes(searchTerm) || preview.includes(searchTerm)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>