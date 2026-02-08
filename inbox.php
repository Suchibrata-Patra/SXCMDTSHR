<?php
/**
 * INBOX PAGE - SPLIT VIEW WITH MESSAGE READER
 * Modern layout with sidebar, message list, and reading pane
 */

session_start();

// Check authentication BEFORE any output
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: login.php");
    exit();
}

require_once 'db_config.php';
require_once 'inbox_functions.php';
require_once 'settings_helper.php';
require_once 'imap_helper.php';

$userEmail = $_SESSION['smtp_user'];

// Load IMAP config to session if not already loaded
if (!isset($_SESSION['imap_config'])) {
    if (isset($_SESSION['smtp_pass'])) {
        loadImapConfigToSession($userEmail, $_SESSION['smtp_pass']);
    } else {
        session_destroy();
        header("Location: login.php?error=session_expired");
        exit();
    }
}

// Check if IMAP settings are configured
$imapConfig = getImapConfigFromSession();
$hasImapSettings = $imapConfig ? true : false;

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'sync':
            if ($hasImapSettings) {
                $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
                $result = fetchNewMessagesFromSession($userEmail, $limit);
                echo json_encode($result);
            } else {
                echo json_encode(['success' => false, 'error' => 'IMAP not configured']);
            }
            exit();
            
        case 'get_message':
            $messageId = isset($_GET['id']) ? intval($_GET['id']) : 0;
            if ($messageId > 0) {
                $pdo = getDatabaseConnection();
                $stmt = $pdo->prepare("SELECT * FROM inbox_messages WHERE id = :id AND user_email = :email");
                $stmt->execute([':id' => $messageId, ':email' => $userEmail]);
                $message = $stmt->fetch();
                
                if ($message) {
                    // Mark as read
                    markMessageAsRead($messageId, $userEmail);
                    echo json_encode(['success' => true, 'message' => $message]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Message not found']);
                }
            }
            exit();
            
        case 'mark_read':
        case 'mark_unread':
            $input = json_decode(file_get_contents('php://input'), true);
            $messageId = intval($input['message_id'] ?? 0);
            
            if ($messageId > 0) {
                $result = ($_GET['action'] === 'mark_read') 
                    ? markMessageAsRead($messageId, $userEmail)
                    : markMessageAsUnread($messageId, $userEmail);
                    
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Status updated' : 'Failed to update'
                ]);
            }
            exit();
            
        case 'delete':
            $input = json_decode(file_get_contents('php://input'), true);
            $messageId = intval($input['message_id'] ?? 0);
            
            if ($messageId > 0) {
                $result = deleteInboxMessage($messageId, $userEmail);
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Message deleted' : 'Failed to delete'
                ]);
            }
            exit();
            
        case 'star':
            $input = json_decode(file_get_contents('php://input'), true);
            $messageId = intval($input['message_id'] ?? 0);
            
            if ($messageId > 0) {
                $result = toggleStarMessage($messageId, $userEmail);
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Star toggled' : 'Failed to toggle star'
                ]);
            }
            exit();
    }
}

// Get filter parameters
$filters = [
    'search' => $_GET['search'] ?? '',
    'unread_only' => isset($_GET['unread']) && $_GET['unread'] === '1',
    'sender' => $_GET['sender'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Get messages and counts
$messages = $hasImapSettings ? getInboxMessages($userEmail, $perPage, $offset, $filters) : [];
$totalMessages = $hasImapSettings ? getInboxMessageCount($userEmail, $filters) : 0;
$unreadCount = $hasImapSettings ? getUnreadCount($userEmail) : 0;
$totalPages = ceil($totalMessages / $perPage);
$lastSyncDate = $hasImapSettings ? getLastSyncDate($userEmail) : null;
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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f5f5f7;
            color: #1c1c1e;
            overflow: hidden;
        }

        .main-container {
            display: flex;
            height: 100vh;
            width: 100vw;
        }

        .content-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .top-header {
            background: white;
            border-bottom: 1px solid #e5e5ea;
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            min-height: 64px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .page-title {
            font-size: 20px;
            font-weight: 600;
            color: #1c1c1e;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .unread-badge {
            background: #007AFF;
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .search-box {
            position: relative;
            width: 300px;
        }

        .search-box input {
            width: 100%;
            padding: 8px 12px 8px 36px;
            border: 1px solid #e5e5ea;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .search-box input:focus {
            outline: none;
            border-color: #007AFF;
            box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
        }

        .search-box .material-icons {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 18px;
            color: #8e8e93;
        }

        .header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-primary {
            background: #007AFF;
            color: white;
        }

        .btn-primary:hover {
            background: #0051D5;
        }

        .split-container {
            flex: 1;
            display: flex;
            overflow: hidden;
        }

        .message-list-panel {
            width: 400px;
            background: white;
            border-right: 1px solid #e5e5ea;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .message-list-header {
            padding: 12px 16px;
            border-bottom: 1px solid #e5e5ea;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .message-count {
            font-size: 13px;
            color: #8e8e93;
        }

        .filter-buttons {
            display: flex;
            gap: 8px;
        }

        .filter-btn {
            padding: 4px 10px;
            border: 1px solid #e5e5ea;
            background: white;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .filter-btn:hover {
            background: #f5f5f7;
        }

        .filter-btn.active {
            background: #007AFF;
            color: white;
            border-color: #007AFF;
        }

        .message-list {
            flex: 1;
            overflow-y: auto;
        }

        .message-list::-webkit-scrollbar {
            width: 6px;
        }

        .message-list::-webkit-scrollbar-track {
            background: transparent;
        }

        .message-list::-webkit-scrollbar-thumb {
            background: #d1d1d6;
            border-radius: 10px;
        }

        .message-item {
            padding: 16px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: background 0.15s;
            position: relative;
        }

        .message-item:hover {
            background: #f9f9f9;
        }

        .message-item.active {
            background: #e3f2fd;
            border-left: 3px solid #007AFF;
        }

        .message-item.unread {
            background: #f0f9ff;
        }

        .message-item.unread .message-subject {
            font-weight: 600;
        }

        .message-sender {
            font-size: 14px;
            font-weight: 600;
            color: #1c1c1e;
            margin-bottom: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .message-time {
            font-size: 12px;
            color: #8e8e93;
        }

        .message-subject {
            font-size: 13px;
            color: #1c1c1e;
            margin-bottom: 4px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .message-preview {
            font-size: 12px;
            color: #8e8e93;
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
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-attachment {
            background: #f5f5f7;
            color: #8e8e93;
        }

        .badge-starred {
            background: #fff9e6;
            color: #ff9500;
        }

        .reader-panel {
            flex: 1;
            background: #fafafa;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .reader-empty {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: #8e8e93;
        }

        .reader-empty .material-icons {
            font-size: 80px;
            margin-bottom: 16px;
            opacity: 0.3;
        }

        .reader-content {
            flex: 1;
            background: white;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .reader-header {
            padding: 24px 32px;
            border-bottom: 1px solid #e5e5ea;
        }

        .reader-subject {
            font-size: 24px;
            font-weight: 600;
            color: #1c1c1e;
            margin-bottom: 16px;
            line-height: 1.3;
        }

        .reader-meta {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 12px;
        }

        .sender-info {
            flex: 1;
        }

        .sender-name {
            font-size: 15px;
            font-weight: 600;
            color: #1c1c1e;
        }

        .sender-email {
            font-size: 13px;
            color: #8e8e93;
        }

        .reader-date {
            font-size: 13px;
            color: #8e8e93;
        }

        .reader-actions {
            display: flex;
            gap: 8px;
            padding: 16px 32px;
            border-bottom: 1px solid #e5e5ea;
            background: #fafafa;
        }

        .action-btn {
            padding: 8px 16px;
            border: 1px solid #e5e5ea;
            background: white;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .action-btn:hover {
            background: #f5f5f7;
            border-color: #d1d1d6;
        }

        .action-btn.danger {
            color: #ff3b30;
        }

        .action-btn.danger:hover {
            background: #fff5f5;
            border-color: #ffcccb;
        }

        .reader-body {
            flex: 1;
            padding: 32px;
            overflow-y: auto;
            line-height: 1.6;
        }

        .reader-body::-webkit-scrollbar {
            width: 8px;
        }

        .reader-body::-webkit-scrollbar-track {
            background: transparent;
        }

        .reader-body::-webkit-scrollbar-thumb {
            background: #d1d1d6;
            border-radius: 10px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #8e8e93;
        }

        .empty-icon {
            font-size: 64px;
            margin-bottom: 16px;
            opacity: 0.3;
        }

        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #007AFF;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .sync-status {
            font-size: 12px;
            color: #8e8e93;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .sync-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #34c759;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 16px 20px;
            margin: 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .warning-box a {
            color: #007AFF;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Include Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Top Header -->
            <div class="top-header">
                <div class="header-left">
                    <div class="page-title">
                        <span class="material-icons">inbox</span>
                        Inbox
                        <?php if ($unreadCount > 0): ?>
                            <span class="unread-badge"><?= $unreadCount ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="search-box">
                        <span class="material-icons">search</span>
                        <input type="text" id="searchInput" placeholder="Search messages...">
                    </div>
                </div>

                <div class="header-actions">
                    <?php if ($lastSyncDate): ?>
                        <div class="sync-status">
                            <span class="sync-dot"></span>
                            Last synced: <?= date('h:i A', strtotime($lastSyncDate)) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($hasImapSettings): ?>
                        <button class="btn btn-primary" onclick="syncMessages()">
                            <span class="material-icons" style="font-size: 18px;">sync</span>
                            Sync
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!$hasImapSettings): ?>
                <div class="warning-box">
                    <span class="material-icons" style="color: #ffc107;">warning</span>
                    <div>
                        <strong>IMAP Not Configured:</strong> 
                        Please configure your mail server settings to receive emails.
                        <a href="imap_settings.php">Configure Now →</a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Split View Container -->
            <div class="split-container">
                <!-- Message List Panel -->
                <div class="message-list-panel">
                    <div class="message-list-header">
                        <div class="message-count">
                            <?= $totalMessages ?> messages
                        </div>
                        <div class="filter-buttons">
                            <button class="filter-btn <?= !$filters['unread_only'] ? 'active' : '' ?>" 
                                    onclick="filterMessages('all')">
                                All
                            </button>
                            <button class="filter-btn <?= $filters['unread_only'] ? 'active' : '' ?>" 
                                    onclick="filterMessages('unread')">
                                Unread
                            </button>
                        </div>
                    </div>

                    <div class="message-list" id="messageList">
                        <?php if (empty($messages)): ?>
                            <div class="empty-state">
                                <div class="empty-icon">📭</div>
                                <h3>No messages</h3>
                                <p>Click "Sync" to fetch your latest emails</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($messages as $msg): ?>
                                <div class="message-item <?= $msg['is_read'] ? '' : 'unread' ?>" 
                                     data-id="<?= $msg['id'] ?>"
                                     onclick="loadMessage(<?= $msg['id'] ?>)">
                                    <div class="message-sender">
                                        <span><?= htmlspecialchars($msg['sender_name'] ?: $msg['sender_email']) ?></span>
                                        <span class="message-time">
                                            <?= date('M d', strtotime($msg['received_date'])) ?>
                                        </span>
                                    </div>
                                    <div class="message-subject">
                                        <?= htmlspecialchars($msg['subject']) ?>
                                    </div>
                                    <div class="message-preview">
                                        <?= htmlspecialchars(substr($msg['body'], 0, 100)) ?>...
                                    </div>
                                    <?php if ($msg['has_attachments'] || $msg['is_starred']): ?>
                                        <div class="message-badges">
                                            <?php if ($msg['has_attachments']): ?>
                                                <span class="badge badge-attachment">
                                                    <span class="material-icons" style="font-size: 14px;">attach_file</span>
                                                    Attachment
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($msg['is_starred']): ?>
                                                <span class="badge badge-starred">
                                                    <span class="material-icons" style="font-size: 14px;">star</span>
                                                    Starred
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Reader Panel -->
                <div class="reader-panel">
                    <div class="reader-empty" id="readerEmpty">
                        <span class="material-icons">mail_outline</span>
                        <h3>Select a message to read</h3>
                        <p>Choose a message from the list to view its contents</p>
                    </div>

                    <div class="reader-content" id="readerContent" style="display: none;">
                        <div class="reader-header">
                            <div class="reader-subject" id="readerSubject"></div>
                            <div class="reader-meta">
                                <div class="sender-info">
                                    <div class="sender-name" id="readerSenderName"></div>
                                    <div class="sender-email" id="readerSenderEmail"></div>
                                </div>
                                <div class="reader-date" id="readerDate"></div>
                            </div>
                        </div>

                        <div class="reader-actions">
                            <button class="action-btn" onclick="replyToMessage()">
                                <span class="material-icons" style="font-size: 18px;">reply</span>
                                Reply
                            </button>
                            <button class="action-btn" onclick="forwardMessage()">
                                <span class="material-icons" style="font-size: 18px;">forward</span>
                                Forward
                            </button>
                            <button class="action-btn" onclick="toggleStar()">
                                <span class="material-icons" style="font-size: 18px;" id="starIcon">star_border</span>
                                <span id="starText">Star</span>
                            </button>
                            <button class="action-btn danger" onclick="deleteMessage()">
                                <span class="material-icons" style="font-size: 18px;">delete</span>
                                Delete
                            </button>
                        </div>

                        <div class="reader-body" id="readerBody"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentMessageId = null;
        let currentMessageData = null;

        function loadMessage(messageId) {
            currentMessageId = messageId;
            
            document.querySelectorAll('.message-item').forEach(item => {
                item.classList.remove('active');
            });
            document.querySelector(`.message-item[data-id="${messageId}"]`)?.classList.add('active');
            
            document.getElementById('readerEmpty').style.display = 'none';
            document.getElementById('readerContent').style.display = 'flex';
            document.getElementById('readerBody').innerHTML = '<div class="loading"><div class="spinner"></div></div>';
            
            fetch(`?action=get_message&id=${messageId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        currentMessageData = data.message;
                        displayMessage(data.message);
                        
                        const messageItem = document.querySelector(`.message-item[data-id="${messageId}"]`);
                        if (messageItem) {
                            messageItem.classList.remove('unread');
                        }
                    } else {
                        alert('Error loading message: ' + data.error);
                    }
                })
                .catch(err => {
                    alert('Network error: ' + err.message);
                });
        }

        function displayMessage(msg) {
            document.getElementById('readerSubject').textContent = msg.subject;
            document.getElementById('readerSenderName').textContent = msg.sender_name || msg.sender_email;
            document.getElementById('readerSenderEmail').textContent = msg.sender_email;
            document.getElementById('readerDate').textContent = formatDate(msg.received_date);
            document.getElementById('readerBody').innerHTML = formatMessageBody(msg.body);
            
            const starIcon = document.getElementById('starIcon');
            const starText = document.getElementById('starText');
            if (msg.is_starred == 1) {
                starIcon.textContent = 'star';
                starText.textContent = 'Unstar';
            } else {
                starIcon.textContent = 'star_border';
                starText.textContent = 'Star';
            }
        }

        function formatMessageBody(body) {
            return body.replace(/\n/g, '<br>');
        }

        function formatDate(dateStr) {
            const date = new Date(dateStr);
            const now = new Date();
            const diff = now - date;
            const hours = Math.floor(diff / 3600000);
            
            if (hours < 24) {
                return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
            } else if (hours < 48) {
                return 'Yesterday ' + date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
            } else {
                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) + 
                       ' ' + date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
            }
        }

        function replyToMessage() {
            if (!currentMessageData) return;
            
            const recipient = currentMessageData.sender_email;
            const subject = 'Re: ' + currentMessageData.subject;
            
            window.location.href = `index.php?reply_to=${encodeURIComponent(recipient)}&subject=${encodeURIComponent(subject)}`;
        }

        function forwardMessage() {
            if (!currentMessageData) return;
            
            const subject = 'Fwd: ' + currentMessageData.subject;
            const body = `\n\n---------- Forwarded message ---------\nFrom: ${currentMessageData.sender_email}\nDate: ${currentMessageData.received_date}\nSubject: ${currentMessageData.subject}\n\n${currentMessageData.body}`;
            
            window.location.href = `index.php?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
        }

        function toggleStar() {
            if (!currentMessageId) return;
            
            fetch('?action=star', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({message_id: currentMessageId})
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const starIcon = document.getElementById('starIcon');
                    const starText = document.getElementById('starText');
                    if (starIcon.textContent === 'star_border') {
                        starIcon.textContent = 'star';
                        starText.textContent = 'Unstar';
                    } else {
                        starIcon.textContent = 'star_border';
                        starText.textContent = 'Star';
                    }
                }
            });
        }

        function deleteMessage() {
            if (!currentMessageId) return;
            
            if (!confirm('Move this message to trash?')) return;
            
            fetch('?action=delete', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({message_id: currentMessageId})
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.querySelector(`.message-item[data-id="${currentMessageId}"]`)?.remove();
                    
                    document.getElementById('readerEmpty').style.display = 'flex';
                    document.getElementById('readerContent').style.display = 'none';
                    
                    currentMessageId = null;
                    currentMessageData = null;
                }
            });
        }

        function syncMessages() {
            const btn = event.target.closest('button');
            btn.disabled = true;
            btn.innerHTML = '<span class="material-icons" style="font-size: 18px;">hourglass_empty</span> Syncing...';

            fetch('?action=sync&limit=50')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert(`✅ ${data.message}`);
                        location.reload();
                    } else {
                        alert(`❌ Error: ${data.error || 'Sync failed'}`);
                        btn.disabled = false;
                        btn.innerHTML = '<span class="material-icons" style="font-size: 18px;">sync</span> Sync';
                    }
                })
                .catch(err => {
                    alert('❌ Network error: ' + err.message);
                    btn.disabled = false;
                    btn.innerHTML = '<span class="material-icons" style="font-size: 18px;">sync</span> Sync';
                });
        }

        function filterMessages(type) {
            if (type === 'unread') {
                window.location.href = '?unread=1';
            } else {
                window.location.href = 'inbox.php';
            }
        }

        document.getElementById('searchInput')?.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            document.querySelectorAll('.message-item').forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(searchTerm) ? 'block' : 'none';
            });
        });
    </script>
</body>
</html>