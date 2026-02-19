<?php
session_start();
require 'vendor/autoload.php';
require 'config.php';
require 'db_config.php';

// ==================== Authentication ====================
$hasSessionAuth = isset($_SESSION['smtp_user']) && isset($_SESSION['smtp_pass']);
$hasEnvAuth     = !empty(env('SMTP_USERNAME')) && !empty(env('SMTP_PASSWORD'));

if (!$hasSessionAuth && !$hasEnvAuth) {
    header("Location: login.php");
    exit();
}

// Prefer ENV credentials over session for security
$smtpUser = env('SMTP_USERNAME') ?: ($_SESSION['smtp_user'] ?? '');
$smtpPass = env('SMTP_PASSWORD') ?: ($_SESSION['smtp_pass'] ?? '');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ==================== Database Connection ====================
$pdo = null;
try {
    if (function_exists('getDatabaseConnection')) {
        $pdo = getDatabaseConnection();
    } elseif (isset($GLOBALS['pdo'])) {
        $pdo = $GLOBALS['pdo'];
    } else {
        $dbHost = env('DB_HOST', 'localhost');
        $dbName = env('DB_NAME', '');
        $dbUser = env('DB_USER', '');
        $dbPass = env('DB_PASS', '');

        if ($dbName && $dbUser) {
            $pdo = new PDO(
                "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
                $dbUser,
                $dbPass,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        }
    }
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    $pdo = null;
}

// ==================== Helper Functions ====================

/**
 * Format bytes into a human-readable file size string.
 */
function formatFileSize(int $bytes, int $precision = 2): string
{
    if ($bytes <= 0) return "0 B";
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $power = min((int) floor(log($bytes, 1024)), count($units) - 1);
    return round($bytes / pow(1024, $power), $precision) . ' ' . $units[$power];
}

/**
 * Load template1.html and substitute all {{PLACEHOLDER}} variables with real values.
 * Signature rows are hidden (display:none) when their content is empty.
 */
function buildEmailFromTemplate(
    string $articleTitle,
    string $messageContent,
    string $signatureWish,
    string $signatureName,
    string $signatureDesignation,
    string $signatureExtra
): string {
    $templatePath = __DIR__ . '/template1.html';

    if (!file_exists($templatePath)) {
        throw new \RuntimeException("Email template not found: $templatePath");
    }

    $html = file_get_contents($templatePath);

    // Core content replacements
    $html = str_replace('{{DATE}}',        date('F j, Y'),                           $html);
    $html = str_replace('{{articletitle}}', htmlspecialchars($articleTitle, ENT_QUOTES, 'UTF-8'), $html);
    $html = str_replace('{{MESSAGE}}',     nl2br(htmlspecialchars($messageContent, ENT_QUOTES, 'UTF-8')), $html);

    // Signature replacements — blank fields become empty strings so the template
    // can conditionally show/hide them via inline style or simply render nothing.
    $html = str_replace('{{SIGNATURE_WISH}}',        htmlspecialchars($signatureWish,        ENT_QUOTES, 'UTF-8'), $html);
    $html = str_replace('{{SIGNATURE_NAME}}',        htmlspecialchars($signatureName,        ENT_QUOTES, 'UTF-8'), $html);
    $html = str_replace('{{SIGNATURE_DESIGNATION}}', htmlspecialchars($signatureDesignation, ENT_QUOTES, 'UTF-8'), $html);
    $html = str_replace('{{SIGNATURE_EXTRA}}',       htmlspecialchars($signatureExtra,       ENT_QUOTES, 'UTF-8'), $html);

    return $html;
}

/**
 * Save a sent email record to the database and return the new row ID.
 */
function saveSentEmailToDatabase(?PDO $pdo, array $emailData): ?int
{
    if ($pdo === null) {
        error_log("saveSentEmailToDatabase: no database connection.");
        return null;
    }

    try {
        $sql = "INSERT INTO sent_emails_new
                    (email_uuid, user_email, from_email, from_name, recipient_email,
                     cc_emails, bcc_emails, reply_to, subject, article_title,
                     body_text, body_html, label_id, label_name, label_color,
                     has_attachments, email_type, sent_at, created_at, updated_at)
                VALUES
                    (:email_uuid, :user_email, :from_email, :from_name, :recipient_email,
                     :cc_emails, :bcc_emails, :reply_to, :subject, :article_title,
                     :body_text, :body_html, :label_id, :label_name, :label_color,
                     :has_attachments, :email_type, NOW(), NOW(), NOW())";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':email_uuid'      => $emailData['email_uuid'],
            ':user_email'      => $emailData['user_email'],
            ':from_email'      => $emailData['from_email'],
            ':from_name'       => $emailData['from_name'],
            ':recipient_email' => $emailData['recipient_email'],
            ':cc_emails'       => $emailData['cc_emails']       ?? null,
            ':bcc_emails'      => $emailData['bcc_emails']      ?? null,
            ':reply_to'        => $emailData['reply_to']        ?? null,
            ':subject'         => $emailData['subject'],
            ':article_title'   => $emailData['article_title']   ?? null,
            ':body_text'       => $emailData['body_text']       ?? null,
            ':body_html'       => $emailData['body_html']       ?? null,
            ':label_id'        => $emailData['label_id']        ?? null,
            ':label_name'      => $emailData['label_name']      ?? null,
            ':label_color'     => $emailData['label_color']     ?? null,
            ':has_attachments' => $emailData['has_attachments'] ?? 0,
            ':email_type'      => $emailData['email_type']      ?? 'sent',
        ]);

        $emailId = (int) $pdo->lastInsertId();
        error_log($emailId ? "✓ Email saved to DB (ID: $emailId)" : "✗ Failed to save email to DB");

        return $emailId ?: null;

    } catch (PDOException $e) {
        error_log("Error saving sent email: " . $e->getMessage());
        error_log("SQL Error Details: " . print_r($e->errorInfo, true));
        return null;
    }
}

/**
 * Link uploaded attachment records to a saved sent-email row.
 */
function linkAttachmentsToSentEmail(PDO $pdo, int $emailId, string $emailUuid, array $attachmentsData): bool
{
    if ($emailId <= 0 || empty($emailUuid) || empty($attachmentsData)) {
        return false;
    }

    $sql = "INSERT INTO sent_email_attachments_new
                (sent_email_id, email_uuid, original_filename, stored_filename, file_path,
                 file_size, mime_type, file_extension, upload_session_id, uploaded_at)
            VALUES
                (:sent_email_id, :email_uuid, :original_filename, :stored_filename, :file_path,
                 :file_size, :mime_type, :file_extension, :upload_session_id, :uploaded_at)";

    $stmt = $pdo->prepare($sql);

    foreach ($attachmentsData as $attachment) {
        try {
            $stmt->execute([
                ':sent_email_id'     => $emailId,
                ':email_uuid'        => $emailUuid,
                ':original_filename' => $attachment['original_name'] ?? 'unknown',
                ':stored_filename'   => $attachment['path']          ?? '',
                ':file_path'         => 'uploads/attachments/' . ($attachment['path'] ?? ''),
                ':file_size'         => $attachment['file_size']     ?? 0,
                ':mime_type'         => $attachment['mime_type']     ?? 'application/octet-stream',
                ':file_extension'    => $attachment['extension']     ?? '',
                ':upload_session_id' => $attachment['id']            ?? null,
                ':uploaded_at'       => date('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {
            error_log("Error saving attachment to database: " . $e->getMessage());
        }
    }

    return true;
}

// ==================== Main POST Handler ====================
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $mail = new PHPMailer(true);

    try {
        // ---------- SMTP Configuration ----------
        $mail->isSMTP();
        $mail->SMTPDebug = 0;

        $settings = $_SESSION['user_settings'] ?? [];

        $mail->Host       = env('SMTP_HOST');
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUser;
        $mail->Password   = $smtpPass;
        $mail->Port       = (int) env('SMTP_PORT');

        $encryption       = env('SMTP_ENCRYPTION');
        $mail->SMTPSecure = ($encryption === 'tls')
            ? PHPMailer::ENCRYPTION_STARTTLS
            : PHPMailer::ENCRYPTION_SMTPS;

        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];

        $displayName = !empty($settings['display_name']) ? $settings['display_name'] : env('FROM_NAME');
        $fromEmail   = env('FROM_EMAIL') ?: $smtpUser;
        $mail->setFrom($fromEmail, $displayName);

        // ---------- Capture & Sanitise Form Fields ----------
        $recipient   = filter_var(trim($_POST['email']        ?? ''), FILTER_SANITIZE_EMAIL);
        $subject     = trim($_POST['subject']                 ?? 'Official Communication');
        $articleTitle   = trim($_POST['articletitle']         ?? 'Official Communication');
        $messageContent = $_POST['message']                   ?? '';

        $ccEmails  = !empty($_POST['ccEmails'])  ? array_map('trim', explode(',', $_POST['ccEmails']))  : [];
        $bccEmails = !empty($_POST['bccEmails']) ? array_map('trim', explode(',', $_POST['bccEmails'])) : [];

        $signatureWish        = trim($_POST['signatureWish']        ?? '');
        $signatureName        = trim($_POST['signatureName']        ?? '');
        $signatureDesignation = trim($_POST['signatureDesignation'] ?? '');
        $signatureExtra       = trim($_POST['signatureExtra']       ?? '');

        $labelId    = !empty($_POST['label_id'])    ? intval($_POST['label_id'])          : null;
        $labelName  = !empty($_POST['label_name'])  ? trim($_POST['label_name'])          : null;
        $labelColor = !empty($_POST['label_color']) ? trim($_POST['label_color'])         : null;

        $attachmentIds = !empty($_POST['attachment_ids']) ? explode(',', $_POST['attachment_ids']) : [];

        // ---------- Validation ----------
        if (!$recipient || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid or missing recipient email address.");
        }
        if (empty($subject)) {
            throw new Exception("Subject is required.");
        }
        if (empty($articleTitle)) {
            throw new Exception("Article title is required.");
        }
        if (empty($messageContent)) {
            throw new Exception("Message content is required.");
        }

        // ---------- Recipients ----------
        $mail->addAddress($recipient);

        $validCCs = [];
        foreach ($ccEmails as $cc) {
            $cc = filter_var($cc, FILTER_SANITIZE_EMAIL);
            if ($cc && filter_var($cc, FILTER_VALIDATE_EMAIL)) {
                $mail->addCC($cc);
                $validCCs[] = $cc;
            }
        }

        $validBCCs = [];
        foreach ($bccEmails as $bcc) {
            $bcc = filter_var($bcc, FILTER_SANITIZE_EMAIL);
            if ($bcc && filter_var($bcc, FILTER_VALIDATE_EMAIL)) {
                $mail->addBCC($bcc);
                $validBCCs[] = $bcc;
            }
        }

        // ---------- Attachments ----------
        $attachments       = [];
        $attachmentIdsForDB = [];
        $uploadDir         = env('UPLOAD_ATTACHMENTS_DIR', 'uploads/attachments/');

        error_log("Processing attachments - IDs received: " . implode(',', $attachmentIds));

        if (!empty($attachmentIds) && isset($_SESSION['temp_attachments'])) {
            $sessionAttachments = $_SESSION['temp_attachments'];

            foreach ($attachmentIds as $attachmentId) {
                $attachmentId = trim($attachmentId);

                foreach ($sessionAttachments as $attachment) {
                    if (isset($attachment['id']) && $attachment['id'] == $attachmentId) {
                        $relativePath = $attachment['path']          ?? '';
                        $fullFilePath = $uploadDir . $relativePath;
                        $fileName     = $attachment['original_name'] ?? 'attachment';

                        if (!file_exists($fullFilePath)) {
                            error_log("✗ Attachment file not found: $fullFilePath");
                            throw new Exception("Attachment file not found: $fileName");
                        }

                        $mail->addAttachment($fullFilePath, $fileName);

                        $attachments[] = [
                            'id'            => $attachmentId,
                            'original_name' => $fileName,
                            'path'          => $relativePath,
                            'file_size'     => $attachment['file_size'] ?? 0,
                            'extension'     => $attachment['extension'] ?? 'file',
                            'mime_type'     => $attachment['mime_type'] ?? 'application/octet-stream',
                            'size_formatted'=> formatFileSize($attachment['file_size'] ?? 0),
                        ];

                        $attachmentIdsForDB[] = $attachmentId;
                        error_log("✓ Attached: $fullFilePath as $fileName");
                        break;
                    }
                }
            }
        }

        // ---------- Build Email Body from template1.html ----------
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;

        $htmlMessage   = buildEmailFromTemplate(
            $articleTitle,
            $messageContent,
            $signatureWish,
            $signatureName,
            $signatureDesignation,
            $signatureExtra
        );

        $mail->Body    = $htmlMessage;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>','<br />'], "\n", $messageContent));

        // ---------- Send ----------
        $mail->send();

        // ---------- Save to Database ----------
        $emailUuid = uniqid('email_', true);

        $emailData = [
            'email_uuid'      => $emailUuid,
            'user_email'      => $smtpUser,
            'from_email'      => $fromEmail,
            'from_name'       => $displayName,
            'recipient_email' => $recipient,
            'cc_emails'       => !empty($validCCs)  ? implode(',', $validCCs)  : null,
            'bcc_emails'      => !empty($validBCCs) ? implode(',', $validBCCs) : null,
            'reply_to'        => $fromEmail,
            'subject'         => $subject,
            'article_title'   => $articleTitle,
            'body_text'       => strip_tags($messageContent),
            'body_html'       => $htmlMessage,
            'label_id'        => $labelId,
            'label_name'      => $labelName,
            'label_color'     => $labelColor,
            'has_attachments' => !empty($attachments) ? 1 : 0,
            'email_type'      => 'sent',
        ];

        $savedEmailId = saveSentEmailToDatabase($pdo, $emailData);

        if ($savedEmailId && !empty($attachments)) {
            linkAttachmentsToSentEmail($pdo, $savedEmailId, $emailUuid, $attachments);
        }

        // ---------- Success Page ----------
        $allEmails = array_merge([$recipient], $validCCs, $validBCCs);
        $summary   = [
            'subject'          => $subject,
            'article_title'    => $articleTitle,
            'sent_at'          => date('F j, Y g:i A'),
            'cc_count'         => count($validCCs),
            'bcc_count'        => count($validBCCs),
            'attachment_count' => count($attachments),
        ];

        showSuccessPage($allEmails, $summary, $attachments);

    } catch (Exception $e) {
        error_log("Email sending error: " . $e->getMessage());
        showErrorPage($e->getMessage());
    }
}

// ==================== Success Page ====================
function showSuccessPage(array $emails, array $summary, array $attachments): void
{
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php
        define('PAGE_TITLE', 'SXC MDTS | Email Delivered');
        include 'header.php';
    ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <style>
        :root {
            --ink:        #1a1a2e;
            --ink-2:      #2d2d44;
            --ink-3:      #6b6b8a;
            --ink-4:      #a8a8c0;
            --bg:         #f0f0f7;
            --surface:    #ffffff;
            --surface-2:  #f7f7fc;
            --border:     rgba(100,100,160,0.12);
            --border-2:   rgba(100,100,160,0.22);
            --blue:       #5781a9;
            --blue-2:     #c6d3ea;
            --blue-glow:  rgba(79,70,229,0.15);
            --green:      #10b981;
            --green-2:    #d1fae5;
            --green-glow: rgba(16,185,129,0.15);
            --amber:      #f59e0b;
            --purple:     #8b5cf6;
            --r:          10px;
            --r-lg:       16px;
            --shadow:     0 1px 3px rgba(79,70,229,0.08), 0 4px 16px rgba(79,70,229,0.06);
            --shadow-lg:  0 8px 32px rgba(79,70,229,0.14), 0 2px 8px rgba(0,0,0,0.06);
            --ease:       cubic-bezier(.4,0,.2,1);
            --ease-spring:cubic-bezier(.34,1.56,.64,1);
        }

        *,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', -apple-system, sans-serif;
            background: var(--bg);
            color: var(--ink);
            -webkit-font-smoothing: antialiased;
            display: flex;
            min-height: 100vh;
        }

        .app-container { display: flex; width: 100%; min-height: 100vh; }

        .main-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .delivery-wrapper {
            max-width: 800px;
            width: 100%;
            animation: fadeInUp .6s var(--ease) both;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: none; }
        }

        /* Success Header */
        .success-header { text-align: center; margin-bottom: 40px; }

        .success-icon-wrapper {
            width: 96px; height: 96px;
            background: linear-gradient(135deg, var(--green) 0%, #059669 100%);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 24px;
            box-shadow: 0 10px 40px var(--green-glow), 0 4px 16px rgba(16,185,129,0.25);
            animation: scaleIn .5s var(--ease-spring) both;
            animation-delay: .1s;
        }

        @keyframes scaleIn {
            from { transform: scale(0); }
            to   { transform: scale(1); }
        }

        .success-icon-wrapper .material-icons-round { font-size: 56px; color: white; }

        .success-title {
            font-size: 32px; font-weight: 700;
            color: var(--ink); margin-bottom: 8px; letter-spacing: -0.5px;
        }

        .success-subtitle { font-size: 16px; color: var(--ink-3); font-weight: 400; }

        .status-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 16px;
            background: var(--green-2);
            border: 1.5px solid rgba(16,185,129,0.3);
            border-radius: 20px; margin: 16px auto 0;
            font-size: 13px; font-weight: 600; color: var(--green);
        }

        /* Card */
        .delivery-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            padding: 28px;
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            animation: fadeInUp .6s var(--ease) both;
            animation-delay: .15s;
        }

        .card-header {
            display: flex; align-items: center; gap: 12px;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }

        .card-icon {
            width: 40px; height: 40px;
            background: var(--blue-glow);
            border-radius: var(--r);
            display: flex; align-items: center; justify-content: center;
            color: var(--blue);
        }

        .card-icon .material-icons-round { font-size: 22px; }

        .card-title { font-size: 16px; font-weight: 600; color: var(--ink-2); }

        /* Summary Grid */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .summary-item label {
            display: flex; align-items: center; gap: 6px;
            font-size: 11px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.5px;
            color: var(--ink-4); margin-bottom: 6px;
        }

        .summary-item label .material-icons-round { font-size: 14px; }

        .summary-item .value {
            font-size: 15px; font-weight: 500; color: var(--ink-2);
        }

        .value-mono { font-family: 'DM Mono', monospace; font-size: 13px !important; }

        /* Recipients */
        .recipients-list { display: flex; flex-direction: column; gap: 10px; }

        .recipient-item {
            display: flex; align-items: center; gap: 12px;
            padding: 12px 14px;
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: var(--r);
        }

        .recipient-icon {
            width: 36px; height: 36px;
            background: var(--blue-glow);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: var(--blue); flex-shrink: 0;
        }

        .recipient-icon .material-icons-round { font-size: 18px; }

        .recipient-info { flex: 1; min-width: 0; }

        .recipient-email {
            font-size: 14px; font-weight: 500;
            color: var(--ink-2); white-space: nowrap;
            overflow: hidden; text-overflow: ellipsis;
        }

        .recipient-type { font-size: 12px; color: var(--ink-4); margin-top: 2px; }

        .recipient-badge {
            padding: 3px 10px; border-radius: 12px;
            font-size: 11px; font-weight: 600;
            flex-shrink: 0;
        }

        .recipient-badge.primary { background: var(--green-2); color: var(--green); }
        .recipient-badge.cc      { background: #ede9fe; color: var(--purple); }
        .recipient-badge.bcc     { background: #fef3c7; color: var(--amber); }

        /* Attachments */
        .attachments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 12px;
        }

        .attachment-card {
            padding: 16px;
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: var(--r);
            text-align: center;
        }

        .attachment-icon {
            width: 44px; height: 44px;
            background: var(--blue-glow);
            border-radius: var(--r);
            display: flex; align-items: center; justify-content: center;
            color: var(--blue); margin: 0 auto 10px;
        }

        .attachment-icon .material-icons-round { font-size: 22px; }
        .attachment-name { font-size: 13px; font-weight: 500; color: var(--ink-2); word-break: break-all; }
        .attachment-size { font-size: 12px; color: var(--ink-4); margin-top: 4px; }

        /* Action Buttons */
        .action-buttons {
            display: flex; gap: 12px; margin-top: 8px;
        }

        .btn {
            flex: 1; height: 44px; padding: 0 24px;
            border: none; border-radius: var(--r);
            font-family: inherit; font-size: 14px; font-weight: 600;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            text-decoration: none;
            transition: all .18s var(--ease);
        }

        .btn-primary  { background: var(--blue); color: white; }
        .btn-primary:hover  { background: #4a6f94; box-shadow: 0 4px 12px rgba(87,129,169,0.3); transform: translateY(-1px); }

        .btn-secondary { background: var(--surface-2); color: var(--ink-2); border: 1.5px solid var(--border-2); }
        .btn-secondary:hover { background: var(--blue-glow); border-color: var(--blue); color: var(--blue); }

        .btn:active { transform: translateY(0) scale(.98); }
        .btn .material-icons-round { font-size: 20px; }

        @media (max-width: 768px) {
            .summary-grid     { grid-template-columns: 1fr; }
            .action-buttons   { flex-direction: column; }
            .attachments-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="delivery-wrapper">

            <!-- Success Header -->
            <div class="success-header">
                <div class="success-icon-wrapper">
                    <span class="material-icons-round">check_circle</span>
                </div>
                <h1 class="success-title">Email Delivered Successfully</h1>
                <p class="success-subtitle">Your message has been sent to all recipients</p>
                <div class="status-badge">
                    <span class="material-icons-round">done_all</span>
                    Delivered at <?= date('g:i A') ?>
                </div>
            </div>

            <!-- Email Details Card -->
            <div class="delivery-card">
                <div class="card-header">
                    <div class="card-icon"><span class="material-icons-round">mail</span></div>
                    <h2 class="card-title">Email Details</h2>
                </div>

                <div class="summary-grid">
                    <div class="summary-item">
                        <label><span class="material-icons-round">subject</span> Subject</label>
                        <div class="value"><?= htmlspecialchars($summary['subject']) ?></div>
                    </div>
                    <div class="summary-item">
                        <label><span class="material-icons-round">article</span> Article Title</label>
                        <div class="value"><?= htmlspecialchars($summary['article_title']) ?></div>
                    </div>
                    <div class="summary-item">
                        <label><span class="material-icons-round">schedule</span> Sent At</label>
                        <div class="value value-mono"><?= htmlspecialchars($summary['sent_at']) ?></div>
                    </div>
                    <div class="summary-item">
                        <label><span class="material-icons-round">people</span> Total Recipients</label>
                        <div class="value value-mono">
                            <?= count($emails) ?>
                            <?php if ($summary['cc_count'] > 0 || $summary['bcc_count'] > 0): ?>
                                <span style="font-size:12px;color:var(--ink-3);font-weight:500;">
                                    (<?= $summary['cc_count'] ?> CC, <?= $summary['bcc_count'] ?> BCC)
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($summary['attachment_count'] > 0): ?>
                    <div class="summary-item">
                        <label><span class="material-icons-round">attach_file</span> Attachments</label>
                        <div class="value value-mono"><?= $summary['attachment_count'] ?> file(s)</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recipients Card -->
            <div class="delivery-card">
                <div class="card-header">
                    <div class="card-icon"><span class="material-icons-round">group</span></div>
                    <h2 class="card-title">Recipients (<?= count($emails) ?>)</h2>
                </div>

                <div class="recipients-list">
                    <?php
                    $recipientIndex = 0;
                    foreach ($emails as $email):
                        if ($recipientIndex === 0) {
                            $type = 'primary'; $typeName = 'To';
                        } elseif ($recipientIndex <= $summary['cc_count']) {
                            $type = 'cc'; $typeName = 'CC';
                        } else {
                            $type = 'bcc'; $typeName = 'BCC';
                        }
                        $recipientIndex++;
                    ?>
                    <div class="recipient-item">
                        <div class="recipient-icon"><span class="material-icons-round">person</span></div>
                        <div class="recipient-info">
                            <div class="recipient-email"><?= htmlspecialchars($email) ?></div>
                            <div class="recipient-type"><?= $typeName ?></div>
                        </div>
                        <span class="recipient-badge <?= $type ?>"><?= $typeName ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Attachments Card -->
            <?php if (!empty($attachments)): ?>
            <div class="delivery-card">
                <div class="card-header">
                    <div class="card-icon"><span class="material-icons-round">attach_file</span></div>
                    <h2 class="card-title">Attachments (<?= count($attachments) ?>)</h2>
                </div>
                <div class="attachments-grid">
                    <?php foreach ($attachments as $attachment): ?>
                    <div class="attachment-card">
                        <div class="attachment-icon"><span class="material-icons-round">insert_drive_file</span></div>
                        <div class="attachment-name"><?= htmlspecialchars($attachment['original_name']) ?></div>
                        <div class="attachment-size"><?= htmlspecialchars($attachment['size_formatted']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="index.php" class="btn btn-primary">
                    <span class="material-icons-round">edit</span>
                    Compose New Email
                </a>
                <a href="sent_history.php" class="btn btn-secondary">
                    <span class="material-icons-round">history</span>
                    View Sent History
                </a>
            </div>

        </div>
    </div>
</div>
</body>
</html>
<?php
}

// ==================== Error Page ====================
function showErrorPage(string $errorMessage): void
{
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php
        define('PAGE_TITLE', 'SXC MDTS | Send Error');
        include 'header.php';
    ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <style>
        :root {
            --ink:      #1a1a2e;
            --ink-2:    #2d2d44;
            --ink-3:    #6b6b8a;
            --bg:       #f0f0f7;
            --surface:  #ffffff;
            --border:   rgba(100,100,160,0.12);
            --blue:     #5781a9;
            --red:      #ef4444;
            --red-2:    #fecaca;
            --red-glow: rgba(239,68,68,0.15);
            --r:        10px;
            --r-lg:     16px;
            --shadow:   0 1px 3px rgba(79,70,229,0.08), 0 4px 16px rgba(79,70,229,0.06);
            --ease:     cubic-bezier(.4,0,.2,1);
            --ease-spring: cubic-bezier(.34,1.56,.64,1);
        }

        *,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', -apple-system, sans-serif;
            background: var(--bg); color: var(--ink);
            -webkit-font-smoothing: antialiased;
            display: flex; min-height: 100vh;
        }

        .app-container { display: flex; width: 100%; min-height: 100vh; }

        .main-content {
            flex: 1; display: flex;
            align-items: center; justify-content: center;
            padding: 40px 20px;
        }

        .error-wrapper {
            max-width: 600px; width: 100%;
            animation: fadeInUp .6s var(--ease) both;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: none; }
        }

        .error-header { text-align: center; margin-bottom: 40px; }

        .error-icon-wrapper {
            width: 96px; height: 96px;
            background: linear-gradient(135deg, var(--red) 0%, #dc2626 100%);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 24px;
            box-shadow: 0 10px 40px var(--red-glow), 0 4px 16px rgba(239,68,68,0.25);
            animation: scaleIn .5s var(--ease-spring) both;
            animation-delay: .1s;
        }

        @keyframes scaleIn {
            from { transform: scale(0); }
            to   { transform: scale(1); }
        }

        .error-icon-wrapper .material-icons-round { font-size: 56px; color: white; }

        .error-title { font-size: 32px; font-weight: 700; color: var(--ink); margin-bottom: 8px; letter-spacing: -0.5px; }
        .error-subtitle { font-size: 16px; color: var(--ink-3); font-weight: 400; }

        .error-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            padding: 28px;
            box-shadow: var(--shadow);
            animation: fadeInUp .6s var(--ease) both;
            animation-delay: .1s;
        }

        .error-message-box {
            background: var(--red-2);
            border: 1.5px solid rgba(239,68,68,0.3);
            border-left: 4px solid var(--red);
            padding: 20px; border-radius: var(--r);
            margin-bottom: 24px;
        }

        .error-message-box strong {
            display: flex; align-items: center; gap: 8px;
            margin-bottom: 12px; color: #991b1b;
            font-size: 14px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.5px;
        }

        .error-message-box strong .material-icons-round { font-size: 18px; }
        .error-message-box p { color: #7f1d1d; line-height: 1.6; font-size: 14px; font-weight: 500; }

        .btn {
            width: 100%; height: 44px; padding: 0 24px;
            border: none; border-radius: var(--r);
            font-family: inherit; font-size: 14px; font-weight: 600;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            text-decoration: none;
            transition: all .18s var(--ease);
            background: var(--blue); color: white;
        }

        .btn:hover { background: #4a6f94; box-shadow: 0 4px 12px rgba(87,129,169,0.3); transform: translateY(-1px); }
        .btn:active { transform: translateY(0) scale(.98); }
        .btn .material-icons-round { font-size: 20px; }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="error-wrapper">
            <div class="error-header">
                <div class="error-icon-wrapper">
                    <span class="material-icons-round">error</span>
                </div>
                <h1 class="error-title">Email Sending Failed</h1>
                <p class="error-subtitle">We encountered a problem while sending your email</p>
            </div>

            <div class="error-card">
                <div class="error-message-box">
                    <strong>
                        <span class="material-icons-round">info</span>
                        Error Details
                    </strong>
                    <p><?= htmlspecialchars($errorMessage) ?></p>
                </div>

                <a href="index.php" class="btn">
                    <span class="material-icons-round">refresh</span>
                    Try Again
                </a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
<?php
}