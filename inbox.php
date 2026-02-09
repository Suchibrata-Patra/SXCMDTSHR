<?php
/**
 * INBOX PAGE - AUTO-FETCH ENABLED
 * Automatically fetches emails on page load
 * Manual refresh button included
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inbox - SXC MDTS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Inter', sans-serif;
            background: #f5f5f7;
            color: #1c1c1e;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .inbox-header {
            background: white;
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }

        .inbox-title {
            font-size: 28px;
            font-weight: 700;
            color: #1c1c1e;
        }

        .inbox-actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #007AFF;
            color: white;
        }

        .btn-primary:hover {
            background: #0051D5;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 122, 255, 0.3);
        }

        .btn-secondary {
            background: #f0f0f0;
            color: #1c1c1e;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .sync-status {
            background: white;
            padding: 16px 24px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }

        .sync-info {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            color: #8e8e93;
        }

        .spinner {
            width: 20px;
            height: 20px;
            border: 3px solid #f0f0f0;
            border-top-color: #007AFF;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .messages-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            overflow: hidden;
        }

        .message-item {
            padding: 20px 24px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: background 0.2s;
        }

        .message-item:hover {
            background: #f9f9f9;
        }

        .message-item:last-child {
            border-bottom: none;
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }

        .message-from {
            font-weight: 600;
            font-size: 15px;
            color: #1c1c1e;
        }

        .message-sender-email {
            font-size: 13px;
            color: #8e8e93;
            margin-left: 8px;
        }

        .message-date {
            font-size: 13px;
            color: #8e8e93;
        }

        .message-subject {
            font-size: 14px;
            font-weight: 600;
            color: #1c1c1e;
            margin-bottom: 6px;
        }

        .message-preview {
            font-size: 14px;
            color: #8e8e93;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .message-meta {
            display: flex;
            gap: 12px;
            margin-top: 8px;
            align-items: center;
        }

        .attachment-badge {
            font-size: 12px;
            background: #f0f9ff;
            color: #0284c7;
            padding: 4px 10px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #8e8e93;
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 16px;
        }

        .empty-state-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #1c1c1e;
        }

        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 16px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 9999;
            display: none;
            align-items: center;
            gap: 12px;
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
    <div class="container">
        <!-- Header -->
        <div class="inbox-header">
            <h1 class="inbox-title">📬 Inbox</h1>
            <div class="inbox-actions">
                <button class="btn btn-secondary" id="refreshBtn">
                    🔄 Refresh
                </button>
                <button class="btn btn-primary" id="composeBtn" onclick="window.location.href='compose.php'">
                    ✉️ Compose
                </button>
            </div>
        </div>

        <!-- Sync Status -->
        <div class="sync-status" id="syncStatus" style="display: none;">
            <div class="sync-info">
                <div class="spinner"></div>
                <span id="syncText">Syncing emails...</span>
            </div>
        </div>

        <!-- Messages -->
        <div class="messages-container">
            <?php if (empty($messages)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <div class="empty-state-title">No messages yet</div>
                    <p>Click the refresh button to fetch your emails</p>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $message): ?>
                    <div class="message-item" onclick="viewMessage(<?= $message['id'] ?>)">
                        <div class="message-header">
                            <div>
                                <span class="message-from">
                                    <?= htmlspecialchars($message['sender_name'] ?: 'Unknown') ?>
                                </span>
                                <span class="message-sender-email">
                                    <?= htmlspecialchars($message['sender_email']) ?>
                                </span>
                            </div>
                            <div class="message-date">
                                <?= date('M d, Y H:i', strtotime($message['received_date'])) ?>
                            </div>
                        </div>
                        <div class="message-subject">
                            <?= htmlspecialchars($message['subject']) ?>
                        </div>
                        <div class="message-preview">
                            <?= htmlspecialchars(substr($message['body'], 0, 150)) ?>...
                        </div>
                        <?php if ($message['has_attachments']): ?>
                            <div class="message-meta">
                                <span class="attachment-badge">
                                    📎 <?= count(json_decode($message['attachment_data'] ?? '[]', true)) ?> attachment(s)
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast">
        <span id="toastMessage"></span>
    </div>

    <script>
        // Auto-fetch on page load
        window.addEventListener('load', function() {
            fetchEmails(false); // false = don't force refresh, just get new ones
        });

        // Refresh button click
        document.getElementById('refreshBtn').addEventListener('click', function() {
            fetchEmails(true); // true = force refresh (clear and refetch all)
        });

        function fetchEmails(forceRefresh = false) {
            const refreshBtn = document.getElementById('refreshBtn');
            const syncStatus = document.getElementById('syncStatus');
            const syncText = document.getElementById('syncText');
            
            // Disable button and show sync status
            refreshBtn.disabled = true;
            refreshBtn.textContent = '⏳ Syncing...';
            syncStatus.style.display = 'flex';
            syncText.textContent = forceRefresh ? 'Refreshing all emails...' : 'Fetching new emails...';
            
            // Fetch emails
            fetch('fetch_emails.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'force_refresh=' + (forceRefresh ? '1' : '0')
            })
            .then(response => response.json())
            .then(result => {
                refreshBtn.disabled = false;
                refreshBtn.textContent = '🔄 Refresh';
                syncStatus.style.display = 'none';
                
                if (result.success) {
                    showToast('✅ ' + result.message, 'success');
                    
                    // Reload page to show new messages
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
                refreshBtn.textContent = '🔄 Refresh';
                syncStatus.style.display = 'none';
                showToast('❌ Network error: ' + error.message, 'error');
            });
        }

        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toastMessage');
            
            toastMessage.textContent = message;
            toast.className = 'toast ' + type + ' show';
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        function viewMessage(id) {
            window.location.href = 'view_message.php?id=' + id;
        }
    </script>
</body>
</html>