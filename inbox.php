<?php
/**
 * INBOX - Apple-Inspired Minimalist Design
 * Clean, professional email interface with sidebar integration
 */

session_start();

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

// Auto-sync emails
$syncResult = ['message' => '', 'class' => '', 'count' => 0];

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

if (isset($_SESSION['imap_config']) && $userPassword) {
    try {
        $result = fetchNewMessagesFromSession($userEmail, 100);
        $syncResult['message'] = $result['success'] ? $result['message'] : ($result['error'] ?? 'Sync failed');
        $syncResult['class'] = $result['success'] ? 'success' : 'error';
        $syncResult['count'] = $result['count'] ?? 0;
    } catch (Exception $e) {
        $syncResult['message'] = 'Sync error';
        $syncResult['class'] = 'error';
    }
}

// Fetch messages
function getInboxMessages($userEmail, $limit = 100) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return [];
        
        $stmt = $pdo->prepare("
            SELECT *,
                CASE WHEN is_read = 0 AND TIMESTAMPDIFF(MINUTE, fetched_at, NOW()) <= 5 
                THEN 1 ELSE 0 END as is_new
            FROM inbox_messages 
            WHERE user_email = :email AND is_deleted = 0
            ORDER BY received_date DESC 
            LIMIT :limit
        ");
        $stmt->bindValue(':email', $userEmail, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Inbox fetch error: " . $e->getMessage());
        return [];
    }
}

$messages = getInboxMessages($userEmail);
$unreadCount = getUnreadCount($userEmail);

function formatDate($dateStr) {
    $date = new DateTime($dateStr);
    $now = new DateTime();
    $diff = $now->diff($date);
    
    if ($diff->days === 0) return $date->format('g:i A');
    if ($diff->days === 1) return 'Yesterday';
    if ($diff->days < 7) return $date->format('l');
    return $date->format('M j');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inbox<?= $unreadCount > 0 ? " ($unreadCount)" : '' ?> - SXC MDTS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --apple-blue: #007AFF;
            --apple-gray: #8E8E93;
            --apple-bg: #F2F2F7;
            --border: #E5E5EA;
            --text-primary: #1c1c1e;
            --text-secondary: #52525b;
            --success: #34C759;
            --error: #FF3B30;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--apple-bg);
            color: var(--text-primary);
            overflow: hidden;
            display: flex;
            height: 100vh;
        }

        /* ========== LAYOUT ========== */
        .app-container {
            display: flex;
            flex: 1;
            height: 100vh;
            overflow: hidden;
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }

        /* ========== SYNC BANNER ========== */
        .sync-banner {
            padding: 10px 20px;
            text-align: center;
            font-size: 12px;
            font-weight: 600;
            border-bottom: 1px solid rgba(0,0,0,0.08);
            animation: slideDown 0.3s ease;
        }

        .sync-banner.success {
            background: linear-gradient(135deg, #34C759 0%, #2ea44f 100%);
            color: white;
        }

        .sync-banner.error {
            background: linear-gradient(135deg, #FF3B30 0%, #dc2626 100%);
            color: white;
        }

        @keyframes slideDown {
            from { transform: translateY(-100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* ========== INBOX CONTAINER ========== */
        .inbox-container {
            flex: 1;
            display: flex;
            overflow: hidden;
        }

        /* ========== MESSAGE LIST ========== */
        .message-list-panel {
            width: 360px;
            background: white;
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
        }

        .list-header {
            padding: 24px 20px 16px;
            border-bottom: 1px solid var(--border);
            background: white;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .list-title-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .list-title {
            font-size: 26px;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.6px;
        }

        .badge-group {
            display: flex;
            gap: 6px;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
        }

        .badge.unread {
            background: var(--apple-blue);
            color: white;
        }

        .badge.new {
            background: var(--success);
            color: white;
        }

        .search-bar {
            position: relative;
            margin-bottom: 12px;
        }

        .search-bar input {
            width: 100%;
            padding: 10px 16px 10px 40px;
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            background: #F8F9FA;
            transition: all 0.2s;
        }

        .search-bar input:focus {
            outline: none;
            border-color: var(--apple-blue);
            background: white;
            box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
        }

        .search-bar .material-icons {
            position: absolute;
            left: 12px;
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
            border-radius: 10px;
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

        .message-list::-webkit-scrollbar {
            width: 6px;
        }

        .message-list::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }

        /* ========== MESSAGE ITEM ========== */
        .message-item {
            padding: 16px 20px;
            border-bottom: 1px solid #F2F2F7;
            cursor: pointer;
            transition: background 0.15s;
            position: relative;
        }

        .message-item:hover {
            background: #FAFAFA;
        }

        .message-item.active {
            background: #E8F4FF;
            border-left: 3px solid var(--apple-blue);
        }

        .message-item.unread {
            background: #FAFBFC;
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

        .message-item.unread .message-sender {
            font-weight: 700;
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 6px;
        }

        .message-sender {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            letter-spacing: -0.2px;
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
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .message-preview {
            font-size: 12px;
            color: var(--text-secondary);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .attachment-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            background: #E8F4FF;
            color: var(--apple-blue);
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
            margin-top: 6px;
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

        .message-viewer::-webkit-scrollbar {
            width: 6px;
        }

        .message-viewer::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 10px;
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
            font-size: 64px;
            margin-bottom: 12px;
            opacity: 0.3;
        }

        .viewer-placeholder-text {
            font-size: 15px;
            font-weight: 500;
        }

        .viewer-header {
            padding: 20px 32px;
            border-bottom: 1px solid var(--border);
            background: white;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            position: sticky;
            top: 0;
            z-index: 10;
            backdrop-filter: blur(10px);
        }

        .icon-btn {
            width: 40px;
            height: 40px;
            border: 1px solid var(--border);
            background: white;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .icon-btn:hover {
            background: #F8F9FA;
            border-color: var(--apple-gray);
        }

        .icon-btn.delete:hover {
            background: #FEE;
            border-color: var(--error);
            color: var(--error);
        }

        .viewer-content {
            padding: 32px;
            max-width: 800px;
            width: 100%;
            margin: 0 auto;
        }

        .message-subject-display {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 24px;
            line-height: 1.2;
            letter-spacing: -0.8px;
        }

        .message-meta {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 1px solid var(--border);
        }

        .meta-row {
            display: flex;
            gap: 16px;
        }

        .meta-label {
            font-size: 13px;
            color: var(--text-secondary);
            min-width: 60px;
            font-weight: 500;
        }

        .sender-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }

        .sender-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--apple-blue) 0%, #0051d5 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
            flex-shrink: 0;
        }

        .sender-details {
            flex: 1;
        }

        .sender-name {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
            letter-spacing: -0.2px;
        }

        .sender-email {
            font-size: 13px;
            color: var(--text-secondary);
            margin-top: 2px;
        }

        /* ========== ATTACHMENTS ========== */
        .attachments-section {
            margin: 28px 0;
            padding: 20px;
            background: #F8F9FA;
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
            margin-bottom: 16px;
        }

        .attachments-header .material-icons {
            font-size: 20px;
            color: var(--apple-blue);
        }

        .attachments-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .attachment-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px;
            background: white;
            border-radius: 10px;
            border: 1px solid var(--border);
            transition: all 0.2s;
        }

        .attachment-item:hover {
            border-color: var(--apple-blue);
            box-shadow: 0 2px 12px rgba(0, 122, 255, 0.12);
            transform: translateY(-1px);
        }

        .attachment-icon {
            font-size: 32px;
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
            margin-bottom: 2px;
        }

        .attachment-size {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .download-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--apple-blue);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.2s;
            flex-shrink: 0;
        }

        .download-btn:hover {
            background: #0051d5;
            transform: scale(1.08);
            box-shadow: 0 4px 12px rgba(0, 122, 255, 0.35);
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

        /* ========== TOAST ========== */
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

        /* ========== EMPTY STATE ========== */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--apple-gray);
        }

        .empty-state .material-icons {
            font-size: 72px;
            opacity: 0.3;
            margin-bottom: 16px;
        }

        .empty-state-text {
            font-size: 16px;
            font-weight: 500;
        }

        /* ========== RESPONSIVE ========== */
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

            .viewer-content {
                padding: 24px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <?php if ($syncResult['message']): ?>
            <div class="sync-banner <?= $syncResult['class'] ?>" id="syncBanner">
                <?= htmlspecialchars($syncResult['message']) ?>
            </div>
            <?php endif; ?>

            <div class="inbox-container">
                <!-- Message List -->
                <div class="message-list-panel">
                    <div class="list-header">
                        <div class="list-title-row">
                            <h1 class="list-title">Inbox</h1>
                            <div class="badge-group">
                                <?php if ($unreadCount > 0): ?>
                                <span class="badge unread"><?= $unreadCount ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="search-bar">
                            <span class="material-icons">search</span>
                            <input type="text" placeholder="Search messages..." id="searchInput">
                        </div>

                        <button class="refresh-btn" id="refreshBtn" onclick="refreshInbox()">
                            <span class="material-icons" style="font-size: 18px;">refresh</span>
                            Refresh
                        </button>
                    </div>

                    <div class="message-list" id="messageList">
                        <?php if (empty($messages)): ?>
                            <div class="empty-state">
                                <span class="material-icons">mail_outline</span>
                                <p class="empty-state-text">No messages</p>
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
                                        $attachments = json_decode($msg['attachment_data'], true);
                                        $count = is_array($attachments) ? count($attachments) : 0;
                                        echo $count . ' file' . ($count > 1 ? 's' : '');
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
    </div>

    <div class="toast" id="toast">
        <span id="toastMessage"></span>
    </div>

    <script>
        // Auto-hide sync banner
        setTimeout(() => {
            const banner = document.getElementById('syncBanner');
            if (banner) {
                banner.style.transition = 'opacity 0.5s';
                banner.style.opacity = '0';
                setTimeout(() => banner.remove(), 500);
            }
        }, 4000);

        // Refresh inbox
        function refreshInbox() {
            const btn = document.getElementById('refreshBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="material-icons" style="font-size: 18px;">hourglass_empty</span> Syncing...';
            
            fetch('fetch_inbox_messages.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
            })
            .then(r => r.json())
            .then(result => {
                btn.disabled = false;
                btn.innerHTML = '<span class="material-icons" style="font-size: 18px;">refresh</span> Refresh';
                
                if (result.success) {
                    showToast('✅ ' + result.message, 'success');
                    if (result.count > 0) setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('❌ ' + (result.error || result.message), 'error');
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = '<span class="material-icons" style="font-size: 18px;">refresh</span> Refresh';
                showToast('❌ Network error', 'error');
            });
        }

        // Load message
        function loadMessage(id) {
            document.querySelectorAll('.message-item').forEach(i => i.classList.remove('active'));
            document.querySelector(`[data-message-id="${id}"]`).classList.add('active');
            document.getElementById('messageViewer').classList.add('show');
            
            fetch('get_message.php?id=' + id)
                .then(r => r.json())
                .then(msg => {
                    if (msg.error) {
                        showToast('❌ ' + msg.error, 'error');
                        return;
                    }
                    displayMessage(msg);
                    markAsRead(id);
                })
                .catch(() => showToast('❌ Error loading message', 'error'));
        }

        // Display message
        function displayMessage(msg) {
            let attachmentsHtml = '';
            if (msg.has_attachments && msg.attachment_data) {
                try {
                    const atts = JSON.parse(msg.attachment_data);
                    if (Array.isArray(atts) && atts.length > 0) {
                        attachmentsHtml = `
                            <div class="attachments-section">
                                <div class="attachments-header">
                                    <span class="material-icons">attach_file</span>
                                    <span>${atts.length} Attachment${atts.length > 1 ? 's' : ''}</span>
                                </div>
                                <div class="attachments-list">
                                    ${atts.map(a => `
                                        <div class="attachment-item">
                                            <span class="attachment-icon">${a.icon || '📎'}</span>
                                            <div class="attachment-info">
                                                <div class="attachment-name">${escapeHtml(a.filename)}</div>
                                                <div class="attachment-size">${formatFileSize(a.size)}</div>
                                            </div>
                                            <a href="download_attachment.php?message_id=${msg.id}&filename=${encodeURIComponent(a.filename)}" 
                                               class="download-btn" download="${a.filename}" title="Download">
                                                <span class="material-icons">download</span>
                                            </a>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        `;
                    }
                } catch(e) {}
            }
            
            document.getElementById('messageViewer').innerHTML = `
                <div class="viewer-header">
                    <button class="icon-btn" onclick="window.print()" title="Print">
                        <span class="material-icons">print</span>
                    </button>
                    <button class="icon-btn delete" onclick="deleteMessage(${msg.id})" title="Delete">
                        <span class="material-icons">delete</span>
                    </button>
                </div>
                <div class="viewer-content">
                    <h1 class="message-subject-display">${escapeHtml(msg.subject)}</h1>
                    <div class="message-meta">
                        <div class="meta-row">
                            <span class="meta-label">From</span>
                            <div class="sender-info">
                                <div class="sender-avatar">${msg.sender_email.charAt(0).toUpperCase()}</div>
                                <div class="sender-details">
                                    <div class="sender-name">${escapeHtml(msg.sender_name || msg.sender_email)}</div>
                                    <div class="sender-email">${escapeHtml(msg.sender_email)}</div>
                                </div>
                            </div>
                        </div>
                        <div class="meta-row">
                            <span class="meta-label">Date</span>
                            <span>${formatFullDate(msg.received_date)}</span>
                        </div>
                    </div>
                    ${attachmentsHtml}
                    <div class="message-body-section">${escapeHtml(msg.body).replace(/\n/g, '<br>')}</div>
                </div>
            `;
        }

        // Mark as read
        function markAsRead(id) {
            fetch('mark_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message_id: id })
            })
            .then(r => r.json())
            .then(result => {
                if (result.success) {
                    document.querySelector(`[data-message-id="${id}"]`)?.classList.remove('unread');
                }
            });
        }

        // Delete message
        function deleteMessage(id) {
            if (!confirm('Delete this message?')) return;

            fetch('delete_inbox_message.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message_id: id })
            })
            .then(r => r.json())
            .then(result => {
                if (result.success) {
                    showToast('✅ Message deleted', 'success');
                    document.querySelector(`[data-message-id="${id}"]`)?.remove();
                    document.getElementById('messageViewer').innerHTML = `
                        <div class="viewer-placeholder">
                            <span class="material-icons">drafts</span>
                            <p class="viewer-placeholder-text">Select a message to read</p>
                        </div>
                    `;
                } else {
                    showToast('❌ ' + (result.error || 'Error deleting'), 'error');
                }
            })
            .catch(() => showToast('❌ Network error', 'error'));
        }

        // Search
        document.getElementById('searchInput')?.addEventListener('input', e => {
            const term = e.target.value.toLowerCase();
            document.querySelectorAll('.message-item').forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(term) ? '' : 'none';
            });
        });

        // Helpers
        function showToast(msg, type = 'success') {
            const toast = document.getElementById('toast');
            document.getElementById('toastMessage').textContent = msg;
            toast.className = 'toast ' + type + ' show';
            setTimeout(() => toast.classList.remove('show'), 3000);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatFullDate(dateStr) {
            return new Date(dateStr).toLocaleString('en-US', { 
                year: 'numeric', month: 'long', day: 'numeric',
                hour: '2-digit', minute: '2-digit'
            });
        }

        function formatFileSize(bytes) {
            if (!bytes) return '0 B';
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        }
    </script>
</body>
</html>