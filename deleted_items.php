<?php
// deleted_items.php - Apple-Inspired Minimalist Interface
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
    <title>Mail</title>

    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        :root {
            /* Apple System Colors */
            --system-white: #ffffff;
            --system-gray-1: #f5f5f7;
            --system-gray-2: #e8e8ed;
            --system-gray-3: #d2d2d7;
            --system-gray-4: #c7c7cc;
            --system-gray-5: #aeaeb2;
            --system-gray-6: #8e8e93;
            --system-separator: rgba(60, 60, 67, 0.12);
            
            /* Text Colors */
            --label-primary: #000000;
            --label-secondary: rgba(60, 60, 67, 0.6);
            --label-tertiary: rgba(60, 60, 67, 0.3);
            --label-quaternary: rgba(60, 60, 67, 0.18);
            
            /* Accent Colors - Subtle */
            --system-blue: #007aff;
            --system-blue-subtle: rgba(0, 122, 255, 0.1);
            
            /* Semantic - Muted */
            --fill-positive: rgba(52, 199, 89, 0.15);
            --fill-negative: rgba(255, 59, 48, 0.15);
            --text-positive: rgba(52, 199, 89, 1);
            --text-negative: rgba(255, 59, 48, 1);
            
            /* Shadows - Ultra Subtle */
            --shadow-1: 0 1px 2px rgba(0, 0, 0, 0.04);
            --shadow-2: 0 2px 8px rgba(0, 0, 0, 0.04);
            --shadow-3: 0 4px 16px rgba(0, 0, 0, 0.06);
            
            /* Transitions */
            --ease: cubic-bezier(0.25, 0.1, 0.25, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "SF Pro Text", "Helvetica Neue", Helvetica, Arial, sans-serif;
            background: var(--system-white);
            color: var(--label-primary);
            display: flex;
            height: 100vh;
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }

        /* ========== MAIN LAYOUT ========== */
        #main-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }

        /* ========== COMPACT TOOLBAR ========== */
        .toolbar {
            background: var(--system-white);
            border-bottom: 0.5px solid var(--system-separator);
            padding: 8px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            height: 52px;
        }

        .toolbar-left,
        .toolbar-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Search Field - Apple Style */
        .search-field {
            position: relative;
            width: 260px;
        }

        .search-field input {
            width: 100%;
            height: 32px;
            padding: 0 32px 0 28px;
            border: none;
            border-radius: 6px;
            background: var(--system-gray-2);
            font-size: 13px;
            font-weight: 400;
            color: var(--label-primary);
            transition: background 0.2s var(--ease);
        }

        .search-field input:focus {
            outline: none;
            background: var(--system-gray-3);
        }

        .search-field input::placeholder {
            color: var(--label-tertiary);
        }

        .search-field .icon-left {
            position: absolute;
            left: 8px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 16px;
            color: var(--label-tertiary);
            pointer-events: none;
        }

        .search-field .icon-clear {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 14px;
            color: var(--label-tertiary);
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.2s var(--ease);
        }

        .search-field input:not(:placeholder-shown) + .icon-clear {
            opacity: 1;
        }

        /* Minimal Icon Buttons */
        .icon-button {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            border-radius: 6px;
            background: transparent;
            color: var(--label-secondary);
            cursor: pointer;
            transition: all 0.15s var(--ease);
        }

        .icon-button:hover {
            background: var(--system-gray-2);
            color: var(--label-primary);
        }

        .icon-button.active {
            background: var(--system-blue-subtle);
            color: var(--system-blue);
        }

        .icon-button .material-icons-round {
            font-size: 18px;
        }

        /* Count Badge - Subtle */
        .count-badge {
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            background: var(--system-gray-5);
            color: var(--system-white);
            border-radius: 9px;
            font-size: 11px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-left: 6px;
        }

        /* ========== SELECTION BAR (Compact) ========== */
        .selection-bar {
            background: var(--system-gray-1);
            border-bottom: 0.5px solid var(--system-separator);
            padding: 8px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 44px;
            opacity: 0;
            transform: translateY(-100%);
            transition: all 0.3s var(--ease);
            position: absolute;
            top: 52px;
            left: 0;
            right: 0;
            z-index: 10;
        }

        .selection-bar.active {
            opacity: 1;
            transform: translateY(0);
            position: relative;
            top: 0;
        }

        .selection-info {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
            color: var(--label-secondary);
            font-weight: 500;
        }

        .selection-count {
            font-weight: 600;
            color: var(--label-primary);
        }

        .selection-actions {
            display: flex;
            gap: 6px;
        }

        /* Minimal Action Buttons */
        .action-button {
            height: 28px;
            padding: 0 12px;
            border: 0.5px solid var(--system-separator);
            border-radius: 6px;
            background: var(--system-white);
            font-size: 12px;
            font-weight: 500;
            color: var(--label-primary);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.15s var(--ease);
        }

        .action-button:hover {
            background: var(--system-gray-1);
            border-color: var(--system-gray-4);
        }

        .action-button .material-icons-round {
            font-size: 14px;
        }

        .action-button.restore {
            color: var(--text-positive);
            border-color: rgba(52, 199, 89, 0.3);
        }

        .action-button.restore:hover {
            background: var(--fill-positive);
        }

        .action-button.delete {
            color: var(--text-negative);
            border-color: rgba(255, 59, 48, 0.3);
        }

        .action-button.delete:hover {
            background: var(--fill-negative);
        }

        /* ========== FILTER PANEL ========== */
        .filter-panel {
            background: var(--system-white);
            border-bottom: 0.5px solid var(--system-separator);
            padding: 0;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s var(--ease);
        }

        .filter-panel.active {
            max-height: 400px;
            padding: 16px 20px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin-bottom: 12px;
        }

        .filter-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .filter-field label {
            font-size: 11px;
            font-weight: 600;
            color: var(--label-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-field input,
        .filter-field select {
            height: 32px;
            padding: 0 10px;
            border: 0.5px solid var(--system-separator);
            border-radius: 6px;
            background: var(--system-white);
            font-size: 13px;
            font-weight: 400;
            color: var(--label-primary);
            transition: all 0.15s var(--ease);
        }

        .filter-field input:focus,
        .filter-field select:focus {
            outline: none;
            border-color: var(--system-blue);
            box-shadow: 0 0 0 3px var(--system-blue-subtle);
        }

        .filter-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            padding-top: 12px;
            border-top: 0.5px solid var(--system-separator);
        }

        /* Active Filters */
        .active-filters {
            padding: 10px 20px;
            background: var(--system-gray-1);
            border-bottom: 0.5px solid var(--system-separator);
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .filter-chip {
            height: 24px;
            padding: 0 10px;
            background: var(--system-white);
            border: 0.5px solid var(--system-separator);
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            color: var(--label-primary);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .filter-chip .material-icons-round {
            font-size: 14px;
            color: var(--label-tertiary);
            cursor: pointer;
            transition: color 0.15s var(--ease);
        }

        .filter-chip .material-icons-round:hover {
            color: var(--text-negative);
        }

        /* ========== EMAIL LIST ========== */
        .content-area {
            flex: 1;
            overflow-y: auto;
            background: var(--system-white);
        }

        .email-list {
            padding: 0;
        }

        .email-row {
            display: grid;
            grid-template-columns: 36px 200px 1fr auto 100px;
            gap: 12px;
            align-items: center;
            padding: 10px 20px;
            border-bottom: 0.5px solid var(--system-separator);
            cursor: pointer;
            transition: background 0.15s var(--ease);
            position: relative;
        }

        .email-row:hover {
            background: var(--system-gray-1);
        }

        .email-row.selected {
            background: var(--system-blue-subtle);
        }

        .email-row::after {
            content: '';
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            font-family: 'Material Icons Round';
            content: 'chevron_right';
            font-size: 16px;
            color: var(--label-quaternary);
            opacity: 0;
            transition: opacity 0.15s var(--ease);
        }

        .email-row:hover::after {
            opacity: 1;
        }

        /* Checkbox */
        .email-checkbox-col {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .email-checkbox {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: var(--system-blue);
        }

        /* Recipient */
        .email-from {
            font-size: 13px;
            font-weight: 500;
            color: var(--label-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Subject & Preview */
        .email-content {
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .email-subject {
            font-size: 13px;
            font-weight: 500;
            color: var(--label-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .email-preview {
            font-size: 12px;
            color: var(--label-tertiary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Label */
        .email-label {
            display: flex;
            gap: 6px;
        }

        .label-dot {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            opacity: 0.3;
        }

        /* Date & Attachment */
        .email-meta {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 400;
            color: var(--label-secondary);
            white-space: nowrap;
        }

        .email-meta .material-icons-round {
            font-size: 14px;
            color: var(--label-tertiary);
        }

        /* ========== EMPTY STATE ========== */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 80px 40px;
            text-align: center;
        }

        .empty-icon {
            width: 80px;
            height: 80px;
            margin-bottom: 16px;
            border-radius: 50%;
            background: var(--system-gray-2);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .empty-icon .material-icons-round {
            font-size: 40px;
            color: var(--label-quaternary);
        }

        .empty-state h3 {
            font-size: 17px;
            font-weight: 600;
            color: var(--label-primary);
            margin-bottom: 6px;
        }

        .empty-state p {
            font-size: 13px;
            color: var(--label-secondary);
            max-width: 300px;
            line-height: 1.5;
        }

        /* ========== PAGINATION ========== */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 4px;
            padding: 12px 20px;
            background: var(--system-white);
            border-top: 0.5px solid var(--system-separator);
        }

        .page-button {
            min-width: 32px;
            height: 32px;
            padding: 0 8px;
            border: none;
            border-radius: 6px;
            background: transparent;
            color: var(--label-primary);
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s var(--ease);
        }

        .page-button:hover {
            background: var(--system-gray-2);
        }

        .page-button.active {
            background: var(--system-gray-3);
            color: var(--label-primary);
        }

        /* ========== SCROLLBAR ========== */
        .content-area::-webkit-scrollbar {
            width: 10px;
        }

        .content-area::-webkit-scrollbar-track {
            background: transparent;
        }

        .content-area::-webkit-scrollbar-thumb {
            background: var(--system-gray-4);
            border-radius: 10px;
            border: 2px solid var(--system-white);
        }

        .content-area::-webkit-scrollbar-thumb:hover {
            background: var(--system-gray-5);
        }

        /* ========== ANIMATIONS ========== */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .email-row {
            animation: fadeIn 0.2s var(--ease) backwards;
        }

        <?php for ($i = 0; $i < min(15, count($sentEmails)); $i++): ?>
        .email-row:nth-child(<?= $i + 1 ?>) {
            animation-delay: <?= $i * 0.015 ?>s;
        }
        <?php endfor; ?>

        /* ========== RESPONSIVE ========== */
        @media (max-width: 768px) {
            .email-row {
                grid-template-columns: 36px 1fr 80px;
            }

            .email-from,
            .email-label {
                display: none;
            }

            .search-field {
                width: 180px;
            }
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <div id="main-wrapper">
        <!-- Compact Toolbar -->
        <div class="toolbar">
            <div class="toolbar-left">
                <div class="search-field">
                    <span class="material-icons-round icon-left">search</span>
                    <input type="text" placeholder="Search" 
                           value="<?= htmlspecialchars($filters['search']) ?>"
                           onchange="handleSearch(this.value)">
                    <span class="material-icons-round icon-clear" onclick="clearSearch()">close</span>
                </div>
                <?php if ($totalEmails > 0): ?>
                <span style="font-size: 13px; color: var(--label-secondary); margin-left: 4px;">
                    <?= $totalEmails ?> <?= $totalEmails === 1 ? 'item' : 'items' ?>
                </span>
                <?php endif; ?>
            </div>

            <div class="toolbar-right">
                <button class="icon-button <?= $hasActiveFilters ? 'active' : '' ?>" 
                        onclick="toggleFilters()" 
                        title="Filters">
                    <span class="material-icons-round">filter_list</span>
                </button>
            </div>
        </div>

        <!-- Selection Bar -->
        <div class="selection-bar" id="selectionBar">
            <div class="selection-info">
                <span class="selection-count" id="selectedCount">0</span> selected
            </div>
            <div class="selection-actions">
                <button class="action-button restore" onclick="bulkRestore()">
                    <span class="material-icons-round">arrow_upward</span>
                    Restore
                </button>
                <button class="action-button delete" onclick="bulkDeleteForever()">
                    <span class="material-icons-round">delete_forever</span>
                    Delete
                </button>
                <button class="action-button" onclick="clearSelection()">
                    Clear
                </button>
            </div>
        </div>

        <!-- Filter Panel -->
        <div class="filter-panel <?= $hasActiveFilters ? 'active' : '' ?>" id="filterPanel">
            <form method="GET" action="deleted_items.php">
                <div class="filter-grid">
                    <div class="filter-field">
                        <label>Recipient</label>
                        <input type="text" name="recipient" 
                               value="<?= htmlspecialchars($filters['recipient']) ?>">
                    </div>
                    <div class="filter-field">
                        <label>Subject</label>
                        <input type="text" name="subject" 
                               value="<?= htmlspecialchars($filters['subject']) ?>">
                    </div>
                    <div class="filter-field">
                        <label>Label</label>
                        <select name="label_id">
                            <option value="">All</option>
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
                    <div class="filter-field">
                        <label>From Date</label>
                        <input type="date" name="date_from" 
                               value="<?= htmlspecialchars($filters['date_from']) ?>">
                    </div>
                    <div class="filter-field">
                        <label>To Date</label>
                        <input type="date" name="date_to" 
                               value="<?= htmlspecialchars($filters['date_to']) ?>">
                    </div>
                </div>
                <div class="filter-actions">
                    <button type="button" class="action-button" onclick="clearForm()">Clear</button>
                    <button type="submit" class="action-button" style="color: var(--system-blue); border-color: var(--system-blue);">Apply</button>
                </div>
            </form>
        </div>

        <!-- Active Filters -->
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
                <?= htmlspecialchars($filters['recipient']) ?>
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
                    <span class="material-icons-round">inbox</span>
                </div>
                <h3>No Items</h3>
                <p>Deleted emails appear here for 30 days before being permanently removed.</p>
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
            document.querySelector('.icon-button').classList.toggle('active');
        }

        function handleSearch(value) {
            const url = new URL(window.location.href);
            value ? url.searchParams.set('search', value) : url.searchParams.delete('search');
            url.searchParams.delete('page');
            window.location.href = url.toString();
        }

        function clearSearch() {
            const input = document.querySelector('.search-field input');
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
                document.querySelector('.search-field input').focus();
            }
        });
    </script>
</body>

</html>