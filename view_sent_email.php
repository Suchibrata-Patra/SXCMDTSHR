<?php
session_start();
require 'config.php';
require 'db_config.php';

// Security check
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    header("Location: login.php");
    exit();
}

$userEmail = $_SESSION['smtp_user'];
$emailId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$emailId) {
    header("Location: sent_history.php");
    exit();
}

/**  Get sent email by ID */
function getSentEmailById($emailId, $userEmail) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return null;
        
        $stmt = $pdo->prepare("
            SELECT * FROM sent_emails_new 
            WHERE id = ? AND sender_email = ? AND is_deleted = 0
        ");
        $stmt->execute([$emailId, $userEmail]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching email: " . $e->getMessage());
        return null;
    }
}

/* Get attachments for a sent email */
function getSentEmailAttachments($emailId) {
    try {
        $pdo = getDatabaseConnection();
        if (!$pdo) return [];
        
        $stmt = $pdo->prepare("
            SELECT * FROM sent_email_attachments_new 
            WHERE sent_email_id = ? 
            ORDER BY uploaded_at ASC
        ");
        $stmt->execute([$emailId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching attachments: " . $e->getMessage());
        return [];
    }
}

/* Format file size */
function formatFileSize($bytes) {
    if ($bytes <= 0) return "0 B";
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $power = floor(log($bytes, 1024));
    $power = min($power, count($units) - 1);
    $size = $bytes / pow(1024, $power);
    return round($size, 2) . ' ' . $units[$power];
}

// Fetch the email
$email = getSentEmailById($emailId, $userEmail);

if (!$email) {
    header("Location: sent_history.php");
    exit();
}

// Get attachments if any
$attachments = [];
if ($email['has_attachments']) {
    $attachments = getSentEmailAttachments($emailId);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php
        define('PAGE_TITLE', 'SXC MDTS | Dashboard');
        include 'header.php';
    ?> 
    <style>
        :root {
            --sf-blue: #007AFF;
            --sf-gray: #8E8E93;
            --sf-bg: #F2F2F7;
            --sf-card: rgba(255, 255, 255, 0.72);
            --sf-border: rgba(0, 0, 0, 0.08);
            --text-main: #1D1D1F;
            --text-secondary: #6E6E73;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-font-smoothing: antialiased;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "SF Pro Text", "Helvetica Neue", sans-serif;
            background-color: var(--sf-bg);
            color: var(--text-main);
            overflow-x: hidden;
        }

        /* Top Navigation Bar (Frosted Glass) */
        .top-nav {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(242, 242, 247, 0.8);
            backdrop-filter: saturate(180%) blur(20px);
            border-bottom: 0.5px solid var(--sf-border);
            padding: 12px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-left { display: flex; align-items: center; gap: 12px; }

        .btn-icon {
            background: none;
            border: none;
            color: var(--sf-blue);
            font-size: 17px;
            display: flex;
            align-items: center;
            gap: 4px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 400;
            transition: opacity 0.2s;
        }

        .btn-icon:hover { opacity: 0.7; }

        /* Main Content Wrapper */
        .viewport {
            max-width: 860px;
            margin: 32px auto;
            padding: 0 20px;
            animation: slideUp 0.6s cubic-bezier(0.23, 1, 0.32, 1);
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Email Card Layout */
        .mail-sheet {
            background: #FFFFFF;
            border-radius: 18px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.04);
            border: 0.5px solid var(--sf-border);
            overflow: hidden;
        }

        .header-section {
            padding: 32px 40px;
            border-bottom: 0.5px solid var(--sf-border);
            background: linear-gradient(to bottom, #ffffff, #fafafa);
        }

        .subject-line {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.5px;
            margin-bottom: 20px;
            line-height: 1.2;
        }

        .meta-container {
            display: grid;
            gap: 8px;
        }

        .meta-row {
            display: flex;
            font-size: 14px;
            align-items: baseline;
        }

        .meta-key {
            color: var(--text-secondary);
            width: 60px;
            flex-shrink: 0;
        }

        .meta-value {
            color: var(--text-main);
            font-weight: 400;
        }

        .meta-value .email-addr {
            color: var(--sf-gray);
            font-size: 13px;
        }

        .date-pill {
            margin-top: 12px;
            font-size: 12px;
            color: var(--sf-gray);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Body Content */
        .content-section {
            padding: 40px;
            font-size: 17px;
            line-height: 1.6;
            color: #3A3A3C;
        }

        .content-section img {
            max-width: 100%;
            border-radius: 12px;
        }

        /* Attachments Section - Apple Style Cells */
        .attachment-area {
            background: #F9F9F9;
            padding: 24px 40px;
            border-top: 0.5px solid var(--sf-border);
        }

        .attachment-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 16px;
            text-transform: uppercase;
        }

        .attachment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 12px;
        }

        .attachment-cell {
            background: white;
            border: 0.5px solid var(--sf-border);
            border-radius: 12px;
            padding: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
        }

        .attachment-cell:hover {
            background: #F2F2F7;
        }

        .attachment-cell:active {
            transform: scale(0.98);
        }

        .file-icon-box {
            width: 40px;
            height: 40px;
            background: var(--sf-blue);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .file-info { overflow: hidden; }
        .file-name {
            font-size: 14px;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .file-size {
            font-size: 12px;
            color: var(--sf-gray);
        }

        /* Danger Action */
        .btn-delete {
            background: #FF3B30;
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            cursor: pointer;
        }

        @media (max-width: 600px) {
            .header-section, .content-section, .attachment-area { padding: 24px; }
            .subject-line { font-size: 22px; }
        }
    </style>
</head>

<body>
    <nav class="top-nav">
        <div class="nav-left">
            <a href="sent_history.php" class="btn-icon">
                <span class="material-icons-round">arrow_back_ios</span>
                Sent
            </a>
        </div>
        <div class="nav-right" style="display: flex; gap: 20px;">
            <button onclick="window.print()" class="btn-icon">
                <span class="material-icons-round">print</span>
            </button>
            <button onclick="deleteEmail()" class="btn-icon" style="color: #FF3B30;">
                <span class="material-icons-round">delete_outline</span>
            </button>
        </div>
    </nav>

    <main class="viewport">
        <article class="mail-sheet">
            <header class="header-section">
                <h1 class="subject-line"><?= htmlspecialchars($email['subject']) ?></h1>
                
                <div class="meta-container">
                    <div class="meta-row">
                        <span class="meta-key">From</span>
                        <span class="meta-value">
                            <strong><?= htmlspecialchars($email['sender_name'] ?? 'Unknown') ?></strong> 
                            <span class="email-addr">&lt;<?= htmlspecialchars($email['sender_email']) ?>&gt;</span>
                        </span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-key">To</span>
                        <span class="meta-value"><?= htmlspecialchars($email['recipient_email']) ?></span>
                    </div>
                    <?php if (!empty($email['cc_list'])): ?>
                    <div class="meta-row">
                        <span class="meta-key">Cc</span>
                        <span class="meta-value"><?= htmlspecialchars($email['cc_list']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="date-pill">
                    <?= date('l, M j, Y • g:i A', strtotime($email['sent_at'])) ?>
                </div>
            </header>

            <section class="content-section">
                <?php if (!empty($email['article_title'])): ?>
                    <h2 style="margin-bottom: 20px; font-weight: 600;"><?= htmlspecialchars($email['article_title']) ?></h2>
                <?php endif; ?>

                <div class="email-text">
                    <?php 
                        if (!empty($email['body_html'])) {
                            echo $email['body_html']; 
                        } else {
                            echo nl2br(htmlspecialchars($email['body_text'] ?? 'No content'));
                        }
                    ?>
                </div>
            </section>

            <?php if (!empty($attachments)): ?>
            <footer class="attachment-area">
                <div class="attachment-label">Attachments (<?= count($attachments) ?>)</div>
                <div class="attachment-grid">
                    <?php foreach ($attachments as $attachment): ?>
                    <div class="attachment-cell" onclick="downloadAttachment('<?= htmlspecialchars($attachment['file_path']) ?>', '<?= htmlspecialchars($attachment['original_filename']) ?>')">
                        <div class="file-icon-box">
                            <span class="material-icons-round">description</span>
                        </div>
                        <div class="file-info">
                            <div class="file-name"><?= htmlspecialchars($attachment['original_filename']) ?></div>
                            <div class="file-size"><?= formatFileSize($attachment['file_size']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </footer>
            <?php endif; ?>
        </article>
    </main>

    <script>
        function downloadAttachment(filePath, filename) {
            const link = document.createElement('a');
            link.href = filePath;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function deleteEmail() {
            // Pro-level confirmation (Native style)
            if (confirm('Delete Message?\nThis action cannot be undone.')) {
                fetch('sent_history.php?action=delete&id=<?= $emailId ?>')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.href = 'sent_history.php';
                        } else {
                            alert('Something went wrong.');
                        }
                    });
            }
        }
    </script>
</body>
</html>