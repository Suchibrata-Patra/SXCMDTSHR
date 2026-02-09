<?php
/**
 * INBOX PAGE - SPLIT VIEW
 * Sidebar + Message List + Message Preview
 * Auto-fetch enabled with manual refresh
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

$userEmail = $_SESSION['smtp_user'];

// Load IMAP config to session if not already loaded
if (!isset($_SESSION['imap_config'])) {
    $settings = getSettingsWithDefaults($userEmail);
    $_SESSION['imap_config'] = [
        'imap_server' => $settings['imap_server'] ?? 'imap.hostinger.com',
        'imap_port' => $settings['imap_port'] ?? '993',
        'imap_encryption' => $settings['imap_encryption'] ?? 'ssl',
        'imap_username' => $settings['imap_username'] ?? $userEmail,
        'imap_password' => $_SESSION['smtp_pass']
    ];
}

// Fetch messages from database
function getInboxMessages($userEmail, $limit = 100) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return [];
        
        $stmt = $pdo->prepare("
            SELECT * FROM inbox_messages 
            WHERE user_email = :email 
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
    <title>Inbox - SXC MDTS</title>
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
        }

        .list-actions {
            display: flex;
            gap: 8px;
        }

        .btn-action {
            flex: 1;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-refresh {
            background: var(--apple-bg);
            color: var(--text-primary);
        }

        .btn-refresh:hover {
            background: #e5e5ea;
        }

        .btn-compose {
            background: var(--apple-blue);
            color: white;
        }

        .btn-compose:hover {
            background: #0051D5;
        }

        .btn-action:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .sync-status {
            padding: 12px 20px;
            background: #f0f9ff;
            border-bottom: 1px solid #bfdbfe;
            display: none;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: #0c4a6e;
        }

        .sync-status.active {
            display: flex;
        }

        .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid #bfdbfe;
            border-top-color: #0284c7;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* ========== MESSAGE LIST ========== */
        .message-list {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .message-list::-webkit-scrollbar {
            width: 6px;
        }

        .message-list::-webkit-scrollbar-track {
            background: transparent;
        }

        .message-list::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }

        .message-item {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }

        .message-item:hover {
            background: var(--apple-bg);
        }

        .message-item.active {
            background: #e3f2fd;
            border-left: 3px solid var(--apple-blue);
            padding-left: 17px;
        }

        .message-item.unread {
            background: #f8f9fa;
        }

        .message-item.unread::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 50%;
            transform: translateY(-50%);
            width: 8px;
            height: 8px;
            background: var(--apple-blue);
            border-radius: 50%;
        }

        .message-from {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-primary);
            margin-bottom: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .message-date {
            font-size: 12px;
            color: var(--apple-gray);
            white-space: nowrap;
        }

        .message-subject {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 4px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .message-preview {
            font-size: 13px;
            color: var(--apple-gray);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .message-badges {
            display: flex;
            gap: 6px;
            margin-top: 6px;
        }

        .badge {
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: 600;
        }

        .badge-attachment {
            background: #f0f9ff;
            color: #0284c7;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--apple-gray);
        }

        .empty-icon {
            font-size: 64px;
            margin-bottom: 16px;
            opacity: 0.3;
        }

        .empty-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-primary);
        }

        .empty-text {
            font-size: 14px;
        }

        /* ========== MESSAGE VIEWER PANEL ========== */
        .message-viewer-panel {
            flex: 1;
            background: white;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }

        .viewer-placeholder {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--apple-gray);
            flex-direction: column;
            gap: 16px;
        }

        .viewer-placeholder .material-icons {
            font-size: 80px;
            opacity: 0.2;
        }

        .viewer-placeholder-text {
            font-size: 16px;
            font-weight: 500;
        }

        .viewer-header {
            padding: 20px 30px;
            border-bottom: 1px solid var(--border);
            background: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .viewer-actions {
            display: flex;
            gap: 8px;
        }

        .icon-btn {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            color: var(--text-primary);
        }

        .icon-btn:hover {
            background: var(--apple-bg);
        }

        .icon-btn.delete {
            color: #ea4335;
        }

        .icon-btn.delete:hover {
            background: #fee;
        }

        .viewer-content {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .viewer-content::-webkit-scrollbar {
            width: 8px;
        }

        .viewer-content::-webkit-scrollbar-track {
            background: transparent;
        }

        .viewer-content::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }

        .message-header-section {
            padding: 30px;
            border-bottom: 1px solid var(--border);
        }

        .message-subject-display {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 24px;
            letter-spacing: -0.5px;
            line-height: 1.2;
        }

        .message-meta {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .meta-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 14px;
        }

        .meta-label {
            font-weight: 600;
            color: var(--apple-gray);
            min-width: 60px;
        }

        .meta-value {
            color: var(--text-primary);
            flex: 1;
        }

        .sender-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sender-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--apple-blue);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 16px;
            flex-shrink: 0;
        }

        .sender-details {
            flex: 1;
        }

        .sender-name {
            font-weight: 600;
            color: var(--text-primary);
        }

        .sender-email {
            font-size: 13px;
            color: var(--apple-gray);
        }

        .message-body-section {
            padding: 40px 30px;
            font-size: 15px;
            line-height: 1.8;
            color: var(--text-primary);
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .attachment-section {
            padding: 20px 30px;
            border-top: 1px solid var(--border);
            background: var(--apple-bg);
        }

        .attachment-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--apple-gray);
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .attachment-list {
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
        }

        .attachment-icon {
            font-size: 24px;
        }

        .attachment-info {
            flex: 1;
        }

        .attachment-name {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
        }

        .attachment-size {
            font-size: 12px;
            color: var(--apple-gray);
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 1200px) {
            .message-list-panel {
                width: 320px;
            }
        }

        @media (max-width: 992px) {
            .message-viewer-panel {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 1000;
                transform: translateX(100%);
                transition: transform 0.3s ease;
            }

            .message-viewer-panel.show {
                transform: translateX(0);
            }

            .message-list-panel {
                width: 100%;
            }
        }

        /* ========== TOAST NOTIFICATION ========== */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 16px 24px;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            z-index: 9999;
            display: none;
            align-items: center;
            gap: 12px;
            min-width: 300px;
        }

        .toast.success {
            border-left: 4px solid #10b981;
        }

        .toast.error {
            border-left: 4px solid #ef4444;
        }

        .toast.show {
            display: flex;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <?php require 'sidebar.php'; ?>

        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <!-- Message List Panel -->
            <div class="message-list-panel">
                <div class="list-header">
                    <h1 class="list-title">📬 Inbox</h1>
                    <div class="list-actions">
                        <button class="btn-action btn-refresh" id="refreshBtn">
                            <span class="material-icons" style="font-size: 18px;">refresh</span>
                            Refresh
                        </button>
                        <button class="btn-action btn-compose" onclick="window.location.href='index.php'">
                            <span class="material-icons" style="font-size: 18px;">edit</span>
                            Compose
                        </button>
                    </div>
                </div>

                <div class="sync-status" id="syncStatus">
                    <div class="spinner"></div>
                    <span id="syncText">Syncing emails...</span>
                </div>

                <div class="message-list" id="messageList">
                    <?php if (empty($messages)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📭</div>
                            <div class="empty-title">No messages yet</div>
                            <p class="empty-text">Click refresh to fetch your emails</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $message): ?>
                            <div class="message-item <?= !$message['is_read'] ? 'unread' : '' ?>" 
                                 data-message-id="<?= $message['id'] ?>"
                                 onclick="loadMessage(<?= $message['id'] ?>)">
                                <div class="message-from">
                                    <span><?= htmlspecialchars($message['sender_name'] ?: $message['sender_email']) ?></span>
                                    <span class="message-date"><?= formatDate($message['received_date']) ?></span>
                                </div>
                                <div class="message-subject">
                                    <?= htmlspecialchars($message['subject']) ?>
                                </div>
                                <div class="message-preview">
                                    <?= htmlspecialchars(substr($message['body'], 0, 100)) ?>...
                                </div>
                                <?php if ($message['has_attachments']): ?>
                                    <div class="message-badges">
                                        <span class="badge badge-attachment">
                                            📎 <?= count(json_decode($message['attachment_data'] ?? '[]', true)) ?> attachment(s)
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Message Viewer Panel -->
            <div class="message-viewer-panel" id="messageViewer">
                <div class="viewer-placeholder">
                    <span class="material-icons">drafts</span>
                    <p class="viewer-placeholder-text">Select a message to read</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast">
        <span id="toastMessage"></span>
    </div>

    <script>
        let currentMessageId = null;

        // Auto-fetch on page load
        window.addEventListener('load', function() {
            fetchEmails(false);
        });

        // Refresh button click
        document.getElementById('refreshBtn').addEventListener('click', function() {
            fetchEmails(true);
        });

        // Fetch emails function
        function fetchEmails(forceRefresh = false) {
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
            
            const attachmentsHtml = message.has_attachments ? `
                <div class="attachment-section">
                    <div class="attachment-title">📎 Attachments</div>
                    <div class="attachment-list">
                        ${JSON.parse(message.attachment_data || '[]').map(att => `
                            <div class="attachment-item">
                                <div class="attachment-icon">${att.icon || '📄'}</div>
                                <div class="attachment-info">
                                    <div class="attachment-name">${att.filename}</div>
                                    <div class="attachment-size">${formatBytes(att.size)}</div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            ` : '';
            
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
                    <div class="message-body-section">
                        ${escapeHtml(message.body).replace(/\n/g, '<br>')}
                    </div>
                    ${attachmentsHtml}
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
                    showToast('❌ Error deleting message', 'error');
                }
            })
            .catch(error => {
                showToast('❌ Network error', 'error');
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

        function formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
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
    </script>
</body>
</html>