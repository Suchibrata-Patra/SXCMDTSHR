<?php
/**
 * Professional Inbox Page with Sidebar - COMPLETE VERSION
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
            --apple-light-gray: #C7C7CC;
            --apple-bg: #F2F2F7;
            --glass: rgba(255, 255, 255, 0.7);
            --border: #E5E5EA;
            --success-green: #34C759;
            --warning-orange: #FF9500;
            --danger-red: #FF3B30;
            --card-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            --hover-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
            --sidebar-width: 260px;
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

        /* ========== SIDEBAR ========== */
        .sidebar {
            width: var(--sidebar-width);
            background: white;
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            z-index: 200;
        }

        .sidebar-header {
            padding: 24px 20px;
            border-bottom: 1px solid var(--border);
        }

        .app-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--apple-blue);
            letter-spacing: -0.5px;
            margin-bottom: 4px;
        }

        .app-subtitle {
            font-size: 13px;
            color: var(--apple-gray);
            font-weight: 400;
        }

        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            padding: 12px 8px;
        }

        .nav-section {
            margin-bottom: 24px;
        }

        .nav-section-title {
            font-size: 11px;
            font-weight: 600;
            color: var(--apple-gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 8px 12px;
            margin-bottom: 4px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 10px 12px;
            margin: 2px 0;
            border-radius: 8px;
            color: #1c1c1e;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .nav-item:hover {
            background: var(--apple-bg);
        }

        .nav-item.active {
            background: var(--apple-blue);
            color: white;
        }

        .nav-item .material-icons {
            font-size: 20px;
            margin-right: 12px;
        }

        .nav-item-text {
            flex: 1;
        }

        .nav-item-badge {
            background: var(--apple-blue);
            color: white;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 10px;
            min-width: 20px;
            text-align: center;
        }

        .nav-item.active .nav-item-badge {
            background: white;
            color: var(--apple-blue);
        }

        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid var(--border);
        }

        .user-info {
            display: flex;
            align-items: center;
            padding: 8px;
            border-radius: 8px;
            background: var(--apple-bg);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--apple-blue);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            margin-right: 12px;
        }

        .user-details {
            flex: 1;
            min-width: 0;
        }

        .user-name {
            font-size: 13px;
            font-weight: 600;
            color: #1c1c1e;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-email {
            font-size: 11px;
            color: var(--apple-gray);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .logout-btn {
            background: none;
            border: none;
            color: var(--danger-red);
            cursor: pointer;
            padding: 8px;
            border-radius: 6px;
            transition: all 0.2s;
        }

        .logout-btn:hover {
            background: rgba(255, 59, 48, 0.1);
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
            padding: 20px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left {
            flex: 1;
        }

        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: #1c1c1e;
            letter-spacing: -0.7px;
            margin-bottom: 4px;
        }

        .page-subtitle {
            font-size: 14px;
            color: var(--apple-gray);
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
            border-radius: 8px;
            font-size: 14px;
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

        .btn-secondary {
            background: white;
            color: var(--apple-blue);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: var(--apple-bg);
            border-color: var(--apple-blue);
        }

        .btn-icon {
            padding: 10px;
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
        }

        /* ========== STATS BAR ========== */
        .stats-bar {
            background: white;
            padding: 16px 32px;
            border-bottom: 1px solid var(--border);
            display: flex;
            gap: 32px;
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
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .stat-icon.total {
            background: rgba(0, 122, 255, 0.1);
            color: var(--apple-blue);
        }

        .stat-icon.unread {
            background: rgba(255, 149, 0, 0.1);
            color: var(--warning-orange);
        }

        .stat-icon.new {
            background: rgba(52, 199, 89, 0.1);
            color: var(--success-green);
        }

        .stat-content {
            display: flex;
            flex-direction: column;
        }

        .stat-number {
            font-size: 20px;
            font-weight: 700;
            color: #1c1c1e;
            line-height: 1;
        }

        .stat-label {
            font-size: 12px;
            color: var(--apple-gray);
            margin-top: 2px;
        }

        .sync-info {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: var(--apple-gray);
        }

        .sync-info .material-icons {
            font-size: 16px;
        }

        /* ========== CONTENT AREA ========== */
        .content-area {
            flex: 1;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* ========== TOOLBAR ========== */
        .toolbar {
            background: white;
            padding: 16px 32px;
            border-bottom: 1px solid var(--border);
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
            padding: 10px 14px 10px 42px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
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
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--apple-gray);
            font-size: 20px;
        }

        .filter-group {
            display: flex;
            gap: 8px;
        }

        .filter-btn {
            padding: 8px 16px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
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
            font-size: 16px;
        }

        /* ========== MESSAGES AREA ========== */
        .messages-area {
            flex: 1;
            overflow-y: auto;
            padding: 20px 32px;
        }

        .messages-container {
            background: white;
            border-radius: 12px;
            border: 1px solid var(--border);
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }

        .message-item {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            gap: 14px;
            align-items: start;
            position: relative;
        }

        .message-item:last-child {
            border-bottom: none;
        }

        .message-item:hover {
            background: #FAFAFA;
        }

        .message-item.unread {
            background: linear-gradient(90deg, #F5F9FF 0%, #FFFFFF 100%);
        }

        .message-item.unread:hover {
            background: linear-gradient(90deg, #EBF4FF 0%, #FAFAFA 100%);
        }

        .message-item.new {
            background: linear-gradient(90deg, #E8F5E9 0%, #FFFFFF 100%);
        }

        .message-item.new:hover {
            background: linear-gradient(90deg, #DFF0E0 0%, #FAFAFA 100%);
        }

        .message-item.new::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background: var(--success-green);
        }

        .message-checkbox {
            width: 18px;
            height: 18px;
            margin-top: 3px;
            cursor: pointer;
            accent-color: var(--apple-blue);
        }

        .message-star {
            cursor: pointer;
            color: var(--apple-light-gray);
            font-size: 20px;
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

        .message-content {
            flex: 1;
            min-width: 0;
        }

        .message-header {
            display: flex;
            justify-space-between;
            align-items: start;
            margin-bottom: 6px;
        }

        .message-sender {
            font-weight: 600;
            color: #1c1c1e;
            font-size: 15px;
            margin-right: 12px;
        }

        .message-date {
            font-size: 12px;
            color: var(--apple-gray);
            white-space: nowrap;
            font-weight: 500;
        }

        .message-subject {
            font-size: 14px;
            color: #1c1c1e;
            margin-bottom: 6px;
            font-weight: 500;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .message-preview {
            font-size: 13px;
            color: var(--apple-gray);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.5;
        }

        .message-badges {
            display: flex;
            gap: 6px;
            margin-top: 8px;
            flex-wrap: wrap;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-new {
            background: rgba(52, 199, 89, 0.15);
            color: var(--success-green);
        }

        .badge-attachment {
            background: rgba(142, 142, 147, 0.15);
            color: var(--apple-gray);
        }

        .badge-attachment .material-icons {
            font-size: 12px;
        }

        /* ========== EMPTY STATE ========== */
        .empty-state {
            padding: 80px 20px;
            text-align: center;
        }

        .empty-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }

        .empty-title {
            font-size: 22px;
            font-weight: 600;
            color: #1c1c1e;
            margin-bottom: 8px;
        }

        .empty-text {
            font-size: 15px;
            color: var(--apple-gray);
            max-width: 400px;
            margin: 0 auto 24px;
            line-height: 1.6;
        }

        .empty-action {
            margin-top: 20px;
        }

        /* ========== LOADING ========== */
        .loading {
            padding: 60px;
            text-align: center;
        }

        .loading-spinner {
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 3px solid var(--border);
            border-top-color: var(--apple-blue);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-bottom: 16px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .loading-text {
            font-size: 14px;
            color: var(--apple-gray);
        }

        /* ========== MODAL ========== */
        .modal-overlay {
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
            backdrop-filter: blur(4px);
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal {
            background: white;
            border-radius: 16px;
            max-width: 900px;
            max-height: 90vh;
            width: 90%;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header {
            padding: 24px 28px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: start;
            background: white;
        }

        .modal-title-section {
            flex: 1;
            min-width: 0;
            margin-right: 20px;
        }

        .modal-subject {
            font-size: 20px;
            font-weight: 600;
            color: #1c1c1e;
            margin-bottom: 8px;
            line-height: 1.4;
        }

        .modal-meta {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .modal-meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: var(--apple-gray);
        }

        .modal-meta-item .material-icons {
            font-size: 16px;
        }

        .modal-meta-label {
            font-weight: 600;
            color: #1c1c1e;
        }

        .modal-actions {
            display: flex;
            gap: 8px;
        }

        .modal-body {
            padding: 28px;
            overflow-y: auto;
            flex: 1;
            background: #FAFAFA;
        }

        .message-detail {
            background: white;
            border-radius: 12px;
            padding: 24px;
            font-size: 15px;
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

        /* Attachments in modal */
        .attachments-section {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid var(--border);
        }

        .attachments-title {
            font-size: 14px;
            font-weight: 600;
            color: #1c1c1e;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .attachments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 12px;
        }

        .attachment-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px;
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
            font-size: 40px;
            margin-bottom: 8px;
        }

        .attachment-name {
            font-size: 12px;
            font-weight: 500;
            color: #1c1c1e;
            margin-bottom: 4px;
            word-break: break-word;
        }

        .attachment-size {
            font-size: 11px;
            color: var(--apple-gray);
        }

        /* ========== TOAST NOTIFICATIONS ========== */
        .toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #1c1c1e;
            color: white;
            padding: 14px 20px;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            font-weight: 500;
            z-index: 2000;
            animation: toastSlideIn 0.3s ease-out;
            max-width: 400px;
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
            font-size: 20px;
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 768px) {
            .sidebar {
                width: 220px;
            }
            
            .page-header {
                padding: 16px 20px;
            }
            
            .page-title {
                font-size: 24px;
            }
            
            .stats-bar {
                padding: 12px 20px;
                gap: 20px;
            }
            
            .toolbar {
                padding: 12px 20px;
                flex-wrap: wrap;
            }
            
            .messages-area {
                padding: 16px 20px;
            }
        }
    </style>
</head>
<body>
    <!-- ========== SIDEBAR ========== -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="app-title">SXC MDTS</div>
            <div class="app-subtitle">Mail Delivery & Tracking</div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Mail</div>
                <a href="index.php" class="nav-item">
                    <span class="material-icons">edit</span>
                    <span class="nav-item-text">Compose</span>
                </a>
                <a href="inbox.php" class="nav-item active">
                    <span class="material-icons">inbox</span>
                    <span class="nav-item-text">Inbox</span>
                    <span class="nav-item-badge" id="sidebarUnreadBadge"><?= $unreadCount ?></span>
                </a>
                <a href="sent.php" class="nav-item">
                    <span class="material-icons">send</span>
                    <span class="nav-item-text">Sent</span>
                </a>
                <a href="drafts.php" class="nav-item">
                    <span class="material-icons">drafts</span>
                    <span class="nav-item-text">Drafts</span>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Organization</div>
                <div class="nav-item">
                    <span class="material-icons">star</span>
                    <span class="nav-item-text">Starred</span>
                </div>
                <div class="nav-item">
                    <span class="material-icons">label</span>
                    <span class="nav-item-text">Important</span>
                </div>
                <div class="nav-item">
                    <span class="material-icons">delete</span>
                    <span class="nav-item-text">Trash</span>
                </div>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Settings</div>
                <a href="settings.php" class="nav-item">
                    <span class="material-icons">settings</span>
                    <span class="nav-item-text">Settings</span>
                </a>
                <a href="help.php" class="nav-item">
                    <span class="material-icons">help</span>
                    <span class="nav-item-text">Help & Support</span>
                </a>
            </div>
        </nav>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($userEmail, 0, 1)) ?></div>
                <div class="user-details">
                    <div class="user-name">Account</div>
                    <div class="user-email"><?= htmlspecialchars($userEmail) ?></div>
                </div>
                <button class="logout-btn" onclick="logout()" title="Logout">
                    <span class="material-icons">logout</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ========== MAIN CONTENT ========== -->
    <div class="main-content">
        <!-- Header -->
        <div class="page-header">
            <div class="header-left">
                <h1 class="page-title">📬 Inbox</h1>
                <p class="page-subtitle">View and manage your incoming emails</p>
            </div>
            <div class="header-actions">
                <button class="btn-icon" onclick="syncMessages()" title="Sync now" id="syncBtn">
                    <span class="material-icons">sync</span>
                </button>
                <button class="btn-icon" onclick="forceRefresh()" title="Force refresh">
                    <span class="material-icons">refresh</span>
                </button>
                <button class="btn btn-primary" onclick="location.href='index.php'">
                    <span class="material-icons">edit</span>
                    Compose New
                </button>
            </div>
        </div>

        <!-- Stats bar -->
        <div class="stats-bar">
            <div class="stat-item">
                <div class="stat-icon total">
                    <span class="material-icons">mail</span>
                </div>
                <div class="stat-content">
                    <div class="stat-number" id="totalCount"><?= $totalCount ?></div>
                    <div class="stat-label">Total Messages</div>
                </div>
            </div>

            <div class="stat-item">
                <div class="stat-icon unread">
                    <span class="material-icons">mark_email_unread</span>
                </div>
                <div class="stat-content">
                    <div class="stat-number" id="unreadCount"><?= $unreadCount ?></div>
                    <div class="stat-label">Unread</div>
                </div>
            </div>

            <div class="stat-item">
                <div class="stat-icon new">
                    <span class="material-icons">fiber_new</span>
                </div>
                <div class="stat-content">
                    <div class="stat-number" id="newCount"><?= $newCount ?></div>
                    <div class="stat-label">New Today</div>
                </div>
            </div>

            <div class="sync-info">
                <span class="material-icons">schedule</span>
                <span id="lastSyncText">
                    <?php if ($lastSync): ?>
                        Last synced: <?= date('g:i A', strtotime($lastSync)) ?>
                    <?php else: ?>
                        Never synced
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <!-- Content area -->
        <div class="content-area">
            <!-- Toolbar -->
            <div class="toolbar">
                <div class="search-box">
                    <span class="material-icons">search</span>
                    <input type="text" id="searchInput" placeholder="Search in inbox..." onkeyup="searchMessages()">
                </div>

                <div class="filter-group">
                    <button class="filter-btn active" id="filterAll" onclick="setFilter('all')">
                        All
                    </button>
                    <button class="filter-btn" id="filterUnread" onclick="setFilter('unread')">
                        <span class="material-icons">mark_email_unread</span>
                        Unread
                    </button>
                    <button class="filter-btn" id="filterStarred" onclick="setFilter('starred')">
                        <span class="material-icons">star</span>
                        Starred
                    </button>
                    <button class="filter-btn" id="filterNew" onclick="setFilter('new')">
                        <span class="material-icons">fiber_new</span>
                        New
                    </button>
                </div>
            </div>

            <!-- Messages area -->
            <div class="messages-area">
                <div class="messages-container" id="messagesContainer">
                    <div class="loading">
                        <div class="loading-spinner"></div>
                        <div class="loading-text">Loading messages...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== MESSAGE DETAIL MODAL ========== -->
    <div class="modal-overlay" id="messageModal" onclick="closeModalOnOverlay(event)">
        <div class="modal" onclick="event.stopPropagation()">
            <div class="modal-header">
                <div class="modal-title-section">
                    <h2 class="modal-subject" id="modalSubject"></h2>
                    <div class="modal-meta" id="modalMeta"></div>
                </div>
                <div class="modal-actions">
                    <button class="btn-icon" onclick="toggleStarFromModal()" id="modalStarBtn" title="Star">
                        <span class="material-icons">star_border</span>
                    </button>
                    <button class="btn-icon" onclick="deleteMessageFromModal()" title="Delete">
                        <span class="material-icons">delete</span>
                    </button>
                    <button class="btn-icon" onclick="closeModal()" title="Close">
                        <span class="material-icons">close</span>
                    </button>
                </div>
            </div>
            <div class="modal-body" id="modalBody"></div>
        </div>
    </div>

    <script>
        let currentFilter = 'all';
        let messages = <?= json_encode($messages) ?>;
        let currentMessageId = null;
        let syncInterval = null;

        // Initial render
        setTimeout(() => {
            renderMessages(messages);
        }, 500);

        // Auto-sync every 2 minutes
        syncInterval = setInterval(() => {
            syncMessages(true); // Silent sync
        }, 120000);

        function renderMessages(msgs) {
            const container = document.getElementById('messagesContainer');
            
            if (!msgs || msgs.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">📭</div>
                        <div class="empty-title">No messages found</div>
                        <div class="empty-text">Your inbox is empty or there are no messages matching your current filter.</div>
                        <div class="empty-action">
                            <button class="btn btn-primary" onclick="syncMessages()">
                                <span class="material-icons">sync</span>
                                Sync Now
                            </button>
                        </div>
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
                
                let attachments = [];
                if (hasAttachments && msg.attachment_data) {
                    try {
                        attachments = JSON.parse(msg.attachment_data);
                    } catch (e) {}
                }
                
                html += `
                    <div class="message-item ${isUnread ? 'unread' : ''} ${isNew ? 'new' : ''}" onclick="viewMessage(${msg.id})">
                        <span class="material-icons message-star ${isStarred ? 'starred' : ''}" 
                              onclick="event.stopPropagation(); toggleStar(${msg.id})">
                            ${isStarred ? 'star' : 'star_border'}
                        </span>
                        <div class="message-content">
                            <div class="message-header">
                                <div class="message-sender">${escapeHtml(msg.sender_name || msg.sender_email)}</div>
                                <div class="message-date">${formatDate(msg.received_date)}</div>
                            </div>
                            <div class="message-subject">${escapeHtml(msg.subject)}</div>
                            <div class="message-preview">${escapeHtml(msg.body_preview || '')}</div>
                            ${isNew || hasAttachments ? `
                                <div class="message-badges">
                                    ${isNew ? '<span class="badge badge-new">NEW</span>' : ''}
                                    ${hasAttachments ? `
                                        <span class="badge badge-attachment">
                                            <span class="material-icons">attach_file</span>
                                            ${attachments.length} file${attachments.length > 1 ? 's' : ''}
                                        </span>
                                    ` : ''}
                                </div>
                            ` : ''}
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
            const filterId = 'filter' + filter.charAt(0).toUpperCase() + filter.slice(1);
            document.getElementById(filterId).classList.add('active');
            
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
                showToast('Failed to load messages', 'error');
            }
        }

        async function syncMessages(silent = false) {
            const syncBtn = document.getElementById('syncBtn');
            if (syncBtn) {
                syncBtn.querySelector('.material-icons').style.animation = 'spin 0.8s linear infinite';
            }
            
            if (!silent) {
                showToast('Syncing messages...', 'info');
            }
            
            try {
                const response = await fetch('inbox.php?action=sync&limit=50');
                const data = await response.json();
                
                if (syncBtn) {
                    syncBtn.querySelector('.material-icons').style.animation = '';
                }
                
                if (data.success) {
                    if (!silent) {
                        showToast(data.message, 'success');
                    }
                    fetchMessages();
                    updateLastSyncTime();
                } else {
                    if (!silent) {
                        showToast('Sync failed: ' + data.error, 'error');
                    }
                }
            } catch (error) {
                if (syncBtn) {
                    syncBtn.querySelector('.material-icons').style.animation = '';
                }
                console.error('Error syncing:', error);
                if (!silent) {
                    showToast('Sync failed', 'error');
                }
            }
        }

        async function forceRefresh() {
            if (!confirm('Force refresh will clear all cached messages and re-fetch from the server. This may take a moment. Continue?')) {
                return;
            }
            
            showToast('Refreshing inbox...', 'info');
            
            try {
                const response = await fetch('inbox.php?action=sync&limit=100&force=true');
                const data = await response.json();
                
                if (data.success) {
                    showToast(data.message, 'success');
                    fetchMessages();
                    updateLastSyncTime();
                } else {
                    showToast('Refresh failed: ' + data.error, 'error');
                }
            } catch (error) {
                console.error('Error refreshing:', error);
                showToast('Refresh failed', 'error');
            }
        }

        async function viewMessage(messageId) {
            currentMessageId = messageId;
            
            try {
                const response = await fetch(`inbox.php?action=get_message&id=${messageId}`);
                const data = await response.json();
                
                if (data.success) {
                    const msg = data.message;
                    
                    // Set subject
                    document.getElementById('modalSubject').textContent = msg.subject;
                    
                    // Set metadata
                    const metaHtml = `
                        <div class="modal-meta-item">
                            <span class="material-icons">person</span>
                            <span class="modal-meta-label">From:</span>
                            ${escapeHtml(msg.sender_name || msg.sender_email)}
                        </div>
                        <div class="modal-meta-item">
                            <span class="material-icons">schedule</span>
                            <span class="modal-meta-label">Date:</span>
                            ${formatDateLong(msg.received_date)}
                        </div>
                    `;
                    document.getElementById('modalMeta').innerHTML = metaHtml;
                    
                    // Set star button state
                    const starBtn = document.getElementById('modalStarBtn');
                    const starIcon = starBtn.querySelector('.material-icons');
                    if (msg.is_starred == 1) {
                        starIcon.textContent = 'star';
                        starIcon.style.color = 'var(--warning-orange)';
                    } else {
                        starIcon.textContent = 'star_border';
                        starIcon.style.color = '';
                    }
                    
                    // Parse attachments
                    let attachments = [];
                    if (msg.has_attachments && msg.attachment_data) {
                        try {
                            attachments = JSON.parse(msg.attachment_data);
                        } catch (e) {}
                    }
                    
                    // Set body
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
                                        <div class="attachment-card">
                                            <div class="attachment-icon">${att.icon || '📎'}</div>
                                            <div class="attachment-name">${escapeHtml(att.filename)}</div>
                                            <div class="attachment-size">${formatBytes(att.size)}</div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        `;
                    }
                    
                    document.getElementById('modalBody').innerHTML = bodyHtml;
                    document.getElementById('messageModal').classList.add('active');
                    
                    // Refresh list to update read status
                    fetchMessages();
                }
            } catch (error) {
                console.error('Error viewing message:', error);
                showToast('Failed to load message', 'error');
            }
        }

        function closeModal() {
            document.getElementById('messageModal').classList.remove('active');
            currentMessageId = null;
        }

        function closeModalOnOverlay(event) {
            if (event.target.id === 'messageModal') {
                closeModal();
            }
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

        async function toggleStarFromModal() {
            if (!currentMessageId) return;
            
            await toggleStar(currentMessageId);
            
            // Refresh modal
            setTimeout(() => {
                viewMessage(currentMessageId);
            }, 300);
        }

        async function deleteMessageFromModal() {
            if (!currentMessageId) return;
            
            if (!confirm('Are you sure you want to delete this message?')) {
                return;
            }
            
            try {
                const response = await fetch(`inbox.php?action=delete&id=${currentMessageId}`);
                const data = await response.json();
                
                if (data.success) {
                    showToast('Message deleted', 'success');
                    closeModal();
                    fetchMessages();
                } else {
                    showToast('Failed to delete message', 'error');
                }
            } catch (error) {
                console.error('Error deleting message:', error);
                showToast('Failed to delete message', 'error');
            }
        }

        async function updateCounts() {
            try {
                const response = await fetch('inbox.php?action=get_counts');
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('unreadCount').textContent = data.unread;
                    document.getElementById('newCount').textContent = data.new;
                    document.getElementById('sidebarUnreadBadge').textContent = data.unread;
                    
                    // Hide badge if 0
                    if (data.unread == 0) {
                        document.getElementById('sidebarUnreadBadge').style.display = 'none';
                    } else {
                        document.getElementById('sidebarUnreadBadge').style.display = 'block';
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
            // Remove existing toasts
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

        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        }

        // Auto-update counts every 30 seconds
        setInterval(updateCounts, 30000);

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // ESC to close modal
            if (e.key === 'Escape' && document.getElementById('messageModal').classList.contains('active')) {
                closeModal();
            }
            
            // Ctrl/Cmd + R to sync
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                syncMessages();
            }
        });
    </script>
</body>
</html>