<?php
// /Applications/XAMPP/xamppfiles/htdocs/send.php
session_start();
require 'vendor/autoload.php';
require 'config.php';

if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    header("Location: login.php");
    exit();
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $mail = new PHPMailer(true);
    
    $successEmails = [];
    $failedEmails = [];

    try {
        // --- SMTP Configuration ---
        $mail->isSMTP();
        $mail->Host       = env("SMTP_HOST", "smtp.gmail.com"); 
        $mail->SMTPAuth   = true;
        $mail->Username   = $_SESSION['smtp_user']; 
        $mail->Password   = $_SESSION['smtp_pass']; 
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
        $mail->Port       = env("SMTP_PORT", 587);

        // --- Recipients ---
        $settings = $_SESSION['user_settings'] ?? [];
        $displayName = !empty($settings['display_name']) ? $settings['display_name'] : "MailDash Sender";
        
        $mail->setFrom($_SESSION['smtp_user'], $displayName);
        
        // Main recipient
        $recipient = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $mail->addAddress($recipient);
            $successEmails[] = ['email' => $recipient, 'type' => 'To'];
        } else {
            $failedEmails[] = ['email' => $recipient, 'type' => 'To', 'reason' => 'Invalid email format'];
        }

        // --- Handle CC Recipients ---
        if (!empty($_POST['cc'])) {
            $ccEmails = parseEmailList($_POST['cc']);
            foreach ($ccEmails as $ccEmail) {
                if (filter_var($ccEmail, FILTER_VALIDATE_EMAIL)) {
                    try {
                        $mail->addCC($ccEmail);
                        $successEmails[] = ['email' => $ccEmail, 'type' => 'CC'];
                    } catch (Exception $e) {
                        $failedEmails[] = ['email' => $ccEmail, 'type' => 'CC', 'reason' => $e->getMessage()];
                    }
                } else {
                    $failedEmails[] = ['email' => $ccEmail, 'type' => 'CC', 'reason' => 'Invalid email format'];
                }
            }
        }

        // --- Handle BCC Recipients ---
        if (!empty($_POST['bcc'])) {
            $bccEmails = parseEmailList($_POST['bcc']);
            foreach ($bccEmails as $bccEmail) {
                if (filter_var($bccEmail, FILTER_VALIDATE_EMAIL)) {
                    try {
                        $mail->addBCC($bccEmail);
                        $successEmails[] = ['email' => $bccEmail, 'type' => 'BCC'];
                    } catch (Exception $e) {
                        $failedEmails[] = ['email' => $bccEmail, 'type' => 'BCC', 'reason' => $e->getMessage()];
                    }
                } else {
                    $failedEmails[] = ['email' => $bccEmail, 'type' => 'BCC', 'reason' => 'Invalid email format'];
                }
            }
        }

        // --- Handle File Attachment ---
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == UPLOAD_ERR_OK) {
            $mail->addAttachment(
                $_FILES['attachment']['tmp_name'], 
                $_FILES['attachment']['name']
            );
        }

        // --- Content Processing ---
        $mail->isHTML(true);
        
        // Apply subject prefix if set
        $subject = $_POST['subject'] ?? 'Notification';
        if (!empty($settings['default_subject_prefix'])) {
            $subject = $settings['default_subject_prefix'] . " " . $subject;
        }
        $mail->Subject = $subject;
        
        $messageBody = $_POST['message'] ?? '';
        $articleTitle = $_POST['articletitle'] ?? '';
        
        // Apply signature if set
        if (!empty($settings['signature'])) {
            $messageBody .= "\n\n" . $settings['signature'];
        }
        
        $templatePath = 'templates/template1.html';

        if (file_exists($templatePath)) {
            $htmlStructure = file_get_contents($templatePath);
            $formattedText = nl2br(htmlspecialchars($messageBody));
            $finalHtml = str_replace('{{MESSAGE}}', $formattedText, $htmlStructure);
            $finalHtml = str_replace('{{SUBJECT}}', htmlspecialchars($subject), $finalHtml);
            $template = str_replace('{{articletitle}}', $articleTitle, $htmlStructure);
            $finalHtml = str_replace('{{SENDER_NAME}}', htmlspecialchars($displayName), $finalHtml);
            $finalHtml = str_replace('{{SENDER_EMAIL}}', htmlspecialchars($_SESSION['smtp_user']), $finalHtml);
            $finalHtml = str_replace('{{RECIPIENT_EMAIL}}', htmlspecialchars($recipient), $finalHtml);
            $finalHtml = str_replace('{{CURRENT_DATE}}', date('F j, Y'), $finalHtml);
            $finalHtml = str_replace('{{CURRENT_YEAR}}', date('Y'), $finalHtml);
            $finalHtml = str_replace('{{ATTACHMENT}}', '', $finalHtml);
            $mail->Body = $finalHtml;
            $mail->AltBody = strip_tags($messageBody);
        } else {
            $mail->Body = nl2br(htmlspecialchars($messageBody));
        }

        $mail->send();
        
        // Generate response HTML
        showResultPage($subject, $successEmails, $failedEmails);

    } catch (Exception $e) {
        showErrorPage("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
    }
} else {
    header("Location: index.php");
    exit();
}

/**
 * Parse comma/semicolon/newline separated email list
 */
function parseEmailList($emailString) {
    $emails = preg_split('/[,;\n\r]+/', $emailString);
    $emails = array_map('trim', $emails);
    $emails = array_filter($emails, function($email) {
        return !empty($email);
    });
    return array_unique($emails);
}

/**
 * Show result page with sidebar navigation - Nature style
 */
function showResultPage($subject, $successEmails, $failedEmails) {
    $totalEmails = count($successEmails) + count($failedEmails);
    $successCount = count($successEmails);
    $failureCount = count($failedEmails);
    $timestamp = date('d F Y, H:i');
    
    // Get user initial for avatar
    $userEmail = $_SESSION['smtp_user'];
    $userInitial = strtoupper(substr($userEmail, 0, 1));
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Transmission Report</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Harding:wght@400;600;700&family=Inter:wght@400;500;600&display=swap">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            
            body { 
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
                background-color: #ffffff;
                color: #222;
                display: flex;
                height: 100vh;
                overflow: hidden;
                font-size: 14px;
                line-height: 1.6;
            }

            /* Sidebar Styles */
            .sidebar {
                width: 280px;
                background-color: #ffffff;
                border-right: 1px solid #e0e0e0;
                display: flex;
                flex-direction: column;
                transition: transform 0.3s ease, width 0.3s ease;
                position: relative;
                z-index: 100;
            }

            .sidebar.collapsed { width: 70px; }

            .sidebar-header {
                padding: 24px 20px;
                border-bottom: 1px solid #e0e0e0;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }

            .logo {
                display: flex;
                align-items: center;
                gap: 12px;
                font-size: 20px;
                font-weight: 600;
                color: #222;
                letter-spacing: -0.5px;
                text-decoration: none;
            }

            .logo i { font-size: 24px; }
            .sidebar.collapsed .logo-text { display: none; }

            .toggle-sidebar {
                background: none;
                border: none;
                cursor: pointer;
                padding: 8px;
                color: #666;
                border-radius: 4px;
                transition: all 0.2s;
            }

            .toggle-sidebar:hover {
                background-color: #f5f5f5;
                color: #222;
            }

            .sidebar.collapsed .toggle-sidebar { transform: rotate(180deg); }

            .nav-section {
                flex: 1;
                padding: 20px 0;
                overflow-y: auto;
            }

            .nav-item {
                display: flex;
                align-items: center;
                padding: 12px 20px;
                color: #666;
                text-decoration: none;
                transition: all 0.2s;
                cursor: pointer;
                border-left: 3px solid transparent;
                gap: 12px;
                font-size: 15px;
            }

            .nav-item:hover {
                background-color: #f9f9f9;
                color: #222;
            }

            .nav-item.active {
                background-color: #f5f5f5;
                color: #222;
                border-left-color: #222;
                font-weight: 500;
            }

            .nav-item i {
                width: 20px;
                text-align: center;
            }

            .sidebar.collapsed .nav-item span { display: none; }
            .sidebar.collapsed .nav-item {
                justify-content: center;
                padding: 12px;
            }

            .user-info {
                padding: 20px;
                border-top: 1px solid #e0e0e0;
            }

            .user-details {
                display: flex;
                align-items: center;
                gap: 12px;
                margin-bottom: 16px;
            }

            .user-avatar {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                background: #222;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-weight: 600;
                font-size: 16px;
            }

            .user-email {
                flex: 1;
                font-size: 13px;
                color: #666;
                overflow: hidden;
            }

            .user-email strong {
                display: block;
                color: #222;
                font-size: 14px;
                margin-bottom: 2px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .sidebar.collapsed .user-email { display: none; }

            .logout-link {
                color: #d32f2f;
                text-decoration: none;
                padding: 10px 12px;
                border-radius: 6px;
                display: flex;
                align-items: center;
                gap: 10px;
                transition: all 0.2s;
                font-size: 14px;
                font-weight: 500;
            }

            .logout-link:hover { background-color: #ffebee; }
            .sidebar.collapsed .logout-link span { display: none; }
            .sidebar.collapsed .logout-link { justify-content: center; }

            /* Main Content Area */
            .main-content {
                flex: 1;
                display: flex;
                overflow: hidden;
                background-color: #fafafa;
            }

            .content-area {
                flex: 1;
                padding: 40px 60px;
                overflow-y: auto;
            }

            /* Report Styles */
            .report-header {
                background: #fff;
                border: 1px solid #e0e0e0;
                border-radius: 4px;
                padding: 32px;
                margin-bottom: 24px;
            }

            .report-type {
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                color: #0073e6;
                font-weight: 600;
                margin-bottom: 12px;
            }

            h1 {
                font-family: 'Harding', Georgia, serif;
                font-size: 28px;
                font-weight: 700;
                line-height: 1.3;
                color: #222;
                margin-bottom: 16px;
            }

            .meta-info {
                font-size: 13px;
                color: #666;
                line-height: 1.8;
                border-top: 1px solid #e0e0e0;
                padding-top: 16px;
                margin-top: 16px;
            }

            .meta-info strong {
                color: #222;
                font-weight: 600;
            }

            .meta-timestamp {
                font-size: 12px;
                color: #999;
                margin-top: 8px;
            }

            /* Summary Box */
            .summary-box {
                background: #fff;
                border: 1px solid #e0e0e0;
                border-left: 3px solid #0073e6;
                border-radius: 4px;
                padding: 24px;
                margin-bottom: 24px;
            }

            .summary-title {
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                color: #666;
                margin-bottom: 16px;
                font-weight: 600;
            }

            .summary-stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
                gap: 20px;
            }

            .stat-item {
                display: flex;
                flex-direction: column;
            }

            .stat-label {
                font-size: 11px;
                color: #666;
                text-transform: uppercase;
                letter-spacing: 0.3px;
                margin-bottom: 4px;
            }

            .stat-value {
                font-size: 24px;
                font-weight: 600;
                color: #222;
            }

            .stat-value.success { color: #087f23; }
            .stat-value.failure { color: #c5221f; }

            /* Content Sections */
            .content-section {
                background: #fff;
                border: 1px solid #e0e0e0;
                border-radius: 4px;
                padding: 32px;
                margin-bottom: 24px;
            }

            h2 {
                font-family: 'Harding', Georgia, serif;
                font-size: 18px;
                font-weight: 600;
                color: #222;
                margin-bottom: 20px;
                padding-bottom: 12px;
                border-bottom: 2px solid #222;
            }

            /* Email Table */
            .email-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 16px;
                font-size: 13px;
            }

            .email-table thead { border-bottom: 2px solid #222; }

            .email-table th {
                text-align: left;
                font-weight: 600;
                padding: 12px 16px 12px 0;
                color: #222;
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.3px;
            }

            .email-table td {
                padding: 14px 16px 14px 0;
                border-bottom: 1px solid #e0e0e0;
                vertical-align: top;
            }

            .email-table tbody tr:last-child td { border-bottom: none; }

            .email-address {
                font-family: 'Monaco', 'Courier New', monospace;
                font-size: 13px;
                color: #222;
            }

            .email-type {
                display: inline-block;
                padding: 3px 10px;
                background: #e0e0e0;
                border-radius: 3px;
                font-size: 11px;
                font-weight: 600;
                color: #222;
            }

            .email-type.to { background: #e3f2fd; color: #0d47a1; }
            .email-type.cc { background: #fff3e0; color: #e65100; }
            .email-type.bcc { background: #f3e5f5; color: #4a148c; }

            .error-reason {
                color: #c5221f;
                font-size: 12px;
                line-height: 1.5;
            }

            /* Action Buttons */
            .actions {
                background: #fff;
                border: 1px solid #e0e0e0;
                border-radius: 4px;
                padding: 24px;
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
            }

            .btn {
                padding: 10px 20px;
                border: 1px solid #222;
                background: #fff;
                color: #222;
                text-decoration: none;
                font-size: 13px;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s;
                font-family: 'Inter', sans-serif;
                border-radius: 3px;
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }

            .btn:hover {
                background: #222;
                color: #fff;
            }

            .btn-primary {
                background: #222;
                color: #fff;
            }

            .btn-primary:hover { background: #000; }
            .btn i { font-size: 12px; }

            @media (max-width: 768px) {
                .content-area { padding: 24px 20px; }
                h1 { font-size: 24px; }
                .email-table { font-size: 12px; }
                .summary-stats { grid-template-columns: 1fr; }
                .actions { flex-direction: column; }
                .btn {
                    width: 100%;
                    justify-content: center;
                }
            }
        </style>
    </head>
    <body>
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
            <a href="index.php" class="logo">
            <img src="https://upload.wikimedia.org/wikipedia/en/b/b0/St._Xavier%27s_College%2C_Kolkata_logo.jpg" alt="Logo">
        </a>
                <button class="toggle-sidebar" id="toggleSidebar">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
            </div>

            <nav class="nav-section">
                <a href="index.php" class="nav-item">
                    <i class="fa-solid fa-paper-plane"></i>
                    <span>Compose</span>
                </a>
                <div class="nav-item active">
                    <i class="fa-solid fa-chart-line"></i>
                    <span>Report</span>
                </div>
                <a href="#" class="nav-item" id="settingsBtn">
                    <i class="fa-solid fa-gear"></i>
                    <span>Settings</span>
                </a>
            </nav>

            <div class="user-info">
                <div class="user-details">
                    <div class="user-avatar"><?= $userInitial ?></div>
                    <div class="user-email">
                        <strong>Account</strong>
                        <?= htmlspecialchars($userEmail) ?>
                    </div>
                </div>
                <a href="logout.php" class="logout-link">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="content-area">
                <!-- Report Header -->
                <div class="report-header">
                    <!-- <div class="report-type">Transmission Report</div> -->
                    <h1>Mail Delivery Status</h1>
                    <div class="meta-info">
                        <strong>Subject:</strong> <?= htmlspecialchars($subject) ?><br>
                        <strong>Sender:</strong> <?= htmlspecialchars($_SESSION['smtp_user']) ?>
                        <div class="meta-timestamp">Generated on <?= $timestamp ?></div>
                    </div>
                </div>

                <!-- Summary Statistics -->
                <div class="summary-box">
                    <div class="summary-title">Summary</div>
                    <div class="summary-stats">
                        <div class="stat-item">
                            <div class="stat-label">Total Recipients</div>
                            <div class="stat-value"><?= $totalEmails ?></div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Successfully Sent</div>
                            <div class="stat-value success"><?= $successCount ?></div>
                        </div>
                        <?php if ($failureCount > 0): ?>
                        <div class="stat-item">
                            <div class="stat-label">Failed</div>
                            <div class="stat-value failure"><?= $failureCount ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Successfully Delivered -->
                <?php if (count($successEmails) > 0): ?>
                <div class="content-section">
                    <h2>Successfully Delivered</h2>
                    <table class="email-table">
                        <thead>
                            <tr>
                                <th style="width: 60%">Email Address</th>
                                <th style="width: 40%">Recipient Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($successEmails as $email): ?>
                            <tr>
                                <td class="email-address"><?= htmlspecialchars($email['email']) ?></td>
                                <td><span class="email-type <?= strtolower($email['type']) ?>"><?= htmlspecialchars($email['type']) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <!-- Delivery Failures -->
                <?php if (count($failedEmails) > 0): ?>
                <div class="content-section">
                    <h2>Delivery Failures</h2>
                    <table class="email-table">
                        <thead>
                            <tr>
                                <th style="width: 45%">Email Address</th>
                                <th style="width: 15%">Type</th>
                                <th style="width: 40%">Failure Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($failedEmails as $email): ?>
                            <tr>
                                <td class="email-address"><?= htmlspecialchars($email['email']) ?></td>
                                <td><span class="email-type <?= strtolower($email['type']) ?>"><?= htmlspecialchars($email['type']) ?></span></td>
                                <td class="error-reason"><?= htmlspecialchars($email['reason']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <!-- Actions -->
                <div class="actions">
                    <a href="index.php" class="btn btn-primary">
                        <i class="fa-solid fa-paper-plane"></i>
                        Send Another Email
                    </a>
                    <?php if ($failureCount > 0): ?>
                    <button onclick="copyFailedEmails()" class="btn">
                        <i class="fa-solid fa-copy"></i>
                        Copy Failed Addresses
                    </button>
                    <?php endif; ?>
                    <?php if ($successCount > 0): ?>
                    <button onclick="copySuccessEmails()" class="btn">
                        <i class="fa-solid fa-copy"></i>
                        Copy Successful Addresses
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script>
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('toggleSidebar');
            
            toggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('collapsed');
            });

            function copyFailedEmails() {
                const failedEmails = <?= json_encode(array_column($failedEmails, 'email')) ?>;
                const emailList = failedEmails.join(', ');
                
                navigator.clipboard.writeText(emailList).then(() => {
                    alert('Failed email addresses copied to clipboard.');
                }).catch(() => {
                    alert('Failed to copy. Please select and copy manually.');
                });
            }

            function copySuccessEmails() {
                const successEmails = <?= json_encode(array_column($successEmails, 'email')) ?>;
                const emailList = successEmails.join(', ');
                
                navigator.clipboard.writeText(emailList).then(() => {
                    alert('Successful email addresses copied to clipboard.');
                }).catch(() => {
                    alert('Failed to copy. Please select and copy manually.');
                });
            }

            document.getElementById('settingsBtn').addEventListener('click', (e) => {
                e.preventDefault();
                window.location.href = 'index.php#settings';
            });
        </script>
    </body>
    </html>
    <?php
}

/**
 * Show error page with sidebar navigation
 */
function showErrorPage($errorMessage) {
    $timestamp = date('d F Y, H:i');
    $userEmail = $_SESSION['smtp_user'];
    $userInitial = strtoupper(substr($userEmail, 0, 1));
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Transmission Error</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Harding:wght@400;600;700&family=Inter:wght@400;500;600&display=swap">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            
            body { 
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
                background-color: #ffffff;
                color: #222;
                display: flex;
                height: 100vh;
                overflow: hidden;
                font-size: 14px;
                line-height: 1.6;
            }

            .sidebar {
                width: 280px;
                background-color: #ffffff;
                border-right: 1px solid #e0e0e0;
                display: flex;
                flex-direction: column;
                transition: transform 0.3s ease, width 0.3s ease;
                position: relative;
                z-index: 100;
            }

            .sidebar.collapsed { width: 70px; }

            .sidebar-header {
                padding: 24px 20px;
                border-bottom: 1px solid #e0e0e0;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }

            .logo {
                display: flex;
                align-items: center;
                gap: 12px;
                font-size: 20px;
                font-weight: 600;
                color: #222;
                letter-spacing: -0.5px;
                text-decoration: none;
            }

            .logo i { font-size: 24px; }
            .sidebar.collapsed .logo-text { display: none; }

            .toggle-sidebar {
                background: none;
                border: none;
                cursor: pointer;
                padding: 8px;
                color: #666;
                border-radius: 4px;
                transition: all 0.2s;
            }

            .toggle-sidebar:hover {
                background-color: #f5f5f5;
                color: #222;
            }

            .sidebar.collapsed .toggle-sidebar { transform: rotate(180deg); }

            .nav-section {
                flex: 1;
                padding: 20px 0;
                overflow-y: auto;
            }

            .nav-item {
                display: flex;
                align-items: center;
                padding: 12px 20px;
                color: #666;
                text-decoration: none;
                transition: all 0.2s;
                cursor: pointer;
                border-left: 3px solid transparent;
                gap: 12px;
                font-size: 15px;
            }

            .nav-item:hover {
                background-color: #f9f9f9;
                color: #222;
            }

            .nav-item.active {
                background-color: #f5f5f5;
                color: #222;
                border-left-color: #222;
                font-weight: 500;
            }

            .nav-item i {
                width: 20px;
                text-align: center;
            }

            .sidebar.collapsed .nav-item span { display: none; }
            .sidebar.collapsed .nav-item {
                justify-content: center;
                padding: 12px;
            }

            .user-info {
                padding: 20px;
                border-top: 1px solid #e0e0e0;
            }

            .user-details {
                display: flex;
                align-items: center;
                gap: 12px;
                margin-bottom: 16px;
            }

            .user-avatar {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                background: #222;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-weight: 600;
                font-size: 16px;
            }

            .user-email {
                flex: 1;
                font-size: 13px;
                color: #666;
                overflow: hidden;
            }

            .user-email strong {
                display: block;
                color: #222;
                font-size: 14px;
                margin-bottom: 2px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .sidebar.collapsed .user-email { display: none; }

            .logout-link {
                color: #d32f2f;
                text-decoration: none;
                padding: 10px 12px;
                border-radius: 6px;
                display: flex;
                align-items: center;
                gap: 10px;
                transition: all 0.2s;
                font-size: 14px;
                font-weight: 500;
            }

            .logout-link:hover { background-color: #ffebee; }
            .sidebar.collapsed .logout-link span { display: none; }
            .sidebar.collapsed .logout-link { justify-content: center; }

            .main-content {
                flex: 1;
                display: flex;
                overflow: hidden;
                background-color: #fafafa;
            }

            .content-area {
                flex: 1;
                padding: 40px 60px;
                overflow-y: auto;
            }

            .error-header {
                background: #fff;
                border: 1px solid #e0e0e0;
                border-radius: 4px;
                padding: 32px;
                margin-bottom: 24px;
            }

            .error-type {
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                color: #c5221f;
                font-weight: 600;
                margin-bottom: 12px;
            }

            h1 {
                font-family: 'Harding', Georgia, serif;
                font-size: 28px;
                font-weight: 700;
                line-height: 1.3;
                color: #222;
                margin-bottom: 24px;
            }

            .error-box {
                background: #fff;
                border: 1px solid #e0e0e0;
                border-left: 3px solid #c5221f;
                border-radius: 4px;
                padding: 24px;
                margin-bottom: 24px;
            }

            .error-title {
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                color: #c5221f;
                margin-bottom: 12px;
                font-weight: 600;
            }

            .error-message {
                color: #222;
                line-height: 1.6;
                font-size: 14px;
            }

            .actions {
                background: #fff;
                border: 1px solid #e0e0e0;
                border-radius: 4px;
                padding: 24px;
            }

            .btn {
                padding: 10px 20px;
                border: 1px solid #222;
                background: #222;
                color: #fff;
                text-decoration: none;
                font-size: 13px;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s;
                border-radius: 3px;
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }

            .btn:hover { background: #000; }
            .btn i { font-size: 12px; }
        </style>
    </head>
    <body>
    <?php include 'sidebar.php'; ?>
        <!-- <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <a href="index.php" class="logo">
                    <i class="fa-solid fa-envelope"></i>
                    <span class="logo-text">MailDash</span>
                </a>
                <button class="toggle-sidebar" id="toggleSidebar">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
            </div>

            <nav class="nav-section">
                <a href="index.php" class="nav-item">
                    <i class="fa-solid fa-paper-plane"></i>
                    <span>Compose</span>
                </a>
                <div class="nav-item active">
                    <i class="fa-solid fa-exclamation-triangle"></i>
                    <span>Error</span>
                </div>
                <a href="#" class="nav-item" id="settingsBtn">
                    <i class="fa-solid fa-gear"></i>
                    <span>Settings</span>
                </a>
            </nav>

            <div class="user-info">
                <div class="user-details">
                    <div class="user-avatar"><?= $userInitial ?></div>
                    <div class="user-email">
                        <strong>Account</strong>
                        <?= htmlspecialchars($userEmail) ?>
                    </div>
                </div>
                <a href="logout.php" class="logout-link">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div> -->

        <div class="main-content">
            <div class="content-area">
                <div class="error-header">
                    <div class="error-type">Error Report</div>
                    <h1>Email Transmission Failed</h1>
                </div>

                <div class="error-box">
                    <div class="error-title">Error Details</div>
                    <div class="error-message"><?= htmlspecialchars($errorMessage) ?></div>
                </div>

                <div class="actions">
                    <a href="index.php" class="btn">
                        <i class="fa-solid fa-arrow-left"></i>
                        Return to Compose
                    </a>
                </div>
            </div>
        </div>

        <script>
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('toggleSidebar');
            
            toggleBtn.addEventListener('click', () => {
                sidebar.classList.toggle('collapsed');
            });

            document.getElementById('settingsBtn').addEventListener('click', (e) => {
                e.preventDefault();
                window.location.href = 'index.php#settings';
            });
        </script>
    </body>
    </html>
    <?php
}
?>