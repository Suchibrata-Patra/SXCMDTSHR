<?php
// sent_history.php - Gmail-style list with option to open in same or new tab
session_start();
require 'config.php';
require 'db_config.php';

// Security check
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    header("Location: login.php");
    exit();
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Get sent emails
$userEmail = $_SESSION['smtp_user'];
$sentEmails = getSentEmails($userEmail, $perPage, $offset);
$totalEmails = getSentEmailCount($userEmail);
$totalPages = ceil($totalEmails / $perPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sent - SXC MDTS</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        /* Scoped styles to avoid sidebar conflicts */
        #sent-history-content * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        #sent-history-content { 
            font-family: 'Roboto', -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background-color: #f6f8fc;
            color: #202124;
            display: flex;
            flex-direction: column;
            height: 100vh;
            line-height: 1.5;
            font-size: 14px;
        }

        #sent-history-content .top-bar {
            background: #ffffff;
            border-bottom: 1px solid #e0e0e0;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 64px;
        }

        #sent-history-content .top-bar-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        #sent-history-content .page-title {
            font-family: 'Google Sans', sans-serif;
            font-size: 22px;
            font-weight: 400;
            color: #202124;
        }

        #sent-history-content .email-count {
            font-size: 13px;
            color: #5f6368;
        }

        #sent-history-content .top-bar-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        #sent-history-content .content-area {
            flex: 1;
            overflow-y: auto;
            background: #f6f8fc;
        }

        #sent-history-content .emails-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0;
        }

        #sent-history-content .email-list {
            background: white;
            margin: 0;
            box-shadow: inset 0 -1px 0 0 #e0e0e0;
        }

        #sent-history-content .email-row {
            display: flex;
            align-items: center;
            padding: 12px 24px;
            border-bottom: 1px solid #e0e0e0;
            cursor: pointer;
            transition: all 0.15s ease;
            text-decoration: none;
            color: inherit;
            min-height: 56px;
        }

        #sent-history-content .email-row:hover {
            box-shadow: inset 1px 0 0 #dadce0, inset -1px 0 0 #dadce0, 
                        0 1px 2px 0 rgba(60,64,67,0.3), 0 1px 3px 1px rgba(60,64,67,0.15);
            z-index: 1;
        }

        #sent-history-content .email-row:active {
            background: #f8f9fa;
        }

        #sent-history-content .email-left {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 0 0 200px;
            min-width: 0;
        }

        #sent-history-content .email-recipient {
            font-weight: 500;
            color: #202124;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 14px;
        }

        #sent-history-content .email-middle {
            flex: 1;
            min-width: 0;
            padding: 0 16px;
        }

        #sent-history-content .email-subject {
            font-weight: 500;
            color: #202124;
            margin-bottom: 2px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        #sent-history-content .email-preview {
            font-size: 13px;
            color: #5f6368;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        #sent-history-content .email-right {
            display: flex;
            align-items: center;
            gap: 16px;
            flex: 0 0 auto;
        }

        #sent-history-content .email-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 12px;
            color: #5f6368;
        }

        #sent-history-content .email-date {
            min-width: 100px;
            text-align: right;
            font-size: 12px;
            color: #5f6368;
            white-space: nowrap;
        }

        #sent-history-content .email-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            background: #e8f0fe;
            color: #1967d2;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }

        #sent-history-content .empty-state {
            text-align: center;
            padding: 120px 20px;
            background: white;
            margin: 24px;
            border-radius: 8px;
        }

        #sent-history-content .empty-state i {
            font-size: 72px;
            color: #dadce0;
            margin-bottom: 24px;
        }

        #sent-history-content .empty-state h2 {
            font-family: 'Google Sans', sans-serif;
            font-size: 22px;
            font-weight: 400;
            color: #202124;
            margin-bottom: 12px;
        }

        #sent-history-content .empty-state p {
            font-size: 14px;
            color: #5f6368;
            margin-bottom: 24px;
        }

        #sent-history-content .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            padding: 24px;
            background: white;
            margin-top: 1px;
        }

        #sent-history-content .page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 12px;
            background: white;
            border: 1px solid #dadce0;
            border-radius: 4px;
            color: #202124;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.15s;
        }

        #sent-history-content .page-link:hover {
            background: #f8f9fa;
            border-color: #bdc1c6;
        }

        #sent-history-content .page-link.active {
            background: #1a73e8;
            border-color: #1a73e8;
            color: white;
        }

        #sent-history-content .page-link.disabled {
            opacity: 0.4;
            cursor: not-allowed;
            pointer-events: none;
        }

        #sent-history-content .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.15s;
            border: 1px solid;
            cursor: pointer;
            font-family: 'Google Sans', sans-serif;
        }

        #sent-history-content .btn-primary {
            background: #1a73e8;
            color: white;
            border-color: #1a73e8;
        }

        #sent-history-content .btn-primary:hover {
            background: #1765cc;
            border-color: #1765cc;
            box-shadow: 0 1px 2px 0 rgba(60,64,67,0.3), 0 1px 3px 1px rgba(60,64,67,0.15);
        }

        #sent-history-content .content-area::-webkit-scrollbar {
            width: 12px;
        }

        #sent-history-content .content-area::-webkit-scrollbar-track {
            background: #f6f8fc;
        }

        #sent-history-content .content-area::-webkit-scrollbar-thumb {
            background: #dadce0;
            border-radius: 6px;
            border: 3px solid #f6f8fc;
        }

        #sent-history-content .content-area::-webkit-scrollbar-thumb:hover {
            background: #bdc1c6;
        }

        @media (max-width: 768px) {
            #sent-history-content .email-left {
                flex: 0 0 120px;
            }

            #sent-history-content .email-meta {
                display: none;
            }

            #sent-history-content .top-bar {
                padding: 12px 16px;
            }

            #sent-history-content .email-row {
                padding: 12px 16px;
            }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; display: flex; height: 100vh; overflow: hidden;">
    <?php include 'sidebar.php'; ?>

    <div id="sent-history-content">
        <div class="top-bar">
            <div class="top-bar-left">
                <h1 class="page-title">Sent</h1>
                <span class="email-count"><?= number_format($totalEmails) ?> email<?= $totalEmails != 1 ? 's' : '' ?></span>
            </div>
            <div class="top-bar-right">
                <a href="index.php" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i>
                    Compose
                </a>
            </div>
        </div>

        <div class="content-area">
            <div class="emails-container">
                <?php if (empty($sentEmails)): ?>
                    <div class="empty-state">
                        <i class="fa-regular fa-envelope"></i>
                        <h2>No sent emails</h2>
                        <p>Emails you send will appear here</p>
                        <a href="index.php" class="btn btn-primary">
                            <i class="fa-solid fa-plus"></i>
                            Send your first email
                        </a>
                    </div>
                <?php else: ?>
                    <div class="email-list">
                        <?php foreach ($sentEmails as $email): ?>
                        <a href="view_sent_email.php?id=<?= $email['id'] ?>" class="email-row" target="_blank">
                            <div class="email-left">
                                <span class="email-recipient">
                                    <?= htmlspecialchars($email['recipient_email']) ?>
                                </span>
                            </div>
                            
                            <div class="email-middle">
                                <div class="email-subject">
                                    <?= htmlspecialchars($email['subject']) ?>
                                    <?php if (!empty($email['attachment_names'])): ?>
                                    <i class="fa-solid fa-paperclip" style="color: #5f6368; font-size: 12px;"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="email-preview">
                                    <?php if (!empty($email['article_title'])): ?>
                                        <?= htmlspecialchars($email['article_title']) ?> —
                                    <?php endif; ?>
                                    <?= htmlspecialchars(strip_tags(substr($email['message_body'], 0, 100))) ?>...
                                </div>
                            </div>
                            
                            <div class="email-right">
                                <div class="email-meta">
                                    <?php if (!empty($email['cc_list'])): ?>
                                    <span class="email-badge">
                                        <i class="fa-solid fa-copy"></i>
                                        CC
                                    </span>
                                    <?php endif; ?>
                                    <?php if (!empty($email['bcc_list'])): ?>
                                    <span class="email-badge">
                                        <i class="fa-solid fa-user-secret"></i>
                                        BCC
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <span class="email-date">
                                    <?php
                                    $sentTime = strtotime($email['sent_at']);
                                    $now = time();
                                    $diff = $now - $sentTime;
                                    
                                    if ($diff < 86400) {
                                        echo date('g:i A', $sentTime);
                                    } elseif ($diff < 604800) {
                                        echo date('D', $sentTime);
                                    } elseif (date('Y', $sentTime) == date('Y')) {
                                        echo date('M j', $sentTime);
                                    } else {
                                        echo date('M j, Y', $sentTime);
                                    }
                                    ?>
                                </span>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>" class="page-link">
                                <i class="fa-solid fa-chevron-left"></i>
                            </a>
                        <?php else: ?>
                            <span class="page-link disabled">
                                <i class="fa-solid fa-chevron-left"></i>
                            </span>
                        <?php endif; ?>

                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        
                        if ($startPage > 1) {
                            echo '<a href="?page=1" class="page-link">1</a>';
                            if ($startPage > 2) {
                                echo '<span class="page-link disabled">...</span>';
                            }
                        }
                        
                        for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <a href="?page=<?= $i ?>" class="page-link <?= $i == $page ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor;
                        
                        if ($endPage < $totalPages) {
                            if ($endPage < $totalPages - 1) {
                                echo '<span class="page-link disabled">...</span>';
                            }
                            echo '<a href="?page=' . $totalPages . '" class="page-link">' . $totalPages . '</a>';
                        }
                        ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>" class="page-link">
                                <i class="fa-solid fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="page-link disabled">
                                <i class="fa-solid fa-chevron-right"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>