<?php
// sent_history.php - Display sent emails history with Nature.com-inspired design
session_start();
require 'config.php';
require 'db_config.php';

// Security check
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    header("Location: login.php");
    exit();
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Get sent emails
$userEmail = $_SESSION['smtp_user'];
$sentEmails = getSentEmails($userEmail, $perPage, $offset);
$totalEmails = getSentEmailCount($userEmail);
$totalPages = ceil($totalEmails / $perPage);

// Get user info for sidebar
$userInitial = strtoupper(substr($userEmail, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sent History – SXC MDTS</title>
    
    <!-- Google Fonts - Nature.com inspired -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Harding:wght@400;500;600&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        body { 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background-color: #fff;
            color: #191919;
            display: flex;
            height: 100vh;
            overflow: hidden;
            line-height: 1.6;
            font-size: 16px;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            display: flex;
            overflow: hidden;
        }

        .content-area {
            flex: 1;
            overflow-y: auto;
            background: #fafafa;
        }

        /* Nature.com style header */
        .page-header {
            background: #fff;
            border-bottom: 1px solid #e0e0e0;
            padding: 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 20px 40px;
        }

        .breadcrumb {
            font-size: 14px;
            color: #666;
            margin-bottom: 12px;
        }

        .breadcrumb a {
            color: #0973dc;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .breadcrumb-separator {
            margin: 0 8px;
            color: #999;
        }

        .page-title-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }

        h1 {
            font-family: 'Harding', Georgia, serif;
            font-size: 32px;
            font-weight: 600;
            line-height: 1.2;
            color: #191919;
            letter-spacing: -0.5px;
        }

        .header-actions {
            display: flex;
            gap: 12px;
        }

        .page-meta {
            display: flex;
            align-items: center;
            gap: 20px;
            font-size: 14px;
            color: #666;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .meta-item i {
            color: #0c7b93;
        }

        /* Main container */
        .main-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 40px 40px 80px;
        }

        /* Email cards grid */
        .emails-grid {
            display: grid;
            gap: 16px;
        }

        .email-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 24px;
            transition: all 0.2s;
            cursor: pointer;
        }

        .email-card:hover {
            border-color: #0973dc;
            box-shadow: 0 4px 12px rgba(9, 115, 220, 0.1);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .card-subject {
            font-size: 18px;
            font-weight: 600;
            color: #191919;
            margin-bottom: 8px;
            line-height: 1.4;
            flex: 1;
        }

        .card-date {
            font-size: 13px;
            color: #666;
            white-space: nowrap;
            margin-left: 16px;
        }

        .card-recipient {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #666;
            margin-bottom: 12px;
        }

        .card-recipient i {
            color: #0c7b93;
            font-size: 13px;
        }

        .card-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            font-size: 13px;
            color: #999;
            padding-top: 12px;
            border-top: 1px solid #f0f0f0;
        }

        .card-meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .card-meta-item i {
            color: #0c7b93;
        }

        .card-article-title {
            font-size: 14px;
            color: #666;
            font-style: italic;
            margin-bottom: 8px;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
        }

        .empty-state i {
            font-size: 64px;
            color: #e0e0e0;
            margin-bottom: 24px;
        }

        .empty-state h2 {
            font-family: 'Harding', Georgia, serif;
            font-size: 24px;
            color: #191919;
            margin-bottom: 12px;
        }

        .empty-state p {
            font-size: 16px;
            color: #666;
            margin-bottom: 24px;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 40px;
            padding-top: 40px;
            border-top: 1px solid #e0e0e0;
        }

        .page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
            padding: 0 12px;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            color: #191919;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .page-link:hover {
            border-color: #0973dc;
            color: #0973dc;
        }

        .page-link.active {
            background: #0973dc;
            border-color: #0973dc;
            color: white;
        }

        .page-link.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
            border: 1px solid;
            cursor: pointer;
        }

        .btn-primary {
            background: #0973dc;
            color: white;
            border-color: #0973dc;
        }

        .btn-primary:hover {
            background: #006bb3;
            border-color: #006bb3;
            box-shadow: 0 2px 8px rgba(9, 115, 220, 0.25);
        }

        .btn-secondary {
            background: white;
            color: #191919;
            border-color: #e0e0e0;
        }

        .btn-secondary:hover {
            background: #f9fafb;
            border-color: #c0c0c0;
        }

        /* Modal for viewing email details */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            overflow-y: auto;
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .modal-content {
            background: white;
            border-radius: 8px;
            max-width: 900px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            padding: 24px 32px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-family: 'Harding', Georgia, serif;
            font-size: 24px;
            font-weight: 600;
            color: #191919;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: #666;
            cursor: pointer;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .modal-close:hover {
            background: #f0f0f0;
            color: #191919;
        }

        .modal-body {
            padding: 32px;
        }

        .modal-section {
            margin-bottom: 24px;
        }

        .modal-section-title {
            font-size: 14px;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .modal-section-content {
            font-size: 15px;
            color: #191919;
            line-height: 1.6;
        }

        .modal-email-body {
            background: #fafafa;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            padding: 20px;
            max-height: 400px;
            overflow-y: auto;
        }

        .tag {
            display: inline-block;
            padding: 4px 10px;
            background: #e8f4f8;
            color: #0c7b93;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            margin-right: 6px;
            margin-bottom: 6px;
        }

        ::-webkit-scrollbar {
            width: 10px;
        }

        ::-webkit-scrollbar-track {
            background: #f5f5f5;
        }

        ::-webkit-scrollbar-thumb {
            background: #c0c0c0;
            border-radius: 5px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #a0a0a0;
        }

        @media (max-width: 768px) {
            .main-container {
                padding: 24px 20px 60px;
            }

            h1 {
                font-size: 24px;
            }

            .modal-content {
                margin: 0;
                max-height: 100vh;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="content-area">
            <div class="page-header">
                <div class="header-container">
                    <div class="breadcrumb">
                        <a href="index.php">Home</a>
                        <span class="breadcrumb-separator">›</span>
                        <span>Sent History</span>
                    </div>
                    <div class="page-title-section">
                        <h1>Sent History</h1>
                        <div class="header-actions">
                            <a href="index.php" class="btn btn-primary">
                                <i class="fa-solid fa-plus"></i>
                                Compose New
                            </a>
                        </div>
                    </div>
                    <div class="page-meta">
                        <div class="meta-item">
                            <i class="fa-solid fa-envelope"></i>
                            <span><?= number_format($totalEmails) ?> sent emails</span>
                        </div>
                        <div class="meta-item">
                            <i class="fa-solid fa-user"></i>
                            <span><?= htmlspecialchars($userEmail) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="main-container">
                <?php if (empty($sentEmails)): ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-inbox"></i>
                        <h2>No Sent Emails Yet</h2>
                        <p>Your sent email history will appear here once you start sending emails.</p>
                        <a href="index.php" class="btn btn-primary">
                            <i class="fa-solid fa-plus"></i>
                            Compose Your First Email
                        </a>
                    </div>
                <?php else: ?>
                    <div class="emails-grid">
                        <?php foreach ($sentEmails as $email): ?>
                        <div class="email-card" onclick="showEmailDetails(<?= $email['id'] ?>)">
                            <div class="card-header">
                                <div style="flex: 1;">
                                    <div class="card-subject"><?= htmlspecialchars($email['subject']) ?></div>
                                    <?php if (!empty($email['article_title'])): ?>
                                    <div class="card-article-title">
                                        <i class="fa-solid fa-file-lines"></i>
                                        <?= htmlspecialchars($email['article_title']) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-date">
                                    <i class="fa-regular fa-clock"></i>
                                    <?= date('d M Y, H:i', strtotime($email['sent_at'])) ?>
                                </div>
                            </div>
                            
                            <div class="card-recipient">
                                <i class="fa-solid fa-paper-plane"></i>
                                <span><strong>To:</strong> <?= htmlspecialchars($email['recipient_email']) ?></span>
                            </div>

                            <?php if (!empty($email['cc_list']) || !empty($email['bcc_list']) || !empty($email['attachment_names'])): ?>
                            <div class="card-meta">
                                <?php if (!empty($email['cc_list'])): ?>
                                <div class="card-meta-item">
                                    <i class="fa-solid fa-copy"></i>
                                    <span>CC: <?= htmlspecialchars($email['cc_list']) ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($email['bcc_list'])): ?>
                                <div class="card-meta-item">
                                    <i class="fa-solid fa-user-secret"></i>
                                    <span>BCC: <?= htmlspecialchars($email['bcc_list']) ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($email['attachment_names'])): ?>
                                <div class="card-meta-item">
                                    <i class="fa-solid fa-paperclip"></i>
                                    <span><?= count(explode(', ', $email['attachment_names'])) ?> attachment(s)</span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>" class="page-link">
                                <i class="fa-solid fa-chevron-left"></i>
                            </a>
                        <?php else: ?>
                            <span class="page-link disabled">
                                <i class="fa-solid fa-chevron-left"></i>
                            </span>
                        <?php endif; ?>

                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        
                        for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <a href="?page=<?= $i ?>" class="page-link <?= $i == $page ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>" class="page-link">
                                <i class="fa-solid fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="page-link disabled">
                                <i class="fa-solid fa-chevron-right"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal for email details -->
    <div id="emailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Email Details</h2>
                <button class="modal-close" onclick="closeModal()">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Content loaded dynamically -->
            </div>
        </div>
    </div>

    <script>
        // Store email data for modal
        const emailData = <?= json_encode($sentEmails) ?>;

        function showEmailDetails(emailId) {
            const email = emailData.find(e => e.id == emailId);
            if (!email) return;

            const modalBody = document.getElementById('modalBody');
            
            let attachmentsList = '';
            if (email.attachment_names) {
                const attachments = email.attachment_names.split(', ');
                attachmentsList = attachments.map(name => 
                    `<span class="tag"><i class="fa-solid fa-file"></i> ${escapeHtml(name)}</span>`
                ).join('');
            }

            modalBody.innerHTML = `
                <div class="modal-section">
                    <div class="modal-section-title">Subject</div>
                    <div class="modal-section-content">${escapeHtml(email.subject)}</div>
                </div>

                ${email.article_title ? `
                <div class="modal-section">
                    <div class="modal-section-title">Article Title</div>
                    <div class="modal-section-content">${escapeHtml(email.article_title)}</div>
                </div>
                ` : ''}

                <div class="modal-section">
                    <div class="modal-section-title">Recipients</div>
                    <div class="modal-section-content">
                        <div style="margin-bottom: 8px;">
                            <strong>To:</strong> ${escapeHtml(email.recipient_email)}
                        </div>
                        ${email.cc_list ? `
                        <div style="margin-bottom: 8px;">
                            <strong>CC:</strong> ${escapeHtml(email.cc_list)}
                        </div>
                        ` : ''}
                        ${email.bcc_list ? `
                        <div>
                            <strong>BCC:</strong> ${escapeHtml(email.bcc_list)}
                        </div>
                        ` : ''}
                    </div>
                </div>

                ${attachmentsList ? `
                <div class="modal-section">
                    <div class="modal-section-title">Attachments</div>
                    <div class="modal-section-content">
                        ${attachmentsList}
                    </div>
                </div>
                ` : ''}

                <div class="modal-section">
                    <div class="modal-section-title">Sent At</div>
                    <div class="modal-section-content">
                        ${new Date(email.sent_at).toLocaleString('en-US', {
                            weekday: 'long',
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        })}
                    </div>
                </div>

                <div class="modal-section">
                    <div class="modal-section-title">Message Body</div>
                    <div class="modal-email-body">
                        ${email.message_body}
                    </div>
                </div>
            `;

            document.getElementById('emailModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('emailModal').classList.remove('active');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Close modal when clicking outside
        document.getElementById('emailModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>