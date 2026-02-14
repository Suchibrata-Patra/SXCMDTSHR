<?php
/**
 * SXC MDTS Inbox - Redesigned with Light Clean UI/UX
 * Inspired by drive.php aesthetic
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
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* ═══════════════════════════════════════════════════════════ */
        /*  MAIN CONTAINER                                              */
        /* ═══════════════════════════════════════════════════════════ */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 24px;
            gap: 20px;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }

        /* ═══════════════════════════════════════════════════════════ */
        /*  HEADER                                                       */
        /* ═══════════════════════════════════════════════════════════ */
        .page-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 24px 28px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: slideDown 0.4s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header-left h1 {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a2e;
            letter-spacing: -0.5px;
            margin-bottom: 4px;
        }

        .header-left p {
            font-size: 14px;
            color: #64748b;
            font-weight: 400;
        }

        .header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .btn {
            padding: 10px 18px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: inherit;
        }

        .btn .material-icons-round {
            font-size: 20px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: white;
            color: #64748b;
            border: 1.5px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            transform: translateY(-1px);
        }

        .sync-btn.syncing .material-icons-round {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* ═══════════════════════════════════════════════════════════ */
        /*  STATS CARDS                                                  */
        /* ═══════════════════════════════════════════════════════════ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            animation: fadeIn 0.5s ease 0.1s backwards;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            border: 1.5px solid rgba(255, 255, 255, 0.8);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 28px rgba(0, 0, 0, 0.12);
            border-color: rgba(102, 126, 234, 0.3);
        }

        .stat-card .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            font-size: 22px;
        }

        .stat-card.total .stat-icon {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .stat-card.unread .stat-icon {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }

        .stat-card.new .stat-icon {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }

        .stat-card .stat-label {
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .stat-card .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #1a1a2e;
            line-height: 1;
        }

        /* ═══════════════════════════════════════════════════════════ */
        /*  FILTER BAR                                                   */
        /* ═══════════════════════════════════════════════════════════ */
        .filter-bar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 14px;
            padding: 18px 24px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
            animation: fadeIn 0.5s ease 0.2s backwards;
        }

        .search-box {
            position: relative;
            flex: 1;
            min-width: 200px;
        }

        .search-box .material-icons-round {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 20px;
        }

        .search-box input {
            width: 100%;
            padding: 11px 16px 11px 46px;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s;
            background: white;
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .filter-chip {
            padding: 8px 16px;
            border: 1.5px solid #e2e8f0;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
            color: #64748b;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .filter-chip .material-icons-round {
            font-size: 18px;
        }

        .filter-chip.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: transparent;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .filter-chip:hover:not(.active) {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        /* ═══════════════════════════════════════════════════════════ */
        /*  MESSAGE LIST                                                 */
        /* ═══════════════════════════════════════════════════════════ */
        .messages-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 14px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
            overflow: hidden;
            animation: fadeIn 0.5s ease 0.3s backwards;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .message-list {
            overflow-y: auto;
            max-height: calc(100vh - 400px);
        }

        .message-list::-webkit-scrollbar {
            width: 8px;
        }

        .message-list::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        .message-list::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        .message-list::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .message-item {
            display: flex;
            align-items: center;
            padding: 18px 24px;
            border-bottom: 1px solid #f1f5f9;
            cursor: pointer;
            transition: all 0.2s;
            gap: 16px;
        }

        .message-item:hover {
            background: linear-gradient(90deg, rgba(102, 126, 234, 0.04) 0%, rgba(118, 75, 162, 0.04) 100%);
        }

        .message-item.unread {
            background: rgba(240, 147, 251, 0.05);
        }

        .message-item.unread:hover {
            background: rgba(240, 147, 251, 0.1);
        }

        .message-checkbox {
            width: 20px;
            height: 20px;
            border: 2px solid #cbd5e1;
            border-radius: 6px;
            cursor: pointer;
            flex-shrink: 0;
            transition: all 0.2s;
        }

        .message-checkbox:hover {
            border-color: #667eea;
        }

        .message-checkbox.checked {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: transparent;
        }

        .message-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 16px;
            flex-shrink: 0;
            text-transform: uppercase;
        }

        .message-content {
            flex: 1;
            min-width: 0;
        }

        .message-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
        }

        .message-sender {
            font-weight: 600;
            color: #1a1a2e;
            font-size: 14px;
        }

        .message-item.unread .message-sender {
            font-weight: 700;
        }

        .message-subject {
            font-size: 14px;
            color: #1a1a2e;
            margin-bottom: 4px;
            font-weight: 500;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .message-item.unread .message-subject {
            font-weight: 600;
        }

        .message-preview {
            font-size: 13px;
            color: #64748b;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .message-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 8px;
            flex-shrink: 0;
        }

        .message-time {
            font-size: 12px;
            color: #94a3b8;
            font-weight: 500;
            white-space: nowrap;
        }

        .message-actions {
            display: flex;
            gap: 4px;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .message-item:hover .message-actions {
            opacity: 1;
        }

        .message-action-btn {
            width: 32px;
            height: 32px;
            border: none;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            color: #64748b;
        }

        .message-action-btn:hover {
            background: #f1f5f9;
            color: #1a1a2e;
            transform: scale(1.1);
        }

        .message-action-btn.starred {
            color: #fbbf24;
        }

        .message-action-btn .material-icons-round {
            font-size: 18px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .badge.new {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }

        .badge.unread-badge {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }

        /* ═══════════════════════════════════════════════════════════ */
        /*  MODAL                                                        */
        /* ═══════════════════════════════════════════════════════════ */
        .modal-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(8px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: fadeInBackdrop 0.3s ease;
        }

        .modal-backdrop.show {
            display: flex;
        }

        @keyframes fadeInBackdrop {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal {
            background: white;
            border-radius: 16px;
            max-width: 900px;
            width: 100%;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(40px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header {
            padding: 24px 28px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            font-size: 20px;
            font-weight: 700;
            color: #1a1a2e;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            flex: 1;
            margin-right: 16px;
        }

        .modal-close {
            width: 36px;
            height: 36px;
            border: none;
            background: #f1f5f9;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            flex-shrink: 0;
        }

        .modal-close:hover {
            background: #e2e8f0;
            transform: rotate(90deg);
        }

        .modal-close .material-icons-round {
            font-size: 20px;
            color: #64748b;
        }

        .modal-body {
            padding: 24px 28px;
            overflow-y: auto;
            flex: 1;
        }

        .message-detail-header {
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f1f5f9;
        }

        .message-detail-subject {
            font-size: 24px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 16px;
            line-height: 1.3;
        }

        .message-detail-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .message-detail-from {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message-detail-from .message-avatar {
            width: 40px;
            height: 40px;
            font-size: 15px;
        }

        .sender-info strong {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #1a1a2e;
            margin-bottom: 2px;
        }

        .sender-info span {
            font-size: 13px;
            color: #64748b;
        }

        .message-detail-date {
            font-size: 13px;
            color: #94a3b8;
        }

        .message-detail-body {
            font-size: 15px;
            line-height: 1.7;
            color: #334155;
        }

        .message-detail-body p {
            margin-bottom: 14px;
        }

        /* ═══════════════════════════════════════════════════════════ */
        /*  TOAST NOTIFICATIONS                                          */
        /* ═══════════════════════════════════════════════════════════ */
        .toast-container {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .toast {
            background: white;
            border-radius: 12px;
            padding: 16px 20px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 300px;
            animation: toastIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border-left: 4px solid;
        }

        @keyframes toastIn {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .toast.success {
            border-color: #10b981;
        }

        .toast.error {
            border-color: #ef4444;
        }

        .toast.info {
            border-color: #3b82f6;
        }

        .toast .material-icons-round {
            font-size: 24px;
        }

        .toast.success .material-icons-round {
            color: #10b981;
        }

        .toast.error .material-icons-round {
            color: #ef4444;
        }

        .toast.info .material-icons-round {
            color: #3b82f6;
        }

        .toast-message {
            flex: 1;
            font-size: 14px;
            font-weight: 500;
            color: #1a1a2e;
        }

        /* ═══════════════════════════════════════════════════════════ */
        /*  EMPTY STATE                                                  */
        /* ═══════════════════════════════════════════════════════════ */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #64748b;
        }

        .empty-state .material-icons-round {
            font-size: 64px;
            color: #cbd5e1;
            margin-bottom: 16px;
        }

        .empty-state h3 {
            font-size: 20px;
            font-weight: 600;
            color: #1a1a2e;
            margin-bottom: 8px;
        }

        .empty-state p {
            font-size: 14px;
            color: #64748b;
        }

        /* ═══════════════════════════════════════════════════════════ */
        /*  LOADING                                                      */
        /* ═══════════════════════════════════════════════════════════ */
        .loading-spinner {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }

        .loading-spinner .material-icons-round {
            font-size: 36px;
            color: #667eea;
            animation: spin 1s linear infinite;
        }

        /* ═══════════════════════════════════════════════════════════ */
        /*  RESPONSIVE                                                   */
        /* ═══════════════════════════════════════════════════════════ */
        @media (max-width: 768px) {
            .main-content {
                padding: 16px;
                gap: 16px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
                padding: 20px;
            }

            .header-actions {
                width: 100%;
                justify-content: stretch;
            }

            .header-actions .btn {
                flex: 1;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filter-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                min-width: 100%;
            }

            .message-item {
                padding: 14px 16px;
            }

            .message-avatar {
                width: 36px;
                height: 36px;
                font-size: 14px;
            }

            .message-actions {
                opacity: 1;
            }

            .modal {
                max-width: 100%;
                margin: 0;
                border-radius: 16px 16px 0 0;
            }
        }

        /* ═══════════════════════════════════════════════════════════ */
        /*  HIGHLIGHT STYLES                                             */
        /* ═══════════════════════════════════════════════════════════ */
        mark {
            background-color: #fef08a;
            color: #1a1a2e;
            padding: 2px 4px;
            border-radius: 4px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="page-header">
            <div class="header-left">
                <h1>📬 Inbox</h1>
                <p><?= htmlspecialchars($userEmail) ?> • <?= $lastSync ? 'Last sync: ' . date('M d, Y H:i', strtotime($lastSync)) : 'Never synced' ?></p>
            </div>
            <div class="header-actions">
                <button class="btn btn-secondary" onclick="window.location.href='compose.php'">
                    <span class="material-icons-round">edit</span>
                    Compose
                </button>
                <button class="btn btn-primary sync-btn" onclick="syncMessages()">
                    <span class="material-icons-round">sync</span>
                    Sync
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card total" onclick="clearFilters()">
                <div class="stat-icon">
                    <span class="material-icons-round">mail</span>
                </div>
                <div class="stat-label">Total Messages</div>
                <div class="stat-value" id="totalCount"><?= $totalCount ?></div>
            </div>
            <div class="stat-card unread" onclick="filterUnread()">
                <div class="stat-icon">
                    <span class="material-icons-round">mark_email_unread</span>
                </div>
                <div class="stat-label">Unread</div>
                <div class="stat-value" id="unreadCount"><?= $unreadCount ?></div>
            </div>
            <div class="stat-card new" onclick="filterNew()">
                <div class="stat-icon">
                    <span class="material-icons-round">fiber_new</span>
                </div>
                <div class="stat-label">New Today</div>
                <div class="stat-value" id="newCount"><?= $newCount ?></div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="search-box">
                <span class="material-icons-round">search</span>
                <input type="text" id="searchInput" placeholder="Search messages..." autocomplete="off">
            </div>
            <div class="filter-chip" id="filterAll" onclick="clearFilters()">
                <span class="material-icons-round">all_inbox</span>
                All
            </div>
            <div class="filter-chip" id="filterUnread" onclick="filterUnread()">
                <span class="material-icons-round">mark_email_unread</span>
                Unread
            </div>
            <div class="filter-chip" id="filterStarred" onclick="filterStarred()">
                <span class="material-icons-round">star</span>
                Starred
            </div>
            <div class="filter-chip" id="filterNew" onclick="filterNew()">
                <span class="material-icons-round">fiber_new</span>
                New
            </div>
        </div>

        <!-- Messages Container -->
        <div class="messages-container">
            <div class="message-list" id="messageList">
                <?php if (empty($messages)): ?>
                    <div class="empty-state">
                        <span class="material-icons-round">inbox</span>
                        <h3>No messages yet</h3>
                        <p>Your inbox is empty. Sync to fetch new messages.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $msg): ?>
                        <?php
                        $fromParts = explode('@', $msg['sender_email']);
                        $initial = strtoupper(substr($fromParts[0], 0, 1));
                        $isUnread = empty($msg['read_at']);
                        $isNew = !empty($msg['is_new']) && $msg['is_new'] == 1;
                        $isStarred = !empty($msg['starred']) && $msg['starred'] == 1;
                        ?>
                        <div class="message-item <?= $isUnread ? 'unread' : '' ?>" 
                             data-message-id="<?= $msg['id'] ?>"
                             onclick="openMessage(<?= $msg['id'] ?>)">
                            <div class="message-checkbox" onclick="event.stopPropagation(); toggleSelect(this)"></div>
                            <div class="message-avatar"><?= $initial ?></div>
                            <div class="message-content">
                                <div class="message-header">
                                    <span class="message-sender"><?= htmlspecialchars($msg['sender_name'] ?: $msg['sender_email']) ?></span>
                                    <?php if ($isNew): ?>
                                        <span class="badge new">New</span>
                                    <?php endif; ?>
                                    <?php if ($isUnread): ?>
                                        <span class="badge unread-badge">Unread</span>
                                    <?php endif; ?>
                                </div>
                                <div class="message-subject"><?= htmlspecialchars($msg['subject'] ?: '(No Subject)') ?></div>
                                <div class="message-preview"><?= htmlspecialchars(substr(strip_tags($msg['body_text'] ?? $msg['body_html'] ?? ''), 0, 100)) ?></div>
                            </div>
                            <div class="message-meta">
                                <div class="message-time"><?= date('M d', strtotime($msg['received_at'])) ?></div>
                                <div class="message-actions">
                                    <button class="message-action-btn <?= $isStarred ? 'starred' : '' ?>" 
                                            onclick="event.stopPropagation(); toggleStar(<?= $msg['id'] ?>, this)"
                                            title="Star">
                                        <span class="material-icons-round"><?= $isStarred ? 'star' : 'star_outline' ?></span>
                                    </button>
                                    <button class="message-action-btn" 
                                            onclick="event.stopPropagation(); deleteMessage(<?= $msg['id'] ?>)"
                                            title="Delete">
                                        <span class="material-icons-round">delete_outline</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Message Detail Modal -->
    <div class="modal-backdrop" id="messageModal">
        <div class="modal">
            <div class="modal-header">
                <h2 id="modalSubject">Message</h2>
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
            setActiveFilter('filterAll');
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
            const messageList = document.getElementById('messageList');
            messageList.innerHTML = '<div class="loading-spinner"><span class="material-icons-round">autorenew</span></div>';
            
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
                messageList.innerHTML = `<div class="empty-state">
                    <span class="material-icons-round">error_outline</span>
                    <h3>Error loading messages</h3>
                    <p>${escapeHtml(error.message)}</p>
                </div>`;
            }
        }

        // ═══════════════════════════════════════════════════════════
        //  RENDER MESSAGES
        // ═══════════════════════════════════════════════════════════
        function renderMessages(messages) {
            const messageList = document.getElementById('messageList');
            
            if (!messages || messages.length === 0) {
                messageList.innerHTML = `<div class="empty-state">
                    <span class="material-icons-round">inbox</span>
                    <h3>No messages found</h3>
                    <p>Try adjusting your filters or sync to fetch new messages.</p>
                </div>`;
                return;
            }
            
            messageList.innerHTML = messages.map(msg => {
                const initial = msg.sender_email.charAt(0).toUpperCase();
                const isUnread = !msg.read_at;
                const isNew = msg.is_new == 1;
                const isStarred = msg.starred == 1;
                
                return `
                    <div class="message-item ${isUnread ? 'unread' : ''}" 
                         data-message-id="${msg.id}"
                         onclick="openMessage(${msg.id})">
                        <div class="message-checkbox" onclick="event.stopPropagation(); toggleSelect(this)"></div>
                        <div class="message-avatar">${initial}</div>
                        <div class="message-content">
                            <div class="message-header">
                                <span class="message-sender">${escapeHtml(msg.sender_name || msg.sender_email)}</span>
                                ${isNew ? '<span class="badge new">New</span>' : ''}
                                ${isUnread ? '<span class="badge unread-badge">Unread</span>' : ''}
                            </div>
                            <div class="message-subject">${escapeHtml(msg.subject || '(No Subject)')}</div>
                            <div class="message-preview">${escapeHtml((msg.body_text || msg.body_html || '').replace(/<[^>]*>/g, '').substring(0, 100))}</div>
                        </div>
                        <div class="message-meta">
                            <div class="message-time">${formatDate(msg.received_at)}</div>
                            <div class="message-actions">
                                <button class="message-action-btn ${isStarred ? 'starred' : ''}" 
                                        onclick="event.stopPropagation(); toggleStar(${msg.id}, this)"
                                        title="Star">
                                    <span class="material-icons-round">${isStarred ? 'star' : 'star_outline'}</span>
                                </button>
                                <button class="message-action-btn" 
                                        onclick="event.stopPropagation(); deleteMessage(${msg.id})"
                                        title="Delete">
                                    <span class="material-icons-round">delete_outline</span>
                                </button>
                            </div>
                        </div>
                    </div>
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
                                    <div class="message-avatar">${initial}</div>
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
                    const messageItem = document.querySelector(`[data-message-id="${id}"]`);
                    if (messageItem) {
                        messageItem.classList.remove('unread');
                        const unreadBadge = messageItem.querySelector('.badge.unread-badge');
                        if (unreadBadge) unreadBadge.remove();
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
            fetchMessages();
        }

        function setActiveFilter(id) {
            document.querySelectorAll('.filter-chip').forEach(chip => {
                chip.classList.remove('active');
            });
            document.getElementById(id).classList.add('active');
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
                    const messageItem = document.querySelector(`[data-message-id="${id}"]`);
                    if (messageItem) {
                        messageItem.style.animation = 'fadeOut 0.3s ease';
                        setTimeout(() => {
                            messageItem.remove();
                            updateCounts();
                        }, 300);
                    }
                    showToast('Message deleted', 'success');
                }
            } catch (error) {
                showToast('Error deleting message', 'error');
            }
        }

        function toggleSelect(checkbox) {
            checkbox.classList.toggle('checked');
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

        function performClientSideSearch() {
            const searchInput = document.getElementById('searchInput');
            const searchTerm = searchInput ? searchInput.value.trim() : '';
            
            if (!searchTerm) {
                removeAllHighlights();
                document.querySelectorAll('.message-item').forEach(item => {
                    item.style.display = 'flex';
                });
                removeNoResultsMessage();
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
            
            if (visibleCount === 0) {
                showNoResultsMessage();
            } else {
                removeNoResultsMessage();
            }
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

        function highlightMatches(text, searchTerm) {
            if (!searchTerm || !text) return text;
            
            const escapedTerm = searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const regex = new RegExp(`(${escapedTerm})`, 'gi');
            return text.replace(regex, '<mark>$1</mark>');
        }

        function showNoResultsMessage() {
            const messageList = document.getElementById('messageList');
            let noResultsDiv = document.getElementById('searchNoResults');
            
            if (!noResultsDiv) {
                noResultsDiv = document.createElement('div');
                noResultsDiv.id = 'searchNoResults';
                noResultsDiv.className = 'empty-state';
                noResultsDiv.innerHTML = `
                    <span class="material-icons-round">search_off</span>
                    <h3>No results found</h3>
                    <p>Try adjusting your search terms</p>
                `;
                messageList.appendChild(noResultsDiv);
            }
            noResultsDiv.style.display = 'block';
        }

        function removeNoResultsMessage() {
            const noResultsDiv = document.getElementById('searchNoResults');
            if (noResultsDiv) {
                noResultsDiv.style.display = 'none';
            }
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
                toast.style.animation = 'toastOut 0.3s ease forwards';
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

        // Add fadeOut animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeOut {
                from {
                    opacity: 1;
                    transform: translateX(0);
                }
                to {
                    opacity: 0;
                    transform: translateX(-20px);
                }
            }
            @keyframes toastOut {
                to {
                    opacity: 0;
                    transform: translateX(100px);
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>