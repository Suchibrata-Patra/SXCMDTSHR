<?php
/**
 * Deleted Items Page - Minimalist UI matching drive.php aesthetic
 * Shows deleted inbox messages with preview
 */
session_start();

// Security check
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    header("Location: login.php");
    exit();
}

require_once 'config.php';
require_once 'db_config.php';
require_once 'inbox_functions.php';

$userEmail = $_SESSION['smtp_user'];

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'get_deleted_messages':
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            
            $filters = ['search' => $_GET['search'] ?? ''];
            
            $messages = getDeletedMessages($userEmail, $limit, $offset, $filters);
            $total = getDeletedMessageCount($userEmail, $filters);
            
            echo json_encode([
                'success' => true,
                'messages' => $messages,
                'total' => $total
            ]);
            exit();
            
        case 'get_message':
            $messageId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            
            try {
                $pdo = getDatabaseConnection();
                if (!$pdo) {
                    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
                    exit();
                }
                
                $stmt = $pdo->prepare("
                    SELECT * FROM inbox_messages 
                    WHERE id = :id AND user_email = :email AND is_deleted = 1
                ");
                
                $stmt->execute([
                    ':id' => $messageId,
                    ':email' => $userEmail
                ]);
                
                $message = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($message) {
                    echo json_encode(['success' => true, 'message' => $message]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Message not found or not deleted']);
                }
            } catch (PDOException $e) {
                error_log("Error getting message: " . $e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
            }
            exit();
            
        case 'restore':
            $messageId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            $success = restoreInboxMessage($messageId, $userEmail);
            echo json_encode(['success' => $success]);
            exit();
            
        case 'permanent_delete':
            $messageId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            $success = permanentDeleteMessage($messageId, $userEmail);
            echo json_encode(['success' => $success]);
            exit();
    }
}

/**
 * Get deleted messages
 */
function getDeletedMessages($userEmail, $limit = 50, $offset = 0, $filters = []) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return [];
        
        $sql = "SELECT 
                    id, message_id, sender_email, sender_name, 
                    subject, body_preview, received_date, deleted_at,
                    has_attachments, attachment_data
                FROM inbox_messages 
                WHERE user_email = :email 
                AND is_deleted = 1";
        
        $params = [':email' => $userEmail];
        
        // Apply search filter
        if (!empty($filters['search'])) {
            $sql .= " AND (subject LIKE :search OR sender_email LIKE :search OR body_preview LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        $sql .= " ORDER BY deleted_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error getting deleted messages: " . $e->getMessage());
        return [];
    }
}

/**
 * Get deleted message count
 */
function getDeletedMessageCount($userEmail, $filters = []) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return 0;
        
        $sql = "SELECT COUNT(*) as count 
                FROM inbox_messages 
                WHERE user_email = :email 
                AND is_deleted = 1";
        
        $params = [':email' => $userEmail];
        
        if (!empty($filters['search'])) {
            $sql .= " AND (subject LIKE :search OR sender_email LIKE :search OR body_preview LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
        
    } catch (PDOException $e) {
        error_log("Error getting deleted count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Restore deleted message
 */
function restoreInboxMessage($messageId, $userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;
        
        $stmt = $pdo->prepare("
            UPDATE inbox_messages 
            SET is_deleted = 0, deleted_at = NULL 
            WHERE id = :id AND user_email = :email AND is_deleted = 1
        ");
        
        return $stmt->execute([
            ':id' => $messageId,
            ':email' => $userEmail
        ]);
        
    } catch (PDOException $e) {
        error_log("Error restoring message: " . $e->getMessage());
        return false;
    }
}

/**
 * Permanently delete message
 */
function permanentDeleteMessage($messageId, $userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;
        
        $stmt = $pdo->prepare("
            DELETE FROM inbox_messages 
            WHERE id = :id AND user_email = :email AND is_deleted = 1
        ");
        
        return $stmt->execute([
            ':id' => $messageId,
            ':email' => $userEmail
        ]);
        
    } catch (PDOException $e) {
        error_log("Error permanently deleting message: " . $e->getMessage());
        return false;
    }
}

// Initial data load
$messages = getDeletedMessages($userEmail, 50, 0) ?? [];
$totalCount = getDeletedMessageCount($userEmail) ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php define('PAGE_TITLE', 'Deleted Items | SXC MDTS'); include 'header.php'; ?>
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
        .topbar-title .material-icons-round { font-size:20px; color:var(--ink-3); }
        .topbar-spacer { flex:1; }
        
        .count-badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 9px;
            background: var(--surface-2);
            color: var(--ink-3);
            border: 1px solid var(--border);
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            font-family: 'DM Mono', monospace;
        }

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
            background: var(--surface);
            color: var(--ink-2);
            border: 1.5px solid var(--border-2);
            border-radius: 8px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all .18s;
            text-decoration: none;
        }
        .btn:hover {
            border-color: var(--blue);
            color: var(--blue);
            background: var(--blue-glow);
        }
        .btn .material-icons-round { font-size:16px; }

        .btn-primary {
            background: var(--blue);
            color: white;
            border-color: var(--blue);
        }
        .btn-primary:hover {
            background: var(--blue-2);
            box-shadow: 0 4px 12px var(--blue-glow);
        }

        /* ── CONTENT AREA ─────────────────────────────────────── */
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

        .panel-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
        }

        .panel-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--ink-2);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .panel-subtitle {
            font-size: 12px;
            color: var(--ink-3);
            margin-top: 4px;
        }

        .messages-list {
            flex: 1;
            overflow-y: auto;
        }

        .messages-list::-webkit-scrollbar { width:5px; }
        .messages-list::-webkit-scrollbar-track { background:transparent; }
        .messages-list::-webkit-scrollbar-thumb { background:var(--border-2); border-radius:10px; }

        /* Message Item */
        .message-item {
            padding: 14px 24px;
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

        .message-item.active {
            background: rgba(79,70,229,.05);
            border-left: 2px solid var(--blue);
        }

        .message-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: var(--surface-2);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .message-icon .material-icons-round {
            font-size: 18px;
            color: var(--ink-3);
        }

        .message-info {
            flex: 1;
            min-width: 0;
        }

        .message-sender {
            font-size: 13px;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .message-subject {
            font-size: 12px;
            color: var(--ink-2);
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .message-preview {
            font-size: 11px;
            color: var(--ink-3);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .message-meta {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 6px;
        }

        .message-date {
            font-size: 10px;
            color: var(--ink-4);
            font-family: 'DM Mono', monospace;
        }

        .attachment-badge {
            display: inline-flex;
            align-items: center;
            gap: 2px;
            font-size: 10px;
            color: var(--ink-4);
        }

        .attachment-badge .material-icons-round {
            font-size: 12px;
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
            gap: 8px;
        }

        .icon-btn {
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

        .icon-btn:hover {
            border-color: var(--blue);
            color: var(--blue);
            background: var(--blue-glow);
        }

        .icon-btn .material-icons-round {
            font-size: 18px;
        }

        /* Message Detail */
        .message-detail {
            flex: 1;
            overflow-y: auto;
            padding: 24px;
        }

        .message-detail::-webkit-scrollbar { width:5px; }
        .message-detail::-webkit-scrollbar-track { background:transparent; }
        .message-detail::-webkit-scrollbar-thumb { background:var(--border-2); border-radius:10px; }

        .message-header {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            padding: 24px;
            margin-bottom: 20px;
        }

        .message-subject-large {
            font-size: 20px;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 16px;
            line-height: 1.4;
        }

        .message-from {
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

        .message-dates {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .date-row {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: var(--ink-3);
        }

        .date-row .material-icons-round {
            font-size: 14px;
            color: var(--ink-4);
        }

        .date-label {
            font-weight: 600;
            min-width: 70px;
        }

        .date-value {
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

        .attachment-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: var(--r);
            margin-bottom: 8px;
            transition: border-color .18s;
        }

        .attachment-item:hover {
            border-color: var(--blue);
        }

        .attachment-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: var(--surface);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .attachment-icon .material-icons-round {
            font-size: 18px;
            color: var(--blue);
        }

        .attachment-info {
            flex: 1;
        }

        .attachment-name {
            font-size: 12px;
            font-weight: 500;
            color: var(--ink);
        }

        .attachment-size {
            font-size: 11px;
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
        }

        /* Loading */
        .loading-spinner {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }

        .spin {
            animation: spin .8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Highlight */
        mark {
            background-color: #ce9ec2;
            color: var(--ink);
            padding: 2px 3px;
            border-radius: 2px;
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

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }

        .toast .material-icons-round {
            font-size: 18px;
            color: var(--blue);
        }

        .toast.error .material-icons-round {
            color: var(--red);
        }

        .toast-message {
            flex: 1;
            font-size: 13px;
            font-weight: 500;
            color: var(--ink);
        }

        /* Modal */
        .modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 999;
            background: rgba(20,20,40,.55);
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
        }

        .modal-backdrop.show {
            display: flex;
        }

        .modal {
            background: var(--surface);
            border-radius: var(--r-lg);
            box-shadow: var(--shadow-lg);
            min-width: 400px;
            max-width: 90vw;
            animation: modalIn .22s cubic-bezier(.34,1.56,.64,1);
        }

        @keyframes modalIn {
            from {
                opacity: 0;
                transform: scale(.92) translateY(8px);
            }
            to {
                opacity: 1;
                transform: none;
            }
        }

        .modal-header {
            padding: 20px 24px 16px;
            border-bottom: 1px solid var(--border);
        }

        .modal-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--ink);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal-body {
            padding: 20px 24px;
        }

        .modal-text {
            font-size: 13px;
            color: var(--ink-2);
            line-height: 1.6;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 10px;
            justify-content: flex-end;
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
                    <span class="material-icons-round">delete</span>
                    Deleted Items
                    <span class="count-badge" id="total-count"><?= number_format($totalCount) ?></span>
                </div>
                <div class="topbar-spacer"></div>
                <div class="search-wrap">
                    <span class="material-icons-round">search</span>
                    <input 
                        type="text" 
                        id="searchInput" 
                        placeholder="Search deleted messages..."
                        autocomplete="off"
                    >
                </div>
                <!-- <a href="inbox.php" class="btn">
                    <span class="material-icons-round">arrow_back</span>
                    Back to Inbox
                </a> -->
            </div>

            <!-- Content Wrapper -->
            <div class="content-wrapper">
                <!-- Messages List Panel -->
                <div class="messages-panel">
                    <!-- <div class="panel-header">
                        <div class="panel-title">
                            <span class="material-icons-round">inbox</span>
                            Messages
                        </div>
                        <div class="panel-subtitle">
                            <span id="deleted-count"><?= number_format($totalCount) ?></span> deleted item<?= $totalCount !== 1 ? 's' : '' ?>
                        </div>
                    </div> -->

                    <div class="messages-list" id="messages-container">
                        <?php if (empty($messages)): ?>
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <span class="material-icons-round">delete_outline</span>
                                </div>
                                <div class="empty-title">No deleted messages</div>
                                <div class="empty-text">Your trash is empty</div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($messages as $msg): ?>
                                <div class="message-item" data-id="<?= htmlspecialchars($msg['id']) ?>" onclick="loadMessage(<?= htmlspecialchars($msg['id']) ?>)">
                                    <div class="message-icon">
                                        <span class="material-icons-round">mail</span>
                                    </div>
                                    <div class="message-info">
                                        <div class="message-sender"><?= htmlspecialchars($msg['sender_name'] ?: $msg['sender_email']) ?></div>
                                        <div class="message-subject"><?= htmlspecialchars($msg['subject'] ?: '(No Subject)') ?></div>
                                        <div class="message-preview"><?= htmlspecialchars(substr($msg['body_preview'] ?? '', 0, 60)) ?></div>
                                        <div class="message-meta">
                                            <span class="message-date"><?= date('M j, Y', strtotime($msg['deleted_at'])) ?></span>
                                            <?php if ($msg['has_attachments']): ?>
                                                <span class="attachment-badge">
                                                    <span class="material-icons-round">attach_file</span>
                                                </span>
                                            <?php endif; ?>
                                        </div>
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
                            <button class="icon-btn" onclick="restoreMessage()" title="Restore">
                                <span class="material-icons-round">restore_from_trash</span>
                            </button>
                            <button class="icon-btn" onclick="confirmDelete()" title="Delete Permanently">
                                <span class="material-icons-round">delete_forever</span>
                            </button>
                        </div>
                    </div>

                    <div class="message-detail" id="message-detail">
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

    <!-- Delete Confirmation Modal -->
    <div class="modal-backdrop" id="deleteModal">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-title">
                    <span class="material-icons-round" style="color: var(--red);">warning</span>
                    Permanently Delete Message
                </div>
            </div>
            <div class="modal-body">
                <p class="modal-text">
                    Are you sure you want to permanently delete this message? This action cannot be undone.
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closeModal('deleteModal')">Cancel</button>
                <button class="btn btn-primary" onclick="permanentDelete()">Delete Forever</button>
            </div>
        </div>
    </div>

    <script>
        let selectedMessageId = null;
        let currentQuery = '';

        // Load message details
        function loadMessage(id) {
            selectedMessageId = id;
            
            // Update active state
            document.querySelectorAll('.message-item').forEach(item => {
                item.classList.remove('active');
            });
            document.querySelector(`[data-id="${id}"]`).classList.add('active');

            // Show loading
            const detailDiv = document.getElementById('message-detail');
            detailDiv.innerHTML = `
                <div class="loading-spinner">
                    <span class="material-icons-round spin" style="font-size: 32px; color: var(--ink-4);">autorenew</span>
                </div>
            `;

            // Fetch message
            fetch(`?action=get_message&id=${id}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.message) {
                        displayMessage(data.message);
                        document.getElementById('preview-actions').style.display = 'flex';
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
                            ${attachments.map(att => `
                                <div class="attachment-item">
                                    <div class="attachment-icon">
                                        <span class="material-icons-round">insert_drive_file</span>
                                    </div>
                                    <div class="attachment-info">
                                        <div class="attachment-name">${escapeHtml(att.name)}</div>
                                        <div class="attachment-size">${formatBytes(att.size)}</div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    `;
                } catch (e) {
                    console.error('Error parsing attachments:', e);
                }
            }

            detailDiv.innerHTML = `
                <div class="message-header">
                    <div class="message-subject-large">${escapeHtml(msg.subject || '(No Subject)')}</div>
                    
                    <div class="message-from">
                        <div class="sender-avatar">${initials}</div>
                        <div class="sender-info">
                            <div class="sender-name">${escapeHtml(senderName)}</div>
                            <div class="sender-email">${escapeHtml(msg.sender_email)}</div>
                        </div>
                    </div>

                    <div class="message-dates">
                        <div class="date-row">
                            <span class="material-icons-round">schedule</span>
                            <span class="date-label">Received:</span>
                            <span class="date-value">${formatDate(msg.received_date)}</span>
                        </div>
                        <div class="date-row">
                            <span class="material-icons-round">delete</span>
                            <span class="date-label">Deleted:</span>
                            <span class="date-value">${formatDate(msg.deleted_at)}</span>
                        </div>
                    </div>
                </div>

                <div class="message-body-wrap">
                    <div class="message-body">${escapeHtml(msg.body_preview || '')}</div>
                    ${attachmentsHtml}
                </div>
            `;

            // Store original content for highlighting
            detailDiv.dataset.originalContent = detailDiv.innerHTML;
            
            // Apply highlighting if search is active
            if (currentQuery) {
                highlightMessageView(currentQuery);
            }
        }

        // Restore message
        function restoreMessage() {
            if (!selectedMessageId) return;

            fetch(`?action=restore&id=${selectedMessageId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast('Message restored successfully', 'success');
                        
                        // Remove from list
                        const messageItem = document.querySelector(`[data-id="${selectedMessageId}"]`);
                        if (messageItem) {
                            messageItem.style.opacity = '0';
                            setTimeout(() => {
                                messageItem.remove();
                                updateCount();
                                clearPreview();
                            }, 200);
                        }
                    } else {
                        showToast('Failed to restore message', 'error');
                    }
                })
                .catch(err => {
                    showToast('Error restoring message', 'error');
                });
        }

        // Confirm permanent delete
        function confirmDelete() {
            openModal('deleteModal');
        }

        // Permanent delete
        function permanentDelete() {
            if (!selectedMessageId) return;

            closeModal('deleteModal');

            fetch(`?action=permanent_delete&id=${selectedMessageId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast('Message permanently deleted', 'success');
                        
                        // Remove from list
                        const messageItem = document.querySelector(`[data-id="${selectedMessageId}"]`);
                        if (messageItem) {
                            messageItem.style.opacity = '0';
                            setTimeout(() => {
                                messageItem.remove();
                                updateCount();
                                clearPreview();
                            }, 200);
                        }
                    } else {
                        showToast('Failed to delete message', 'error');
                    }
                })
                .catch(err => {
                    showToast('Error deleting message', 'error');
                });
        }

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

        // Search functionality
        const originalMessageContent = new Map();

        function storeOriginalMessages() {
            const messageItems = document.querySelectorAll('.message-item');
            messageItems.forEach(item => {
                const id = item.dataset.id;
                const sender = item.querySelector('.message-sender');
                const subject = item.querySelector('.message-subject');
                const preview = item.querySelector('.message-preview');
                
                if (sender && subject && preview) {
                    originalMessageContent.set(id, {
                        sender: sender.textContent,
                        subject: subject.textContent,
                        preview: preview.textContent
                    });
                }
            });
        }

        // Initialize on load
        if (document.querySelectorAll('.message-item').length > 0) {
            storeOriginalMessages();
        }

        // Search with highlighting
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const query = e.target.value.trim();
            currentQuery = query;
            performClientSearch(query);
        });

        function performClientSearch(query) {
            const messageItems = document.querySelectorAll('.message-item');
            
            if (!query) {
                // Show all messages and remove highlights
                messageItems.forEach(item => {
                    item.style.display = 'flex';
                    const id = item.dataset.id;
                    if (originalMessageContent.has(id)) {
                        const original = originalMessageContent.get(id);
                        const sender = item.querySelector('.message-sender');
                        const subject = item.querySelector('.message-subject');
                        const preview = item.querySelector('.message-preview');
                        
                        if (sender) sender.textContent = original.sender;
                        if (subject) subject.textContent = original.subject;
                        if (preview) preview.textContent = original.preview;
                    }
                });
                
                clearMessageViewHighlights();
                
                const emptyState = document.querySelector('.empty-state-search');
                if (emptyState) emptyState.remove();
                return;
            }
            
            let visibleCount = 0;
            
            messageItems.forEach(item => {
                const id = item.dataset.id;
                const original = originalMessageContent.get(id);
                
                if (!original) {
                    item.style.display = 'none';
                    return;
                }
                
                const senderMatch = original.sender.toLowerCase().includes(query.toLowerCase());
                const subjectMatch = original.subject.toLowerCase().includes(query.toLowerCase());
                const previewMatch = original.preview.toLowerCase().includes(query.toLowerCase());
                
                if (senderMatch || subjectMatch || previewMatch) {
                    item.style.display = 'flex';
                    visibleCount++;
                    
                    const sender = item.querySelector('.message-sender');
                    const subject = item.querySelector('.message-subject');
                    const preview = item.querySelector('.message-preview');
                    
                    if (sender) {
                        sender.innerHTML = senderMatch ? highlightText(original.sender, query) : original.sender;
                    }
                    if (subject) {
                        subject.innerHTML = subjectMatch ? highlightText(original.subject, query) : original.subject;
                    }
                    if (preview) {
                        preview.innerHTML = previewMatch ? highlightText(original.preview, query) : original.preview;
                    }
                } else {
                    item.style.display = 'none';
                }
            });
            
            // Show empty state if no results
            const messagesContainer = document.getElementById('messages-container');
            const existingEmpty = messagesContainer.querySelector('.empty-state-search');
            
            if (visibleCount === 0 && !existingEmpty) {
                const emptyState = document.createElement('div');
                emptyState.className = 'empty-state empty-state-search';
                emptyState.innerHTML = `
                    <div class="empty-icon">
                        <span class="material-icons-round">search_off</span>
                    </div>
                    <div class="empty-title">No messages found</div>
                    <div class="empty-text">Try a different search term</div>
                `;
                messagesContainer.appendChild(emptyState);
            } else if (visibleCount > 0 && existingEmpty) {
                existingEmpty.remove();
            }
            
            // Highlight in currently viewed message
            if (selectedMessageId) {
                highlightMessageView(query);
            }
        }

        function highlightText(text, query) {
            if (!query) return text;
            const regex = new RegExp(`(${escapeRegex(query)})`, 'gi');
            return text.replace(regex, '<mark>$1</mark>');
        }

        function escapeRegex(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }

        function highlightMessageView(query) {
            const messageDetail = document.getElementById('message-detail');
            if (!messageDetail || !messageDetail.dataset.originalContent) return;
            
            if (!query) {
                messageDetail.innerHTML = messageDetail.dataset.originalContent;
                return;
            }
            
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = messageDetail.dataset.originalContent;
            
            const walker = document.createTreeWalker(tempDiv, NodeFilter.SHOW_TEXT, null, false);
            const textNodes = [];
            while (walker.nextNode()) {
                textNodes.push(walker.currentNode);
            }
            
            textNodes.forEach(node => {
                if (node.textContent.toLowerCase().includes(query.toLowerCase())) {
                    const span = document.createElement('span');
                    span.innerHTML = highlightText(node.textContent, query);
                    node.parentNode.replaceChild(span, node);
                }
            });
            
            messageDetail.innerHTML = tempDiv.innerHTML;
        }

        function clearMessageViewHighlights() {
            const messageDetail = document.getElementById('message-detail');
            if (messageDetail && messageDetail.dataset.originalContent) {
                messageDetail.innerHTML = messageDetail.dataset.originalContent;
            }
        }

        // Update count
        function updateCount() {
            fetch('?action=get_deleted_messages&limit=1')
                .then(res => res.json())
                .then(data => {
                    const count = data.total || 0;
                    document.getElementById('total-count').textContent = count.toLocaleString();
                    document.getElementById('deleted-count').textContent = count.toLocaleString();
                    
                    if (count === 0 && document.querySelectorAll('.message-item').length === 0) {
                        document.getElementById('messages-container').innerHTML = `
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <span class="material-icons-round">delete_outline</span>
                                </div>
                                <div class="empty-title">No deleted messages</div>
                                <div class="empty-text">Your trash is empty</div>
                            </div>
                        `;
                    }
                });
        }

        // Modal functions
        function openModal(id) {
            document.getElementById(id).classList.add('show');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('show');
        }

        document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
            backdrop.addEventListener('click', (e) => {
                if (e.target === backdrop) {
                    closeModal(backdrop.id);
                }
            });
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-backdrop.show').forEach(modal => {
                    closeModal(modal.id);
                });
            }
        });

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
    </script>
</body>
</html>