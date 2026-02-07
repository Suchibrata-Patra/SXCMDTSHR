<?php
// sent_history.php - Minimalist Nature-Inspired UI
session_start();
require 'config.php';
require 'db_config.php';

if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    header("Location: login.php");
    exit();
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50; 
$offset = ($page - 1) * $perPage;

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
    <title>Archive | SXC MDTS</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Libre+Baskerville:wght@400;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --nature-red: #e4002b;
            --text-main: #222222;
            --text-muted: #555555;
            --border-color: #eeeeee;
            --hover-bg: #f9f9f9;
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

        /* Minimalist List */
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
        }

        .email-item:hover {
            background-color: var(--hover-bg);
        }

        /* Column Controls */
        .col-recipient {
            flex: 0 0 220px;
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
            min-width: 0; /* Important for ellipsis */
            gap: 10px;
        }

        .subject-text {
            font-family: 'Libre Baskerville', serif;
            font-size: 14.5px;
            font-weight: 700;
            white-space: nowrap;
            flex-shrink: 0; /* Keep subject visible */
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

        /* Buttons */
        .btn-minimal {
            border: 1px solid var(--text-main);
            padding: 6px 14px;
            text-decoration: none;
            color: var(--text-main);
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            transition: all 0.2s;
        }

        .btn-minimal:hover {
            background: var(--text-main);
            color: #fff;
        }

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

        @media (max-width: 900px) {
            .col-recipient { flex: 0 0 150px; }
            .snippet-text { display: none; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div id="main-wrapper">
        <header class="page-header">
            <div class="header-left">
                <p class="count-label">Sent Archive / <?= $totalEmails ?> total</p>
                <h1>Correspondence</h1>
            </div>
            <a href="index.php" class="btn-minimal">New Draft</a>
        </header>

        <div class="email-list">
            <?php if (empty($sentEmails)): ?>
                <p style="padding: 20px; color: var(--text-muted);">Archive is empty.</p>
            <?php else: ?>
                <?php foreach ($sentEmails as $email): ?>
                <a href="view_sent_email.php?id=<?= $email['id'] ?>" class="email-item" target="_blank">
                    <div class="col-recipient">
                        <?= htmlspecialchars($email['recipient_email']) ?>
                    </div>
                    
                    <div class="col-content">
                        <span class="subject-text"><?= htmlspecialchars($email['subject']) ?></span>
                        <span class="snippet-text">
                            — <?= htmlspecialchars(strip_tags($email['message_body'])) ?>
                        </span>
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
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>" class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>