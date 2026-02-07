<?php
// sent_history.php - Nature Journal Inspired UI
session_start();
require 'config.php';
require 'db_config.php';

if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    header("Location: login.php");
    exit();
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 25; // Academic lists are usually cleaner with fewer items
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
    <title>Sent Archive | SXC MDTS</title>
    
    <link rel="stylesheet" href="https://use.typekit.net/ovm6vst.css"> <link href="https://fonts.googleapis.com/css2?family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --nature-red: #e4002b;
            --nature-black: #222222;
            --nature-grey: #666666;
            --nature-light-grey: #f3f3f3;
            --nature-border: #e6e6e6;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #fff;
            color: var(--nature-black);
            display: flex;
            height: 100vh;
        }

        #main-wrapper {
            flex: 1;
            overflow-y: auto;
            padding: 40px 5%;
        }

        /* Header Section */
        .journal-header {
            border-bottom: 3px solid var(--nature-black);
            padding-bottom: 20px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .journal-title-section h1 {
            font-family: 'Libre Baskerville', serif;
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .journal-subtitle {
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 12px;
            font-weight: 700;
            color: var(--nature-red);
        }

        /* List Styling */
        .article-list {
            list-style: none;
            max-width: 1000px; /* Aligned to Nature's readable line length */
        }

        .article-item {
            padding: 25px 0;
            border-bottom: 1px solid var(--nature-border);
            transition: background 0.2s;
        }

        .article-item:hover .article-link-title {
            color: var(--nature-red);
            text-decoration: underline;
        }

        .article-meta {
            font-size: 13px;
            color: var(--nature-grey);
            margin-bottom: 10px;
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .article-type {
            font-weight: 700;
            color: var(--nature-black);
            text-transform: capitalize;
        }

        .article-link-title {
            font-family: 'Libre Baskerville', serif;
            font-size: 20px;
            font-weight: 700;
            color: var(--nature-black);
            text-decoration: none;
            display: block;
            margin-bottom: 10px;
            line-height: 1.3;
        }

        .article-description {
            font-size: 15px;
            line-height: 1.6;
            color: #444;
            margin-bottom: 15px;
        }

        .article-footer {
            display: flex;
            gap: 20px;
            font-size: 13px;
        }

        .author-name {
            font-weight: 700;
        }

        /* Pagination */
        .pagination-nature {
            margin-top: 50px;
            display: flex;
            gap: 10px;
        }

        .page-btn {
            padding: 8px 16px;
            border: 1px solid var(--nature-border);
            text-decoration: none;
            color: var(--nature-black);
            font-weight: 700;
            font-size: 14px;
        }

        .page-btn.active {
            background: var(--nature-black);
            color: white;
            border-color: var(--nature-black);
        }

        .compose-btn {
            background-color: var(--nature-red);
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 1px;
        }

        @media (max-width: 768px) {
            .journal-header { flex-direction: column; align-items: flex-start; gap: 20px; }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div id="main-wrapper">
        <header class="journal-header">
            <div class="journal-title-section">
                <p class="journal-subtitle">Archive & Sent Communication</p>
                <h1>Sent Emails</h1>
                <p style="color: var(--nature-grey); font-size: 14px;">
                    Showing <?= count($sentEmails) ?> of <?= $totalEmails ?> records
                </p>
            </div>
            <a href="index.php" class="compose-btn">New Correspondence</a>
        </header>

        <main class="article-list">
            <?php if (empty($sentEmails)): ?>
                <div style="padding: 50px 0; text-align: center; border: 1px dashed #ccc;">
                    <p>No records found in the current archive.</p>
                </div>
            <?php else: ?>
                <?php foreach ($sentEmails as $email): ?>
                <article class="article-item">
                    <div class="article-meta">
                        <span class="article-type">Sent Log</span>
                        <span class="article-date">
                            <i class="fa-regular fa-calendar"></i> 
                            <?= date('d F Y', strtotime($email['sent_at'])) ?>
                        </span>
                        <?php if (!empty($email['attachment_names'])): ?>
                            <span><i class="fa-solid fa-paperclip"></i> Supplement</span>
                        <?php endif; ?>
                    </div>

                    <a href="view_sent_email.php?id=<?= $email['id'] ?>" class="article-link-title" target="_blank">
                        <?= htmlspecialchars($email['subject']) ?>
                    </a>

                    <div class="article-description">
                        <?php 
                            $snippet = strip_tags($email['message_body']);
                            echo htmlspecialchars(substr($snippet, 0, 180)) . '...';
                        ?>
                    </div>

                    <div class="article-footer">
                        <span>Recipient: <span class="author-name"><?= htmlspecialchars($email['recipient_email']) ?></span></span>
                        <?php if (!empty($email['article_title'])): ?>
                            <span style="font-style: italic; color: var(--nature-grey);">
                                Ref: <?= htmlspecialchars($email['article_title']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </article>
                <?php endforeach; ?>

                <?php if ($totalPages > 1): ?>
                <nav class="pagination-nature">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?= $i ?>" class="page-btn <?= $i == $page ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </nav>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>