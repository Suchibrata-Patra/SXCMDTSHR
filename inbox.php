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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inbox - Messages</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --color-total: rgb(229, 56, 81);
            --color-total-bg: rgba(229, 56, 81, 0.1);
            --color-unread: rgb(144, 236, 64);
            --color-unread-bg: rgba(144, 236, 64, 0.1);
            --color-new: rgb(20, 121, 246);
            --color-new-bg: rgba(20, 121, 246, 0.1);
            --highlight-yellow: #FFE58F;
            --bg-primary: #FFFFFF;
            --bg-secondary: #F8F9FA;
            --text-primary: #1A1A1A;
            --text-secondary: #6C757D;
            --text-tertiary: #ADB5BD;
            --border-color: #E9ECEF;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.12);
            --transition-smooth: cubic-bezier(0.4, 0.0, 0.2, 1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: var(--bg-secondary);
            color: var(--text-primary);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            overflow-x: hidden;
        }

        html {
            scroll-behavior: smooth;
        }

        /* Smooth scrolling momentum */
        body, .messages-area {
            -webkit-overflow-scrolling: touch;
            scroll-behavior: smooth;
        }

        /* Main Container */
        .app-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px;
            min-height: 100vh;
        }

        /* Header Section */
        .header {
            background: var(--bg-primary);
            border-radius: 20px;
            padding: 32px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
            transition: all 0.4s var(--transition-smooth);
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--color-total) 0%, var(--color-new) 50%, var(--color-unread) 100%);
            opacity: 0.8;
        }

        .header:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .header-title h1 {
            font-size: 32px;
            font-weight: 700;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, var(--color-total), var(--color-new));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .inbox-badge {
            background: var(--color-new-bg);
            color: var(--color-new);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s var(--transition-smooth);
        }

        .inbox-badge:hover {
            background: var(--color-new);
            color: white;
            transform: scale(1.05);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .stat-card {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.35s var(--transition-smooth);
            position: relative;
            overflow: hidden;
            border: 2px solid transparent;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, transparent 0%, rgba(255, 255, 255, 0.1) 100%);
            opacity: 0;
            transition: opacity 0.3s var(--transition-smooth);
        }

        .stat-card:hover {
            transform: translateY(-4px) scale(1.02);
            box-shadow: var(--shadow-md);
        }

        .stat-card:hover::before {
            opacity: 1;
        }

        .stat-card.total {
            border-color: var(--color-total-bg);
        }

        .stat-card.total:hover {
            border-color: var(--color-total);
            background: var(--color-total-bg);
        }

        .stat-card.unread {
            border-color: var(--color-unread-bg);
        }

        .stat-card.unread:hover {
            border-color: var(--color-unread);
            background: var(--color-unread-bg);
        }

        .stat-card.new {
            border-color: var(--color-new-bg);
        }

        .stat-card.new:hover {
            border-color: var(--color-new);
            background: var(--color-new-bg);
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            transition: all 0.3s var(--transition-smooth);
            flex-shrink: 0;
        }

        .stat-icon.total {
            background: var(--color-total-bg);
            color: var(--color-total);
        }

        .stat-icon.unread {
            background: var(--color-unread-bg);
            color: var(--color-unread);
        }

        .stat-icon.new {
            background: var(--color-new-bg);
            color: var(--color-new);
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .stat-info {
            flex: 1;
        }

        .stat-label {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1;
        }

        /* Search Section */
        .search-section {
            margin-bottom: 24px;
        }

        .search-container {
            background: var(--bg-primary);
            border-radius: 20px;
            padding: 24px;
            box-shadow: var(--shadow-sm);
            transition: all 0.4s var(--transition-smooth);
        }

        .search-container:focus-within {
            box-shadow: var(--shadow-md), 0 0 0 4px var(--color-new-bg);
            transform: translateY(-2px);
        }

        .search-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .search-icon-wrapper {
            position: absolute;
            left: 20px;
            color: var(--text-tertiary);
            transition: all 0.3s var(--transition-smooth);
            pointer-events: none;
        }

        .search-container:focus-within .search-icon-wrapper {
            color: var(--color-new);
            transform: scale(1.1);
        }

        #searchInput {
            flex: 1;
            padding: 18px 24px 18px 56px;
            border: 2px solid var(--border-color);
            border-radius: 16px;
            font-size: 16px;
            font-weight: 500;
            background: var(--bg-secondary);
            color: var(--text-primary);
            transition: all 0.3s var(--transition-smooth);
            outline: none;
        }

        #searchInput:focus {
            border-color: var(--color-new);
            background: var(--bg-primary);
            box-shadow: 0 0 0 3px var(--color-new-bg);
        }

        #searchInput::placeholder {
            color: var(--text-tertiary);
        }

        .clear-btn {
            position: absolute;
            right: 20px;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: none;
            background: var(--text-tertiary);
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transform: scale(0);
            transition: all 0.3s var(--transition-smooth);
        }

        .clear-btn.visible {
            opacity: 1;
            transform: scale(1);
        }

        .clear-btn:hover {
            background: var(--color-total);
            transform: scale(1.1);
        }

        .clear-btn:active {
            transform: scale(0.95);
        }

        .search-results {
            margin-top: 16px;
            padding: 12px 20px;
            background: var(--color-new-bg);
            border-radius: 12px;
            color: var(--color-new);
            font-size: 14px;
            font-weight: 600;
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.3s var(--transition-smooth);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .search-results.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Messages Section */
        .messages-section {
            background: var(--bg-primary);
            border-radius: 20px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: all 0.4s var(--transition-smooth);
        }

        .messages-section:hover {
            box-shadow: var(--shadow-md);
        }

        .messages-area {
            max-height: calc(100vh - 420px);
            overflow-y: auto;
            scroll-behavior: smooth;
            will-change: scroll-position;
        }

        /* Custom Scrollbar */
        .messages-area::-webkit-scrollbar {
            width: 10px;
        }

        .messages-area::-webkit-scrollbar-track {
            background: var(--bg-secondary);
        }

        .messages-area::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, var(--color-new), var(--color-total));
            border-radius: 10px;
            transition: all 0.3s var(--transition-smooth);
        }

        .messages-area::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, var(--color-total), var(--color-unread));
        }

        /* Message Item */
        .message-item {
            padding: 24px 28px;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: all 0.3s var(--transition-smooth);
            will-change: transform;
            contain: layout style paint;
        }

        .message-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, var(--color-new), var(--color-total));
            transform: scaleY(0);
            transition: transform 0.3s var(--transition-smooth);
        }

        .message-item:hover::before {
            transform: scaleY(1);
        }

        .message-item:hover {
            background: linear-gradient(90deg, var(--bg-secondary) 0%, transparent 100%);
            transform: translateX(8px);
        }

        .message-item:active {
            transform: translateX(8px) scale(0.99);
        }

        .message-item:last-child {
            border-bottom: none;
        }

        .message-item.hidden {
            display: none;
        }

        /* Unread Messages */
        .message-item.unread {
            background: linear-gradient(90deg, var(--color-unread-bg) 0%, transparent 100%);
        }

        .message-item.unread::after {
            content: '';
            position: absolute;
            left: 28px;
            top: 50%;
            transform: translateY(-50%);
            width: 10px;
            height: 10px;
            background: var(--color-unread);
            border-radius: 50%;
            box-shadow: 0 0 0 3px var(--color-unread-bg);
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
                transform: translateY(-50%) scale(1);
            }
            50% {
                opacity: 0.6;
                transform: translateY(-50%) scale(1.2);
            }
        }

        .message-item.unread .message-content {
            padding-left: 28px;
        }

        /* Message Header */
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
            gap: 16px;
        }

        .sender {
            font-size: 17px;
            font-weight: 700;
            color: var(--text-primary);
            transition: all 0.3s var(--transition-smooth);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message-item:hover .sender {
            color: var(--color-new);
        }

        .priority-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .priority-badge.high {
            background: var(--color-total-bg);
            color: var(--color-total);
        }

        .priority-badge.medium {
            background: var(--color-new-bg);
            color: var(--color-new);
        }

        .time {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-tertiary);
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .subject {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
            line-height: 1.4;
            transition: all 0.3s var(--transition-smooth);
        }

        .message-item:hover .subject {
            color: var(--color-total);
        }

        .preview {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.6;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Highlight */
        .highlight {
            background: var(--highlight-yellow);
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 600;
            color: var(--text-primary);
            box-shadow: 0 2px 4px rgba(255, 229, 143, 0.3);
            animation: highlightFade 0.5s ease-out;
        }

        @keyframes highlightFade {
            0% {
                background: #FFD700;
                transform: scale(1.1);
            }
            100% {
                background: var(--highlight-yellow);
                transform: scale(1);
            }
        }

        /* Empty State */
        .empty-state {
            display: none;
            text-align: center;
            padding: 80px 40px;
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.5s var(--transition-smooth);
        }

        .empty-state.visible {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }

        .empty-icon {
            font-size: 80px;
            margin-bottom: 24px;
            opacity: 0.3;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-10px);
            }
        }

        .empty-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 12px;
        }

        .empty-text {
            font-size: 16px;
            color: var(--text-secondary);
        }

        /* Loading Animation */
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message-item {
            animation: slideInUp 0.5s var(--transition-smooth) backwards;
        }

        .message-item:nth-child(1) { animation-delay: 0.05s; }
        .message-item:nth-child(2) { animation-delay: 0.1s; }
        .message-item:nth-child(3) { animation-delay: 0.15s; }
        .message-item:nth-child(4) { animation-delay: 0.2s; }
        .message-item:nth-child(5) { animation-delay: 0.25s; }
        .message-item:nth-child(6) { animation-delay: 0.3s; }
        .message-item:nth-child(7) { animation-delay: 0.35s; }
        .message-item:nth-child(8) { animation-delay: 0.4s; }
        .message-item:nth-child(9) { animation-delay: 0.45s; }
        .message-item:nth-child(10) { animation-delay: 0.5s; }

        /* Responsive Design */
        @media (max-width: 768px) {
            .app-container {
                padding: 16px;
            }

            .header {
                padding: 24px;
            }

            .header-title h1 {
                font-size: 26px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .message-item {
                padding: 20px;
            }

            .messages-area {
                max-height: calc(100vh - 500px);
            }

            .message-item:hover {
                transform: translateX(4px);
            }
        }

        /* Utility Classes */
        .fade-in {
            animation: fadeIn 0.6s var(--transition-smooth);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Header -->
        <div class="header fade-in">
            <div class="header-top">
                <div class="header-title">
                    <h1>📨 Inbox</h1>
                    <div class="inbox-badge" id="totalBadge">0 Messages</div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-icon total">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                        </svg>
                    </div>
                    <div class="stat-info">
                        <div class="stat-label">Total Messages</div>
                        <div class="stat-value" id="totalCount">0</div>
                    </div>
                </div>

                <div class="stat-card unread">
                    <div class="stat-icon unread">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M12 6v6l4 2"/>
                        </svg>
                    </div>
                    <div class="stat-info">
                        <div class="stat-label">Unread</div>
                        <div class="stat-value" id="unreadCount">0</div>
                    </div>
                </div>

                <div class="stat-card new">
                    <div class="stat-icon new">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>
                        </svg>
                    </div>
                    <div class="stat-info">
                        <div class="stat-label">New Today</div>
                        <div class="stat-value" id="newCount">0</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search Section -->
        <div class="search-section fade-in">
            <div class="search-container">
                <div class="search-wrapper">
                    <div class="search-icon-wrapper">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <circle cx="11" cy="11" r="8"/>
                            <path d="m21 21-4.35-4.35"/>
                        </svg>
                    </div>
                    <input 
                        type="text" 
                        id="searchInput" 
                        placeholder="Search by sender, subject, or content..."
                        autocomplete="off"
                    >
                    <button class="clear-btn" id="clearBtn">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </div>
                <div class="search-results" id="searchResults">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    <span id="resultsText">Found 0 messages</span>
                </div>
            </div>
        </div>

        <!-- Messages Section -->
        <div class="messages-section fade-in">
            <div class="messages-area" id="messagesArea">
                <!-- Message Items -->
                <div class="message-item unread" 
                     data-sender="Sarah Johnson" 
                     data-subject="Q4 Marketing Strategy - Urgent Review Needed" 
                     data-preview="Hi team, I need everyone to review the attached Q4 marketing strategy document before our meeting tomorrow. There are some critical changes to our campaign approach that we need to discuss. Please pay special attention to the budget allocations and timeline adjustments.">
                    <div class="message-content">
                        <div class="message-header">
                            <div class="sender">
                                Sarah Johnson
                                <span class="priority-badge high">High Priority</span>
                            </div>
                            <div class="time">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                                2m ago
                            </div>
                        </div>
                        <div class="subject">Q4 Marketing Strategy - Urgent Review Needed</div>
                        <div class="preview">Hi team, I need everyone to review the attached Q4 marketing strategy document before our meeting tomorrow. There are some critical changes to our campaign approach that we need to discuss...</div>
                    </div>
                </div>

                <div class="message-item unread" 
                     data-sender="Mike Chen" 
                     data-subject="Project Timeline Update - Development Phase" 
                     data-preview="The development timeline has been adjusted based on the client feedback we received yesterday. The new schedule extends the testing phase by two weeks to ensure comprehensive quality assurance. I've updated the project board with all the changes.">
                    <div class="message-content">
                        <div class="message-header">
                            <div class="sender">
                                Mike Chen
                                <span class="priority-badge medium">Medium</span>
                            </div>
                            <div class="time">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                                18m ago
                            </div>
                        </div>
                        <div class="subject">Project Timeline Update - Development Phase</div>
                        <div class="preview">The development timeline has been adjusted based on the client feedback we received yesterday. The new schedule extends the testing phase by two weeks to ensure comprehensive quality assurance...</div>
                    </div>
                </div>

                <div class="message-item" 
                     data-sender="Emma Davis" 
                     data-subject="Team Meeting Notes - January 15th" 
                     data-preview="Here are the comprehensive notes from today's team meeting. Key takeaways include the decision to move forward with the redesign project, hiring two new developers, and reorganizing our sprint planning process. Action items have been assigned to respective team members.">
                    <div class="message-content">
                        <div class="message-header">
                            <div class="sender">Emma Davis</div>
                            <div class="time">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                                1h ago
                            </div>
                        </div>
                        <div class="subject">Team Meeting Notes - January 15th</div>
                        <div class="preview">Here are the comprehensive notes from today's team meeting. Key takeaways include the decision to move forward with the redesign project, hiring two new developers, and reorganizing our sprint planning process...</div>
                    </div>
                </div>

                <div class="message-item" 
                     data-sender="Alex Rodriguez" 
                     data-subject="Client Feedback Summary - Prototype v2.3" 
                     data-preview="Our client has provided detailed feedback on the latest prototype version 2.3. Overall, the response has been very positive with particular praise for the new navigation system and improved user interface. However, they've requested some modifications to the checkout flow.">
                    <div class="message-content">
                        <div class="message-header">
                            <div class="sender">
                                Alex Rodriguez
                                <span class="priority-badge high">High Priority</span>
                            </div>
                            <div class="time">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                                3h ago
                            </div>
                        </div>
                        <div class="subject">Client Feedback Summary - Prototype v2.3</div>
                        <div class="preview">Our client has provided detailed feedback on the latest prototype version 2.3. Overall, the response has been very positive with particular praise for the new navigation system and improved user interface...</div>
                    </div>
                </div>

                <div class="message-item" 
                     data-sender="Jessica Lee" 
                     data-subject="Budget Approval Request - Marketing Campaign" 
                     data-preview="I'm requesting approval for additional resources needed for the upcoming digital marketing campaign. The proposed budget increase of $15,000 will cover enhanced social media advertising, influencer partnerships, and content creation. Detailed breakdown attached.">
                    <div class="message-content">
                        <div class="message-header">
                            <div class="sender">Jessica Lee</div>
                            <div class="time">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                                Yesterday
                            </div>
                        </div>
                        <div class="subject">Budget Approval Request - Marketing Campaign</div>
                        <div class="preview">I'm requesting approval for additional resources needed for the upcoming digital marketing campaign. The proposed budget increase of $15,000 will cover enhanced social media advertising...</div>
                    </div>
                </div>

                <div class="message-item" 
                     data-sender="David Park" 
                     data-subject="Code Review Complete - Feature Branch Authentication" 
                     data-preview="I've completed the comprehensive review of your latest commit on the authentication feature branch. Excellent work on the optimization improvements! The code is clean, well-documented, and follows our style guidelines. I've left a few minor suggestions for improvement.">
                    <div class="message-content">
                        <div class="message-header">
                            <div class="sender">David Park</div>
                            <div class="time">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                                Yesterday
                            </div>
                        </div>
                        <div class="subject">Code Review Complete - Feature Branch Authentication</div>
                        <div class="preview">I've completed the comprehensive review of your latest commit on the authentication feature branch. Excellent work on the optimization improvements! The code is clean, well-documented...</div>
                    </div>
                </div>

                <div class="message-item" 
                     data-sender="Rachel Kim" 
                     data-subject="User Research Findings - Navigation Study" 
                     data-preview="The recent user interviews and usability testing sessions have revealed some fascinating insights about our navigation patterns. Users are having difficulty finding the account settings, and the search functionality needs to be more prominent. Full report attached with recommendations.">
                    <div class="message-content">
                        <div class="message-header">
                            <div class="sender">
                                Rachel Kim
                                <span class="priority-badge medium">Medium</span>
                            </div>
                            <div class="time">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                                2 days ago
                            </div>
                        </div>
                        <div class="subject">User Research Findings - Navigation Study</div>
                        <div class="preview">The recent user interviews and usability testing sessions have revealed some fascinating insights about our navigation patterns. Users are having difficulty finding the account settings...</div>
                    </div>
                </div>

                <div class="message-item" 
                     data-sender="Tom Wilson" 
                     data-subject="Weekly Status Report - Development Team" 
                     data-preview="This week's accomplishments include completing the authentication module, fixing 23 bugs, implementing the new dashboard design, and conducting performance optimization. Next week we'll focus on integrating the payment gateway and starting the mobile app development.">
                    <div class="message-content">
                        <div class="message-header">
                            <div class="sender">Tom Wilson</div>
                            <div class="time">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                                3 days ago
                            </div>
                        </div>
                        <div class="subject">Weekly Status Report - Development Team</div>
                        <div class="preview">This week's accomplishments include completing the authentication module, fixing 23 bugs, implementing the new dashboard design, and conducting performance optimization...</div>
                    </div>
                </div>

                <div class="message-item" 
                     data-sender="Lisa Anderson" 
                     data-subject="Product Launch Preparation Checklist" 
                     data-preview="As we approach the product launch date, I've compiled a comprehensive checklist of all remaining tasks. We need to finalize the marketing materials, complete QA testing, prepare customer support documentation, and conduct final stakeholder reviews.">
                    <div class="message-content">
                        <div class="message-header">
                            <div class="sender">
                                Lisa Anderson
                                <span class="priority-badge high">High Priority</span>
                            </div>
                            <div class="time">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                                4 days ago
                            </div>
                        </div>
                        <div class="subject">Product Launch Preparation Checklist</div>
                        <div class="preview">As we approach the product launch date, I've compiled a comprehensive checklist of all remaining tasks. We need to finalize the marketing materials, complete QA testing...</div>
                    </div>
                </div>

                <div class="message-item" 
                     data-sender="Chris Martinez" 
                     data-subject="Security Audit Results and Recommendations" 
                     data-preview="The security audit has been completed and I'm pleased to report that our application passed with a strong security rating. However, there are a few recommended improvements including updating dependencies, implementing rate limiting, and enhancing password policies.">
                    <div class="message-content">
                        <div class="message-header">
                            <div class="sender">Chris Martinez</div>
                            <div class="time">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                                5 days ago
                            </div>
                        </div>
                        <div class="subject">Security Audit Results and Recommendations</div>
                        <div class="preview">The security audit has been completed and I'm pleased to report that our application passed with a strong security rating. However, there are a few recommended improvements...</div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div class="empty-state" id="emptyState">
                <div class="empty-icon">🔍</div>
                <div class="empty-title">No Messages Found</div>
                <div class="empty-text">Try adjusting your search query</div>
            </div>
        </div>
    </div>

    <script>
        // DOM Elements
        const searchInput = document.getElementById('searchInput');
        const clearBtn = document.getElementById('clearBtn');
        const messagesArea = document.getElementById('messagesArea');
        const emptyState = document.getElementById('emptyState');
        const searchResults = document.getElementById('searchResults');
        const resultsText = document.getElementById('resultsText');
        const totalCount = document.getElementById('totalCount');
        const unreadCount = document.getElementById('unreadCount');
        const newCount = document.getElementById('newCount');
        const totalBadge = document.getElementById('totalBadge');

        // Get all message items
        const messages = Array.from(document.querySelectorAll('.message-item'));
        
        // Store original HTML for each message
        const originalHTML = new Map();
        messages.forEach(msg => {
            originalHTML.set(msg, msg.innerHTML);
        });

        // Initialize counts
        function updateCounts() {
            const total = messages.length;
            const unread = messages.filter(m => m.classList.contains('unread')).length;
            const newToday = Math.min(unread, 3); // Simulate new messages
            
            totalCount.textContent = total;
            unreadCount.textContent = unread;
            newCount.textContent = newToday;
            totalBadge.textContent = `${total} Message${total !== 1 ? 's' : ''}`;
        }

        updateCounts();

        // Debounce function for smooth performance
        function debounce(func, delay) {
            let timeoutId;
            return function (...args) {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => func.apply(this, args), delay);
            };
        }

        // Escape special regex characters
        function escapeRegex(str) {
            return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }

        // Highlight matching text
        function highlightText(text, query) {
            if (!query) return text;
            const regex = new RegExp(`(${escapeRegex(query)})`, 'gi');
            return text.replace(regex, '<span class="highlight">$1</span>');
        }

        // Main search function
        function performSearch(query) {
            query = query.trim().toLowerCase();
            let visibleCount = 0;

            if (!query) {
                // Reset to original state
                messages.forEach(msg => {
                    msg.innerHTML = originalHTML.get(msg);
                    msg.classList.remove('hidden');
                    visibleCount++;
                });
                
                searchResults.classList.remove('visible');
                emptyState.classList.remove('visible');
                messagesArea.style.display = 'block';
            } else {
                // Search through messages
                messages.forEach(msg => {
                    const sender = (msg.dataset.sender || '').toLowerCase();
                    const subject = (msg.dataset.subject || '').toLowerCase();
                    const preview = (msg.dataset.preview || '').toLowerCase();
                    
                    const isMatch = sender.includes(query) || 
                                  subject.includes(query) || 
                                  preview.includes(query);

                    if (isMatch) {
                        // Restore original HTML first
                        msg.innerHTML = originalHTML.get(msg);
                        
                        // Apply highlighting
                        const senderEl = msg.querySelector('.sender');
                        const subjectEl = msg.querySelector('.subject');
                        const previewEl = msg.querySelector('.preview');
                        
                        if (sender.includes(query) && senderEl) {
                            const originalSender = senderEl.innerHTML;
                            const textOnly = msg.dataset.sender;
                            senderEl.innerHTML = originalSender.replace(
                                textOnly,
                                highlightText(textOnly, query)
                            );
                        }
                        
                        if (subject.includes(query) && subjectEl) {
                            subjectEl.innerHTML = highlightText(msg.dataset.subject, query);
                        }
                        
                        if (preview.includes(query) && previewEl) {
                            previewEl.innerHTML = highlightText(msg.dataset.preview, query);
                        }
                        
                        msg.classList.remove('hidden');
                        visibleCount++;
                    } else {
                        msg.classList.add('hidden');
                    }
                });

                // Update search results info
                resultsText.textContent = `Found ${visibleCount} message${visibleCount !== 1 ? 's' : ''}`;
                searchResults.classList.add('visible');

                // Show/hide empty state
                if (visibleCount === 0) {
                    messagesArea.style.display = 'none';
                    emptyState.classList.add('visible');
                } else {
                    messagesArea.style.display = 'block';
                    emptyState.classList.remove('visible');
                }
            }

            // Smooth scroll to top
            messagesArea.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }

        // Debounced search
        const debouncedSearch = debounce(performSearch, 200);

        // Event Listeners
        searchInput.addEventListener('input', (e) => {
            const value = e.target.value;
            
            // Show/hide clear button
            clearBtn.classList.toggle('visible', value.length > 0);
            
            // Perform search
            debouncedSearch(value);
        });

        // Clear button
        clearBtn.addEventListener('click', () => {
            searchInput.value = '';
            clearBtn.classList.remove('visible');
            performSearch('');
            searchInput.focus();
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Cmd/Ctrl + F to focus search
            if ((e.metaKey || e.ctrlKey) && e.key === 'f') {
                e.preventDefault();
                searchInput.focus();
                searchInput.select();
            }
            
            // Escape to clear search
            if (e.key === 'Escape' && document.activeElement === searchInput) {
                if (searchInput.value) {
                    searchInput.value = '';
                    clearBtn.classList.remove('visible');
                    performSearch('');
                } else {
                    searchInput.blur();
                }
            }
        });

        // Message click handlers
        messages.forEach(msg => {
            msg.addEventListener('click', function(e) {
                // Don't trigger if clicking on badges or other interactive elements
                if (!e.target.closest('.priority-badge')) {
                    console.log('Opening message:', this.dataset.subject);
                    // Add your message open logic here
                    
                    // Visual feedback
                    this.style.transform = 'translateX(8px) scale(0.98)';
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 200);
                }
            });
        });

        // Smooth scroll optimization
        let scrollTimeout;
        messagesArea.addEventListener('scroll', () => {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(() => {
                // Scroll has stopped
            }, 100);
        }, { passive: true });

        // Performance: Intersection Observer for lazy animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '50px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Initial animation trigger
        window.addEventListener('load', () => {
            document.querySelectorAll('.fade-in').forEach(el => {
                el.style.opacity = '1';
            });
        });
    </script>
</body>
</html>