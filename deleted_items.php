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
    /* Apple Pro Palette */
    --system-white: #ffffff;
    --system-bg: #f5f5f7;
    --sidebar-bg: rgba(242, 242, 247, 0.7);
    --glass-bg: rgba(255, 255, 255, 0.7);
    
    /* Text - SF Pro Style */
    --label-primary: #1d1d1f;
    --label-secondary: #86868b;
    --label-tertiary: #a1a1a6;
    
    /* Accents */
    --system-blue: #0071e3;
    --system-blue-hover: #0077ed;
    --system-red: #ff3b30;
    --system-green: #34c759;
    
    /* Depth & Borders */
    --thin-border: 0.5px solid rgba(0, 0, 0, 0.1);
    --glass-blur: blur(20px) saturate(180%);
    --shadow-soft: 0 4px 12px rgba(0, 0, 0, 0.05);
    --shadow-heavy: 0 8px 30px rgba(0, 0, 0, 0.12);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    -webkit-font-smoothing: antialiased;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "SF Pro Text", sans-serif;
    background-color: var(--system-bg);
    color: var(--label-primary);
    letter-spacing: -0.015em;
    height: 100vh;
    display: flex;
}

/* ========== MAIN WRAPPER (The Glass Container) ========== */
#main-wrapper {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: var(--system-white);
    margin: 12px;
    margin-left: 0;
    border-radius: 16px;
    border: var(--thin-border);
    box-shadow: var(--shadow-soft);
    overflow: hidden;
    position: relative;
}

/* ========== TOOLBAR (High-End Glass) ========== */
.toolbar {
    height: 64px;
    padding: 0 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--glass-bg);
    backdrop-filter: var(--glass-blur);
    -webkit-backdrop-filter: var(--glass-blur);
    border-bottom: var(--thin-border);
    z-index: 100;
}

/* Apple Search Input */
.search-field {
    position: relative;
    width: 320px;
}

.search-field input {
    width: 100%;
    height: 36px;
    padding: 0 12px 0 36px;
    border: none;
    border-radius: 10px;
    background: rgba(0, 0, 0, 0.05);
    font-size: 14px;
    transition: all 0.2s ease;
}

.search-field input:focus {
    background: var(--system-white);
    box-shadow: 0 0 0 4px rgba(0, 113, 227, 0.1);
    outline: none;
}

.search-field .icon-left {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 18px;
    color: var(--label-secondary);
}

/* ========== EMAIL ROW (Precision List) ========== */
.content-area {
    flex: 1;
    overflow-y: auto;
    background: var(--system-white);
}

.email-row {
    display: grid;
    grid-template-columns: 44px 220px 1fr 100px;
    align-items: center;
    padding: 14px 24px;
    border-bottom: 0.5px solid rgba(0, 0, 0, 0.05);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    background: var(--system-white);
}

.email-row:hover {
    background: #f9f9fb;
    transform: scale(0.998); /* Subtle "click-ready" feel */
}

.email-row.selected {
    background: rgba(0, 113, 227, 0.04);
}

/* Typography Overhaul */
.email-from {
    font-size: 14px;
    font-weight: 600;
    color: var(--label-primary);
}

.email-subject {
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 2px;
}

.email-preview {
    font-size: 13px;
    color: var(--label-secondary);
    font-weight: 400;
    line-height: 1.4;
}

.email-meta {
    text-align: right;
    font-size: 12px;
    font-weight: 500;
    color: var(--label-tertiary);
}

/* ========== SELECTION FLOATING BAR ========== */
/* Instead of pushing content, Apple often uses a floating capsule */
.selection-bar {
    position: absolute;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%) translateY(100px);
    background: rgba(29, 29, 31, 0.9);
    backdrop-filter: blur(20px);
    padding: 10px 20px;
    border-radius: 100px;
    display: flex;
    align-items: center;
    gap: 16px;
    color: white;
    box-shadow: var(--shadow-heavy);
    transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    z-index: 1000;
}

.selection-bar.active {
    transform: translateX(-50%) translateY(0);
}

.selection-info {
    font-size: 13px;
    border-right: 1px solid rgba(255,255,255,0.2);
    padding-right: 16px;
}

.action-button {
    background: transparent;
    border: none;
    color: white;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    opacity: 0.9;
}

.action-button:hover {
    opacity: 1;
}

.action-button.restore { color: #30d158; }
.action-button.delete { color: #ff453a; }

/* ========== EMPTY STATE ========== */
.empty-state {
    height: 60%;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    color: var(--label-tertiary);
}

.empty-icon {
    background: none;
    font-size: 64px;
    margin-bottom: 20px;
}

/* ========== PAGINATION ========== */
.pagination {
    padding: 16px;
    background: var(--glass-bg);
    backdrop-filter: var(--glass-blur);
    border-top: var(--thin-border);
    display: flex;
    justify-content: center;
    gap: 8px;
}

.page-button {
    height: 32px;
    min-width: 32px;
    border-radius: 8px;
    background: rgba(0,0,0,0.03);
    color: var(--label-primary);
    font-size: 13px;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: background 0.2s;
}

.page-button.active {
    background: var(--system-blue);
    color: white;
}

/* Checkbox Styling (Apple Style) */
.email-checkbox {
    width: 18px;
    height: 18px;
    border-radius: 5px;
    accent-color: var(--system-blue);
    cursor: pointer;
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