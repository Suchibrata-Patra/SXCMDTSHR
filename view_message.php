<?php
/**
 * View Message Page
 * Displays full message content
 */
require_once __DIR__ . '/security_handler.php';
session_start();

// Check authentication
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: login.php');
    exit;
}

require_once 'db_config.php';

$userEmail = $_SESSION['smtp_user'];
$messageId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($messageId === 0) {
    header('Location: inbox.php');
    exit;
}

// Fetch message details
$pdo = getDatabaseConnection();
$stmt = $pdo->prepare("
    SELECT * FROM inbox_messages 
    WHERE id = :id AND user_email = :user_email AND is_deleted = 0
");
$stmt->execute([
    ':id' => $messageId,
    ':user_email' => $userEmail
]);
$message = $stmt->fetch();

if (!$message) {
    header('Location: inbox.php');
    exit;
}

// Mark as read if not already
if (!$message['is_read']) {
    markMessageAsRead($messageId, $userEmail);
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
            --apple-blue: #007AFF;
            --apple-gray: #8E8E93;
            --apple-bg: #F2F2F7;
            --border: #E5E5EA;
            --text-primary: #1c1c1e;
            --text-secondary: #52525b;
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
            padding: 30px 40px;
            overflow-y: auto;
        }

        .message-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .back-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: white;
            color: var(--text-primary);
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
        }

        .back-btn:hover {
            background: var(--apple-bg);
            border-color: var(--apple-gray);
        }

        .message-actions-bar {
            display: flex;
            gap: 12px;
        }

        .action-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: white;
            color: var(--text-primary);
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .action-btn:hover {
            background: var(--apple-bg);
        }

        .action-btn.delete {
            color: #ea4335;
        }

        .message-container {
            background: white;
            border-radius: 12px;
            border: 1px solid var(--border);
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }

        .message-header {
            padding: 30px;
            border-bottom: 1px solid var(--border);
        }

        .message-subject {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 20px;
            letter-spacing: -0.5px;
        }

        .message-meta {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .meta-row {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }

        .meta-label {
            font-weight: 600;
            color: var(--apple-gray);
            min-width: 80px;
        }

        .meta-value {
            color: var(--text-primary);
        }

        .sender-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--apple-blue);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 16px;
            margin-right: 8px;
        }

        .message-body {
            padding: 40px 30px;
            font-size: 15px;
            line-height: 1.8;
            color: var(--text-primary);
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .attachment-section {
            padding: 20px 30px;
            border-top: 1px solid var(--border);
            background: var(--apple-bg);
        }

        .attachment-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--apple-gray);
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px 16px;
            }

            .message-header-bar {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }

            .message-subject {
                font-size: 22px;
            }

            .message-body {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <!-- Header Bar -->
            <div class="message-header-bar">
                <a href="inbox.php" class="back-btn">
                    <span class="material-icons">arrow_back</span>
                    Back to Inbox
                </a>
                <div class="message-actions-bar">
                    <button class="action-btn" onclick="window.print()">
                        <span class="material-icons">print</span>
                        Print
                    </button>
                    <button class="action-btn delete" onclick="deleteMessage()">
                        <span class="material-icons">delete</span>
                        Delete
                    </button>
                </div>
            </div>

            <!-- Message Container -->
            <div class="message-container">
                <!-- Message Header -->
                <div class="message-header">
                    <h1 class="message-subject"><?= htmlspecialchars($message['subject']) ?></h1>
                    
                    <div class="message-meta">
                        <div class="meta-row">
                            <span class="meta-label">From:</span>
                            <div style="display: flex; align-items: center;">
                                <div class="sender-avatar">
                                    <?= strtoupper(substr($message['sender_email'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="meta-value">
                                        <?php 
                                        $displayName = !empty($message['sender_name']) 
                                            ? htmlspecialchars($message['sender_name']) 
                                            : htmlspecialchars($message['sender_email']);
                                        echo $displayName;
                                        ?>
                                    </div>
                                    <div style="font-size: 12px; color: var(--apple-gray);">
                                        <?= htmlspecialchars($message['sender_email']) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="meta-row">
                            <span class="meta-label">Date:</span>
                            <span class="meta-value">
                                <?= date('F j, Y \a\t g:i A', strtotime($message['received_date'])) ?>
                            </span>
                        </div>
                        
                        <div class="meta-row">
                            <span class="meta-label">To:</span>
                            <span class="meta-value"><?= htmlspecialchars($userEmail) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Message Body -->
                <div class="message-body">
                    <?= nl2br(htmlspecialchars($message['body'])) ?>
                </div>

                <!-- Attachments Section -->
                <?php if ($message['has_attachments']): ?>
                <div class="attachment-section">
                    <div class="attachment-title">Attachments</div>
                    <p style="font-size: 14px; color: var(--apple-gray);">
                        This message contains attachments. (Attachment download feature coming soon)
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function deleteMessage() {
            if (!confirm('Are you sure you want to delete this message?')) {
                return;
            }

            fetch('delete_inbox_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    message_id: <?= $messageId ?>
                })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('Message deleted successfully');
                    window.location.href = 'inbox.php';
                } else {
                    alert('Error: ' + (result.error || 'Could not delete message'));
                }
            })
            .catch(error => {
                alert('Network error occurred');
            });
        }
    </script>
</body>
</html>
