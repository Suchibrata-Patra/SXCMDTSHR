<?php
// deleted_items.php - Shows ALL deleted emails (both sent and received)
// Using REDESIGNED database schema
session_start();
require 'config.php';
require 'db_config_REDESIGNED.php';

if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    header("Location: login.php");
    exit();
}

$userEmail = $_SESSION['smtp_user'];

// Get filter parameters
$filters = [
    'search' => $_GET['search'] ?? '',
    'sender' => $_GET['sender'] ?? '',
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

// Get deleted emails using new function (includes BOTH sent and received)
$deletedEmails = getUserDeletedEmails($userEmail, $perPage, $offset, $filters);

// Get total count for pagination
function getDeletedEmailCount($userEmail, $filters) {
    $pdo = getDatabaseConnection();
    if (!$pdo) return 0;
    
    $user = getUserByEmail($userEmail);
    if (!$user) return 0;
    
    $sql = "SELECT COUNT(*) as count 
            FROM emails e
            INNER JOIN user_email_access uea ON e.id = uea.email_id
            WHERE uea.user_id = :user_id 
            AND uea.is_deleted = 1";
    
    $params = [':user_id' => $user['id']];
    
    // Apply same filters
    $sql = applyEmailFilters($sql, $params, $filters);
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    $result = $stmt->fetch();
    return $result['count'] ?? 0;
}

$totalEmails = getDeletedEmailCount($userEmail, $filters);
$totalPages = ceil($totalEmails / $perPage);

// Get all labels
$labels = getLabelCounts($userEmail);

// Check if filters are active
$hasActiveFilters = !empty(array_filter($filters));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deleted Items — SXC MDTS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        :root {
            --apple-blue: #007AFF;
            --apple-gray: #8E8E93;
            --apple-bg: #F2F2F7;
            --glass: rgba(255, 255, 255, 0.7);
            --border: #E5E5EA;
            --apple-red: #FF3B30;
            --apple-green: #34C759;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: #f2f2f7;
            color: #1c1c1e;
            display: flex;
            height: 100vh;
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
        }

        #main-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }

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
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-title .material-icons {
            color: var(--apple-red);
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
            background: #FEF2F4;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            color: var(--apple-red);
        }

        .header-actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 10px;
            border: none;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--apple-blue);
            color: white;
        }

        .btn-primary:hover {
            background: #0051D5;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: white;
            color: #1c1c1e;
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: #f9f9f9;
        }

        .content-wrapper {
            flex: 1;
            overflow-y: auto;
            padding: 24px 32px;
        }

        .toolbar {
            background: white;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--border);
        }

        .search-bar {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
            max-width: 500px;
        }

        .search-input {
            flex: 1;
            padding: 10px 16px;
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--apple-blue);
            box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
        }

        .filter-panel {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            border: 1px solid var(--border);
            max-height: 0;
            overflow: hidden;
            opacity: 0;
            transition: all 0.3s;
        }

        .filter-panel.active {
            max-height: 500px;
            opacity: 1;
            margin-bottom: 16px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-label {
            font-size: 13px;
            font-weight: 500;
            color: #1c1c1e;
        }

        .filter-input,
        .filter-select {
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
        }

        .active-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 16px;
        }

        .filter-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: #EBF5FF;
            color: var(--apple-blue);
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }

        .filter-chip .material-icons {
            font-size: 16px;
            cursor: pointer;
        }

        .email-list {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--border);
        }

        .email-item {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 16px;
            align-items: center;
            transition: background 0.2s;
            cursor: pointer;
        }

        .email-item:last-child {
            border-bottom: none;
        }

        .email-item:hover {
            background: #f9f9f9;
        }

        .email-item.selected {
            background: #EBF5FF;
        }

        .email-checkbox {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .email-main {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 0;
        }

        .email-header {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .email-type-badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .email-type-sent {
            background: #E8F5E9;
            color: #2E7D32;
        }

        .email-type-received {
            background: #E3F2FD;
            color: #1565C0;
        }

        .email-sender {
            font-weight: 600;
            color: #1c1c1e;
            font-size: 14px;
        }

        .email-recipient {
            color: var(--apple-gray);
            font-size: 13px;
        }

        .email-subject {
            font-size: 15px;
            color: #1c1c1e;
            font-weight: 500;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .email-preview {
            font-size: 13px;
            color: var(--apple-gray);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .email-meta {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .email-label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
        }

        .email-date {
            font-size: 13px;
            color: var(--apple-gray);
            white-space: nowrap;
        }

        .attachment-icon {
            color: var(--apple-gray);
            font-size: 18px;
        }

        .deleted-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            background: #FFEBEE;
            color: #C62828;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
        }

        .bulk-toolbar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 1px solid var(--border);
            padding: 16px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transform: translateY(100%);
            transition: transform 0.3s;
            z-index: 1000;
            box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.1);
        }

        .bulk-toolbar.active {
            transform: translateY(0);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state .material-icons {
            font-size: 64px;
            color: var(--apple-gray);
            margin-bottom: 16px;
        }

        .empty-state h3 {
            font-size: 20px;
            color: #1c1c1e;
            margin-bottom: 8px;
        }

        .empty-state p {
            font-size: 14px;
            color: var(--apple-gray);
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            padding: 24px 0;
        }

        .page-link {
            padding: 8px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            color: #1c1c1e;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s;
        }

        .page-link:hover {
            background: #f9f9f9;
        }

        .page-link.active {
            background: var(--apple-blue);
            color: white;
            border-color: var(--apple-blue);
        }
    </style>
</head>
<body>
    <div id="main-wrapper">
        <!-- Header -->
        <div class="page-header">
            <div class="header-left">
                <h1 class="page-title">
                    <span class="material-icons">delete</span>
                    Deleted Items
                </h1>
                <p class="page-subtitle">
                    <span class="email-count-badge">
                        <span class="material-icons" style="font-size: 16px;">email</span>
                        <?= number_format($totalEmails) ?> deleted
                    </span>
                </p>
            </div>
            <div class="header-actions">
                <button class="btn btn-secondary" onclick="window.location.href='inbox.php'">
                    <span class="material-icons">arrow_back</span>
                    Back to Inbox
                </button>
            </div>
        </div>

        <div class="content-wrapper">
            <!-- Toolbar -->
            <div class="toolbar">
                <div class="search-bar">
                    <span class="material-icons" style="color: var(--apple-gray);">search</span>
                    <input 
                        type="text" 
                        class="search-input" 
                        placeholder="Search deleted emails..."
                        value="<?= htmlspecialchars($filters['search']) ?>"
                        onchange="handleSearch(this.value)"
                    >
                </div>
                <button class="btn btn-secondary btn-filter-toggle" onclick="toggleFilters()">
                    <span class="material-icons">filter_list</span>
                    Filters
                </button>
            </div>

            <!-- Active Filters -->
            <?php if ($hasActiveFilters): ?>
            <div class="active-filters">
                <?php if (!empty($filters['search'])): ?>
                <div class="filter-chip">
                    Search: <?= htmlspecialchars($filters['search']) ?>
                    <span class="material-icons" onclick="removeFilter('search')">close</span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($filters['sender'])): ?>
                <div class="filter-chip">
                    From: <?= htmlspecialchars($filters['sender']) ?>
                    <span class="material-icons" onclick="removeFilter('sender')">close</span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($filters['recipient'])): ?>
                <div class="filter-chip">
                    To: <?= htmlspecialchars($filters['recipient']) ?>
                    <span class="material-icons" onclick="removeFilter('recipient')">close</span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($filters['subject'])): ?>
                <div class="filter-chip">
                    Subject: <?= htmlspecialchars($filters['subject']) ?>
                    <span class="material-icons" onclick="removeFilter('subject')">close</span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($filters['label_id'])): ?>
                <div class="filter-chip">
                    Label Filter
                    <span class="material-icons" onclick="removeFilter('label_id')">close</span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($filters['date_from']) || !empty($filters['date_to'])): ?>
                <div class="filter-chip">
                    Date Range
                    <span class="material-icons" onclick="clearDateFilters()">close</span>
                </div>
                <?php endif; ?>
                
                <button class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;" onclick="clearAllFilters()">
                    Clear All
                </button>
            </div>
            <?php endif; ?>

            <!-- Filter Panel -->
            <div class="filter-panel" id="filterPanel">
                <form method="GET">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label class="filter-label">From (Sender)</label>
                            <input type="text" name="sender" class="filter-input" 
                                   value="<?= htmlspecialchars($filters['sender']) ?>" 
                                   placeholder="sender@example.com">
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">To (Recipient)</label>
                            <input type="text" name="recipient" class="filter-input" 
                                   value="<?= htmlspecialchars($filters['recipient']) ?>" 
                                   placeholder="recipient@example.com">
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">Subject Contains</label>
                            <input type="text" name="subject" class="filter-input" 
                                   value="<?= htmlspecialchars($filters['subject']) ?>" 
                                   placeholder="Subject keywords">
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">Label</label>
                            <select name="label_id" class="filter-select">
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
                    
                    <div style="display: flex; gap: 12px;">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <button type="button" class="btn btn-secondary" onclick="clearForm()">Clear</button>
                    </div>
                </form>
            </div>

            <!-- Email List -->
            <div class="email-list">
                <?php if (empty($deletedEmails)): ?>
                    <div class="empty-state">
                        <span class="material-icons">delete_outline</span>
                        <h3>No Deleted Items</h3>
                        <p>Your deleted emails will appear here</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($deletedEmails as $email): ?>
                        <div class="email-item" onclick="openEmail(<?= $email['id'] ?>)">
                            <input type="checkbox" class="email-checkbox" 
                                   value="<?= $email['id'] ?>" 
                                   onclick="event.stopPropagation(); handleCheckboxChange();">
                            
                            <div class="email-main">
                                <div class="email-header">
                                    <!-- Email Type Badge (SENT or RECEIVED) -->
                                    <span class="email-type-badge email-type-<?= $email['email_type'] ?>">
                                        <?= strtoupper($email['email_type']) ?>
                                    </span>
                                    
                                    <span class="deleted-badge">
                                        <span class="material-icons" style="font-size: 14px;">delete</span>
                                        Deleted <?= date('M j', strtotime($email['deleted_at'])) ?>
                                    </span>
                                    
                                    <?php if ($email['email_type'] === 'sent'): ?>
                                        <span class="email-sender">To: <?= htmlspecialchars($email['recipient_email']) ?></span>
                                    <?php else: ?>
                                        <span class="email-sender">From: <?= htmlspecialchars($email['sender_email']) ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="email-subject">
                                    <?= htmlspecialchars($email['subject']) ?: '(No Subject)' ?>
                                </div>
                                
                                <?php if (!empty($email['body_text'])): ?>
                                <div class="email-preview">
                                    <?= htmlspecialchars(substr(strip_tags($email['body_text']), 0, 100)) ?>...
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="email-meta">
                                <?php if ($email['attachment_count'] > 0): ?>
                                    <span class="material-icons attachment-icon">attach_file</span>
                                <?php endif; ?>
                                
                                <?php if ($email['label_name']): ?>
                                    <span class="email-label" style="background-color: <?= $email['label_color'] ?>20; color: <?= $email['label_color'] ?>;">
                                        <span class="material-icons" style="font-size: 16px;">label</span>
                                        <?= htmlspecialchars($email['label_name']) ?>
                                    </span>
                                <?php endif; ?>
                                
                                <div class="email-date">
                                    <?= date('M j, Y', strtotime($email['email_date'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bulk Actions Toolbar -->
        <div class="bulk-toolbar" id="bulkActionToolbar">
            <div>
                <span id="selectedCount">0</span> selected
            </div>
            <div style="display: flex; gap: 12px;">
                <button class="btn btn-primary" onclick="bulkRestore()">
                    <span class="material-icons">restore</span>
                    Restore
                </button>
                <button class="btn btn-secondary" style="background: var(--apple-red); color: white;" onclick="bulkDeleteForever()">
                    <span class="material-icons">delete_forever</span>
                    Delete Forever
                </button>
                <button class="btn btn-secondary" onclick="clearSelection()">Cancel</button>
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

                const response = await fetch('bulk_trash_actions_v2.php', {
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

            if (!confirm(`⚠️ WARNING: This will permanently delete ${emailIds.length} email(s).\n\nThis action CANNOT be undone and will also schedule deletion from the IMAP server.\n\nAre you sure?`)) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'bulk_delete_forever');
                formData.append('email_ids', JSON.stringify(emailIds));

                const response = await fetch('bulk_trash_actions_v2.php', {
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
            window.open('view_email.php?id=' + emailId, '_blank');
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
        });
    </script>
</body>
</html>