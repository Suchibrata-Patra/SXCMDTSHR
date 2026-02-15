<?php
/**
 * Inbox Page - Minimalist UI matching drive.php aesthetic
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

// Initial data load
$messages = getInboxMessages($userEmail, 50, 0) ?? [];
$totalCount = getInboxMessageCount($userEmail) ?? 0;
$unreadCount = getUnreadCount($userEmail) ?? 0;
$newCount = getNewCount($userEmail) ?? 0;
$lastSync = getLastSyncDate($userEmail);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php define('PAGE_TITLE', 'Inbox | SXC MDTS'); include 'header.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    
    <style>
        :root {
            --ink:       #1a1a2e;
            --ink-2:     #2d2d44;
            --ink-3:     #6b6b8a;
            --ink-4:     #a8a8c0;
            --bg:        #f0f0f7;
            --surface:   #ffffff;
            --surface-2: #f7f7fc;
            --border:    rgba(100,100,160,0.12);
            --border-2:  rgba(100,100,160,0.22);
            --blue:      #5781a9;
            --blue-2:    #c6d3ea;
            --blue-glow: rgba(79,70,229,0.15);
            --amber:     #d97706;
            --r:         10px;
            --r-lg:      16px;
            --shadow:    0 1px 3px rgba(79,70,229,0.08), 0 4px 16px rgba(79,70,229,0.06);
            --shadow-lg: 0 8px 32px rgba(79,70,229,0.14), 0 2px 8px rgba(0,0,0,0.06);
            --ease:      cubic-bezier(.4,0,.2,1);
            --ease-spring: cubic-bezier(.34,1.56,.64,1);
        }

        *,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }

        body {
            font-family: 'DM Sans', -apple-system, sans-serif;
            background: var(--bg);
            color: var(--ink);
            -webkit-font-smoothing: antialiased;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* ── LAYOUT ───────────────────────────────────────────── */
        .app-shell  { display:flex; flex:1; overflow:hidden; }
        .main-col   { flex:1; display:flex; flex-direction:column; overflow:hidden; }

        /* ── TOP BAR ─────────────────────────────────────────── */
        .topbar {
            height: 60px;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 24px;
            flex-shrink: 0;
        }
        .topbar-title {
            font-size: 17px;
            font-weight: 700;
            color: var(--ink);
            letter-spacing: -0.4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .topbar-title .material-icons-round { font-size:20px; color:var(--blue); }
        .topbar-spacer { flex:1; }

        /* Search */
        .search-wrap {
            position: relative;
            width: 280px;
        }
        .search-wrap .material-icons-round {
            position: absolute;
            left: 10px; top: 50%;
            transform: translateY(-50%);
            font-size: 16px;
            color: var(--ink-4);
            pointer-events: none;
        }
        #searchInput {
            width: 100%;
            height: 36px;
            border: 1.5px solid var(--border-2);
            border-radius: 20px;
            padding: 0 12px 0 34px;
            font-family: inherit;
            font-size: 13px;
            background: var(--surface-2);
            color: var(--ink);
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }
        #searchInput:focus { border-color:var(--blue); box-shadow:0 0 0 3px var(--blue-glow); background:var(--surface); }
        #searchInput::placeholder { color:var(--ink-4); }

        /* Buttons */
        .btn {
            height: 36px;
            padding: 0 16px;
            background: var(--blue);
            color: white;
            border: none;
            border-radius: 8px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all .18s var(--ease-spring);
        }
        .btn:hover { background:var(--blue-2); box-shadow:0 4px 12px var(--blue-glow); }
        .btn:active { transform:scale(.96); }
        .btn .material-icons-round { font-size:16px; }

        .btn-icon {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            border: 1.5px solid var(--border-2);
            background: var(--surface);
            color: var(--ink-3);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all .18s;
        }
        .btn-icon:hover {
            border-color: var(--blue);
            color: var(--blue);
            background: var(--blue-glow);
        }
        .btn-icon .material-icons-round { font-size:18px; }
        .btn-icon.rotating .material-icons-round { animation: spin .6s linear; }
        @keyframes spin { to { transform:rotate(360deg); } }

        /* ── STATS BAR ───────────────────────────────────────── */
        .stats-bar {
            background: var(--surface);
            padding: 14px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            gap: 28px;
            align-items: center;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stat-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--surface-2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: var(--ink-3);
        }

        .stat-content {
            display: flex;
            flex-direction: column;
        }

        .stat-number {
            font-size: 17px;
            font-weight: 700;
            color: var(--ink);
            line-height: 1;
            font-family: 'DM Mono', monospace;
        }

        .stat-label {
            font-size: 11px;
            color: var(--ink-3);
            margin-top: 2px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .sync-info {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            color: var(--ink-3);
            font-family: 'DM Mono', monospace;
        }

        .sync-info .material-icons-round {
            font-size: 14px;
        }

        /* ── CONTENT WRAPPER ─────────────────────────────────── */
        .content-wrapper {
            flex: 1;
            overflow: hidden;
            display: grid;
            grid-template-columns: 420px 1fr;
            gap: 0;
        }

        /* ── MESSAGES PANEL ──────────────────────────────────── */
        .messages-panel {
            background: var(--surface);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .toolbar {
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            gap: 8px;
            align-items: center;
            flex-shrink: 0;
        }

        .filter-group {
            display: flex;
            gap: 6px;
        }

        .filter-btn {
            height: 30px;
            padding: 0 12px;
            border: 1.5px solid var(--border-2);
            border-radius: 20px;
            font-family: inherit;
            font-size: 12px;
            font-weight: 600;
            color: var(--ink-3);
            background: var(--surface);
            cursor: pointer;
            transition: all .18s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .filter-btn:hover { border-color:var(--blue); color:var(--blue); background:var(--blue-glow); }
        .filter-btn.active { border-color:var(--blue); color:var(--blue); background:rgba(79,70,229,.1); }
        .filter-btn .material-icons-round { font-size:14px; }

        .messages-area {
            flex: 1;
            overflow-y: auto;
        }

        .messages-area::-webkit-scrollbar { width:5px; }
        .messages-area::-webkit-scrollbar-track { background:transparent; }
        .messages-area::-webkit-scrollbar-thumb { background:var(--border-2); border-radius:10px; }

        /* Message Item */
        .message-item {
            padding: 12px 20px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: background .14s;
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }

        .message-item:hover {
            background: var(--surface-2);
        }

        .message-item.selected {
            background: rgba(79,70,229,.05);
            border-left: 2px solid var(--blue);
        }

        .message-item.unread .message-sender {
            font-weight: 700;
        }

        .message-item.unread .message-subject {
            font-weight: 600;
        }

        .message-star {
            cursor: pointer;
            color: var(--ink-4);
            font-size: 16px;
            margin-top: 2px;
            transition: all .2s;
            flex-shrink: 0;
        }

        .message-star:hover {
            color: var(--amber);
            transform: scale(1.1);
        }

        .message-star.starred {
            color: var(--amber);
        }

        .message-content {
            flex: 1;
            min-width: 0;
        }

        .message-header {
            display: flex;
            align-items: baseline;
            margin-bottom: 2px;
            gap: 8px;
        }

        .message-sender {
            font-weight: 500;
            color: var(--ink);
            font-size: 13px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            flex: 1;
        }

        .message-date {
            font-size: 10px;
            color: var(--ink-4);
            white-space: nowrap;
            font-family: 'DM Mono', monospace;
            flex-shrink: 0;
        }

        .message-subject {
            font-size: 12px;
            color: var(--ink-2);
            margin-bottom: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .message-preview {
            font-size: 11px;
            color: var(--ink-3);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.4;
        }

        .message-badges {
            display: flex;
            gap: 4px;
            margin-top: 6px;
            flex-wrap: wrap;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .badge-new {
            background: rgba(79,70,229,.1);
            color: var(--blue);
        }

        .badge-attachment {
            background: var(--surface-2);
            border: 1px solid var(--border);
            color: var(--ink-3);
        }

        .badge-attachment .material-icons-round {
            font-size: 10px;
        }

        .unread-dot {
            width: 8px;
            height: 8px;
            background: var(--blue);
            border-radius: 50%;
            flex-shrink: 0;
            margin-top: 4px;
        }

        /* ── PREVIEW PANEL ───────────────────────────────────── */
        .preview-panel {
            background: var(--bg);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .preview-header {
            background: var(--surface);
            padding: 16px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }

        .preview-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--ink-2);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .preview-actions {
            display: flex;
            gap: 6px;
        }

        .action-btn {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            border: 1.5px solid var(--border-2);
            background: var(--surface);
            color: var(--ink-3);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all .18s;
        }

        .action-btn:hover {
            border-color: var(--blue);
            color: var(--blue);
            background: var(--blue-glow);
        }

        .action-btn .material-icons-round {
            font-size: 18px;
        }

        /* Message Detail */
        .message-view-body {
            flex: 1;
            overflow-y: auto;
            padding: 24px;
        }

        .message-view-body::-webkit-scrollbar { width:5px; }
        .message-view-body::-webkit-scrollbar-track { background:transparent; }
        .message-view-body::-webkit-scrollbar-thumb { background:var(--border-2); border-radius:10px; }

        .message-detail-header {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            padding: 24px;
            margin-bottom: 20px;
        }

        .message-detail-subject {
            font-size: 20px;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 16px;
            line-height: 1.4;
        }

        .message-detail-from {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }

        .sender-avatar {
            width: 44px;
            height: 44px;
            border-radius: var(--r);
            background: var(--surface-2);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 600;
            color: var(--ink-3);
        }

        .sender-info {
            flex: 1;
        }

        .sender-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--ink);
        }

        .sender-email {
            font-size: 12px;
            color: var(--ink-3);
            font-family: 'DM Mono', monospace;
        }

        .message-detail-meta {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .meta-row {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: var(--ink-3);
        }

        .meta-row .material-icons-round {
            font-size: 14px;
            color: var(--ink-4);
        }

        .meta-label {
            font-weight: 600;
            min-width: 60px;
        }

        .meta-value {
            font-family: 'DM Mono', monospace;
        }

        /* Message Body */
        .message-body-wrap {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            padding: 24px;
        }

        .message-body {
            font-size: 13px;
            line-height: 1.7;
            color: var(--ink-2);
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .message-body a {
            color: var(--blue);
            text-decoration: none;
        }

        .message-body a:hover {
            text-decoration: underline;
        }

        /* Attachments */
        .attachments-section {
            margin-top: 20px;
        }

        .attachments-header {
            font-size: 12px;
            font-weight: 700;
            color: var(--ink-2);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .attachments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 10px;
        }

        .attachment-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r);
            padding: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            transition: all .18s;
            cursor: pointer;
        }

        .attachment-card:hover {
            border-color: var(--blue);
            box-shadow: var(--shadow);
            transform: translateY(-2px);
        }

        .attachment-icon {
            font-size: 32px;
            margin-bottom: 6px;
            color: var(--blue);
        }

        .attachment-name {
            font-size: 11px;
            font-weight: 500;
            color: var(--ink);
            margin-bottom: 3px;
            word-break: break-word;
        }

        .attachment-size {
            font-size: 10px;
            color: var(--ink-3);
            font-family: 'DM Mono', monospace;
        }

        /* Empty State */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 80px 40px;
            text-align: center;
        }

        .empty-icon {
            width: 64px;
            height: 64px;
            border-radius: var(--r-lg);
            background: var(--surface-2);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }

        .empty-icon .material-icons-round {
            font-size: 32px;
            color: var(--ink-4);
        }

        .empty-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--ink-2);
            margin-bottom: 6px;
        }

        .empty-text {
            font-size: 13px;
            color: var(--ink-3);
            max-width: 300px;
        }

        /* Loading */
        .loading {
            padding: 40px;
            text-align: center;
        }

        .loading-spinner {
            display: inline-block;
            width: 32px;
            height: 32px;
            border: 2px solid var(--border);
            border-top-color: var(--blue);
            border-radius: 50%;
            animation: spin .8s linear infinite;
            margin-bottom: 12px;
        }

        .loading-text {
            font-size: 12px;
            color: var(--ink-3);
        }

        /* Toast */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .toast {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 18px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r);
            box-shadow: var(--shadow-lg);
            min-width: 280px;
            animation: slideIn 0.3s ease;
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

        .toast .material-icons-round {
            font-size: 18px;
            color: var(--blue);
        }

        .toast.error .material-icons-round {
            color: var(--ink-3);
        }

        .toast-message {
            flex: 1;
            font-size: 13px;
            font-weight: 500;
            color: var(--ink);
        }

        /* Highlight */
        mark {
            background-color: #fef08a;
            color: var(--ink);
            padding: 1px 3px;
            border-radius: 3px;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .content-wrapper {
                grid-template-columns: 380px 1fr;
            }
        }

        @media (max-width: 968px) {
            .content-wrapper {
                grid-template-columns: 1fr;
            }

            .messages-panel {
                border-right: none;
                border-bottom: 1px solid var(--border);
                max-height: 50vh;
            }
        }
    </style>
</head>
<body>
    <!-- Include sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Main App Shell -->
    <div class="app-shell">
        <div class="main-col">
            <!-- Top Bar -->
            <div class="topbar">
                <div class="topbar-title">
                    <span class="material-icons-round">inbox</span>
                    Inbox
                </div>
                <div class="topbar-spacer"></div>
                <div class="search-wrap">
                    <span class="material-icons-round">search</span>
                    <input 
                        type="text" 
                        id="searchInput" 
                        placeholder="Search messages..."
                        autocomplete="off"
                    >
                </div>
                <button class="btn-icon" id="syncBtn" onclick="syncMessages()">
                    <span class="material-icons-round">sync</span>
                </button>
                <button class="btn" onclick="window.location.href='compose.php'">
                    <span class="material-icons-round">edit</span>
                    Compose
                </button>
            </div>

            <!-- Stats Bar -->
            <div class="stats-bar">
                <div class="stat-item">
                    <div class="stat-icon total">
                        <span class="material-icons-round">inbox</span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number" id="total-count"><?= $totalCount ?></div>
                        <div class="stat-label">Total</div>
                    </div>
                </div>

                <div class="stat-item">
                    <div class="stat-icon unread">
                        <span class="material-icons-round">mark_email_unread</span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number" id="unread-count"><?= $unreadCount ?></div>
                        <div class="stat-label">Unread</div>
                    </div>
                </div>

                <div class="stat-item">
                    <div class="stat-icon new">
                        <span class="material-icons-round">fiber_new</span>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number" id="new-count"><?= $newCount ?></div>
                        <div class="stat-label">New</div>
                    </div>
                </div>

                <div class="sync-info">
                    <span class="material-icons-round">schedule</span>
                    <span id="last-sync"><?= $lastSync ? date('M j, g:i A', strtotime($lastSync)) : 'Never' ?></span>
                </div>
            </div>

            <!-- Content Wrapper -->
            <div class="content-wrapper">
                <!-- Messages Panel -->
                <div class="messages-panel">
                    <div class="toolbar">
                        <div class="filter-group">
                            <button class="filter-btn" id="filter-unread" onclick="toggleFilter('unread')">
                                <span class="material-icons-round">mark_email_unread</span>
                                Unread
                            </button>
                            <button class="filter-btn" id="filter-starred" onclick="toggleFilter('starred')">
                                <span class="material-icons-round">star</span>
                                Starred
                            </button>
                            <button class="filter-btn" id="filter-new" onclick="toggleFilter('new')">
                                <span class="material-icons-round">fiber_new</span>
                                New
                            </button>
                        </div>
                    </div>

                    <div class="messages-area" id="messages-container">
                        <?php if (empty($messages)): ?>
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <span class="material-icons-round">inbox</span>
                                </div>
                                <div class="empty-title">No messages</div>
                                <div class="empty-text">Your inbox is empty</div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($messages as $msg): 
                                $unreadClass = $msg['is_read'] ? '' : 'unread';
                                $starredClass = $msg['is_starred'] ? 'starred' : '';
                            ?>
                                <div class="message-item <?= $unreadClass ?>" 
                                     data-id="<?= $msg['id'] ?>" 
                                     onclick="loadMessage(<?= $msg['id'] ?>)">
                                    
                                    <?php if (!$msg['is_read']): ?>
                                        <div class="unread-dot"></div>
                                    <?php endif; ?>

                                    <span class="material-icons-round message-star <?= $starredClass ?>" 
                                          onclick="event.stopPropagation(); toggleStar(<?= $msg['id'] ?>)">
                                        <?= $msg['is_starred'] ? 'star' : 'star_border' ?>
                                    </span>

                                    <div class="message-content">
                                        <div class="message-header">
                                            <div class="message-sender"><?= htmlspecialchars($msg['sender_name'] ?: $msg['sender_email']) ?></div>
                                            <div class="message-date"><?= date('M j', strtotime($msg['received_date'])) ?></div>
                                        </div>
                                        <div class="message-subject"><?= htmlspecialchars($msg['subject'] ?: '(No Subject)') ?></div>
                                        <div class="message-preview"><?= htmlspecialchars(substr($msg['body_preview'] ?? '', 0, 100)) ?></div>
                                        
                                        <?php if ($msg['is_new'] || $msg['has_attachments']): ?>
                                            <div class="message-badges">
                                                <?php if ($msg['is_new']): ?>
                                                    <span class="badge badge-new">New</span>
                                                <?php endif; ?>
                                                <?php if ($msg['has_attachments']): ?>
                                                    <span class="badge badge-attachment">
                                                        <span class="material-icons-round">attach_file</span>
                                                        Attachment
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Preview Panel -->
                <div class="preview-panel">
                    <div class="preview-header">
                        <div class="preview-title">Message Preview</div>
                        <div class="preview-actions" id="preview-actions" style="display: none;">
                            <button class="action-btn" onclick="markAsUnread()" title="Mark as unread">
                                <span class="material-icons-round">mark_email_unread</span>
                            </button>
                            <button class="action-btn" onclick="deleteMessage()" title="Delete">
                                <span class="material-icons-round">delete</span>
                            </button>
                        </div>
                    </div>

                    <div class="message-view-body" id="message-detail">
                        <div class="empty-state">
                            <div class="empty-icon">
                                <span class="material-icons-round">mail_outline</span>
                            </div>
                            <div class="empty-title">Select a message</div>
                            <div class="empty-text">Choose a message from the list to view its contents</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let selectedMessageId = null;
        let currentFilters = { unread: false, starred: false, new: false };
        let currentQuery = '';

        // Load message details
        function loadMessage(id) {
            selectedMessageId = id;
            
            // Update active state
            document.querySelectorAll('.message-item').forEach(item => {
                item.classList.remove('selected');
            });
            document.querySelector(`[data-id="${id}"]`).classList.add('selected');

            // Show loading
            const detailDiv = document.getElementById('message-detail');
            detailDiv.innerHTML = `
                <div class="loading">
                    <div class="loading-spinner"></div>
                    <div class="loading-text">Loading message...</div>
                </div>
            `;

            // Fetch message
            fetch(`?action=get_message&id=${id}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.message) {
                        displayMessage(data.message);
                        document.getElementById('preview-actions').style.display = 'flex';
                        
                        // Update unread count
                        updateCounts();
                    } else {
                        showToast('Failed to load message', 'error');
                    }
                })
                .catch(err => {
                    showToast('Error loading message', 'error');
                });
        }

        // Display message in preview
        function displayMessage(msg) {
            const detailDiv = document.getElementById('message-detail');
            
            // Get initials for avatar
            const senderName = msg.sender_name || msg.sender_email;
            const initials = senderName.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
            
            // Parse attachments
            let attachmentsHtml = '';
            if (msg.has_attachments && msg.attachment_data) {
                try {
                    const attachments = JSON.parse(msg.attachment_data);
                    attachmentsHtml = `
                        <div class="attachments-section">
                            <div class="attachments-header">
                                <span class="material-icons-round">attach_file</span>
                                Attachments (${attachments.length})
                            </div>
                            <div class="attachments-grid">
                                ${attachments.map(att => `
                                    <div class="attachment-card">
                                        <span class="material-icons-round attachment-icon">insert_drive_file</span>
                                        <div class="attachment-name">${escapeHtml(att.name)}</div>
                                        <div class="attachment-size">${formatBytes(att.size)}</div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    `;
                } catch (e) {
                    console.error('Error parsing attachments:', e);
                }
            }

            detailDiv.innerHTML = `
                <div class="message-detail-header">
                    <div class="message-detail-subject">${escapeHtml(msg.subject || '(No Subject)')}</div>
                    
                    <div class="message-detail-from">
                        <div class="sender-avatar">${initials}</div>
                        <div class="sender-info">
                            <div class="sender-name">${escapeHtml(senderName)}</div>
                            <div class="sender-email">${escapeHtml(msg.sender_email)}</div>
                        </div>
                    </div>

                    <div class="message-detail-meta">
                        <div class="meta-row">
                            <span class="material-icons-round">schedule</span>
                            <span class="meta-label">Received:</span>
                            <span class="meta-value">${formatDate(msg.received_date)}</span>
                        </div>
                        <div class="meta-row">
                            <span class="material-icons-round">email</span>
                            <span class="meta-label">Message ID:</span>
                            <span class="meta-value">${escapeHtml(msg.message_id)}</span>
                        </div>
                    </div>
                </div>

                <div class="message-body-wrap">
                    <div class="message-body">${escapeHtml(msg.body_preview || '')}</div>
                    ${attachmentsHtml}
                </div>
            `;
        }

        // Toggle star
        function toggleStar(id) {
            fetch(`?action=toggle_star&id=${id}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const item = document.querySelector(`[data-id="${id}"]`);
                        const star = item.querySelector('.message-star');
                        
                        if (star.classList.contains('starred')) {
                            star.classList.remove('starred');
                            star.textContent = 'star_border';
                        } else {
                            star.classList.add('starred');
                            star.textContent = 'star';
                        }
                    }
                });
        }

        // Mark as unread
        function markAsUnread() {
            if (!selectedMessageId) return;

            fetch(`?action=mark_unread&id=${selectedMessageId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast('Marked as unread', 'success');
                        const item = document.querySelector(`[data-id="${selectedMessageId}"]`);
                        if (item) {
                            item.classList.add('unread');
                            if (!item.querySelector('.unread-dot')) {
                                const dot = document.createElement('div');
                                dot.className = 'unread-dot';
                                item.insertBefore(dot, item.firstChild);
                            }
                        }
                        updateCounts();
                    }
                });
        }

        // Delete message
        function deleteMessage() {
            if (!selectedMessageId) return;

            if (!confirm('Move this message to trash?')) return;

            fetch(`?action=delete&id=${selectedMessageId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast('Message moved to trash', 'success');
                        
                        const messageItem = document.querySelector(`[data-id="${selectedMessageId}"]`);
                        if (messageItem) {
                            messageItem.style.opacity = '0';
                            setTimeout(() => {
                                messageItem.remove();
                                clearPreview();
                                updateCounts();
                            }, 200);
                        }
                    } else {
                        showToast('Failed to delete message', 'error');
                    }
                });
        }

        // Sync messages
        function syncMessages(force = false) {
            const syncBtn = document.getElementById('syncBtn');
            syncBtn.classList.add('rotating');

            fetch(`?action=sync&limit=50${force ? '&force=true' : ''}`)
                .then(res => res.json())
                .then(data => {
                    syncBtn.classList.remove('rotating');
                    
                    if (data.success) {
                        showToast(`Synced ${data.new_count} new message(s)`, 'success');
                        loadMessages();
                        updateCounts();
                        updateLastSync();
                    } else {
                        showToast(data.error || 'Sync failed', 'error');
                    }
                })
                .catch(err => {
                    syncBtn.classList.remove('rotating');
                    showToast('Sync error', 'error');
                });
        }

        // Toggle filter
        function toggleFilter(type) {
            currentFilters[type] = !currentFilters[type];
            document.getElementById(`filter-${type}`).classList.toggle('active');
            loadMessages();
        }

        // Load messages
        function loadMessages() {
            const params = new URLSearchParams({
                action: 'fetch_messages',
                limit: 50,
                offset: 0
            });

            if (currentFilters.unread) params.append('unread_only', '1');
            if (currentFilters.starred) params.append('starred_only', '1');
            if (currentFilters.new) params.append('new_only', '1');
            if (currentQuery) params.append('search', currentQuery);

            fetch(`?${params}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        renderMessages(data.messages);
                    }
                });
        }

        // Render messages
        function renderMessages(messages) {
            const container = document.getElementById('messages-container');
            
            if (messages.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">
                            <span class="material-icons-round">inbox</span>
                        </div>
                        <div class="empty-title">No messages</div>
                        <div class="empty-text">No messages match your filters</div>
                    </div>
                `;
                return;
            }

            container.innerHTML = messages.map(msg => {
                const unreadClass = msg.is_read ? '' : 'unread';
                const starredClass = msg.is_starred ? 'starred' : '';
                
                return `
                    <div class="message-item ${unreadClass}" 
                         data-id="${msg.id}" 
                         onclick="loadMessage(${msg.id})">
                        
                        ${!msg.is_read ? '<div class="unread-dot"></div>' : ''}

                        <span class="material-icons-round message-star ${starredClass}" 
                              onclick="event.stopPropagation(); toggleStar(${msg.id})">
                            ${msg.is_starred ? 'star' : 'star_border'}
                        </span>

                        <div class="message-content">
                            <div class="message-header">
                                <div class="message-sender">${escapeHtml(msg.sender_name || msg.sender_email)}</div>
                                <div class="message-date">${formatDateShort(msg.received_date)}</div>
                            </div>
                            <div class="message-subject">${escapeHtml(msg.subject || '(No Subject)')}</div>
                            <div class="message-preview">${escapeHtml((msg.body_preview || '').substring(0, 100))}</div>
                            
                            ${(msg.is_new || msg.has_attachments) ? `
                                <div class="message-badges">
                                    ${msg.is_new ? '<span class="badge badge-new">New</span>' : ''}
                                    ${msg.has_attachments ? '<span class="badge badge-attachment"><span class="material-icons-round">attach_file</span>Attachment</span>' : ''}
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Search
        document.getElementById('searchInput').addEventListener('input', function(e) {
            currentQuery = e.target.value.trim();
            
            if (currentQuery.length > 0 || currentFilters.unread || currentFilters.starred || currentFilters.new) {
                loadMessages();
            } else {
                // Reset to initial state
                window.location.reload();
            }
        });

        // Update counts
        function updateCounts() {
            fetch('?action=get_counts')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('unread-count').textContent = data.unread;
                        document.getElementById('new-count').textContent = data.new;
                    }
                });
        }

        // Update last sync time
        function updateLastSync() {
            const now = new Date();
            const formatted = now.toLocaleString('en-US', { 
                month: 'short', 
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
            document.getElementById('last-sync').textContent = formatted;
        }

        // Clear preview
        function clearPreview() {
            document.getElementById('message-detail').innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">
                        <span class="material-icons-round">mail_outline</span>
                    </div>
                    <div class="empty-title">Select a message</div>
                    <div class="empty-text">Choose a message from the list to view its contents</div>
                </div>
            `;
            document.getElementById('preview-actions').style.display = 'none';
            selectedMessageId = null;
        }

        // Utility functions
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleString('en-US', { 
                month: 'short', 
                day: 'numeric', 
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function formatDateShort(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        }

        function formatBytes(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }

        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            const icon = type === 'success' ? 'check_circle' : 'error';
            
            toast.innerHTML = `
                <span class="material-icons-round">${icon}</span>
                <span class="toast-message">${escapeHtml(message)}</span>
            `;
            
            document.getElementById('toastContainer').appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Auto-sync every 5 minutes
        setInterval(() => {
            syncMessages();
        }, 5 * 60 * 1000);
    </script>
</body>
</html>