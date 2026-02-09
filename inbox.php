<?php
/**
 * ENHANCED INBOX PAGE v2.0
 * Features: 
 * - Single "Check Mail" refresh button
 * - Visual distinction between NEW (fetched <5min ago) and UNREAD emails
 * - Auto-sync capability
 * - Attachment download support
 */

session_start();

if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: login.php");
    exit();
}

require_once 'db_config.php';
require_once 'inbox_functions.php';
require_once 'settings_helper.php';
require_once 'imap_helper.php';

$userEmail = $_SESSION['smtp_user'];

if (!isset($_SESSION['imap_config'])) {
    if (isset($_SESSION['smtp_pass'])) {
        loadImapConfigToSession($userEmail, $_SESSION['smtp_pass']);
    } else {
        session_destroy();
        header("Location: login.php?error=session_expired");
        exit();
    }
}

$imapConfig = getImapConfigFromSession();
$hasImapSettings = $imapConfig ? true : false;

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'check_mail':
            // Single check mail function - fetches new messages
            if ($hasImapSettings) {
                $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
                $result = fetchNewMessagesFromSession($userEmail, $limit, false);
                
                // Get updated counts
                $newCount = getNewCount($userEmail);
                $unreadCount = getUnreadCount($userEmail);
                
                $result['new_count'] = $newCount;
                $result['unread_count'] = $unreadCount;
                
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
                    markMessageAsRead($messageId, $userEmail);
                    
                    // Parse attachment data
                    if ($message['attachment_data']) {
                        $message['attachments'] = json_decode($message['attachment_data'], true);
                    } else {
                        $message['attachments'] = [];
                    }
                    
                    echo json_encode(['success' => true, 'message' => $message]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Message not found']);
                }
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
            
        case 'get_messages_list':
            $messages = $hasImapSettings ? getInboxMessages($userEmail, 50, 0, []) : [];
            $totalMessages = $hasImapSettings ? getInboxMessageCount($userEmail, []) : 0;
            $unreadCount = $hasImapSettings ? getUnreadCount($userEmail) : 0;
            $newCount = $hasImapSettings ? getNewCount($userEmail) : 0;
            
            echo json_encode([
                'success' => true,
                'messages' => $messages,
                'total' => $totalMessages,
                'unread' => $unreadCount,
                'new' => $newCount
            ]);
            exit();
    }
}
function getImapConfigFromSession() {
    if (isset($_SESSION['imap_config'])) {
        return $_SESSION['imap_config'];
    }
    return false;
}
$filters = [
    'search' => $_GET['search'] ?? '',
    'unread_only' => isset($_GET['unread']) && $_GET['unread'] === '1'
];

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

$messages = $hasImapSettings ? getInboxMessages($userEmail, $perPage, $offset, $filters) : [];
$totalMessages = $hasImapSettings ? getInboxMessageCount($userEmail, $filters) : 0;
$unreadCount = $hasImapSettings ? getUnreadCount($userEmail) : 0;
$newCount = $hasImapSettings ? getNewCount($userEmail) : 0;
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

        .badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .unread-badge {
            background: #007AFF;
            color: white;
        }

        .new-badge {
            background: #34C759;
            color: white;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
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
            align-items: center;
            gap: 12px;
        }

        .check-mail-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #007AFF;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .check-mail-btn:hover {
            background: #0051D5;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 122, 255, 0.3);
        }

        .check-mail-btn:disabled {
            background: #d1d1d6;
            cursor: not-allowed;
            transform: none;
        }

        .check-mail-btn .material-icons {
            font-size: 20px;
        }

        .check-mail-btn.checking .material-icons {
            animation: rotate 1s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .sync-status {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: #8e8e93;
        }

        .sync-dot {
            width: 8px;
            height: 8px;
            background: #34c759;
            border-radius: 50%;
        }

        .split-container {
            display: flex;
            flex: 1;
            overflow: hidden;
        }

        .message-list-panel {
            width: 400px;
            border-right: 1px solid #e5e5ea;
            display: flex;
            flex-direction: column;
            background: white;
        }

        .message-list-header {
            padding: 16px 20px;
            border-bottom: 1px solid #e5e5ea;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .message-count {
            font-size: 14px;
            font-weight: 600;
            color: #52525b;
        }

        .filter-buttons {
            display: flex;
            gap: 8px;
        }

        .filter-btn {
            padding: 6px 12px;
            border: 1px solid #e5e5ea;
            background: white;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
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

        .message-item {
            padding: 16px 20px;
            border-bottom: 1px solid #f3f4f6;
            cursor: pointer;
            transition: background 0.2s;
            position: relative;
        }

        .message-item:hover {
            background: #f9fafb;
        }

        .message-item.active {
            background: #eff6ff;
            border-left: 3px solid #007AFF;
        }

        /* NEW EMAIL STYLING - Green left border + bold */
        .message-item.new {
            background: #f0fdf4;
            border-left: 4px solid #34C759;
            font-weight: 600;
        }

        .message-item.new:hover {
            background: #dcfce7;
        }

        .message-item.new .message-sender {
            color: #166534;
            font-weight: 700;
        }

        .message-item.new .new-indicator {
            position: absolute;
            top: 16px;
            right: 16px;
            background: #34C759;
            color: white;
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }

        /* UNREAD EMAIL STYLING - Blue dot + bold */
        .message-item.unread {
            font-weight: 600;
        }

        .message-item.unread::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 50%;
            transform: translateY(-50%);
            width: 8px;
            height: 8px;
            background: #007AFF;
            border-radius: 50%;
        }

        .message-item.unread .message-sender {
            color: #1c1c1e;
            font-weight: 700;
        }

        /* READ EMAIL STYLING - Normal weight */
        .message-item.read {
            font-weight: 400;
        }

        .message-item.read .message-sender {
            color: #52525b;
        }

        .message-sender {
            font-size: 14px;
            margin-bottom: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
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

        .message-date {
            font-size: 12px;
            color: #8e8e93;
        }

        .message-reader {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #fafafa;
        }

        .reader-empty {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #8e8e93;
        }

        .reader-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: white;
            display: none;
        }

        .reader-header {
            padding: 24px 32px;
            border-bottom: 1px solid #e5e5ea;
        }

        .reader-subject {
            font-size: 24px;
            font-weight: 700;
            color: #1c1c1e;
            margin-bottom: 16px;
        }

        .reader-meta {
            display: flex;
            gap: 16px;
            font-size: 14px;
            color: #52525b;
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

        .reader-attachments {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e5e5ea;
        }

        .attachments-title {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 12px;
            color: #52525b;
        }

        .attachments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 12px;
        }

        .attachment-card {
            background: #f9f9f9;
            border: 1px solid #e5e5ea;
            border-radius: 8px;
            padding: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            text-align: center;
            transition: all 0.2s;
            text-decoration: none;
            color: inherit;
            position: relative;
            cursor: pointer;
        }

        .attachment-card:hover {
            background: #f0f0f5;
            border-color: #007AFF;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 122, 255, 0.15);
        }

        .attachment-card-icon {
            font-size: 32px;
        }

        .attachment-card-name {
            font-size: 11px;
            color: #1c1c1e;
            font-weight: 500;
            word-break: break-word;
            line-height: 1.3;
        }

        .attachment-card-size {
            font-size: 10px;
            color: #8e8e93;
        }

        .attachment-card-download {
            position: absolute;
            top: 8px;
            right: 8px;
            background: #007AFF;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .attachment-card-download .material-icons {
            font-size: 16px;
        }

        .attachment-card:hover .attachment-card-download {
            opacity: 1;
        }

        .warning-box {
            background: #fff7ed;
            border-left: 4px solid #f59e0b;
            padding: 16px 20px;
            margin: 16px 24px;
            border-radius: 8px;
            display: flex;
            gap: 12px;
        }

        .toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #1c1c1e;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            display: none;
            align-items: center;
            gap: 10px;
            z-index: 10000;
            animation: slideIn 0.3s ease-out;
        }

        .toast.show {
            display: flex;
        }

        .toast.success {
            background: #34c759;
        }

        .toast.error {
            background: #ff3b30;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
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
    <div class="main-container">
        <?php include 'sidebar.php'; ?>

        <div class="content-area">
            <div class="top-header">
                <div class="header-left">
                    <div class="page-title">
                        <span class="material-icons">inbox</span>
                        Inbox
                        <?php if ($newCount > 0): ?>
                            <span class="badge new-badge" id="newBadge"><?= $newCount ?> new</span>
                        <?php endif; ?>
                        <?php if ($unreadCount > 0): ?>
                            <span class="badge unread-badge" id="unreadBadge"><?= $unreadCount ?> unread</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="search-box">
                        <span class="material-icons">search</span>
                        <input type="text" id="searchInput" placeholder="Search messages...">
                    </div>
                </div>

                <div class="header-actions">
                    <?php if ($lastSyncDate): ?>
                        <div class="sync-status" id="syncStatus">
                            <span class="sync-dot" id="syncDot"></span>
                            <span id="syncText">Last checked: <?= date('h:i A', strtotime($lastSyncDate)) ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($hasImapSettings): ?>
                        <button class="check-mail-btn" onclick="checkForNewMail()" id="checkMailBtn">
                            <span class="material-icons" id="checkMailIcon">mail_outline</span>
                            <span id="checkMailText">Check Mail</span>
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

            <div class="split-container">
                <div class="message-list-panel">
                    <div class="message-list-header">
                        <div class="message-count" id="messageCount">
                            <?= $totalMessages ?> messages
                        </div>
                        <div class="filter-buttons">
                            <button class="filter-btn <?= !isset($_GET['unread']) ? 'active' : '' ?>" 
                                    onclick="filterMessages('all')">All</button>
                            <button class="filter-btn <?= isset($_GET['unread']) ? 'active' : '' ?>" 
                                    onclick="filterMessages('unread')">Unread</button>
                        </div>
                    </div>

                    <div class="message-list" id="messageList">
                        <?php if (empty($messages)): ?>
                            <div style="text-align: center; padding: 60px 20px; color: #8e8e93;">
                                <div style="font-size: 64px; margin-bottom: 16px; opacity: 0.3;">📭</div>
                                <p><strong>No messages</strong></p>
                                <p style="font-size: 14px; margin-top: 8px;">
                                    <?= $hasImapSettings ? 'Click "Check Mail" to fetch your latest emails' : 'Configure IMAP to receive emails' ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($messages as $msg): ?>
                                <?php
                                // Determine message class: new, unread, or read
                                $messageClass = 'message-item';
                                if ($msg['is_new'] == 1) {
                                    $messageClass .= ' new';
                                } elseif ($msg['is_read'] == 0) {
                                    $messageClass .= ' unread';
                                } else {
                                    $messageClass .= ' read';
                                }
                                ?>
                                <div class="<?= $messageClass ?>" 
                                     data-id="<?= $msg['id'] ?>" 
                                     onclick="loadMessage(<?= $msg['id'] ?>)">
                                    
                                    <?php if ($msg['is_new'] == 1): ?>
                                        <span class="new-indicator">New</span>
                                    <?php endif; ?>
                                    
                                    <div class="message-sender">
                                        <span><?= htmlspecialchars($msg['sender_name'] ?: $msg['sender_email']) ?></span>
                                        <span class="message-date"><?= date('M d', strtotime($msg['received_date'])) ?></span>
                                    </div>
                                    <div class="message-subject">
                                        <?= htmlspecialchars($msg['subject'] ?: '(No Subject)') ?>
                                        <?php if ($msg['has_attachments']): ?>
                                            <span class="material-icons" style="font-size: 14px; vertical-align: middle;">attach_file</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="message-preview">
                                        <?= htmlspecialchars(substr($msg['body'], 0, 100)) ?>...
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="message-reader">
                    <div class="reader-empty" id="readerEmpty" style="display: flex;">
                        <span class="material-icons" style="font-size: 64px; color: #e5e5ea; margin-bottom: 16px;">mail_outline</span>
                        <p style="font-size: 18px; font-weight: 500; margin-bottom: 8px;">Select a message to read</p>
                        <p style="font-size: 14px;">Choose a message from the list to view its contents</p>
                    </div>

                    <div class="reader-content" id="readerContent">
                        <div class="reader-header">
                            <h1 class="reader-subject" id="readerSubject"></h1>
                            <div class="reader-meta">
                                <div>
                                    <strong id="readerSenderName"></strong>
                                    &lt;<span id="readerSenderEmail"></span>&gt;
                                </div>
                                <div id="readerDate"></div>
                            </div>
                        </div>

                        <div class="reader-actions">
                            <button class="action-btn" onclick="replyToMessage()">
                                <span class="material-icons" style="font-size: 16px;">reply</span>
                                Reply
                            </button>
                            <button class="action-btn" onclick="forwardMessage()">
                                <span class="material-icons" style="font-size: 16px;">forward</span>
                                Forward
                            </button>
                            <button class="action-btn" onclick="toggleStar()">
                                <span class="material-icons" id="starIcon" style="font-size: 16px;">star_border</span>
                                <span id="starText">Star</span>
                            </button>
                            <button class="action-btn danger" onclick="deleteMessage()">
                                <span class="material-icons" style="font-size: 16px;">delete</span>
                                Delete
                            </button>
                        </div>

                        <div class="reader-body" id="readerBody">
                            <div id="readerAttachmentsContainer"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast">
        <span class="material-icons" id="toastIcon">info</span>
        <span id="toastMessage"></span>
    </div>

    <script>
        let currentMessageId = null;
        let currentMessageData = null;
        let isChecking = false;

        // Check for new mail
        function checkForNewMail() {
            if (isChecking) return;
            
            isChecking = true;
            const btn = document.getElementById('checkMailBtn');
            const icon = document.getElementById('checkMailIcon');
            const text = document.getElementById('checkMailText');
            
            btn.disabled = true;
            btn.classList.add('checking');
            icon.textContent = 'refresh';
            text.textContent = 'Checking...';
            
            fetch('?action=check_mail')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // Update counts
                        updateBadges(data.new_count, data.unread_count);
                        
                        // Reload messages list
                        reloadMessagesList();
                        
                        // Show success message
                        if (data.count > 0) {
                            showToast(`${data.count} new message(s) received!`, 'success');
                        } else {
                            showToast('No new messages', 'info');
                        }
                        
                        // Update sync status
                        updateSyncStatus();
                    } else {
                        showToast('Error: ' + data.error, 'error');
                    }
                })
                .catch(err => {
                    showToast('Network error: ' + err.message, 'error');
                })
                .finally(() => {
                    isChecking = false;
                    btn.disabled = false;
                    btn.classList.remove('checking');
                    icon.textContent = 'mail_outline';
                    text.textContent = 'Check Mail';
                });
        }

        // Update badges
        function updateBadges(newCount, unreadCount) {
            const pageTitle = document.querySelector('.page-title');
            
            // Remove existing badges
            const existingBadges = pageTitle.querySelectorAll('.badge');
            existingBadges.forEach(badge => badge.remove());
            
            // Add new count badge if > 0
            if (newCount > 0) {
                const newBadge = document.createElement('span');
                newBadge.className = 'badge new-badge';
                newBadge.id = 'newBadge';
                newBadge.textContent = newCount + ' new';
                pageTitle.appendChild(newBadge);
            }
            
            // Add unread badge if > 0
            if (unreadCount > 0) {
                const unreadBadge = document.createElement('span');
                unreadBadge.className = 'badge unread-badge';
                unreadBadge.id = 'unreadBadge';
                unreadBadge.textContent = unreadCount + ' unread';
                pageTitle.appendChild(unreadBadge);
            }
        }

        // Reload messages list
        function reloadMessagesList() {
            fetch('?action=get_messages_list')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // Update message count
                        document.getElementById('messageCount').textContent = data.total + ' messages';
                        
                        // Reload page to show new messages with proper styling
                        location.reload();
                    }
                });
        }

        // Update sync status
        function updateSyncStatus() {
            const syncText = document.getElementById('syncText');
            if (syncText) {
                const now = new Date();
                const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                syncText.textContent = 'Last checked: ' + timeStr;
            }
        }

        // Load message
        function loadMessage(messageId) {
            currentMessageId = messageId;
            
            fetch('?action=get_message&id=' + messageId)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        currentMessageData = data.message;
                        displayMessage(data.message);
                        
                        const messageItem = document.querySelector(`.message-item[data-id="${messageId}"]`);
                        if (messageItem) {
                            // Remove all active classes
                            document.querySelectorAll('.message-item').forEach(item => {
                                item.classList.remove('active');
                            });
                            
                            // Add active to current
                            messageItem.classList.add('active');
                            
                            // Change from unread/new to read
                            messageItem.classList.remove('unread', 'new');
                            messageItem.classList.add('read');
                            
                            // Remove new indicator
                            const newIndicator = messageItem.querySelector('.new-indicator');
                            if (newIndicator) {
                                newIndicator.remove();
                            }
                            
                            // Update sender styling
                            const sender = messageItem.querySelector('.message-sender span');
                            if (sender) {
                                sender.style.color = '#52525b';
                                sender.style.fontWeight = '400';
                            }
                        }
                        
                        // Update counts (decrease unread)
                        const unreadBadge = document.getElementById('unreadBadge');
                        if (unreadBadge) {
                            const currentCount = parseInt(unreadBadge.textContent);
                            const newCount = currentCount - 1;
                            if (newCount > 0) {
                                unreadBadge.textContent = newCount + ' unread';
                            } else {
                                unreadBadge.remove();
                            }
                        }
                    } else {
                        showToast('Error loading message: ' + data.error, 'error');
                    }
                })
                .catch(err => {
                    showToast('Network error: ' + err.message, 'error');
                });
        }

        // Display message
        function displayMessage(msg) {
            document.getElementById('readerSubject').textContent = msg.subject;
            document.getElementById('readerSenderName').textContent = msg.sender_name || msg.sender_email;
            document.getElementById('readerSenderEmail').textContent = msg.sender_email;
            document.getElementById('readerDate').textContent = formatDate(msg.received_date);
            document.getElementById('readerBody').innerHTML = formatMessageBody(msg.body);
            
            // Display attachments
            const attachContainer = document.getElementById('readerAttachmentsContainer');
            if (msg.attachments && msg.attachments.length > 0) {
                const attachHTML = `
                    <div class="reader-attachments">
                        <div class="attachments-title">📎 Attachments (${msg.attachments.length})</div>
                        <div class="attachments-grid">
                            ${msg.attachments.map((att, index) => {
                                const downloadUrl = `download_attachment.php?inbox_id=${msg.id}-${index}`;
                                return `
                                    <a href="${downloadUrl}" class="attachment-card" download>
                                        <div class="attachment-card-icon">${att.icon}</div>
                                        <div class="attachment-card-name">${escapeHtml(att.filename)}</div>
                                        <div class="attachment-card-size">${formatFileSize(att.size)}</div>
                                        <div class="attachment-card-download">
                                            <span class="material-icons">download</span>
                                        </div>
                                    </a>
                                `;
                            }).join('')}
                        </div>
                    </div>
                `;
                attachContainer.innerHTML = attachHTML;
            } else {
                attachContainer.innerHTML = '';
            }
            
            // Update star icon
            const starIcon = document.getElementById('starIcon');
            const starText = document.getElementById('starText');
            if (msg.is_starred == 1) {
                starIcon.textContent = 'star';
                starText.textContent = 'Unstar';
            } else {
                starIcon.textContent = 'star_border';
                starText.textContent = 'Star';
            }
            
            // Show reader
            document.getElementById('readerEmpty').style.display = 'none';
            document.getElementById('readerContent').style.display = 'flex';
        }

        // Format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
        }

        // Format message body
        function formatMessageBody(body) {
            return escapeHtml(body).replace(/\n/g, '<br>');
        }

        // Format date
        function formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', { 
                month: 'short', 
                day: 'numeric', 
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit'
            });
        }

        // Escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Reply
        function replyToMessage() {
            if (!currentMessageData) return;
            const recipient = currentMessageData.sender_email;
            const subject = 'Re: ' + currentMessageData.subject;
            window.location.href = `index.php?reply_to=${encodeURIComponent(recipient)}&subject=${encodeURIComponent(subject)}`;
        }

        // Forward
        function forwardMessage() {
            if (!currentMessageData) return;
            const subject = 'Fwd: ' + currentMessageData.subject;
            const body = `\n\n---------- Forwarded message ---------\nFrom: ${currentMessageData.sender_email}\nDate: ${currentMessageData.received_date}\nSubject: ${currentMessageData.subject}\n\n${currentMessageData.body}`;
            window.location.href = `index.php?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
        }

        // Toggle star
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

        // Delete
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
                    showToast('Message moved to trash', 'success');
                }
            });
        }

        // Filter messages
        function filterMessages(type) {
            if (type === 'unread') {
                window.location.href = '?unread=1';
            } else {
                window.location.href = 'inbox.php';
            }
        }

        // Search
        document.getElementById('searchInput')?.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            document.querySelectorAll('.message-item').forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(searchTerm) ? 'block' : 'none';
            });
        });

        // Show toast notification
        function showToast(message, type = 'info') {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toastMessage');
            const toastIcon = document.getElementById('toastIcon');
            
            toast.className = `toast ${type}`;
            toastMessage.textContent = message;
            
            if (type === 'success') {
                toastIcon.textContent = 'check_circle';
            } else if (type === 'error') {
                toastIcon.textContent = 'error';
            } else {
                toastIcon.textContent = 'info';
            }
            
            toast.classList.add('show');
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 4000);
        }
    </script>
</body>
</html>