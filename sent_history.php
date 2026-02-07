<?php
// sent_history.php - Premium Email Archive with Advanced Filtering
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

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Get filtered emails
$sentEmails = getSentEmails($userEmail, $perPage, $offset, $filters);
$totalEmails = getSentEmailCount($userEmail, $filters);
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
    <title>Email Archive — SXC MDTS</title>

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
            letter-spacing: -0.02em;
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
            border-color: var(--accent);
            color: var(--accent);
            background: var(--accent-light);
        }

        .btn-filter-toggle.active {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }

        .btn-filter-toggle .material-icons-round {
            font-size: 18px;
        }

        /* ========== FILTER PANEL ========== */
        .filter-panel {
            background: var(--background);
            border-bottom: 1px solid var(--border);
            padding: 24px 32px;
            display: none;
            flex-shrink: 0;
            animation: slideDown 0.3s var(--transition);
        }

        .filter-panel.active {
            display: block;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            letter-spacing: 0.01em;
        }

        .filter-input,
        .filter-select {
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            color: var(--text-primary);
            background: var(--background);
            transition: all 0.2s var(--transition);
        }

        .filter-input:focus,
        .filter-select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(196, 30, 58, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn-filter {
            padding: 10px 20px;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-apply {
            background: var(--accent);
            color: white;
        }

        .btn-apply:hover {
            background: var(--accent-hover);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-clear {
            background: var(--background);
            color: var(--text-secondary);
            border: 1px solid var(--border);
        }

        .btn-clear:hover {
            background: var(--gray-100);
            border-color: var(--gray-300);
        }

        /* ========== ACTIVE FILTERS ========== */
        .active-filters {
            padding: 16px 32px;
            background: var(--gray-100);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            flex-shrink: 0;
        }

        .active-filters-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
        }

        .filter-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            background: var(--background);
            border: 1px solid var(--border);
            border-radius: 20px;
            font-size: 13px;
            color: var(--text-primary);
            font-weight: 500;
        }

        .filter-tag .remove {
            cursor: pointer;
            color: var(--text-tertiary);
            font-size: 16px;
            font-weight: 700;
            line-height: 1;
            transition: color 0.2s;
        }

        .filter-tag .remove:hover {
            color: var(--accent);
        }

        /* ========== EMAIL LIST ========== */
        .email-list-container {
            flex: 1;
            overflow-y: auto;
            padding: 0;
        }

        .email-list {
            max-width: 1400px;
            margin: 0 auto;
            padding: 8px 0;
        }

        /* Email Item */
        .email-item {
            display: grid;
            grid-template-columns: minmax(200px, 280px) 1fr auto 90px;
            gap: 20px;
            align-items: center;
            padding: 5px 32px;
            background: var(--background);
            border-bottom: 1px solid var(--border);
            text-decoration: none;
            color: var(--text-primary);
            /* transition: all 0.2s var(--transition); */
            position: relative;
        }

        .email-item:hover {
            background: var(--gray-50);
            border-left: 3px solid var(--accent);
            padding-left: 29px;
        }

        .email-item:active {
            background: var(--gray-100);
        }

        /* Recipient Column */
        .col-recipient {
            font-size: 13px;
            color: var(--text-tertiary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-weight: 400;
        }

        /* Content Column */
        .col-content {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
            flex: 1;
        }

        .label-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            color: white;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .subject-text {
            font-weight: 500;
            font-size: 14px;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .snippet-text {
            font-size: 13px;
            color: var(--text-tertiary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-weight: 400;
        }

        /* Quick Label Dropdown */
        .quick-label {
            position: relative;
            z-index: 10;
        }

        .label-dropdown-btn {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            border: 1px solid var(--border);
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.2s var(--transition);
            color: var(--text-tertiary);
        }

        .label-dropdown-btn:hover {
            background: var(--accent-light);
            border-color: var(--accent);
            color: var(--accent);
            transform: scale(1.05);
        }

        .label-dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            top: calc(100% + 8px);
            background: var(--background);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-xl);
            min-width: 200px;
            z-index: 100;
            overflow: hidden;
        }

        .label-dropdown:hover .label-dropdown-content {
            display: block;
            animation: fadeIn 0.2s var(--transition);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-5px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .label-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            cursor: pointer;
            transition: all 0.15s var(--transition);
            font-size: 13px;
            color: var(--text-primary);
        }

        .label-option:hover {
            background: var(--gray-100);
        }

        .label-color-dot {
            width: 12px;
            height: 12px;
            border-radius: 3px;
            flex-shrink: 0;
        }

        /* Date Column */
        .col-date {
            font-size: 13px;
            color: var(--text-secondary);
            font-weight: 500;
            text-align: right;
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
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 80px 32px;
            text-align: center;
            color: var(--text-secondary);
        }

        .empty-state .material-icons-round {
            font-size: 72px;
            color: var(--text-tertiary);
            margin-bottom: 20px;
            opacity: 0.4;
        }

        .empty-state h3 {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .empty-state p {
            font-size: 14px;
            color: var(--text-secondary);
        }

        /* ========== PAGINATION ========== */
        .pagination {
            padding: 24px 32px;
            background: var(--background);
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .page-link {
            min-width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            text-decoration: none;
            color: var(--text-primary);
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s var(--transition);
        }

        .page-link:hover {
            background: var(--gray-100);
            border-color: var(--gray-300);
        }

        .page-link.active {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 1200px) {
            .email-item {
                grid-template-columns: minmax(160px, 220px) 1fr auto 90px;
                gap: 16px;
            }

            .search-input {
                width: 260px;
            }
        }

        @media (max-width: 768px) {
            .page-header {
                padding: 20px 20px;
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }

            .header-actions {
                width: 100%;
                flex-direction: column;
            }

            .search-container {
                width: 100%;
            }

            .search-input {
                width: 100%;
            }

            .filter-panel {
                padding: 20px;
            }

            .filter-grid {
                grid-template-columns: 1fr;
            }

            .active-filters {
                padding: 12px 20px;
            }

            .email-item {
                grid-template-columns: 1fr;
                gap: 8px;
                padding: 16px 20px;
            }

            .email-item:hover {
                padding-left: 17px;
            }

            .col-recipient {
                font-size: 13px;
            }

            .col-content {
                flex-direction: column;
                align-items: flex-start;
            }

            .col-date {
                justify-content: flex-start;
            }

            .quick-label {
                position: absolute;
                right: 20px;
                top: 16px;
            }

            .pagination {
                padding: 16px 20px;
                gap: 4px;
            }

            .page-link {
                min-width: 32px;
                height: 32px;
                font-size: 13px;
            }
        }

        /* ========== SCROLLBAR ========== */
        .email-list-container::-webkit-scrollbar {
            width: 8px;
        }

        .email-list-container::-webkit-scrollbar-track {
            background: var(--gray-100);
        }

        .email-list-container::-webkit-scrollbar-thumb {
            background: var(--gray-300);
            border-radius: 4px;
        }

        .email-list-container::-webkit-scrollbar-thumb:hover {
            background: var(--accent);
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>

    <div id="main-wrapper">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-left">
                <p class="page-subtitle">View and manage all sent correspondence</p>
            </div>

            <div class="header-actions">
                <div class="search-container">
                    <span class="material-icons-round search-icon">search</span>
                    <input type="text" class="search-input" placeholder="Search emails..."
                        value="<?= htmlspecialchars($filters['search']) ?>" onchange="handleSearch(this.value)">
                </div>

                <button class="btn-filter-toggle <?= $hasActiveFilters ? 'active' : '' ?>" onclick="toggleFilters()">
                    <span class="material-icons-round">tune</span>
                    <span>Filters</span>
                </button>
            </div>
        </div>

        <!-- Active Filters -->
        <?php if ($hasActiveFilters): ?>
        <div class="active-filters">
            <span class="active-filters-label">Active Filters:</span>

            <?php if (!empty($filters['search'])): ?>
            <span class="filter-tag">
                Search: "
                <?= htmlspecialchars($filters['search']) ?>"
                <span class="remove" onclick="removeFilter('search')">×</span>
            </span>
            <?php endif; ?>

            <?php if (!empty($filters['recipient'])): ?>
            <span class="filter-tag">
                Recipient:
                <?= htmlspecialchars($filters['recipient']) ?>
                <span class="remove" onclick="removeFilter('recipient')">×</span>
            </span>
            <?php endif; ?>

            <?php if (!empty($filters['subject'])): ?>
            <span class="filter-tag">
                Subject:
                <?= htmlspecialchars($filters['subject']) ?>
                <span class="remove" onclick="removeFilter('subject')">×</span>
            </span>
            <?php endif; ?>

            <?php if (!empty($filters['label_id'])): ?>
            <span class="filter-tag">
                Label:
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
                <span class="remove" onclick="removeFilter('label_id')">×</span>
            </span>
            <?php endif; ?>

            <?php if (!empty($filters['date_from']) || !empty($filters['date_to'])): ?>
            <span class="filter-tag">
                Date:
                <?= !empty($filters['date_from']) ? date('M j, Y', strtotime($filters['date_from'])) : 'Any' ?>
                —
                <?= !empty($filters['date_to']) ? date('M j, Y', strtotime($filters['date_to'])) : 'Any' ?>
                <span class="remove" onclick="clearDateFilters()">×</span>
            </span>
            <?php endif; ?>

            <button class="btn-clear" onclick="clearAllFilters()">
                Clear All
            </button>
        </div>
        <?php endif; ?>

        <!-- Filter Panel -->
        <div id="filterPanel" class="filter-panel <?= $hasActiveFilters ? 'active' : '' ?>">
            <form method="GET" action="sent_history.php">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label class="filter-label">Global Search</label>
                        <input type="text" name="search" class="filter-input" placeholder="Search everywhere..."
                            value="<?= htmlspecialchars($filters['search']) ?>">
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Recipient</label>
                        <input type="text" name="recipient" class="filter-input" placeholder="email@example.com"
                            value="<?= htmlspecialchars($filters['recipient']) ?>">
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Subject</label>
                        <input type="text" name="subject" class="filter-input" placeholder="Subject keywords..."
                            value="<?= htmlspecialchars($filters['subject']) ?>">
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Label</label>
                        <select name="label_id" class="filter-select">
                            <option value="">All Labels</option>
                            <!-- <option value="unlabeled" <?=$filters['label_id']==='unlabeled' ? 'selected' : '' ?>> -->
                            Unlabeled (
                            <?= $unlabeledCount ?>)
                            </option>
                            <?php foreach ($labels as $label): ?>
                            <option value="<?= $label['id'] ?>" <?=$filters['label_id']==$label['id'] ? 'selected' : ''
                                ?>>
                                <?= htmlspecialchars($label['label_name']) ?> (
                                <?= $label['email_count'] ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Date From</label>
                        <input type="date" name="date_from" class="filter-input"
                            value="<?= htmlspecialchars($filters['date_from']) ?>">
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Date To</label>
                        <input type="date" name="date_to" class="filter-input"
                            value="<?= htmlspecialchars($filters['date_to']) ?>">
                    </div>
                </div>

                <div class="filter-actions">
                    <button type="button" class="btn-filter btn-clear" onclick="clearForm()">
                        <span class="material-icons-round" style="font-size: 18px;">clear</span>
                        Clear
                    </button>
                    <button type="submit" class="btn-filter btn-apply">
                        <span class="material-icons-round" style="font-size: 18px;">check</span>
                        Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Email List -->
        <div class="email-list-container">
            <div class="email-list">
                <?php if (empty($sentEmails)): ?>
                <div class="empty-state">
                    <span class="material-icons-round">inbox</span>
                    <h3>
                        <?= $hasActiveFilters ? 'No emails match your filters' : 'Archive is empty' ?>
                    </h3>
                    <p>
                        <?= $hasActiveFilters ? 'Try adjusting your search criteria' : 'Start by composing your first email' ?>
                    </p>
                </div>
                <?php else: ?>
                <?php foreach ($sentEmails as $email): ?>
                <a href="view_sent_email.php?id=<?= $email['id'] ?>" class="email-item" target="_blank">
                    <div class="col-recipient">
                        <?= htmlspecialchars($email['recipient_email']) ?>
                    </div>

                    <div class="col-content">
                        <?php if (!empty($email['label_name'])): ?>
                        <span class="label-badge"
                            style="background-color: <?= htmlspecialchars($email['label_color']) ?>;">
                            <?= htmlspecialchars($email['label_name']) ?>
                        </span>
                        <?php endif; ?>

                        <span class="subject-text">
                            <?= htmlspecialchars($email['subject']) ?>
                        </span>
                        <span class="snippet-text">
                            —
                            <?= htmlspecialchars(mb_substr(strip_tags($email['message_body']), 0, 100)) ?>
                        </span>
                    </div>

                    <div class="quick-label">
                        <div class="label-dropdown" onclick="event.preventDefault(); event.stopPropagation();">
                            <button class="label-dropdown-btn">
                                <span class="material-icons-round" style="font-size: 16px;">label</span>
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

                    <div class="col-date">
                        <?php if (!empty($email['attachment_names'])): ?>
                        <i class="fa-solid fa-paperclip attachment-icon"></i>
                        <?php endif; ?>
                        <?= date('M j, Y', strtotime($email['sent_at'])) ?>
                    </div>
                </a>
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
            window.location.href = 'sent_history.php';
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
        });
    </script>
</body>

</html>