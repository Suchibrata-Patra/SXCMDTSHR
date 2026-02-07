<?php
// deleted_items.php - Trash View
session_start();
require 'config.php';
require 'db_config.php';

if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    header("Location: login.php");
    exit();
}

$userEmail = $_SESSION['smtp_user'];

// Define filters from GET parameters
$filters = [
    'search' => $_GET['search'] ?? '',
    'recipient' => $_GET['recipient'] ?? '',
    'subject' => $_GET['subject'] ?? '',
    'label_id' => $_GET['label_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

/**
 * Since we cannot modify db_config.php, we create a local version of the fetcher 
 * that specifically targets current_status = 0 (Deleted).
 */
function getDeletedEmails($userEmail, $limit, $offset, $filters) {
    $pdo = getDatabaseConnection(); // Uses the function from db_config.php
    if (!$pdo) return [];

    $sql = "SELECT se.*, l.label_name, l.label_color 
            FROM sent_emails se 
            LEFT JOIN labels l ON se.label_id = l.id 
            WHERE se.sender_email = :sender_email 
            AND se.current_status = 0"; // Hardcoded filter for deleted items

    $params = [':sender_email' => $userEmail];

    // Replicate the filtering logic found in sent_history.php
    if (!empty($filters['search'])) {
        $sql .= " AND (se.recipient_email LIKE :search OR se.subject LIKE :search OR se.message_body LIKE :search)";
        $params[':search'] = '%' . $filters['search'] . '%';
    }
    if (!empty($filters['recipient'])) {
        $sql .= " AND se.recipient_email LIKE :recipient";
        $params[':recipient'] = '%' . $filters['recipient'] . '%';
    }
    if (!empty($filters['label_id'])) {
        $sql .= " AND se.label_id = :label_id";
        $params[':label_id'] = $filters['label_id'];
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
    $sql = "SELECT COUNT(*) as count FROM sent_emails WHERE sender_email = :sender_email AND current_status = 0";
    $params = [':sender_email' => $userEmail];
    
    // Add same filters as above...
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $res = $stmt->fetch();
    return $res['count'] ?? 0;
}

// Pagination logic
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

$sentEmails = getDeletedEmails($userEmail, $perPage, $offset, $filters);
$totalEmails = getDeletedEmailCount($userEmail, $filters);
$totalPages = ceil($totalEmails / $perPage);

$labels = getLabelCounts($userEmail); // Uses existing function
$hasActiveFilters = !empty(array_filter($filters));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Trash — SXC MDTS</title>
    <style>
        /* ... (Copy styles from sent_history.php) ... */
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div id="main-wrapper">
        <div class="page-header">
            <div class="header-left">
                <p class="page-subtitle">Manage deleted correspondence (Status: Deleted)</p>
            </div>
            </div>

        <div class="email-list-container">
            <div class="email-list">
                <?php if (empty($sentEmails)): ?>
                <div class="empty-state">
                    <span class="material-icons-round">delete_outline</span>
                    <h3>Trash is empty</h3>
                    <p>No deleted emails found.</p>
                </div>
                <?php else: ?>
                    <?php foreach ($sentEmails as $email): ?>
                        <div class="email-item">
                            <div class="col-recipient"><?= htmlspecialchars($email['recipient_email']) ?></div>
                            <div class="col-content">
                                <span class="subject-text"><?= htmlspecialchars($email['subject']) ?></span>
                            </div>
                            <div class="col-date"><?= date('M j, Y', strtotime($email['sent_at'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>