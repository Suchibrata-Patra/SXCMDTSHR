<?php
// deleted_items.php - Premium Trash Archive with Restore & Permanent Delete
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
    <title>Trash — SXC MDTS</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">

    <style>
        :root {
            /* Dark Sophisticated Palette */
            --primary: #0a0a0a;
            --primary-soft: #1a1a1a;
            --accent: #c41e3a;
            --accent-hover: #a01629;
            --accent-light: #fef2f4;
            
            /* Trash-specific colors */
            --trash-primary: #dc2626;
            --trash-hover: #b91c1c;
            --trash-light: #fee2e2;
            --restore-primary: #059669;
            --restore-hover: #047857;
            --restore-light: #d1fae5;
            
            /* Grays */
            --gray-50: #fafafa;
            --gray-100: #f4f4f5;
            --gray-200: #e4e4e7;
            --gray-300: #d4d4d8;
            --gray-400: #a1a1aa;
            --gray-500: #71717a;
            --gray-600: #52525b;
            --gray-700: #3f3f46;
            --gray-800: #27272a;
            --gray-900: #18181b;
            
            /* Semantic */
            --background: #ffffff;
            --surface: #fafafa;
            --border: #e4e4e7;
            --text-primary: #0a0a0a;
            --text-secondary: #52525b;
            --text-tertiary: #a1a1aa;
            
            /* Effects */
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
            
            --radius-sm: 6px;
            --radius: 8px;
            --radius-lg: 12px;
            --radius-xl: 16px;
            
            --transition: cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--surface);
            color: var(--text-primary);
            display: flex;
            height: 100vh;
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Main Wrapper */
        #main-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }

        /* ========== HEADER ========== */
        .page-header {
            background: linear-gradient(135deg, #ffffff 0%, #fafafa 100%);
            border-bottom: 1px solid var(--border);
            padding: 28px 36px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--trash-primary) 0%, var(--accent) 100%);
        }

        .header-left {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .page-title {
            font-family: 'Instrument Serif', serif;
            font-size: 32px;
            font-weight: 400;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 16px;
            letter-spacing: -0.02em;
        }
        
        .page-title .material-icons-round {
            font-size: 36px;
            color: var(--trash-primary);
            animation: gentleFloat 3s ease-in-out infinite;
        }
        
        @keyframes gentleFloat {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-4px); }
        }

        .page-subtitle {
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 400;
            padding-left: 52px;
        }

        .email-count-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            background: var(--trash-light);
            border: 1px solid rgba(220, 38, 38, 0.2);
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            color: var(--trash-primary);
        }

        /* Header Actions */
        .header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .search-container {
            position: relative;
        }

        .search-input {
            width: 340px;
            padding: 11px 18px 11px 46px;
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            color: var(--text-primary);
            background: var(--background);
            transition: all 0.3s var(--transition);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--trash-primary);
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
            transform: translateY(-1px);
        }

        .search-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-tertiary);
            pointer-events: none;
            font-size: 20px;
        }

        .btn-filter-toggle {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 20px;
            background: var(--background);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            color: var(--text-primary);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s var(--transition);
        }

        .btn-filter-toggle:hover {
            background: var(--gray-50);
            border-color: var(--gray-300);
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }

        .btn-filter-toggle.active {
            background: var(--accent-light);
            border-color: var(--accent);
            color: var(--accent);
        }

        /* ========== BULK ACTION TOOLBAR ========== */
        .bulk-action-toolbar {
            background: linear-gradient(135deg, #1a1a1a 0%, #0a0a0a 100%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 16px 36px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            transform: translateY(-100%);
            opacity: 0;
            transition: all 0.4s var(--transition);
            box-shadow: var(--shadow-lg);
        }

        .bulk-action-toolbar.active {
            transform: translateY(0);
            opacity: 1;
        }

        .toolbar-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .selection-info {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #ffffff;
            font-weight: 500;
            font-size: 14px;
        }

        .selection-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 28px;
            height: 28px;
            padding: 0 10px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 14px;
            font-weight: 700;
            font-size: 13px;
        }

        .toolbar-actions {
            display: flex;
            gap: 10px;
        }

        .btn-toolbar {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: none;
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s var(--transition);
            font-family: 'Inter', sans-serif;
        }

        .btn-toolbar-restore {
            background: var(--restore-primary);
            color: #ffffff;
        }

        .btn-toolbar-restore:hover {
            background: var(--restore-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(5, 150, 105, 0.3);
        }

        .btn-toolbar-delete {
            background: var(--trash-primary);
            color: #ffffff;
        }

        .btn-toolbar-delete:hover {
            background: var(--trash-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(220, 38, 38, 0.3);
        }

        .btn-toolbar-clear {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-toolbar-clear:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        /* ========== FILTER PANEL ========== */
        .filter-panel {
            background: var(--background);
            border-bottom: 1px solid var(--border);
            padding: 0;
            max-height: 0;
            overflow: hidden;
            transition: all 0.4s var(--transition);
        }

        .filter-panel.active {
            max-height: 400px;
            padding: 24px 36px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .filter-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-input,
        .filter-select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            color: var(--text-primary);
            background: var(--background);
            transition: all 0.2s var(--transition);
        }

        .filter-input:focus,
        .filter-select:focus {
            outline: none;
            border-color: var(--trash-primary);
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn-apply,
        .btn-clear {
            padding: 10px 24px;
            border: none;
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s var(--transition);
            font-family: 'Inter', sans-serif;
        }

        .btn-apply {
            background: var(--trash-primary);
            color: #ffffff;
        }

        .btn-apply:hover {
            background: var(--trash-hover);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-clear {
            background: var(--gray-100);
            color: var(--text-primary);
        }

        .btn-clear:hover {
            background: var(--gray-200);
        }

        /* Active Filters */
        .active-filters {
            padding: 16px 36px;
            background: var(--gray-50);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .filter-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            background: var(--background);
            border: 1px solid var(--border);
            border-radius: 20px;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .filter-badge-close {
            cursor: pointer;
            color: var(--text-tertiary);
            transition: color 0.2s;
        }

        .filter-badge-close:hover {
            color: var(--trash-primary);
        }

        .btn-clear-all {
            padding: 6px 16px;
            background: var(--trash-primary);
            color: #ffffff;
            border: none;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s var(--transition);
        }

        .btn-clear-all:hover {
            background: var(--trash-hover);
            transform: translateY(-1px);
        }

        /* ========== EMAIL LIST ========== */
        .email-list-container {
            flex: 1;
            overflow-y: auto;
            background: var(--surface);
        }

        .email-list {
            padding: 8px 0;
        }

        .email-item {
            display: grid;
            grid-template-columns: 48px 260px 1fr auto 120px;
            gap: 20px;
            align-items: center;
            padding: 16px 36px;
            background: var(--background);
            border-bottom: 1px solid var(--border);
            transition: all 0.2s var(--transition);
            cursor: pointer;
            position: relative;
        }

        .email-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 0;
            background: var(--trash-primary);
            transition: width 0.3s var(--transition);
        }

        .email-item:hover {
            background: var(--gray-50);
            transform: translateX(4px);
        }

        .email-item:hover::before {
            width: 3px;
        }

        .email-item.selected {
            background: var(--trash-light);
            border-left: 3px solid var(--trash-primary);
        }

        /* Checkbox Column */
        .col-checkbox {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .email-checkbox {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: var(--trash-primary);
        }

        /* Recipient Column */
        .col-recipient {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Subject & Preview Column */
        .col-subject {
            min-width: 0;
        }

        .subject-text {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-primary);
            margin-right: 8px;
        }

        .snippet-text {
            color: var(--text-tertiary);
            font-size: 13px;
            font-weight: 400;
        }

        /* Label Column */
        .col-label {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .label-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Date Column */
        .col-date {
            text-align: right;
            font-size: 13px;
            color: var(--text-secondary);
            white-space: nowrap;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
        }

        .attachment-icon {
            color: var(--text-tertiary);
            font-size: 14px;
        }

        /* ========== EMPTY STATE ========== */
        .empty-state {
            text-align: center;
            padding: 120px 40px;
            color: var(--text-secondary);
        }

        .empty-state-icon {
            width: 120px;
            height: 120px;
            margin: 0 auto 24px;
            background: linear-gradient(135deg, var(--trash-light) 0%, var(--gray-100) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: pulseGlow 3s ease-in-out infinite;
        }

        .empty-state-icon .material-icons-round {
            font-size: 64px;
            color: var(--trash-primary);
        }

        @keyframes pulseGlow {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.2);
            }
            50% {
                transform: scale(1.05);
                box-shadow: 0 0 0 20px rgba(220, 38, 38, 0);
            }
        }

        .empty-state h3 {
            font-size: 24px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 12px;
        }

        .empty-state p {
            font-size: 15px;
            color: var(--text-secondary);
            max-width: 400px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* ========== PAGINATION ========== */
        .pagination {
            display: flex;
            gap: 8px;
            justify-content: center;
            padding: 24px 36px;
            background: var(--background);
            border-top: 1px solid var(--border);
        }

        .page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
            padding: 0 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            color: var(--text-primary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s var(--transition);
        }

        .page-link:hover {
            background: var(--gray-50);
            border-color: var(--gray-300);
            transform: translateY(-1px);
        }

        .page-link.active {
            background: var(--trash-primary);
            color: #ffffff;
            border-color: var(--trash-primary);
        }

        /* ========== SCROLLBAR ========== */
        .email-list-container::-webkit-scrollbar {
            width: 10px;
        }

        .email-list-container::-webkit-scrollbar-track {
            background: var(--gray-100);
        }

        .email-list-container::-webkit-scrollbar-thumb {
            background: var(--gray-400);
            border-radius: 10px;
        }

        .email-list-container::-webkit-scrollbar-thumb:hover {
            background: var(--gray-500);
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 1200px) {
            .filter-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .email-item {
                grid-template-columns: 48px 1fr 80px;
                gap: 12px;
            }

            .col-recipient,
            .col-label {
                display: none;
            }

            .filter-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <div id="main-wrapper">
        <!-- Header -->
        <div class="page-header">
            <div class="header-left">
                <h1 class="page-title">
                    <span class="material-icons-round">delete</span>
                    Trash
                </h1>
                <p class="page-subtitle">
                    Items deleted 30 days ago are permanently removed
                    <span class="email-count-badge">
                        <span class="material-icons-round">inventory_2</span>
                        <?= $totalEmails ?> items
                    </span>
                </p>
            </div>

            <div class="header-actions">
                <div class="search-container">
                    <span class="material-icons-round search-icon">search</span>
                    <input type="text" class="search-input" placeholder="Search trash..." 
                           value="<?= htmlspecialchars($filters['search']) ?>"
                           onchange="handleSearch(this.value)">
                </div>
                <button class="btn-filter-toggle <?= $hasActiveFilters ? 'active' : '' ?>" onclick="toggleFilters()">
                    <span class="material-icons-round">tune</span>
                    Filters
                    <?php if ($hasActiveFilters): ?>
                    <span style="background: var(--accent); color: #fff; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 700;">
                        <?= count(array_filter($filters)) ?>
                    </span>
                    <?php endif; ?>
                </button>
            </div>
        </div>

        <!-- Bulk Action Toolbar -->
        <div class="bulk-action-toolbar" id="bulkActionToolbar">
            <div class="toolbar-left">
                <div class="selection-info">
                    <span class="selection-count" id="selectedCount">0</span>
                    <span>items selected</span>
                </div>
            </div>
            <div class="toolbar-actions">
                <button class="btn-toolbar btn-toolbar-restore" onclick="bulkRestore()">
                    <span class="material-icons-round" style="font-size: 18px;">restore</span>
                    Restore
                </button>
                <button class="btn-toolbar btn-toolbar-delete" onclick="bulkDeleteForever()">
                    <span class="material-icons-round" style="font-size: 18px;">delete_forever</span>
                    Delete Forever
                </button>
                <button class="btn-toolbar btn-toolbar-clear" onclick="clearSelection()">
                    <span class="material-icons-round" style="font-size: 18px;">close</span>
                    Clear
                </button>
            </div>
        </div>

        <!-- Filter Panel -->
        <div class="filter-panel <?= $hasActiveFilters ? 'active' : '' ?>" id="filterPanel">
            <form method="GET" action="deleted_items.php">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label>Recipient Email</label>
                        <input type="text" name="recipient" class="filter-input" 
                               placeholder="Enter recipient email"
                               value="<?= htmlspecialchars($filters['recipient']) ?>">
                    </div>
                    <div class="filter-group">
                        <label>Subject</label>
                        <input type="text" name="subject" class="filter-input" 
                               placeholder="Enter subject"
                               value="<?= htmlspecialchars($filters['subject']) ?>">
                    </div>
                    <div class="filter-group">
                        <label>Label</label>
                        <select name="label_id" class="filter-select">
                            <option value="">All Labels</option>
                            <option value="unlabeled" <?= $filters['label_id'] === 'unlabeled' ? 'selected' : '' ?>>
                                Unlabeled
                            </option>
                            <?php foreach ($labels as $label): ?>
                            <option value="<?= $label['id'] ?>" <?= $filters['label_id'] == $label['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label['label_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Date From</label>
                        <input type="date" name="date_from" class="filter-input" 
                               value="<?= htmlspecialchars($filters['date_from']) ?>">
                    </div>
                    <div class="filter-group">
                        <label>Date To</label>
                        <input type="date" name="date_to" class="filter-input" 
                               value="<?= htmlspecialchars($filters['date_to']) ?>">
                    </div>
                </div>
                <div class="filter-actions">
                    <button type="button" class="btn-clear" onclick="clearForm()">Clear</button>
                    <button type="submit" class="btn-apply">Apply Filters</button>
                </div>
            </form>
        </div>

        <!-- Active Filters -->
        <?php if ($hasActiveFilters): ?>
        <div class="active-filters">
            <span style="font-size: 13px; font-weight: 600; color: var(--text-secondary);">Active Filters:</span>
            <?php if (!empty($filters['search'])): ?>
            <div class="filter-badge">
                Search: <?= htmlspecialchars($filters['search']) ?>
                <span class="material-icons-round filter-badge-close" onclick="removeFilter('search')" style="font-size: 16px;">close</span>
            </div>
            <?php endif; ?>
            <?php if (!empty($filters['recipient'])): ?>
            <div class="filter-badge">
                Recipient: <?= htmlspecialchars($filters['recipient']) ?>
                <span class="material-icons-round filter-badge-close" onclick="removeFilter('recipient')" style="font-size: 16px;">close</span>
            </div>
            <?php endif; ?>
            <?php if (!empty($filters['subject'])): ?>
            <div class="filter-badge">
                Subject: <?= htmlspecialchars($filters['subject']) ?>
                <span class="material-icons-round filter-badge-close" onclick="removeFilter('subject')" style="font-size: 16px;">close</span>
            </div>
            <?php endif; ?>
            <?php if (!empty($filters['label_id'])): ?>
            <div class="filter-badge">
                Label: <?php 
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
                <span class="material-icons-round filter-badge-close" onclick="removeFilter('label_id')" style="font-size: 16px;">close</span>
            </div>
            <?php endif; ?>
            <?php if (!empty($filters['date_from']) || !empty($filters['date_to'])): ?>
            <div class="filter-badge">
                Date Range: <?= htmlspecialchars($filters['date_from'] ?: 'Any') ?> - <?= htmlspecialchars($filters['date_to'] ?: 'Any') ?>
                <span class="material-icons-round filter-badge-close" onclick="clearDateFilters()" style="font-size: 16px;">close</span>
            </div>
            <?php endif; ?>
            <button class="btn-clear-all" onclick="clearAllFilters()">
                <span class="material-icons-round" style="font-size: 14px; vertical-align: middle;">clear_all</span>
                Clear All
            </button>
        </div>
        <?php endif; ?>

        <!-- Email List -->
        <div class="email-list-container">
            <?php if (empty($sentEmails)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <span class="material-icons-round">delete_sweep</span>
                </div>
                <h3>Trash is empty</h3>
                <p>Items you delete will appear here. They'll be permanently removed after 30 days.</p>
            </div>
            <?php else: ?>
            <div class="email-list">
                <?php foreach ($sentEmails as $email): ?>
                <div class="email-item">
                    <div class="col-checkbox">
                        <input type="checkbox" class="email-checkbox" value="<?= $email['id'] ?>" 
                               onchange="handleCheckboxChange()">
                    </div>

                    <div class="col-recipient" onclick="openEmail(<?= $email['id'] ?>)">
                        <?= htmlspecialchars($email['recipient_email']) ?>
                    </div>

                    <div class="col-subject" onclick="openEmail(<?= $email['id'] ?>)">
                        <span class="subject-text"><?= htmlspecialchars($email['subject']) ?></span>
                        <span class="snippet-text">
                            — <?= htmlspecialchars(mb_substr(strip_tags($email['message_body']), 0, 100)) ?>...
                        </span>
                    </div>

                    <div class="col-label">
                        <?php if (!empty($email['label_name'])): ?>
                        <span class="label-badge" style="background: <?= htmlspecialchars($email['label_color']) ?>;">
                            <?= htmlspecialchars($email['label_name']) ?>
                        </span>
                        <?php endif; ?>
                    </div>

                    <div class="col-date" onclick="openEmail(<?= $email['id'] ?>)">
                        <?php if (!empty($email['attachment_names'])): ?>
                        <i class="fa-solid fa-paperclip attachment-icon"></i>
                        <?php endif; ?>
                        <?= date('M j, Y', strtotime($email['sent_at'])) ?>
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
            
            if ($page > 3) {
                $currentParams['page'] = 1;
                echo '<a href="?' . http_build_query($currentParams) . '" class="page-link">1</a>';
                if ($page > 4) {
                    echo '<span class="page-link" style="border: none; background: none;">...</span>';
                }
            }
            
            for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++) {
                $currentParams['page'] = $i;
                $queryString = http_build_query($currentParams);
                echo '<a href="?' . $queryString . '" class="page-link ' . ($i == $page ? 'active' : '') . '">' . $i . '</a>';
            }
            
            if ($page < $totalPages - 2) {
                if ($page < $totalPages - 3) {
                    echo '<span class="page-link" style="border: none; background: none;">...</span>';
                }
                $currentParams['page'] = $totalPages;
                echo '<a href="?' . http_build_query($currentParams) . '" class="page-link">' . $totalPages . '</a>';
            }
            ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Checkbox Selection Management
        function handleCheckboxChange() {
            const checkedBoxes = document.querySelectorAll('.email-checkbox:checked');
            const toolbar = document.getElementById('bulkActionToolbar');
            const selectedCount = document.getElementById('selectedCount');

            selectedCount.textContent = checkedBoxes.length;

            if (checkedBoxes.length > 0) {
                toolbar.classList.add('active');
                document.querySelectorAll('.email-item').forEach(item => {
                    const checkbox = item.querySelector('.email-checkbox');
                    if (checkbox.checked) {
                        item.classList.add('selected');
                    } else {
                        item.classList.remove('selected');
                    }
                });
            } else {
                toolbar.classList.remove('active');
                document.querySelectorAll('.email-item').forEach(item => {
                    item.classList.remove('selected');
                });
            }
        }

        function clearSelection() {
            document.querySelectorAll('.email-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            handleCheckboxChange();
        }

        // Bulk Restore - Change current_status from 0 to 1
        async function bulkRestore() {
            const checkedBoxes = document.querySelectorAll('.email-checkbox:checked');
            const emailIds = Array.from(checkedBoxes).map(cb => cb.value);

            if (emailIds.length === 0) {
                alert('Please select at least one email to restore');
                return;
            }

            if (!confirm(`Restore ${emailIds.length} email(s) back to inbox?`)) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'bulk_restore');
                formData.append('email_ids', JSON.stringify(emailIds));

                const response = await fetch('bulk_trash_actions.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    showNotification('Emails restored successfully', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    alert('Failed to restore emails: ' + (result.message || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while restoring emails');
            }
        }

        // Bulk Delete Forever - Change current_status from 0 to 2
        async function bulkDeleteForever() {
            const checkedBoxes = document.querySelectorAll('.email-checkbox:checked');
            const emailIds = Array.from(checkedBoxes).map(cb => cb.value);

            if (emailIds.length === 0) {
                alert('Please select at least one email to delete permanently');
                return;
            }

            if (!confirm(`⚠️ PERMANENT DELETE\n\nDelete ${emailIds.length} email(s) forever? This action cannot be undone!`)) {
                return;
            }

            // Double confirmation for permanent deletion
            if (!confirm('Are you absolutely sure? This will permanently delete the selected emails.')) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'bulk_delete_forever');
                formData.append('email_ids', JSON.stringify(emailIds));

                const response = await fetch('bulk_trash_actions.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    showNotification('Emails permanently deleted', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    alert('Failed to delete emails: ' + (result.message || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while deleting emails');
            }
        }

        function openEmail(emailId) {
            window.open('view_sent_email.php?id=' + emailId, '_blank');
        }

        function toggleFilters() {
            const panel = document.getElementById('filterPanel');
            const btn = document.querySelector('.btn-filter-toggle');
            panel.classList.toggle('active');
            btn.classList.toggle('active');
        }

        function handleSearch(value) {
            const url = new URL(window.location.href);
            if (value) {
                url.searchParams.set('search', value);
            } else {
                url.searchParams.delete('search');
            }
            url.searchParams.delete('page');
            window.location.href = url.toString();
        }

        function clearForm() {
            const form = document.querySelector('#filterPanel form');
            form.reset();
        }

        function removeFilter(filterName) {
            const url = new URL(window.location.href);
            url.searchParams.delete(filterName);
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

        function clearAllFilters() {
            window.location.href = 'deleted_items.php';
        }

        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 24px;
                right: 24px;
                padding: 16px 24px;
                background: ${type === 'success' ? '#059669' : '#dc2626'};
                color: white;
                border-radius: 12px;
                box-shadow: 0 10px 25px rgba(0,0,0,0.2);
                z-index: 10000;
                font-weight: 600;
                animation: slideIn 0.3s ease-out;
            `;
            notification.textContent = message;
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease-out';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + K to focus search
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                document.querySelector('.search-input').focus();
            }

            // Ctrl/Cmd + F to toggle filters
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                toggleFilters();
            }

            // R key for restore
            if (e.key === 'r' || e.key === 'R') {
                const checkedBoxes = document.querySelectorAll('.email-checkbox:checked');
                if (checkedBoxes.length > 0 && !e.target.matches('input[type="text"], input[type="date"], select')) {
                    e.preventDefault();
                    bulkRestore();
                }
            }

            // Delete key for permanent delete
            if (e.key === 'Delete' || e.key === 'Backspace') {
                const checkedBoxes = document.querySelectorAll('.email-checkbox:checked');
                if (checkedBoxes.length > 0 && !e.target.matches('input[type="text"], input[type="date"], select')) {
                    e.preventDefault();
                    bulkDeleteForever();
                }
            }
        });

        // Add CSS animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(400px); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(400px); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>

</html>