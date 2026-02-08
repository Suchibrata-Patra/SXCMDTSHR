<?php
// deleted_items.php - Premium Apple-Inspired Interface
session_start();
require 'config.php';
require 'db_config.php';

if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    header("Location: login.php");
    exit();
}

$userEmail = $_SESSION['smtp_user'];

// Get filter parameters
$filters = [
    'search' => $_GET['search'] ?? '',
    'recipient' => $_GET['recipient'] ?? '',
    'subject' => $_GET['subject'] ?? '',
    'label_id' => $_GET['label_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

// Function to fetch only deleted items (current_status = 0)
function getDeletedEmailsLocal($userEmail, $limit, $offset, $filters) {
    $pdo = getDatabaseConnection();
    if (!$pdo) return [];
    
    $sql = "SELECT se.*, l.label_name, l.label_color 
            FROM sent_emails se 
            LEFT JOIN labels l ON se.label_id = l.id 
            WHERE se.sender_email = :sender_email 
            AND se.current_status = 0";

    $params = [':sender_email' => $userEmail];

    if (!empty($filters['search'])) {
        $sql .= " AND (se.recipient_email LIKE :search OR se.subject LIKE :search OR se.message_body LIKE :search)";
        $params[':search'] = '%' . $filters['search'] . '%';
    }
    
    if (!empty($filters['recipient'])) {
        $sql .= " AND se.recipient_email LIKE :recipient";
        $params[':recipient'] = '%' . $filters['recipient'] . '%';
    }
    
    if (!empty($filters['subject'])) {
        $sql .= " AND se.subject LIKE :subject";
        $params[':subject'] = '%' . $filters['subject'] . '%';
    }
    
    if (!empty($filters['label_id'])) {
        if ($filters['label_id'] === 'unlabeled') {
            $sql .= " AND se.label_id IS NULL";
        } else {
            $sql .= " AND se.label_id = :label_id";
            $params[':label_id'] = $filters['label_id'];
        }
    }
    
    if (!empty($filters['date_from'])) {
        $sql .= " AND DATE(se.sent_at) >= :date_from";
        $params[':date_from'] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $sql .= " AND DATE(se.sent_at) <= :date_to";
        $params[':date_to'] = $filters['date_to'];
    }

    $sql .= " ORDER BY se.sent_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    foreach ($params as $key => $val) { $stmt->bindValue($key, $val); }
    $stmt->execute();
    return $stmt->fetchAll();
}

function getDeletedEmailCount($userEmail, $filters) {
    $pdo = getDatabaseConnection();
    if (!$pdo) return 0;
    
    $sql = "SELECT COUNT(*) as count FROM sent_emails se 
            WHERE se.sender_email = :sender_email 
            AND se.current_status = 0";
    
    $params = [':sender_email' => $userEmail];
    
    if (!empty($filters['search'])) {
        $sql .= " AND (se.recipient_email LIKE :search OR se.subject LIKE :search OR se.message_body LIKE :search)";
        $params[':search'] = '%' . $filters['search'] . '%';
    }
    
    if (!empty($filters['recipient'])) {
        $sql .= " AND se.recipient_email LIKE :recipient";
        $params[':recipient'] = '%' . $filters['recipient'] . '%';
    }
    
    if (!empty($filters['subject'])) {
        $sql .= " AND se.subject LIKE :subject";
        $params[':subject'] = '%' . $filters['subject'] . '%';
    }
    
    if (!empty($filters['label_id'])) {
        if ($filters['label_id'] === 'unlabeled') {
            $sql .= " AND se.label_id IS NULL";
        } else {
            $sql .= " AND se.label_id = :label_id";
            $params[':label_id'] = $filters['label_id'];
        }
    }
    
    if (!empty($filters['date_from'])) {
        $sql .= " AND DATE(se.sent_at) >= :date_from";
        $params[':date_from'] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $sql .= " AND DATE(se.sent_at) <= :date_to";
        $params[':date_to'] = $filters['date_to'];
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $result = $stmt->fetch();
    return $result['count'] ?? 0;
}

// Pagination & Data Retrieval
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

$sentEmails = getDeletedEmailsLocal($userEmail, $perPage, $offset, $filters);
$totalEmails = getDeletedEmailCount($userEmail, $filters);
$totalPages = ceil($totalEmails / $perPage);
$labels = getLabelCounts($userEmail);
$hasActiveFilters = !empty(array_filter($filters));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deleted Items — Mail</title>

    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        :root {
            /* Apple Premium Color Palette */
            --apple-off-white: #F5F5F7;
            --apple-white: #FFFFFF;
            --apple-glass-bg: rgba(255, 255, 255, 0.55);
            --apple-glass-border: rgba(255, 255, 255, 0.3);
            
            /* Apple SF Typography Colors */
            --sf-black: #000000;
            --sf-primary: #3A3A3C;
            --sf-secondary: #6E6E73;
            --sf-tertiary: #8E8E93;
            --sf-quaternary: #C7C7CC;
            
            /* Apple System Colors */
            --system-blue: #007AFF;
            --system-blue-subtle: rgba(0, 122, 255, 0.12);
            --system-green: #34C759;
            --system-green-subtle: rgba(52, 199, 89, 0.1);
            --system-green-border: rgba(52, 199, 89, 0.3);
            --system-red: #FF3B30;
            --system-red-subtle: rgba(255, 59, 48, 0.1);
            --system-red-border: rgba(255, 59, 48, 0.3);
            --system-gray-subtle: rgba(142, 142, 147, 0.08);
            
            /* Premium Shadows (Apple-calibrated) */
            --shadow-card: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-card-hover: 0 3px 6px rgba(0, 0, 0, 0.08);
            --shadow-glass: 0 4px 12px rgba(0, 0, 0, 0.06);
            --shadow-elevated: 0 8px 24px rgba(0, 0, 0, 0.12);
            
            /* Transitions (Apple-smooth) */
            --ease-out: cubic-bezier(0.25, 0.1, 0.25, 1);
            --duration-fast: 120ms;
            --duration-medium: 200ms;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "SF Pro Text", "Helvetica Neue", sans-serif;
            background: var(--apple-off-white);
            color: var(--sf-primary);
            display: flex;
            height: 100vh;
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
        }

        /* ========== GLASSMORPHIC SIDEBAR ========== */
        .sidebar {
            width: 240px;
            background: var(--apple-glass-bg);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border-right: 1px solid var(--apple-glass-border);
            box-shadow: var(--shadow-glass);
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            padding: 20px 12px;
        }

        .sidebar-header {
            padding: 8px 12px 20px;
        }

        .sidebar-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--sf-black);
            letter-spacing: -0.5px;
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 8px;
            color: var(--sf-primary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all var(--duration-fast) var(--ease-out);
            cursor: pointer;
        }

        .nav-item:hover {
            background: var(--system-gray-subtle);
        }

        .nav-item.active {
            background: var(--system-blue-subtle);
            color: var(--system-blue);
        }

        .nav-item .material-icons-round {
            font-size: 20px;
            color: inherit;
        }

        .nav-item .count {
            margin-left: auto;
            font-size: 13px;
            color: var(--sf-tertiary);
            font-weight: 500;
        }

        /* ========== MAIN WRAPPER ========== */
        #main-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }

        /* ========== GLASSMORPHIC HEADER ========== */
        .toolbar {
            background: var(--apple-glass-bg);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border-bottom: 1px solid var(--apple-glass-border);
            box-shadow: var(--shadow-glass);
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            min-height: 56px;
            z-index: 100;
        }

        .toolbar-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--sf-black);
            letter-spacing: -0.3px;
        }

        .search-container {
            flex: 1;
            max-width: 480px;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 8px 36px 8px 36px;
            background: var(--apple-white);
            border: 1px solid var(--sf-quaternary);
            border-radius: 8px;
            font-size: 14px;
            color: var(--sf-primary);
            transition: all var(--duration-fast) var(--ease-out);
            font-family: inherit;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--system-blue);
            box-shadow: 0 0 0 3px var(--system-blue-subtle);
        }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--sf-tertiary);
            font-size: 18px;
            pointer-events: none;
        }

        .clear-search {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--sf-tertiary);
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: all var(--duration-fast) var(--ease-out);
        }

        .clear-search:hover {
            background: var(--system-gray-subtle);
        }

        .toolbar-actions {
            display: flex;
            gap: 8px;
            margin-left: auto;
        }

        .icon-btn {
            background: none;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--sf-tertiary);
            cursor: pointer;
            transition: all var(--duration-fast) var(--ease-out);
        }

        .icon-btn:hover {
            background: var(--system-gray-subtle);
            color: var(--sf-primary);
        }

        .icon-btn.active {
            background: var(--system-blue-subtle);
            color: var(--system-blue);
        }

        /* ========== SELECTION BAR (Glassmorphic Float) ========== */
        .selection-bar {
            position: absolute;
            top: 72px;
            left: 50%;
            transform: translateX(-50%) translateY(-120%);
            background: var(--apple-glass-bg);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid var(--apple-glass-border);
            box-shadow: var(--shadow-elevated);
            border-radius: 16px;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            z-index: 200;
            opacity: 0;
            pointer-events: none;
            transition: all var(--duration-medium) var(--ease-out);
        }

        .selection-bar.active {
            opacity: 1;
            pointer-events: all;
            transform: translateX(-50%) translateY(0);
        }

        .selection-count {
            font-size: 14px;
            font-weight: 600;
            color: var(--sf-primary);
        }

        .selection-divider {
            width: 1px;
            height: 20px;
            background: var(--sf-quaternary);
        }

        .selection-actions {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--duration-fast) var(--ease-out);
            border: 1px solid;
            display: flex;
            align-items: center;
            gap: 6px;
            font-family: inherit;
        }

        .action-btn .material-icons-round {
            font-size: 16px;
        }

        .action-btn.restore {
            background: var(--system-green-subtle);
            border-color: var(--system-green-border);
            color: var(--system-green);
        }

        .action-btn.restore:hover {
            background: rgba(52, 199, 89, 0.18);
            box-shadow: 0 2px 4px rgba(52, 199, 89, 0.15);
        }

        .action-btn.delete {
            background: var(--system-red-subtle);
            border-color: var(--system-red-border);
            color: var(--system-red);
        }

        .action-btn.delete:hover {
            background: rgba(255, 59, 48, 0.18);
            box-shadow: 0 2px 4px rgba(255, 59, 48, 0.15);
        }

        .action-btn.clear {
            background: var(--apple-white);
            border-color: var(--sf-quaternary);
            color: var(--sf-secondary);
        }

        .action-btn.clear:hover {
            background: var(--system-gray-subtle);
        }

        /* ========== FILTER PANEL ========== */
        .filter-panel {
            position: absolute;
            top: 56px;
            right: 0;
            width: 320px;
            background: var(--apple-white);
            border-left: 1px solid var(--sf-quaternary);
            box-shadow: var(--shadow-elevated);
            height: calc(100vh - 56px);
            transform: translateX(100%);
            transition: transform var(--duration-medium) var(--ease-out);
            z-index: 150;
            overflow-y: auto;
        }

        .filter-panel.active {
            transform: translateX(0);
        }

        .filter-header {
            padding: 20px;
            border-bottom: 1px solid var(--sf-quaternary);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .filter-title {
            font-size: 17px;
            font-weight: 600;
            color: var(--sf-black);
        }

        .filter-body {
            padding: 20px;
        }

        .filter-group {
            margin-bottom: 24px;
        }

        .filter-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--sf-secondary);
            margin-bottom: 8px;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-input {
            width: 100%;
            padding: 10px 12px;
            background: var(--apple-off-white);
            border: 1px solid var(--sf-quaternary);
            border-radius: 8px;
            font-size: 14px;
            color: var(--sf-primary);
            font-family: inherit;
            transition: all var(--duration-fast) var(--ease-out);
        }

        .filter-input:focus {
            outline: none;
            border-color: var(--system-blue);
            background: var(--apple-white);
        }

        .filter-select {
            width: 100%;
            padding: 10px 12px;
            background: var(--apple-off-white);
            border: 1px solid var(--sf-quaternary);
            border-radius: 8px;
            font-size: 14px;
            color: var(--sf-primary);
            font-family: inherit;
            cursor: pointer;
            transition: all var(--duration-fast) var(--ease-out);
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--system-blue);
            background: var(--apple-white);
        }

        .filter-actions {
            display: flex;
            gap: 8px;
            padding: 20px;
            border-top: 1px solid var(--sf-quaternary);
            background: var(--apple-off-white);
        }

        .filter-btn {
            flex: 1;
            padding: 10px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--duration-fast) var(--ease-out);
            border: none;
            font-family: inherit;
        }

        .filter-btn.primary {
            background: var(--system-blue);
            color: var(--apple-white);
        }

        .filter-btn.primary:hover {
            background: #0066d6;
        }

        .filter-btn.secondary {
            background: var(--apple-white);
            color: var(--sf-secondary);
            border: 1px solid var(--sf-quaternary);
        }

        .filter-btn.secondary:hover {
            background: var(--system-gray-subtle);
        }

        /* ========== FILTER CHIPS ========== */
        .active-filters {
            padding: 12px 20px;
            background: var(--apple-white);
            border-bottom: 1px solid var(--sf-quaternary);
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .filter-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: rgba(0, 0, 0, 0.05);
            border-radius: 16px;
            font-size: 12px;
            font-weight: 500;
            color: var(--sf-secondary);
        }

        .filter-chip .material-icons-round {
            font-size: 16px;
            cursor: pointer;
            transition: all var(--duration-fast) var(--ease-out);
        }

        .filter-chip .material-icons-round:hover {
            color: var(--sf-primary);
        }

        /* ========== CONTENT AREA ========== */
        .content-area {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
        }

        /* Custom Scrollbar (macOS Style) */
        .content-area::-webkit-scrollbar {
            width: 10px;
        }

        .content-area::-webkit-scrollbar-track {
            background: transparent;
        }

        .content-area::-webkit-scrollbar-thumb {
            background: var(--sf-quaternary);
            border-radius: 10px;
            border: 2px solid var(--apple-off-white);
        }

        .content-area::-webkit-scrollbar-thumb:hover {
            background: var(--sf-tertiary);
        }

        /* ========== EMAIL LIST (Card-Based) ========== */
        .email-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .email-row {
            background: var(--apple-white);
            border-radius: 8px;
            box-shadow: var(--shadow-card);
            padding: 14px 16px;
            display: grid;
            grid-template-columns: 32px 180px 1fr auto 100px;
            gap: 16px;
            align-items: center;
            transition: all var(--duration-fast) var(--ease-out);
            cursor: pointer;
        }

        .email-row:hover {
            box-shadow: var(--shadow-card-hover);
            transform: translateY(-1px);
        }

        .email-row.selected {
            background: var(--system-blue-subtle);
            box-shadow: 0 0 0 2px var(--system-blue);
        }

        .email-checkbox-col {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .email-checkbox {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid var(--sf-quaternary);
            appearance: none;
            cursor: pointer;
            transition: all var(--duration-fast) var(--ease-out);
            position: relative;
        }

        .email-checkbox:checked {
            background: var(--system-blue);
            border-color: var(--system-blue);
        }

        .email-checkbox:checked::after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 12px;
            font-weight: bold;
        }

        .email-from {
            font-size: 14px;
            font-weight: 600;
            color: var(--sf-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .email-content {
            min-width: 0;
        }

        .email-subject {
            font-size: 15px;
            font-weight: 600;
            color: var(--sf-black);
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .email-preview {
            font-size: 13px;
            color: var(--sf-secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .email-label {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .label-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .email-meta {
            font-size: 13px;
            color: var(--sf-tertiary);
            text-align: right;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
        }

        .email-meta .material-icons-round {
            font-size: 16px;
        }

        /* ========== EMPTY STATE ========== */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 80px 20px;
            text-align: center;
        }

        .empty-icon {
            width: 80px;
            height: 80px;
            background: var(--system-gray-subtle);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .empty-icon .material-icons-round {
            font-size: 40px;
            color: var(--sf-tertiary);
        }

        .empty-state h3 {
            font-size: 20px;
            font-weight: 600;
            color: var(--sf-primary);
            margin-bottom: 8px;
        }

        .empty-state p {
            font-size: 14px;
            color: var(--sf-secondary);
            max-width: 400px;
            line-height: 1.5;
        }

        /* ========== PAGINATION ========== */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 4px;
            padding: 20px;
            background: var(--apple-white);
            border-top: 1px solid var(--sf-quaternary);
        }

        .page-button {
            min-width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            color: var(--sf-primary);
            text-decoration: none;
            transition: all var(--duration-fast) var(--ease-out);
        }

        .page-button:hover {
            background: var(--system-gray-subtle);
        }

        .page-button.active {
            background: var(--system-blue);
            color: var(--apple-white);
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 1024px) {
            .sidebar {
                width: 200px;
            }

            .email-row {
                grid-template-columns: 32px 140px 1fr auto 80px;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                position: absolute;
                left: -240px;
                z-index: 300;
                transition: left var(--duration-medium) var(--ease-out);
            }

            .sidebar.active {
                left: 0;
            }

            .email-row {
                grid-template-columns: 32px 1fr auto;
                gap: 12px;
            }

            .email-from {
                display: none;
            }

            .email-label {
                display: none;
            }
        }
    </style>
</head>

<body>
    <!-- Glassmorphic Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-title">Mail</div>
        </div>

        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <span class="material-icons-round">inbox</span>
                Inbox
                <span class="count">42</span>
            </a>
            <a href="sent.php" class="nav-item">
                <span class="material-icons-round">send</span>
                Sent
            </a>
            <a href="deleted_items.php" class="nav-item active">
                <span class="material-icons-round">delete</span>
                Deleted
                <span class="count"><?= $totalEmails ?></span>
            </a>
            <a href="labels.php" class="nav-item">
                <span class="material-icons-round">label</span>
                Labels
            </a>
        </nav>
    </div>

    <!-- Main Content Wrapper -->
    <div id="main-wrapper">
        <!-- Glassmorphic Header -->
        <div class="toolbar">
            <div class="toolbar-title">Deleted Items</div>

            <div class="search-container">
                <span class="material-icons-round search-icon">search</span>
                <input 
                    type="text" 
                    class="search-input" 
                    placeholder="Search deleted items..."
                    value="<?= htmlspecialchars($filters['search']) ?>"
                    onkeydown="if(event.key === 'Enter') handleSearch(this.value)"
                >
                <?php if (!empty($filters['search'])): ?>
                <button class="clear-search" onclick="clearSearch()">
                    <span class="material-icons-round">close</span>
                </button>
                <?php endif; ?>
            </div>

            <div class="toolbar-actions">
                <button class="icon-btn" onclick="toggleFilters()" title="Filters">
                    <span class="material-icons-round">tune</span>
                </button>
                <button class="icon-btn" onclick="location.reload()" title="Refresh">
                    <span class="material-icons-round">refresh</span>
                </button>
            </div>
        </div>

        <!-- Glassmorphic Selection Bar -->
        <div class="selection-bar" id="selectionBar">
            <span class="selection-count"><span id="selectedCount">0</span> selected</span>
            <div class="selection-divider"></div>
            <div class="selection-actions">
                <button class="action-btn restore" onclick="bulkRestore()">
                    <span class="material-icons-round">restore</span>
                    Restore
                </button>
                <button class="action-btn delete" onclick="bulkDeleteForever()">
                    <span class="material-icons-round">delete_forever</span>
                    Delete Forever
                </button>
                <button class="action-btn clear" onclick="clearSelection()">
                    Clear
                </button>
            </div>
        </div>

        <!-- Filter Panel -->
        <div class="filter-panel" id="filterPanel">
            <div class="filter-header">
                <div class="filter-title">Filters</div>
                <button class="icon-btn" onclick="toggleFilters()">
                    <span class="material-icons-round">close</span>
                </button>
            </div>

            <form method="GET" action="">
                <div class="filter-body">
                    <div class="filter-group">
                        <label class="filter-label">Recipient Email</label>
                        <input 
                            type="text" 
                            name="recipient" 
                            class="filter-input" 
                            placeholder="Filter by recipient..."
                            value="<?= htmlspecialchars($filters['recipient']) ?>"
                        >
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Subject Contains</label>
                        <input 
                            type="text" 
                            name="subject" 
                            class="filter-input" 
                            placeholder="Filter by subject..."
                            value="<?= htmlspecialchars($filters['subject']) ?>"
                        >
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Label</label>
                        <select name="label_id" class="filter-select">
                            <option value="">All labels</option>
                            <option value="unlabeled" <?= $filters['label_id'] === 'unlabeled' ? 'selected' : '' ?>>
                                Unlabeled
                            </option>
                            <?php foreach ($labels as $label): ?>
                            <option value="<?= $label['id'] ?>" <?= $filters['label_id'] == $label['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label['label_name']) ?> (<?= $label['count'] ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Date From</label>
                        <input 
                            type="date" 
                            name="date_from" 
                            class="filter-input"
                            value="<?= htmlspecialchars($filters['date_from']) ?>"
                        >
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Date To</label>
                        <input 
                            type="date" 
                            name="date_to" 
                            class="filter-input"
                            value="<?= htmlspecialchars($filters['date_to']) ?>"
                        >
                    </div>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="filter-btn primary">Apply Filters</button>
                    <button type="button" class="filter-btn secondary" onclick="clearForm()">Clear</button>
                </div>
            </form>
        </div>

        <!-- Active Filter Chips -->
        <?php if ($hasActiveFilters): ?>
        <div class="active-filters">
            <?php if (!empty($filters['search'])): ?>
            <div class="filter-chip">
                Search: <?= htmlspecialchars($filters['search']) ?>
                <span class="material-icons-round" onclick="removeFilter('search')">close</span>
            </div>
            <?php endif; ?>
            <?php if (!empty($filters['recipient'])): ?>
            <div class="filter-chip">
                To: <?= htmlspecialchars($filters['recipient']) ?>
                <span class="material-icons-round" onclick="removeFilter('recipient')">close</span>
            </div>
            <?php endif; ?>
            <?php if (!empty($filters['subject'])): ?>
            <div class="filter-chip">
                Subject: <?= htmlspecialchars($filters['subject']) ?>
                <span class="material-icons-round" onclick="removeFilter('subject')">close</span>
            </div>
            <?php endif; ?>
            <?php if (!empty($filters['label_id'])): ?>
            <div class="filter-chip">
                <?php
                    if ($filters['label_id'] === 'unlabeled') {
                        echo 'Unlabeled';
                    } else {
                        foreach ($labels as $label) {
                            if ($label['id'] == $filters['label_id']) {
                                echo htmlspecialchars($label['label_name']);
                                break;
                            }
                        }
                    }
                ?>
                <span class="material-icons-round" onclick="removeFilter('label_id')">close</span>
            </div>
            <?php endif; ?>
            <?php if (!empty($filters['date_from']) || !empty($filters['date_to'])): ?>
            <div class="filter-chip">
                <?= htmlspecialchars($filters['date_from'] ?: '...') ?> – <?= htmlspecialchars($filters['date_to'] ?: '...') ?>
                <span class="material-icons-round" onclick="clearDateFilters()">close</span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Content Area -->
        <div class="content-area">
            <?php if (empty($sentEmails)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <span class="material-icons-round">delete_outline</span>
                </div>
                <h3>No Deleted Items</h3>
                <p>Deleted emails appear here for 30 days before being permanently removed. They can be restored at any time during this period.</p>
            </div>
            <?php else: ?>
            <div class="email-list">
                <?php foreach ($sentEmails as $email): ?>
                <div class="email-row">
                    <div class="email-checkbox-col">
                        <input type="checkbox" class="email-checkbox" value="<?= $email['id'] ?>" 
                               onchange="handleCheckboxChange()">
                    </div>

                    <div class="email-from" onclick="openEmail(<?= $email['id'] ?>)">
                        <?= htmlspecialchars($email['recipient_email']) ?>
                    </div>

                    <div class="email-content" onclick="openEmail(<?= $email['id'] ?>)">
                        <div class="email-subject"><?= htmlspecialchars($email['subject']) ?></div>
                        <div class="email-preview">
                            <?= htmlspecialchars(mb_substr(strip_tags($email['message_body']), 0, 120)) ?>
                        </div>
                    </div>

                    <div class="email-label">
                        <?php if (!empty($email['label_color'])): ?>
                        <div class="label-dot" style="background: <?= htmlspecialchars($email['label_color']) ?>;" 
                             title="<?= htmlspecialchars($email['label_name']) ?>"></div>
                        <?php endif; ?>
                    </div>

                    <div class="email-meta" onclick="openEmail(<?= $email['id'] ?>)">
                        <?php if (!empty($email['attachment_names'])): ?>
                        <span class="material-icons-round">attach_file</span>
                        <?php endif; ?>
                        <?= date('M j', strtotime($email['sent_at'])) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php
            $currentParams = $_GET;
            
            if ($page > 1) {
                $currentParams['page'] = $page - 1;
                echo '<a href="?' . http_build_query($currentParams) . '" class="page-button">‹</a>';
            }
            
            for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++) {
                $currentParams['page'] = $i;
                echo '<a href="?' . http_build_query($currentParams) . '" class="page-button ' . ($i == $page ? 'active' : '') . '">' . $i . '</a>';
            }
            
            if ($page < $totalPages) {
                $currentParams['page'] = $page + 1;
                echo '<a href="?' . http_build_query($currentParams) . '" class="page-button">›</a>';
            }
            ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function handleCheckboxChange() {
            const checked = document.querySelectorAll('.email-checkbox:checked');
            const bar = document.getElementById('selectionBar');
            const count = document.getElementById('selectedCount');

            count.textContent = checked.length;

            if (checked.length > 0) {
                bar.classList.add('active');
                document.querySelectorAll('.email-row').forEach(row => {
                    const checkbox = row.querySelector('.email-checkbox');
                    row.classList.toggle('selected', checkbox.checked);
                });
            } else {
                bar.classList.remove('active');
                document.querySelectorAll('.email-row').forEach(row => {
                    row.classList.remove('selected');
                });
            }
        }

        function clearSelection() {
            document.querySelectorAll('.email-checkbox').forEach(cb => cb.checked = false);
            handleCheckboxChange();
        }

        async function bulkRestore() {
            const ids = Array.from(document.querySelectorAll('.email-checkbox:checked')).map(cb => cb.value);
            if (ids.length === 0) return;

            if (!confirm(`Restore ${ids.length} ${ids.length === 1 ? 'item' : 'items'}?`)) return;

            try {
                const response = await fetch('bulk_trash_actions.php', {
                    method: 'POST',
                    body: new URLSearchParams({ action: 'bulk_restore', email_ids: JSON.stringify(ids) })
                });
                const result = await response.json();
                if (result.success) {
                    setTimeout(() => location.reload(), 800);
                } else {
                    alert(result.message || 'Failed');
                }
            } catch (error) {
                alert('Error occurred');
            }
        }

        async function bulkDeleteForever() {
            const ids = Array.from(document.querySelectorAll('.email-checkbox:checked')).map(cb => cb.value);
            if (ids.length === 0) return;

            if (!confirm(`Permanently delete ${ids.length} ${ids.length === 1 ? 'item' : 'items'}? This cannot be undone.`)) return;

            try {
                const response = await fetch('bulk_trash_actions.php', {
                    method: 'POST',
                    body: new URLSearchParams({ action: 'bulk_delete_forever', email_ids: JSON.stringify(ids) })
                });
                const result = await response.json();
                if (result.success) {
                    setTimeout(() => location.reload(), 800);
                } else {
                    alert(result.message || 'Failed');
                }
            } catch (error) {
                alert('Error occurred');
            }
        }

        function openEmail(id) {
            window.open('view_sent_email.php?id=' + id, '_blank');
        }

        function toggleFilters() {
            document.getElementById('filterPanel').classList.toggle('active');
        }

        function handleSearch(value) {
            const url = new URL(window.location.href);
            value ? url.searchParams.set('search', value) : url.searchParams.delete('search');
            url.searchParams.delete('page');
            window.location.href = url.toString();
        }

        function clearSearch() {
            const input = document.querySelector('.search-input');
            input.value = '';
            handleSearch('');
        }

        function clearForm() {
            document.querySelector('#filterPanel form').reset();
        }

        function removeFilter(name) {
            const url = new URL(window.location.href);
            url.searchParams.delete(name);
            url.searchParams.delete('page');
            window.location.href = url.toString();
        }

        function clearDateFilters() {
            const url = new URL(window.location.href);
            url.searchParams.delete('date_from');
            url.searchParams.delete('date_to');
            url.searchParams.delete('page');
            window.location.href = url.toString();
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key === 'f') {
                e.preventDefault();
                document.querySelector('.search-input').focus();
            }
            
            if (e.key === 'Escape') {
                const filterPanel = document.getElementById('filterPanel');
                if (filterPanel.classList.contains('active')) {
                    toggleFilters();
                }
            }
        });

        // Smooth scroll behavior
        document.querySelector('.content-area').style.scrollBehavior = 'smooth';
    </script>
</body>

</html>