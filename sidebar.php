<?php
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$userEmail = $_SESSION['smtp_user'] ?? 'user@example.com';
$userInitial = strtoupper(substr($userEmail, 0, 1));

require_once 'db_config.php';
$sidebarLabels = getLabelCounts($userEmail);
$unlabeledCount = getUnlabeledEmailCount($userEmail);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@700&family=Inter:wght@400;500;600;700&display=swap"
        rel="stylesheet">

    <style>
        :root {
            --sidebar-width: 260px;
            --nature-red: #a10420;
            /* Signature Nature Active Color */
            --inst-black: #1a1a1a;
            --inst-gray: #555555;
            --inst-border: #d1d1d1;
            --inst-bg: #ffffff;
            --hover-bg: #f8f8f8;
        }

        .sidebar {
            width: var(--sidebar-width);
            background: var(--inst-bg);
            border-right: 2px solid var(--inst-border);
            display: flex;
            flex-direction: column;
            height: 100vh;
            font-family: 'Inter', sans-serif;
            color: var(--inst-black);
        }

        /* Institutional Header */
        .sidebar-header {
            padding: 32px 24px;
            border-bottom: 2px solid var(--inst-border);
            background-color: #fcfcfc;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 16px;
            text-decoration: none;
        }

        .logo-image {
            width: 52px;
            /* Restored bigger logo */
            height: 52px;
            object-fit: contain;
        }

        .logo-text {
            display: flex;
            flex-direction: column;
        }

        .logo-title {
            font-family: 'Crimson Pro', serif;
            font-size: 22px;
            font-weight: 700;
            color: var(--inst-black);
            line-height: 1.1;
            letter-spacing: -0.02em;
        }

        .logo-subtitle {
            font-size: 11px;
            color: var(--inst-gray);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-weight: 700;
            margin-top: 4px;
        }

        /* Navigation Section */
        .nav-section {
            flex: 1;
            padding: 24px 12px;
            overflow-y: auto;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            text-decoration: none;
            color: var(--inst-black);
            font-size: 15px;
            font-weight: 600;
            /* Bold design */
            border-radius: 6px;
            margin-bottom: 4px;
            transition: all 0.2s ease;
        }

        .nav-item .material-icons {
            font-size: 22px;
            color: var(--inst-gray);
        }

        .nav-item:hover {
            background: var(--hover-bg);
        }

        /* Active Tab with Nature Red */
        .nav-item.active {
            background: #f4f4f4;
            color: black;
            border-left: 3px solid var(--nature-red);
        }

        .nav-item.active .material-icons {
            color: black;
        }

        .nav-section-title {
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            color: var(--inst-gray);
            padding: 20px 16px 10px;
            letter-spacing: 0.05em;
        }

        .label-item {
            display: flex;
            align-items: center;
            padding: 10px 16px;
            text-decoration: none;
            color: var(--inst-gray);
            font-size: 14px;
            font-weight: 500;
        }

        .label-dot {
            width: 10px;
            height: 10px;
            border-radius: 2px;
            margin-right: 12px;
        }

        /* Elegant Footer */
        .user-footer {
            padding: 24px;
            border-top: 2px solid var(--inst-border);
            background: #f9f9f9;
        }

        .user-profile {
            margin-bottom: 16px;
        }

        .user-email-label {
            font-size: 10px;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--inst-gray);
            display: block;
            margin-bottom: 2px;
        }

        .user-email {
            font-size: 14px;
            font-weight: 700;
            color: var(--inst-black);
            word-break: break-all;
        }

        .footer-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 12px;
        }

        .action-link {
            font-size: 13px;
            font-weight: 700;
            color: var(--inst-gray);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .action-link:hover {
            color: var(--nature-red);
        }

        .logout-btn {
            color: var(--nature-red);
            border: 1.5px solid var(--nature-red);
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 800;
            text-decoration: none;
            transition: all 0.2s;
        }

        .logout-btn:hover {
            background: var(--nature-red);
            color: white;
        }
    </style>
</head>

<body>

    <div class="sidebar">
        <div class="sidebar-header">
            <a href="index.php" class="logo">
                <img src="https://upload.wikimedia.org/wikipedia/en/b/b0/St._Xavier%27s_College%2C_Kolkata_logo.jpg"
                    alt="Institutional Logo" class="logo-image">
                <div class="logo-text">
                    <span class="logo-title">SXC MDTS</span>
                    <span class="logo-subtitle">Mail Delivery System</span>
                </div>
            </a>
        </div>

        <nav class="nav-section">
            <a href="index.php" class="nav-item <?= ($current_page == 'index') ? 'active' : ''; ?>">
                <span class="material-icons">border_color</span>
                <span>COMPOSE</span>
            </a>

            <a href="sent_history.php" class="nav-item <?= ($current_page == 'sent_history') ? 'active' : ''; ?>">
                <span class="material-icons">send</span>
                <span>All Mail</span>
            </a>

            <a href="send.php" class="nav-item <?= ($current_page == 'send') ? 'active' : ''; ?>">
                <span class="material-icons">info</span>
                <span>Info</span>
            </a>

            <div class="nav-section-title">Institutional Labels</div>
            <?php foreach ($sidebarLabels as $label): ?>
            <a href="sent_history.php?label_id=<?= $label['id'] ?>" class="label-item">
                <div class="label-dot" style="background-color: <?= htmlspecialchars($label['label_color']) ?>;"></div>
                <span>
                    <?= htmlspecialchars($label['label_name']) ?>
                </span>
            </a>
            <?php endforeach; ?>
        </nav>

        <div class="user-footer">
            <div class="user-profile">
                <span class="user-email-label">Authenticated User</span>
                <span class="user-email">
                    <?= htmlspecialchars($userEmail) ?>
                </span>
            </div>

            <div class="footer-actions">
                <a href="settings.php" class="action-link">
                    <span class="material-icons" style="font-size:16px">settings</span>
                    CONFIG
                </a>
                <a href="logout.php" class="logout-btn">Sign Out</a>
            </div>
        </div>
    </div>

</body>

</html>