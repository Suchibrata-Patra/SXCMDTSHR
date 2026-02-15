<?php
/**
 * Professional Inbox Page - Redesigned with Drive UI Aesthetic
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
        /* ══════════════════════════════════════════════════════════
           DRIVE UI DESIGN SYSTEM - INBOX IMPLEMENTATION
           ══════════════════════════════════════════════════════════ */
        
        :root {
            /* Foundation Colors */
            --ink:       #1a1a2e;
            --ink-2:     #2d2d44;
            --ink-3:     #6b6b8a;
            --ink-4:     #a8a8c0;
            --bg:        #f0f0f7;
            --surface:   #ffffff;
            --surface-2: #f7f7fc;
            --border:    rgba(100,100,160,0.12);
            --border-2:  rgba(100,100,160,0.22);
            
            /* Accent Colors */
            --blue:      #5781a9;
            --blue-2:    #c6d3ea;
            --blue-glow: rgba(79,70,229,0.15);
            --red:       #ef4444;
            --green:     #10b981;
            --amber:     #f59e0b;
            --purple:    #8b5cf6;
            
            /* System */
            --r:         10px;
            --r-lg:      16px;
            --shadow:    0 1px 3px rgba(79,70,229,0.08), 0 4px 16px rgba(79,70,229,0.06);
            --shadow-lg: 0 8px 32px rgba(79,70,229,0.14), 0 2px 8px rgba(0,0,0,0.06);
            --ease:      cubic-bezier(.4,0,.2,1);
            --ease-spring: cubic-bezier(.34,1.56,.64,1);
        }

        *,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', -apple-system, sans-serif;
            background: var(--bg);
            color: var(--ink);
            -webkit-font-smoothing: antialiased;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* ══════════════════════════════════════════════════════════
           MAIN LAYOUT
           ══════════════════════════════════════════════════════════ */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* ══════════════════════════════════════════════════════════
           TOP BAR
           ══════════════════════════════════════════════════════════ */
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

        .topbar-title .material-icons-round {
            font-size: 20px;
            color: var(--blue);
        }

        .topbar-spacer { flex: 1; }

        /* Action buttons */
        .topbar-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .btn-icon {
            width: 36px;
            height: 36px;
            border: 1.5px solid var(--border-2);
            border-radius: 8px;
            background: var(--surface-2);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--ink-3);
            transition: all .18s;
        }

        .btn-icon:hover {
            background: var(--blue-glow);
            border-color: var(--blue);
            color: var(--blue);
        }

        .btn-icon .material-icons-round {
            font-size: 18px;
        }

        .btn-icon.rotating .material-icons-round {
            animation: spin .8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .btn-compose {
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
            transition: all .18s;
        }

        .btn-compose:hover {
            background: var(--blue-2);
            box-shadow: 0 4px 12px var(--blue-glow);
        }

        .btn-compose:active {
            transform: scale(.96);
        }

        .btn-compose .material-icons-round {
            font-size: 16px;
        }

        /* ══════════════════════════════════════════════════════════
           STATS BAR
           ══════════════════════════════════════════════════════════ */
        .stats-bar {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 14px 24px;
            display: flex;
            gap: 32px;
            align-items: center;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--r);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .stat-icon.total {
            background: rgba(87,129,169,0.1);
            color: var(--blue);
        }

        .stat-icon.unread {
            background: rgba(16,185,129,0.1);
            color: var(--green);
        }

        .stat-icon.new-stat {
            background: rgba(139,92,246,0.1);
            color: var(--purple);
        }

        .stat-icon .material-icons-round {
            font-size: 20px;
        }

        .stat-content {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .stat-number {
            font-size: 20px;
            font-weight: 700;
            color: var(--ink);
            line-height: 1;
            font-family: 'DM Mono', monospace;
        }

        .stat-label {
            font-size: 11px;
            color: var(--ink-3);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .sync-info {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sync-text {
            font-size: 12px;
            color: var(--ink-3);
            font-family: 'DM Mono', monospace;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .sync-text .material-icons-round {
            font-size: 14px;
        }

        /* ══════════════════════════════════════════════════════════
           SPLIT PANE LAYOUT
           ══════════════════════════════════════════════════════════ */
        .content-wrapper {
            flex: 1;
            display: flex;
            overflow: hidden;
            gap: 0;
        }

        .messages-pane {
            width: 400px;
            display: flex;
            flex-direction: column;
            border-right: 1px solid var(--border);
            background: var(--surface);
        }

        .message-view-pane {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--bg);
        }

        /* ══════════════════════════════════════════════════════════
           TOOLBAR (Search & Filters)
           ══════════════════════════════════════════════════════════ */
        .toolbar {
            background: var(--surface);
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        /* Search box */
        .search-wrap {
            position: relative;
        }

        .search-wrap .material-icons-round {
            position: absolute;
            left: 10px;
            top: 50%;
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

        #searchInput:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px var(--blue-glow);
            background: var(--surface);
        }

        #searchInput::placeholder {
            color: var(--ink-4);
        }

        /* Filter chips */
        .filter-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .filter-chip {
            height: 30px;
            padding: 0 12px;
            border: 1.5px solid var(--border-2);
            border-radius: 20px;
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

        .filter-chip:hover {
            border-color: var(--blue);
            color: var(--blue);
            background: var(--blue-glow);
        }

        .filter-chip.active {
            border-color: var(--blue);
            color: var(--blue);
            background: rgba(79,70,229,.1);
        }

        .filter-chip .material-icons-round {
            font-size: 14px;
        }

        /* ══════════════════════════════════════════════════════════
           MESSAGES LIST
           ══════════════════════════════════════════════════════════ */
        .messages-area {
            flex: 1;
            overflow-y: auto;
            background: var(--surface);
        }

        .messages-area::-webkit-scrollbar {
            width: 5px;
        }

        .messages-area::-webkit-scrollbar-track {
            background: transparent;
        }

        .messages-area::-webkit-scrollbar-thumb {
            background: var(--border-2);
            border-radius: 10px;
        }

        .message-item {
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: all .14s;
            display: flex;
            gap: 12px;
            align-items: start;
            animation: rowFadeIn .2s var(--ease) both;
        }

        @keyframes rowFadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: none; }
        }

        .message-item:hover {
            background: var(--surface-2);
        }

        .message-item.selected {
            background: rgba(79,70,229,.05);
            border-left: 3px solid var(--blue);
            padding-left: 17px;
        }

        .message-item.unread .message-sender {
            font-weight: 700;
            color: var(--ink);
        }

        .message-item.unread .message-subject {
            font-weight: 600;
        }

        /* Star icon */
        .message-star {
            cursor: pointer;
            color: var(--ink-4);
            font-size: 18px;
            transition: all .18s;
            flex-shrink: 0;
        }

        .message-star:hover {
            color: var(--amber);
            transform: scale(1.1);
        }

        .message-star.starred {
            color: var(--amber);
        }

        /* Message content */
        .message-content {
            flex: 1;
            min-width: 0;
        }

        .message-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 4px;
            gap: 8px;
        }

        .message-sender {
            font-weight: 500;
            color: var(--ink-2);
            font-size: 13px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            flex: 1;
        }

        .sender-email-hint {
            font-size: 11px;
            color: var(--ink-4);
            font-weight: 400;
        }

        .message-date {
            font-size: 11px;
            color: var(--ink-3);
            white-space: nowrap;
            font-family: 'DM Mono', monospace;
            flex-shrink: 0;
        }

        .message-subject-line {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 4px;
        }

        .message-subject {
            font-size: 13px;
            color: var(--ink);
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex: 1;
        }

        .inline-attachment-icon {
            font-size: 14px !important;
            color: var(--ink-3);
            flex-shrink: 0;
        }

        .message-preview {
            font-size: 12px;
            color: var(--ink-3);
            line-height: 1.5;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        /* Unread dot */
        .message-right-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 8px;
            flex-shrink: 0;
        }

        .unread-dot {
            width: 8px;
            height: 8px;
            background: var(--blue);
            border-radius: 50%;
        }

        .message-id-badge {
            font-size: 10px;
            color: var(--ink-4);
            font-family: 'DM Mono', monospace;
            background: var(--surface-2);
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 500;
        }

        /* Badges */
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
            padding: 2px 7px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .badge-new {
            background: rgba(16,185,129,0.15);
            color: var(--green);
        }

        .badge-attachment {
            background: var(--surface-2);
            color: var(--ink-3);
            border: 1px solid var(--border-2);
        }

        .badge-attachment .material-icons-round {
            font-size: 11px;
        }

        /* ══════════════════════════════════════════════════════════
           MESSAGE VIEW PANE
           ══════════════════════════════════════════════════════════ */
        .message-view-header {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 20px 24px;
        }

        .message-view-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 12px;
            line-height: 1.4;
        }

        .message-view-meta {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .message-view-meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
        }

        .message-view-meta-item .material-icons-round {
            font-size: 16px;
            color: var(--ink-3);
        }

        .message-view-meta-label {
            font-weight: 600;
            color: var(--ink-3);
        }

        .message-view-meta-value {
            color: var(--ink);
        }

        .message-view-actions {
            display: flex;
            gap: 6px;
        }

        .msg-action-btn {
            height: 32px;
            padding: 0 12px;
            border: 1.5px solid var(--border-2);
            border-radius: 8px;
            background: var(--surface-2);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            color: var(--ink-2);
            font-family: inherit;
            transition: all .18s;
        }

        .msg-action-btn:hover {
            background: var(--blue-glow);
            border-color: var(--blue);
            color: var(--blue);
        }

        .msg-action-btn.danger:hover {
            background: rgba(239,68,68,.08);
            border-color: var(--red);
            color: var(--red);
        }

        .msg-action-btn .material-icons-round {
            font-size: 16px;
        }

        /* Message body */
        .message-view-body {
            flex: 1;
            overflow-y: auto;
            padding: 24px;
        }

        .message-view-body::-webkit-scrollbar {
            width: 5px;
        }

        .message-view-body::-webkit-scrollbar-track {
            background: transparent;
        }

        .message-view-body::-webkit-scrollbar-thumb {
            background: var(--border-2);
            border-radius: 10px;
        }

        .message-detail {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            padding: 24px;
            font-size: 14px;
            line-height: 1.7;
            color: var(--ink);
            box-shadow: var(--shadow);
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .message-detail p {
            margin-bottom: 12px;
        }

        .message-detail a {
            color: var(--blue);
            text-decoration: none;
        }

        .message-detail a:hover {
            text-decoration: underline;
        }

        /* Attachments */
        .attachments-section {
            margin-top: 20px;
        }

        .attachments-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .attachments-title .material-icons-round {
            font-size: 18px;
            color: var(--blue);
        }

        .attachments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 12px;
        }

        .attachment-card {
            background: var(--surface);
            border: 1.5px solid var(--border);
            border-radius: var(--r);
            padding: 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            transition: all .18s var(--ease);
            cursor: pointer;
        }

        .attachment-card:hover {
            border-color: var(--blue);
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .attachment-icon {
            font-size: 36px;
            margin-bottom: 8px;
            color: var(--blue);
        }

        .attachment-name {
            font-size: 12px;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 4px;
            word-break: break-word;
        }

        .attachment-size {
            font-size: 11px;
            color: var(--ink-3);
            font-family: 'DM Mono', monospace;
        }

        /* ══════════════════════════════════════════════════════════
           EMPTY STATE
           ══════════════════════════════════════════════════════════ */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 80px 20px;
            gap: 12px;
            text-align: center;
            height: 100%;
        }

        .empty-icon {
            font-size: 64px;
            margin-bottom: 8px;
            opacity: 0.6;
        }

        .empty-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--ink-2);
        }

        .empty-text {
            font-size: 14px;
            color: var(--ink-3);
            max-width: 300px;
        }

        /* ══════════════════════════════════════════════════════════
           LOADING STATE
           ══════════════════════════════════════════════════════════ */
        .loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 20px;
            gap: 16px;
            color: var(--ink-3);
        }

        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 3px solid var(--border-2);
            border-top-color: var(--blue);
            border-radius: 50%;
            animation: spin .8s linear infinite;
        }

        .loading-text {
            font-size: 13px;
            font-weight: 600;
        }

        /* ══════════════════════════════════════════════════════════
           TOAST NOTIFICATIONS
           ══════════════════════════════════════════════════════════ */
        #toastContainer {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .toast {
            background: var(--ink);
            color: white;
            padding: 10px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,.25);
            animation: toastIn .25s var(--ease-spring) forwards;
            max-width: 320px;
        }

        .toast.success {
            background: var(--green);
        }

        .toast.error {
            background: var(--red);
        }

        .toast.info {
            background: var(--blue);
        }

        @keyframes toastIn {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: none; }
        }

        @keyframes toastOut {
            to { opacity: 0; transform: translateX(20px); }
        }

        .toast .material-icons-round {
            font-size: 16px;
        }

        /* ══════════════════════════════════════════════════════════
           SEARCH HIGHLIGHTS
           ══════════════════════════════════════════════════════════ */
        mark {
            background-color: rgba(245,158,11,0.3);
            color: var(--ink);
            padding: 2px 3px;
            border-radius: 3px;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <?php require 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="topbar">
            <div class="topbar-title">
                <span class="material-icons-round">mail</span>
                Inbox
            </div>
            <div class="topbar-spacer"></div>
            <div class="topbar-actions">
                <button class="btn-icon" onclick="syncMessages()" title="Sync now" id="syncBtn">
                    <span class="material-icons-round">sync</span>
                </button>
                <button class="btn-icon" onclick="forceRefresh()" title="Force refresh" id="refreshBtn">
                    <span class="material-icons-round">refresh</span>
                </button>
                <button class="btn-compose" onclick="location.href='index.php'">
                    <span class="material-icons-round">edit</span>
                    Compose
                </button>
            </div>
        </div>

        <!-- Stats Bar -->
        <div class="stats-bar">
            <div class="stat-item">
                <div class="stat-icon total">
                    <span class="material-icons-round">mail</span>
                </div>
                <div class="stat-content">
                    <div class="stat-number" id="totalCount"><?= $totalCount ?></div>
                    <div class="stat-label">Total Received</div>
                </div>
            </div>

            <div class="stat-item">
                <div class="stat-icon unread">
                    <span class="material-icons-round">mark_email_unread</span>
                </div>
                <div class="stat-content">
                    <div class="stat-number" id="unreadCount"><?= $unreadCount ?></div>
                    <div class="stat-label">Unread</div>
                </div>
            </div>

            <div class="stat-item">
                <div class="stat-icon new-stat">
                    <span class="material-icons-round">fiber_new</span>
                </div>
                <div class="stat-content">
                    <div class="stat-number" id="newCount"><?= $newCount ?></div>
                    <div class="stat-label">New Today</div>
                </div>
            </div>

            <div class="sync-info">
                <div class="sync-text">
                    <span class="material-icons-round">schedule</span>
                    <span id="lastSyncText">
                        <?php if ($lastSync): ?>
                        Last synced: <?= date('g:i A', strtotime($lastSync)) ?>
                        <?php else: ?>
                        Never synced
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Split Pane Content -->
        <div class="content-wrapper">
            <!-- Left Pane: Messages List -->
            <div class="messages-pane">
                <!-- Toolbar -->
                <div class="toolbar">
                    <div class="search-wrap">
                        <span class="material-icons-round">search</span>
                        <input type="text" id="searchInput" placeholder="Search messages…" autocomplete="off">
                    </div>

                    <div class="filter-row">
                        <button class="filter-chip" id="filterUnread" onclick="toggleFilter('unread')">
                            <span class="material-icons-round">mark_email_unread</span>
                            Unread
                        </button>
                        <button class="filter-chip" id="filterStarred" onclick="toggleFilter('starred')">
                            <span class="material-icons-round">star</span>
                            Starred
                        </button>
                        <button class="filter-chip" id="filterNew" onclick="toggleFilter('new')">
                            <span class="material-icons-round">fiber_new</span>
                            New
                        </button>
                    </div>
                </div>

                <!-- Messages Area -->
                <div class="messages-area">
                    <div id="messagesList">
                        <div class="loading">
                            <div class="loading-spinner"></div>
                            <div class="loading-text">Loading messages...</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Pane: Message View -->
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

    <!-- Toast Container -->
    <div id="toastContainer"></div>

    <script>
        let currentFilters = { unread_only: false, starred_only: false, new_only: false };
        let currentSearchQuery = '';
        let currentMessageId = null;
        let selectedMessageElement = null;

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', () => {
            fetchMessages();
            setupSearchListener();
        });

        // Fetch messages
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

        // Render messages
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

                // Get preview
                let preview = '';
                if (msg.body_preview && msg.body_preview.trim()) {
                    preview = msg.body_preview.substring(0, 120);
                } else if (msg.body) {
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = msg.body;
                    const plainText = tempDiv.textContent || tempDiv.innerText || '';
                    preview = plainText.substring(0, 120);
                } else {
                    preview = 'No preview available';
                }
                
                const senderEmail = msg.sender_email || 'unknown@email.com';
                const senderName = msg.sender_name || senderEmail;
                const subjectDisplay = msg.subject || '(No Subject)';

                return `
                    <div class="${classes.join(' ')}" onclick="viewMessage(${msg.id}, event)" data-message-id="${msg.id}">
                        <span class="material-icons-round message-star ${msg.is_starred == 1 ? 'starred' : ''}"
                              onclick="toggleStar(${msg.id}, event)">
                            ${msg.is_starred == 1 ? 'star' : 'star_border'}
                        </span>

                        <div class="message-content">
                            <div class="message-header">
                                <span class="message-sender" title="${escapeHtml(senderEmail)}">
                                    ${escapeHtml(senderName)}
                                    ${senderName !== senderEmail ? '<span class="sender-email-hint">&lt;' + escapeHtml(senderEmail) + '&gt;</span>' : ''}
                                </span>
                                <span class="message-date" title="${formatDateLong(msg.received_date)}">
                                    ${formatDate(msg.received_date)}
                                </span>
                            </div>
                            <div class="message-subject-line">
                                <span class="message-subject" title="${escapeHtml(subjectDisplay)}">
                                    ${escapeHtml(subjectDisplay)}
                                </span>
                                ${msg.has_attachments == 1 ? '<span class="inline-attachment-icon material-icons-round" title="Has attachments">attach_file</span>' : ''}
                            </div>
                            <div class="message-preview" title="${escapeHtml(preview)}">
                                ${escapeHtml(preview)}${preview.length >= 120 ? '...' : ''}
                            </div>
                        </div>
                        
                        <div class="message-right-meta">
                            ${msg.is_read == 0 ? '<span class="unread-dot"></span>' : ''}
                            <span class="message-id-badge" title="Message ID: ${msg.id}">#${msg.id}</span>
                        </div>
                    </div>
                `;
            }).join('');

            storeOriginalContent();
        }

        // View message
        async function viewMessage(messageId, event) {
            if (event) {
                if (selectedMessageElement) {
                    selectedMessageElement.classList.remove('selected');
                }
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

                    // Build attachments HTML
                    let attachmentsHtml = '';
                    if (attachments.length > 0) {
                        attachmentsHtml = `
                            <div class="attachments-section">
                                <div class="attachments-title">
                                    <span class="material-icons-round">attach_file</span>
                                    Attachments (${attachments.length})
                                </div>
                                <div class="attachments-grid">
                                    ${attachments.map(att => `
                                        <div class="attachment-card" onclick="downloadAttachment('${escapeHtml(att.filename)}')">
                                            <span class="material-icons-round attachment-icon">
                                                ${getFileIcon(att.filename)}
                                            </span>
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
                            <div class="message-view-title">${escapeHtml(msg.subject || '(No Subject)')}</div>
                            <div class="message-view-meta">
                                <div class="message-view-meta-item">
                                    <span class="material-icons-round">person</span>
                                    <span class="message-view-meta-label">From:</span>
                                    <span class="message-view-meta-value">${escapeHtml(msg.sender_name || msg.sender_email)}</span>
                                </div>
                                <div class="message-view-meta-item">
                                    <span class="material-icons-round">schedule</span>
                                    <span class="message-view-meta-label">Received:</span>
                                    <span class="message-view-meta-value">${formatDateLong(msg.received_date)}</span>
                                </div>
                            </div>
                            <div class="message-view-actions">
                                <button class="msg-action-btn" onclick="markAsUnread(${msg.id})">
                                    <span class="material-icons-round">mark_email_unread</span>
                                    Mark unread
                                </button>
                                <button class="msg-action-btn" onclick="toggleStar(${msg.id})">
                                    <span class="material-icons-round">${msg.is_starred ? 'star' : 'star_border'}</span>
                                    ${msg.is_starred ? 'Unstar' : 'Star'}
                                </button>
                                <button class="msg-action-btn danger" onclick="deleteMessage(${msg.id})">
                                    <span class="material-icons-round">delete</span>
                                    Delete
                                </button>
                            </div>
                        </div>
                        <div class="message-view-body">
                            <div class="message-detail">${escapeHtml(msg.body)}</div>
                            ${attachmentsHtml}
                        </div>
                    `;

                    // Update unread indicator
                    if (selectedMessageElement) {
                        selectedMessageElement.classList.remove('unread');
                        const unreadDot = selectedMessageElement.querySelector('.unread-dot');
                        if (unreadDot) unreadDot.remove();
                    }

                    updateCounts();
                }
            } catch (error) {
                console.error('Error viewing message:', error);
                showToast('Error loading message', 'error');
            }
        }

        // Toggle star
        async function toggleStar(messageId, event) {
            if (event) event.stopPropagation();

            try {
                const response = await fetch(`inbox.php?action=toggle_star&id=${messageId}`);
                const data = await response.json();

                if (data.success) {
                    const starIcon = document.querySelector(`.message-item[data-message-id="${messageId}"] .message-star`);
                    if (starIcon) {
                        const isStarred = starIcon.classList.toggle('starred');
                        starIcon.textContent = isStarred ? 'star' : 'star_border';
                    }
                    
                    if (currentMessageId === messageId) {
                        viewMessage(messageId);
                    }
                }
            } catch (error) {
                console.error('Error toggling star:', error);
            }
        }

        // Mark as unread
        async function markAsUnread(messageId) {
            try {
                const response = await fetch(`inbox.php?action=mark_unread&id=${messageId}`);
                const data = await response.json();

                if (data.success) {
                    showToast('Marked as unread', 'success');
                    fetchMessages();
                    updateCounts();
                }
            } catch (error) {
                console.error('Error marking as unread:', error);
            }
        }

        // Delete message
        async function deleteMessage(messageId) {
            if (!confirm('Delete this message?')) return;

            try {
                const response = await fetch(`inbox.php?action=delete&id=${messageId}`);
                const data = await response.json();

                if (data.success) {
                    showToast('Message deleted', 'success');
                    document.getElementById('messageViewContent').innerHTML = `
                        <div class="empty-state">
                            <div class="empty-icon">✉️</div>
                            <div class="empty-title">No message selected</div>
                            <div class="empty-text">Click on a message to view its contents</div>
                        </div>
                    `;
                    currentMessageId = null;
                    selectedMessageElement = null;
                    fetchMessages();
                    updateCounts();
                }
            } catch (error) {
                console.error('Error deleting message:', error);
                showToast('Error deleting message', 'error');
            }
        }

        // Toggle filter
        function toggleFilter(type) {
            const filterMap = {
                'unread': 'unread_only',
                'starred': 'starred_only',
                'new': 'new_only'
            };

            const filterKey = filterMap[type];
            currentFilters[filterKey] = !currentFilters[filterKey];

            const btnId = 'filter' + type.charAt(0).toUpperCase() + type.slice(1);
            document.getElementById(btnId).classList.toggle('active', currentFilters[filterKey]);

            fetchMessages();
        }

        // Sync messages
        async function syncMessages() {
            const btn = document.getElementById('syncBtn');
            btn.classList.add('rotating');
            
            try {
                const response = await fetch('inbox.php?action=sync&limit=50');
                const data = await response.json();

                if (data.success) {
                    showToast(`Synced ${data.new_count || 0} new messages`, 'success');
                    fetchMessages();
                    updateCounts();
                    
                    const now = new Date();
                    document.getElementById('lastSyncText').textContent = 
                        `Last synced: ${now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })}`;
                }
            } catch (error) {
                console.error('Error syncing:', error);
                showToast('Sync failed', 'error');
            } finally {
                setTimeout(() => btn.classList.remove('rotating'), 600);
            }
        }

        // Force refresh
        async function forceRefresh() {
            const btn = document.getElementById('refreshBtn');
            btn.classList.add('rotating');

            try {
                const response = await fetch('inbox.php?action=sync&limit=50&force=true');
                const data = await response.json();

                if (data.success) {
                    showToast('Inbox refreshed', 'success');
                    fetchMessages();
                    updateCounts();
                }
            } catch (error) {
                console.error('Error refreshing:', error);
                showToast('Refresh failed', 'error');
            } finally {
                setTimeout(() => btn.classList.remove('rotating'), 600);
            }
        }

        // Update counts
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

        // Show toast
        function showToast(message, type = 'info') {
            const icons = { success: 'check_circle', error: 'error', info: 'info' };
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <span class="material-icons-round">${icons[type] || 'info'}</span>
                ${escapeHtml(message)}
            `;
            document.getElementById('toastContainer').appendChild(toast);
            setTimeout(() => {
                toast.style.animation = 'toastOut .3s ease forwards';
                setTimeout(() => toast.remove(), 300);
            }, 3200);
        }

        // Helper functions
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatDate(dateStr) {
            const date = new Date(dateStr);
            const now = new Date();
            const diff = now - date;
            const days = Math.floor(diff / 86400000);

            if (days === 0) {
                return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
            } else if (days === 1) {
                return 'Yesterday';
            } else if (days < 7) {
                return date.toLocaleDateString('en-US', { weekday: 'short' });
            } else {
                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            }
        }

        function formatDateLong(dateStr) {
            return new Date(dateStr).toLocaleString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit'
            });
        }

        function formatBytes(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1048576).toFixed(1) + ' MB';
        }

        function getFileIcon(filename) {
            const ext = filename.split('.').pop().toLowerCase();
            const iconMap = {
                'pdf': 'picture_as_pdf',
                'doc': 'description', 'docx': 'description',
                'xls': 'table_chart', 'xlsx': 'table_chart',
                'jpg': 'image', 'jpeg': 'image', 'png': 'image', 'gif': 'image',
                'zip': 'folder_zip', 'rar': 'folder_zip',
                'txt': 'article',
            };
            return iconMap[ext] || 'insert_drive_file';
        }

        // Client-side search with highlighting
        const originalMessageContent = new Map();

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

        function performClientSideSearch() {
            const searchInput = document.getElementById('searchInput');
            const searchTerm = searchInput ? searchInput.value.trim() : '';
            
            if (!searchTerm) {
                removeAllHighlights();
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
                
                const senderText = original.sender.replace(/<[^>]*>/g, '').toLowerCase();
                const subjectText = original.subject.replace(/<[^>]*>/g, '').toLowerCase();
                const previewText = original.preview.replace(/<[^>]*>/g, '').toLowerCase();
                
                const senderMatch = senderText.includes(searchLower);
                const subjectMatch = subjectText.includes(searchLower);
                const previewMatch = previewText.includes(searchLower);
                
                const isMatch = senderMatch || subjectMatch || previewMatch;
                
                if (isMatch) {
                    item.style.display = 'flex';
                    visibleCount++;
                    
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
            
            const messageList = document.getElementById('messagesList');
            if (visibleCount === 0 && messageList) {
                let noResultsDiv = document.getElementById('searchNoResults');
                if (!noResultsDiv) {
                    noResultsDiv = document.createElement('div');
                    noResultsDiv.id = 'searchNoResults';
                    noResultsDiv.className = 'empty-state';
                    noResultsDiv.innerHTML = `
                        <div class="empty-icon">🔍</div>
                        <div class="empty-title">No results found</div>
                        <div class="empty-text">Try adjusting your search terms</div>
                    `;
                    messageList.appendChild(noResultsDiv);
                }
                noResultsDiv.style.display = 'flex';
            } else {
                const noResultsDiv = document.getElementById('searchNoResults');
                if (noResultsDiv) {
                    noResultsDiv.style.display = 'none';
                }
            }
        }

        function highlightMatches(text, searchTerm) {
            if (!searchTerm || !text) return text;
            
            const escapedTerm = searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const regex = new RegExp(`(${escapedTerm})`, 'gi');
            return text.replace(regex, '<mark>$1</mark>');
        }

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

        // Auto-update counts every 30 seconds
        setInterval(updateCounts, 30000);

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                syncMessages();
            }
        });
    </script>
</body>

</html>