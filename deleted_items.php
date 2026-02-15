<?php
/**
 * Deleted Items Page - Split Pane UI
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
            $message = getInboxMessageById($messageId, $userEmail);
            
            if ($message && $message['is_deleted'] == 1) {
                echo json_encode(['success' => true, 'message' => $message]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Message not found']);
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
    <?php
        define('PAGE_TITLE', 'Deleted Items | SXC MDTS');
        include 'header.php';
    ?>
    <style>
        :root {
            --apple-blue: #007AFF;
            --apple-gray: #8E8E93;
            --apple-light-gray: #C7C7CC;
            --apple-bg: #F2F2F7;
            --border: #E5E5EA;
            --danger-red: #FF3B30;
            --success-green: #34C759;
            --card-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
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
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-title .material-icons {
            color: var(--danger-red);
            font-size: 28px;
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
            text-decoration: none;
        }

        .btn .material-icons {
            font-size: 18px;
        }

        .btn-secondary {
            background: white;
            color: #1c1c1e;
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: var(--apple-bg);
            border-color: var(--apple-blue);
        }

        .btn-danger {
            background: var(--danger-red);
            color: white;
        }

        .btn-danger:hover {
            background: #D32F2F;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(255, 59, 48, 0.3);
        }

        .btn-success {
            background: var(--success-green);
            color: white;
        }

        .btn-success:hover {
            background: #2CA84B;
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
            background: rgba(255, 59, 48, 0.1);
            color: var(--danger-red);
        }

        .stat-content {
            display: flex;
            flex-direction: column;
        }

        .stat-value {
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

        /* ========== SPLIT PANE LAYOUT ========== */
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

        /* ========== MESSAGES LIST ========== */
        .messages-area {
            flex: 1;
            overflow-y: auto;
        }

        .message-item {
            padding: 12px 20px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            gap: 12px;
            align-items: start;
        }

        .message-item:hover {
            background: var(--apple-bg);
        }

        .message-item.selected {
            background: #E3F2FD;
            border-left: 3px solid var(--apple-blue);
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

        .deleted-badge {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 2px 6px;
            background: rgba(255, 59, 48, 0.1);
            color: var(--danger-red);
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .deleted-badge .material-icons {
            font-size: 12px;
        }

        .message-sender {
            font-size: 14px;
            font-weight: 600;
            color: #1c1c1e;
        }

        .message-subject {
            font-size: 13px;
            font-weight: 500;
            color: #1c1c1e;
            margin-bottom: 3px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .message-preview {
            font-size: 12px;
            color: var(--apple-gray);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .message-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 4px;
            flex-shrink: 0;
        }

        .message-date {
            font-size: 11px;
            color: var(--apple-gray);
            white-space: nowrap;
        }

        .attachment-indicator {
            color: var(--apple-gray);
            font-size: 16px;
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
            margin-bottom: 12px;
            line-height: 1.4;
        }

        .message-view-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
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
            gap: 8px;
        }

        .message-view-body {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
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

        /* ========== ATTACHMENTS ========== */
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

        /* ========== EMPTY STATE ========== */
        .empty-state {
            padding: 60px 20px;
            text-align: center;
        }

        .empty-icon {
            font-size: 64px;
            color: var(--apple-gray);
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
        }

        /* ========== TOAST ========== */
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
    </style>
</head>

<body>
    <?php include('sidebar.php'); ?>

    <div class="main-content">
        <!-- Header -->
        <div class="page-header">
            <div class="header-left">
                <h1 class="page-title">
                    <span class="material-icons">delete</span>
                    Deleted Items
                </h1>
                <p class="page-subtitle">
                    <span id="total-count"><?= number_format($totalCount) ?></span> deleted messages
                </p>
            </div>
            <div class="header-actions">
                <a href="inbox.php" class="btn btn-secondary">
                    <span class="material-icons">arrow_back</span>
                    Back to Inbox
                </a>
            </div>
        </div>

        <!-- Stats Bar -->
        <div class="stats-bar">
            <div class="stat-item">
                <div class="stat-icon">
                    <span class="material-icons">delete</span>
                </div>
                <div class="stat-content">
                    <div class="stat-value" id="deleted-count"><?= number_format($totalCount) ?></div>
                    <div class="stat-label">Deleted Messages</div>
                </div>
            </div>
        </div>

        <!-- Split Pane Content -->
        <div class="content-wrapper">
            <!-- Messages List Pane -->
            <div class="messages-pane">
                <!-- Toolbar -->
                <div class="toolbar">
                    <div class="search-box">
                        <span class="material-icons">search</span>
                        <input type="text" id="search-input" placeholder="Search deleted messages..." autocomplete="off">
                    </div>
                </div>

                <!-- Messages List -->
                <div class="messages-area" id="messages-area">
                    <div class="messages-container" id="messages-container">
                        <?php if (empty($messages)): ?>
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <span class="material-icons">delete_outline</span>
                                </div>
                                <div class="empty-title">No Deleted Messages</div>
                                <div class="empty-text">Your deleted messages will appear here</div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($messages as $msg): ?>
                                <div class="message-item" data-id="<?= $msg['id'] ?>" onclick="selectMessage(<?= $msg['id'] ?>)">
                                    <div class="message-content">
                                        <div class="message-header">
                                            <span class="deleted-badge">
                                                <span class="material-icons">delete</span>
                                                Deleted
                                            </span>
                                            <span class="message-sender"><?= htmlspecialchars($msg['sender_name'] ?: $msg['sender_email']) ?></span>
                                        </div>
                                        <div class="message-subject"><?= htmlspecialchars($msg['subject'] ?: '(No Subject)') ?></div>
                                        <div class="message-preview"><?= htmlspecialchars($msg['body_preview'] ?: '') ?></div>
                                    </div>
                                    <div class="message-meta">
                                        <div class="message-date"><?= date('M j', strtotime($msg['deleted_at'])) ?></div>
                                        <?php if ($msg['has_attachments']): ?>
                                            <span class="material-icons attachment-indicator">attach_file</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Message View Pane -->
            <div class="message-view-pane" id="message-view-pane">
                <div class="empty-state">
                    <div class="empty-icon">
                        <span class="material-icons">mail_outline</span>
                    </div>
                    <div class="empty-title">Select a message</div>
                    <div class="empty-text">Choose a message from the list to view its contents</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let selectedMessageId = null;

        // Select and view message
        function selectMessage(messageId) {
            selectedMessageId = messageId;
            
            // Update selected state in list
            document.querySelectorAll('.message-item').forEach(item => {
                item.classList.remove('selected');
            });
            document.querySelector(`[data-id="${messageId}"]`).classList.add('selected');
            
            // Load message content
            loadMessageView(messageId);
        }

        // Load message view
        function loadMessageView(messageId) {
            fetch(`?action=get_message&id=${messageId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        displayMessage(data.message);
                    } else {
                        showToast('Failed to load message', 'error');
                    }
                })
                .catch(err => {
                    console.error(err);
                    showToast('Error loading message', 'error');
                });
        }

        // Display message in view pane
        function displayMessage(msg) {
            const viewPane = document.getElementById('message-view-pane');
            
            // Parse attachments
            let attachments = [];
            if (msg.attachment_data) {
                try {
                    attachments = JSON.parse(msg.attachment_data);
                } catch (e) {}
            }
            
            viewPane.innerHTML = `
                <div class="message-view-header">
                    <div class="message-view-title">${escapeHtml(msg.subject || '(No Subject)')}</div>
                    <div class="message-view-meta">
                        <div class="message-view-meta-item">
                            <span class="material-icons">person</span>
                            <span class="message-view-meta-label">From:</span>
                            ${escapeHtml(msg.sender_name || msg.sender_email)}
                        </div>
                        <div class="message-view-meta-item">
                            <span class="material-icons">schedule</span>
                            <span class="message-view-meta-label">Received:</span>
                            ${formatDate(msg.received_date)}
                        </div>
                        <div class="message-view-meta-item">
                            <span class="material-icons">delete</span>
                            <span class="message-view-meta-label">Deleted:</span>
                            ${formatDate(msg.deleted_at)}
                        </div>
                    </div>
                    <div class="message-view-actions">
                        <button class="btn btn-success" onclick="restoreMessage(${msg.id})">
                            <span class="material-icons">restore</span>
                            Restore
                        </button>
                        <button class="btn btn-danger" onclick="permanentDelete(${msg.id})">
                            <span class="material-icons">delete_forever</span>
                            Delete Forever
                        </button>
                    </div>
                </div>
                <div class="message-view-body">
                    <div class="message-detail">${escapeHtml(msg.body)}</div>
                    ${attachments.length > 0 ? `
                        <div class="attachments-section">
                            <div class="attachments-title">
                                <span class="material-icons">attach_file</span>
                                Attachments (${attachments.length})
                            </div>
                            <div class="attachments-grid">
                                ${attachments.map(att => `
                                    <div class="attachment-card">
                                        <div class="attachment-icon">${att.icon || '📄'}</div>
                                        <div class="attachment-name">${escapeHtml(att.filename)}</div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    ` : ''}
                </div>
            `;
        }

        // Restore message
        function restoreMessage(messageId) {
            if (!confirm('Restore this message to your inbox?')) return;
            
            fetch(`?action=restore&id=${messageId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast('Message restored successfully', 'success');
                        // Remove from list
                        document.querySelector(`[data-id="${messageId}"]`).remove();
                        // Clear view
                        document.getElementById('message-view-pane').innerHTML = `
                            <div class="empty-state">
                                <div class="empty-icon"><span class="material-icons">mail_outline</span></div>
                                <div class="empty-title">Select a message</div>
                            </div>
                        `;
                        // Update count
                        updateCount();
                    } else {
                        showToast('Failed to restore message', 'error');
                    }
                })
                .catch(err => {
                    showToast('Error restoring message', 'error');
                });
        }

        // Permanent delete
        function permanentDelete(messageId) {
            if (!confirm('Permanently delete this message? This cannot be undone!')) return;
            
            fetch(`?action=permanent_delete&id=${messageId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast('Message deleted permanently', 'success');
                        document.querySelector(`[data-id="${messageId}"]`).remove();
                        document.getElementById('message-view-pane').innerHTML = `
                            <div class="empty-state">
                                <div class="empty-icon"><span class="material-icons">mail_outline</span></div>
                                <div class="empty-title">Select a message</div>
                            </div>
                        `;
                        updateCount();
                    } else {
                        showToast('Failed to delete message', 'error');
                    }
                })
                .catch(err => {
                    showToast('Error deleting message', 'error');
                });
        }

        // Search
        let searchTimeout;
        document.getElementById('search-input').addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                performSearch(e.target.value);
            }, 300);
        });

        function performSearch(query) {
            fetch(`?action=get_deleted_messages&search=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        renderMessages(data.messages);
                    }
                });
        }

        function renderMessages(messages) {
            const container = document.getElementById('messages-container');
            if (messages.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon"><span class="material-icons">search_off</span></div>
                        <div class="empty-title">No messages found</div>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = messages.map(msg => `
                <div class="message-item" data-id="${msg.id}" onclick="selectMessage(${msg.id})">
                    <div class="message-content">
                        <div class="message-header">
                            <span class="deleted-badge">
                                <span class="material-icons">delete</span>
                                Deleted
                            </span>
                            <span class="message-sender">${escapeHtml(msg.sender_name || msg.sender_email)}</span>
                        </div>
                        <div class="message-subject">${escapeHtml(msg.subject || '(No Subject)')}</div>
                        <div class="message-preview">${escapeHtml(msg.body_preview || '')}</div>
                    </div>
                    <div class="message-meta">
                        <div class="message-date">${formatDateShort(msg.deleted_at)}</div>
                        ${msg.has_attachments ? '<span class="material-icons attachment-indicator">attach_file</span>' : ''}
                    </div>
                </div>
            `).join('');
        }

        // Update count
        function updateCount() {
            fetch('?action=get_deleted_messages&limit=1')
                .then(res => res.json())
                .then(data => {
                    document.getElementById('total-count').textContent = data.total.toLocaleString();
                    document.getElementById('deleted-count').textContent = data.total.toLocaleString();
                });
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

        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <span class="material-icons">${type === 'success' ? 'check_circle' : 'error'}</span>
                ${message}
            `;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }
    </script>
</body>
</html>