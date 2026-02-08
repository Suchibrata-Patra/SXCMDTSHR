<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle restore action
if (isset($_POST['restore']) && isset($_POST['message_ids'])) {
    $message_ids = $_POST['message_ids'];
    $placeholders = str_repeat('?,', count($message_ids) - 1) . '?';
    $params = array_merge($message_ids, [$user_id]);
    
    $stmt = $pdo->prepare("UPDATE messages SET is_deleted = 0, deleted_at = NULL 
                          WHERE id IN ($placeholders) AND receiver_id = ?");
    $stmt->execute($params);
    
    header('Location: deleted_items.php');
    exit();
}

// Handle permanent delete action
if (isset($_POST['delete_permanent']) && isset($_POST['message_ids'])) {
    $message_ids = $_POST['message_ids'];
    $placeholders = str_repeat('?,', count($message_ids) - 1) . '?';
    $params = array_merge($message_ids, [$user_id]);
    
    $stmt = $pdo->prepare("DELETE FROM messages 
                          WHERE id IN ($placeholders) AND receiver_id = ?");
    $stmt->execute($params);
    
    header('Location: deleted_items.php');
    exit();
}

// Fetch deleted messages
$stmt = $pdo->prepare("
    SELECT m.*, u.username as sender_name 
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.receiver_id = ? AND m.is_deleted = 1
    ORDER BY m.deleted_at DESC
");
$stmt->execute([$user_id]);
$deleted_messages = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deleted Items</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f5;
            padding-top: 60px;
        }

        .top-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #fff;
            border-bottom: 1px solid #e0e0e0;
            z-index: 100;
            height: 60px;
            display: flex;
            align-items: center;
            padding: 0 16px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .top-bar h1 {
            font-size: 20px;
            font-weight: 500;
            color: #333;
        }

        .action-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #1976d2;
            height: 60px;
            display: none;
            align-items: center;
            padding: 0 16px;
            z-index: 101;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .action-bar.active {
            display: flex;
        }

        .action-bar .count {
            color: #fff;
            font-size: 18px;
            font-weight: 500;
            margin-right: auto;
        }

        .action-bar .actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .action-btn {
            background: none;
            border: none;
            color: #fff;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .action-btn:hover {
            background: rgba(255,255,255,0.1);
        }

        .action-btn .material-icons {
            font-size: 24px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 16px;
        }

        .select-all-container {
            background: #fff;
            padding: 12px 16px;
            margin-bottom: 8px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .select-all-container label {
            display: flex;
            align-items: center;
            cursor: pointer;
            font-size: 14px;
            color: #666;
        }

        .select-all-container input[type="checkbox"] {
            margin-right: 12px;
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .message-list {
            list-style: none;
        }

        .message-item {
            background: #fff;
            margin-bottom: 8px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: all 0.2s;
        }

        .message-item:hover {
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }

        .message-content {
            display: flex;
            align-items: center;
            padding: 16px;
            cursor: pointer;
        }

        .checkbox-wrapper {
            margin-right: 16px;
        }

        .checkbox-wrapper input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .message-info {
            flex: 1;
            min-width: 0;
        }

        .message-header {
            display: flex;
            align-items: center;
            margin-bottom: 4px;
        }

        .sender-name {
            font-weight: 500;
            color: #333;
            margin-right: 12px;
        }

        .message-date {
            font-size: 12px;
            color: #999;
        }

        .message-subject {
            font-size: 14px;
            color: #666;
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .message-preview {
            font-size: 13px;
            color: #999;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state .material-icons {
            font-size: 64px;
            color: #ccc;
            margin-bottom: 16px;
        }

        .empty-state p {
            font-size: 16px;
        }

        @media (max-width: 600px) {
            .container {
                padding: 8px;
            }

            .message-content {
                padding: 12px;
            }

            .sender-name {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <h1>Deleted Items</h1>
    </div>

    <div class="action-bar" id="actionBar">
        <span class="count" id="selectedCount">0 selected</span>
        <div class="actions">
            <button type="button" class="action-btn" id="restoreBtn" title="Restore">
                <span class="material-icons">restore_from_trash</span>
            </button>
            <button type="button" class="action-btn" id="deleteBtn" title="Delete Permanently">
                <span class="material-icons">delete_forever</span>
            </button>
            <button type="button" class="action-btn" id="closeBtn" title="Close">
                <span class="material-icons">close</span>
            </button>
        </div>
    </div>

    <div class="container">
        <?php if (count($deleted_messages) > 0): ?>
            <form id="messageForm" method="POST">
                <div class="select-all-container">
                    <label>
                        <input type="checkbox" id="selectAll">
                        <span>Select all</span>
                    </label>
                </div>

                <ul class="message-list">
                    <?php foreach ($deleted_messages as $message): ?>
                        <li class="message-item">
                            <div class="message-content">
                                <div class="checkbox-wrapper">
                                    <input type="checkbox" class="message-checkbox" name="message_ids[]" value="<?= $message['id'] ?>">
                                </div>
                                <div class="message-info">
                                    <div class="message-header">
                                        <span class="sender-name"><?= htmlspecialchars($message['sender_name']) ?></span>
                                        <span class="message-date"><?= date('M d', strtotime($message['deleted_at'])) ?></span>
                                    </div>
                                    <div class="message-subject">
                                        <?= htmlspecialchars($message['subject'] ?: '(No Subject)') ?>
                                    </div>
                                    <div class="message-preview">
                                        <?= htmlspecialchars(substr($message['message'], 0, 100)) ?>...
                                    </div>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </form>
        <?php else: ?>
            <div class="empty-state">
                <span class="material-icons">delete_outline</span>
                <p>No deleted items</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const actionBar = document.getElementById('actionBar');
        const selectedCount = document.getElementById('selectedCount');
        const selectAllCheckbox = document.getElementById('selectAll');
        const messageCheckboxes = document.querySelectorAll('.message-checkbox');
        const messageForm = document.getElementById('messageForm');
        const restoreBtn = document.getElementById('restoreBtn');
        const deleteBtn = document.getElementById('deleteBtn');
        const closeBtn = document.getElementById('closeBtn');

        function updateActionBar() {
            const checkedBoxes = document.querySelectorAll('.message-checkbox:checked');
            const count = checkedBoxes.length;

            if (count > 0) {
                actionBar.classList.add('active');
                selectedCount.textContent = `${count} selected`;
            } else {
                actionBar.classList.remove('active');
            }

            // Update select all checkbox state
            selectAllCheckbox.checked = count === messageCheckboxes.length && count > 0;
        }

        // Individual checkbox change
        messageCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateActionBar);
        });

        // Select all functionality
        selectAllCheckbox.addEventListener('change', function() {
            messageCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateActionBar();
        });

        // Restore button
        restoreBtn.addEventListener('click', function() {
            if (confirm('Restore selected messages?')) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'restore';
                input.value = '1';
                messageForm.appendChild(input);
                messageForm.submit();
            }
        });

        // Delete permanently button
        deleteBtn.addEventListener('click', function() {
            if (confirm('Permanently delete selected messages? This cannot be undone.')) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'delete_permanent';
                input.value = '1';
                messageForm.appendChild(input);
                messageForm.submit();
            }
        });

        // Close button
        closeBtn.addEventListener('click', function() {
            messageCheckboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            selectAllCheckbox.checked = false;
            updateActionBar();
        });
    </script>
</body>
</html>