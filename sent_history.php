<?php
/**
 * CORRECTED PHP PORTION for sent_history.php
 * Works with sent_emails_new table structure
 * Replace lines 1-270 with this code
 */

session_start();
require 'config.php';
require 'db_config.php';

// Security check
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    header("Location: login.php");
    exit();
}

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
            if (isset($_GET['recipient'])) $filters['recipient'] = $_GET['recipient'];
            if (isset($_GET['label_id'])) $filters['label_id'] = $_GET['label_id'];
            
            $messages = getSentEmails($userEmail, $limit, $offset, $filters);
            $total = getSentEmailCountLocal($userEmail, $filters);
            
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
                // Get attachments if any
                $attachments = [];
                if ($message['has_attachments']) {
                    try {
                        $pdo = getDatabaseConnection();
                        $stmt = $pdo->prepare("
                            SELECT * FROM sent_email_attachments_new 
                            WHERE sent_email_id = ? 
                            ORDER BY uploaded_at ASC
                        ");
                        $stmt->execute([$messageId]);
                        $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                        error_log("Error fetching attachments: " . $e->getMessage());
                    }
                }
                $message['attachments'] = $attachments;
                
                echo json_encode(['success' => true, 'message' => $message]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Message not found']);
            }
            exit();
            
        case 'delete':
            $messageId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            try {
                $pdo = getDatabaseConnection();
                $stmt = $pdo->prepare("
                    UPDATE sent_emails_new 
                    SET is_deleted = 1 
                    WHERE id = ? AND sender_email = ?
                ");
                $success = $stmt->execute([$messageId, $userEmail]);
                echo json_encode(['success' => $success]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit();
            
        case 'get_counts':
            $total = getSentEmailCountLocal($userEmail);
            $labeled = getSentEmailCountLocal($userEmail, ['has_label' => true]);
            $unlabeled = getSentEmailCountLocal($userEmail, ['label_id' => 'unlabeled']);
            
            echo json_encode([
                'success' => true,
                'total' => $total,
                'labeled' => $labeled,
                'unlabeled' => $unlabeled
            ]);
            exit();
    }
}

/**
 * Get sent emails from sent_emails_new table
 */
function getSentEmails($userEmail, $limit = 50, $offset = 0, $filters = []) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return [];
        
        $sql = "SELECT 
                    se.*,
                    (SELECT GROUP_CONCAT(original_filename SEPARATOR ', ')
                     FROM sent_email_attachments_new sea
                     WHERE sea.sent_email_id = se.id) as attachment_names,
                    (SELECT COUNT(*)
                     FROM sent_email_attachments_new sea
                     WHERE sea.sent_email_id = se.id) as attachment_count
                FROM sent_emails_new se
                WHERE se.sender_email = :email
                AND se.is_deleted = 0";
        
        $params = ['email' => $userEmail];
        
        // Apply search filter
        if (!empty($filters['search'])) {
            $sql .= " AND (se.recipient_email LIKE :search 
                        OR se.subject LIKE :search 
                        OR se.body_text LIKE :search 
                        OR se.article_title LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        // Apply recipient filter
        if (!empty($filters['recipient'])) {
            $sql .= " AND se.recipient_email LIKE :recipient";
            $params['recipient'] = '%' . $filters['recipient'] . '%';
        }
        
        // Apply subject filter
        if (!empty($filters['subject'])) {
            $sql .= " AND se.subject LIKE :subject";
            $params['subject'] = '%' . $filters['subject'] . '%';
        }
        
        // Apply label filter
        if (!empty($filters['label_id'])) {
            if ($filters['label_id'] === 'unlabeled') {
                $sql .= " AND se.label_id IS NULL";
            } else {
                $sql .= " AND se.label_id = :label_id";
                $params['label_id'] = $filters['label_id'];
            }
        }
        
        // Apply date range filters
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(se.sent_at) >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(se.sent_at) <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }
        
        $sql .= " ORDER BY se.sent_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
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

/**
 * Get sent email count from sent_emails_new table
 */
function getSentEmailCountLocal($userEmail, $filters = []) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return 0;
        
        $sql = "SELECT COUNT(*) as count 
                FROM sent_emails_new
                WHERE sender_email = :email 
                AND is_deleted = 0";
        
        $params = ['email' => $userEmail];
        
        // Apply has_label filter
        if (!empty($filters['has_label'])) {
            $sql .= " AND label_id IS NOT NULL";
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (recipient_email LIKE :search OR subject LIKE :search OR body_text LIKE :search OR article_title LIKE :search)";
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        if (!empty($filters['recipient'])) {
            $sql .= " AND recipient_email LIKE :recipient";
            $params['recipient'] = '%' . $filters['recipient'] . '%';
        }
        
        if (!empty($filters['subject'])) {
            $sql .= " AND subject LIKE :subject";
            $params['subject'] = '%' . $filters['subject'] . '%';
        }
        
        if (!empty($filters['label_id'])) {
            if ($filters['label_id'] === 'unlabeled') {
                $sql .= " AND label_id IS NULL";
            } else {
                $sql .= " AND label_id = :label_id";
                $params['label_id'] = $filters['label_id'];
            }
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(sent_at) >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(sent_at) <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        return $result['count'] ?? 0;
        
    } catch (PDOException $e) {
        error_log("Error counting sent emails: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get single sent email by ID from sent_emails_new table
 */
function getSentEmailById($emailId, $userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return null;
        
        $stmt = $pdo->prepare("
            SELECT * FROM sent_emails_new
            WHERE id = :id AND sender_email = :email AND is_deleted = 0
        ");
        
        $stmt->execute([
            'id' => $emailId,
            'email' => $userEmail
        ]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error getting sent email: " . $e->getMessage());
        return null;
    }
}

/**
 * Get label counts for sent emails
 */
function getLabelCountsForSent($userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return [];
        
        $stmt = $pdo->prepare("
            SELECT 
                label_id,
                label_name,
                label_color,
                COUNT(*) as count
            FROM sent_emails_new
            WHERE sender_email = :email 
            AND is_deleted = 0
            AND label_id IS NOT NULL
            GROUP BY label_id, label_name, label_color
            ORDER BY label_name
        ");
        
        $stmt->execute(['email' => $userEmail]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error getting label counts: " . $e->getMessage());
        return [];
    }
}

// Initial data load for the page
$messages = getSentEmails($userEmail, 50, 0) ?? [];
$totalCount = getSentEmailCountLocal($userEmail) ?? 0;
$labeledCount = getSentEmailCountLocal($userEmail, ['has_label' => true]) ?? 0;
$unlabeledCount = getSentEmailCountLocal($userEmail, ['label_id' => 'unlabeled']) ?? 0;
$labels = getLabelCountsForSent($userEmail) ?? [];

?>
<!-- HTML portion starts here at line 271 -->
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sent Emails — SXC MDTS</title>

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
            margin: 0;
            padding: 0;
            height: 100vh;
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Layout Container */
        .app-container {
            display: flex;
            min-height: 100vh;
            height: 100vh;
            width: 100%;
            background: var(--apple-bg);
            overflow: hidden;
        }

        .main-content-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            min-height: 0;
        }

        /* ========== MAIN CONTENT ========== */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            min-height: 0;
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
            background: rgba(0, 122, 255, 0.1);
            color: var(--apple-blue);
        }

        .stat-icon.labeled {
            background: rgba(52, 199, 89, 0.1);
            color: var(--success-green);
        }

        .stat-icon.unlabeled {
            background: rgba(255, 149, 0, 0.1);
            color: var(--warning-orange);
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

        /* ========== CONTENT AREA WITH SPLIT PANE ========== */
        .content-wrapper {
            flex: 1;
            display: flex;
            overflow: hidden;
            height: 100%;
            min-height: 0;
        }

        .messages-pane {
            width: 40%;
            display: flex;
            flex-direction: column;
            border-right: 1px solid var(--border);
            background: white;
            overflow: hidden;
            min-height: 0;
            height: 100%;
        }

        .message-view-pane {
            width: 60%;
            display: flex;
            flex-direction: column;
            background: #FAFAFA;
            overflow: hidden;
            min-height: 0;
            height: 100%;
        }

        #messageViewContent {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            min-height: 0;
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
            padding: 8px 36px 8px 36px;
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

        .search-box .clear-search {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--apple-gray);
            font-size: 18px;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            display: none;
            transition: all 0.2s;
        }

        .search-box .clear-search:hover {
            background: rgba(0, 0, 0, 0.05);
            color: #1c1c1e;
        }

        .search-box .clear-search.visible {
            display: block;
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

        .filter-select {
            padding: 6px 12px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            color: #1c1c1e;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
        }

        /* ========== MESSAGES AREA ========== */
        .messages-area {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            min-height: 0;
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

        .message-content {
            flex: 1;
            min-width: 0;
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 4px;
        }

        .message-recipient {
            font-weight: 600;
            color: #1c1c1e;
            font-size: 14px;
            margin-right: 12px;
        }

        .message-date {
            font-size: 11px;
            color: var(--apple-gray);
            white-space: nowrap;
            font-weight: 500;
        }

        .message-subject {
            font-size: 13px;
            color: #1c1c1e;
            margin-bottom: 4px;
            font-weight: 500;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .message-preview {
            font-size: 12px;
            color: var(--apple-gray);
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
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
        }

        .badge-label {
            color: white;
        }

        .badge-attachment {
            background: rgb(227, 227, 227);
            color: rgb(33, 33, 33);
            padding: 3px 8px;
            border-radius: 10px;
        }

        .badge-attachment .material-icons {
            font-size: 11px;
        }

        /* ========== SEARCH HIGHLIGHT ========== */
        .search-highlight {
            background-color: #ffeb3b;
            color: #000;
            padding: 2px 0;
            border-radius: 2px;
            font-weight: 600;
        }

        /* ========== MESSAGE VIEW PANE ========== */
        .message-view-header {
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 16px 20px;
        }

        .message-view-title {
            font-size: 20px;
            font-weight: 600;
            color: #1c1c1e;
            margin-bottom: 12px;
            line-height: 1.4;
        }

        .message-view-meta {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .message-view-meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--apple-gray);
        }

        .message-view-meta-item .material-icons {
            font-size: 16px;
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
            overflow-x: hidden;
            padding: 20px;
            min-height: 0;
        }

        .message-detail {
            background: white;
            border-radius: 10px;
            padding: 24px;
            font-size: 14px;
            line-height: 1.7;
            color: #1c1c1e;
            box-shadow: var(--card-shadow);
        }

        .article-title-display {
            font-size: 22px;
            font-weight: 700;
            color: #1c1c1e;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--border);
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
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
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
            gap: 10px;
        }

        .attachment-card {
            background: #FAFAFA;
            border: 1px solid var(--border);
            border-radius: 8px;
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
            font-size: 36px;
            margin-bottom: 8px;
            color: var(--apple-gray);
        }

        .attachment-name {
            font-size: 12px;
            font-weight: 500;
            color: #1c1c1e;
            margin-bottom: 4px;
            word-break: break-word;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .attachment-size {
            font-size: 11px;
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
            background: white;
            padding: 14px 20px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            font-weight: 500;
            z-index: 1000;
            animation: toastSlideIn 0.3s ease-out;
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

        .toast.success {
            border-left: 4px solid var(--success-green);
        }

        .toast.error {
            border-left: 4px solid var(--danger-red);
        }

        .toast.info {
            border-left: 4px solid var(--apple-blue);
        }

        .toast .material-icons {
            font-size: 20px;
        }

        .toast.success .material-icons {
            color: var(--success-green);
        }

        .toast.error .material-icons {
            color: var(--danger-red);
        }

        .toast.info .material-icons {
            color: var(--apple-blue);
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 968px) {
            .messages-pane {
                width: 100%;
                border-right: none;
            }

            .message-view-pane {
                display: none;
            }

            .message-view-pane.mobile-show {
                display: flex;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                width: 100%;
                z-index: 100;
            }
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php require_once 'sidebar.php'; ?>

        <div class="main-content-wrapper">
            <div class="main-content">
        <!-- Header -->
        <div class="page-header">
            <div class="header-left">
                <h1 class="page-title">Sent Emails</h1>
                <p class="page-subtitle">Manage your sent email history</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="window.location.href='index.php'">
                    <span class="material-icons">add</span>
                    Compose Email
                </button>
            </div>
        </div>

        <!-- Stats Bar -->
        <div class="stats-bar">
            <div class="stat-item">
                <div class="stat-icon total">
                    <span class="material-icons">mail</span>
                </div>
                <div class="stat-content">
                    <div class="stat-number" id="totalCount"><?= $totalCount ?></div>
                    <div class="stat-label">Total Sent</div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon labeled">
                    <span class="material-icons">label</span>
                </div>
                <div class="stat-content">
                    <div class="stat-number" id="labeledCount"><?= $labeledCount ?></div>
                    <div class="stat-label">Labeled</div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon unlabeled">
                    <span class="material-icons">label_off</span>
                </div>
                <div class="stat-content">
                    <div class="stat-number" id="unlabeledCount"><?= $unlabeledCount ?></div>
                    <div class="stat-label">Unlabeled</div>
                </div>
            </div>
        </div>

        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <!-- Messages Pane -->
            <div class="messages-pane">
                <!-- Toolbar -->
                <div class="toolbar">
                    <div class="search-box">
                        <span class="material-icons">search</span>
                        <input type="text" id="searchInput" placeholder="Search sent emails..." oninput="searchMessages()">
                        <span class="material-icons clear-search" id="clearSearch" onclick="clearSearch()">close</span>
                    </div>
                    <div class="filter-group">
                        <select id="labelFilter" class="filter-select" onchange="filterByLabel()">
                            <option value="">All Labels</option>
                            <option value="unlabeled">Unlabeled</option>
                            <?php foreach ($labels as $label): ?>
                            <option value="<?= $label['label_id'] ?>">
                                <?= htmlspecialchars($label['label_name']) ?> (<?= $label['email_count'] ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Messages Area -->
                <div class="messages-area">
                    <div class="messages-container" id="messagesContainer">
                        <?php if (empty($messages)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📧</div>
                            <div class="empty-title">No sent emails</div>
                            <div class="empty-text">Your sent emails will appear here</div>
                        </div>
                        <?php else: ?>
                        <?php foreach ($messages as $msg): ?>
                        <div class="message-item" onclick="viewMessage(<?= $msg['id'] ?>)" data-message-id="<?= $msg['id'] ?>">
                            <div class="message-content">
                                <div class="message-header">
                                    <div class="message-recipient">
                                        <?= htmlspecialchars($msg['recipient_email']) ?>
                                    </div>
                                    <div class="message-date">
                                        <?= date('M j', strtotime($msg['sent_at'])) ?>
                                    </div>
                                </div>
                                <div class="message-subject">
                                    <?= htmlspecialchars($msg['subject']) ?>
                                </div>
                                <div class="message-preview">
                                    <?= htmlspecialchars(strip_tags($msg['body_text']) ?: 'No preview available') ?>
                                </div>
                                <div class="message-badges">
                                    <?php if (!empty($msg['label_name'])): ?>
                                    <span class="badge badge-label" style="background: <?= htmlspecialchars($msg['label_color'] ?? '#6b7280') ?>">
                                        <?= htmlspecialchars($msg['label_name']) ?>
                                    </span>
                                    <?php endif; ?>
                                    <?php if ($msg['has_attachments']): ?>
                                    <span class="badge badge-attachment">
                                        <span class="material-icons">attach_file</span>
                                        <?= $msg['attachment_count'] ?? '1' ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Message View Pane -->
            <div class="message-view-pane" id="messageViewPane">
                <div id="messageViewContent">
                    <div class="empty-state">
                        <div class="empty-icon">📨</div>
                        <div class="empty-title">No message selected</div>
                        <div class="empty-text">Click on an email to view its contents</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div> <!-- /main-content-wrapper -->
    </div> <!-- /app-container -->

    <script>
        let currentMessageId = null;
        let currentFilters = {
            search: '',
            label_id: ''
        };
        let allMessages = []; // Store all messages for client-side search

        // Load initial message if present in URL
        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const messageId = urlParams.get('id');
            if (messageId) {
                viewMessage(parseInt(messageId));
            }
            
            // Load all messages initially
            loadAllMessages();
        });

        async function loadAllMessages() {
            try {
                const params = new URLSearchParams({
                    action: 'fetch_messages',
                    limit: 1000,
                    offset: 0
                });

                if (currentFilters.label_id) params.append('label_id', currentFilters.label_id);

                const response = await fetch('sent_history.php?' + params);
                const data = await response.json();

                if (data.success) {
                    allMessages = data.messages;
                    displayMessages(allMessages);
                }
            } catch (error) {
                console.error('Error loading messages:', error);
            }
        }

        function searchMessages() {
            const searchTerm = document.getElementById('searchInput').value.trim();
            const clearBtn = document.getElementById('clearSearch');
            currentFilters.search = searchTerm;

            // Show/hide clear button
            if (searchTerm) {
                clearBtn.classList.add('visible');
            } else {
                clearBtn.classList.remove('visible');
            }

            if (!searchTerm) {
                // If search is empty, show all messages
                displayMessages(allMessages);
                return;
            }

            // Filter messages based on search term
            const filteredMessages = allMessages.filter(msg => {
                const searchLower = searchTerm.toLowerCase();
                const recipient = (msg.recipient_email || '').toLowerCase();
                const subject = (msg.subject || '').toLowerCase();
                const body = (msg.body_text || '').toLowerCase();
                const article = (msg.article_title || '').toLowerCase();
                const label = (msg.label_name || '').toLowerCase();

                return recipient.includes(searchLower) ||
                       subject.includes(searchLower) ||
                       body.includes(searchLower) ||
                       article.includes(searchLower) ||
                       label.includes(searchLower);
            });

            displayMessages(filteredMessages, searchTerm);
        }

        function clearSearch() {
            document.getElementById('searchInput').value = '';
            document.getElementById('clearSearch').classList.remove('visible');
            currentFilters.search = '';
            displayMessages(allMessages);
        }

        function displayMessages(messages, searchTerm = '') {
            const container = document.getElementById('messagesContainer');

            if (messages.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">🔍</div>
                        <div class="empty-title">No results found</div>
                        <div class="empty-text">${searchTerm ? 'Try a different search term' : 'No messages to display'}</div>
                    </div>
                `;
                return;
            }

            let html = '';
            messages.forEach(msg => {
                const recipient = highlightText(escapeHtml(msg.recipient_email), searchTerm);
                const subject = highlightText(escapeHtml(msg.subject), searchTerm);
                const preview = highlightText(escapeHtml(stripTags(msg.body_text) || 'No preview available'), searchTerm);
                const article = msg.article_title ? highlightText(escapeHtml(msg.article_title), searchTerm) : '';
                const labelName = msg.label_name ? highlightText(escapeHtml(msg.label_name), searchTerm) : '';

                html += `
                    <div class="message-item" onclick="viewMessage(${msg.id})" data-message-id="${msg.id}">
                        <div class="message-content">
                            <div class="message-header">
                                <div class="message-recipient">
                                    ${recipient}
                                </div>
                                <div class="message-date">
                                    ${formatDate(msg.sent_at)}
                                </div>
                            </div>
                            ${article ? `<div class="message-subject">${article}</div>` : ''}
                            <div class="message-subject">
                                ${subject}
                            </div>
                            <div class="message-preview">
                                ${preview}
                            </div>
                            <div class="message-badges">
                                ${msg.label_name ? `
                                    <span class="badge badge-label" style="background: ${msg.label_color || '#6b7280'}">
                                        ${labelName}
                                    </span>
                                ` : ''}
                                ${msg.has_attachments ? `
                                    <span class="badge badge-attachment">
                                        <span class="material-icons">attach_file</span>
                                        ${msg.attachment_count || '1'}
                                    </span>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;

            // Re-highlight selected message if it exists
            if (currentMessageId) {
                const selectedItem = container.querySelector(`[data-message-id="${currentMessageId}"]`);
                if (selectedItem) {
                    selectedItem.classList.add('selected');
                }
            }
        }

        function highlightText(text, searchTerm) {
            if (!searchTerm || !text) return text;

            const regex = new RegExp(`(${escapeRegex(searchTerm)})`, 'gi');
            return text.replace(regex, '<span class="search-highlight">$1</span>');
        }

        function escapeRegex(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }

        async function fetchMessages() {
            // This function is now replaced by loadAllMessages for client-side filtering
            await loadAllMessages();
        }

        async function filterByLabel() {
            currentFilters.label_id = document.getElementById('labelFilter').value;
            await loadAllMessages();
            
            // Reapply search if there's a search term
            if (currentFilters.search) {
                searchMessages();
            }
        }

        async function viewMessage(messageId) {
            currentMessageId = messageId;

            // Update selected state
            document.querySelectorAll('.message-item').forEach(item => {
                item.classList.remove('selected');
            });
            const selectedItem = document.querySelector(`[data-message-id="${messageId}"]`);
            if (selectedItem) {
                selectedItem.classList.add('selected');
            }

            // Show loading
            document.getElementById('messageViewContent').innerHTML = `
                <div class="loading">
                    <div class="loading-spinner"></div>
                    <div class="loading-text">Loading email...</div>
                </div>
            `;

            try {
                const response = await fetch(`sent_history.php?action=get_message&id=${messageId}`);
                const data = await response.json();

                if (data.success) {
                    renderMessageView(data.message);
                } else {
                    document.getElementById('messageViewContent').innerHTML = `
                        <div class="empty-state">
                            <div class="empty-icon">⚠️</div>
                            <div class="empty-title">Error Loading Message</div>
                            <div class="empty-text">${data.error || 'Message not found'}</div>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading message:', error);
                showToast('Failed to load message', 'error');
            }
        }

        function renderMessageView(message) {
            const hasAttachments = message.attachments && message.attachments.length > 0;

            const html = `
                <div class="message-view-header">
                    <h2 class="message-view-title">${escapeHtml(message.subject)}</h2>
                    <div class="message-view-meta">
                        <div class="message-view-meta-item">
                            <span class="material-icons">person</span>
                            <span><span class="message-view-meta-label">To:</span> ${escapeHtml(message.recipient_email)}</span>
                        </div>
                        ${message.cc_list ? `
                            <div class="message-view-meta-item">
                                <span class="material-icons">group</span>
                                <span><span class="message-view-meta-label">CC:</span> ${escapeHtml(message.cc_list)}</span>
                            </div>
                        ` : ''}
                        <div class="message-view-meta-item">
                            <span class="material-icons">schedule</span>
                            <span>${formatDateLong(message.sent_at)}</span>
                        </div>
                        ${message.label_name ? `
                            <div class="message-view-meta-item">
                                <span class="badge badge-label" style="background: ${message.label_color || '#6b7280'}">
                                    ${escapeHtml(message.label_name)}
                                </span>
                            </div>
                        ` : ''}
                    </div>
                    <div class="message-view-actions">
                        <button class="btn btn-icon" onclick="window.open('view_sent_email.php?id=${message.id}', '_blank')" title="Open in new tab">
                            <span class="material-icons">open_in_new</span>
                        </button>
                        <button class="btn btn-icon" onclick="deleteMessageFromView()" title="Delete">
                            <span class="material-icons">delete</span>
                        </button>
                    </div>
                </div>
                <div class="message-view-body">
                    <div class="message-detail">
                        ${message.article_title ? `
                            <div class="article-title-display">${escapeHtml(message.article_title)}</div>
                        ` : ''}
                        ${message.body_html || nl2br(escapeHtml(message.body_text || 'No content'))}
                    </div>
                    ${hasAttachments ? `
                        <div class="attachments-section">
                            <div class="attachments-title">
                                <span class="material-icons">attach_file</span>
                                Attachments (${message.attachments.length})
                            </div>
                            <div class="attachments-grid">
                                ${message.attachments.map(att => `
                                    <div class="attachment-card" onclick="downloadAttachment('${att.file_path}', '${escapeHtml(att.original_filename)}')">
                                        <div class="attachment-icon">
                                            <span class="material-icons">insert_drive_file</span>
                                        </div>
                                        <div class="attachment-name">${escapeHtml(att.original_filename)}</div>
                                        <div class="attachment-size">${formatBytes(att.file_size)}</div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    ` : ''}
                </div>
            `;

            document.getElementById('messageViewContent').innerHTML = html;
        }

        async function deleteMessageFromView() {
            if (!currentMessageId) return;

            if (!confirm('Are you sure you want to delete this email?')) {
                return;
            }

            try {
                const response = await fetch(`sent_history.php?action=delete&id=${currentMessageId}`);
                const data = await response.json();

                if (data.success) {
                    showToast('Email deleted successfully', 'success');
                    currentMessageId = null;
                    document.getElementById('messageViewContent').innerHTML = `
                        <div class="empty-state">
                            <div class="empty-icon">📨</div>
                            <div class="empty-title">No message selected</div>
                            <div class="empty-text">Click on an email to view its contents</div>
                        </div>
                    `;
                    await loadAllMessages();
                    updateCounts();
                } else {
                    showToast('Failed to delete email', 'error');
                }
            } catch (error) {
                console.error('Error deleting message:', error);
                showToast('Failed to delete email', 'error');
            }
        }

        function downloadAttachment(filePath, filename) {
            const downloadUrl = `uploads/attachments/${filePath}`;
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.download = filename;
            link.click();
        }

        async function updateCounts() {
            try {
                const response = await fetch('sent_history.php?action=get_counts');
                const data = await response.json();

                if (data.success) {
                    document.getElementById('totalCount').textContent = data.total;
                    document.getElementById('labeledCount').textContent = data.labeled;
                    document.getElementById('unlabeledCount').textContent = data.unlabeled;
                }
            } catch (error) {
                console.error('Error updating counts:', error);
            }
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

        function stripTags(html) {
            if (!html) return '';
            const div = document.createElement('div');
            div.innerHTML = html;
            return div.textContent || div.innerText || '';
        }

        function nl2br(text) {
            if (!text) return '';
            return text.replace(/\n/g, '<br>');
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
    </script>
</body>

</html>