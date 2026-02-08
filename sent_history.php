<?php
// sent_history.php - Premium Email Archive
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

// Get all labels
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
    <title>Sent History — SXC MDTS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        :root {
            --apple-blue: #007AFF;
            --apple-gray: #8E8E93;
            --apple-bg: #F2F2F7;
            --glass: rgba(255, 255, 255, 0.7);
            --border: #E5E5EA;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: var(--apple-bg);
            color: #1c1c1e;
            display: flex;
            height: 100vh;
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* ========== LAYOUT ========== */
        #main-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }

        /* ========== HEADER ========== */
        .page-header {
            background: white;
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
            gap: 4px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 600;
            color: #1c1c1e;
            letter-spacing: -0.5px;
        }

        .page-subtitle {
            font-size: 14px;
            color: var(--apple-gray);
            font-weight: 400;
        }

        .email-count-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            background: #F2F2F7;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            color: var(--apple-gray);
        }

        .email-count-badge .material-icons {
            font-size: 16px;
        }

        /* ========== HEADER ACTIONS ========== */
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
            padding: 10px 16px 10px 40px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            color: #1c1c1e;
            background: white;
            transition: all 0.2s;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--apple-blue);
            box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
        }

        .search-input::placeholder {
            color: var(--apple-gray);
        }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--apple-gray);
            font-size: 20px;
            pointer-events: none;
        }

        .btn-filter-toggle {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 8px;
            color: #1c1c1e;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Inter', sans-serif;
        }

        .btn-filter-toggle:hover {
            background: #F2F2F7;
        }

        .btn-filter-toggle.active {
            background: var(--apple-blue);
            color: white;
            border-color: var(--apple-blue);
        }

        /* ========== FILTER PANEL ========== */
        .filter-panel {
            background: white;
            border-bottom: 1px solid var(--border);
            padding: 0;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s;
        }

        .filter-panel.active {
            max-height: 500px;
            padding: 24px 32px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
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
            font-weight: 500;
            color: #52525b;
        }

        .filter-input,
        .filter-select {
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            background: white;
            color: #1c1c1e;
            transition: all 0.2s;
        }

        .filter-input:focus,
        .filter-select:focus {
            outline: none;
            border-color: var(--apple-blue);
            box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn-filter-action {
            padding: 8px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            font-family: 'Inter', sans-serif;
        }

        .btn-clear {
            background: #F2F2F7;
            color: #52525b;
        }

        .btn-clear:hover {
            background: #E5E5EA;
        }

        .btn-apply {
            background: var(--apple-blue);
            color: white;
        }

        .btn-apply:hover {
            background: #0051D5;
        }

        /* ========== ACTIVE FILTERS ========== */
        .active-filters {
            padding: 16px 32px;
            background: white;
            border-bottom: 1px solid var(--border);
            display: none;
        }

        .active-filters.show {
            display: block;
        }

        .filter-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        .filter-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: #F2F2F7;
            border-radius: 20px;
            font-size: 13px;
            color: #1c1c1e;
            font-weight: 500;
        }

        .filter-chip .material-icons {
            font-size: 16px;
            cursor: pointer;
            color: var(--apple-gray);
        }

        .filter-chip .material-icons:hover {
            color: #1c1c1e;
        }

        .clear-all-filters {
            color: var(--apple-blue);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
        }

        .clear-all-filters:hover {
            text-decoration: underline;
        }

        /* ========== BULK ACTION TOOLBAR ========== */
        .bulk-action-toolbar {
            padding: 12px 32px;
            background: white;
            border-bottom: 1px solid var(--border);
            display: none;
            align-items: center;
            gap: 16px;
        }

        .bulk-action-toolbar.active {
            display: flex;
        }

        .selection-info {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #1c1c1e;
            font-weight: 500;
        }

        .bulk-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 8px;
            color: #1c1c1e;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .bulk-action-btn:hover {
            background: #F2F2F7;
        }

        .bulk-action-btn.danger:hover {
            background: #FF3B30;
            color: white;
            border-color: #FF3B30;
        }

        /* ========== EMAIL LIST ========== */
        .email-list-container {
            flex: 1;
            overflow-y: auto;
            background: var(--apple-bg);
        }

        .email-list-wrapper {
            padding: 24px 32px;
        }

        .email-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--border);
        }

        .email-item {
            display: grid;
            grid-template-columns: 40px 2fr 3fr 1fr 150px;
            gap: 16px;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            align-items: center;
            transition: all 0.2s;
            cursor: pointer;
        }

        .email-item:last-child {
            border-bottom: none;
        }

        .email-item:hover {
            background: #F9F9FB;
        }

        .email-item.selected {
            background: rgba(0, 122, 255, 0.05);
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
            accent-color: var(--apple-blue);
        }

        .col-recipient {
            font-size: 14px;
            font-weight: 500;
            color: #1c1c1e;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .col-subject {
            font-size: 14px;
            color: #52525b;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .col-label {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .label-dropdown {
            position: relative;
            display: inline-block;
        }

        .label-dropdown-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .label-dropdown-btn:hover {
            opacity: 0.8;
        }

        .label-dropdown-content {
            display: none;
            position: absolute;
            background: white;
            border: 1px solid var(--border);
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            z-index: 10;
            min-width: 180px;
            top: 100%;
            left: 0;
            margin-top: 4px;
        }

        .label-dropdown:hover .label-dropdown-content {
            display: block;
        }

        .label-option {
            padding: 8px 14px;
            cursor: pointer;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .label-option:hover {
            background: #F2F2F7;
        }

        .label-color-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .col-date {
            font-size: 13px;
            color: var(--apple-gray);
            text-align: right;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
        }

        .attachment-icon {
            font-size: 14px;
            color: var(--apple-gray);
        }

        /* ========== EMPTY STATE ========== */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
        }

        .empty-state .material-icons {
            font-size: 64px;
            color: #D1D1D6;
            margin-bottom: 16px;
        }

        .empty-state h3 {
            font-size: 20px;
            font-weight: 600;
            color: #1c1c1e;
            margin-bottom: 8px;
        }

        .empty-state p {
            font-size: 14px;
            color: var(--apple-gray);
        }

        /* ========== PAGINATION ========== */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            padding: 24px 32px;
        }

        .page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 12px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            color: #1c1c1e;
            background: white;
            border: 1px solid var(--border);
            transition: all 0.2s;
        }

        .page-link:hover {
            background: #F2F2F7;
        }

        .page-link.active {
            background: var(--apple-blue);
            color: white;
            border-color: var(--apple-blue);
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div id="main-wrapper">
        <!-- Header -->
        <div class="page-header">
            <div class="header-left">
                <h1 class="page-title">Sent History</h1>
                <div class="page-subtitle">
                    <span class="email-count-badge">
                        <span class="material-icons">mail</span>
                        <?= number_format($totalEmails) ?> emails
                    </span>
                </div>
            </div>

            <div class="header-actions">
                <div class="search-container">
                    <span class="material-icons search-icon">search</span>
                    <input type="text" 
                           class="search-input" 
                           placeholder="Search emails..."
                           value="<?= htmlspecialchars($filters['search']) ?>"
                           onchange="handleSearch(this.value)">
                </div>
                <button class="btn-filter-toggle" onclick="toggleFilters()">
                    <span class="material-icons">filter_list</span>
                    Filters
                </button>
            </div>
        </div>

        <!-- Filter Panel -->
        <div class="filter-panel" id="filterPanel">
            <form method="GET" action="sent_history.php">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label class="filter-label">Recipient</label>
                        <input type="text" 
                               name="recipient" 
                               class="filter-input" 
                               placeholder="Filter by recipient"
                               value="<?= htmlspecialchars($filters['recipient']) ?>">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Subject</label>
                        <input type="text" 
                               name="subject" 
                               class="filter-input" 
                               placeholder="Filter by subject"
                               value="<?= htmlspecialchars($filters['subject']) ?>">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Label</label>
                        <select name="label_id" class="filter-select">
                            <option value="">All labels</option>
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
                        <label class="filter-label">Date From</label>
                        <input type="date" 
                               name="date_from" 
                               class="filter-input"
                               value="<?= htmlspecialchars($filters['date_from']) ?>">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Date To</label>
                        <input type="date" 
                               name="date_to" 
                               class="filter-input"
                               value="<?= htmlspecialchars($filters['date_to']) ?>">
                    </div>
                </div>
                <div class="filter-actions">
                    <button type="button" class="btn-filter-action btn-clear" onclick="clearForm()">
                        Clear
                    </button>
                    <button type="submit" class="btn-filter-action btn-apply">
                        Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Active Filters -->
        <?php if ($hasActiveFilters): ?>
        <div class="active-filters show">
            <div class="filter-chips">
                <strong>Active filters:</strong>
                <?php if (!empty($filters['search'])): ?>
                <span class="filter-chip">
                    Search: "<?= htmlspecialchars($filters['search']) ?>"
                    <span class="material-icons" onclick="removeFilter('search')">close</span>
                </span>
                <?php endif; ?>
                <?php if (!empty($filters['recipient'])): ?>
                <span class="filter-chip">
                    Recipient: <?= htmlspecialchars($filters['recipient']) ?>
                    <span class="material-icons" onclick="removeFilter('recipient')">close</span>
                </span>
                <?php endif; ?>
                <?php if (!empty($filters['subject'])): ?>
                <span class="filter-chip">
                    Subject: <?= htmlspecialchars($filters['subject']) ?>
                    <span class="material-icons" onclick="removeFilter('subject')">close</span>
                </span>
                <?php endif; ?>
                <?php if (!empty($filters['date_from']) || !empty($filters['date_to'])): ?>
                <span class="filter-chip">
                    Date: <?= $filters['date_from'] ?? 'Any' ?> to <?= $filters['date_to'] ?? 'Now' ?>
                    <span class="material-icons" onclick="clearDateFilters()">close</span>
                </span>
                <?php endif; ?>
                <a href="#" class="clear-all-filters" onclick="clearAllFilters()">Clear all</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Bulk Action Toolbar -->
        <div class="bulk-action-toolbar" id="bulkActionToolbar">
            <div class="selection-info">
                <span id="selectedCount">0</span> selected
            </div>
            <button class="bulk-action-btn danger" onclick="bulkDelete()">
                <span class="material-icons">delete</span>
                Delete
            </button>
            <button class="bulk-action-btn" onclick="clearSelection()">
                <span class="material-icons">close</span>
                Clear Selection
            </button>
        </div>

        <!-- Email List -->
        <div class="email-list-container">
            <div class="email-list-wrapper">
                <div class="email-table">
                    <?php if (empty($sentEmails)): ?>
                    <div class="empty-state">
                        <span class="material-icons">inbox</span>
                        <h3>No emails found</h3>
                        <p>Try adjusting your filters or search query</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($sentEmails as $email): ?>
                        <div class="email-item">
                            <div class="col-checkbox">
                                <input type="checkbox" 
                                       class="email-checkbox" 
                                       value="<?= $email['id'] ?>"
                                       onchange="handleCheckboxChange()">
                            </div>

                            <div class="col-recipient" onclick="openEmail(<?= $email['id'] ?>)">
                                <?= htmlspecialchars($email['recipient_email']) ?>
                            </div>

                            <div class="col-subject" onclick="openEmail(<?= $email['id'] ?>)">
                                <?= htmlspecialchars($email['subject']) ?>
                            </div>

                            <div class="col-label">
                                <div class="label-dropdown">
                                    <button class="label-dropdown-btn" 
                                            style="background: <?= $email['label_color'] ?? '#F2F2F7' ?>; 
                                                   color: <?= $email['label_color'] ? 'white' : '#52525b' ?>;">
                                        <?= htmlspecialchars($email['label_name'] ?? 'No label') ?>
                                    </button>
                                    <div class="label-dropdown-content">
                                        <div class="label-option" onclick="updateLabel(<?= $email['id'] ?>, null)">
                                            <span class="material-icons">label_off</span>
                                            Remove Label
                                        </div>
                                        <?php foreach ($labels as $label): ?>
                                        <div class="label-option" onclick="updateLabel(<?= $email['id'] ?>, <?= $label['id'] ?>)">
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
                                <span class="material-icons attachment-icon">attach_file</span>
                                <?php endif; ?>
                                <?= date('M j, Y', strtotime($email['sent_at'])) ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
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
                    echo '<span class="page-link" style="border: none;">...</span>';
                }
            }
            
            for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++) {
                $currentParams['page'] = $i;
                echo '<a href="?' . http_build_query($currentParams) . '" class="page-link ' . ($i == $page ? 'active' : '') . '">' . $i . '</a>';
            }
            
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

        async function bulkDelete() {
            const checkedBoxes = document.querySelectorAll('.email-checkbox:checked');
            const emailIds = Array.from(checkedBoxes).map(cb => cb.value);

            if (emailIds.length === 0) {
                alert('Please select at least one email to delete');
                return;
            }

            if (!confirm(`Are you sure you want to delete ${emailIds.length} email(s)?`)) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'bulk_delete');
                formData.append('email_ids', JSON.stringify(emailIds));

                const response = await fetch('bulk_actions.php', {
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
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                document.querySelector('.search-input').focus();
            }

            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                toggleFilters();
            }

            if (e.key === 'Delete' || e.key === 'Backspace') {
                const checkedBoxes = document.querySelectorAll('.email-checkbox:checked');
                if (checkedBoxes.length > 0 && !e.target.matches('input[type="text"], input[type="date"], select')) {
                    e.preventDefault();
                    bulkDelete();
                }
            }
        });
    </script>
</body>
</html>