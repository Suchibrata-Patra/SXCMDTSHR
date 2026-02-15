<?php
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
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

/**
 * Check if sent_emails table exists
 */
function tableExists($tableName) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return false;
        
        $stmt = $pdo->query("SHOW TABLES LIKE '$tableName'");
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        error_log("Error checking table existence: " . $e->getMessage());
        return false;
    }
}

/**
 * Get deleted emails - handles both tables if they exist
 */
function getAllDeletedEmails($userEmail, $limit, $offset, $filters) {
    $pdo = getDatabaseConnection();
    if (!$pdo) return [];
    
    try {
        $hasSentEmails = tableExists('sent_emails');
        error_log("sent_emails table exists: " . ($hasSentEmails ? "YES" : "NO"));
        
        if ($hasSentEmails) {
            // Use UNION query for both tables
            $sentWhere = "se.sender_email = :sender_email AND se.is_deleted = 1";
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
            
            // UNION query
            $sql = "
                (SELECT 
                    se.id,
                    'sent' as email_type,
                    se.sender_email,
                    se.recipient_email,
                    se.subject,
                    se.message_body as body,
                    se.sent_at as email_date,
                    se.sent_at as deleted_at,
                    CASE WHEN se.attachment_names IS NOT NULL AND se.attachment_names != '' THEN 1 ELSE 0 END as has_attachments
                FROM sent_emails se
                WHERE {$sentWhere})
                
                UNION ALL
                
                (SELECT 
                    im.id,
                    'received' as email_type,
                    im.sender_email,
                    im.user_email as recipient_email,
                    im.subject,
                    im.body,
                    im.received_date as email_date,
                    im.deleted_at,
                    im.has_attachments
                FROM inbox_messages im
                WHERE {$inboxWhere})
                
                ORDER BY deleted_at DESC, email_date DESC
                LIMIT :limit OFFSET :offset
            ";
        } else {
            // Only query inbox_messages
            $inboxWhere = "im.user_email = :user_email AND im.is_deleted = 1";
            $params = [':user_email' => $userEmail];
            
            // Add search filter
            if (!empty($filters['search'])) {
                $searchTerm = '%' . $filters['search'] . '%';
                $inboxWhere .= " AND (im.sender_email LIKE :search OR im.subject LIKE :search2 OR im.body LIKE :search3)";
                $params[':search'] = $searchTerm;
                $params[':search2'] = $searchTerm;
                $params[':search3'] = $searchTerm;
            }
            
            // Add date filters
            if (!empty($filters['date_from'])) {
                $inboxWhere .= " AND DATE(im.received_date) >= :date_from";
                $params[':date_from'] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $inboxWhere .= " AND DATE(im.received_date) <= :date_to";
                $params[':date_to'] = $filters['date_to'];
            }
            
            // Simple query for inbox only
            $sql = "
                SELECT 
                    im.id,
                    'received' as email_type,
                    im.sender_email,
                    im.user_email as recipient_email,
                    im.subject,
                    im.body,
                    im.received_date as email_date,
                    im.deleted_at,
                    im.has_attachments
                FROM inbox_messages im
                WHERE {$inboxWhere}
                ORDER BY im.deleted_at DESC, im.received_date DESC
                LIMIT :limit OFFSET :offset
            ";
        }
        
        $stmt = $pdo->prepare($sql);
        
        // Bind all parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        
        $stmt->execute();
        $results = $stmt->fetchAll();
        
        error_log("Deleted emails found: " . count($results));
        
        return $results;
        
    } catch (PDOException $e) {
        error_log("Error fetching deleted emails: " . $e->getMessage());
        return [];
    }
}

/**
 * Get total count of deleted emails
 */
function getAllDeletedEmailCount($userEmail, $filters) {
    $pdo = getDatabaseConnection();
    if (!$pdo) return 0;
    
    try {
        $hasSentEmails = tableExists('sent_emails');
        
        if ($hasSentEmails) {
            $sentWhere = "se.sender_email = :sender_email AND se.is_deleted = 1";
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
        } else {
            $inboxWhere = "im.user_email = :user_email AND im.is_deleted = 1";
            $params = [':user_email' => $userEmail];
            
            if (!empty($filters['search'])) {
                $searchTerm = '%' . $filters['search'] . '%';
                $inboxWhere .= " AND (im.sender_email LIKE :search OR im.subject LIKE :search2 OR im.body LIKE :search3)";
                $params[':search'] = $searchTerm;
                $params[':search2'] = $searchTerm;
                $params[':search3'] = $searchTerm;
            }
            
            if (!empty($filters['date_from'])) {
                $inboxWhere .= " AND DATE(im.received_date) >= :date_from";
                $params[':date_from'] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $inboxWhere .= " AND DATE(im.received_date) <= :date_to";
                $params[':date_to'] = $filters['date_to'];
            }
            
            $sql = "SELECT COUNT(*) as total_count FROM inbox_messages im WHERE {$inboxWhere}";
        }
        
        $stmt = $pdo->prepare($sql);
        
        // Bind parameters
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
$limit = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Get deleted emails and count
$deletedEmails = getAllDeletedEmails($userEmail, $limit, $offset, $filters);
$totalEmails = getAllDeletedEmailCount($userEmail, $filters);
$totalPages = ceil($totalEmails / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deleted Items - Email System</title>
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
            --border: #E5E5EA;
            --text: #1C1C1E;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #FFFFFF;
            color: var(--text);
        }

        #main-wrapper {
            margin-left: 280px;
            padding: 32px;
            max-width: 1400px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 1px solid var(--border);
        }

        .header-left h1 {
            font-size: 32px;
            font-weight: 600;
            color: #1c1c1e;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }

        .header-left h1 .material-icons {
            font-size: 32px;
            color: #FF3B30;
        }

        .page-subtitle {
            font-size: 14px;
            color: var(--apple-gray);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .email-count-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            background: var(--apple-light-gray);
            border-radius: 12px;
            font-size: 13px;
            font-weight: 500;
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
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-secondary {
            background: var(--apple-light-gray);
            color: #1c1c1e;
        }

        .btn-secondary:hover {
            background: #E5E5EA;
        }

        .content-wrapper {
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }

        .toolbar {
            padding: 16px;
            border-bottom: 1px solid var(--border);
            display: flex;
            gap: 12px;
            align-items: center;
            background: var(--apple-light-gray);
        }

        .search-bar {
            flex: 1;
            position: relative;
            display: flex;
            align-items: center;
            background: white;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 8px 12px;
        }

        .search-input {
            flex: 1;
            border: none;
            outline: none;
            font-size: 14px;
            margin-left: 8px;
        }

        .email-list {
            max-height: calc(100vh - 350px);
            overflow-y: auto;
        }

        .email-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: background 0.15s;
        }

        .email-item:hover {
            background: var(--apple-light-gray);
        }

        .email-main {
            flex: 1;
            min-width: 0;
        }

        .email-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 6px;
            flex-wrap: wrap;
        }

        .email-type-badge {
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .email-type-sent {
            background: #E3F2FD;
            color: #1976D2;
        }

        .email-type-received {
            background: #F3E5F5;
            color: #7B1FA2;
        }

        .deleted-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            background: #FFEBEE;
            color: #C62828;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }

        .email-sender {
            font-size: 13px;
            color: var(--apple-gray);
        }

        .email-subject {
            font-size: 15px;
            font-weight: 500;
            color: #1c1c1e;
            margin-bottom: 4px;
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
            flex-shrink: 0;
        }

        .email-date {
            font-size: 13px;
            color: var(--apple-gray);
            white-space: nowrap;
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
                        <?= number_format($totalEmails) ?> deleted items
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
            // Open in new window for viewing
            window.open('view_message.php?id=' + emailId, '_blank');
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