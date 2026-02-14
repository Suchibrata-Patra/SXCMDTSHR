<?php
/**
 * SXC MDTS Inbox - Redesigned with Drive.php Clean UI/UX
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
    <?php
        define('PAGE_TITLE', 'SXC MDTS | Inbox');
        include 'header.php';
    ?>
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
            --red:       #ef4444;
            --green:     #10b981;
            --amber:     #f59e0b;
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

        /* ── LAYOUT ─────────────────────────────────────────── */
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
            position: relative;
            z-index: 10;
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
        .topbar-subtitle {
            font-size: 12px;
            color: var(--ink-3);
            font-weight: 500;
            margin-left: 4px;
        }
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
            border: none;
            border-radius: 8px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all .18s var(--ease);
            white-space: nowrap;
        }
        .btn .material-icons-round { font-size:16px; }
        
        .btn-primary {
            background: var(--blue);
            color: white;
        }
        .btn-primary:hover  { background:var(--blue-2); box-shadow:0 4px 12px var(--blue-glow); }
        .btn-primary:active { transform:scale(.96); }
        
        .btn-secondary {
            background: var(--surface-2);
            color: var(--ink-2);
            border: 1.5px solid var(--border-2);
        }
        .btn-secondary:hover { background:var(--surface); border-color:var(--blue); color:var(--blue); }

        .sync-btn.syncing .material-icons-round {
            animation: spin .8s linear infinite;
        }
        @keyframes spin { to { transform:rotate(360deg); } }

        /* ── CONTENT AREA ─────────────────────────────────────── */
        .content-area {
            flex: 1;
            overflow-y: auto;
            padding: 24px;
        }
        .content-area::-webkit-scrollbar { width:5px; }
        .content-area::-webkit-scrollbar-track { background:transparent; }
        .content-area::-webkit-scrollbar-thumb { background:var(--border-2); border-radius:10px; }

        /* ── STATS CARDS ─────────────────────────────────────── */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            cursor: pointer;
            transition: all .18s var(--ease);
            box-shadow: var(--shadow);
        }
        .stat-card:hover {
            border-color: var(--blue);
            box-shadow: var(--shadow-lg);
            transform: translateY(-1px);
        }
        .stat-card.active {
            border-color: var(--blue);
            background: rgba(79,70,229,.04);
        }
        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--r);
            background: var(--surface-2);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .stat-icon .material-icons-round {
            font-size: 20px;
            color: var(--blue);
        }
        .stat-content {
            flex: 1;
        }
        .stat-label {
            font-size: 12px;
            color: var(--ink-3);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .4px;
            margin-bottom: 4px;
        }
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--ink);
            line-height: 1;
        }

        /* ── FILTER CHIPS ─────────────────────────────────────── */
        .filter-row {
            display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px;
        }
        .chip {
            height:30px; padding:0 12px;
            border:1.5px solid var(--border-2);
            border-radius:20px; font-family:inherit;
            font-size:12px; font-weight:600;
            color:var(--ink-3); background:var(--surface);
            cursor:pointer; transition:all .18s;
            display:flex; align-items:center; gap:5px;
        }
        .chip:hover  { border-color:var(--blue); color:var(--blue); background:var(--blue-glow); }
        .chip.active { border-color:var(--blue); color:var(--blue); background:rgba(79,70,229,.1); }
        .chip .material-icons-round { font-size:14px; }

        /* ── MESSAGE LIST ─────────────────────────────────────── */
        .messages-wrap {
            background:var(--surface);
            border:1px solid var(--border);
            border-radius:var(--r-lg);
            overflow:hidden;
            box-shadow: var(--shadow);
        }
        .message-table {
            width:100%; border-collapse:collapse;
        }
        .message-table thead th {
            background:var(--surface-2);
            padding:10px 16px;
            text-align:left;
            font-size:11px; font-weight:700;
            color:var(--ink-3);
            text-transform:uppercase; letter-spacing:.6px;
            border-bottom:1px solid var(--border);
            white-space:nowrap;
        }

        .message-table tbody tr {
            border-bottom:1px solid var(--border);
            transition:background .14s;
            cursor: pointer;
        }
        .message-table tbody tr:last-child { border-bottom:none; }
        .message-table tbody tr:hover { background:var(--surface-2); }
        .message-table tbody tr.unread { background:rgba(79,70,229,.02); }
        .message-table tbody tr.unread:hover { background:rgba(79,70,229,.06); }

        .message-table td {
            padding:12px 16px;
            font-size:13.5px;
            vertical-align:middle;
        }
        .message-table td.td-check { width:40px; padding-right:0; }
        .message-table td.td-avatar { width:50px; padding-right:8px; }
        .message-table td.td-from { font-weight:500; min-width:180px; }
        .message-table td.td-subject { font-weight:500; max-width:400px; }
        .message-table td.td-preview { color:var(--ink-3); max-width:300px; font-size:12px; }
        .message-table td.td-time { color:var(--ink-3); width:100px; font-size:12px; white-space:nowrap; }
        .message-table td.td-actions { width:120px; text-align:right; }

        .message-table tbody tr.unread td.td-from,
        .message-table tbody tr.unread td.td-subject {
            font-weight: 700;
        }

        .text-ellipsis {
            overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
            display:inline-block; max-width:100%; vertical-align:middle;
        }

        /* Avatar */
        .msg-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--blue), var(--blue-2));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
        }

        /* Checkbox */
        .cb-msg {
            width:16px; height:16px; accent-color:var(--blue); cursor:pointer;
        }

        /* Badges */
        .badge {
            display:inline-flex;
            padding:2px 7px;
            border-radius:10px;
            font-size:10px;
            font-weight:700;
            text-transform:uppercase;
            letter-spacing:.3px;
            margin-left:6px;
        }
        .badge.new {
            background:rgba(16,185,129,.1);
            color:var(--green);
        }
        .badge.unread {
            background:rgba(79,70,229,.1);
            color:var(--blue);
        }

        /* Row action buttons */
        .row-actions { 
            display:flex; align-items:center; justify-content:flex-end; gap:6px; 
            opacity:0; transition:opacity .14s; 
        }
        .message-table tbody tr:hover .row-actions { opacity:1; }
        .act-btn {
            width:28px; height:28px;
            border:none; border-radius:6px;
            background:transparent; cursor:pointer;
            display:flex; align-items:center; justify-content:center;
            color:var(--ink-3); transition:all .15s;
        }
        .act-btn:hover { background:var(--surface-2); color:var(--blue); }
        .act-btn.starred { color:var(--amber); }
        .act-btn.del:hover { background:rgba(239,68,68,.08); color:var(--red); }
        .act-btn .material-icons-round { font-size:16px; }

        /* ── EMPTY STATE ─────────────────────────────────────── */
        .empty-state {
            display:flex; flex-direction:column;
            align-items:center; justify-content:center;
            padding:80px 0; gap:12px; text-align:center;
        }
        .empty-state .material-icons-round { font-size:56px; color:var(--ink-4); }
        .empty-state h3 { font-size:18px; font-weight:700; color:var(--ink-2); }
        .empty-state p  { font-size:14px; color:var(--ink-3); max-width:300px; }

        /* ── MODALS ──────────────────────────────────────────── */
        .modal-backdrop {
            position:fixed; inset:0; z-index:1000;
            background:rgba(20,20,40,.55);
            backdrop-filter:blur(4px);
            display:none; align-items:center; justify-content:center;
        }
        .modal-backdrop.show { display:flex; }

        .modal {
            background:var(--surface);
            border-radius:var(--r-lg);
            box-shadow:var(--shadow-lg);
            min-width:600px;
            max-width:90vw;
            max-height:90vh;
            display:flex;
            flex-direction:column;
            animation:modalIn .22s var(--ease-spring) forwards;
        }
        @keyframes modalIn { from{opacity:0;transform:scale(.92) translateY(8px)} to{opacity:1;transform:none} }

        .modal-header {
            padding:20px 24px 16px;
            border-bottom:1px solid var(--border);
            display:flex;
            align-items:center;
            justify-content:space-between;
        }
        .modal-header h3 {
            font-size:16px;
            font-weight:700;
            color:var(--ink);
            overflow:hidden;
            text-overflow:ellipsis;
            white-space:nowrap;
            flex:1;
        }
        .modal-close {
            width:32px; height:32px;
            border:none; background:transparent;
            border-radius:6px; cursor:pointer;
            display:flex; align-items:center; justify-content:center;
            color:var(--ink-3); transition:all .15s;
            flex-shrink:0;
        }
        .modal-close:hover { background:var(--surface-2); color:var(--ink); }
        .modal-close .material-icons-round { font-size:18px; }

        .modal-body {
            padding:24px;
            overflow-y:auto;
            flex:1;
        }
        .modal-body::-webkit-scrollbar { width:5px; }
        .modal-body::-webkit-scrollbar-track { background:transparent; }
        .modal-body::-webkit-scrollbar-thumb { background:var(--border-2); border-radius:10px; }

        .message-detail-header {
            margin-bottom:24px;
            padding-bottom:20px;
            border-bottom:1px solid var(--border);
        }
        .message-detail-subject {
            font-size:20px;
            font-weight:700;
            color:var(--ink);
            margin-bottom:16px;
            line-height:1.3;
        }
        .message-detail-meta {
            display:flex;
            align-items:center;
            gap:12px;
            flex-wrap:wrap;
        }
        .message-detail-from {
            display:flex;
            align-items:center;
            gap:10px;
        }
        .sender-info strong {
            display:block;
            font-size:13px;
            font-weight:600;
            color:var(--ink);
            margin-bottom:2px;
        }
        .sender-info span {
            font-size:12px;
            color:var(--ink-3);
        }
        .message-detail-date {
            font-size:12px;
            color:var(--ink-3);
        }
        .message-detail-body {
            font-size:14px;
            line-height:1.7;
            color:var(--ink-2);
        }

        /* ── TOAST ───────────────────────────────────────────── */
        .toast-container {
            position:fixed;
            bottom:24px; right:24px;
            z-index:10000;
            display:flex;
            flex-direction:column;
            gap:12px;
        }
        .toast {
            background:var(--surface);
            border:1px solid var(--border);
            border-radius:var(--r);
            padding:14px 18px;
            box-shadow:var(--shadow-lg);
            display:flex;
            align-items:center;
            gap:12px;
            min-width:280px;
            animation:toastIn .3s var(--ease-spring);
        }
        @keyframes toastIn { from{opacity:0;transform:translateX(100px)} to{opacity:1;transform:translateX(0)} }
        .toast.success { border-left:3px solid var(--green); }
        .toast.error { border-left:3px solid var(--red); }
        .toast.info { border-left:3px solid var(--blue); }
        .toast .material-icons-round { font-size:20px; }
        .toast.success .material-icons-round { color:var(--green); }
        .toast.error .material-icons-round { color:var(--red); }
        .toast.info .material-icons-round { color:var(--blue); }
        .toast-message {
            flex:1;
            font-size:13px;
            font-weight:500;
            color:var(--ink);
        }

        /* ── LOADING ─────────────────────────────────────────── */
        .loading-spinner {
            display:flex; align-items:center; justify-content:center;
            padding:80px; gap:12px; color:var(--ink-3); font-weight:600;
        }
        .loading-spinner .material-icons-round {
            animation: spin .8s linear infinite;
        }

        /* ── HIGHLIGHT ───────────────────────────────────────── */
        mark {
            background:rgba(245,158,11,.2);
            color:var(--ink);
            padding:2px 4px;
            border-radius:3px;
            font-weight:600;
        }

        /* ── RESPONSIVE ──────────────────────────────────────── */
        @media (max-width: 768px) {
            .topbar {
                padding: 0 16px;
            }
            .search-wrap {
                width: 200px;
            }
            .content-area {
                padding: 16px;
            }
            .stats-row {
                grid-template-columns: 1fr;
            }
            .modal {
                min-width: 95vw;
            }
            .message-table td.td-preview {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php include 'sidebar.php'; ?>

    <div class="app-shell">
        <div class="main-col">
            <!-- Top Bar -->
            <div class="topbar">
                <div class="topbar-title">
                    <span class="material-icons-round">mail</span>
                    Inbox
                </div>
                <span class="topbar-subtitle"><?= htmlspecialchars($userEmail) ?></span>
                <div class="topbar-spacer"></div>
                
                <div class="search-wrap">
                    <span class="material-icons-round">search</span>
                    <input type="text" id="searchInput" placeholder="Search messages..." autocomplete="off">
                </div>

                <button class="btn btn-secondary" onclick="window.location.href='compose.php'">
                    <span class="material-icons-round">edit</span>
                    Compose
                </button>
                
                <button class="btn btn-primary sync-btn" onclick="syncMessages()">
                    <span class="material-icons-round">sync</span>
                    Sync
                </button>
            </div>

            <!-- Content Area -->
            <div class="content-area">
                <!-- Stats Cards -->
                <div class="stats-row">
                    <div class="stat-card" onclick="clearFilters()">
                        <div class="stat-icon">
                            <span class="material-icons-round">inbox</span>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Total</div>
                            <div class="stat-value" id="totalCount"><?= $totalCount ?></div>
                        </div>
                    </div>
                    <div class="stat-card" onclick="filterUnread()">
                        <div class="stat-icon">
                            <span class="material-icons-round">mark_email_unread</span>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">Unread</div>
                            <div class="stat-value" id="unreadCount"><?= $unreadCount ?></div>
                        </div>
                    </div>
                    <div class="stat-card" onclick="filterNew()">
                        <div class="stat-icon">
                            <span class="material-icons-round">fiber_new</span>
                        </div>
                        <div class="stat-content">
                            <div class="stat-label">New</div>
                            <div class="stat-value" id="newCount"><?= $newCount ?></div>
                        </div>
                    </div>
                </div>

                <!-- Filter Chips -->
                <div class="filter-row">
                    <div class="chip active" id="filterAll" onclick="clearFilters()">
                        <span class="material-icons-round">all_inbox</span>
                        All
                    </div>
                    <div class="chip" id="filterUnread" onclick="filterUnread()">
                        <span class="material-icons-round">mark_email_unread</span>
                        Unread
                    </div>
                    <div class="chip" id="filterStarred" onclick="filterStarred()">
                        <span class="material-icons-round">star</span>
                        Starred
                    </div>
                    <div class="chip" id="filterNew" onclick="filterNew()">
                        <span class="material-icons-round">fiber_new</span>
                        New
                    </div>
                </div>

                <!-- Messages Table -->
                <div class="messages-wrap">
                    <table class="message-table" id="messageList">
                        <thead>
                            <tr>
                                <th class="td-check"><input type="checkbox" class="cb-msg" id="selectAll"></th>
                                <th class="td-avatar"></th>
                                <th class="td-from">From</th>
                                <th class="td-subject">Subject</th>
                                <th class="td-preview">Preview</th>
                                <th class="td-time">Time</th>
                                <th class="td-actions"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($messages)): ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state">
                                            <span class="material-icons-round">inbox</span>
                                            <h3>No messages yet</h3>
                                            <p>Your inbox is empty. Sync to fetch new messages.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($messages as $msg): ?>
                                    <?php
                                    $initial = strtoupper(substr($msg['sender_email'], 0, 1));
                                    $isUnread = empty($msg['read_at']);
                                    $isNew = !empty($msg['is_new']) && $msg['is_new'] == 1;
                                    $isStarred = !empty($msg['starred']) && $msg['starred'] == 1;
                                    ?>
                                    <tr class="<?= $isUnread ? 'unread' : '' ?>" 
                                        data-message-id="<?= $msg['id'] ?>"
                                        onclick="openMessage(<?= $msg['id'] ?>)">
                                        <td class="td-check" onclick="event.stopPropagation()">
                                            <input type="checkbox" class="cb-msg">
                                        </td>
                                        <td class="td-avatar">
                                            <div class="msg-avatar"><?= $initial ?></div>
                                        </td>
                                        <td class="td-from">
                                            <span class="text-ellipsis message-sender"><?= htmlspecialchars($msg['sender_name'] ?: $msg['sender_email']) ?></span>
                                            <?php if ($isNew): ?>
                                                <span class="badge new">New</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="td-subject">
                                            <span class="text-ellipsis message-subject"><?= htmlspecialchars($msg['subject'] ?: '(No Subject)') ?></span>
                                        </td>
                                        <td class="td-preview">
                                            <span class="text-ellipsis message-preview"><?= htmlspecialchars(substr(strip_tags($msg['body_text'] ?? $msg['body_html'] ?? ''), 0, 80)) ?></span>
                                        </td>
                                        <td class="td-time"><?= date('M d', strtotime($msg['received_at'])) ?></td>
                                        <td class="td-actions">
                                            <div class="row-actions">
                                                <button class="act-btn <?= $isStarred ? 'starred' : '' ?>" 
                                                        onclick="event.stopPropagation(); toggleStar(<?= $msg['id'] ?>, this)"
                                                        title="Star">
                                                    <span class="material-icons-round"><?= $isStarred ? 'star' : 'star_outline' ?></span>
                                                </button>
                                                <button class="act-btn del" 
                                                        onclick="event.stopPropagation(); deleteMessage(<?= $msg['id'] ?>)"
                                                        title="Delete">
                                                    <span class="material-icons-round">delete_outline</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Message Detail Modal -->
    <div class="modal-backdrop" id="messageModal">
        <div class="modal">
            <div class="modal-header">
                <h3 id="modalSubject">Message</h3>
                <button class="modal-close" onclick="closeMessageModal()">
                    <span class="material-icons-round">close</span>
                </button>
            </div>
            <div class="modal-body" id="modalBody">
                <div class="loading-spinner">
                    <span class="material-icons-round">autorenew</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script>
        // ═══════════════════════════════════════════════════════════
        //  GLOBAL STATE
        // ═══════════════════════════════════════════════════════════
        let currentFilters = {
            search: '',
            unread_only: false,
            starred_only: false,
            new_only: false
        };
        const originalMessageContent = new Map();

        // ═══════════════════════════════════════════════════════════
        //  INITIALIZATION
        // ═══════════════════════════════════════════════════════════
        document.addEventListener('DOMContentLoaded', function() {
            storeOriginalContent();
            setupSearchListener();
        });

        // ═══════════════════════════════════════════════════════════
        //  SYNC MESSAGES
        // ═══════════════════════════════════════════════════════════
        async function syncMessages() {
            const btn = document.querySelector('.sync-btn');
            btn.classList.add('syncing');
            
            try {
                const response = await fetch('inbox.php?action=sync&limit=50&force=true');
                const data = await response.json();
                
                if (data.success) {
                    await fetchMessages();
                    await updateCounts();
                    showToast(data.message || 'Inbox synced successfully!', 'success');
                } else {
                    showToast(data.error || 'Sync failed', 'error');
                }
            } catch (error) {
                showToast('Sync error: ' + error.message, 'error');
            } finally {
                btn.classList.remove('syncing');
            }
        }

        // ═══════════════════════════════════════════════════════════
        //  FETCH MESSAGES
        // ═══════════════════════════════════════════════════════════
        async function fetchMessages() {
            const tbody = document.querySelector('#messageList tbody');
            tbody.innerHTML = '<tr><td colspan="7"><div class="loading-spinner"><span class="material-icons-round">autorenew</span></div></td></tr>';
            
            try {
                const params = new URLSearchParams({
                    action: 'fetch_messages',
                    limit: 50,
                    offset: 0,
                    ...currentFilters
                });
                
                const response = await fetch('inbox.php?' + params);
                const data = await response.json();
                
                if (data.success && data.messages) {
                    renderMessages(data.messages);
                    document.getElementById('totalCount').textContent = data.total;
                    
                    setTimeout(() => {
                        storeOriginalContent();
                        performClientSideSearch();
                    }, 100);
                }
            } catch (error) {
                tbody.innerHTML = `<tr><td colspan="7"><div class="empty-state">
                    <span class="material-icons-round">error_outline</span>
                    <h3>Error loading messages</h3>
                    <p>${escapeHtml(error.message)}</p>
                </div></td></tr>`;
            }
        }

        // ═══════════════════════════════════════════════════════════
        //  RENDER MESSAGES
        // ═══════════════════════════════════════════════════════════
        function renderMessages(messages) {
            const tbody = document.querySelector('#messageList tbody');
            
            if (!messages || messages.length === 0) {
                tbody.innerHTML = `<tr><td colspan="7"><div class="empty-state">
                    <span class="material-icons-round">inbox</span>
                    <h3>No messages found</h3>
                    <p>Try adjusting your filters or sync to fetch new messages.</p>
                </div></td></tr>`;
                return;
            }
            
            tbody.innerHTML = messages.map(msg => {
                const initial = msg.sender_email.charAt(0).toUpperCase();
                const isUnread = !msg.read_at;
                const isNew = msg.is_new == 1;
                const isStarred = msg.starred == 1;
                
                return `
                    <tr class="${isUnread ? 'unread' : ''}" 
                        data-message-id="${msg.id}"
                        onclick="openMessage(${msg.id})">
                        <td class="td-check" onclick="event.stopPropagation()">
                            <input type="checkbox" class="cb-msg">
                        </td>
                        <td class="td-avatar">
                            <div class="msg-avatar">${initial}</div>
                        </td>
                        <td class="td-from">
                            <span class="text-ellipsis message-sender">${escapeHtml(msg.sender_name || msg.sender_email)}</span>
                            ${isNew ? '<span class="badge new">New</span>' : ''}
                        </td>
                        <td class="td-subject">
                            <span class="text-ellipsis message-subject">${escapeHtml(msg.subject || '(No Subject)')}</span>
                        </td>
                        <td class="td-preview">
                            <span class="text-ellipsis message-preview">${escapeHtml((msg.body_text || msg.body_html || '').replace(/<[^>]*>/g, '').substring(0, 80))}</span>
                        </td>
                        <td class="td-time">${formatDate(msg.received_at)}</td>
                        <td class="td-actions">
                            <div class="row-actions">
                                <button class="act-btn ${isStarred ? 'starred' : ''}" 
                                        onclick="event.stopPropagation(); toggleStar(${msg.id}, this)"
                                        title="Star">
                                    <span class="material-icons-round">${isStarred ? 'star' : 'star_outline'}</span>
                                </button>
                                <button class="act-btn del" 
                                        onclick="event.stopPropagation(); deleteMessage(${msg.id})"
                                        title="Delete">
                                    <span class="material-icons-round">delete_outline</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        // ═══════════════════════════════════════════════════════════
        //  OPEN MESSAGE DETAIL
        // ═══════════════════════════════════════════════════════════
        async function openMessage(id) {
            const modal = document.getElementById('messageModal');
            const modalBody = document.getElementById('modalBody');
            const modalSubject = document.getElementById('modalSubject');
            
            modal.classList.add('show');
            modalBody.innerHTML = '<div class="loading-spinner"><span class="material-icons-round">autorenew</span></div>';
            modalSubject.textContent = 'Loading...';
            
            try {
                const response = await fetch(`inbox.php?action=get_message&id=${id}`);
                const data = await response.json();
                
                if (data.success && data.message) {
                    const msg = data.message;
                    const initial = msg.sender_email.charAt(0).toUpperCase();
                    
                    modalSubject.textContent = msg.subject || '(No Subject)';
                    modalBody.innerHTML = `
                        <div class="message-detail-header">
                            <h3 class="message-detail-subject">${escapeHtml(msg.subject || '(No Subject)')}</h3>
                            <div class="message-detail-meta">
                                <div class="message-detail-from">
                                    <div class="msg-avatar">${initial}</div>
                                    <div class="sender-info">
                                        <strong>${escapeHtml(msg.sender_name || msg.sender_email)}</strong>
                                        <span>&lt;${escapeHtml(msg.sender_email)}&gt;</span>
                                    </div>
                                </div>
                                <div class="message-detail-date">${formatDateTime(msg.received_at)}</div>
                            </div>
                        </div>
                        <div class="message-detail-body">
                            ${msg.body_html || msg.body_text.replace(/\n/g, '<br>') || '<p>No content</p>'}
                        </div>
                    `;
                    
                    // Mark as read and update UI
                    const messageRow = document.querySelector(`tr[data-message-id="${id}"]`);
                    if (messageRow) {
                        messageRow.classList.remove('unread');
                        const newBadge = messageRow.querySelector('.badge.new');
                        if (newBadge) newBadge.remove();
                    }
                    
                    await updateCounts();
                } else {
                    throw new Error(data.error || 'Message not found');
                }
            } catch (error) {
                modalBody.innerHTML = `<div class="empty-state">
                    <span class="material-icons-round">error_outline</span>
                    <h3>Error loading message</h3>
                    <p>${escapeHtml(error.message)}</p>
                </div>`;
            }
        }

        function closeMessageModal() {
            document.getElementById('messageModal').classList.remove('show');
        }

        // ═══════════════════════════════════════════════════════════
        //  FILTERS
        // ═══════════════════════════════════════════════════════════
        function clearFilters() {
            currentFilters = {
                search: '',
                unread_only: false,
                starred_only: false,
                new_only: false
            };
            setActiveFilter('filterAll');
            setActiveStatCard(0);
            fetchMessages();
        }

        function filterUnread() {
            currentFilters = {
                search: '',
                unread_only: true,
                starred_only: false,
                new_only: false
            };
            setActiveFilter('filterUnread');
            setActiveStatCard(1);
            fetchMessages();
        }

        function filterStarred() {
            currentFilters = {
                search: '',
                unread_only: false,
                starred_only: true,
                new_only: false
            };
            setActiveFilter('filterStarred');
            setActiveStatCard(-1);
            fetchMessages();
        }

        function filterNew() {
            currentFilters = {
                search: '',
                unread_only: false,
                starred_only: false,
                new_only: true
            };
            setActiveFilter('filterNew');
            setActiveStatCard(2);
            fetchMessages();
        }

        function setActiveFilter(id) {
            document.querySelectorAll('.chip').forEach(chip => {
                chip.classList.remove('active');
            });
            document.getElementById(id).classList.add('active');
        }

        function setActiveStatCard(index) {
            document.querySelectorAll('.stat-card').forEach((card, i) => {
                card.classList.toggle('active', i === index);
            });
        }

        // ═══════════════════════════════════════════════════════════
        //  MESSAGE ACTIONS
        // ═══════════════════════════════════════════════════════════
        async function toggleStar(id, btn) {
            try {
                const response = await fetch(`inbox.php?action=toggle_star&id=${id}`);
                const data = await response.json();
                
                if (data.success) {
                    btn.classList.toggle('starred');
                    const icon = btn.querySelector('.material-icons-round');
                    icon.textContent = btn.classList.contains('starred') ? 'star' : 'star_outline';
                }
            } catch (error) {
                showToast('Error toggling star', 'error');
            }
        }

        async function deleteMessage(id) {
            if (!confirm('Delete this message?')) return;
            
            try {
                const response = await fetch(`inbox.php?action=delete&id=${id}`);
                const data = await response.json();
                
                if (data.success) {
                    const messageRow = document.querySelector(`tr[data-message-id="${id}"]`);
                    if (messageRow) {
                        messageRow.style.opacity = '0';
                        messageRow.style.transition = 'opacity 0.3s';
                        setTimeout(() => {
                            messageRow.remove();
                            updateCounts();
                        }, 300);
                    }
                    showToast('Message deleted', 'success');
                }
            } catch (error) {
                showToast('Error deleting message', 'error');
            }
        }

        // ═══════════════════════════════════════════════════════════
        //  UPDATE COUNTS
        // ═══════════════════════════════════════════════════════════
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

        // ═══════════════════════════════════════════════════════════
        //  SEARCH
        // ═══════════════════════════════════════════════════════════
        function setupSearchListener() {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('input', performClientSideSearch);
                searchInput.addEventListener('keyup', (e) => {
                    if (e.key === 'Escape') {
                        searchInput.value = '';
                        performClientSideSearch();
                    }
                });
            }
        }

        function storeOriginalContent() {
            originalMessageContent.clear();
            const messageRows = document.querySelectorAll('tr[data-message-id]');
            messageRows.forEach(row => {
                const id = row.dataset.messageId;
                const senderEl = row.querySelector('.message-sender');
                const subjectEl = row.querySelector('.message-subject');
                const previewEl = row.querySelector('.message-preview');
                
                if (senderEl && subjectEl && previewEl) {
                    originalMessageContent.set(id, {
                        sender: senderEl.innerHTML,
                        subject: subjectEl.innerHTML,
                        preview: previewEl.innerHTML
                    });
                }
            });
        }

        function performClientSideSearch() {
            const searchInput = document.getElementById('searchInput');
            const searchTerm = searchInput ? searchInput.value.trim() : '';
            
            if (!searchTerm) {
                removeAllHighlights();
                document.querySelectorAll('tr[data-message-id]').forEach(row => {
                    row.style.display = '';
                });
                return;
            }
            
            const searchLower = searchTerm.toLowerCase();
            const messageRows = document.querySelectorAll('tr[data-message-id]');
            let visibleCount = 0;
            
            messageRows.forEach(row => {
                const id = row.dataset.messageId;
                const original = originalMessageContent.get(id);
                
                if (!original) return;
                
                const senderText = original.sender.replace(/<[^>]*>/g, '').toLowerCase();
                const subjectText = original.subject.replace(/<[^>]*>/g, '').toLowerCase();
                const previewText = original.preview.replace(/<[^>]*>/g, '').toLowerCase();
                
                const senderMatch = senderText.includes(searchLower);
                const subjectMatch = subjectText.includes(searchLower);
                const previewMatch = previewText.includes(searchLower);
                
                const isMatch = senderMatch || subjectMatch || previewMatch;
                
                if (isMatch) {
                    row.style.display = '';
                    visibleCount++;
                    
                    const senderEl = row.querySelector('.message-sender');
                    const subjectEl = row.querySelector('.message-subject');
                    const previewEl = row.querySelector('.message-preview');
                    
                    if (senderMatch && senderEl) {
                        senderEl.innerHTML = highlightMatches(original.sender.replace(/<[^>]*>/g, ''), searchTerm);
                    } else if (senderEl) {
                        senderEl.innerHTML = original.sender;
                    }
                    
                    if (subjectMatch && subjectEl) {
                        subjectEl.innerHTML = highlightMatches(original.subject.replace(/<[^>]*>/g, ''), searchTerm);
                    } else if (subjectEl) {
                        subjectEl.innerHTML = original.subject;
                    }
                    
                    if (previewMatch && previewEl) {
                        previewEl.innerHTML = highlightMatches(original.preview.replace(/<[^>]*>/g, ''), searchTerm);
                    } else if (previewEl) {
                        previewEl.innerHTML = original.preview;
                    }
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function removeAllHighlights() {
            const messageRows = document.querySelectorAll('tr[data-message-id]');
            messageRows.forEach(row => {
                const id = row.dataset.messageId;
                const original = originalMessageContent.get(id);
                
                if (original) {
                    const senderEl = row.querySelector('.message-sender');
                    const subjectEl = row.querySelector('.message-subject');
                    const previewEl = row.querySelector('.message-preview');
                    
                    if (senderEl) senderEl.innerHTML = original.sender;
                    if (subjectEl) subjectEl.innerHTML = original.subject;
                    if (previewEl) previewEl.innerHTML = original.preview;
                }
            });
        }

        function highlightMatches(text, searchTerm) {
            if (!searchTerm || !text) return text;
            
            const escapedTerm = searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const regex = new RegExp(`(${escapedTerm})`, 'gi');
            return text.replace(regex, '<mark>$1</mark>');
        }

        // ═══════════════════════════════════════════════════════════
        //  TOAST NOTIFICATIONS
        // ═══════════════════════════════════════════════════════════
        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            const icons = {
                success: 'check_circle',
                error: 'error',
                info: 'info'
            };
            
            toast.innerHTML = `
                <span class="material-icons-round">${icons[type] || 'info'}</span>
                <div class="toast-message">${escapeHtml(message)}</div>
            `;
            
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100px)';
                toast.style.transition = 'all 0.3s';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // ═══════════════════════════════════════════════════════════
        //  UTILITIES
        // ═══════════════════════════════════════════════════════════
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatDate(dateStr) {
            const date = new Date(dateStr);
            const now = new Date();
            const diff = now - date;
            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            
            if (days === 0) return 'Today';
            if (days === 1) return 'Yesterday';
            if (days < 7) return `${days}d ago`;
            
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        }

        function formatDateTime(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', {
                month: 'long',
                day: 'numeric',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        }

        // Close modal on backdrop click
        document.getElementById('messageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeMessageModal();
            }
        });

        // ESC key to close modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeMessageModal();
            }
        });

        // Auto-update counts every 30 seconds
        setInterval(updateCounts, 30000);

        // Keyboard shortcut: Ctrl/Cmd + R to sync
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                syncMessages();
            }
        });
    </script>
</body>
</html>