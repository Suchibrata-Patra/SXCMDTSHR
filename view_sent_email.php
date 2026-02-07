<?php
// view_sent_email.php - Display individual sent email with exact template
session_start();
require 'config.php';
require 'db_config.php';

// Security check
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    header("Location: login.php");
    exit();
}

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
    
    $stmt = $pdo->prepare("SELECT * FROM sent_emails WHERE id = :id AND sender_email = :sender");
    $stmt->execute([
        ':id' => $emailId,
        ':sender' => $_SESSION['smtp_user']
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($email['subject']) ?> - SXC MDTS</title>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
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

        /* Email Body - Renders exact template */
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

        /* Fallback for direct HTML rendering */
        .email-body-direct {
            padding: 32px;
            line-height: 1.6;
            color: #202124;
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

        /* Print Styles */
        @media print {
            .top-nav {
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
            <!-- Render the exact email HTML in an iframe for isolation -->
            <iframe id="emailFrame" srcdoc="<?= htmlspecialchars($email['message_body']) ?>"></iframe>
        </div>
    </div>

    <script>
        // Auto-resize iframe to fit content
        const iframe = document.getElementById('emailFrame');
        
        iframe.addEventListener('load', function() {
            try {
                // Add some padding for safety
                const contentHeight = iframe.contentWindow.document.body.scrollHeight + 40;
                iframe.style.height = contentHeight + 'px';
                
                // Add base styles to iframe content if needed
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
                // Fallback height
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