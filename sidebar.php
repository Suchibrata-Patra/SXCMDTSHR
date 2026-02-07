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
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@600&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        :root {
            --sidebar-width: 240px;
            --n-black: #000000;
            --n-mid-gray: #444444;
            --n-light-gray: #767676;
            --n-border: #e6e6e6;
            --n-bg: #ffffff;
            --n-accent: #005a8d; /* Professional Research Blue */
            --n-selection: #f3f3f3;
        }

        .sidebar {
            width: var(--sidebar-width);
            background: var(--n-bg);
            border-right: 1px solid var(--n-border);
            display: flex;
            flex-direction: column;
            height: 100vh;
            font-family: 'Inter', sans-serif;
            color: var(--n-black);
            -webkit-font-smoothing: antialiased;
        }

        /* Branding Section: Clean and Typographic */
        .sidebar-header {
            padding: 32px 24px;
            border-bottom: 1px solid var(--n-border);
        }

        .logo {
            text-decoration: none;
            display: block;
        }

        .logo-title {
            font-family: 'Crimson Pro', serif;
            font-size: 20px;
            font-weight: 600;
            color: var(--n-black);
            letter-spacing: -0.01em;
            line-height: 1;
            margin-bottom: 4px;
            display: block;
        }

        .logo-subtitle {
            font-size: 11px;
            color: var(--n-light-gray);
            text-transform: uppercase;
            letter-spacing: 0.12em;
            font-weight: 500;
        }

        /* Navigation: Focused on White Space and Thin Lines */
        .nav-section {
            flex: 1;
            padding: 24px 0;
            overflow-y: auto;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 10px 24px;
            text-decoration: none;
            color: var(--n-mid-gray);
            font-size: 14px;
            font-weight: 400;
            transition: all 0.15s ease;
            border-left: 2px solid transparent;
        }

        .nav-item .material-icons {
            font-size: 18px;
            margin-right: 12px;
            color: var(--n-light-gray);
            font-weight: 300;
        }

        .nav-item:hover {
            color: var(--n-black);
            background-color: var(--n-selection);
        }

        .nav-item.active {
            color: var(--n-black);
            font-weight: 600;
            border-left: 2px solid var(--n-black);
        }

        .nav-item.active .material-icons {
            color: var(--n-black);
        }

        /* Labels Section */
        .nav-section-title {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--n-light-gray);
            padding: 24px 24px 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .label-item {
            display: flex;
            align-items: center;
            padding: 8px 24px;
            text-decoration: none;
            color: var(--n-mid-gray);
            font-size: 13px;
        }

        .label-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            margin-right: 12px;
        }

        /* Footer: Elegant User Profile */
        .user-footer {
            padding: 24px;
            border-top: 1px solid var(--n-border);
        }

        .user-profile {
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin-bottom: 16px;
        }

        .signed-in-as {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--n-light-gray);
        }

        .user-email {
            font-size: 13px;
            font-weight: 500;
            color: var(--n-black);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .footer-links {
            display: flex;
            gap: 16px;
        }

        .footer-link {
            font-size: 12px;
            color: var(--n-light-gray);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: color 0.2s;
        }

        .footer-link:hover {
            color: var(--n-black);
            text-decoration: underline;
        }

        .footer-link .material-icons {
            font-size: 14px;
        }

        /* Remove scrollbar clutter */
        .nav-section::-webkit-scrollbar { width: 4px; }
        .nav-section::-webkit-scrollbar-thumb { background: var(--n-border); }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-header">
            <a href="index.php" class="logo">
                <span class="logo-title">SXC MDTS</span>
                <span class="logo-subtitle">Research Correspondence</span>
            </a>
        </div>

        <nav class="nav-section">
            <a href="index.php" class="nav-item <?= ($current_page == 'index') ? 'active' : ''; ?>">
                <span class="material-icons">add</span>
                <span>Compose</span>
            </a>

            <a href="sent_history.php" class="nav-item <?= ($current_page == 'sent_history') ? 'active' : ''; ?>">
                <span class="material-icons">history</span>
                <span>Correspondence</span>
            </a>

            <a href="send.php" class="nav-item <?= ($current_page == 'send') ? 'active' : ''; ?>">
                <span class="material-icons">bar_chart</span>
                <span>Analytics</span>
            </a>

            <div class="nav-section-title">
                Classification
                <a href="manage_labels.php" style="color: inherit;"><span class="material-icons" style="font-size: 14px;">settings</span></a>
            </div>

            <?php foreach ($sidebarLabels as $label): ?>
                <a href="sent_history.php?label_id=<?= $label['id'] ?>" class="label-item">
                    <div class="label-dot" style="background-color: <?= htmlspecialchars($label['label_color']) ?>;"></div>
                    <span><?= htmlspecialchars($label['label_name']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="user-footer">
            <div class="user-profile">
                <span class="signed-in-as">Authenticated Session</span>
                <span class="user-email" title="<?= htmlspecialchars($userEmail) ?>">
                    <?= htmlspecialchars($userEmail) ?>
                </span>
            </div>

            <div class="footer-links">
                <a href="settings.php" class="footer-link">
                    <span class="material-icons">tune</span>
                    <span>Preferences</span>
                </a>
                <a href="logout.php" class="footer-link" style="color: #c00;">
                    <span>Sign out</span>
                </a>
            </div>
        </div>
    </div>

</body>
</html>