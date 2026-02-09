<?php
/**
 * ENHANCED INBOX PAGE
 * Features: Auto-refresh, Manual refresh, HTML stripping, Attachment previews
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
        case 'sync':
            if ($hasImapSettings) {
                $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
                $result = fetchNewMessagesFromSession($userEmail, $limit, false);
                echo json_encode($result);
            } else {
                echo json_encode(['success' => false, 'error' => 'IMAP not configured']);
            }
            exit();
            
        case 'force_refresh':
            if ($hasImapSettings) {
                $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
                $result = fetchNewMessagesFromSession($userEmail, $limit, true);
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
            
            echo json_encode([
                'success' => true,
                'messages' => $messages,
                'total' => $totalMessages,
                'unread' => $unreadCount
            ]);
            exit();
    }
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
            font-family: 'Inter', sans-serif;
        }

        .btn-primary {
            background: #007AFF;
            color: white;
        }

        .btn-primary:hover {
            background: #0051D5;
        }

        .btn-secondary {
            background: white;
            color: #1c1c1e;
            border: 1px solid #e5e5ea;
        }

        .btn-secondary:hover {
            background: #f5f5f7;
        }

        .btn-danger {
            background: #ff3b30;
            color: white;
        }

        .btn-danger:hover {
            background: #d32f2f;
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

        .sync-dot.syncing {
            background: #ff9500;
            animation: pulse 0.5s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
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
            flex-wrap: wrap;
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

        /* Attachment Previews */
        .attachment-preview {
            background: #f0f0f5;
            border: 1px solid #e5e5ea;
            border-radius: 6px;
            padding: 6px 10px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            max-width: 150px;
        }

        .attachment-icon {
            font-size: 16px;
        }

        .attachment-name {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
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

        .reader-attachments {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #f0f0f0;
        }

        .attachments-title {
            font-size: 13px;
            font-weight: 600;
            color: #1c1c1e;
            margin-bottom: 12px;
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

        /* Toast Notification */
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
                        <?php if ($unreadCount > 0): ?>
                            <span class="unread-badge" id="unreadBadge"><?= $unreadCount ?></span>
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
                            <span id="syncText">Last synced: <?= date('h:i A', strtotime($lastSyncDate)) ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($hasImapSettings): ?>
                        <button class="btn btn-secondary" onclick="forceRefresh()" title="Force refresh all emails from server">
                            <span class="material-icons" style="font-size: 18px;">refresh</span>
                            Refresh
                        </button>
                        <button class="btn btn-primary" onclick="syncMessages()" id="syncBtn">
                            <span class="material-icons" style="font-size: 18px;">sync</span>
                            Sync New
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
                            <?php foreach ($messages as $msg): 
                                $attachments = $msg['attachment_data'] ? json_decode($msg['attachment_data'], true) : [];
                            ?>
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
                                    <?php if ($msg['has_attachments'] || $msg['is_starred'] || !empty($attachments)): ?>
                                        <div class="message-badges">
                                            <?php if ($msg['is_starred']): ?>
                                                <span class="badge badge-starred">
                                                    <span class="material-icons" style="font-size: 14px;">star</span>
                                                    Starred
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($msg['has_attachments'] && !empty($attachments)): ?>
                                                <?php foreach (array_slice($attachments, 0, 2) as $att): ?>
                                                    <div class="attachment-preview">
                                                        <span class="attachment-icon"><?= $att['icon'] ?? '📎' ?></span>
                                                        <span class="attachment-name"><?= htmlspecialchars($att['filename']) ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                                <?php if (count($attachments) > 2): ?>
                                                    <span class="badge badge-attachment">
                                                        +<?= count($attachments) - 2 ?> more
                                                    </span>
                                                <?php endif; ?>
                                            <?php elseif ($msg['has_attachments']): ?>
                                                <span class="badge badge-attachment">
                                                    <span class="material-icons" style="font-size: 14px;">attach_file</span>
                                                    Attachment
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

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
                            <div id="readerAttachmentsContainer"></div>
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

    <!-- Toast Notification -->
    <div class="toast" id="toast">
        <span class="material-icons" id="toastIcon">info</span>
        <span id="toastMessage"></span>
    </div>

    <script>
        let currentMessageId = null;
        let currentMessageData = null;
        let autoSyncInterval = null;

        // Auto-sync every 30 seconds
        function startAutoSync() {
            autoSyncInterval = setInterval(() => {
                autoSyncMessages();
            }, 30000); // 30 seconds
        }

        // Auto sync (silent, no alerts)
        function autoSyncMessages() {
            const syncDot = document.getElementById('syncDot');
            const syncText = document.getElementById('syncText');
            
            if (syncDot) syncDot.classList.add('syncing');
            if (syncText) syncText.textContent = 'Syncing...';
            
            fetch('?action=sync&limit=50')
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.count > 0) {
                        showToast(`${data.count} new message(s) received`, 'success');
                        refreshMessageList();
                    }
                    
                    if (syncDot) syncDot.classList.remove('syncing');
                    if (syncText) {
                        const now = new Date();
                        syncText.textContent = `Last synced: ${now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })}`;
                    }
                })
                .catch(err => {
                    if (syncDot) syncDot.classList.remove('syncing');
                });
        }

        // Manual sync
        function syncMessages() {
            const btn = document.getElementById('syncBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="material-icons" style="font-size: 18px;">hourglass_empty</span> Syncing...';

            fetch('?action=sync&limit=50')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message, 'success');
                        refreshMessageList();
                    } else {
                        showToast('Error: ' + data.error, 'error');
                    }
                    
                    btn.disabled = false;
                    btn.innerHTML = '<span class="material-icons" style="font-size: 18px;">sync</span> Sync New';
                })
                .catch(err => {
                    showToast('Network error: ' + err.message, 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<span class="material-icons" style="font-size: 18px;">sync</span> Sync New';
                });
        }

        // Force refresh (re-fetch ALL emails)
        function forceRefresh() {
            if (!confirm('This will re-fetch all emails from the server and may take a moment. Continue?')) {
                return;
            }
            
            const btn = event.target.closest('button');
            btn.disabled = true;
            btn.innerHTML = '<span class="material-icons" style="font-size: 18px;">hourglass_empty</span> Refreshing...';

            fetch('?action=force_refresh&limit=100')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message, 'success');
                        location.reload();
                    } else {
                        showToast('Error: ' + data.error, 'error');
                        btn.disabled = false;
                        btn.innerHTML = '<span class="material-icons" style="font-size: 18px;">refresh</span> Refresh';
                    }
                })
                .catch(err => {
                    showToast('Network error: ' + err.message, 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<span class="material-icons" style="font-size: 18px;">refresh</span> Refresh';
                });
        }

        // Refresh message list without page reload
        function refreshMessageList() {
            fetch('?action=get_messages_list')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        updateMessageList(data.messages);
                        updateCounts(data.total, data.unread);
                    }
                });
        }

        // Update message list in UI
        function updateMessageList(messages) {
            const listContainer = document.getElementById('messageList');
            
            if (messages.length === 0) {
                listContainer.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">📭</div>
                        <h3>No messages</h3>
                        <p>Click "Sync" to fetch your latest emails</p>
                    </div>
                `;
                return;
            }
            
            listContainer.innerHTML = messages.map(msg => {
                const attachments = msg.attachment_data ? JSON.parse(msg.attachment_data) : [];
                const unreadClass = msg.is_read ? '' : 'unread';
                const activeClass = (currentMessageId === msg.id) ? 'active' : '';
                
                let attachmentHTML = '';
                if (msg.has_attachments && attachments.length > 0) {
                    attachmentHTML = attachments.slice(0, 2).map(att => `
                        <div class="attachment-preview">
                            <span class="attachment-icon">${att.icon}</span>
                            <span class="attachment-name">${escapeHtml(att.filename)}</span>
                        </div>
                    `).join('');
                    
                    if (attachments.length > 2) {
                        attachmentHTML += `<span class="badge badge-attachment">+${attachments.length - 2} more</span>`;
                    }
                }
                
                const starBadge = msg.is_starred ? `
                    <span class="badge badge-starred">
                        <span class="material-icons" style="font-size: 14px;">star</span>
                        Starred
                    </span>
                ` : '';
                
                return `
                    <div class="message-item ${unreadClass} ${activeClass}" 
                         data-id="${msg.id}"
                         onclick="loadMessage(${msg.id})">
                        <div class="message-sender">
                            <span>${escapeHtml(msg.sender_name || msg.sender_email)}</span>
                            <span class="message-time">${formatShortDate(msg.received_date)}</span>
                        </div>
                        <div class="message-subject">${escapeHtml(msg.subject)}</div>
                        <div class="message-preview">${escapeHtml(msg.body.substring(0, 100))}...</div>
                        ${(starBadge || attachmentHTML) ? `<div class="message-badges">${starBadge}${attachmentHTML}</div>` : ''}
                    </div>
                `;
            }).join('');
        }

        // Update counts
        function updateCounts(total, unread) {
            document.getElementById('messageCount').textContent = `${total} messages`;
            const badge = document.getElementById('unreadBadge');
            if (unread > 0) {
                if (badge) {
                    badge.textContent = unread;
                } else {
                    const titleEl = document.querySelector('.page-title');
                    titleEl.innerHTML += `<span class="unread-badge" id="unreadBadge">${unread}</span>`;
                }
            } else if (badge) {
                badge.remove();
            }
        }

        // Load message
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

        // Format short date
        function formatShortDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
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

        // Start auto-sync on page load
        window.addEventListener('load', () => {
            startAutoSync();
        });

        // Clear interval on page unload
        window.addEventListener('beforeunload', () => {
            if (autoSyncInterval) {
                clearInterval(autoSyncInterval);
            }
        });
    </script>
</body>
</html>