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
    <title><?= htmlspecialchars($email['subject']) ?> - SXC MDTS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f8f9fa;
            color: #1c1c1e;
            line-height: 1.6;
        }

        .container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .email-header {
            background: white;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            border: 1px solid #e5e7eb;
        }

        .email-subject {
            font-size: 28px;
            font-weight: 700;
            color: #1c1c1e;
            margin-bottom: 20px;
        }

        .email-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            padding: 15px 0;
            border-top: 1px solid #e5e7eb;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #6b7280;
        }

        .meta-item .material-icons {
            font-size: 18px;
            color: #9ca3af;
        }

        .meta-label {
            font-weight: 600;
            color: #374151;
        }

        .label-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            color: white;
        }

        .email-body {
            background: white;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            border: 1px solid #e5e7eb;
        }

        .article-title {
            font-size: 24px;
            font-weight: 700;
            color: #1c1c1e;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e5e7eb;
        }

        .email-content {
            font-size: 15px;
            line-height: 1.8;
            color: #374151;
        }

        .attachments-section {
            background: white;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            border: 1px solid #e5e7eb;
        }

        .attachments-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 700;
            color: #1c1c1e;
            margin-bottom: 20px;
        }

        .attachments-title .material-icons {
            color: #007AFF;
        }

        .attachments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }

        .attachment-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
            background: #f9fafb;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            cursor: pointer;
            transition: all 0.3s;
        }

        .attachment-card:hover {
            background: #f3f4f6;
            border-color: #007AFF;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 122, 255, 0.1);
        }

        .attachment-icon {
            width: 48px;
            height: 48px;
            background: #007AFF;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
        }

        .attachment-icon .material-icons {
            font-size: 28px;
            color: white;
        }

        .attachment-name {
            font-size: 13px;
            font-weight: 600;
            color: #1c1c1e;
            text-align: center;
            word-break: break-word;
            margin-bottom: 4px;
        }

        .attachment-size {
            font-size: 12px;
            color: #6b7280;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: #007AFF;
            color: white;
        }

        .btn-primary:hover {
            background: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 122, 255, 0.3);
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }

        @media print {
            .action-buttons {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .container {
                margin: 20px auto;
            }

            .email-subject {
                font-size: 22px;
            }

            .email-meta {
                flex-direction: column;
                gap: 10px;
            }

            .attachments-grid {
                grid-template-columns: 1fr;
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
            <h1 class="email-subject"><?= htmlspecialchars($email['subject']) ?></h1>
            
            <div class="email-meta">
                <div class="meta-item">
                    <span class="material-icons">person</span>
                    <span>
                        <span class="meta-label">From:</span> 
                        <?= htmlspecialchars($email['sender_name'] ?? $email['sender_email']) ?>
                        &lt;<?= htmlspecialchars($email['sender_email']) ?>&gt;
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
                    <span class="label-badge" style="background: <?= htmlspecialchars($email['label_color'] ?? '#6b7280') ?>">
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
                Attachments (<?= count($attachments) ?>)
            </div>
            
            <div class="attachments-grid">
                <?php foreach ($attachments as $attachment): ?>
                <div class="attachment-card" onclick="downloadAttachment('<?= htmlspecialchars($attachment['file_path']) ?>', '<?= htmlspecialchars($attachment['original_filename']) ?>')">
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