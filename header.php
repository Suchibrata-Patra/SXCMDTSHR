<?php
declare(strict_types=1);
$page = basename($_SERVER['SCRIPT_NAME']);
$titles = [
    // Core Pages
    'index.php' => 'SXC MDTS | Dashboard',
    'inbox.php' => 'SXC MDTS | Inbox',
    'sent_history.php' => 'SXC MDTS | Sent History',
    'deleted_items.php' => 'SXC MDTS | Deleted Items',
    'preview.php' => 'SXC MDTS | Preview',
    'view_message.php' => 'SXC MDTS | View Message',
    'view_sent_email.php' => 'SXC MDTS | View Sent Email',

    // Authentication
    'login.php' => 'SXC MDTS | Login',
    'logout.php' => 'SXC MDTS | Logout',
    'login_auth_helper.php' => 'SXC MDTS | Auth Processor',

    // Mail Operations
    'send.php' => 'SXC MDTS | Compose Mail',
    'bulk_mail_backend.php' => 'SXC MDTS | Bulk Mail Backend',
    'bunch_mailer.php' => 'Group Mail',
    'process_bulk_mail.php' => 'SXC MDTS | Processing Bulk Mail',
    'process_deletion_queue.php' => 'SXC MDTS | Processing Deletion',
    'fetch_inbox_messages.php' => 'SXC MDTS | Fetch Inbox',
    'get_message.php' => 'SXC MDTS | Message Data',
    'delete_inbox_message.php' => 'SXC MDTS | Delete Message',
    'update_email_label.php' => 'SXC MDTS | Update Label',

    // Upload / Download
    'upload_handler.php' => 'SXC MDTS | Upload',
    'download.php' => 'SXC MDTS | Download',
    'download_file.php' => 'SXC MDTS | Download File',
    'download_attachment.php' => 'SXC MDTS | Download Attachment',
    'csv_upload_mapper.php' => 'SXC MDTS | CSV Upload Mapper',

    // IMAP & Settings
    'imap_helper.php' => 'SXC MDTS | IMAP Helper',
    'imap_settings_ui_component.php' => 'SXC MDTS | IMAP Settings',
    'settings.php' => 'SXC MDTS | Settings',
    'settings_helper.php' => 'SXC MDTS | Settings Helper',
    'save_settings.php' => 'SXC MDTS | Save Settings',
    'manage_labels.php' => 'SXC MDTS | Manage Labels',

    // Utilities
    'config.php' => 'SXC MDTS | Configuration',
    'db_config.php' => 'SXC MDTS | Database Configuration',
    'debug.php' => 'SXC MDTS | Debug',
    'diagnose.php' => 'SXC MDTS | Diagnose',
    'sidebar.php' => 'SXC MDTS | Sidebar',
    'header.php' => 'SXC MDTS | Header',

    // Special Files
    'mail_details.csv' => 'SXC MDTS | Mail Data',
    'README.md' => 'SXC MDTS | Documentation',

    // Temporary / Special
    '##bulk_actions.php' => 'SXC MDTS | Bulk Actions',
    '##draft.php' => 'SXC MDTS | Draft',
];
$title = $titles[$page] ?? 'SXC MDTS';
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), camera=()");
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/x-icon" href="https://hr.holidayseva.com/Assets/image/sxc_logo.png">
<title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inbox — SXC MDTS</title>
