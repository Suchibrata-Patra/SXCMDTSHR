<?php
/**
 * INBOX PAGE - FIXED VERSION
 * Displays received emails from IMAP server
 */

// Start session FIRST
session_start();

// Check authentication BEFORE any output
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: login.php");
    exit();
}

// NOW require files (after session check)
require_once 'db_config.php';
require_once 'inbox_functions.php';
require_once 'settings_helper.php';
require_once 'imap_helper.php';

$userEmail = $_SESSION['smtp_user'];

// Load IMAP config to session if not already loaded
if (!isset($_SESSION['imap_config'])) {
    // Load from database
    if (isset($_SESSION['smtp_pass'])) {
        loadImapConfigToSession($userEmail, $_SESSION['smtp_pass']);
    } else {
        // Password not in session - require re-login
        session_destroy();
        header("Location: login.php?error=session_expired");
        exit();
    }
}

// Check if IMAP settings are configured
$imapConfig = getImapConfigFromSession();
if (!$imapConfig) {
    $hasImapSettings = false;
    $errorMessage = "IMAP settings not configured. Please configure your mail server settings first.";
} else {
    $hasImapSettings = true;
    $errorMessage = null;
}

// Handle AJAX sync request
if (isset($_GET['action']) && $_GET['action'] === 'sync' && $hasImapSettings) {
    header('Content-Type: application/json');
    
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    $result = fetchNewMessagesFromSession($userEmail, $limit);
    
    echo json_encode($result);
    exit();
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

// Get last sync info
$lastSyncDate = $hasImapSettings ? getLastSyncDate($userEmail) : null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inbox - SXC MDTS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f5f5f7;
            color: #1c1c1e;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 600;
        }

        .header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 14px;
        }

        .btn-primary {
            background: #007AFF;
            color: white;
        }

        .btn-primary:hover {
            background: #0051D5;
        }

        .btn-secondary {
            background: #f5f5f7;
            color: #1c1c1e;
        }

        .btn-secondary:hover {
            background: #e5e5ea;
        }

        .stats-bar {
            background: white;
            padding: 15px 30px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }

        .stats {
            display: flex;
            gap: 30px;
        }

        .stat-item {
            font-size: 14px;
            color: #8e8e93;
        }

        .stat-value {
            font-weight: 600;
            color: #1c1c1e;
            margin-left: 6px;
        }

        .sync-info {
            font-size: 12px;
            color: #8e8e93;
        }

        .error-banner {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .error-banner a {
            color: #007AFF;
            text-decoration: none;
            font-weight: 600;
        }

        .messages-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            overflow: hidden;
        }

        .message-item {
            padding: 20px 30px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: background 0.2s;
        }

        .message-item:hover {
            background: #f9f9f9;
        }

        .message-item.unread {
            background: #f0f9ff;
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }

        .sender-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .sender-name {
            font-weight: 600;
            font-size: 15px;
        }

        .sender-email {
            font-size: 13px;
            color: #8e8e93;
        }

        .message-date {
            font-size: 13px;
            color: #8e8e93;
        }

        .message-subject {
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 6px;
        }

        .message-preview {
            font-size: 13px;
            color: #8e8e93;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-unread {
            background: #007AFF;
            color: white;
        }

        .badge-attachment {
            background: #f5f5f7;
            color: #8e8e93;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #8e8e93;
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            padding: 20px;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #8e8e93;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #007AFF;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 16px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📥 Inbox</h1>
            <div class="header-actions">
                <?php if ($hasImapSettings): ?>
                    <button class="btn btn-primary" onclick="syncMessages()">
                        🔄 Sync Now
                    </button>
                <?php endif; ?>
                <a href="index.php" class="btn btn-secondary">← Back to Compose</a>
                <a href="settings.php" class="btn btn-secondary">⚙️ Settings</a>
            </div>
        </div>

        <?php if (!$hasImapSettings): ?>
            <div class="error-banner">
                <span>⚠️</span>
                <div>
                    <strong>IMAP Not Configured:</strong> 
                    <?= htmlspecialchars($errorMessage) ?>
                    <a href="settings.php">Configure Now →</a>
                </div>
            </div>
        <?php else: ?>
            <div class="stats-bar">
                <div class="stats">
                    <div class="stat-item">
                        Total: <span class="stat-value"><?= $totalMessages ?></span>
                    </div>
                    <div class="stat-item">
                        Unread: <span class="stat-value"><?= $unreadCount ?></span>
                    </div>
                    <div class="stat-item">
                        Page: <span class="stat-value"><?= $page ?> / <?= max(1, $totalPages) ?></span>
                    </div>
                </div>
                <div class="sync-info">
                    <?php if ($lastSyncDate): ?>
                        Last synced: <?= date('M d, Y h:i A', strtotime($lastSyncDate)) ?>
                    <?php else: ?>
                        Never synced
                    <?php endif; ?>
                </div>
            </div>

            <div class="messages-container">
                <?php if (empty($messages)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📭</div>
                        <h3>No messages found</h3>
                        <p>Click "Sync Now" to fetch your latest emails</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $message): ?>
                        <div class="message-item <?= $message['is_read'] ? '' : 'unread' ?>" 
                             onclick="viewMessage(<?= $message['id'] ?>)">
                            <div class="message-header">
                                <div class="sender-info">
                                    <div class="sender-name">
                                        <?= htmlspecialchars($message['sender_name'] ?: $message['sender_email']) ?>
                                        <?php if (!$message['is_read']): ?>
                                            <span class="badge badge-unread">New</span>
                                        <?php endif; ?>
                                        <?php if ($message['has_attachments']): ?>
                                            <span class="badge badge-attachment">📎</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="sender-email">
                                        <?= htmlspecialchars($message['sender_email']) ?>
                                    </div>
                                </div>
                                <div class="message-date">
                                    <?= date('M d, Y h:i A', strtotime($message['received_date'])) ?>
                                </div>
                            </div>
                            <div class="message-subject">
                                <?= htmlspecialchars($message['subject']) ?>
                            </div>
                            <div class="message-preview">
                                <?= htmlspecialchars(substr($message['body'], 0, 150)) ?>...
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>" class="btn btn-secondary">← Previous</a>
                            <?php endif; ?>
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page + 1 ?>" class="btn btn-secondary">Next →</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function syncMessages() {
            const btn = event.target;
            btn.disabled = true;
            btn.innerHTML = '⏳ Syncing...';

            fetch('?action=sync&limit=50')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert(`✅ ${data.message}`);
                        location.reload();
                    } else {
                        alert(`❌ Error: ${data.error || 'Sync failed'}`);
                        btn.disabled = false;
                        btn.innerHTML = '🔄 Sync Now';
                    }
                })
                .catch(err => {
                    alert('❌ Network error: ' + err.message);
                    btn.disabled = false;
                    btn.innerHTML = '🔄 Sync Now';
                });
        }

        function viewMessage(messageId) {
            // Mark as read
            fetch('mark_read.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({message_id: messageId, action: 'read'})
            });

            // TODO: Open message in modal or new page
            alert('Message viewer coming soon! Message ID: ' + messageId);
        }
    </script>
</body>
</html>