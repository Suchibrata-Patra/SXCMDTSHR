<?php
// deleted_items.php - Premium Trash Archive
session_start();
require 'config.php'; //
require 'db_config.php'; //

if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    header("Location: login.php");
    exit();
}

$userEmail = $_SESSION['smtp_user'];

// 1. Get filter parameters
$filters = [
    'search' => $_GET['search'] ?? '',
    'recipient' => $_GET['recipient'] ?? '',
    'subject' => $_GET['subject'] ?? '',
    'label_id' => $_GET['label_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

// 2. Local function to fetch only deleted items (current_status = 0)
function getDeletedEmailsLocal($userEmail, $limit, $offset, $filters) {
    $pdo = getDatabaseConnection(); //
    if (!$pdo) return [];
    
    $sql = "SELECT se.*, l.label_name, l.label_color 
            FROM sent_emails se 
            LEFT JOIN labels l ON se.label_id = l.id 
            WHERE se.sender_email = :sender_email 
            AND se.current_status = 0"; // Force filter for deleted items

    $params = [':sender_email' => $userEmail];

    if (!empty($filters['search'])) {
        $sql .= " AND (se.recipient_email LIKE :search OR se.subject LIKE :search OR se.message_body LIKE :search)";
        $params[':search'] = '%' . $filters['search'] . '%';
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

// 3. Pagination & Data Retrieval
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

$sentEmails = getDeletedEmailsLocal($userEmail, $perPage, $offset, $filters);
$labels = getLabelCounts($userEmail); //
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
            --primary: #0a0a0a; --accent: #c41e3a; --surface: #fafafa; --border: #e4e4e7;
            --text-primary: #0a0a0a; --text-secondary: #52525b; --text-tertiary: #a1a1aa;
        }
        body { font-family: 'Inter', sans-serif; background: var(--surface); display: flex; height: 100vh; overflow: hidden; }
        #main-wrapper { flex: 1; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        .page-header { background: #fff; border-bottom: 1px solid var(--border); padding: 24px 32px; flex-shrink: 0; }
        .page-title { font-family: 'Instrument Serif', serif; font-size: 28px; }
        .email-list-container { flex: 1; overflow-y: auto; padding: 8px 0; }
        .email-item { display: grid; grid-template-columns: 280px 1fr auto 100px; gap: 20px; align-items: center; padding: 12px 32px; background: #fff; border-bottom: 1px solid var(--border); text-decoration: none; color: var(--text-primary); }
        .email-item:hover { background: var(--surface); border-left: 3px solid var(--accent); }
        .subject-text { font-weight: 500; }
        .snippet-text { color: var(--text-tertiary); font-size: 13px; }
        .empty-state { text-align: center; padding: 80px; color: var(--text-secondary); }
        .empty-state .material-icons-round { font-size: 64px; opacity: 0.3; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?> <div id="main-wrapper">
        <div class="page-header">
            <h1 class="page-title">Trash</h1>
            <p style="color: var(--text-secondary); font-size: 14px;">Review and manage deleted messages</p>
        </div>

        <div class="email-list-container">
            <?php if (empty($sentEmails)): ?>
                <div class="empty-state">
                    <span class="material-icons-round">delete_outline</span>
                    <h3>Trash is empty</h3>
                    <p>Deleted emails will appear here.</p>
                </div>
            <?php else: ?>
                <div class="email-list">
                    <?php foreach ($sentEmails as $email): ?>
                        <a href="view_sent_email.php?id=<?= $email['id'] ?>" class="email-item" target="_blank"> <div style="font-size: 13px;"><?= htmlspecialchars($email['recipient_email']) ?></div>
                            <div>
                                <span class="subject-text"><?= htmlspecialchars($email['subject']) ?></span>
                                <span class="snippet-text"> — <?= htmlspecialchars(mb_substr(strip_tags($email['message_body']), 0, 80)) ?>...</span>
                            </div>
                            <div>
                                <?php if (!empty($email['label_name'])): ?>
                                    <span style="background:<?= $email['label_color'] ?>; color:#fff; padding:2px 8px; border-radius:10px; font-size:10px;">
                                        <?= htmlspecialchars($email['label_name']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div style="text-align: right; font-size: 12px; color: var(--text-secondary);">
                                <?= date('M j', strtotime($email['sent_at'])) ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>