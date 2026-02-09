<?php
/**
 * Inbox Page - FIXED VERSION
 * Properly handles return values from inbox_functions.php
 */

session_start();

// Security check
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    header("Location: login.php");
    exit();
}

require_once 'inbox_functions.php';
require_once 'imap_helper.php';

$userEmail = $_SESSION['smtp_user'];

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'fetch_messages':
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            
            $filters = [];
            if (isset($_GET['search'])) $filters['search'] = $_GET['search'];
            if (isset($_GET['unread_only'])) $filters['unread_only'] = true;
            if (isset($_GET['starred_only'])) $filters['starred_only'] = true;
            if (isset($_GET['new_only'])) $filters['new_only'] = true;
            
            $messages = getInboxMessages($userEmail, $limit, $offset, $filters);
            $total = getInboxMessageCount($userEmail, $filters);
            
            echo json_encode([
                'success' => true,
                'messages' => $messages,
                'total' => $total
            ]);
            exit();
            
        case 'sync':
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $forceRefresh = isset($_GET['force']) && $_GET['force'] === 'true';
            
            $result = fetchNewMessagesFromSession($userEmail, $limit, $forceRefresh);
            echo json_encode($result);
            exit();
            
        case 'get_message':
            $messageId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            $message = getInboxMessageById($messageId, $userEmail);
            
            if ($message) {
                // Mark as read
                markMessageAsRead($messageId, $userEmail);
                echo json_encode(['success' => true, 'message' => $message]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Message not found']);
            }
            exit();
            
        case 'mark_read':
            $messageId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            $success = markMessageAsRead($messageId, $userEmail);
            echo json_encode(['success' => $success]);
            exit();
            
        case 'mark_unread':
            $messageId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            $success = markMessageAsUnread($messageId, $userEmail);
            echo json_encode(['success' => $success]);
            exit();
            
        case 'toggle_star':
            $messageId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            $success = toggleStarMessage($messageId, $userEmail);
            echo json_encode(['success' => $success]);
            exit();
            
        case 'delete':
            $messageId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            $success = deleteInboxMessage($messageId, $userEmail);
            echo json_encode(['success' => $success]);
            exit();
            
        case 'get_counts':
            $unread = getUnreadCount($userEmail);
            $new = getNewCount($userEmail);
            echo json_encode([
                'success' => true,
                'unread' => $unread,
                'new' => $new
            ]);
            exit();
    }
}

// Initial data load - FIXED: Properly handle return values
$messages = getInboxMessages($userEmail, 50, 0) ?? [];
$totalCount = getInboxMessageCount($userEmail) ?? 0;
$unreadCount = getUnreadCount($userEmail) ?? 0;
$newCount = getNewCount($userEmail) ?? 0;

// Filter messages safely - FIXED: Only filter if $messages is an array
$unreadMessages = is_array($messages) ? array_filter($messages, function($msg) {
    return isset($msg['is_read']) && $msg['is_read'] == 0;
}) : [];

$newMessages = is_array($messages) ? array_filter($messages, function($msg) {
    return isset($msg['is_new']) && $msg['is_new'] == 1;
}) : [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inbox — SXC MDTS</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    
    <style>
        :root {
            --apple-blue: #007AFF;
            --apple-gray: #8E8E93;
            --apple-bg: #F2F2F7;
            --border: #E5E5EA;
            --success-green: #34C759;
            --warning-orange: #FF9500;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: var(--apple-bg);
            color: #1c1c1e;
            -webkit-font-smoothing: antialiased;
        }

        /* Header */
        .page-header {
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 20px 40px;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
            font-size: 28px;
            font-weight: 600;
            color: #1c1c1e;
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
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--apple-blue);
            color: white;
        }

        .btn-primary:hover {
            background: #0051D5;
        }

        .btn-secondary {
            background: white;
            color: var(--apple-blue);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: var(--apple-bg);
        }

        /* Stats bar */
        .stats-bar {
            background: white;
            padding: 16px 40px;
            border-bottom: 1px solid var(--border);
        }

        .stats-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            gap: 24px;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: var(--apple-gray);
        }

        .stat-number {
            font-weight: 600;
            color: #1c1c1e;
        }

        /* Main content */
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px 40px;
        }

        /* Toolbar */
        .toolbar {
            background: white;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
            border: 1px solid var(--border);
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .search-box {
            flex: 1;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 10px 14px 10px 40px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 15px;
        }

        .search-box .material-icons {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--apple-gray);
        }

        .filter-btn {
            padding: 10px 16px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 14px;
        }

        .filter-btn.active {
            background: var(--apple-blue);
            color: white;
            border-color: var(--apple-blue);
        }

        /* Messages list */
        .messages-container {
            background: white;
            border-radius: 12px;
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .message-item {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            gap: 16px;
            align-items: start;
        }

        .message-item:last-child {
            border-bottom: none;
        }

        .message-item:hover {
            background: #F9F9F9;
        }

        .message-item.unread {
            background: #F5F9FF;
        }

        .message-item.new {
            background: #E8F4FF;
        }

        .message-checkbox {
            width: 20px;
            height: 20px;
            margin-top: 2px;
        }

        .message-star {
            cursor: pointer;
            color: var(--apple-gray);
            font-size: 20px;
            margin-top: 2px;
        }

        .message-star.starred {
            color: var(--warning-orange);
        }

        .message-content {
            flex: 1;
            min-width: 0;
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
        }

        .message-sender {
            font-weight: 600;
            color: #1c1c1e;
            font-size: 15px;
        }

        .message-date {
            font-size: 13px;
            color: var(--apple-gray);
            white-space: nowrap;
        }

        .message-subject {
            font-size: 14px;
            color: #1c1c1e;
            margin-bottom: 4px;
            font-weight: 500;
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
            gap: 8px;
            margin-top: 8px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 500;
        }

        .badge-new {
            background: #E8F4FF;
            color: var(--apple-blue);
        }

        .badge-attachment {
            background: var(--apple-bg);
            color: var(--apple-gray);
        }

        /* Empty state */
        .empty-state {
            padding: 60px 20px;
            text-align: center;
        }

        .empty-icon {
            font-size: 64px;
            color: var(--apple-gray);
            margin-bottom: 16px;
        }

        .empty-title {
            font-size: 20px;
            font-weight: 600;
            color: #1c1c1e;
            margin-bottom: 8px;
        }

        .empty-text {
            font-size: 15px;
            color: var(--apple-gray);
        }

        /* Loading */
        .loading {
            padding: 40px;
            text-align: center;
            color: var(--apple-gray);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            max-width: 800px;
            max-height: 90vh;
            width: 90%;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 18px;
            font-weight: 600;
        }

        .modal-body {
            padding: 20px;
            overflow-y: auto;
            flex: 1;
        }

        .message-detail {
            font-size: 15px;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="page-header">
        <div class="header-content">
            <h1 class="page-title">📬 Inbox</h1>
            <div class="header-actions">
                <button class="btn btn-secondary" onclick="syncMessages()">
                    <span class="material-icons">sync</span>
                    Sync
                </button>
                <button class="btn btn-secondary" onclick="forceRefresh()">
                    <span class="material-icons">refresh</span>
                    Force Refresh
                </button>
                <button class="btn btn-primary" onclick="location.href='index.php'">
                    <span class="material-icons">edit</span>
                    Compose
                </button>
            </div>
        </div>
    </div>

    <!-- Stats bar -->
    <div class="stats-bar">
        <div class="stats-content">
            <div class="stat-item">
                <span class="material-icons">mail</span>
                <span class="stat-number" id="totalCount"><?= $totalCount ?></span> Total
            </div>
            <div class="stat-item">
                <span class="material-icons">mark_email_unread</span>
                <span class="stat-number" id="unreadCount"><?= $unreadCount ?></span> Unread
            </div>
            <div class="stat-item">
                <span class="material-icons">fiber_new</span>
                <span class="stat-number" id="newCount"><?= $newCount ?></span> New
            </div>
        </div>
    </div>

    <!-- Main content -->
    <div class="main-content">
        <!-- Toolbar -->
        <div class="toolbar">
            <div class="search-box">
                <span class="material-icons">search</span>
                <input type="text" id="searchInput" placeholder="Search messages..." onkeyup="searchMessages()">
            </div>
            <button class="filter-btn" id="filterAll" onclick="setFilter('all')">
                All
            </button>
            <button class="filter-btn" id="filterUnread" onclick="setFilter('unread')">
                Unread
            </button>
            <button class="filter-btn" id="filterStarred" onclick="setFilter('starred')">
                <span class="material-icons">star</span>
                Starred
            </button>
            <button class="filter-btn" id="filterNew" onclick="setFilter('new')">
                New
            </button>
        </div>

        <!-- Messages -->
        <div class="messages-container" id="messagesContainer">
            <div class="loading">Loading messages...</div>
        </div>
    </div>

    <!-- Message detail modal -->
    <div class="modal" id="messageModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalSubject"></h2>
                <button class="btn btn-secondary" onclick="closeModal()">
                    <span class="material-icons">close</span>
                </button>
            </div>
            <div class="modal-body" id="modalBody">
            </div>
        </div>
    </div>

    <script>
        let currentFilter = 'all';
        let messages = <?= json_encode($messages) ?>;

        // Initial render
        renderMessages(messages);

        function renderMessages(msgs) {
            const container = document.getElementById('messagesContainer');
            
            if (!msgs || msgs.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">📭</div>
                        <div class="empty-title">No messages</div>
                        <div class="empty-text">Your inbox is empty or messages haven't been synced yet.</div>
                    </div>
                `;
                return;
            }

            let html = '';
            msgs.forEach(msg => {
                const isUnread = msg.is_read == 0;
                const isNew = msg.is_new == 1;
                const isStarred = msg.is_starred == 1;
                const hasAttachments = msg.has_attachments == 1;
                
                html += `
                    <div class="message-item ${isUnread ? 'unread' : ''} ${isNew ? 'new' : ''}" onclick="viewMessage(${msg.id})">
                        <span class="material-icons message-star ${isStarred ? 'starred' : ''}" onclick="event.stopPropagation(); toggleStar(${msg.id})">
                            ${isStarred ? 'star' : 'star_border'}
                        </span>
                        <div class="message-content">
                            <div class="message-header">
                                <div class="message-sender">${escapeHtml(msg.sender_name || msg.sender_email)}</div>
                                <div class="message-date">${formatDate(msg.received_date)}</div>
                            </div>
                            <div class="message-subject">${escapeHtml(msg.subject)}</div>
                            <div class="message-preview">${escapeHtml(msg.body_preview || '')}</div>
                            <div class="message-badges">
                                ${isNew ? '<span class="badge badge-new">NEW</span>' : ''}
                                ${hasAttachments ? '<span class="badge badge-attachment"><span class="material-icons" style="font-size:12px">attach_file</span> Attachment</span>' : ''}
                            </div>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        function setFilter(filter) {
            currentFilter = filter;
            
            // Update button states
            document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById('filter' + filter.charAt(0).toUpperCase() + filter.slice(1)).classList.add('active');
            
            fetchMessages();
        }

        function searchMessages() {
            fetchMessages();
        }

        async function fetchMessages() {
            const searchTerm = document.getElementById('searchInput').value;
            let url = `inbox.php?action=fetch_messages`;
            
            if (searchTerm) url += `&search=${encodeURIComponent(searchTerm)}`;
            if (currentFilter === 'unread') url += '&unread_only=1';
            if (currentFilter === 'starred') url += '&starred_only=1';
            if (currentFilter === 'new') url += '&new_only=1';
            
            try {
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.success) {
                    messages = data.messages;
                    renderMessages(messages);
                    updateCounts();
                }
            } catch (error) {
                console.error('Error fetching messages:', error);
            }
        }

        async function syncMessages() {
            try {
                const response = await fetch('inbox.php?action=sync&limit=50');
                const data = await response.json();
                
                if (data.success) {
                    alert(data.message);
                    fetchMessages();
                } else {
                    alert('Sync failed: ' + data.error);
                }
            } catch (error) {
                console.error('Error syncing:', error);
                alert('Sync failed');
            }
        }

        async function forceRefresh() {
            if (!confirm('Force refresh will clear all messages and re-fetch from server. Continue?')) {
                return;
            }
            
            try {
                const response = await fetch('inbox.php?action=sync&limit=100&force=true');
                const data = await response.json();
                
                if (data.success) {
                    alert(data.message);
                    fetchMessages();
                } else {
                    alert('Refresh failed: ' + data.error);
                }
            } catch (error) {
                console.error('Error refreshing:', error);
                alert('Refresh failed');
            }
        }

        async function viewMessage(messageId) {
            try {
                const response = await fetch(`inbox.php?action=get_message&id=${messageId}`);
                const data = await response.json();
                
                if (data.success) {
                    const msg = data.message;
                    document.getElementById('modalSubject').textContent = msg.subject;
                    document.getElementById('modalBody').innerHTML = `
                        <p><strong>From:</strong> ${escapeHtml(msg.sender_name || msg.sender_email)}</p>
                        <p><strong>Date:</strong> ${formatDate(msg.received_date)}</p>
                        <hr style="margin: 16px 0; border: none; border-top: 1px solid var(--border)">
                        <div class="message-detail">${msg.body}</div>
                    `;
                    document.getElementById('messageModal').classList.add('active');
                    fetchMessages(); // Refresh list
                }
            } catch (error) {
                console.error('Error viewing message:', error);
            }
        }

        function closeModal() {
            document.getElementById('messageModal').classList.remove('active');
        }

        async function toggleStar(messageId) {
            try {
                const response = await fetch(`inbox.php?action=toggle_star&id=${messageId}`);
                const data = await response.json();
                
                if (data.success) {
                    fetchMessages();
                }
            } catch (error) {
                console.error('Error toggling star:', error);
            }
        }

        async function updateCounts() {
            try {
                const response = await fetch('inbox.php?action=get_counts');
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('unreadCount').textContent = data.unread;
                    document.getElementById('newCount').textContent = data.new;
                }
            } catch (error) {
                console.error('Error updating counts:', error);
            }
        }

        function formatDate(dateStr) {
            const date = new Date(dateStr);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMs / 3600000);
            const diffDays = Math.floor(diffMs / 86400000);
            
            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return diffMins + 'm ago';
            if (diffHours < 24) return diffHours + 'h ago';
            if (diffDays < 7) return diffDays + 'd ago';
            
            return date.toLocaleDateString();
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Set default filter
        document.getElementById('filterAll').classList.add('active');

        // Auto-refresh counts every 30 seconds
        setInterval(updateCounts, 30000);
    </script>
</body>
</html>