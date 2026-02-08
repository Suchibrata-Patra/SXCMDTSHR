<?php
// deleted_items.php - Premium Email Archive with Advanced Filtering & Gmail-style Bulk Actions
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

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Get filtered emails (only deleted ones with current_status = 0)
$sentEmails = getDeletedEmailsLocal($userEmail, $perPage, $offset, $filters);
$totalEmails = getDeletedEmailCount($userEmail, $filters);
$totalPages = ceil($totalEmails / $perPage);

// Get all labels and counts
$labels = getLabelCounts($userEmail);
$unlabeledCount = getUnlabeledEmailCount($userEmail);

// Check if filters are active
$hasActiveFilters = !empty(array_filter($filters));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deleted Items — SXC MDTS</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Instrument+Serif:ital@0;1&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">

    <style>
        :root {
            /* Color Palette */
            --primary: #0a0a0a;
            --primary-soft: #1a1a1a;
            --accent: #c41e3a;
            --accent-hover: #a01629;
            --accent-light: #fef2f4;

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

            /* Semantic Colors */
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

        /* Main Content Area */
        #main-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }

        /* ========== HEADER ========== */
        .page-header {
            background: var(--background);
            border-bottom: 1px solid var(--border);
            padding: 24px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }

        .header-left {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .page-title {
            font-family: 'Instrument Serif', serif;
            font-size: 28px;
            font-weight: 400;
            color: var(--text-primary);
            letter-spacing: 0.7rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-subtitle {
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 400;
        }

        .email-count-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            background: var(--gray-100);
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
        }

        .email-count-badge .material-icons-round {
            font-size: 16px;
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
            width: 320px;
            padding: 10px 16px 10px 44px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            color: var(--text-primary);
            background: var(--background);
            transition: all 0.2s var(--transition);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(196, 30, 58, 0.1);
        }

        .search-input::placeholder {
            color: var(--text-tertiary);
        }

        .search-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-tertiary);
            pointer-events: none;
        }

        .btn-filter-toggle {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: var(--background);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            color: var(--text-primary);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s var(--transition);
        }

        .btn-filter-toggle:hover {
            background: var(--gray-50);
        }

        .btn-filter-toggle.active {
            background: var(--accent-light);
            border-color: var(--accent);
            color: var(--accent);
        }

        .btn-filter-toggle .material-icons-round {
            font-size: 18px;
        }

        /* ========== FILTER PANEL ========== */
        .filter-panel {
            position: absolute;
            top: 100%;
            right: 32px;
            margin-top: 8px;
            width: 420px;
            background: var(--background);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            z-index: 1000;
            max-height: 0;
            overflow: hidden;
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.3s var(--transition);
        }

        .filter-panel.active {
            max-height: 600px;
            opacity: 1;
            transform: translateY(0);
        }

        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
        }

        .filter-header h3 {
            font-size: 16px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .btn-clear-all {
            font-size: 13px;
            color: var(--accent);
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: opacity 0.2s;
        }

        .btn-clear-all:hover {
            opacity: 0.8;
        }

        .filter-body {
            padding: 24px;
            max-height: 450px;
            overflow-y: auto;
        }

        .filter-group {
            margin-bottom: 24px;
        }

        .filter-group:last-child {
            margin-bottom: 0;
        }

        .filter-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .filter-group input[type="text"],
        .filter-group input[type="date"],
        .filter-group select {
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

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(196, 30, 58, 0.1);
        }

        .date-range-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .filter-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }

        .btn-apply-filter {
            flex: 1;
            padding: 10px 20px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-apply-filter:hover {
            background: var(--accent-hover);
        }

        .btn-reset-filter {
            padding: 10px 20px;
            background: var(--gray-100);
            color: var(--text-primary);
            border: none;
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-reset-filter:hover {
            background: var(--gray-200);
        }

        /* ========== ACTIVE FILTERS ========== */
        .active-filters {
            background: var(--background);
            border-bottom: 1px solid var(--border);
            padding: 16px 32px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .active-filters-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .filter-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            background: var(--accent-light);
            border: 1px solid rgba(196, 30, 58, 0.2);
            border-radius: 20px;
            font-size: 13px;
            color: var(--accent);
            font-weight: 500;
        }

        .filter-chip .material-icons-round {
            font-size: 16px;
            cursor: pointer;
            transition: opacity 0.2s;
        }

        .filter-chip .material-icons-round:hover {
            opacity: 0.7;
        }

        /* ========== BULK ACTION TOOLBAR ========== */
        .bulk-action-toolbar {
            background: var(--background);
            border-bottom: 1px solid var(--border);
            padding: 16px 32px;
            display: flex;
            align-items: center;
            gap: 16px;
            opacity: 0;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s var(--transition);
        }

        .bulk-action-toolbar.active {
            opacity: 1;
            max-height: 80px;
        }

        .toolbar-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .selection-count {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .toolbar-actions {
            display: flex;
            gap: 8px;
        }

        .btn-toolbar {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: var(--background);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            color: var(--text-primary);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s var(--transition);
        }

        .btn-toolbar:hover {
            background: var(--gray-50);
        }

        .btn-toolbar.danger {
            color: #dc2626;
            border-color: rgba(220, 38, 38, 0.3);
        }

        .btn-toolbar.danger:hover {
            background: rgba(220, 38, 38, 0.05);
        }

        .btn-toolbar .material-icons-round {
            font-size: 18px;
        }

        /* ========== EMAIL LIST ========== */
        .content-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .email-list-container {
            flex: 1;
            overflow-y: auto;
            background: var(--background);
        }

        .email-list-header {
            display: grid;
            grid-template-columns: 48px 200px 1fr 120px 160px;
            gap: 16px;
            padding: 12px 32px;
            background: var(--gray-50);
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 10;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .email-item {
            display: grid;
            grid-template-columns: 48px 200px 1fr 120px 160px;
            gap: 16px;
            padding: 16px 32px;
            border-bottom: 1px solid var(--border);
            transition: all 0.15s var(--transition);
            cursor: pointer;
            align-items: center;
        }

        .email-item:hover {
            background: var(--gray-50);
        }

        .email-item.selected {
            background: var(--accent-light);
        }

        .col-checkbox {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .email-checkbox {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--accent);
        }

        .col-recipient {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .col-subject {
            display: flex;
            flex-direction: column;
            gap: 4px;
            overflow: hidden;
        }

        .subject-text {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .preview-text {
            font-size: 13px;
            color: var(--text-secondary);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .col-label {
            display: flex;
            align-items: center;
            gap: 8px;
            position: relative;
        }

        .label-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .label-name {
            font-size: 13px;
            color: var(--text-secondary);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .label-dropdown {
            position: relative;
        }

        .label-dropdown-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: var(--background);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 13px;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s;
        }

        .label-dropdown-btn:hover {
            background: var(--gray-50);
        }

        .label-dropdown-btn .material-icons-round {
            font-size: 16px;
        }

        .label-dropdown-content {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            margin-top: 4px;
            min-width: 200px;
            background: var(--background);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            z-index: 100;
        }

        .label-dropdown:hover .label-dropdown-content {
            display: block;
        }

        .label-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            font-size: 14px;
            color: var(--text-primary);
            cursor: pointer;
            transition: background 0.2s;
        }

        .label-option:hover {
            background: var(--gray-50);
        }

        .label-color-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .col-date {
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: flex-end;
            font-size: 13px;
            color: var(--text-secondary);
            white-space: nowrap;
        }

        .attachment-icon {
            font-size: 14px;
            color: var(--text-tertiary);
        }

        /* ========== EMPTY STATE ========== */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 80px 32px;
            text-align: center;
        }

        .empty-state .material-icons-round {
            font-size: 64px;
            color: var(--text-tertiary);
            margin-bottom: 16px;
        }

        .empty-state h3 {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .empty-state p {
            font-size: 14px;
            color: var(--text-secondary);
            max-width: 400px;
        }

        /* ========== PAGINATION ========== */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            padding: 24px 32px;
            background: var(--background);
            border-top: 1px solid var(--border);
        }

        .page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 12px;
            background: var(--background);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
            text-decoration: none;
            transition: all 0.2s var(--transition);
        }

        .page-link:hover {
            background: var(--gray-50);
            border-color: var(--gray-300);
        }

        .page-link.active {
            background: var(--accent);
            border-color: var(--accent);
            color: white;
        }

        .page-link.active:hover {
            background: var(--accent-hover);
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
                    DELETED ITEMS
                </h1>
                <p class="page-subtitle">
                    <span class="email-count-badge">
                        <span class="material-icons-round">delete</span>
                        <?= number_format($totalEmails) ?> deleted
                    </span>
                </p>
            </div>

            <div class="header-actions">
                <div class="search-container">
                    <span class="material-icons-round search-icon">search</span>
                    <input type="text" class="search-input" placeholder="Search deleted emails..."
                        value="<?= htmlspecialchars($filters['search']) ?>"
                        onchange="handleSearch(this.value)">
                </div>

                <button class="btn-filter-toggle <?= $hasActiveFilters ? 'active' : '' ?>"
                    onclick="toggleFilters()">
                    <span class="material-icons-round">tune</span>
                    Filters
                </button>

                <!-- Filter Panel -->
                <div class="filter-panel" id="filterPanel">
                    <div class="filter-header">
                        <h3>Advanced Filters</h3>
                        <button class="btn-clear-all" onclick="clearAllFilters()">Clear All</button>
                    </div>

                    <div class="filter-body">
                        <form method="GET" action="">
                            <div class="filter-group">
                                <label>Recipient Email</label>
                                <input type="text" name="recipient" placeholder="Enter email address"
                                    value="<?= htmlspecialchars($filters['recipient']) ?>">
                            </div>

                            <div class="filter-group">
                                <label>Subject Contains</label>
                                <input type="text" name="subject" placeholder="Enter subject text"
                                    value="<?= htmlspecialchars($filters['subject']) ?>">
                            </div>

                            <div class="filter-group">
                                <label>Label</label>
                                <select name="label_id">
                                    <option value="">All Labels</option>
                                    <option value="unlabeled" <?= $filters['label_id'] === 'unlabeled' ? 'selected' : '' ?>>
                                        Unlabeled
                                    </option>
                                    <?php foreach ($labels as $label): ?>
                                    <option value="<?= $label['id'] ?>"
                                        <?= $filters['label_id'] == $label['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label['label_name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label>Date Range</label>
                                <div class="date-range-group">
                                    <input type="date" name="date_from"
                                        value="<?= htmlspecialchars($filters['date_from']) ?>">
                                    <input type="date" name="date_to"
                                        value="<?= htmlspecialchars($filters['date_to']) ?>">
                                </div>
                            </div>

                            <div class="filter-actions">
                                <button type="submit" class="btn-apply-filter">Apply Filters</button>
                                <button type="button" class="btn-reset-filter"
                                    onclick="clearForm()">Reset</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Filters Display -->
        <?php if ($hasActiveFilters): ?>
        <div class="active-filters">
            <span class="active-filters-label">Active Filters:</span>
            <?php if (!empty($filters['search'])): ?>
            <div class="filter-chip">
                Search: "<?= htmlspecialchars($filters['search']) ?>"
                <span class="material-icons-round" onclick="removeFilter('search')">close</span>
            </div>
            <?php endif; ?>
            <?php if (!empty($filters['recipient'])): ?>
            <div class="filter-chip">
                Recipient: <?= htmlspecialchars($filters['recipient']) ?>
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
                Label: <?= $filters['label_id'] === 'unlabeled' ? 'Unlabeled' : htmlspecialchars(array_filter($labels, fn($l) => $l['id'] == $filters['label_id'])[0]['label_name'] ?? 'Unknown') ?>
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

        <!-- Bulk Action Toolbar -->
        <div class="bulk-action-toolbar" id="bulkActionToolbar">
            <div class="toolbar-left">
                <span class="selection-count"><span id="selectedCount">0</span> selected</span>
                <button class="btn-toolbar" onclick="clearSelection()">
                    <span class="material-icons-round">close</span>
                    Clear
                </button>
            </div>

            <div class="toolbar-actions">
                <button class="btn-toolbar" onclick="bulkRestore()">
                    <span class="material-icons-round">restore</span>
                    Restore
                </button>
                <button class="btn-toolbar danger" onclick="bulkDeleteForever()">
                    <span class="material-icons-round">delete_forever</span>
                    Delete Forever
                </button>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-wrapper">
            <div class="email-list-container">
                <?php if (empty($sentEmails)): ?>
                <div class="empty-state">
                    <span class="material-icons-round">delete_outline</span>
                    <h3>No Deleted Items</h3>
                    <p>Deleted emails will appear here. They can be restored or permanently deleted.</p>
                </div>
                <?php else: ?>
                <div class="email-list-header">
                    <div></div>
                    <div>Recipient</div>
                    <div>Subject</div>
                    <div>Label</div>
                    <div>Date</div>
                </div>

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
                        <div class="subject-text"><?= htmlspecialchars($email['subject']) ?></div>
                        <div class="preview-text">
                            <?= htmlspecialchars(mb_substr(strip_tags($email['message_body']), 0, 100)) ?>
                        </div>
                    </div>

                    <div class="col-label">
                        <?php if (!empty($email['label_color'])): ?>
                        <span class="label-indicator"
                            style="background: <?= htmlspecialchars($email['label_color']) ?>;"></span>
                        <span class="label-name"><?= htmlspecialchars($email['label_name']) ?></span>
                        <?php else: ?>
                        <span class="label-name" style="color: var(--text-tertiary);">No label</span>
                        <?php endif; ?>

                        <div class="label-dropdown">
                            <button class="label-dropdown-btn" onclick="event.stopPropagation()">
                                <span class="material-icons-round">label</span>
                            </button>
                            <div class="label-dropdown-content">
                                <div class="label-option" onclick="updateLabel(<?= $email['id'] ?>, null)">
                                    <span class="material-icons-round" style="font-size: 18px;">label_off</span>
                                    Remove Label
                                </div>
                                <?php foreach ($labels as $label): ?>
                                <div class="label-option"
                                    onclick="updateLabel(<?= $email['id'] ?>, <?= $label['id'] ?>)">
                                    <span class="label-color-dot"
                                        style="background: <?= htmlspecialchars($label['label_color']) ?>;"></span>
                                    <?= htmlspecialchars($label['label_name']) ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-date" onclick="openEmail(<?= $email['id'] ?>)">
                        <?php if (!empty($email['attachment_names'])): ?>
                        <i class="fa-solid fa-paperclip attachment-icon"></i>
                        <?php endif; ?>
                        <?= date('M j, Y', strtotime($email['sent_at'])) ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php
            $currentParams = $_GET;
            
            // Show first page
            if ($page > 3) {
                $currentParams['page'] = 1;
                echo '<a href="?' . http_build_query($currentParams) . '" class="page-link">1</a>';
                if ($page > 4) {
                    echo '<span class="page-link" style="border: none;">...</span>';
                }
            }
            
            // Show pages around current
            for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++) {
                $currentParams['page'] = $i;
                $queryString = http_build_query($currentParams);
                echo '<a href="?' . $queryString . '" class="page-link ' . ($i == $page ? 'active' : '') . '">' . $i . '</a>';
            }
            
            // Show last page
            if ($page < $totalPages - 2) {
                if ($page < $totalPages - 3) {
                    echo '<span class="page-link" style="border: none;">...</span>';
                }
                $currentParams['page'] = $totalPages;
                echo '<a href="?' . http_build_query($currentParams) . '" class="page-link">' . $totalPages . '</a>';
            }
            ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Gmail-style checkbox selection management
        function handleCheckboxChange() {
            const checkedBoxes = document.querySelectorAll('.email-checkbox:checked');
            const toolbar = document.getElementById('bulkActionToolbar');
            const selectedCount = document.getElementById('selectedCount');

            selectedCount.textContent = checkedBoxes.length;

            if (checkedBoxes.length > 0) {
                toolbar.classList.add('active');
                // Highlight selected rows
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

        async function bulkRestore() {
            const checkedBoxes = document.querySelectorAll('.email-checkbox:checked');
            const emailIds = Array.from(checkedBoxes).map(cb => cb.value);

            if (emailIds.length === 0) {
                alert('Please select at least one email to restore');
                return;
            }

            if (!confirm(`Are you sure you want to restore ${emailIds.length} email(s)?`)) {
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
                    location.reload();
                } else {
                    alert('Failed to restore emails: ' + (result.message || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while restoring emails');
            }
        }

        async function bulkDeleteForever() {
            const checkedBoxes = document.querySelectorAll('.email-checkbox:checked');
            const emailIds = Array.from(checkedBoxes).map(cb => cb.value);

            if (emailIds.length === 0) {
                alert('Please select at least one email to delete');
                return;
            }

            if (!confirm(`Are you sure you want to permanently delete ${emailIds.length} email(s)? This action cannot be undone.`)) {
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
                    location.reload();
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

        async function updateLabel(emailId, labelId) {
            try {
                const formData = new FormData();
                formData.append('ajax', '1');
                formData.append('email_id', emailId);
                formData.append('label_id', labelId || '');

                const response = await fetch('update_email_label.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    location.reload();
                } else {
                    alert('Failed to update label');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred');
            }
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

            // Delete key for bulk delete
            if (e.key === 'Delete' || e.key === 'Backspace') {
                const checkedBoxes = document.querySelectorAll('.email-checkbox:checked');
                if (checkedBoxes.length > 0 && !e.target.matches('input[type="text"], input[type="date"], select')) {
                    e.preventDefault();
                    bulkDeleteForever();
                }
            }
        });
    </script>
</body>

</html>