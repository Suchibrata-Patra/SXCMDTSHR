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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --sidebar-width: 280px;
            --apple-blue: #007AFF;
            --glass-sidebar: rgba(255, 255, 255, 0.45);
            --glass-border: rgba(0, 0, 0, 0.07);
            --text-main: #1d1d1f;
            --text-secondary: #86868b;
            --active-item-bg: rgba(0, 122, 255, 0.09);
            --transition-premium: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: #f5f5f7;
            color: var(--text-main);
            -webkit-font-smoothing: antialiased;
        }

        /* Premium Sidebar Layout */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--glass-sidebar);
            backdrop-filter: blur(50px) saturate(210%);
            -webkit-backdrop-filter: blur(50px) saturate(210%);
            border-right: 1px solid var(--glass-border);
            display: flex;
            flex-direction: column;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        /* Logo Area */
        .sidebar-header {
            padding: 40px 24px 20px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 14px;
            text-decoration: none;
        }

        .logo-image {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            filter: grayscale(0.2);
        }

        .logo-title {
            font-size: 17px;
            font-weight: 700;
            letter-spacing: -0.4px;
            color: var(--text-main);
            line-height: 1.1;
        }

        .logo-subtitle {
            font-size: 9px;
            font-weight: 600;
            color: var(--text-secondary);
            /* text-transform: uppercase; */
            letter-spacing: 1.2px;
        }

        /* Navigation Content */
        .nav-section {
            flex: 1;
            padding: 10px 14px;
            overflow-y: auto;
            scrollbar-width: none;
        }

        .nav-section::-webkit-scrollbar {
            display: none;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            text-decoration: none;
            color: var(--text-main);
            font-size: 14px;
            font-weight: 500;
            border-radius: 12px;
            margin-bottom: 2px;
            transition: var(--transition-premium);
        }

        .nav-item:hover {
            background: rgba(0, 0, 0, 0.04);
            /* transform: translateX(3px); */
        }

        .nav-item.active {
            background: var(--active-item-bg);
            color: var(--apple-blue);
            font-weight: 600;
        }

        .material-icons-round {
            font-size: 20px;
            color: var(--text-secondary);
        }

        .nav-item.active .material-icons-round {
            color: var(--apple-blue);
        }

        /* Labels Section */
        .nav-section-title {
            font-size: 10px;
            font-weight: 700;
            color: var(--text-secondary);
            padding: 24px 14px 10px;
            /* text-transform: uppercase; */
            letter-spacing: 1px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .label-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 14px;
            text-decoration: none;
            color: var(--text-main);
            font-size: 13.5px;
            border-radius: 10px;
            margin-bottom: 2px;
            transition: var(--transition-premium);
        }

        .label-item:hover {
            background: rgba(0, 0, 0, 0.03);
        }

        .label-content {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .label-dot {
            width: 15px;
            height: 15px;
            border-radius: 10%;
            /* border: 2px solid rgba(255,255,255,0.8); */
            /* box-shadow: 0 0 0 1px rgba(0,0,0,0.05); */
        }

        .label-count {
            font-size: 10px;
            font-weight: 700;
            background: rgba(0, 0, 0, 0.06);
            color: var(--text-secondary);
            padding: 2px 8px;
            border-radius: 20px;
        }

        /* Premium Footer Area */
        .user-footer {
            padding: 20px;
            background: rgba(255, 255, 255, 0.15);
            border-top: 1px solid var(--glass-border);
        }

        .user-card {
            background: white;
            padding: 14px;
            border-radius: 16px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.03);
            margin-bottom: 12px;
            border: 1px solid rgba(255, 255, 255, 0.7);
        }

        .auth-badge img {
            display: inline-block;
            width: 30px;
            height: auto;

        }

        .user-email {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-main);
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .footer-actions {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            flex: 1;
            padding: 10px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
            transition: var(--transition-premium);
        }

        .config-btn {
            background: #fff;
            color: var(--text-main);
            border: 1px solid var(--glass-border);
        }

        .logout-btn {
            background: #1d1d1f;
            color: white;
        }

        .logout-btn:hover {
            background: #000;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
        }
    </style>
</head>

<body>
    <div class="sidebar" id="mainSidebar">
        <div class="sidebar-header">
            <a href="index.php" class="logo">
                <img src="https://upload.wikimedia.org/wikipedia/en/b/b0/St._Xavier%27s_College%2C_Kolkata_logo.jpg"
                    class="logo-image">
                <div class="logo-text">
                    <span class="logo-title">SXC MDTS</span>
                    <br>
                    <span class="logo-subtitle">V_1.2.15</span>
                </div>
            </a>
        </div>

        <nav class="nav-section">
            <!-- <div class="nav-section-title">Workspace</div> -->

            <a href="index.php" class="nav-item <?= ($current_page == 'index') ? 'active' : ''; ?>">
                <span class="material-icons-round">edit</span>
                <span>Compose</span>
            </a>
            <a href="sent_history.php" class="nav-item <?= ($current_page == 'sent_history') ? 'active' : ''; ?>">
                <span class="material-icons-round">send</span>
                <span>Sent History</span>
            </a>
            <a href="deleted_items.php" class="nav-item <?= ($current_page == 'deleted_items') ? 'active' : ''; ?>">
                <span class="material-icons-round">delete_outline</span>
                <span>Trash</span>
            </a>
            <a href="send.php" class="nav-item <?= ($current_page == 'send') ? 'active' : ''; ?>">
                <span class="material-icons-round">info</span>
                <span>Analytics</span>
            </a>

            <div class="nav-section-title">
                Labels
                <a href="manage_labels.php" style="color:var(--text-secondary); text-decoration:none;">
                    <span class="material-icons-round" style="font-size: 16px;">settings</span>
                </a>
            </div>

            <?php foreach ($sidebarLabels as $label): ?>
            <a href="sent_history.php?label_id=<?= $label['id'] ?>" class="label-item">
                <div class="label-content">
                    <div class="label-dot" style="background-color: <?= htmlspecialchars($label['label_color']) ?>;">
                    </div>
                    <span>
                        <?= htmlspecialchars($label['label_name']) ?>
                    </span>
                </div>
                <?php if (isset($label['count']) && $label['count'] > 0): ?>
                <span class="label-count">
                    <?= $label['count'] ?>
                </span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </nav>

        <div class="user-footer"
            style="padding: 20px; border-top: 1px solid rgba(0,0,0,0.07); background: rgba(255,255,255,0.2);">
            <div class="user-card"
                style="background: #ffffff; padding: 14px; border-radius: 16px; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 4px 12px rgba(0,0,0,0.03); margin-bottom: 12px;">

                <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 4px;">
                    <span class="auth-badge" style="display: flex; align-items: center;">
                        <img src="/Assets/image/Verified_badge.png" alt="Verified"
                            style="width: 14px; height: 14px; object-fit: contain;">
                    </span>
                    <span
                        style="font-size: 10px; font-weight: 800; color: #3090de; text-transform: uppercase; letter-spacing: 0.5px; line-height: 1;">
                        Verified Account
                    </span>
                </div>

                <span class="user-email"
                    style="display: block; font-size: 12px; font-weight: 600; color: #6e6e6e; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; padding-left: 2px;">
                    <?= htmlspecialchars($userEmail) ?>
                </span>
            </div>

            <div class="footer-actions" style="display: flex; gap: 8px;">
                <a href="settings.php" class="action-btn config-btn"
                    style="flex: 1; padding: 10px; border-radius: 10px; text-decoration: none; font-size: 12px; font-weight: 600; text-align: center; background: #ffffff; color: #1d1d1f; border: 1px solid rgba(0,0,0,0.1); transition: 0.2s;">
                    Settings
                </a>
                <a href="logout.php" class="action-btn logout-btn"
                    style="flex: 1; padding: 10px; border-radius: 10px; text-decoration: none; font-size: 12px; font-weight: 600; text-align: center; background: #000000; color: #ffffff; transition: 0.2s;">
                    Sign Out
                </a>
            </div>
        </div>
    </div>
</body>

</html>