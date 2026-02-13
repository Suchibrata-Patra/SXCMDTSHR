<?php
/**
 * Professional Inbox Page - Refactored with External Sidebar
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
    <?php
        define('PAGE_TITLE', 'SXC MDTS | Dashboard');
        include 'header.php';
    ?>
    <style>
        :root {
            --apple-blue: #007AFF;
            --apple-gray: #8E8E93;
            --apple-light-gray: #C7C7CC;
            --apple-bg: #F2F2F7;
            --glass: rgba(255, 255, 255, 0.7);
            --border: #E5E5EA;
            --success-green: #34C759;
            --warning-orange: #FF9500;
            --danger-red: #FF3B30;
            --card-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            --hover-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--apple-bg);
            color: #1c1c1e;
            display: flex;
            height: 100vh;
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* ========== MAIN CONTENT ========== */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* ========== HEADER ========== */
        .page-header {
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left {
            flex: 1;
        }

        .page-title {
            font-size: 24px;
            font-weight: 700;
            color: #1c1c1e;
            letter-spacing: -0.5px;
            margin-bottom: 2px;
        }

        .page-subtitle {
            font-size: 13px;
            color: var(--apple-gray);
            font-weight: 400;
        }

        .header-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .btn {
            padding: 8px 14px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }

        .btn .material-icons {
            font-size: 18px;
        }

        .btn-primary {
            background: var(--apple-blue);
            color: white;
        }

        .btn-primary:hover {
            background: #0051D5;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 122, 255, 0.3);
        }

        .btn-icon {
            padding: 8px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-icon:hover {
            background: var(--apple-bg);
            border-color: var(--apple-blue);
        }

        .btn-icon .material-icons {
            color: var(--apple-gray);
            transition: transform 0.3s ease;
        }

        .btn-icon.rotating .material-icons {
            animation: rotate 0.6s linear;
        }

        @keyframes rotate {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        /* ========== STATS BAR ========== */
        .stats-bar {
            background: white;
            padding: 12px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            gap: 24px;
            align-items: center;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stat-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .stat-icon.total {
            background: rgb(229,56,81,0.1);
            color: rgb(229,56,81);
        }

        .stat-icon.unread {
            background: rgb(144,236,64, 0.1);
            color: rgb(144,236,64);
        }

        .stat-icon.new {
            background: rgb(20,121,246,0.1);
            color: rgb(20,121,246);
        }

        .stat-content {
            display: flex;
            flex-direction: column;
        }

        .stat-number {
            font-size: 18px;
            font-weight: 700;
            color: #1c1c1e;
            line-height: 1;
        }

        .stat-label {
            font-size: 11px;
            color: var(--apple-gray);
            margin-top: 2px;
        }

        .sync-info {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--apple-gray);
        }

        .sync-info .material-icons {
            font-size: 14px;
        }

        /* ========== CONTENT AREA WITH SPLIT PANE ========== */
        .content-wrapper {
            flex: 1;
            display: flex;
            overflow: hidden;
        }

        .messages-pane {
            width: 40%;
            display: flex;
            flex-direction: column;
            border-right: 1px solid var(--border);
            background: white;
        }

        .message-view-pane {
            width: 60%;
            display: flex;
            flex-direction: column;
            background: #FAFAFA;
        }

        /* ========== TOOLBAR ========== */
        .toolbar {
            background: white;
            padding: 12px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .search-box {
            flex: 1;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 8px 12px 8px 36px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 13px;
            font-family: 'Inter', sans-serif;
            background: var(--apple-bg);
            transition: all 0.2s;
        }

        .search-box input:focus {
            outline: none;
            background: white;
            border-color: var(--apple-blue);
            box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
        }

        .search-box .material-icons {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--apple-gray);
            font-size: 18px;
        }

        .filter-group {
            display: flex;
            gap: 6px;
        }

        .filter-btn {
            padding: 6px 12px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 12px;
            font-weight: 500;
            color: #1c1c1e;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }

        .filter-btn:hover {
            background: var(--apple-bg);
            border-color: var(--apple-blue);
        }

        .filter-btn.active {
            background: var(--apple-blue);
            color: white;
            border-color: var(--apple-blue);
        }

        .filter-btn .material-icons {
            font-size: 14px;
        }

        /* ========== MESSAGES AREA ========== */
        .messages-area {
            flex: 1;
            overflow-y: auto;
        }

        .messages-container {
            background: white;
        }

        .message-item {
            padding: 12px 20px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            gap: 12px;
            align-items: start;
            position: relative;
        }

        .message-item:last-child {
            border-bottom: none;
        }

        .message-item:hover {
            background: #FAFAFA;
        }

        .message-item.selected {
            background: #F0F7FF;
            border-left: 3px solid var(--apple-blue);
        }

        .message-item.unread .message-sender {
            font-weight: 700;
        }
        
        .message-item.unread .message-subject {
            font-weight: 600;
        }

        .message-item.unread:hover {
            background: linear-gradient(90deg, #f9fcff 0%, #FAFAFA 100%);
        }

        .message-star {
            cursor: pointer;
            color: var(--apple-light-gray);
            font-size: 18px;
            margin-top: 2px;
            transition: all 0.2s;
        }

        .message-star:hover {
            color: var(--warning-orange);
            transform: scale(1.1);
        }

        .message-star.starred {
            color: var(--warning-orange);
        }

        .message-important {
            color: var(--warning-orange);
            font-size: 18px;
            margin-top: 2px;
        }

        .sender-email-hint {
            font-size: 11px;
            color: var(--apple-gray);
            font-weight: 400;
            margin-left: 4px;
        }

        .message-content {
            flex: 1;
            min-width: 0;
        }

        .message-header {
            display: flex;
            align-items: baseline;
            margin-bottom: 3px;
            gap: 12px;
        }

        .message-sender {
            font-weight: 500;
            color: #1c1c1e;
            font-size: 13px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .message-sender strong,
        .message-subject strong {
            font-weight: 600;
            color: var(--apple-gray);
            margin-right: 4px;
        }

        .message-date {
            font-size: 11px;
            color: var(--apple-gray);
            white-space: nowrap;
            font-weight: 500;
            flex-shrink: 0;
        }

        .message-subject-line {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 3px;
        }

        .message-subject {
            font-size: 13px;
            color: #1c1c1e;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex: 1;
        }

        .inline-attachment-icon {
            font-size: 14px !important;
            color: var(--apple-gray);
            flex-shrink: 0;
        }

        .message-preview {
            font-size: 12px;
            color: var(--apple-gray);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.5;
            margin-bottom: 4px;
            font-style: italic;
        }
            line-height: 1.5;
            margin-bottom: 4px;
        }

        .message-id-badge {
            font-size: 5px;
            color: var(--apple-gray);
            background: rgb(239, 239, 239);
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 500;
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
            font-size: 10px;
            font-weight: 600;
        }

        .badge-new {
            background: rgba(52, 199, 89, 0.15);
            color: var(--success-green);
        }

        .badge-new-inline {
            display: inline-block;
            background: rgba(52, 199, 89, 0.15);
            color: var(--success-green);
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 700;
            margin-top: 2px;
        }

        .badge-attachment {
            background: rgb(227, 227, 227);
            color: rgb(33, 33, 33);
            padding: 5px 7px;
            border-radius: 20px;
        }

        .badge-attachment .material-icons {
            font-size: 11px;
        }

        /* ========== MESSAGE VIEW PANE ========== */
        .message-view-header {
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 16px 20px;
        }

        .message-view-title {
            font-size: 18px;
            font-weight: 600;
            color: #1c1c1e;
            margin-bottom: 8px;
            line-height: 1.4;
        }

        .message-view-meta {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .message-view-meta-item {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 12px;
            color: var(--apple-gray);
        }

        .message-view-meta-item .material-icons {
            font-size: 14px;
        }

        .message-view-meta-label {
            font-weight: 600;
            color: #1c1c1e;
        }

        .message-view-actions {
            display: flex;
            gap: 6px;
        }

        .message-view-body {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
        }

        .message-meta {
            display: flex;
            flex-direction: column;
            gap: 4px;
            align-items: center;
            padding-top: 2px;
        }

        .message-right-meta {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            padding-top: 2px;
            flex-shrink: 0;
            min-width: 80px;
        }

        /* When there's no unread dot, move ID up to take its place */
        .message-right-meta:not(.has-unread-dot) {
            gap: 8px;
        }

        .message-right-meta:not(.has-unread-dot) .message-id-badge {
            margin-top: 2px;
        }

        .message-right-meta .message-date {
            font-size: 11px;
            color: var(--apple-gray);
            white-space: nowrap;
            font-weight: 500;
        }

        .unread-dot {
            width: 10px;
            height: 10px;
            background-color: #1a73e8;
            /* nice Google-ish blue */
            border-radius: 50%;
        }

        .message-detail {
            background: white;
            border-radius: 10px;
            padding: 20px;
            font-size: 14px;
            line-height: 1.7;
            color: #1c1c1e;
            box-shadow: var(--card-shadow);
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .message-detail p {
            margin-bottom: 12px;
        }

        .message-detail a {
            color: var(--apple-blue);
            text-decoration: none;
        }

        .message-detail a:hover {
            text-decoration: underline;
        }

        /* Attachments */
        .attachments-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        .attachments-title {
            font-size: 13px;
            font-weight: 600;
            color: #1c1c1e;
            margin-bottom: 10px;
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
            background: white;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            transition: all 0.2s;
            cursor: pointer;
        }

        .attachment-card:hover {
            border-color: var(--apple-blue);
            box-shadow: var(--card-shadow);
            transform: translateY(-2px);
        }

        .attachment-icon {
            font-size: 36px;
            margin-bottom: 6px;
        }

        .attachment-name {
            font-size: 11px;
            font-weight: 500;
            color: #1c1c1e;
            margin-bottom: 3px;
            word-break: break-word;
        }

        .attachment-size {
            font-size: 10px;
            color: var(--apple-gray);
        }

        /* ========== EMPTY STATE ========== */
        .empty-state {
            padding: 60px 20px;
            text-align: center;
        }

        .empty-icon {
            font-size: 64px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-title {
            font-size: 18px;
            font-weight: 600;
            color: #1c1c1e;
            margin-bottom: 6px;
        }

        .empty-text {
            font-size: 14px;
            color: var(--apple-gray);
            max-width: 320px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* ========== LOADING ========== */
        .loading {
            padding: 40px;
            text-align: center;
        }

        .loading-spinner {
            display: inline-block;
            width: 32px;
            height: 32px;
            border: 2px solid var(--border);
            border-top-color: var(--apple-blue);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-bottom: 12px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .loading-text {
            font-size: 13px;
            color: var(--apple-gray);
        }

        /* ========== TOAST NOTIFICATIONS ========== */
        .toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #1c1c1e;
            color: white;
            padding: 12px 18px;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 500;
            z-index: 2000;
            animation: toastSlideIn 0.3s ease-out;
            max-width: 360px;
        }

        @keyframes toastSlideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .toast.success {
            background: var(--success-green);
        }

        .toast.error {
            background: var(--danger-red);
        }

        .toast .material-icons {
            font-size: 18px;
        }
    </style>
</head>

<body>
    <!-- ========== SIDEBAR (EXTERNAL) ========== -->
    <?php require 'sidebar.php'; ?>

    <!-- ========== MAIN CONTENT ========== -->
    <div class="main-content">
        <!-- Header -->
        <!-- <div class="page-header">
            <div class="header-left">
                <h1 class="page-title">📬 Inbox</h1>
                <p class="page-subtitle">View and manage your incoming emails</p>
            </div>
            <div class="header-actions">
                <button class="btn-icon" onclick="syncMessages()" title="Sync now" id="syncBtn">
                    <span class="material-icons">sync</span>
                </button>
                <button class="btn-icon" onclick="forceRefresh()" title="Force refresh" id="refreshBtn">
                    <span class="material-icons">refresh</span>
                </button>
                <button class="btn-icon" onclick="location.href='index.php'">
                    <span class="material-icons">edit</span>
                </button>
            </div>
        </div> -->

        <!-- Stats bar -->
        <div class="stats-bar">
            <div class="stat-item">
                <div class="stat-icon total">
                    <span class="material-icons">mail</span>
                </div>
                <div class="stat-content">
                    <div class="stat-number" id="totalCount">
                        <?= $totalCount ?>
                    </div>
                    <div class="stat-label">Total Received</div>
                </div>
            </div>

            <div class="stat-item">
                <div class="stat-icon unread">
                    <span class="material-icons">mark_email_unread</span>
                </div>
                <div class="stat-content">
                    <div class="stat-number" id="unreadCount">
                        <?= $unreadCount ?>
                    </div>
                    <div class="stat-label">Unread</div>
                </div>
            </div>

            <div class="stat-item">
                <div class="stat-icon new">
                    <span class="material-icons">fiber_new</span>
                </div>
                <div class="stat-content">
                    <div class="stat-number" id="newCount">
                        <?= $newCount ?>
                    </div>
                    <div class="stat-label">New Today</div>
                </div>
            </div>

            
            <div class="sync-info">
            <div class="header-actions">
                <button class="btn-icon" onclick="syncMessages()" title="Sync now" id="syncBtn">
                    <span class="material-icons">sync</span>
                </button>
                <button class="btn-icon" onclick="forceRefresh()" title="Force refresh" id="refreshBtn">
                    <span class="material-icons">refresh</span>
                </button>
                <button class="btn-icon" onclick="location.href='index.php'">
                    <span class="material-icons">edit</span>
                </button>
            </div>
                <span class="material-icons">schedule</span>
                <span id="lastSyncText">
                    <?php if ($lastSync): ?>
                    Last synced:
                    <?= date('g:i A', strtotime($lastSync)) ?>
                    <?php else: ?>
                    Never synced
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <!-- Content wrapper with split panes -->
        <div class="content-wrapper">
            <!-- Left pane: Messages list -->
            <div class="messages-pane">
                <!-- Toolbar -->
                <div class="toolbar">
                    <div class="search-box">
                        <span class="material-icons">search</span>
                        <input type="text" id="searchInput" placeholder="Search..." onkeyup="searchMessages()">
                    </div>

                    <div class="filter-group">
                        <button class="filter-btn" id="filterUnread" onclick="toggleFilter('unread')">
                            <span class="material-icons">mark_email_unread</span>
                            Unread
                        </button>
                        <button class="filter-btn" id="filterStarred" onclick="toggleFilter('starred')">
                            <span class="material-icons">star</span>
                            Starred
                        </button>
                        <button class="filter-btn" id="filterNew" onclick="toggleFilter('new')">
                            <span class="material-icons">fiber_new</span>
                            New
                        </button>
                    </div>
                </div>

                <!-- Messages list -->
                <div class="messages-area">
                    <div class="messages-container" id="messagesList">
                        <div class="loading">
                            <div class="loading-spinner"></div>
                            <div class="loading-text">Loading messages...</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right pane: Message view -->
            <div class="message-view-pane">
                <div id="messageViewContent">
                    <div class="empty-state">
                        <div class="empty-icon">✉️</div>
                        <div class="empty-title">No message selected</div>
                        <div class="empty-text">Click on a message to view its contents</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentFilters = { unread_only: false, starred_only: false, new_only: false };
        let currentSearchQuery = '';
        let currentMessageId = null;
        let selectedMessageElement = null;

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', () => {
            fetchMessages();
        });

        async function fetchMessages() {
            try {
                const params = new URLSearchParams({
                    action: 'fetch_messages',
                    limit: 50,
                    offset: 0
                });

                if (currentSearchQuery) params.append('search', currentSearchQuery);
                if (currentFilters.unread_only) params.append('unread_only', '1');
                if (currentFilters.starred_only) params.append('starred_only', '1');
                if (currentFilters.new_only) params.append('new_only', '1');

                const response = await fetch(`inbox.php?${params.toString()}`);
                const data = await response.json();

                if (data.success) {
                    renderMessages(data.messages);
                    document.getElementById('totalCount').textContent = data.total;
                }
            } catch (error) {
                console.error('Error fetching messages:', error);
                document.getElementById('messagesList').innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">⚠️</div>
                        <div class="empty-title">Error loading messages</div>
                        <div class="empty-text">Please try refreshing the page</div>
                    </div>
                `;
            }
        }

        function renderMessages(messages) {
            const container = document.getElementById('messagesList');

            if (!messages || messages.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">📭</div>
                        <div class="empty-title">No messages found</div>
                        <div class="empty-text">Your inbox is empty or no messages match your filters</div>
                    </div>
                `;
                return;
            }

            container.innerHTML = messages.map(msg => {
                const classes = ['message-item'];
                if (msg.is_read == 0) classes.push('unread');
                if (msg.id == currentMessageId) classes.push('selected');

                // Use body_preview if available, otherwise strip HTML from body
                let preview = '';
                if (msg.body_preview && msg.body_preview.trim()) {
                    preview = msg.body_preview.substring(0, 150);
                } else if (msg.body) {
                    // Strip HTML tags and get plain text
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = msg.body;
                    const plainText = tempDiv.textContent || tempDiv.innerText || '';
                    preview = plainText.substring(0, 150);
                } else {
                    preview = 'No preview available';
                }
                
                // Get sender email and name
                const senderEmail = msg.sender_email || 'unknown@email.com';
                const senderName = msg.sender_name || senderEmail;
                
                // Get subject or use default
                const subjectDisplay = msg.subject || '(No Subject)';

                return `
                    <div class="${classes.join(' ')}" onclick="viewMessage(${msg.id}, event)" data-message-id="${msg.id}">
                        <span class="message-meta">
                            <span class="material-icons message-star ${msg.is_starred == 1 ? 'starred' : ''}"
                                  onclick="toggleStar(${msg.id}, event)">
                                ${msg.is_starred == 1 ? 'star' : 'star_border'}
                            </span>
                            ${msg.is_important == 1 ? '<span class="material-icons message-important" title="Important">label_important</span>' : ''}
                        </span>

                        <div class="message-content">
                            <div class="message-header">
                                <span class="message-sender" title="${escapeHtml(senderEmail)}">
                                     ${escapeHtml(senderName)} ${senderName !== senderEmail ? '<span class="sender-email-hint">&lt;' + escapeHtml(senderEmail) + '&gt;</span>' : ''}
                                </span>
                            </div>
                            <div class="message-subject-line">
                                <span class="message-subject" title="${escapeHtml(subjectDisplay)}">
                                    ${escapeHtml(subjectDisplay)}
                                </span>
                                ${msg.has_attachments == 1 ? '<span class="inline-attachment-icon material-icons" title="Has attachments">attach_file</span>' : ''}
                            </div>
                            <div class="message-preview" title="${escapeHtml(preview)}">
                                ${escapeHtml(preview)}${preview.length >= 150 ? '...' : ''}
                            </div>
                        </div>
                        
                        <div class="message-right-meta ${msg.is_read == 0 ? 'has-unread-dot' : ''}">
                            <span class="message-date" title="${formatDateLong(msg.received_date)}">
                                ${formatDate(msg.received_date)}
                            </span>
                            ${msg.is_read == 0 ? '<span class="unread-dot"></span>' : ''}
                            <span class="message-id-badge" title="Message ID: ${msg.id}">ID: ${msg.id}</span>
                        </div>
                    </div>
                `;
            }).join('');
        }

        async function viewMessage(messageId, event) {
            if (event) {
                // Remove selected class from previous element
                if (selectedMessageElement) {
                    selectedMessageElement.classList.remove('selected');
                }

                // Add selected class to clicked element
                selectedMessageElement = event.currentTarget;
                selectedMessageElement.classList.add('selected');
            }

            currentMessageId = messageId;

            try {
                const response = await fetch(`inbox.php?action=get_message&id=${messageId}`);
                const data = await response.json();

                if (data.success) {
                    const msg = data.message;

                    // Parse attachments
                    let attachments = [];
                    if (msg.has_attachments && msg.attachment_data) {
                        try {
                            attachments = JSON.parse(msg.attachment_data);
                        } catch (e) { }
                    }

                    // Build body HTML
                    let bodyHtml = `<div class="message-detail">${escapeHtml(msg.body)}</div>`;

                    // Add attachments section
                    if (attachments.length > 0) {
                        bodyHtml += `
                            <div class="attachments-section">
                                <div class="attachments-title">
                                    <span class="material-icons">attach_file</span>
                                    ${attachments.length} Attachment${attachments.length > 1 ? 's' : ''}
                                </div>
                                <div class="attachments-grid">
                                    ${attachments.map(att => `
                                        <div class="attachment-card" onclick="downloadAttachment(${msg.id}, '${escapeHtml(att.filename)}')">
                                            <div class="attachment-icon">${att.icon || '📎'}</div>
                                            <div class="attachment-name">${escapeHtml(att.filename)}</div>
                                            <div class="attachment-size">${formatBytes(att.size)}</div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        `;
                    }

                    // Render message view
                    document.getElementById('messageViewContent').innerHTML = `
                        <div class="message-view-header">
                            <div class="message-view-title">${escapeHtml(msg.subject)}</div>
                            <div class="message-view-meta">
                                <div class="message-view-meta-item">
                                    <span class="material-icons">person</span>
                                    <span class="message-view-meta-label">From:</span> ${escapeHtml(msg.sender)}
                                </div>
                                <div class="message-view-meta-item">
                                    <span class="material-icons">schedule</span>
                                    <span class="message-view-meta-label">Date:</span> ${formatDateLong(msg.received_date)}
                                </div>
                            </div>
                            <div class="message-view-actions">
                                <button class="filter-btn" onclick="toggleStarFromView()">
                                    <span class="material-icons">${msg.is_starred == 1 ? 'star' : 'star_border'}</span>
                                    ${msg.is_starred == 1 ? 'Starred' : 'Star'}
                                </button>
                                <button class="filter-btn" onclick="deleteMessageFromView()">
                                    <span class="material-icons">delete</span>
                                    Delete
                                </button>
                            </div>
                        </div>
                        <div class="message-view-body">
                            ${bodyHtml}
                        </div>
                    `;

                    // Refresh list to update read status
                    setTimeout(() => fetchMessages(), 100);
                    updateCounts();
                }
            } catch (error) {
                console.error('Error viewing message:', error);
                showToast('Failed to load message', 'error');
            }
        }

        async function syncMessages() {
            const btn = document.getElementById('syncBtn');
            btn.classList.add('rotating');

            try {
                const response = await fetch('inbox.php?action=sync&limit=50&force=false');
                const data = await response.json();

                if (data.success) {
                    showToast(`Synced ${data.new_count} new messages`, 'success');
                    fetchMessages();
                    updateCounts();
                    updateLastSyncTime();
                } else {
                    showToast(data.error || 'Sync failed', 'error');
                }
            } catch (error) {
                console.error('Sync error:', error);
                showToast('Sync failed', 'error');
            } finally {
                setTimeout(() => btn.classList.remove('rotating'), 600);
            }
        }

        async function forceRefresh() {
            const btn = document.getElementById('refreshBtn');
            btn.classList.add('rotating');

            try {
                const response = await fetch('inbox.php?action=sync&limit=50&force=true');
                const data = await response.json();

                if (data.success) {
                    showToast(`Refreshed: ${data.new_count} new messages`, 'success');
                    fetchMessages();
                    updateCounts();
                    updateLastSyncTime();
                } else {
                    showToast(data.error || 'Refresh failed', 'error');
                }
            } catch (error) {
                console.error('Refresh error:', error);
                showToast('Refresh failed', 'error');
            } finally {
                setTimeout(() => btn.classList.remove('rotating'), 600);
            }
        }

        function toggleFilter(filterType) {
            const filterBtn = document.getElementById('filter' + filterType.charAt(0).toUpperCase() + filterType.slice(1));

            if (filterType === 'unread') {
                currentFilters.unread_only = !currentFilters.unread_only;
                filterBtn.classList.toggle('active');
            } else if (filterType === 'starred') {
                currentFilters.starred_only = !currentFilters.starred_only;
                filterBtn.classList.toggle('active');
            } else if (filterType === 'new') {
                currentFilters.new_only = !currentFilters.new_only;
                filterBtn.classList.toggle('active');
            }

            fetchMessages();
        }

        function searchMessages() {
            currentSearchQuery = document.getElementById('searchInput').value;
            fetchMessages();
        }

        async function toggleStar(messageId, event) {
            event.stopPropagation();

            try {
                const response = await fetch(`inbox.php?action=toggle_star&id=${messageId}`);
                const data = await response.json();

                if (data.success) {
                    fetchMessages();
                    if (currentMessageId === messageId) {
                        viewMessage(messageId);
                    }
                }
            } catch (error) {
                console.error('Error toggling star:', error);
            }
        }

        async function toggleStarFromView() {
            if (!currentMessageId) return;
            await toggleStar(currentMessageId, { stopPropagation: () => { } });
        }

        async function deleteMessageFromView() {
            if (!currentMessageId) return;

            if (!confirm('Are you sure you want to delete this message?')) {
                return;
            }

            try {
                const response = await fetch(`inbox.php?action=delete&id=${currentMessageId}`);
                const data = await response.json();

                if (data.success) {
                    showToast('Message deleted', 'success');
                    currentMessageId = null;
                    document.getElementById('messageViewContent').innerHTML = `
                        <div class="empty-state">
                            <div class="empty-icon">✉️</div>
                            <div class="empty-title">No message selected</div>
                            <div class="empty-text">Click on a message to view its contents</div>
                        </div>
                    `;
                    fetchMessages();
                    updateCounts();
                } else {
                    showToast('Failed to delete message', 'error');
                }
            } catch (error) {
                console.error('Error deleting message:', error);
                showToast('Failed to delete message', 'error');
            }
        }

        function downloadAttachment(messageId, filename) {
            window.location.href = `download_attachment.php?message_id=${messageId}&filename=${encodeURIComponent(filename)}`;
        }

        async function updateCounts() {
            try {
                const response = await fetch('inbox.php?action=get_counts');
                const data = await response.json();

                if (data.success) {
                    document.getElementById('unreadCount').textContent = data.unread;
                    document.getElementById('newCount').textContent = data.new;

                    const sidebarBadge = document.getElementById('sidebarUnreadBadge');
                    if (sidebarBadge) {
                        sidebarBadge.textContent = data.unread;
                        sidebarBadge.style.display = data.unread > 0 ? 'block' : 'none';
                    }
                }
            } catch (error) {
                console.error('Error updating counts:', error);
            }
        }

        function updateLastSyncTime() {
            const now = new Date();
            const timeStr = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
            document.getElementById('lastSyncText').textContent = 'Last synced: ' + timeStr;
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

            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        }

        function formatDateLong(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        }

        function formatBytes(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function showToast(message, type = 'info') {
            document.querySelectorAll('.toast').forEach(t => t.remove());

            const toast = document.createElement('div');
            toast.className = `toast ${type}`;

            const icon = type === 'success' ? 'check_circle' :
                type === 'error' ? 'error' :
                    'info';

            toast.innerHTML = `
                <span class="material-icons">${icon}</span>
                <span>${message}</span>
            `;

            document.body.appendChild(toast);

            setTimeout(() => {
                toast.style.animation = 'toastSlideIn 0.3s ease-out reverse';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Auto-update counts every 30 seconds
        setInterval(updateCounts, 30000);

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + R to sync
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                syncMessages();
            }
        });

        // ========== CLIENT-SIDE SEARCH WITH YELLOW HIGHLIGHTING ==========
        
        // Store original content for each message item
        const originalMessageContent = new Map();
        
        // Initialize original content storage
        function storeOriginalContent() {
            const messageItems = document.querySelectorAll('.message-item');
            messageItems.forEach(item => {
                const id = item.dataset.messageId;
                const senderEl = item.querySelector('.message-sender');
                const subjectEl = item.querySelector('.message-subject');
                const previewEl = item.querySelector('.message-preview');
                
                if (senderEl && subjectEl && previewEl) {
                    originalMessageContent.set(id, {
                        sender: senderEl.innerHTML,
                        subject: subjectEl.innerHTML,
                        preview: previewEl.innerHTML
                    });
                }
            });
        }
        
        // Escape special regex characters
        function escapeRegExpSearch(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }
        
        // Highlight matching text with yellow background
        function highlightMatches(text, searchTerm) {
            if (!searchTerm || !text) return text;
            
            const escapedTerm = escapeRegExpSearch(searchTerm);
            const regex = new RegExp(`(${escapedTerm})`, 'gi');
            return text.replace(regex, '<mark style="background-color: #f4cccc; color: #000; padding: 2px 3px; border-radius: 2px; font-weight: 600;">$1</mark>');
        }
        
        // Remove all highlights and restore original content
        function removeAllHighlights() {
            const messageItems = document.querySelectorAll('.message-item');
            messageItems.forEach(item => {
                const id = item.dataset.messageId;
                const original = originalMessageContent.get(id);
                
                if (original) {
                    const senderEl = item.querySelector('.message-sender');
                    const subjectEl = item.querySelector('.message-subject');
                    const previewEl = item.querySelector('.message-preview');
                    
                    if (senderEl) senderEl.innerHTML = original.sender;
                    if (subjectEl) subjectEl.innerHTML = original.subject;
                    if (previewEl) previewEl.innerHTML = original.preview;
                }
            });
        }
        
        // Perform client-side search and highlight
        function performClientSideSearch() {
            const searchInput = document.getElementById('searchInput');
            const searchTerm = searchInput ? searchInput.value.trim() : '';
            
            // If search is empty, restore original content
            if (!searchTerm) {
                removeAllHighlights();
                // Show all messages
                const messageItems = document.querySelectorAll('.message-item');
                messageItems.forEach(item => {
                    item.style.display = 'flex';
                });
                return;
            }
            
            const searchLower = searchTerm.toLowerCase();
            const messageItems = document.querySelectorAll('.message-item');
            let visibleCount = 0;
            
            messageItems.forEach(item => {
                const id = item.dataset.messageId;
                const original = originalMessageContent.get(id);
                
                if (!original) return;
                
                // Get text content for matching
                const senderText = original.sender.replace(/<[^>]*>/g, '').toLowerCase();
                const subjectText = original.subject.replace(/<[^>]*>/g, '').toLowerCase();
                const previewText = original.preview.replace(/<[^>]*>/g, '').toLowerCase();
                
                // Check if search term matches
                const senderMatch = senderText.includes(searchLower);
                const subjectMatch = subjectText.includes(searchLower);
                const previewMatch = previewText.includes(searchLower);
                
                const isMatch = senderMatch || subjectMatch || previewMatch;
                
                if (isMatch) {
                    item.style.display = 'flex';
                    visibleCount++;
                    
                    // Apply highlighting
                    const senderEl = item.querySelector('.message-sender');
                    const subjectEl = item.querySelector('.message-subject');
                    const previewEl = item.querySelector('.message-preview');
                    
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
                    item.style.display = 'none';
                }
            });
            
            // Show "no results" message if needed
            const messageList = document.getElementById('messageList');
            if (visibleCount === 0 && messageList) {
                // Check if no-results message already exists
                let noResultsDiv = document.getElementById('searchNoResults');
                if (!noResultsDiv) {
                    noResultsDiv = document.createElement('div');
                    noResultsDiv.id = 'searchNoResults';
                    noResultsDiv.style.cssText = 'text-align: center; padding: 60px 20px; color: #8E8E93;';
                    noResultsDiv.innerHTML = `
                        <div style="font-size: 48px; margin-bottom: 16px;">🔍</div>
                        <div style="font-size: 18px; font-weight: 600; color: #1c1c1e; margin-bottom: 8px;">No results found</div>
                        <div style="font-size: 14px;">Try adjusting your search terms</div>
                    `;
                    messageList.appendChild(noResultsDiv);
                }
                noResultsDiv.style.display = 'block';
            } else {
                const noResultsDiv = document.getElementById('searchNoResults');
                if (noResultsDiv) {
                    noResultsDiv.style.display = 'none';
                }
            }
        }
        
        // Override the existing searchMessages function to use client-side search
        const originalSearchMessages = searchMessages;
        searchMessages = function() {
            // Store original content if not already stored
            if (originalMessageContent.size === 0) {
                storeOriginalContent();
            }
            
            // Perform client-side search with highlighting
            performClientSideSearch();
        };
        
        // Store original content after messages are loaded
        const originalFetchMessages = fetchMessages;
        fetchMessages = async function() {
            await originalFetchMessages();
            
            // Wait a bit for DOM to update, then store original content
            setTimeout(() => {
                storeOriginalContent();
            }, 100);
        };
        
        // Attach search event listener
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                // Store original content on page load
                setTimeout(() => {
                    storeOriginalContent();
                }, 500);
                
                // Real-time search as user types
                searchInput.addEventListener('input', performClientSideSearch);
                
                // Clear search on ESC key
                searchInput.addEventListener('keyup', (e) => {
                    if (e.key === 'Escape') {
                        searchInput.value = '';
                        performClientSideSearch();
                    }
                });
            }
        });
    </script>
</body>

</html>