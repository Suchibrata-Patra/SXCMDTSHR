<?php
// deleted_items.php - Shows ALL deleted emails (BOTH sent from sent_emails AND received from inbox_messages)
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'db_config.php';

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

/**
 * Get BOTH deleted sent emails AND deleted received emails
 * UNION query combines both tables
 */
function getAllDeletedEmails($userEmail, $limit, $offset, $filters) {
    $pdo = getDatabaseConnection();
    if (!$pdo) return [];
    
    try {
        // Build WHERE clauses for filters
        $sentWhere = "se.sender_email = :sender_email AND se.current_status = 0";
        $inboxWhere = "im.user_email = :user_email AND im.is_deleted = 1";
        $params = [
            ':sender_email' => $userEmail,
            ':user_email' => $userEmail
        ];
        
        // Add search filter
        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $sentWhere .= " AND (se.recipient_email LIKE :search1 OR se.subject LIKE :search2 OR se.message_body LIKE :search3)";
            $inboxWhere .= " AND (im.sender_email LIKE :search4 OR im.subject LIKE :search5 OR im.body LIKE :search6)";
            $params[':search1'] = $searchTerm;
            $params[':search2'] = $searchTerm;
            $params[':search3'] = $searchTerm;
            $params[':search4'] = $searchTerm;
            $params[':search5'] = $searchTerm;
            $params[':search6'] = $searchTerm;
        }
        
        // Add sender filter
        if (!empty($filters['sender'])) {
            $senderTerm = '%' . $filters['sender'] . '%';
            $inboxWhere .= " AND im.sender_email LIKE :sender_filter";
            $params[':sender_filter'] = $senderTerm;
        }
        
        // Add recipient filter  
        if (!empty($filters['recipient'])) {
            $recipientTerm = '%' . $filters['recipient'] . '%';
            $sentWhere .= " AND se.recipient_email LIKE :recipient_filter";
            $params[':recipient_filter'] = $recipientTerm;
        }
        
        // Add subject filter
        if (!empty($filters['subject'])) {
            $subjectTerm = '%' . $filters['subject'] . '%';
            $sentWhere .= " AND se.subject LIKE :subject_sent";
            $inboxWhere .= " AND im.subject LIKE :subject_inbox";
            $params[':subject_sent'] = $subjectTerm;
            $params[':subject_inbox'] = $subjectTerm;
        }
        
        // Add label filter for sent emails
        if (!empty($filters['label_id'])) {
            if ($filters['label_id'] === 'unlabeled') {
                $sentWhere .= " AND se.label_id IS NULL";
            } else {
                $sentWhere .= " AND se.label_id = :label_id";
                $params[':label_id'] = $filters['label_id'];
            }
        }
        
        // Add date filters
        if (!empty($filters['date_from'])) {
            $sentWhere .= " AND DATE(se.sent_at) >= :date_from_sent";
            $inboxWhere .= " AND DATE(im.received_date) >= :date_from_inbox";
            $params[':date_from_sent'] = $filters['date_from'];
            $params[':date_from_inbox'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sentWhere .= " AND DATE(se.sent_at) <= :date_to_sent";
            $inboxWhere .= " AND DATE(im.received_date) <= :date_to_inbox";
            $params[':date_to_sent'] = $filters['date_to'];
            $params[':date_to_inbox'] = $filters['date_to'];
        }
        
        // UNION query to get both sent and received deleted emails
        $sql = "
            (SELECT 
                se.id,
                'sent' as email_type,
                se.sender_email,
                se.recipient_email,
                se.subject,
                se.message_body as body,
                se.article_title,
                se.attachment_names,
                se.sent_at as email_date,
                se.sent_at as deleted_at,
                se.label_id,
                l.label_name,
                l.label_color,
                CASE WHEN se.attachment_names IS NOT NULL AND se.attachment_names != '' THEN 1 ELSE 0 END as has_attachments
            FROM sent_emails se
            LEFT JOIN labels l ON se.label_id = l.id
            WHERE {$sentWhere})
            
            UNION ALL
            
            (SELECT 
                im.id,
                'received' as email_type,
                im.sender_email,
                im.user_email as recipient_email,
                im.subject,
                im.body,
                NULL as article_title,
                NULL as attachment_names,
                im.received_date as email_date,
                im.deleted_at,
                NULL as label_id,
                NULL as label_name,
                NULL as label_color,
                im.has_attachments
            FROM inbox_messages im
            WHERE {$inboxWhere})
            
            ORDER BY deleted_at DESC, email_date DESC
            LIMIT :limit OFFSET :offset
        ";
        
        $stmt = $pdo->prepare($sql);
        
        // Bind all parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error fetching deleted emails: " . $e->getMessage());
        return [];
    }
}

/**
 * Get total count of deleted emails (both sent and received)
 */
function getAllDeletedEmailCount($userEmail, $filters) {
    $pdo = getDatabaseConnection();
    if (!$pdo) return 0;
    
    try {
        $sentWhere = "se.sender_email = :sender_email AND se.current_status = 0";
        $inboxWhere = "im.user_email = :user_email AND im.is_deleted = 1";
        $params = [
            ':sender_email' => $userEmail,
            ':user_email' => $userEmail
        ];
        
        // Add search filter
        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $sentWhere .= " AND (se.recipient_email LIKE :search1 OR se.subject LIKE :search2 OR se.message_body LIKE :search3)";
            $inboxWhere .= " AND (im.sender_email LIKE :search4 OR im.subject LIKE :search5 OR im.body LIKE :search6)";
            $params[':search1'] = $searchTerm;
            $params[':search2'] = $searchTerm;
            $params[':search3'] = $searchTerm;
            $params[':search4'] = $searchTerm;
            $params[':search5'] = $searchTerm;
            $params[':search6'] = $searchTerm;
        }
        
        if (!empty($filters['sender'])) {
            $senderTerm = '%' . $filters['sender'] . '%';
            $inboxWhere .= " AND im.sender_email LIKE :sender_filter";
            $params[':sender_filter'] = $senderTerm;
        }
        
        if (!empty($filters['recipient'])) {
            $recipientTerm = '%' . $filters['recipient'] . '%';
            $sentWhere .= " AND se.recipient_email LIKE :recipient_filter";
            $params[':recipient_filter'] = $recipientTerm;
        }
        
        if (!empty($filters['subject'])) {
            $subjectTerm = '%' . $filters['subject'] . '%';
            $sentWhere .= " AND se.subject LIKE :subject_sent";
            $inboxWhere .= " AND im.subject LIKE :subject_inbox";
            $params[':subject_sent'] = $subjectTerm;
            $params[':subject_inbox'] = $subjectTerm;
        }
        
        if (!empty($filters['label_id'])) {
            if ($filters['label_id'] === 'unlabeled') {
                $sentWhere .= " AND se.label_id IS NULL";
            } else {
                $sentWhere .= " AND se.label_id = :label_id";
                $params[':label_id'] = $filters['label_id'];
            }
        }
        
        if (!empty($filters['date_from'])) {
            $sentWhere .= " AND DATE(se.sent_at) >= :date_from_sent";
            $inboxWhere .= " AND DATE(im.received_date) >= :date_from_inbox";
            $params[':date_from_sent'] = $filters['date_from'];
            $params[':date_from_inbox'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sentWhere .= " AND DATE(se.sent_at) <= :date_to_sent";
            $inboxWhere .= " AND DATE(im.received_date) <= :date_to_inbox";
            $params[':date_to_sent'] = $filters['date_to'];
            $params[':date_to_inbox'] = $filters['date_to'];
        }
        
        $sql = "
            SELECT 
                (SELECT COUNT(*) FROM sent_emails se WHERE {$sentWhere}) +
                (SELECT COUNT(*) FROM inbox_messages im WHERE {$inboxWhere}) 
            as total_count
        ";
        
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->execute();
        
        $result = $stmt->fetch();
        return $result['total_count'] ?? 0;
        
    } catch (PDOException $e) {
        error_log("Error counting deleted emails: " . $e->getMessage());
        return 0;
    }
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Get deleted emails (BOTH sent and received)
$deletedEmails = getAllDeletedEmails($userEmail, $perPage, $offset, $filters);
$totalEmails = getAllDeletedEmailCount($userEmail, $filters);
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
            text-decoration: none;
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
            grid-template-columns: 1fr auto;
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
            flex-wrap: wrap;
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
    <?php include('sidebar.php') ?>
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
                        <?= number_format($totalEmails) ?> deleted (Sent + Received)
                    </span>
                </p>
            </div>
            <div class="header-actions">
                <a href="inbox.php" class="btn btn-secondary">
                    <span class="material-icons">arrow_back</span>
                    Back to Inbox
                </a>
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
                        <div class="email-item" onclick="openEmail('<?= $email['email_type'] ?>', <?= $email['id'] ?>)">
                            <div class="email-main">
                                <div class="email-header">
                                    <!-- Email Type Badge -->
                                    <span class="email-type-badge email-type-<?= $email['email_type'] ?>">
                                        <?= strtoupper($email['email_type']) ?>
                                    </span>
                                    
                                    <span class="deleted-badge">
                                        <span class="material-icons" style="font-size: 14px;">delete</span>
                                        Deleted
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
                                
                                <?php if (!empty($email['body'])): ?>
                                <div class="email-preview">
                                    <?= htmlspecialchars(substr(strip_tags($email['body']), 0, 100)) ?>...
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="email-meta">
                                <?php if ($email['has_attachments']): ?>
                                    <span class="material-icons" style="color: var(--apple-gray); font-size: 18px;">attach_file</span>
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
        function openEmail(type, emailId) {
            if (type === 'sent') {
                window.open('view_sent_email.php?id=' + emailId, '_blank');
            } else {
                window.open('view_sent_email.php?id=' + emailId, '_blank');
            }
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
    </script>
</body>
</html>