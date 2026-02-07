<?php
// sent_history.php - Enhanced with filtering and labeling system
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
    <title>Email Archive | SXC MDTS</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Libre+Baskerville:wght@400;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    
    <style>
        :root {
            --nature-red: #e4002b;
            --text-main: #222222;
            --text-muted: #555555;
            --border-color: #eeeeee;
            --hover-bg: #f9f9f9;
            --accent-blue: #0973dc;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background-color: #ffffff;
            color: var(--text-main);
            display: flex;
            height: 100vh;
        }

        #main-wrapper {
            flex: 1;
            overflow-y: auto;
            padding: 20px 40px;
        }

        /* Header Area */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--text-main);
            margin-bottom: 10px;
        }

        .header-left h1 {
            font-family: 'Libre Baskerville', serif;
            font-size: 24px;
            font-weight: 700;
        }

        .count-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--nature-red);
            font-weight: 600;
        }

        .header-right {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        /* Filter Toggle Button */
        .btn-minimal {
            border: 1px solid var(--text-main);
            padding: 6px 14px;
            text-decoration: none;
            color: var(--text-main);
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            transition: all 0.2s;
            background: white;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-minimal:hover {
            background: var(--text-main);
            color: #fff;
        }

        .btn-minimal.active {
            background: var(--accent-blue);
            color: white;
            border-color: var(--accent-blue);
        }

        /* Filter Panel */
        .filter-panel {
            background: #f8f9fa;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 20px;
            display: none;
        }

        .filter-panel.active {
            display: block;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-label {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 6px;
            letter-spacing: 0.5px;
        }

        .filter-input, .filter-select {
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s;
        }

        .filter-input:focus, .filter-select:focus {
            outline: none;
            border-color: var(--accent-blue);
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn-filter {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-apply {
            background: var(--accent-blue);
            color: white;
        }

        .btn-apply:hover {
            background: #0861c9;
        }

        .btn-clear {
            background: white;
            color: var(--text-main);
            border: 1px solid var(--border-color);
        }

        .btn-clear:hover {
            background: var(--hover-bg);
        }

        /* Active Filters Display */
        .active-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 16px;
        }

        .filter-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--accent-blue);
            color: white;
            padding: 4px 10px;
            border-radius: 16px;
            font-size: 12px;
            font-weight: 500;
        }

        .filter-tag .remove {
            cursor: pointer;
            font-size: 16px;
        }

        /* Email List */
        .email-list {
            list-style: none;
            width: 100%;
        }

        .email-item {
            display: flex;
            align-items: center;
            padding: 12px 10px;
            border-bottom: 1px solid var(--border-color);
            text-decoration: none;
            color: inherit;
            transition: background 0.1s ease;
            position: relative;
        }

        .email-item:hover {
            background-color: var(--hover-bg);
        }

        .email-item:hover .quick-label {
            opacity: 1;
        }

        /* Label Badge */
        .label-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            color: white;
            margin-right: 8px;
        }

        /* Quick Label Dropdown */
        .quick-label {
            position: absolute;
            right: 120px;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .label-dropdown {
            position: relative;
            display: inline-block;
        }

        .label-dropdown-btn {
            background: white;
            border: 1px solid var(--border-color);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .label-dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 100;
            min-width: 160px;
            max-height: 300px;
            overflow-y: auto;
        }

        .label-dropdown:hover .label-dropdown-content {
            display: block;
        }

        .label-option {
            padding: 8px 12px;
            cursor: pointer;
            transition: background 0.1s;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }

        .label-option:hover {
            background: var(--hover-bg);
        }

        .label-color-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        /* Column Controls */
        .col-recipient {
            flex: 0 0 200px;
            font-weight: 600;
            font-size: 13.5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding-right: 15px;
        }

        .col-content {
            flex: 1;
            display: flex;
            align-items: center;
            min-width: 0;
            gap: 10px;
        }

        .subject-text {
            font-family: 'Libre Baskerville', serif;
            font-size: 14.5px;
            font-weight: 700;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .snippet-text {
            color: var(--text-muted);
            font-size: 13.5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .col-date {
            flex: 0 0 100px;
            text-align: right;
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* Pagination */
        .pagination {
            margin-top: 20px;
            display: flex;
            gap: 5px;
        }

        .page-link {
            padding: 5px 10px;
            border: 1px solid var(--border-color);
            text-decoration: none;
            font-size: 12px;
            color: var(--text-main);
        }

        .page-link.active {
            background: var(--nature-red);
            color: #fff;
            border-color: var(--nature-red);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }

        @media (max-width: 900px) {
            .col-recipient { flex: 0 0 150px; }
            .snippet-text { display: none; }
            .filter-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div id="main-wrapper">
        <header class="page-header">
            <div class="header-left">
                <p class="count-label">Sent Archive / <?= $totalEmails ?> 
                    <?= $hasActiveFilters ? 'filtered' : 'total' ?></p>
                <h1>Correspondence</h1>
            </div>
            <div class="header-right">
                <button class="btn-minimal <?= $hasActiveFilters ? 'active' : '' ?>" onclick="toggleFilters()">
                    <i class="fa-solid fa-filter"></i>
                    Filters
                </button>
                <a href="index.php" class="btn-minimal">New Draft</a>
            </div>
        </header>

        <!-- Active Filters Display -->
        <?php if ($hasActiveFilters): ?>
        <div class="active-filters">
            <?php if (!empty($filters['search'])): ?>
                <span class="filter-tag">
                    Search: "<?= htmlspecialchars($filters['search']) ?>"
                    <span class="remove" onclick="removeFilter('search')">×</span>
                </span>
            <?php endif; ?>
            
            <?php if (!empty($filters['recipient'])): ?>
                <span class="filter-tag">
                    Recipient: "<?= htmlspecialchars($filters['recipient']) ?>"
                    <span class="remove" onclick="removeFilter('recipient')">×</span>
                </span>
            <?php endif; ?>
            
            <?php if (!empty($filters['subject'])): ?>
                <span class="filter-tag">
                    Subject: "<?= htmlspecialchars($filters['subject']) ?>"
                    <span class="remove" onclick="removeFilter('subject')">×</span>
                </span>
            <?php endif; ?>
            
            <?php if (!empty($filters['label_id'])): ?>
                <span class="filter-tag">
                    <?php
                    if ($filters['label_id'] === 'unlabeled') {
                        echo 'Unlabeled';
                    } else {
                        foreach ($labels as $label) {
                            if ($label['id'] == $filters['label_id']) {
                                echo 'Label: ' . htmlspecialchars($label['label_name']);
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
                    Date: <?= !empty($filters['date_from']) ? date('M j, Y', strtotime($filters['date_from'])) : 'Any' ?> 
                    - <?= !empty($filters['date_to']) ? date('M j, Y', strtotime($filters['date_to'])) : 'Any' ?>
                    <span class="remove" onclick="clearDateFilters()">×</span>
                </span>
            <?php endif; ?>
            
            <button class="btn-clear" onclick="clearAllFilters()" style="padding: 4px 12px; font-size: 11px;">
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
                        <input type="text" name="search" class="filter-input" 
                               placeholder="Search everywhere..." 
                               value="<?= htmlspecialchars($filters['search']) ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Recipient</label>
                        <input type="text" name="recipient" class="filter-input" 
                               placeholder="email@example.com" 
                               value="<?= htmlspecialchars($filters['recipient']) ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Subject</label>
                        <input type="text" name="subject" class="filter-input" 
                               placeholder="Subject keywords..." 
                               value="<?= htmlspecialchars($filters['subject']) ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Label</label>
                        <select name="label_id" class="filter-select">
                            <option value="">All Labels</option>
                            <option value="unlabeled" <?= $filters['label_id'] === 'unlabeled' ? 'selected' : '' ?>>
                                Unlabeled (<?= $unlabeledCount ?>)
                            </option>
                            <?php foreach ($labels as $label): ?>
                                <option value="<?= $label['id'] ?>" 
                                        <?= $filters['label_id'] == $label['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label['label_name']) ?> (<?= $label['email_count'] ?>)
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
                    <button type="button" class="btn-filter btn-clear" onclick="clearForm()">Clear</button>
                    <button type="submit" class="btn-filter btn-apply">Apply Filters</button>
                </div>
            </form>
        </div>

        <div class="email-list">
            <?php if (empty($sentEmails)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-inbox" style="font-size: 48px; opacity: 0.3; margin-bottom: 16px;"></i>
                    <h3><?= $hasActiveFilters ? 'No emails match your filters' : 'Archive is empty' ?></h3>
                    <p><?= $hasActiveFilters ? 'Try adjusting your search criteria' : 'Start by composing your first email' ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($sentEmails as $email): ?>
                <a href="view_sent_email.php?id=<?= $email['id'] ?>" class="email-item" target="_blank">
                    <div class="col-recipient">
                        <?= htmlspecialchars($email['recipient_email']) ?>
                    </div>
                    
                    <div class="col-content">
                        <?php if (!empty($email['label_name'])): ?>
                            <span class="label-badge" style="background-color: <?= htmlspecialchars($email['label_color']) ?>;">
                                <?= htmlspecialchars($email['label_name']) ?>
                            </span>
                        <?php endif; ?>
                        
                        <span class="subject-text"><?= htmlspecialchars($email['subject']) ?></span>
                        <span class="snippet-text">
                            — <?= htmlspecialchars(mb_substr(strip_tags($email['message_body']), 0, 100)) ?>
                        </span>
                    </div>

                    <div class="quick-label">
                        <div class="label-dropdown" onclick="event.preventDefault(); event.stopPropagation();">
                            <button class="label-dropdown-btn">
                                <span class="material-icons" style="font-size: 14px;">label</span>
                            </button>
                            <div class="label-dropdown-content">
                                <div class="label-option" onclick="updateLabel(<?= $email['id'] ?>, null)">
                                    <span class="material-icons" style="font-size: 16px;">label_off</span>
                                    Remove Label
                                </div>
                                <?php foreach ($labels as $label): ?>
                                    <div class="label-option" onclick="updateLabel(<?= $email['id'] ?>, <?= $label['id'] ?>)">
                                        <span class="label-color-dot" style="background: <?= htmlspecialchars($label['label_color']) ?>;"></span>
                                        <?= htmlspecialchars($label['label_name']) ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-date">
                        <?php if (!empty($email['attachment_names'])): ?>
                            <i class="fa-solid fa-paperclip" style="font-size: 10px; margin-right: 8px;"></i>
                        <?php endif; ?>
                        <?= date('M j', strtotime($email['sent_at'])) ?>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php
            $currentParams = $_GET;
            for ($i = 1; $i <= $totalPages; $i++):
                $currentParams['page'] = $i;
                $queryString = http_build_query($currentParams);
            ?>
                <a href="?<?= $queryString ?>" class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleFilters() {
            const panel = document.getElementById('filterPanel');
            panel.classList.toggle('active');
        }

        function clearForm() {
            const form = document.querySelector('#filterPanel form');
            form.reset();
        }

        function removeFilter(filterName) {
            const url = new URL(window.location.href);
            url.searchParams.delete(filterName);
            url.searchParams.delete('page'); // Reset to page 1
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
    </script>
</body>
</html>