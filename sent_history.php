<?php
/**
 * Professional Sent History Page - Redesigned to match Inbox.php
 */

session_start();

// Security check
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    header("Location: login.php");
    exit();
}

require_once 'db_config.php';

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
            if (isset($_GET['label_id'])) $filters['label_id'] = $_GET['label_id'];
            
            $messages = getSentEmails($userEmail, $limit, $offset, $filters);
            $total = getSentEmailCount($userEmail, $filters);
            
            echo json_encode([
                'success' => true,
                'messages' => $messages,
                'total' => $total
            ]);
            exit();
            
        case 'get_message':
            $messageId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            $message = getSentEmailById($messageId, $userEmail);
            
            if ($message) {
                echo json_encode(['success' => true, 'message' => $message]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Message not found']);
            }
            exit();
            
        case 'delete':
            $messageId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            $success = deleteSentEmail($messageId, $userEmail);
            echo json_encode(['success' => $success]);
            exit();
            
        case 'update_label':
            $messageId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            $labelId = isset($_GET['label_id']) ? (int)$_GET['label_id'] : null;
            $success = updateEmailLabel($messageId, $userEmail, $labelId);
            echo json_encode(['success' => $success]);
            exit();
            
        case 'get_counts':
            $total = getSentEmailCount($userEmail);
            $labeled = getLabeledCount($userEmail);
            echo json_encode([
                'success' => true,
                'total' => $total,
                'labeled' => $labeled
            ]);
            exit();
    }
}

// Initial data load
$messages = getSentEmails($userEmail, 50, 0) ?? [];
$totalCount = getSentEmailCount($userEmail) ?? 0;
$labels = getUserLabels($userEmail) ?? [];

/**
 * Get sent emails for current user with pagination and filters
 */
function getSentEmails($userEmail, $limit = 50, $offset = 0, $filters = []) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return [];

        $userId = getUserId($pdo, $userEmail);
        if (!$userId) return [];

        // Use v_user_sent view which includes body_html
        $sql = "SELECT * FROM v_user_sent WHERE user_id = :user_id";
        $params = [':user_id' => $userId];

        // Apply Search Filter
        if (!empty($filters['search'])) {
            $sql .= " AND (recipient_email LIKE :search 
                        OR subject LIKE :search 
                        OR body_text LIKE :search 
                        OR article_title LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        // Apply Label Filter
        if (!empty($filters['label_id'])) {
            if ($filters['label_id'] === 'unlabeled') {
                $sql .= " AND label_id IS NULL";
            } else {
                $sql .= " AND label_id = :label_id";
                $params[':label_id'] = $filters['label_id'];
            }
        }

        $sql .= " ORDER BY sent_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Error fetching sent emails: " . $e->getMessage());
        return [];
    }
}

function getSentEmailCount($userEmail, $filters = []) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return 0;

        $userId = getUserId($pdo, $userEmail);
        if (!$userId) return 0;

        $sql = "SELECT COUNT(*) FROM v_user_sent WHERE user_id = :user_id";
        $params = [':user_id' => $userId];

        if (!empty($filters['search'])) {
            $sql .= " AND (recipient_email LIKE :search 
                        OR subject LIKE :search 
                        OR body_text LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['label_id'])) {
            if ($filters['label_id'] === 'unlabeled') {
                $sql .= " AND label_id IS NULL";
            } else {
                $sql .= " AND label_id = :label_id";
                $params[':label_id'] = $filters['label_id'];
            }
        }

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();

    } catch (PDOException $e) {
        error_log("Error counting sent emails: " . $e->getMessage());
        return 0;
    }
}

function getSentEmailById($id, $userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return null;

        $userId = getUserId($pdo, $userEmail);
        if (!$userId) return null;

        $stmt = $pdo->prepare("
            SELECT * FROM v_user_sent 
            WHERE id = :id AND user_id = :user_id
        ");
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Error fetching sent email: " . $e->getMessage());
        return null;
    }
}

function deleteSentEmail($id, $userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;

        $userId = getUserId($pdo, $userEmail);
        if (!$userId) return false;

        $stmt = $pdo->prepare("
            UPDATE user_email_access 
            SET is_deleted = 1 
            WHERE email_id = :id AND user_id = :user_id
        ");
        return $stmt->execute([':id' => $id, ':user_id' => $userId]);

    } catch (PDOException $e) {
        error_log("Error deleting sent email: " . $e->getMessage());
        return false;
    }
}

function updateEmailLabel($id, $userEmail, $labelId) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;

        $userId = getUserId($pdo, $userEmail);
        if (!$userId) return false;

        $stmt = $pdo->prepare("
            UPDATE user_email_access 
            SET label_id = :label_id 
            WHERE email_id = :id AND user_id = :user_id
        ");
        return $stmt->execute([
            ':id' => $id, 
            ':user_id' => $userId,
            ':label_id' => $labelId
        ]);

    } catch (PDOException $e) {
        error_log("Error updating label: " . $e->getMessage());
        return false;
    }
}

function getLabeledCount($userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return 0;

        $userId = getUserId($pdo, $userEmail);
        if (!$userId) return 0;

        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM v_user_sent 
            WHERE user_id = :user_id AND label_id IS NOT NULL
        ");
        $stmt->execute([':user_id' => $userId]);
        return (int)$stmt->fetchColumn();

    } catch (PDOException $e) {
        return 0;
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sent — SXC MDTS</title>

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
        }

        .btn-icon:hover {
            background: var(--apple-bg);
            border-color: var(--apple-gray);
        }

        /* ========== TOOLBAR ========== */
        .toolbar {
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 12px 24px;
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .search-container {
            position: relative;
            flex: 1;
            max-width: 400px;
        }

        .search-input {
            width: 100%;
            padding: 8px 12px 8px 36px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 13px;
            font-family: 'Inter', sans-serif;
            background: var(--apple-bg);
            transition: all 0.2s;
        }

        .search-input:focus {
            outline: none;
            background: white;
            border-color: var(--apple-blue);
            box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--apple-gray);
            font-size: 18px;
        }

        .filter-chips {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .filter-chip {
            padding: 6px 12px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            color: #1c1c1e;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .filter-chip:hover {
            background: var(--apple-bg);
        }

        .filter-chip.active {
            background: var(--apple-blue);
            color: white;
            border-color: var(--apple-blue);
        }

        /* ========== LAYOUT ========== */
        .content-wrapper {
            flex: 1;
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 0;
            overflow: hidden;
        }

        /* ========== MESSAGE LIST ========== */
        .message-list-container {
            background: white;
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .message-list {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .message-item {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: all 0.15s;
            position: relative;
        }

        .message-item:hover {
            background: rgba(0, 122, 255, 0.04);
        }

        .message-item.active {
            background: rgba(0, 122, 255, 0.08);
            border-left: 3px solid var(--apple-blue);
        }

        .message-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }

        .message-recipient {
            font-weight: 600;
            font-size: 14px;
            color: #1c1c1e;
        }

        .message-date {
            font-size: 12px;
            color: var(--apple-gray);
            font-weight: 400;
        }

        .message-subject {
            font-size: 13px;
            font-weight: 500;
            color: #1c1c1e;
            margin-bottom: 4px;
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

        .label-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            color: white;
            margin-top: 6px;
        }

        /* ========== MESSAGE VIEW ========== */
        .message-view-container {
            background: white;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .message-view-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
        }

        .message-actions {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
        }

        .message-subject-large {
            font-size: 20px;
            font-weight: 600;
            color: #1c1c1e;
            margin-bottom: 12px;
            line-height: 1.3;
        }

        .message-meta {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .meta-row {
            display: flex;
            gap: 12px;
            font-size: 13px;
        }

        .meta-label {
            color: var(--apple-gray);
            font-weight: 500;
            min-width: 60px;
        }

        .meta-value {
            color: #1c1c1e;
        }

        .message-body-container {
            flex: 1;
            overflow-y: auto;
            padding: 24px;
        }

        .message-body-iframe {
            width: 100%;
            border: none;
            min-height: 500px;
            background: white;
        }

        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--apple-gray);
            padding: 40px;
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
            margin-bottom: 8px;
            color: #1c1c1e;
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
            padding: 12px 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            animation: toastSlideIn 0.3s ease-out;
            z-index: 1000;
        }

        .toast.success {
            background: var(--success-green);
        }

        .toast.error {
            background: var(--danger-red);
        }

        @keyframes toastSlideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* ========== SCROLLBAR ========== */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--apple-bg);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--apple-light-gray);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--apple-gray);
        }

        /* ========== LABEL SELECTOR ========== */
        .label-selector {
            padding: 6px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 12px;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            background: white;
            color: #1c1c1e;
        }

        .label-selector:focus {
            outline: none;
            border-color: var(--apple-blue);
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 1024px) {
            .content-wrapper {
                grid-template-columns: 1fr;
            }

            .message-view-container {
                display: none;
            }

            .message-view-container.active {
                display: flex;
            }

            .message-list-container.hide-on-mobile {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .page-header {
                padding: 12px 16px;
            }

            .toolbar {
                padding: 12px 16px;
                flex-wrap: wrap;
            }

            .search-container {
                max-width: 100%;
            }
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <!-- Header -->
        <div class="page-header">
            <div class="header-left">
                <div class="page-title">Sent</div>
                <div class="page-subtitle">
                    <span id="totalCount"><?= $totalCount ?></span> sent emails
                </div>
            </div>
            <div class="header-actions">
                <a href="send.php" class="btn btn-primary">
                    <span class="material-icons">edit</span>
                    Compose
                </a>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="toolbar">
            <div class="search-container">
                <span class="material-icons search-icon">search</span>
                <input type="text" class="search-input" id="searchInput" placeholder="Search sent emails..." 
                       onkeyup="if(event.key === 'Enter') searchMessages()">
            </div>
            
            <div class="filter-chips">
                <?php foreach ($labels as $label): ?>
                <div class="filter-chip" id="filterLabel<?= $label['id'] ?>" 
                     onclick="toggleLabelFilter(<?= $label['id'] ?>)">
                    <span class="material-icons" style="font-size: 14px;">label</span>
                    <?= htmlspecialchars($label['label_name']) ?>
                </div>
                <?php endforeach; ?>
                
                <div class="filter-chip" id="filterUnlabeled" onclick="toggleLabelFilter('unlabeled')">
                    <span class="material-icons" style="font-size: 14px;">label_off</span>
                    Unlabeled
                </div>
            </div>
        </div>

        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <!-- Message List -->
            <div class="message-list-container" id="messageListContainer">
                <div class="message-list" id="messageList">
                    <?php if (empty($messages)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📧</div>
                        <div class="empty-title">No sent emails</div>
                        <div class="empty-text">You haven't sent any emails yet</div>
                    </div>
                    <?php else: ?>
                        <?php foreach ($messages as $msg): ?>
                        <div class="message-item" onclick="viewMessage(<?= $msg['id'] ?>)" data-id="<?= $msg['id'] ?>">
                            <div class="message-header-row">
                                <div class="message-recipient">
                                    To: <?= htmlspecialchars($msg['recipient_email']) ?>
                                </div>
                                <div class="message-date">
                                    <?= date('M j', strtotime($msg['sent_at'])) ?>
                                </div>
                            </div>
                            <div class="message-subject">
                                <?= htmlspecialchars($msg['subject']) ?>
                            </div>
                            <div class="message-preview">
                                <?= htmlspecialchars(substr(strip_tags($msg['body_text'] ?? ''), 0, 100)) ?>
                            </div>
                            <?php if (!empty($msg['label_name'])): ?>
                            <div class="label-badge" style="background-color: <?= htmlspecialchars($msg['label_color']) ?>">
                                <span class="material-icons" style="font-size: 12px;">label</span>
                                <?= htmlspecialchars($msg['label_name']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Message View -->
            <div class="message-view-container" id="messageViewContainer">
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
        let currentMessageId = null;
        let currentSearchQuery = '';
        let currentLabelFilter = null;

        async function fetchMessages() {
            try {
                let url = 'sent_history.php?action=fetch_messages&limit=50&offset=0';
                
                if (currentSearchQuery) {
                    url += '&search=' + encodeURIComponent(currentSearchQuery);
                }
                
                if (currentLabelFilter) {
                    url += '&label_id=' + encodeURIComponent(currentLabelFilter);
                }

                const response = await fetch(url);
                const data = await response.json();

                if (data.success) {
                    renderMessageList(data.messages);
                    document.getElementById('totalCount').textContent = data.total;
                }
            } catch (error) {
                console.error('Error fetching messages:', error);
            }
        }

        function renderMessageList(messages) {
            const container = document.getElementById('messageList');
            
            if (messages.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">📧</div>
                        <div class="empty-title">No messages found</div>
                        <div class="empty-text">Try adjusting your filters</div>
                    </div>
                `;
                return;
            }

            container.innerHTML = messages.map(msg => {
                const labelBadge = msg.label_name ? 
                    `<div class="label-badge" style="background-color: ${escapeHtml(msg.label_color)}">
                        <span class="material-icons" style="font-size: 12px;">label</span>
                        ${escapeHtml(msg.label_name)}
                    </div>` : '';

                return `
                    <div class="message-item" onclick="viewMessage(${msg.id})" data-id="${msg.id}">
                        <div class="message-header-row">
                            <div class="message-recipient">
                                To: ${escapeHtml(msg.recipient_email)}
                            </div>
                            <div class="message-date">
                                ${formatDate(msg.sent_at)}
                            </div>
                        </div>
                        <div class="message-subject">
                            ${escapeHtml(msg.subject)}
                        </div>
                        <div class="message-preview">
                            ${escapeHtml((msg.body_text || '').substring(0, 100))}
                        </div>
                        ${labelBadge}
                    </div>
                `;
            }).join('');
        }

        async function viewMessage(messageId) {
            try {
                const response = await fetch(`sent_history.php?action=get_message&id=${messageId}`);
                const data = await response.json();

                if (data.success) {
                    currentMessageId = messageId;
                    renderMessageView(data.message);
                    
                    // Highlight active message
                    document.querySelectorAll('.message-item').forEach(item => {
                        item.classList.remove('active');
                    });
                    document.querySelector(`.message-item[data-id="${messageId}"]`)?.classList.add('active');

                    // Mobile: Show message view
                    if (window.innerWidth <= 1024) {
                        document.getElementById('messageListContainer').classList.add('hide-on-mobile');
                        document.getElementById('messageViewContainer').classList.add('active');
                    }
                } else {
                    showToast('Failed to load message', 'error');
                }
            } catch (error) {
                console.error('Error viewing message:', error);
                showToast('Failed to load message', 'error');
            }
        }

        function renderMessageView(message) {
            const container = document.getElementById('messageViewContent');
            
            const labelOptions = <?= json_encode($labels) ?>;
            const labelSelect = `
                <select class="label-selector" onchange="updateLabel(${message.id}, this.value)">
                    <option value="">No Label</option>
                    ${labelOptions.map(l => 
                        `<option value="${l.id}" ${message.label_id == l.id ? 'selected' : ''}>
                            ${escapeHtml(l.label_name)}
                        </option>`
                    ).join('')}
                </select>
            `;

            const labelBadge = message.label_name ? 
                `<div class="label-badge" style="background-color: ${escapeHtml(message.label_color)}">
                    <span class="material-icons" style="font-size: 12px;">label</span>
                    ${escapeHtml(message.label_name)}
                </div>` : '';

            container.innerHTML = `
                <div class="message-view-header">
                    <div class="message-actions">
                        <button class="btn-icon" onclick="goBackToList()" title="Back">
                            <span class="material-icons">arrow_back</span>
                        </button>
                        <button class="btn-icon" onclick="window.print()" title="Print">
                            <span class="material-icons">print</span>
                        </button>
                        ${labelSelect}
                        <button class="btn-icon" onclick="deleteMessage(${message.id})" title="Delete">
                            <span class="material-icons" style="color: var(--danger-red);">delete</span>
                        </button>
                    </div>
                    ${labelBadge}
                    <div class="message-subject-large">${escapeHtml(message.subject)}</div>
                    <div class="message-meta">
                        <div class="meta-row">
                            <span class="meta-label">From:</span>
                            <span class="meta-value">${escapeHtml(message.sender_email || '<?= $userEmail ?>')}</span>
                        </div>
                        <div class="meta-row">
                            <span class="meta-label">To:</span>
                            <span class="meta-value">${escapeHtml(message.recipient_email)}</span>
                        </div>
                        ${message.cc_list ? `
                        <div class="meta-row">
                            <span class="meta-label">CC:</span>
                            <span class="meta-value">${escapeHtml(message.cc_list)}</span>
                        </div>` : ''}
                        <div class="meta-row">
                            <span class="meta-label">Date:</span>
                            <span class="meta-value">${formatDateLong(message.sent_at)}</span>
                        </div>
                    </div>
                </div>
                <div class="message-body-container">
                    <iframe class="message-body-iframe" id="emailBodyFrame"></iframe>
                </div>
            `;

            // Load HTML content into iframe
            const iframe = document.getElementById('emailBodyFrame');
            const htmlContent = message.body_html || message.body_text || 'No content available';
            
            iframe.onload = function() {
                try {
                    const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                    iframeDoc.open();
                    iframeDoc.write(htmlContent);
                    iframeDoc.close();

                    // Auto-resize iframe
                    setTimeout(() => {
                        const height = iframeDoc.documentElement.scrollHeight;
                        iframe.style.height = Math.max(height + 40, 500) + 'px';
                    }, 100);
                } catch (e) {
                    console.error('Error loading iframe:', e);
                }
            };
            
            // Trigger load
            iframe.src = 'about:blank';
        }

        function goBackToList() {
            if (window.innerWidth <= 1024) {
                document.getElementById('messageListContainer').classList.remove('hide-on-mobile');
                document.getElementById('messageViewContainer').classList.remove('active');
            }
        }

        function searchMessages() {
            currentSearchQuery = document.getElementById('searchInput').value;
            fetchMessages();
        }

        function toggleLabelFilter(labelId) {
            const filterBtn = document.getElementById('filterLabel' + labelId) || 
                             document.getElementById('filterUnlabeled');
            
            if (currentLabelFilter === labelId) {
                currentLabelFilter = null;
                filterBtn.classList.remove('active');
            } else {
                // Remove active from all filters
                document.querySelectorAll('.filter-chip').forEach(chip => {
                    chip.classList.remove('active');
                });
                
                currentLabelFilter = labelId;
                filterBtn.classList.add('active');
            }
            
            fetchMessages();
        }

        async function updateLabel(messageId, labelId) {
            try {
                const response = await fetch(`sent_history.php?action=update_label&id=${messageId}&label_id=${labelId}`);
                const data = await response.json();

                if (data.success) {
                    showToast('Label updated', 'success');
                    fetchMessages();
                    viewMessage(messageId);
                } else {
                    showToast('Failed to update label', 'error');
                }
            } catch (error) {
                console.error('Error updating label:', error);
                showToast('Failed to update label', 'error');
            }
        }

        async function deleteMessage(messageId) {
            if (!confirm('Are you sure you want to delete this message?')) {
                return;
            }

            try {
                const response = await fetch(`sent_history.php?action=delete&id=${messageId}`);
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
                } else {
                    showToast('Failed to delete message', 'error');
                }
            } catch (error) {
                console.error('Error deleting message:', error);
                showToast('Failed to delete message', 'error');
            }
        }

        function formatDate(dateStr) {
            const date = new Date(dateStr);
            const now = new Date();
            const diffMs = now - date;
            const diffHours = Math.floor(diffMs / 3600000);
            const diffDays = Math.floor(diffMs / 86400000);

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
                type === 'error' ? 'error' : 'info';

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

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                document.getElementById('searchInput').focus();
            }
        });
    </script>
</body>

</html>