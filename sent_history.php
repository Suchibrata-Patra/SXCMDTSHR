<?php
// sent_history.php - Premium Email Archive WITH READ RECEIPTS
session_start();
require 'config.php';
require 'db_config.php';
require_once 'read_tracking_helper.php';

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
$sentEmails = getSentEmailsWithTracking($userEmail, $perPage, $offset, $filters);
$totalEmails = getSentEmailCount($userEmail, $filters);
$totalPages = ceil($totalEmails / $perPage);

// Get all labels
$labels = getLabelCounts($userEmail);
$unlabeledCount = getUnlabeledEmailCount($userEmail);

// Check if filters are active
$hasActiveFilters = !empty(array_filter($filters));

/**
 * Get sent emails WITH READ TRACKING
 */
function getSentEmailsWithTracking($userEmail, $limit = 100, $offset = 0, $filters = []) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return [];

        $userId = getUserId($pdo, $userEmail);
        if (!$userId) return [];

        // Query with LEFT JOIN to read tracking
$sql = "SELECT 
            se.*,
            rt.tracking_token,
            rt.is_read,
            rt.first_read_at,
            rt.total_opens,
            rt.valid_opens,
            rt.device_type,
            rt.browser,
            rt.os
        FROM emails se
        LEFT JOIN email_read_tracking rt ON rt.email_id = se.id
        WHERE se.sender_email = :email
        AND se.email_type = 'sent'";
        
        $params = [':email' => $userEmail];

        // Apply Search Filter
        if (!empty($filters['search'])) {
            $sql .= " AND (se.recipient_email LIKE :search 
                        OR se.subject LIKE :search 
                        OR se.message_body LIKE :search 
                        OR se.article_title LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        // Apply Recipient Filter
        if (!empty($filters['recipient'])) {
            $sql .= " AND se.recipient_email LIKE :recipient";
            $params[':recipient'] = '%' . $filters['recipient'] . '%';
        }

        // Apply Label Filter
        if (!empty($filters['label_id'])) {
            if ($filters['label_id'] === 'unlabeled') {
                $sql .= " AND se.label_id IS NULL";
            } else {
                $sql .= " AND se.label_id = :label_id";
                $params[':label_id'] = $filters['label_id'];
            }
        }

        // Apply Date Range Filters
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
        
        // Bind parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Error fetching sent emails with tracking: " . $e->getMessage());
        return [];
    }
}

// Include rest of original helper functions from sent_history.php
// function getSentEmailCount($userEmail, $filters = []) {
//     try {
//         $pdo = getDatabaseConnection();
//         if (!$pdo) return 0;

//         $sql = "SELECT COUNT(*) as count FROM sent_emails WHERE sender_email = :email AND current_status = 1";
//         $params = [':email' => $userEmail];

//         if (!empty($filters['search'])) {
//             $sql .= " AND (recipient_email LIKE :search OR subject LIKE :search OR message_body LIKE :search)";
//             $params[':search'] = '%' . $filters['search'] . '%';
//         }

//         if (!empty($filters['recipient'])) {
//             $sql .= " AND recipient_email LIKE :recipient";
//             $params[':recipient'] = '%' . $filters['recipient'] . '%';
//         }

//         if (!empty($filters['label_id'])) {
//             if ($filters['label_id'] === 'unlabeled') {
//                 $sql .= " AND label_id IS NULL";
//             } else {
//                 $sql .= " AND label_id = :label_id";
//                 $params[':label_id'] = $filters['label_id'];
//             }
//         }

//         if (!empty($filters['date_from'])) {
//             $sql .= " AND DATE(sent_at) >= :date_from";
//             $params[':date_from'] = $filters['date_from'];
//         }
//         if (!empty($filters['date_to'])) {
//             $sql .= " AND DATE(sent_at) <= :date_to";
//             $params[':date_to'] = $filters['date_to'];
//         }

//         $stmt = $pdo->prepare($sql);
//         $stmt->execute($params);
//         $result = $stmt->fetch(PDO::FETCH_ASSOC);

//         return $result['count'] ?? 0;

//     } catch (PDOException $e) {
//         error_log("Error counting sent emails: " . $e->getMessage());
//         return 0;
//     }
// }

// function getLabelCounts($userEmail) {
//     try {
//         $pdo = getDatabaseConnection();
//         if (!$pdo) return [];

//         $userId = getUserId($pdo, $userEmail);
//         if (!$userId) return [];

//         $stmt = $pdo->prepare("
//             SELECT l.*, COUNT(se.id) as email_count
//             FROM labels l
//             LEFT JOIN sent_emails se ON se.label_id = l.id AND se.sender_email = :email AND se.current_status = 1
//             WHERE l.user_id = :user_id
//             GROUP BY l.id
//             ORDER BY l.label_name
//         ");

//         $stmt->execute([':user_id' => $userId, ':email' => $userEmail]);
//         return $stmt->fetchAll(PDO::FETCH_ASSOC);

//     } catch (PDOException $e) {
//         error_log("Error getting label counts: " . $e->getMessage());
//         return [];
//     }
// }

// function getUnlabeledEmailCount($userEmail) {
//     try {
//         $pdo = getDatabaseConnection();
//         if (!$pdo) return 0;

//         $stmt = $pdo->prepare("
//             SELECT COUNT(*) as count
//             FROM sent_emails
//             WHERE sender_email = :email AND label_id IS NULL AND current_status = 1
//         ");

//         $stmt->execute([':email' => $userEmail]);
//         $result = $stmt->fetch(PDO::FETCH_ASSOC);

//         return $result['count'] ?? 0;

//     } catch (PDOException $e) {
//         error_log("Error getting unlabeled count: " . $e->getMessage());
//         return 0;
//     }
// }

// function getUserId($pdo, $email) {
//     $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
//     $stmt->execute([$email]);
//     $user = $stmt->fetch(PDO::FETCH_ASSOC);
//     return $user['id'] ?? null;
// }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sent Emails - SXC MDTS</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    
    <style>
        :root {
            --apple-blue: #007AFF;
            --apple-gray: #8E8E93;
            --apple-bg: #F2F2F7;
            --border: #E5E5EA;
            --text-primary: #1c1c1e;
            --text-secondary: #52525b;
            --read-blue: #4A90E2;
            --unread-gray: #C7C7CC;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--apple-bg);
            color: var(--text-primary);
            line-height: 1.6;
        }

        .app-container {
            display: flex;
            min-height: 100vh;
            max-width: 1600px;
            margin: 0 auto;
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* ========== READ RECEIPT STYLES ========== */
        .read-status-col {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            min-width: 80px;
        }

        .read-tick-icon {
            font-size: 20px;
            transition: all 0.3s ease;
        }

        .read-tick-icon.unread {
            color: var(--unread-gray);
        }

        .read-tick-icon.read {
            color: var(--read-blue);
        }

        .read-tick-icon.read-animating {
            animation: tickBlue 0.5s ease;
        }

        @keyframes tickBlue {
            0% { 
                color: var(--unread-gray);
                transform: scale(1);
            }
            50% { 
                transform: scale(1.3);
            }
            100% { 
                color: var(--read-blue);
                transform: scale(1);
            }
        }

        .read-time {
            font-size: 10px;
            color: #FF3B30;
            font-weight: 600;
            white-space: nowrap;
        }

        .no-tracking {
            font-size: 10px;
            color: var(--apple-gray);
            font-style: italic;
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
            grid-template-columns: 40px 2fr 3fr 1fr 80px 150px;
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

        .col-date {
            font-size: 13px;
            color: var(--apple-gray);
            text-align: right;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
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
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
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
                            <div class="email-item" data-email-id="<?= $email['id'] ?>" data-tracking-token="<?= htmlspecialchars($email['tracking_token'] ?? '') ?>">
                                <div class="col-checkbox">
                                    <input type="checkbox" 
                                           class="email-checkbox" 
                                           value="<?= $email['id'] ?>">
                                </div>

                                <div class="col-recipient">
                                    <?= htmlspecialchars($email['recipient_email']) ?>
                                </div>

                                <div class="col-subject">
                                    <?= htmlspecialchars($email['subject']) ?>
                                </div>

                                <div class="col-label">
                                    <!-- Label display here -->
                                </div>

                                <!-- READ STATUS COLUMN -->
                                <div class="read-status-col">
                                    <?php if (!empty($email['tracking_token'])): ?>
                                        <?php if ($email['is_read']): ?>
                                            <span class="material-icons read-tick-icon read" title="Read">done_all</span>
                                            <span class="read-time">
                                                <?= date('M j, g:i A', strtotime($email['first_read_at'])) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="material-icons read-tick-icon unread" title="Sent">done_all</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="no-tracking">No tracking</span>
                                    <?php endif; ?>
                                </div>

                                <div class="col-date">
                                    <?php if (!empty($email['attachment_names'])): ?>
                                    <span class="material-icons" style="font-size: 16px;">attach_file</span>
                                    <?php endif; ?>
                                    <?= date('M j, Y', strtotime($email['sent_at'])) ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // REAL-TIME READ RECEIPT UPDATES
        let pollingInterval = null;
        const POLL_INTERVAL_MS = 5000; // Poll every 5 seconds

        function startReadReceiptPolling() {
            pollingInterval = setInterval(updateReadReceipts, POLL_INTERVAL_MS);
        }

        async function updateReadReceipts() {
            const emailItems = document.querySelectorAll('.email-item[data-tracking-token]');
            const trackingTokens = [];
            
            emailItems.forEach(item => {
                const token = item.getAttribute('data-tracking-token');
                if (token && !item.querySelector('.read-tick-icon.read')) {
                    trackingTokens.push(token);
                }
            });

            if (trackingTokens.length === 0) {
                return; // No unread emails to check
            }

            try {
                const response = await fetch('check_read_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ tokens: trackingTokens })
                });

                const data = await response.json();

                if (data.success && data.read_statuses) {
                    data.read_statuses.forEach(status => {
                        if (status.is_read) {
                            updateEmailReadUI(status.tracking_token, status.first_read_at);
                        }
                    });
                }

            } catch (error) {
                console.error('Error checking read status:', error);
            }
        }

        function updateEmailReadUI(trackingToken, firstReadAt) {
            const emailItem = document.querySelector(`[data-tracking-token="${trackingToken}"]`);
            if (!emailItem) return;

            const tickIcon = emailItem.querySelector('.read-tick-icon');
            const readStatusCol = emailItem.querySelector('.read-status-col');

            if (tickIcon && tickIcon.classList.contains('unread')) {
                // Animate tick to blue
                tickIcon.classList.remove('unread');
                tickIcon.classList.add('read', 'read-animating');

                // Remove animation class after animation completes
                setTimeout(() => {
                    tickIcon.classList.remove('read-animating');
                }, 500);

                // Add read time
                const readTime = document.createElement('span');
                readTime.className = 'read-time';
                readTime.textContent = formatReadTime(firstReadAt);
                readStatusCol.appendChild(readTime);

                // Optional: Play notification sound or show toast
                console.log('Email read:', trackingToken);
            }
        }

        function formatReadTime(timestamp) {
            const date = new Date(timestamp);
            const month = date.toLocaleString('default', { month: 'short' });
            const day = date.getDate();
            const hours = date.getHours();
            const minutes = date.getMinutes();
            const ampm = hours >= 12 ? 'PM' : 'AM';
            const displayHours = hours % 12 || 12;
            const displayMinutes = minutes < 10 ? '0' + minutes : minutes;
            
            return `${month} ${day}, ${displayHours}:${displayMinutes} ${ampm}`;
        }

        // Start polling when page loads
        document.addEventListener('DOMContentLoaded', function() {
            startReadReceiptPolling();
        });

        // Stop polling when page is hidden
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                clearInterval(pollingInterval);
            } else {
                startReadReceiptPolling();
            }
        });

        // Clean up on page unload
        window.addEventListener('beforeunload', function() {
            clearInterval(pollingInterval);
        });
    </script>
</body>
</html>