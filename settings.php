<?php
session_start();
if (!isset($_SESSION['smtp_user']) || !isset($_SESSION['smtp_pass'])) {
    header("Location: login.php");
    exit();
}

require_once 'db_config.php';

$userEmail = $_SESSION['smtp_user'];

// Load user settings from database
function getUserSettings($email) {
    $pdo = getDatabaseConnection();
    if (!$pdo) return [];
    
    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM user_settings WHERE user_email = :email");
        $stmt->execute([':email' => $email]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $settings = [];
        foreach ($results as $row) {
            $value = $row['setting_value'];
            // Convert string booleans to actual booleans
            if ($value === 'true') $value = true;
            if ($value === 'false') $value = false;
            $settings[$row['setting_key']] = $value;
        }
        return $settings;
    } catch (PDOException $e) {
        error_log("Error loading settings: " . $e->getMessage());
        return [];
    }
}

$userSettings = getUserSettings($userEmail);

// Default settings
$defaults = [
    // Identity & Authority
    'display_name' => '',
    'designation' => '',
    'dept' => 'CS',
    'hod_email' => '',
    'staff_id' => '',
    'room_no' => '',
    'ext_no' => '',
    
    // Automation & Compliance
    'auto_bcc_hod' => true,
    'archive_sent' => true,
    'read_receipts' => false,
    'delayed_send' => '0',
    'attach_size_limit' => '25',
    'auto_label_sent' => true,
    'priority_level' => 'normal',
    'mandatory_subject' => true,
    
    // Editor & Composition
    'font_family' => 'Inter',
    'font_size' => '14',
    'spell_check' => true,
    'auto_correct' => true,
    'smart_reply' => false,
    'rich_text' => true,
    'default_cc' => '',
    'default_bcc' => '',
    'undo_send_delay' => '10',
    'signature' => '',
    
    // Interface Personalization
    'sidebar_color' => 'white',
    'compact_mode' => false,
    'dark_mode' => 'auto',
    'show_avatars' => true,
    'anim_speed' => 'normal',
    'blur_effects' => true,
    'density' => 'relaxed',
    'font_weight' => 'medium',
    
    // Notifications & Security
    'push_notif' => true,
    'sound_alerts' => 'tink',
    'browser_notif' => true,
    'two_factor' => false,
    'session_timeout' => '60',
    'ip_lock' => false,
    'debug_logs' => false,
    'activity_report' => 'weekly',
    
    // IMAP Configuration
    'imap_server' => 'imap.hostinger.com',
    'imap_port' => '993',
    'imap_encryption' => 'ssl',
    'imap_username' => '',
    'settings_locked' => false
];

$s = array_merge($defaults, $userSettings);

// Resolve lock state once at the top — used by both HTML and inline JS
$isLocked = isset($s['settings_locked']) && 
            ($s['settings_locked'] === true || $s['settings_locked'] === 'true' || $s['settings_locked'] === '1');
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
            --glass: rgba(255, 255, 255, 0.7);
            --border: #E5E5EA;
            --success-green: #34C759;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background-color: var(--apple-bg);
            display: flex;
            height: 100vh;
            overflow: hidden;
            color: #1c1c1e;
            -webkit-font-smoothing: antialiased;
        }

        /* ========== SIDEBAR NAVIGATION ========== */
        .settings-nav {
            width: 240px;
            background: var(--glass);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-right: 1px solid var(--border);
            padding: 40px 10px;
            display: flex;
            flex-direction: column;
            gap: 5px;
            overflow-y: auto;
        }

        .nav-group-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--apple-gray);
            padding: 10px 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 15px;
            border-radius: 8px;
            text-decoration: none;
            color: #1c1c1e;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
        }

        .nav-link:hover {
            background: rgba(0,0,0,0.05);
        }

        .nav-link.active {
            background: var(--apple-blue);
            color: white;
        }

        .nav-link .material-icons {
            font-size: 18px;
        }

        /* Back to App Link */
        .back-link {
            margin-bottom: 20px;
            padding: 8px 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: var(--apple-blue);
            font-size: 13px;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .back-link:hover {
            background: rgba(0, 122, 255, 0.1);
        }

        /* ========== CONTENT AREA ========== */
        .settings-content {
            flex: 1;
            padding: 60px 80px 100px;
            overflow-y: auto;
            scroll-behavior: smooth;
        }

        .settings-header {
            margin-bottom: 40px;
        }

        .settings-header h1 {
            font-size: 32px;
            font-weight: 600;
            letter-spacing: -0.5px;
            margin-bottom: 8px;
        }

        .settings-header p {
            font-size: 15px;
            color: var(--apple-gray);
        }

        h2 {
            font-size: 22px;
            font-weight: 600;
            margin: 40px 0 20px;
            letter-spacing: -0.3px;
        }

        h2:first-of-type {
            margin-top: 0;
        }

        .section-card {
            background: white;
            border-radius: 12px;
            margin-bottom: 30px;
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .setting-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
            min-height: 60px;
        }

        .setting-row:last-child {
            border-bottom: none;
        }

        .setting-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex: 1;
        }

        .setting-title {
            font-size: 14px;
            font-weight: 500;
            color: #1c1c1e;
        }

        .setting-desc {
            font-size: 12px;
            color: var(--apple-gray);
            line-height: 1.4;
        }

        /* ========== APPLE TOGGLE SWITCH ========== */
        .switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
            flex-shrink: 0;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #D1D1D6;
            transition: .3s;
            border-radius: 24px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 2px;
            bottom: 2px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        input:checked + .slider {
            background-color: var(--success-green);
        }

        input:checked + .slider:before {
            transform: translateX(20px);
        }

        /* ========== INPUTS & SELECTS ========== */
        input[type="text"],
        input[type="email"],
        input[type="number"],
        select,
        textarea {
            border: none;
            background: #F2F2F7;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-family: 'Inter', sans-serif;
            color: #1c1c1e;
            transition: all 0.2s;
        }

        input[type="text"],
        input[type="email"],
        select {
            text-align: right;
            min-width: 200px;
        }

        input[type="number"] {
            text-align: right;
            width: 100px;
        }

        textarea {
            width: 100%;
            min-height: 100px;
            resize: vertical;
            text-align: left;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="number"]:focus,
        select:focus,
        textarea:focus {
            outline: none;
            background: white;
            box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
        }

        /* Full Width Input Rows */
        .setting-row.full-width {
            flex-direction: column;
            align-items: stretch;
            gap: 12px;
        }

        .setting-row.full-width .setting-info {
            margin-bottom: 8px;
        }

        /* ========== SAVE BUTTON ========== */
        .btn-deploy {
            position: fixed;
            bottom: 30px;
            left: 30px;
            background: var(--apple-blue);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            /* box-shadow: 0 4px 16px rgba(0,122,255,0.3); */
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            z-index: 1000;
        }

        .btn-deploy:hover {
            background: #0051D5;
            box-shadow: 0 6px 20px rgba(0,122,255,0.4);
            transform: translateY(-1px);
        }

        .btn-deploy:active {
            transform: translateY(0);
        }

        .btn-deploy .material-icons {
            font-size: 20px;
        }

        /* ========== SUCCESS MESSAGE ========== */
        .success-toast {
            position: fixed;
            top: 30px;
            right: 30px;
            background: white;
            color: #1c1c1e;
            padding: 16px 24px;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
            display: none;
            align-items: center;
            gap: 12px;
            z-index: 1001;
            border: 1px solid var(--border);
        }

        .success-toast.show {
            display: flex;
            animation: slideIn 0.3s ease-out;
        }

        .success-toast .material-icons {
            color: var(--success-green);
            font-size: 24px;
        }

        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* ========== RESPONSIVE ========== */
        @media (max-width: 1200px) {
            .settings-content {
                padding: 40px 50px 100px;
            }
        }

        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }

            .settings-nav {
                width: 100%;
                padding: 20px 10px;
                border-right: none;
                border-bottom: 1px solid var(--border);
            }

            .settings-content {
                padding: 30px 20px 100px;
            }

            .setting-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            input[type="text"],
            input[type="email"],
            select {
                text-align: left;
                width: 100%;
            }
        }

        /* ========== SCROLLBAR ========== */
        .settings-content::-webkit-scrollbar,
        .settings-nav::-webkit-scrollbar {
            width: 6px;
        }

        .settings-content::-webkit-scrollbar-track,
        .settings-nav::-webkit-scrollbar-track {
            background: transparent;
        }

        .settings-content::-webkit-scrollbar-thumb,
        .settings-nav::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }

        .settings-content::-webkit-scrollbar-thumb:hover,
        .settings-nav::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body>
    <!-- Sidebar Navigation -->
    <div class="settings-nav">
        <a href="index.php" class="back-link">
            <span class="material-icons">arrow_back</span>
            Back to App
        </a>

        <div class="nav-group-label">Institutional</div>
        <a href="#identity" class="nav-link active">
            <span class="material-icons">person</span>
            Identity
        </a>
        <a href="#compliance" class="nav-link">
            <span class="material-icons">gavel</span>
            Compliance
        </a>
        <a href="#imap" class="nav-link">
            <span class="material-icons">mail</span>
            Mail Server
        </a>
        
        <div class="nav-group-label">Application</div>
        <a href="#composition" class="nav-link">
            <span class="material-icons">edit</span>
            Composition
        </a>
        <a href="#appearance" class="nav-link">
            <span class="material-icons">palette</span>
            UI & UX
        </a>
        <a href="#notifications" class="nav-link">
            <span class="material-icons">notifications</span>
            Notifications
        </a>
        <a href="#security" class="nav-link">
            <span class="material-icons">shield</span>
            Security
        </a>
    </div>

    <!-- Main Content -->
    <main class="settings-content">
        <div class="settings-header">
            <h1>Settings</h1>
            <p>Customize your email experience • <?= htmlspecialchars($userEmail) ?></p>
        </div>

        <form id="appleSettingsForm" method="post" action="save_settings.php">
            <!-- IDENTITY SECTION -->
            <h2 id="identity">Official Identity</h2>
            <div class="section-card">
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Display Name</span>
                        <span class="setting-desc">How your name appears in the 'From' field</span>
                    </div>
                    <input type="text" name="display_name" value="<?= htmlspecialchars($s['display_name']) ?>" placeholder="Your Name">
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Official Designation</span>
                        <span class="setting-desc">Your title for institutional signatures</span>
                    </div>
                    <input type="text" name="designation" value="<?= htmlspecialchars($s['designation']) ?>" placeholder="Professor">
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Department</span>
                        <span class="setting-desc">Your academic/administrative department</span>
                    </div>
                    <select name="dept">
                        <option value="statistics" <?= $s['dept']=='statistics'?'selected':'' ?>>Statistics</option>
                        <option value="data_science" <?= $s['dept']=='data_science'?'selected':'' ?>>Data Science</option>
                        <option value="CS" <?= $s['dept']=='CS'?'selected':'' ?>>Computer Science</option>
    </select>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">HOD Email</span>
                        <span class="setting-desc">Head of Department email for oversight</span>
                    </div>
                    <input type="email" name="hod_email" value="<?= htmlspecialchars($s['hod_email']) ?>" placeholder="hod@sxc.edu">
                </div>
                <!-- <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Staff ID</span>
                        <span class="setting-desc">UID for administrative logs</span>
                    </div>
                    <input type="text" name="staff_id" value="<?= htmlspecialchars($s['staff_id']) ?>" placeholder="SXC-2024-001">
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Room Number</span>
                        <span class="setting-desc">Office location for contact info</span>
                    </div>
                    <input type="text" name="room_no" value="<?= htmlspecialchars($s['room_no']) ?>" placeholder="A-201">
                </div> -->
                <!-- <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Extension Number</span>
                        <span class="setting-desc">Internal phone extension</span>
                    </div>
                    <input type="text" name="ext_no" value="<?= htmlspecialchars($s['ext_no']) ?>" placeholder="+91">
                </div> -->
            </div>

            <!-- COMPLIANCE SECTION -->
            <h2 id="compliance">Institutional Protocol</h2>
            <div class="section-card">
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">HOD Oversight (BCC)</span>
                        <span class="setting-desc">Automatically copy HOD on all outgoing faculty mail</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="auto_bcc_hod" <?= $s['auto_bcc_hod'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Archive Sent Emails</span>
                        <span class="setting-desc">Automatically save copies of all sent emails</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="archive_sent" <?= $s['archive_sent'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Subject Compliance</span>
                        <span class="setting-desc">Prevent sending if subject line is empty</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="mandatory_subject" <?= $s['mandatory_subject'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Read Receipts</span>
                        <span class="setting-desc">Request acknowledgement for every email sent</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="read_receipts" <?= $s['read_receipts'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Auto-Label Sent Emails</span>
                        <span class="setting-desc">Automatically organize sent emails with labels</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="auto_label_sent" <?= $s['auto_label_sent'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Default Priority Level</span>
                        <span class="setting-desc">Set email importance for all outgoing messages</span>
                    </div>
                    <select name="priority_level">
                        <option value="high" <?= $s['priority_level']=='high'?'selected':'' ?>>High</option>
                        <option value="normal" <?= $s['priority_level']=='normal'?'selected':'' ?>>Normal</option>
                        <option value="low" <?= $s['priority_level']=='low'?'selected':'' ?>>Low</option>
                    </select>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Attachment Size Limit</span>
                        <span class="setting-desc">Maximum file size in MB per email</span>
                    </div>
                    <input type="number" name="attach_size_limit" value="<?= htmlspecialchars($s['attach_size_limit'])?>" min="1" max="100" disabled>
                </div>
            </div>

            <!-- IMAP / SMTP SERVER INFO SECTION (read-only — sourced from server env) -->
            <h2 id="imap">📧 Mail Server Configuration</h2>
            <div class="section-card">
                <?php
                // Pull directly from environment — never from user input
                $envSmtpHost   = env('SMTP_HOST',        'mail.hostinger.com');
                $envSmtpPort   = env('SMTP_PORT',        '465');
                $envImapHost   = env('IMAP_HOST',        'imap.hostinger.com');
                $envImapPort   = env('IMAP_PORT',        '993');
                $envEncryption = env('MAIL_ENCRYPTION',  'ssl');
                ?>

                <!-- Info banner -->
                <div style="background: #f0f9ff; border-left: 4px solid #0284c7; padding: 16px; border-radius: 8px; margin-bottom: 24px; display: flex; gap: 12px; align-items: flex-start;">
                    <span class="material-icons" style="color: #0284c7; font-size: 20px; margin-top: 1px;">info</span>
                    <div>
                        <div style="font-weight: 600; color: #0c4a6e; margin-bottom: 4px;">Server-managed configuration</div>
                        <p style="font-size: 13px; color: #0369a1; line-height: 1.6; margin: 0;">
                            These values are set by the system administrator and cannot be edited here.
                            Your login password is used for both SMTP and IMAP — it is <strong>never stored</strong> in the database.
                        </p>
                    </div>
                </div>

                <!-- SMTP Info -->
                <div style="margin-bottom: 8px; font-size: 11px; font-weight: 700; color: var(--apple-gray); text-transform: uppercase; letter-spacing: 0.5px; padding: 0 4px;">
                    Outgoing Mail (SMTP)
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">SMTP Server</span>
                        <span class="setting-desc">Handles all outgoing email delivery</span>
                    </div>
                    <span style="font-size: 13px; font-weight: 500; color: #1c1c1e; background: #F2F2F7; padding: 8px 14px; border-radius: 6px; font-family: 'SF Mono', 'Fira Mono', monospace;">
                        <?= htmlspecialchars($envSmtpHost) ?>
                    </span>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">SMTP Port</span>
                        <span class="setting-desc">Connection port for outgoing mail</span>
                    </div>
                    <span style="font-size: 13px; font-weight: 500; color: #1c1c1e; background: #F2F2F7; padding: 8px 14px; border-radius: 6px; font-family: 'SF Mono', 'Fira Mono', monospace;">
                        <?= htmlspecialchars($envSmtpPort) ?>
                    </span>
                </div>

                <!-- Divider -->
                <div style="height: 1px; background: var(--border); margin: 8px 0 16px;"></div>

                <!-- IMAP Info -->
                <div style="margin-bottom: 8px; font-size: 11px; font-weight: 700; color: var(--apple-gray); text-transform: uppercase; letter-spacing: 0.5px; padding: 0 4px;">
                    Incoming Mail (IMAP)
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">IMAP Server</span>
                        <span class="setting-desc">Retrieves your incoming messages</span>
                    </div>
                    <span style="font-size: 13px; font-weight: 500; color: #1c1c1e; background: #F2F2F7; padding: 8px 14px; border-radius: 6px; font-family: 'SF Mono', 'Fira Mono', monospace;">
                        <?= htmlspecialchars($envImapHost) ?>
                    </span>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">IMAP Port</span>
                        <span class="setting-desc">Connection port for incoming mail</span>
                    </div>
                    <span style="font-size: 13px; font-weight: 500; color: #1c1c1e; background: #F2F2F7; padding: 8px 14px; border-radius: 6px; font-family: 'SF Mono', 'Fira Mono', monospace;">
                        <?= htmlspecialchars($envImapPort) ?>
                    </span>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Encryption</span>
                        <span class="setting-desc">Security protocol for mail connections</span>
                    </div>
                    <span style="font-size: 13px; font-weight: 500; color: #34C759; background: #f0fdf4; padding: 8px 14px; border-radius: 6px; display: flex; align-items: center; gap: 6px;">
                        <span class="material-icons" style="font-size: 14px;">lock</span>
                        <?= strtoupper(htmlspecialchars($envEncryption)) ?>/TLS
                    </span>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Login Username</span>
                        <span class="setting-desc">Your authenticated account on the mail server</span>
                    </div>
                    <span style="font-size: 13px; font-weight: 500; color: #1c1c1e; background: #F2F2F7; padding: 8px 14px; border-radius: 6px; font-family: 'SF Mono', 'Fira Mono', monospace;">
                        <?= htmlspecialchars($userEmail) ?>
                    </span>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Password</span>
                        <span class="setting-desc">Your login password — held in session only, never stored</span>
                    </div>
                    <span style="font-size: 13px; font-weight: 500; color: var(--apple-gray); background: #F2F2F7; padding: 8px 14px; border-radius: 6px; letter-spacing: 3px;">
                        ••••••••••••
                    </span>
                </div>
            </div>

            <!-- COMPOSITION SECTION -->
            <h2 id="composition">Composition Preferences</h2>
            <div class="section-card">
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Undo Send Delay</span>
                        <span class="setting-desc">Seconds to retract email after clicking send</span>
                    </div>
                    <select name="undo_send_delay">
                        <option value="0" <?= $s['undo_send_delay']=='0'?'selected':'' ?>>Instant (No undo)</option>
                        <option value="5" <?= $s['undo_send_delay']=='5'?'selected':'' ?>>5 Seconds</option>
                        <option value="10" <?= $s['undo_send_delay']=='10'?'selected':'' ?>>10 Seconds</option>
                        <option value="30" <?= $s['undo_send_delay']=='30'?'selected':'' ?>>30 Seconds</option>
                    </select>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Font Family</span>
                        <span class="setting-desc">Default typeface for email composition</span>
                    </div>
                    <select name="font_family">
                        <option value="Inter" <?= $s['font_family']=='Inter'?'selected':'' ?>>Inter</option>
                        <option value="Arial" <?= $s['font_family']=='Arial'?'selected':'' ?>>Arial</option>
                        <option value="Helvetica" <?= $s['font_family']=='Helvetica'?'selected':'' ?>>Helvetica</option>
                        <option value="Times New Roman" <?= $s['font_family']=='Times New Roman'?'selected':'' ?>>Times New Roman</option>
                        <option value="Georgia" <?= $s['font_family']=='Georgia'?'selected':'' ?>>Georgia</option>
                    </select>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Font Size</span>
                        <span class="setting-desc">Default text size in pixels</span>
                    </div>
                    <select name="font_size">
                        <option value="12" <?= $s['font_size']=='12'?'selected':'' ?>>12px</option>
                        <option value="14" <?= $s['font_size']=='14'?'selected':'' ?>>14px</option>
                        <option value="16" <?= $s['font_size']=='16'?'selected':'' ?>>16px</option>
                        <option value="18" <?= $s['font_size']=='18'?'selected':'' ?>>18px</option>
                    </select>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Spell Check</span>
                        <span class="setting-desc">Live grammar and spelling correction</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="spell_check" <?= $s['spell_check'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Auto-Correct</span>
                        <span class="setting-desc">Automatically fix common typos</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="auto_correct" <?= $s['auto_correct'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Rich Text Editor</span>
                        <span class="setting-desc">Enable formatting tools and HTML emails</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="rich_text" <?= $s['rich_text'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Smart Reply</span>
                        <span class="setting-desc">AI-suggested quick responses</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="smart_reply" <?= $s['smart_reply'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Default CC</span>
                        <span class="setting-desc">Always copy these addresses (comma-separated)</span>
                    </div>
                    <input type="text" name="default_cc" value="<?= htmlspecialchars($s['default_cc']) ?>" placeholder="email@example.com">
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Default BCC</span>
                        <span class="setting-desc">Always blind copy these addresses</span>
                    </div>
                    <input type="text" name="default_bcc" value="<?= htmlspecialchars($s['default_bcc']) ?>" placeholder="email@example.com">
                </div>
                <div class="setting-row full-width">
                    <div class="setting-info">
                        <span class="setting-title">Email Signature</span>
                        <span class="setting-desc">Automatically appended to all outgoing emails</span>
                    </div>
                    <textarea name="signature" placeholder="Best regards,
Your Name
Your Title"><?= htmlspecialchars($s['signature']) ?></textarea>
                </div>
            </div>

            <!-- APPEARANCE SECTION -->
            <h2 id="appearance">Interface & Experience</h2>
            <div class="section-card">
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Visual Density</span>
                        <span class="setting-desc">Adjust whitespace between UI elements</span>
                    </div>
                    <select name="density">
                        <option value="compact" <?= $s['density']=='compact'?'selected':'' ?>>Compact</option>
                        <option value="relaxed" <?= $s['density']=='relaxed'?'selected':'' ?>>Relaxed</option>
                    </select>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Dark Mode</span>
                        <span class="setting-desc">Interface color scheme preference</span>
                    </div>
                    <select name="dark_mode">
                        <option value="auto" <?= $s['dark_mode']=='auto'?'selected':'' ?>>Auto (System)</option>
                        <option value="light" <?= $s['dark_mode']=='light'?'selected':'' ?>>Always Light</option>
                        <option value="dark" <?= $s['dark_mode']=='dark'?'selected':'' ?>>Always Dark</option>
                    </select>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Glassmorphism Effects</span>
                        <span class="setting-desc">Enable background blur and translucency</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="blur_effects" <?= $s['blur_effects'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Compact Mode</span>
                        <span class="setting-desc">Reduce padding for more content</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="compact_mode" <?= $s['compact_mode'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Show Avatars</span>
                        <span class="setting-desc">Display profile pictures in email list</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="show_avatars" <?= $s['show_avatars'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Animation Speed</span>
                        <span class="setting-desc">UI transition and animation duration</span>
                    </div>
                    <select name="anim_speed">
                        <option value="fast" <?= $s['anim_speed']=='fast'?'selected':'' ?>>Fast</option>
                        <option value="normal" <?= $s['anim_speed']=='normal'?'selected':'' ?>>Normal</option>
                        <option value="slow" <?= $s['anim_speed']=='slow'?'selected':'' ?>>Slow</option>
                        <option value="none" <?= $s['anim_speed']=='none'?'selected':'' ?>>None</option>
                    </select>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Font Weight</span>
                        <span class="setting-desc">Default text thickness</span>
                    </div>
                    <select name="font_weight">
                        <option value="light" <?= $s['font_weight']=='light'?'selected':'' ?>>Light</option>
                        <option value="medium" <?= $s['font_weight']=='medium'?'selected':'' ?>>Medium</option>
                        <option value="bold" <?= $s['font_weight']=='bold'?'selected':'' ?>>Bold</option>
                    </select>
                </div>
            </div>

            <!-- NOTIFICATIONS SECTION -->
            <h2 id="notifications">Notifications</h2>
            <div class="section-card">
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Push Notifications</span>
                        <span class="setting-desc">Receive alerts for new emails</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="push_notif" <?= $s['push_notif'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Browser Notifications</span>
                        <span class="setting-desc">Desktop notifications when app is open</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="browser_notif" <?= $s['browser_notif'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Sound Alerts</span>
                        <span class="setting-desc">Play sound on new email arrival</span>
                    </div>
                    <select name="sound_alerts">
                        <option value="none" <?= $s['sound_alerts']=='none'?'selected':'' ?>>None</option>
                        <option value="tink" <?= $s['sound_alerts']=='tink'?'selected':'' ?>>Tink</option>
                        <option value="chime" <?= $s['sound_alerts']=='chime'?'selected':'' ?>>Chime</option>
                        <option value="bell" <?= $s['sound_alerts']=='bell'?'selected':'' ?>>Bell</option>
                    </select>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Activity Report</span>
                        <span class="setting-desc">Email usage summary frequency</span>
                    </div>
                    <select name="activity_report">
                        <option value="daily" <?= $s['activity_report']=='daily'?'selected':'' ?>>Daily</option>
                        <option value="weekly" <?= $s['activity_report']=='weekly'?'selected':'' ?>>Weekly</option>
                        <option value="monthly" <?= $s['activity_report']=='monthly'?'selected':'' ?>>Monthly</option>
                        <option value="never" <?= $s['activity_report']=='never'?'selected':'' ?>>Never</option>
                    </select>
                </div>
            </div>

            <!-- SECURITY SECTION -->
            <h2 id="security">Security & Privacy</h2>
            <div class="section-card">
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Two-Factor Authentication</span>
                        <span class="setting-desc">Require additional verification on login</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="two_factor" <?= $s['two_factor'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Session Timeout</span>
                        <span class="setting-desc">Auto-logout after minutes of inactivity</span>
                    </div>
                    <select name="session_timeout">
                        <option value="15" <?= $s['session_timeout']=='15'?'selected':'' ?>>15 Minutes</option>
                        <option value="30" <?= $s['session_timeout']=='30'?'selected':'' ?>>30 Minutes</option>
                        <option value="60" <?= $s['session_timeout']=='60'?'selected':'' ?>>1 Hour</option>
                        <option value="240" <?= $s['session_timeout']=='240'?'selected':'' ?>>4 Hours</option>
                        <option value="0" <?= $s['session_timeout']=='0'?'selected':'' ?>>Never</option>
                    </select>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">IP Lock</span>
                        <span class="setting-desc">Restrict login to specific IP addresses</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="ip_lock" <?= $s['ip_lock'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="setting-row">
                    <div class="setting-info">
                        <span class="setting-title">Debug Logs</span>
                        <span class="setting-desc">Enable detailed error logging (developers only)</span>
                    </div>
                    <label class="switch">
                        <input type="checkbox" name="debug_logs" <?= $s['debug_logs'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>
            </div>

            <button type="submit" class="btn-deploy">
                <!-- <span class="material-icons">save</span> -->
                Save Settings
            </button>
        </form>
    </main>

    <!-- Success Toast -->
    <div class="success-toast" id="successToast">
        <span class="material-icons">check_circle</span>
        <span>Settings saved successfully!</span>
    </div>

    <script>
        // Smooth navigation highlighting
        document.querySelectorAll('.nav-link').forEach(link => {
            link.onclick = (e) => {
                e.preventDefault();
                const target = link.getAttribute('href');
                
                // Update active state
                document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
                link.classList.add('active');
                
                // Smooth scroll to section
                document.querySelector(target).scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });

        // Scroll spy for nav highlighting
        const sections = document.querySelectorAll('h2[id]');
        const navLinks = document.querySelectorAll('.nav-link');
        
        window.addEventListener('scroll', () => {
            let current = '';
            
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.clientHeight;
                if (window.pageYOffset >= (sectionTop - 200)) {
                    current = section.getAttribute('id');
                }
            });
            
            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === `#${current}`) {
                    link.classList.add('active');
                }
            });
        });

        // AJAX Form Submission — POST only, no page navigation
        document.getElementById('appleSettingsForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            e.stopPropagation();

            const saveBtn = document.querySelector('.btn-deploy');
            const originalHTML = saveBtn.innerHTML;
            saveBtn.innerHTML = '<span class="material-icons">hourglass_top</span> Saving…';
            saveBtn.disabled = true;

            // Collect form data
            const formData = new FormData(this);

            // Represent unchecked checkboxes explicitly as 'false'
            this.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                formData.set(cb.name, cb.checked ? 'true' : 'false');
            });

            try {
                const response = await fetch('save_settings.php', {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) throw new Error('Server returned ' + response.status);

                const result = await response.json();

                if (result.success) {
                    const toast = document.getElementById('successToast');
                    toast.classList.add('show');
                    setTimeout(() => toast.classList.remove('show'), 3000);
                } else {
                    alert('❌ ' + (result.message || 'Unknown error'));
                }
            } catch (err) {
                console.error('Save error:', err);
                alert('❌ Failed to save settings: ' + err.message);
            } finally {
                saveBtn.innerHTML = originalHTML;
                saveBtn.disabled = false;
            }
        });
    </script>
</body>
</html>