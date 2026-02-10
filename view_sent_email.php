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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= htmlspecialchars($email['subject']) ?> - SXC MDTS
    </title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
    :root {
        --apple-bg: #F5F5F7;
        --apple-card: rgba(255, 255, 255, 0.8);
        --apple-blue: #0071e3;
        --apple-text: #1d1d1f;
        --apple-text-secondary: #86868b;
        --apple-border: rgba(0, 0, 0, 0.08);
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        -webkit-font-smoothing: antialiased;
    }

    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        background-color: var(--apple-bg);
        color: var(--apple-text);
        line-height: 1.47059;
        letter-spacing: -0.022em;
    }

    .container {
        max-width: 900px;
        margin: 60px auto;
        padding: 0 40px;
    }

    /* Action Buttons - Minimalist */
    .action-buttons {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 40px;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 980px; /* Highly rounded pill buttons */
        font-weight: 400;
        font-size: 14px;
        text-decoration: none;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        border: none;
        cursor: pointer;
    }

    .btn-primary {
        background: var(--apple-blue);
        color: white;
    }

    .btn-primary:hover {
        background: #0077ed;
        opacity: 0.9;
    }

    .btn-secondary {
        background: rgba(0, 0, 0, 0.05);
        color: var(--apple-blue);
    }

    .btn-secondary:hover {
        background: rgba(0, 0, 0, 0.1);
    }

    .btn-danger {
        background: transparent;
        color: #ff3b30;
    }

    .btn-danger:hover {
        background: rgba(255, 59, 48, 0.1);
    }

    /* Email Header */
    .email-header {
        background: var(--apple-card);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-radius: 24px;
        padding: 40px;
        margin-bottom: 24px;
        border: 1px solid var(--apple-border);
    }

    .email-subject {
        font-size: 32px;
        font-weight: 700;
        letter-spacing: -0.015em;
        color: var(--apple-text);
        margin-bottom: 24px;
    }

    .email-meta {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        padding-top: 24px;
        border-top: 1px solid var(--apple-border);
    }

    .meta-item {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 13px;
        color: var(--apple-text-secondary);
    }

    .meta-label {
        font-weight: 500;
        color: var(--apple-text);
        width: 50px;
        display: inline-block;
    }

    /* Label Badge */
    .label-badge {
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    /* Email Body */
    .email-body {
        background: white;
        border-radius: 24px;
        padding: 40px;
        margin-bottom: 24px;
        border: 1px solid var(--apple-border);
        box-shadow: 0 4px 24px rgba(0,0,0,0.02);
    }

    .article-title {
        font-size: 20px;
        font-weight: 600;
        margin-bottom: 24px;
        color: var(--apple-text);
    }

    .email-content {
        font-size: 17px;
        line-height: 1.6;
        color: #333;
    }

    /* Attachments - Glassmorphic Cards */
    .attachments-section {
        background: var(--apple-card);
        backdrop-filter: blur(20px);
        border-radius: 24px;
        padding: 32px;
        border: 1px solid var(--apple-border);
    }

    .attachments-title {
        font-size: 15px;
        font-weight: 600;
        margin-bottom: 20px;
        color: var(--apple-text-secondary);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .attachments-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 16px;
    }

    .attachment-card {
        background: rgba(255, 255, 255, 0.5);
        border: 1px solid var(--apple-border);
        border-radius: 18px;
        padding: 16px;
        transition: all 0.3s ease;
        text-align: center;
    }

    .attachment-card:hover {
        background: white;
        transform: scale(1.02);
        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    }

    .attachment-icon {
        width: 40px;
        height: 40px;
        background: transparent;
        margin: 0 auto 12px;
    }

    .attachment-icon .material-icons {
        font-size: 32px;
        color: var(--apple-blue);
    }

    .attachment-name {
        font-size: 13px;
        font-weight: 500;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-bottom: 4px;
    }

    .attachment-size {
        font-size: 11px;
        color: var(--apple-text-secondary);
    }

    @media (max-width: 768px) {
        .container { padding: 20px; margin-top: 20px; }
        .email-meta { grid-template-columns: 1fr; }
        .email-subject { font-size: 26px; }
    }

    @media print {
        .action-buttons { display: none; }
        body { background: white; }
        .email-header, .email-body, .attachments-section { 
            border: none; 
            box-shadow: none; 
            padding: 20px 0;
        }
    }
</style>
</head>

<body>
    <div class="container">
        <div class="action-buttons">
            <a href="sent_history.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Back to Sent Emails
            </a>
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i>
                Print
            </button>
            <button onclick="deleteEmail()" class="btn btn-danger">
                <i class="fas fa-trash"></i>
                Delete
            </button>
        </div>

        <div class="email-header">
            <h1 class="email-subject">
                <?= htmlspecialchars($email['subject']) ?>
            </h1>

            <div class="email-meta">
                <div class="meta-item">
                    <span class="material-icons">person</span>
                    <span>
                        <span class="meta-label">From:</span>
                        <?= htmlspecialchars($email['sender_name'] ?? $email['sender_email']) ?>
                        &lt;
                        <?= htmlspecialchars($email['sender_email']) ?>&gt;
                    </span>
                </div>

                <div class="meta-item">
                    <span class="material-icons">email</span>
                    <span>
                        <span class="meta-label">To:</span>
                        <?= htmlspecialchars($email['recipient_email']) ?>
                    </span>
                </div>

                <?php if (!empty($email['cc_list'])): ?>
                <div class="meta-item">
                    <span class="material-icons">group</span>
                    <span>
                        <span class="meta-label">CC:</span>
                        <?= htmlspecialchars($email['cc_list']) ?>
                    </span>
                </div>
                <?php endif; ?>

                <div class="meta-item">
                    <span class="material-icons">schedule</span>
                    <span>
                        <?= date('M d, Y h:i A', strtotime($email['sent_at'])) ?>
                    </span>
                </div>

                <?php if (!empty($email['label_name'])): ?>
                <div class="meta-item">
                    <span class="label-badge"
                        style="background: <?= htmlspecialchars($email['label_color'] ?? '#6b7280') ?>">
                        <?= htmlspecialchars($email['label_name']) ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="email-body">
            <?php if (!empty($email['article_title'])): ?>
            <div class="article-title">
                <?= htmlspecialchars($email['article_title']) ?>
            </div>
            <?php endif; ?>

            <div class="email-content">
                <?php 
                if (!empty($email['body_html'])) {
                    echo $email['body_html'];
                } else {
                    echo nl2br(htmlspecialchars($email['body_text'] ?? 'No content'));
                }
                ?>
            </div>
        </div>

        <?php if (!empty($attachments)): ?>
        <div class="attachments-section">
            <div class="attachments-title">
                <span class="material-icons">attach_file</span>
                Attachments (
                <?= count($attachments) ?>)
            </div>

            <div class="attachments-grid">
                <?php foreach ($attachments as $attachment): ?>
                <div class="attachment-card"
                    onclick="downloadAttachment('<?= htmlspecialchars($attachment['file_path']) ?>', '<?= htmlspecialchars($attachment['original_filename']) ?>')">
                    <div class="attachment-icon">
                        <span class="material-icons">insert_drive_file</span>
                    </div>
                    <div class="attachment-name">
                        <?= htmlspecialchars($attachment['original_filename']) ?>
                    </div>
                    <div class="attachment-size">
                        <?= formatFileSize($attachment['file_size']) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function downloadAttachment(filePath, filename) {
            const link = document.createElement('a');
            link.href = filePath;
            link.download = filename;
            link.click();
        }

        function deleteEmail() {
            if (!confirm('Are you sure you want to delete this email?')) {
                return;
            }

            fetch('sent_history.php?action=delete&id=<?= $emailId ?>')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Email deleted successfully');
                        window.location.href = 'sent_history.php';
                    } else {
                        alert('Failed to delete email');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to delete email');
                });
        }
    </script>
</body>

</html>