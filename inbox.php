<?php
session_start();

// Check authentication
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: login.php');
    exit;
}

require_once 'db_config.php';
require_once 'settings_helper.php';

$userEmail = $_SESSION['smtp_user'];

// Get pagination parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Get filter parameters
$filters = [
    'search' => $_GET['search'] ?? '',
    'sender' => $_GET['sender'] ?? '',
    'unread_only' => isset($_GET['unread_only']) ? true : false,
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

// Fetch messages from database
$messages = getInboxMessages($userEmail, $perPage, $offset, $filters);
$totalMessages = getInboxMessageCount($userEmail, $filters);
$totalPages = ceil($totalMessages / $perPage);
$unreadCount = getUnreadCount($userEmail);

// Get user settings
$settings = getSettingsWithDefaults($userEmail);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inbox - SXC MDTS</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    
    <style>
        :root {
            --apple-blue: #007AFF;
            --apple-gray: #8E8E93;
            --apple-bg: #F2F2F7;
            --glass: rgba(255, 255, 255, 0.7);
            --border: #E5E5EA;
            --text-primary: #1c1c1e;
            --text-secondary: #52525b;
            --unread-bg: #f0f9ff;
            --unread-border: #bfdbfe;
            --read-bg: #ffffff;
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
            line-height: 1.6;
        }

        /* ========== LAYOUT ========== */
        .app-container {
            display: flex;
            min-height: 100vh;
            max-width: 1600px;
            margin: 0 auto;
        }

        .main-content {
            flex: 1;
            padding: 30px 40px;
            overflow-y: auto;
        }

        /* ========== HEADER ========== */
        .inbox-header {
            margin-bottom: 30px;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .header-title-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .inbox-title {
            font-size: 32px;
            font-weight: 700;
            letter-spacing: -0.8px;
            color: var(--text-primary);
        }

        .inbox-badge {
            background: var(--apple-blue);
            color: white;
            font-size: 14px;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 12px;
            display: inline-block;
        }

        .header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .sync-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--apple-blue);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(0, 122, 255, 0.25);
        }

        .sync-btn:hover {
            background: #0051D5;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 122, 255, 0.35);
        }

        .sync-btn:active {
            transform: translateY(0);
        }

        .sync-btn .material-icons {
            font-size: 18px;
        }

        .sync-btn.syncing .material-icons {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* ========== FILTERS SECTION ========== */
        .filters-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .filters-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 12px;
            align-items: center;
        }

        .filter-input {
            padding: 10px 15px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
        }

        .filter-input:focus {
            outline: none;
            border-color: var(--apple-blue);
            box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
        }

        .filter-btn {
            padding: 10px 20px;
            background: var(--text-primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .filter-btn:hover {
            background: #000;
        }

        .unread-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: var(--apple-bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .unread-toggle:hover {
            background: #E5E5EA;
        }

        .unread-toggle input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .unread-toggle label {
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            user-select: none;
        }

        /* ========== STATS BAR ========== */
        .stats-bar {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .stat-card {
            background: white;
            padding: 16px 24px;
            border-radius: 10px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .stat-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--apple-gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
        }

        /* ========== MESSAGES LIST ========== */
        .messages-container {
            background: white;
            border-radius: 12px;
            border: 1px solid var(--border);
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }

        .message-item {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            gap: 16px;
            align-items: flex-start;
        }

        .message-item:last-child {
            border-bottom: none;
        }

        .message-item:hover {
            background: var(--apple-bg);
        }

        /* Unread styling */
        .message-item.unread_message {
            background: var(--unread-bg);
            border-left: 4px solid var(--apple-blue);
        }

        .message-item.unread_message:hover {
            background: #dbeafe;
        }

        .message-item.read_message {
            background: var(--read-bg);
        }

        /* Message checkbox */
        .message-checkbox {
            padding-top: 4px;
        }

        .message-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        /* Message content */
        .message-content {
            flex: 1;
            min-width: 0;
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 8px;
            gap: 16px;
        }

        .message-sender {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .unread_message .message-sender {
            font-weight: 700;
        }

        .message-date {
            font-size: 13px;
            color: var(--apple-gray);
            white-space: nowrap;
            flex-shrink: 0;
        }

        .message-subject {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 6px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .unread_message .message-subject {
            font-weight: 600;
        }

        .message-preview {
            font-size: 13px;
            color: var(--text-secondary);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .message-meta {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-top: 8px;
        }

        .attachment-badge {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 12px;
            color: var(--apple-gray);
        }

        .attachment-badge .material-icons {
            font-size: 16px;
        }

        /* Message actions */
        .message-actions {
            display: flex;
            gap: 8px;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .message-item:hover .message-actions {
            opacity: 1;
        }

        .action-icon-btn {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: none;
            background: transparent;
            color: var(--apple-gray);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .action-icon-btn:hover {
            background: rgba(0, 0, 0, 0.05);
            color: var(--text-primary);
        }

        .action-icon-btn .material-icons {
            font-size: 18px;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
        }

        .empty-state .material-icons {
            font-size: 80px;
            color: var(--apple-gray);
            opacity: 0.3;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 24px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 10px;
        }

        .empty-state p {
            font-size: 15px;
            color: var(--text-secondary);
        }

        /* ========== PAGINATION ========== */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 30px;
        }

        .pagination-btn {
            padding: 8px 16px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .pagination-btn:hover:not(.disabled) {
            background: var(--apple-bg);
            border-color: var(--apple-gray);
        }

        .pagination-btn.active {
            background: var(--apple-blue);
            color: white;
            border-color: var(--apple-blue);
        }

        .pagination-btn.disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        .pagination-info {
            padding: 8px 16px;
            font-size: 14px;
            color: var(--text-secondary);
        }

        /* ========== LOADING OVERLAY ========== */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .loading-overlay.active {
            display: flex;
        }

        .loading-spinner {
            background: white;
            padding: 30px 40px;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            text-align: center;
        }

        .loading-spinner .material-icons {
            font-size: 48px;
            color: var(--apple-blue);
            animation: spin 1s linear infinite;
        }

        .loading-spinner p {
            margin-top: 16px;
            font-size: 15px;
            font-weight: 500;
            color: var(--text-primary);
        }

        /* ========== NOTIFICATION TOAST ========== */
        .toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: white;
            padding: 16px 24px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.3s;
            z-index: 10000;
            max-width: 400px;
        }

        .toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        .toast.success {
            border-left: 4px solid #34a853;
        }

        .toast.error {
            border-left: 4px solid #ea4335;
        }

        .toast .material-icons {
            font-size: 24px;
        }

        .toast.success .material-icons {
            color: #34a853;
        }

        .toast.error .material-icons {
            color: #ea4335;
        }

        .toast-content {
            flex: 1;
        }

        .toast-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 2px;
        }

        .toast-message {
            font-size: 13px;
            color: var(--text-secondary);
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 1024px) {
            .main-content {
                padding: 20px 24px;
            }

            .filters-row {
                grid-template-columns: 1fr;
            }

            .inbox-title {
                font-size: 28px;
            }

            .message-item {
                padding: 16px 20px;
            }
        }

        @media (max-width: 768px) {
            .header-top {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }

            .stats-bar {
                overflow-x: auto;
            }

            .message-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="inbox-header">
                <div class="header-top">
                    <div class="header-title-section">
                        <h1 class="inbox-title">Inbox</h1>
                        <?php if ($unreadCount > 0): ?>
                        <span class="inbox-badge"><?= $unreadCount ?> Unread</span>
                        <?php endif; ?>
                    </div>
                    <div class="header-actions">
                        <button class="sync-btn" id="syncBtn">
                            <span class="material-icons">sync</span>
                            Sync Mail
                        </button>
                    </div>
                </div>

                <!-- Stats Bar -->
                <div class="stats-bar">
                    <div class="stat-card">
                        <div class="stat-label">Total Messages</div>
                        <div class="stat-value"><?= number_format($totalMessages) ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Unread</div>
                        <div class="stat-value"><?= number_format($unreadCount) ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Current Page</div>
                        <div class="stat-value"><?= $page ?> / <?= max(1, $totalPages) ?></div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filters-section">
                    <form method="GET" action="inbox.php" id="filterForm">
                        <div class="filters-row">
                            <input 
                                type="text" 
                                name="search" 
                                class="filter-input" 
                                placeholder="Search messages..." 
                                value="<?= htmlspecialchars($filters['search']) ?>"
                            >
                            <input 
                                type="text" 
                                name="sender" 
                                class="filter-input" 
                                placeholder="Filter by sender..." 
                                value="<?= htmlspecialchars($filters['sender']) ?>"
                            >
                            <input 
                                type="date" 
                                name="date_from" 
                                class="filter-input" 
                                value="<?= htmlspecialchars($filters['date_from']) ?>"
                            >
                            <input 
                                type="date" 
                                name="date_to" 
                                class="filter-input" 
                                value="<?= htmlspecialchars($filters['date_to']) ?>"
                            >
                            <button type="submit" class="filter-btn">Apply</button>
                        </div>
                        <div class="unread-toggle" style="margin-top: 12px; width: fit-content;">
                            <input 
                                type="checkbox" 
                                name="unread_only" 
                                id="unreadOnly" 
                                <?= $filters['unread_only'] ? 'checked' : '' ?>
                                onchange="this.form.submit()"
                            >
                            <label for="unreadOnly">Show Unread Only</label>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Messages List -->
            <div class="messages-container">
                <?php if (empty($messages)): ?>
                    <div class="empty-state">
                        <span class="material-icons">inbox</span>
                        <h3>No Messages Found</h3>
                        <p>Your inbox is empty or no messages match your filters.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $message): ?>
                        <div class="message-item <?= $message['is_read'] ? 'read_message' : 'unread_message' ?>" 
                             data-message-id="<?= $message['id'] ?>"
                             onclick="viewMessage(<?= $message['id'] ?>, <?= $message['is_read'] ?>)">
                            
                            <div class="message-checkbox" onclick="event.stopPropagation()">
                                <input type="checkbox" class="message-select" value="<?= $message['id'] ?>">
                            </div>

                            <div class="message-content">
                                <div class="message-header">
                                    <div style="flex: 1; min-width: 0;">
                                        <div class="message-sender">
                                            <?php 
                                            $displayName = !empty($message['sender_name']) 
                                                ? htmlspecialchars($message['sender_name']) 
                                                : htmlspecialchars($message['sender_email']);
                                            echo $displayName;
                                            ?>
                                        </div>
                                        <div style="font-size: 12px; color: var(--apple-gray); margin-top: 2px;">
                                            <?= htmlspecialchars($message['sender_email']) ?>
                                        </div>
                                    </div>
                                    <div class="message-date">
                                        <?= date('M j, g:i A', strtotime($message['received_date'])) ?>
                                    </div>
                                </div>

                                <div class="message-subject">
                                    <?= htmlspecialchars($message['subject']) ?>
                                </div>

                                <div class="message-preview">
                                    <?= htmlspecialchars(substr(strip_tags($message['body']), 0, 150)) ?>...
                                </div>

                                <?php if ($message['has_attachments']): ?>
                                <div class="message-meta">
                                    <div class="attachment-badge">
                                        <span class="material-icons">attach_file</span>
                                        Has Attachments
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="message-actions">
                                <button class="action-icon-btn" 
                                        onclick="event.stopPropagation(); toggleRead(<?= $message['id'] ?>, <?= $message['is_read'] ?>)"
                                        title="<?= $message['is_read'] ? 'Mark as Unread' : 'Mark as Read' ?>">
                                    <span class="material-icons">
                                        <?= $message['is_read'] ? 'mark_email_unread' : 'mark_email_read' ?>
                                    </span>
                                </button>
                                <button class="action-icon-btn" 
                                        onclick="event.stopPropagation(); deleteMessage(<?= $message['id'] ?>)"
                                        title="Delete">
                                    <span class="material-icons">delete</span>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&<?= http_build_query($filters) ?>" class="pagination-btn">
                        ← Previous
                    </a>
                <?php else: ?>
                    <span class="pagination-btn disabled">← Previous</span>
                <?php endif; ?>

                <span class="pagination-info">
                    Page <?= $page ?> of <?= $totalPages ?>
                </span>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&<?= http_build_query($filters) ?>" class="pagination-btn">
                        Next →
                    </a>
                <?php else: ?>
                    <span class="pagination-btn disabled">Next →</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <span class="material-icons">sync</span>
            <p>Syncing messages...</p>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast" id="toast">
        <span class="material-icons"></span>
        <div class="toast-content">
            <div class="toast-title"></div>
            <div class="toast-message"></div>
        </div>
    </div>

    <script>
        // Toast notification function
        function showToast(title, message, type = 'success') {
            const toast = document.getElementById('toast');
            const icon = toast.querySelector('.material-icons');
            const titleEl = toast.querySelector('.toast-title');
            const messageEl = toast.querySelector('.toast-message');

            // Set content
            titleEl.textContent = title;
            messageEl.textContent = message;
            
            // Set icon
            icon.textContent = type === 'success' ? 'check_circle' : 'error';
            
            // Set type class
            toast.className = 'toast ' + type;
            
            // Show toast
            setTimeout(() => toast.classList.add('show'), 10);
            
            // Hide after 4 seconds
            setTimeout(() => {
                toast.classList.remove('show');
            }, 4000);
        }

        // Sync messages
        document.getElementById('syncBtn').addEventListener('click', async function() {
            const btn = this;
            const overlay = document.getElementById('loadingOverlay');
            
            btn.classList.add('syncing');
            btn.disabled = true;
            overlay.classList.add('active');

            try {
                const response = await fetch('fetch_inbox_messages.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });

                const result = await response.json();

                if (result.success) {
                    showToast('Sync Complete', result.message, 'success');
                    
                    // Reload page after 1.5 seconds
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showToast('Sync Failed', result.error || 'Could not sync messages', 'error');
                    btn.classList.remove('syncing');
                    btn.disabled = false;
                }
            } catch (error) {
                showToast('Error', 'Network error occurred', 'error');
                btn.classList.remove('syncing');
                btn.disabled = false;
            } finally {
                overlay.classList.remove('active');
            }
        });

        // Toggle read status
        async function toggleRead(messageId, currentStatus) {
            try {
                const response = await fetch('mark_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        message_id: messageId,
                        action: currentStatus ? 'unread' : 'read'
                    })
                });

                const result = await response.json();

                if (result.success) {
                    // Update UI immediately
                    const messageEl = document.querySelector(`[data-message-id="${messageId}"]`);
                    if (currentStatus) {
                        messageEl.classList.remove('read_message');
                        messageEl.classList.add('unread_message');
                    } else {
                        messageEl.classList.remove('unread_message');
                        messageEl.classList.add('read_message');
                    }
                    
                    showToast('Success', result.message, 'success');
                    
                    // Reload to update unread count
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showToast('Error', result.error || 'Could not update message', 'error');
                }
            } catch (error) {
                showToast('Error', 'Network error occurred', 'error');
            }
        }

        // View message (marks as read)
        function viewMessage(messageId, isRead) {
            if (!isRead) {
                // Mark as read via AJAX
                fetch('mark_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        message_id: messageId,
                        action: 'read'
                    })
                }).then(response => response.json())
                  .then(result => {
                      if (result.success) {
                          // Update UI
                          const messageEl = document.querySelector(`[data-message-id="${messageId}"]`);
                          messageEl.classList.remove('unread_message');
                          messageEl.classList.add('read_message');
                      }
                  });
            }
            
            // Open message in new page or modal
            window.location.href = `view_message.php?id=${messageId}`;
        }

        // Delete message
        async function deleteMessage(messageId) {
            if (!confirm('Are you sure you want to delete this message?')) {
                return;
            }

            try {
                const response = await fetch('delete_inbox_message.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        message_id: messageId
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showToast('Success', 'Message deleted', 'success');
                    
                    // Remove from UI
                    const messageEl = document.querySelector(`[data-message-id="${messageId}"]`);
                    messageEl.style.opacity = '0';
                    setTimeout(() => {
                        messageEl.remove();
                    }, 300);
                } else {
                    showToast('Error', result.error || 'Could not delete message', 'error');
                }
            } catch (error) {
                showToast('Error', 'Network error occurred', 'error');
            }
        }

        // Auto-refresh every 5 minutes
        setInterval(() => {
            console.log('Auto-syncing...');
            document.getElementById('syncBtn').click();
        }, 300000); // 5 minutes
    </script>
</body>
</html>
