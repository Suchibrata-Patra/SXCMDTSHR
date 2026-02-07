<?php
// view_sent_email.php - Enhanced with label display and editing
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

// Fetch email from database
try {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        die("Database connection failed");
    }
    
    $stmt = $pdo->prepare("
        SELECT se.*, el.label_name, el.label_color 
        FROM sent_emails se 
        LEFT JOIN email_labels el ON se.label_id = el.id 
        WHERE se.id = :id AND se.sender_email = :sender
    ");
    $stmt->execute([
        ':id' => $emailId,
        ':sender' => $userEmail
    ]);
    
    $email = $stmt->fetch();
    
    if (!$email) {
        header("Location: sent_history.php");
        exit();
    }
} catch (PDOException $e) {
    error_log("Error fetching email: " . $e->getMessage());
    die("Error loading email");
}

// Get all labels for the dropdown
$allLabels = getUserLabels($userEmail);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($email['subject']) ?> - SXC MDTS</title>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: #f6f8fc;
            min-height: 100vh;
            padding: 0;
        }

        /* Top Navigation Bar */
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
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
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
            border-radius: 4px;
            color: #5f6368;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.15s;
            cursor: pointer;
        }

        .back-button:hover {
            background: #f8f9fa;
            border-color: #bdc1c6;
        }

        .email-subject-nav {
            font-family: 'Google Sans', sans-serif;
            font-size: 18px;
            font-weight: 400;
            color: #202124;
        }

        .nav-actions {
            display: flex;
            gap: 12px;
        }

        /* Email Container */
        .email-container {
            max-width: 900px;
            margin: 24px auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 2px 0 rgba(60,64,67,0.3), 0 1px 3px 1px rgba(60,64,67,0.15);
            overflow: hidden;
        }

        /* Email Header Info */
        .email-header {
            padding: 24px;
            border-bottom: 1px solid #e0e0e0;
        }

        .email-subject {
            font-family: 'Google Sans', sans-serif;
            font-size: 22px;
            font-weight: 400;
            color: #202124;
            margin-bottom: 16px;
            line-height: 1.3;
        }

        /* Label Badge in Header */
        .label-badge-large {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 16px;
            font-size: 13px;
            font-weight: 600;
            color: white;
            margin-bottom: 12px;
        }

        .label-editor {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }

        .label-select {
            padding: 6px 12px;
            border: 1px solid #dadce0;
            border-radius: 4px;
            font-size: 13px;
            font-family: 'Roboto', sans-serif;
            cursor: pointer;
        }

        .label-select:focus {
            outline: none;
            border-color: #1a73e8;
        }

        .btn-save-label {
            padding: 6px 12px;
            background: #1a73e8;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-save-label:hover {
            background: #1765cc;
        }

        .btn-edit-label {
            background: transparent;
            border: 1px solid #dadce0;
            padding: 4px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            color: #5f6368;
            transition: all 0.2s;
        }

        .btn-edit-label:hover {
            background: #f8f9fa;
        }

        .email-meta-grid {
            display: grid;
            gap: 12px;
            font-size: 14px;
        }

        .meta-row {
            display: grid;
            grid-template-columns: 100px 1fr;
            gap: 12px;
        }

        .meta-label {
            color: #5f6368;
            font-weight: 500;
        }

        .meta-value {
            color: #202124;
        }

        .meta-value-muted {
            color: #5f6368;
            font-size: 13px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            background: #e8f0fe;
            color: #1967d2;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            margin-right: 6px;
        }

        /* Email Body */
        .email-body {
            padding: 0;
            background: #f6f8fc;
            min-height: 400px;
        }

        .email-body iframe {
            width: 100%;
            border: none;
            min-height: 600px;
            display: block;
        }

        /* Info Notice */
        .info-notice {
            background: #e8f0fe;
            border-left: 4px solid #1a73e8;
            padding: 16px 20px;
            margin: 24px;
            border-radius: 4px;
            font-size: 13px;
            color: #185abc;
        }

        .info-notice i {
            margin-right: 8px;
        }

        /* Success Message */
        .success-message {
            background: #e6f4ea;
            border-left: 4px solid #34a853;
            padding: 12px 16px;
            margin: 16px 0;
            border-radius: 4px;
            font-size: 13px;
            color: #137333;
            display: none;
        }

        .success-message.active {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Print Styles */
        @media print {
            .top-nav, .label-editor, .btn-edit-label {
                display: none;
            }

            .email-container {
                box-shadow: none;
                margin: 0;
            }

            .email-header {
                border-bottom: 2px solid #000;
            }
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .email-container {
                margin: 0;
                border-radius: 0;
            }

            .email-header {
                padding: 16px;
            }

            .meta-row {
                grid-template-columns: 80px 1fr;
            }

            .email-subject-nav {
                font-size: 16px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
                max-width: 200px;
            }
        }
    </style>
</head>
<body>
    <div class="top-nav">
        <div class="nav-left">
            <a href="sent_history.php" class="back-button">
                <i class="fa-solid fa-arrow-left"></i>
                Back to Sent
            </a>
            <span class="email-subject-nav"><?= htmlspecialchars($email['subject']) ?></span>
        </div>
        <div class="nav-actions">
            <button class="back-button" onclick="window.print()">
                <i class="fa-solid fa-print"></i>
                Print
            </button>
        </div>
    </div>

    <div class="email-container">
        <div class="email-header">
            <div id="successMessage" class="success-message">
                <span class="material-icons" style="font-size: 18px;">check_circle</span>
                Label updated successfully!
            </div>

            <!-- Label Display/Editor -->
            <div id="labelDisplay">
                <?php if (!empty($email['label_name'])): ?>
                    <div class="label-badge-large" style="background-color: <?= htmlspecialchars($email['label_color']) ?>;">
                        <span class="material-icons" style="font-size: 16px;">label</span>
                        <?= htmlspecialchars($email['label_name']) ?>
                    </div>
                    <button class="btn-edit-label" onclick="showLabelEditor()">
                        <i class="fa-solid fa-edit"></i> Change Label
                    </button>
                <?php else: ?>
                    <button class="btn-edit-label" onclick="showLabelEditor()">
                        <span class="material-icons" style="font-size: 14px; vertical-align: middle;">add</span>
                        Add Label
                    </button>
                <?php endif; ?>
            </div>

            <div id="labelEditor" class="label-editor" style="display: none;">
                <select id="labelSelect" class="label-select">
                    <option value="">No Label</option>
                    <?php foreach ($allLabels as $label): ?>
                        <option value="<?= $label['id'] ?>" 
                                <?= ($email['label_id'] == $label['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label['label_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn-save-label" onclick="saveLabel()">Save</button>
                <button class="btn-edit-label" onclick="hideLabelEditor()">Cancel</button>
            </div>

            <h1 class="email-subject"><?= htmlspecialchars($email['subject']) ?></h1>
            
            <div class="email-meta-grid">
                <div class="meta-row">
                    <span class="meta-label">From:</span>
                    <span class="meta-value"><?= htmlspecialchars($email['sender_email']) ?></span>
                </div>
                
                <div class="meta-row">
                    <span class="meta-label">To:</span>
                    <span class="meta-value"><?= htmlspecialchars($email['recipient_email']) ?></span>
                </div>
                
                <?php if (!empty($email['cc_list'])): ?>
                <div class="meta-row">
                    <span class="meta-label">CC:</span>
                    <span class="meta-value"><?= htmlspecialchars($email['cc_list']) ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($email['bcc_list'])): ?>
                <div class="meta-row">
                    <span class="meta-label">BCC:</span>
                    <span class="meta-value"><?= htmlspecialchars($email['bcc_list']) ?></span>
                </div>
                <?php endif; ?>
                
                <div class="meta-row">
                    <span class="meta-label">Date:</span>
                    <span class="meta-value-muted">
                        <?= date('l, F j, Y \a\t g:i A', strtotime($email['sent_at'])) ?>
                    </span>
                </div>
                
                <?php if (!empty($email['article_title'])): ?>
                <div class="meta-row">
                    <span class="meta-label">Article:</span>
                    <span class="meta-value"><?= htmlspecialchars($email['article_title']) ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($email['attachment_names'])): ?>
                <div class="meta-row">
                    <span class="meta-label">Attachments:</span>
                    <span class="meta-value">
                        <?php
                        $attachments = explode(', ', $email['attachment_names']);
                        foreach ($attachments as $attachment):
                        ?>
                            <span class="badge">
                                <i class="fa-solid fa-paperclip"></i>
                                <?= htmlspecialchars($attachment) ?>
                            </span>
                        <?php endforeach; ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="info-notice">
            <i class="fa-solid fa-info-circle"></i>
            This is a preview of the email as it was sent. The layout and styling match the original email template.
        </div>

        <div class="email-body">
            <iframe id="emailFrame" srcdoc="<?= htmlspecialchars($email['message_body']) ?>"></iframe>
        </div>
    </div>

    <script>
        const emailId = <?= $emailId ?>;

        function showLabelEditor() {
            document.getElementById('labelDisplay').style.display = 'none';
            document.getElementById('labelEditor').style.display = 'inline-flex';
        }

        function hideLabelEditor() {
            document.getElementById('labelDisplay').style.display = 'block';
            document.getElementById('labelEditor').style.display = 'none';
        }

        async function saveLabel() {
            const labelId = document.getElementById('labelSelect').value;
            
            try {
                const formData = new FormData();
                formData.append('ajax', '1');
                formData.append('email_id', emailId);
                formData.append('label_id', labelId || '');
                
                const response = await fetch('update_email_label.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Show success message
                    const successMsg = document.getElementById('successMessage');
                    successMsg.classList.add('active');
                    
                    // Reload page after short delay
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    alert('Failed to update label');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred');
            }
        }

        // Auto-resize iframe to fit content
        const iframe = document.getElementById('emailFrame');
        
        iframe.addEventListener('load', function() {
            try {
                const contentHeight = iframe.contentWindow.document.body.scrollHeight + 40;
                iframe.style.height = contentHeight + 'px';
                
                const iframeDoc = iframe.contentWindow.document;
                const style = iframeDoc.createElement('style');
                style.textContent = `
                    body {
                        margin: 0;
                        padding: 20px;
                        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
                        line-height: 1.6;
                        color: #333;
                    }
                    img {
                        max-width: 100%;
                        height: auto;
                    }
                    table {
                        border-collapse: collapse;
                        max-width: 100%;
                    }
                `;
                iframeDoc.head.appendChild(style);
            } catch (e) {
                console.error('Could not resize iframe:', e);
                iframe.style.height = '800px';
            }
        });

        // Handle back button with history
        window.addEventListener('popstate', function() {
            window.location.href = 'sent_history.php';
        });
    </script>
</body>
</html>