<?php
// view_sent_email.php - View single sent email (Simplified 2-Table Version)
session_start();
require 'config.php';
require 'db_config.php';

// Security check
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    header("Location: login.php");
    exit();
}

$userEmail = $_SESSION['smtp_user'];

// Get email ID
$emailId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$emailId) {
    header("Location: sent_history.php");
    exit();
}

// Fetch email from database - SIMPLIFIED
try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        die("Database connection failed");
    }
    
    // Single table query - much simpler!
    $email = getSentEmailById($emailId, $userEmail);
    
    if (!$email) {
        header("Location: sent_history.php");
        exit();
    }
    
    // Get attachments if any
    $attachments = [];
    if ($email['has_attachments']) {
        $attachments = getSentEmailAttachments($pdo, $emailId);
    }
    
} catch (PDOException $e) {
    error_log("Error fetching email: " . $e->getMessage());
    die("Error loading email");
}

// Get all unique labels for the dropdown (from sent_emails table)
$allLabels = getUserLabelsFromSentEmails($userEmail);

// Handle label update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_label'])) {
    $newLabelId = !empty($_POST['label_id']) ? intval($_POST['label_id']) : null;
    $newLabelName = !empty($_POST['label_name']) ? trim($_POST['label_name']) : null;
    $newLabelColor = !empty($_POST['label_color']) ? trim($_POST['label_color']) : null;
    
    if (updateSentEmailLabel($pdo, $emailId, $newLabelId, $newLabelName, $newLabelColor)) {
        $_SESSION['success_message'] = 'Label updated successfully';
        header("Location: view_sent_email.php?id=$emailId");
        exit();
    } else {
        $_SESSION['error_message'] = 'Failed to update label';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($email['subject']) ?> - SXC MDTS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: #f6f8fc;
            min-height: 100vh;
        }

        .top-nav {
            background: white;
            border-bottom: 1px solid #e0e0e0;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: transparent;
            border: 1px solid #dadce0;
            border-radius: 8px;
            color: #5f6368;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.15s;
        }

        .back-button:hover {
            background: #f8f9fa;
            border-color: #bdc1c6;
        }

        .email-subject-nav {
            font-size: 18px;
            font-weight: 600;
            color: #202124;
        }

        .email-container {
            max-width: 900px;
            margin: 24px auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            border: 1px solid #e5e7eb;
            overflow: hidden;
        }

        .email-header {
            padding: 24px;
            border-bottom: 1px solid #e0e0e0;
        }

        .email-subject {
            font-size: 24px;
            font-weight: 700;
            color: #202124;
            margin-bottom: 16px;
        }

        .label-section {
            margin-bottom: 16px;
        }

        .label-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 16px;
            font-size: 13px;
            font-weight: 600;
            color: white;
        }

        .label-editor {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }

        .label-select {
            padding: 8px 12px;
            border: 1px solid #dadce0;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
        }

        .btn-save-label {
            padding: 8px 16px;
            background: #007AFF;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-save-label:hover {
            background: #0056b3;
        }

        .email-meta {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .meta-label {
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .meta-value {
            font-size: 14px;
            color: #1c1c1e;
            font-weight: 500;
        }

        .email-body {
            padding: 24px;
        }

        .article-title {
            font-size: 20px;
            font-weight: 700;
            color: #1c1c1e;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f3f4f6;
        }

        .email-content {
            font-size: 15px;
            line-height: 1.7;
            color: #374151;
        }

        .attachments-section {
            padding: 24px;
            border-top: 1px solid #e0e0e0;
            background: #f9fafb;
        }

        .attachments-title {
            font-size: 16px;
            font-weight: 700;
            color: #1c1c1e;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .attachment-list {
            display: grid;
            gap: 12px;
        }

        .attachment-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .attachment-item:hover {
            border-color: #007AFF;
            box-shadow: 0 2px 8px rgba(0, 122, 255, 0.1);
        }

        .attachment-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f3f4f6;
            border-radius: 8px;
            color: #6b7280;
        }

        .attachment-info {
            flex: 1;
        }

        .attachment-name {
            font-size: 14px;
            font-weight: 600;
            color: #1c1c1e;
        }

        .attachment-size {
            font-size: 12px;
            color: #6b7280;
        }

        .btn-download {
            padding: 6px 12px;
            background: #007AFF;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-download:hover {
            background: #0056b3;
        }

        .success-message {
            background: #d1fae5;
            color: #065f46;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .error-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        @media (max-width: 768px) {
            .email-meta {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="top-nav">
        <div class="nav-left">
            <a href="sent_history.php" class="back-button">
                <i class="fas fa-arrow-left"></i>
                Back to Sent Emails
            </a>
            <span class="email-subject-nav"><?= htmlspecialchars($email['subject']) ?></span>
        </div>
    </div>

    <div class="email-container">
        <div class="email-header">
            <?php if (isset($_SESSION['success_message'])): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($_SESSION['success_message']) ?>
            </div>
            <?php unset($_SESSION['success_message']); endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($_SESSION['error_message']) ?>
            </div>
            <?php unset($_SESSION['error_message']); endif; ?>

            <h1 class="email-subject"><?= htmlspecialchars($email['subject']) ?></h1>

            <!-- Label Section -->
            <div class="label-section">
                <?php if (!empty($email['label_name'])): ?>
                <span class="label-badge" style="background-color: <?= htmlspecialchars($email['label_color'] ?? '#6b7280') ?>">
                    <i class="fas fa-tag"></i>
                    <?= htmlspecialchars($email['label_name']) ?>
                </span>
                <?php endif; ?>

                <!-- Label Editor -->
                <form method="POST" class="label-editor">
                    <select name="label_id" class="label-select" onchange="updateLabelData(this)">
                        <option value="">-- No Label --</option>
                        <?php foreach ($allLabels as $label): ?>
                        <option value="<?= $label['id'] ?>" 
                                data-name="<?= htmlspecialchars($label['label_name']) ?>"
                                data-color="<?= htmlspecialchars($label['label_color']) ?>"
                                <?= $email['label_id'] == $label['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label['label_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="label_name" id="label_name">
                    <input type="hidden" name="label_color" id="label_color">
                    <button type="submit" name="update_label" class="btn-save-label">
                        <i class="fas fa-save"></i> Update Label
                    </button>
                </form>
            </div>

            <!-- Email Metadata -->
            <div class="email-meta">
                <div class="meta-item">
                    <span class="meta-label">From</span>
                    <span class="meta-value">
                        <?= htmlspecialchars($email['sender_name'] ?? $email['sender_email']) ?>
                        <br><small style="color: #6b7280;"><?= htmlspecialchars($email['sender_email']) ?></small>
                    </span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">To</span>
                    <span class="meta-value"><?= htmlspecialchars($email['recipient_email']) ?></span>
                </div>
                <?php if (!empty($email['cc_list'])): ?>
                <div class="meta-item">
                    <span class="meta-label">CC</span>
                    <span class="meta-value"><?= htmlspecialchars($email['cc_list']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($email['bcc_list'])): ?>
                <div class="meta-item">
                    <span class="meta-label">BCC</span>
                    <span class="meta-value"><?= htmlspecialchars($email['bcc_list']) ?></span>
                </div>
                <?php endif; ?>
                <div class="meta-item">
                    <span class="meta-label">Sent At</span>
                    <span class="meta-value">
                        <?= date('F j, Y \a\t g:i A', strtotime($email['sent_at'])) ?>
                    </span>
                </div>
                <div class="meta-item">
                    <span class="meta-label">Message ID</span>
                    <span class="meta-value" style="font-size: 12px; word-break: break-all;">
                        <?= htmlspecialchars($email['message_id'] ?? 'N/A') ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Email Body -->
        <div class="email-body">
            <?php if (!empty($email['article_title'])): ?>
            <div class="article-title"><?= htmlspecialchars($email['article_title']) ?></div>
            <?php endif; ?>

            <div class="email-content">
                <?= $email['body_html'] ?? nl2br(htmlspecialchars($email['body_text'] ?? '')) ?>
            </div>
        </div>

        <!-- Attachments -->
        <?php if (!empty($attachments)): ?>
        <div class="attachments-section">
            <div class="attachments-title">
                <i class="fas fa-paperclip"></i>
                Attachments (<?= count($attachments) ?>)
            </div>
            <div class="attachment-list">
                <?php foreach ($attachments as $attachment): ?>
                <div class="attachment-item">
                    <div class="attachment-icon">
                        <i class="fas fa-file"></i>
                    </div>
                    <div class="attachment-info">
                        <div class="attachment-name"><?= htmlspecialchars($attachment['original_filename']) ?></div>
                        <div class="attachment-size"><?= formatFileSize($attachment['file_size']) ?></div>
                    </div>
                    <a href="download_attachment.php?id=<?= encryptFileId($attachment['id']) ?>" class="btn-download">
                        <i class="fas fa-download"></i>
                        Download
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function updateLabelData(select) {
            const selectedOption = select.options[select.selectedIndex];
            const labelName = selectedOption.getAttribute('data-name') || '';
            const labelColor = selectedOption.getAttribute('data-color') || '';
            
            document.getElementById('label_name').value = labelName;
            document.getElementById('label_color').value = labelColor;
        }
    </script>
</body>
</html>