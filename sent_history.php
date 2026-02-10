<?php
// sent_history.php - Premium Email Archive for Simplified 2-Table Structure
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

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Get filtered emails using simplified function
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
    <title>Sent Emails - SXC MDTS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --apple-blue: #007AFF;
            --apple-gray: #8E8E93;
            --apple-light-gray: #F2F2F7;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            color: #1c1c1e;
            line-height: 1.5;
        }

        .app-container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            padding: 20px;
        }

        .header {
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 32px;
            font-weight: 700;
            color: #1c1c1e;
            margin-bottom: 10px;
        }

        .stats-row {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            flex: 1;
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            border: 1px solid #e5e7eb;
        }

        .stat-card h3 {
            font-size: 14px;
            color: var(--apple-gray);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .stat-card .value {
            font-size: 28px;
            font-weight: 700;
            color: var(--apple-blue);
        }

        .filters-bar {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            border: 1px solid #e5e7eb;
            margin-bottom: 20px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .filter-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--apple-blue);
            box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
        }

        .email-list-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }

        .email-table {
            width: 100%;
        }

        .email-item {
            display: grid;
            grid-template-columns: 40px 1fr 2fr 150px 120px 120px;
            gap: 15px;
            padding: 16px 20px;
            border-bottom: 1px solid #f3f4f6;
            align-items: center;
            cursor: pointer;
            transition: background 0.2s;
        }

        .email-item:hover {
            background: #f9fafb;
        }

        .col-checkbox input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .col-recipient {
            font-size: 14px;
            font-weight: 600;
            color: #1c1c1e;
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

        .label-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            color: white;
        }

        .col-attachment {
            text-align: center;
        }

        .col-date {
            font-size: 13px;
            color: var(--apple-gray);
            text-align: right;
        }

        .empty-state {
            padding: 80px 20px;
            text-align: center;
            color: var(--apple-gray);
        }

        .empty-state .material-icons {
            font-size: 64px;
            opacity: 0.3;
            margin-bottom: 16px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            padding: 20px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            color: #1c1c1e;
            font-weight: 500;
            transition: all 0.2s;
        }

        .pagination a:hover {
            background: var(--apple-light-gray);
        }

        .pagination .active {
            background: var(--apple-blue);
            color: white;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--apple-blue);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn:hover {
            background: #0056b3;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #6b7280;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        @media (max-width: 768px) {
            .email-item {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .stats-row {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <h1>Sent Emails</h1>
                <p>Total: <?= $totalEmails ?> emails</p>
            </div>

            <!-- Stats Row -->
            <div class="stats-row">
                <div class="stat-card">
                    <h3>Total Sent</h3>
                    <div class="value"><?= $totalEmails ?></div>
                </div>
                <div class="stat-card">
                    <h3>Labeled</h3>
                    <div class="value"><?= $totalEmails - $unlabeledCount ?></div>
                </div>
                <div class="stat-card">
                    <h3>Unlabeled</h3>
                    <div class="value"><?= $unlabeledCount ?></div>
                </div>
                <div class="stat-card">
                    <h3>Labels</h3>
                    <div class="value"><?= count($labels) ?></div>
                </div>
            </div>

            <!-- Filters Bar -->
            <div class="filters-bar">
                <form method="GET" action="">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label>Search</label>
                            <input type="text" name="search" placeholder="Search emails..." value="<?= htmlspecialchars($filters['search']) ?>">
                        </div>
                        <div class="filter-group">
                            <label>Recipient</label>
                            <input type="email" name="recipient" placeholder="Filter by recipient..." value="<?= htmlspecialchars($filters['recipient']) ?>">
                        </div>
                        <div class="filter-group">
                            <label>Label</label>
                            <select name="label_id">
                                <option value="">All Labels</option>
                                <option value="unlabeled" <?= $filters['label_id'] === 'unlabeled' ? 'selected' : '' ?>>Unlabeled</option>
                                <?php foreach ($labels as $label): ?>
                                <option value="<?= $label['label_id'] ?>" <?= $filters['label_id'] == $label['label_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label['label_name']) ?> (<?= $label['email_count'] ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Date From</label>
                            <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from']) ?>">
                        </div>
                        <div class="filter-group">
                            <label>Date To</label>
                            <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to']) ?>">
                        </div>
                        <div class="filter-group" style="display: flex; align-items: flex-end; gap: 10px;">
                            <button type="submit" class="btn">
                                <i class="fas fa-search"></i>
                                Filter
                            </button>
                            <?php if ($hasActiveFilters): ?>
                            <a href="sent_history.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i>
                                Clear
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Email List -->
            <div class="email-list-container">
                <div class="email-table">
                    <?php if (empty($sentEmails)): ?>
                    <div class="empty-state">
                        <span class="material-icons">inbox</span>
                        <h3>No emails found</h3>
                        <p>Try adjusting your filters or search query</p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($sentEmails as $email): ?>
                        <div class="email-item" onclick="window.location.href='view_sent_email.php?id=<?= $email['id'] ?>'">
                            <div class="col-checkbox" onclick="event.stopPropagation();">
                                <input type="checkbox" class="email-checkbox" value="<?= $email['id'] ?>">
                            </div>

                            <div class="col-recipient">
                                <?= htmlspecialchars($email['recipient_email']) ?>
                            </div>

                            <div class="col-subject">
                                <?= htmlspecialchars($email['subject']) ?>
                                <?php if (!empty($email['article_title']) && $email['article_title'] != $email['subject']): ?>
                                <span style="color: #9ca3af;"> - <?= htmlspecialchars($email['article_title']) ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="col-label">
                                <?php if (!empty($email['label_name'])): ?>
                                <span class="label-badge" style="background-color: <?= htmlspecialchars($email['label_color'] ?? '#6b7280') ?>">
                                    <?= htmlspecialchars($email['label_name']) ?>
                                </span>
                                <?php endif; ?>
                            </div>

                            <div class="col-attachment">
                                <?php if ($email['has_attachments']): ?>
                                <span class="material-icons" style="font-size: 18px; color: var(--apple-gray);">attach_file</span>
                                <?php if (!empty($email['attachment_count'])): ?>
                                <span style="font-size: 12px; color: var(--apple-gray);">(<?= $email['attachment_count'] ?>)</span>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <div class="col-date">
                                <?= date('M j, Y', strtotime($email['sent_at'])) ?>
                                <div style="font-size: 11px; color: #9ca3af;">
                                    <?= date('g:i A', strtotime($email['sent_at'])) ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&<?= http_build_query($filters) ?>">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?>&<?= http_build_query($filters) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>&<?= http_build_query($filters) ?>">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>