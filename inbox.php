<?php
/**
 * INBOX - Ultra-Fast Optimized Version
 * Apple-Inspired Minimalist Design with Performance Optimizations
 * Target Load Time: < 500ms
 */

session_start();

// Performance monitoring
$startTime = microtime(true);

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

// ==================== CRITICAL: NO IMAP SYNC ON PAGE LOAD ====================
// IMAP sync now happens in background via AJAX - this makes page load instant!

// Only initialize IMAP config, DON'T fetch messages
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

// ==================== OPTIMIZED FETCH FUNCTION ====================

/**
 * Get inbox messages - OPTIMIZED VERSION
 * - Only selects columns needed for list view
 * - No LONGTEXT body column transfer
 * - Uses optimized covering index
 */
function getInboxMessages($userEmail, $limit = 50, $offset = 0) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return ['messages' => [], 'total' => 0];
        
        // Get total count (uses optimized index)
        $countStmt = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM inbox_messages 
            WHERE user_email = :email AND is_deleted = 0
        ");
        $countStmt->execute([':email' => $userEmail]);
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get paginated messages - ONLY columns needed for list view!
        // This query uses idx_inbox_optimized covering index
        $stmt = $pdo->prepare("
            SELECT 
                id,
                sender_email,
                sender_name,
                subject,
                body_preview,
                received_date,
                fetched_at,
                is_read,
                has_attachments,
                attachment_data,
                is_starred,
                CASE WHEN is_read = 0 AND TIMESTAMPDIFF(MINUTE, fetched_at, NOW()) <= 5 
                THEN 1 ELSE 0 END as is_new
            FROM inbox_messages 
            WHERE user_email = :email AND is_deleted = 0
            ORDER BY received_date DESC 
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':email', $userEmail, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return [
            'messages' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total
        ];
    } catch (Exception $e) {
        error_log("Inbox fetch error: " . $e->getMessage());
        return ['messages' => [], 'total' => 0];
    }
}

// ==================== PAGINATION ====================
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

$result = getInboxMessages($userEmail, $perPage, $offset);
$messages = $result['messages'];
$totalMessages = $result['total'];
$totalPages = ceil($totalMessages / $perPage);

// ==================== OPTIMIZED UNREAD COUNT ====================
// Calculate from already-fetched messages instead of separate query!
$unreadCount = count(array_filter($messages, function($msg) {
    return $msg['is_read'] == 0;
}));

// Get actual total unread count for title
$totalUnread = getUnreadCount($userEmail);

// ==================== HELPER FUNCTION ====================
function formatDate($dateStr) {
    $date = new DateTime($dateStr);
    $now = new DateTime();
    $diff = $now->diff($date);
    
    if ($diff->days === 0) return $date->format('g:i A');
    if ($diff->days === 1) return 'Yesterday';
    if ($diff->days < 7) return $date->format('l');
    return $date->format('M j');
}

// Performance log
$loadTime = (microtime(true) - $startTime) * 1000;
error_log("Inbox page load time: " . round($loadTime, 2) . "ms");
if ($loadTime > 500) {
    error_log("WARNING: Inbox load time exceeded 500ms target!");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inbox<?= $totalUnread > 0 ? " ($totalUnread)" : '' ?> - SXC MDTS</title>
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

        /* ========== NEW MESSAGES NOTIFICATION ========== */
        .new-messages-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--apple-blue);
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            cursor: pointer;
            animation: slideInRight 0.3s ease;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 14px;
        }

        @keyframes slideInRight {
            from { transform: translateX(400px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        /* ========== SYNC STATUS ========== */
        .sync-status {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            display: none;
            align-items: center;
            gap: 8px;
            z-index: 1000;
        }

        .sync-status.show {
            display: flex;
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

        .badge.total {
            background: var(--apple-bg);
            color: var(--text-secondary);
        }

        .controls-row {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .search-box {
            flex: 1;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 10px 14px 10px 38px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 13px;
            background: var(--apple-bg);
            transition: all 0.2s;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--apple-blue);
            background: white;
        }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--apple-gray);
            font-size: 18px;
        }

        .refresh-btn {
            padding: 10px 16px;
            background: var(--apple-blue);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .refresh-btn:hover {
            background: #0051D5;
        }

        .refresh-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* ========== MESSAGE LIST SCROLLABLE ========== */
        .message-list {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .message-item {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: background 0.2s;
            position: relative;
        }

        .message-item:hover {
            background: rgba(0, 122, 255, 0.04);
        }

        .message-item.active {
            background: rgba(0, 122, 255, 0.08);
            border-left: 3px solid var(--apple-blue);
            padding-left: 17px;
        }

        .message-item.unread {
            background: rgba(0, 122, 255, 0.02);
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

        .message-item.unread.active::before {
            left: 5px;
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }

        .sender {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-primary);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 200px;
        }

        .date {
            font-size: 12px;
            color: var(--apple-gray);
            white-space: nowrap;
        }

        .subject {
            font-weight: 500;
            font-size: 13px;
            color: var(--text-primary);
            margin-bottom: 4px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .preview {
            font-size: 12px;
            color: var(--text-secondary);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .message-badges {
            display: flex;
            gap: 6px;
            margin-top: 6px;
        }

        .mini-badge {
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
            background: var(--apple-bg);
            color: var(--text-secondary);
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }

        .mini-badge.new {
            background: #34C759;
            color: white;
        }

        /* ========== MESSAGE VIEWER ========== */
        .message-viewer-panel {
            flex: 1;
            background: white;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .viewer-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            background: white;
        }

        .icon-btn {
            width: 36px;
            height: 36px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .icon-btn:hover {
            background: var(--apple-bg);
        }

        .icon-btn.delete {
            border-color: #FF3B30;
            color: #FF3B30;
        }

        .icon-btn.delete:hover {
            background: #FF3B30;
            color: white;
        }

        .viewer-content {
            flex: 1;
            overflow-y: auto;
            padding: 32px 48px;
        }

        .message-subject-display {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 24px;
            line-height: 1.3;
        }

        .message-meta {
            background: var(--apple-bg);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 32px;
        }

        .meta-row {
            display: flex;
            gap: 16px;
            margin-bottom: 12px;
        }

        .meta-row:last-child {
            margin-bottom: 0;
        }

        .meta-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--apple-gray);
            min-width: 60px;
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
            font-weight: 700;
            font-size: 16px;
        }

        .sender-details {
            flex: 1;
        }

        .sender-name {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-primary);
        }

        .sender-email {
            font-size: 12px;
            color: var(--apple-gray);
        }

        .message-body-section {
            font-size: 15px;
            line-height: 1.7;
            color: var(--text-primary);
            white-space: pre-wrap;
        }

        /* ========== ATTACHMENTS ========== */
        .attachments-section {
            margin: 24px 0;
            padding: 20px;
            background: var(--apple-bg);
            border-radius: 12px;
        }

        .attachments-header {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 16px;
            color: var(--text-primary);
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
            font-size: 24px;
        }

        .attachment-info {
            flex: 1;
        }

        .attachment-name {
            font-weight: 500;
            font-size: 13px;
            color: var(--text-primary);
            margin-bottom: 2px;
        }

        .attachment-size {
            font-size: 11px;
            color: var(--apple-gray);
        }

        .download-btn {
            padding: 6px 12px;
            background: var(--apple-blue);
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s;
        }

        .download-btn:hover {
            background: #0051D5;
        }

        /* ========== VIEWER PLACEHOLDER ========== */
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

        /* ========== TOAST ========== */
        .toast {
            position: fixed;
            bottom: -100px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.9);
            color: white;
            padding: 14px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            transition: bottom 0.3s ease;
            z-index: 10000;
        }

        .toast.show {
            bottom: 30px;
        }

        .toast.success {
            background: #34C759;
        }

        .toast.error {
            background: #FF3B30;
        }

        /* ========== PAGINATION ========== */
        .pagination {
            padding: 16px 20px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            background: white;
        }

        .pagination-btn {
            padding: 8px 16px;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: white;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .pagination-btn:hover:not(:disabled) {
            background: var(--apple-bg);
        }

        .pagination-btn:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }

        .pagination-info {
            font-size: 13px;
            color: var(--text-secondary);
        }

        /* ========== LOADING INDICATOR ========== */
        .loading-more {
            padding: 20px;
            text-align: center;
            color: var(--apple-gray);
            font-size: 13px;
        }

        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid var(--border);
            border-top-color: var(--apple-blue);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <div class="inbox-container">
                <!-- Message List Panel -->
                <div class="message-list-panel">
                    <div class="list-header">
                        <div class="list-title-row">
                            <h2 class="list-title">Inbox</h2>
                            <div class="badge-group">
                                <?php if ($totalUnread > 0): ?>
                                    <span class="badge unread"><?= $totalUnread ?> Unread</span>
                                <?php endif; ?>
                                <span class="badge total"><?= $totalMessages ?> Total</span>
                            </div>
                        </div>
                        <div class="controls-row">
                            <div class="search-box">
                                <span class="material-icons search-icon">search</span>
                                <input type="text" class="search-input" id="searchInput" placeholder="Search messages...">
                            </div>
                            <button class="refresh-btn" id="refreshBtn" onclick="refreshInbox()">
                                <span class="material-icons" style="font-size: 18px;">refresh</span>
                                Sync
                            </button>
                        </div>
                    </div>

                    <div class="message-list" id="messageList">
                        <?php foreach ($messages as $msg): ?>
                            <div class="message-item <?= $msg['is_read'] ? '' : 'unread' ?>" 
                                 data-message-id="<?= $msg['id'] ?>"
                                 onclick="loadMessage(<?= $msg['id'] ?>)">
                                <div class="message-header">
                                    <div class="sender"><?= htmlspecialchars($msg['sender_name'] ?: $msg['sender_email']) ?></div>
                                    <div class="date"><?= formatDate($msg['received_date']) ?></div>
                                </div>
                                <div class="subject"><?= htmlspecialchars($msg['subject']) ?></div>
                                <div class="preview"><?= htmlspecialchars(substr($msg['body_preview'] ?? '', 0, 100)) ?></div>
                                <?php if ($msg['has_attachments'] || $msg['is_new']): ?>
                                    <div class="message-badges">
                                        <?php if ($msg['is_new']): ?>
                                            <span class="mini-badge new">NEW</span>
                                        <?php endif; ?>
                                        <?php if ($msg['has_attachments']): ?>
                                            <span class="mini-badge">📎 Attachments</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <?php if (empty($messages)): ?>
                            <div style="padding: 40px 20px; text-align: center; color: var(--apple-gray);">
                                <span class="material-icons" style="font-size: 60px; opacity: 0.3;">inbox</span>
                                <p style="margin-top: 16px; font-size: 14px;">No messages in your inbox</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <button class="pagination-btn" <?= $page <= 1 ? 'disabled' : '' ?> 
                                    onclick="location.href='?page=<?= $page - 1 ?>'">
                                ← Previous
                            </button>
                            <span class="pagination-info">Page <?= $page ?> of <?= $totalPages ?></span>
                            <button class="pagination-btn" <?= $page >= $totalPages ? 'disabled' : '' ?>
                                    onclick="location.href='?page=<?= $page + 1 ?>'">
                                Next →
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Message Viewer Panel -->
                <div class="message-viewer-panel">
                    <div id="messageViewer">
                        <div class="viewer-placeholder">
                            <span class="material-icons">drafts</span>
                            <p class="viewer-placeholder-text">Select a message to read</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast">
        <span id="toastMessage"></span>
    </div>

    <!-- Sync Status -->
    <div class="sync-status" id="syncStatus">
        <div class="spinner"></div>
        <span>Checking for new messages...</span>
    </div>

    <script>
        // ==================== BACKGROUND IMAP SYNC ====================
        // This runs AFTER page loads, doesn't block UI!
        window.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                backgroundSync();
            }, 1000); // Wait 1 second after page load
        });

        function backgroundSync() {
            const statusEl = document.getElementById('syncStatus');
            statusEl.classList.add('show');

            fetch('fetch_inbox_messages.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
            })
            .then(r => r.json())
            .then(result => {
                statusEl.classList.remove('show');
                
                if (result.success && result.count > 0) {
                    // Show notification for new messages
                    const notification = document.createElement('div');
                    notification.className = 'new-messages-notification';
                    notification.innerHTML = `
                        <span class="material-icons">mail</span>
                        <span>${result.count} new message${result.count > 1 ? 's' : ''}</span>
                    `;
                    notification.onclick = () => location.reload();
                    document.body.appendChild(notification);
                    
                    // Auto-remove after 10 seconds
                    setTimeout(() => notification.remove(), 10000);
                }
            })
            .catch(err => {
                statusEl.classList.remove('show');
                console.log('Background sync failed:', err);
            });
        }

        // ==================== REFRESH INBOX ====================
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
                btn.innerHTML = '<span class="material-icons" style="font-size: 18px;">refresh</span> Sync';
                
                if (result.success) {
                    showToast('✅ ' + result.message, 'success');
                    if (result.count > 0) setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('❌ ' + (result.error || result.message), 'error');
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = '<span class="material-icons" style="font-size: 18px;">refresh</span> Sync';
                showToast('❌ Network error', 'error');
            });
        }

        // ==================== LOAD MESSAGE ====================
        function loadMessage(id) {
            document.querySelectorAll('.message-item').forEach(i => i.classList.remove('active'));
            document.querySelector(`[data-message-id="${id}"]`).classList.add('active');
            
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

        // ==================== DISPLAY MESSAGE ====================
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

        // ==================== MARK AS READ ====================
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

        // ==================== DELETE MESSAGE ====================
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

        // ==================== SEARCH ====================
        document.getElementById('searchInput')?.addEventListener('input', e => {
            const term = e.target.value.toLowerCase();
            document.querySelectorAll('.message-item').forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(term) ? '' : 'none';
            });
        });

        // ==================== HELPER FUNCTIONS ====================
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

        // Log page load time to console
        console.log('Page load time: <?= round($loadTime, 2) ?>ms');
    </script>
</body>
</html>