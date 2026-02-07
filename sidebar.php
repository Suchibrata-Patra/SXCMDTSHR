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
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --sidebar-width: 260px;
            /* Apple Color Palette Mapping */
            --nature-red: #007AFF; /* Apple Blue */
            --nature-red-hover: #0056b3;
            --nature-dark: #1c1c1e;
            --nature-gray: #3a3a3c;
            --nature-medium-gray: #8e8e93;
            --nature-light-gray: #c7c7cc;
            --nature-border: rgba(0, 0, 0, 0.08);
            --nature-border-light: rgba(0, 0, 0, 0.04);
            --nature-bg: rgba(242, 242, 247, 0.8); /* Glass effect */
            --nature-bg-hover: rgba(0, 0, 0, 0.04);
            --nature-bg-active: rgba(0, 122, 255, 0.1);
            --shadow-subtle: 0 1px 3px rgba(0, 0, 0, 0.05);
            --shadow-sm: 0 2px 6px rgba(0, 0, 0, 0.04);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.08);
            --transition: cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #ffffff;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Mobile Toggle */
        .mobile-toggle {
            display: none;
            position: fixed;
            top: 16px;
            left: 16px;
            z-index: 1002;
            background: #fff;
            border: 1px solid var(--nature-border);
            color: var(--nature-dark);
            padding: 10px;
            border-radius: 10px;
            cursor: pointer;
            box-shadow: var(--shadow-sm);
            transition: all 0.2s var(--transition);
        }

        .mobile-toggle:hover {
            background: var(--nature-red);
            color: white;
            border-color: var(--nature-red);
        }

        /* Sidebar with Glassmorphism */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--nature-bg);
            backdrop-filter: blur(30px) saturate(180%);
            -webkit-backdrop-filter: blur(30px) saturate(180%);
            border-right: 1px solid var(--nature-border);
            display: flex;
            flex-direction: column;
            height: 100vh;
            color: var(--nature-dark);
            transition: transform 0.3s var(--transition);
            position: sticky;
            top: 0;
        }

        /* Header */
        .sidebar-header {
            padding: 30px 18px 20px;
            border-bottom: 1px solid var(--nature-border-light);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .logo-image {
            width: 36px;
            height: 36px;
            object-fit: contain;
            border-radius: 8px;
            filter: grayscale(1) contrast(1.1); /* Minimalist look */
        }

        .logo-text {
            display: flex;
            flex-direction: column;
            gap: 1px;
        }

        .logo-title {
            font-size: 15px;
            font-weight: 700;
            color: #000;
            letter-spacing: -0.3px;
        }

        .logo-subtitle {
            font-size: 9px;
            font-weight: 600;
            color: var(--nature-medium-gray);
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        /* Navigation */
        .nav-section {
            flex: 1;
            padding: 12px;
            overflow-y: auto;
        }

        /* Navigation Items */
        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            text-decoration: none;
            color: var(--nature-gray);
            font-size: 14px;
            font-weight: 500;
            border-radius: 8px;
            margin-bottom: 2px;
            transition: all 0.2s var(--transition);
            position: relative;
        }

        .nav-item:hover {
            background: var(--nature-bg-hover);
            color: #000;
        }

        .nav-item.active {
            background: var(--nature-bg-active);
            color: var(--nature-red);
            font-weight: 600;
        }

        .nav-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            height: 16px;
            width: 3px;
            background: var(--nature-red);
            border-radius: 0 4px 4px 0;
        }

        /* Section Title */
        .nav-section-title {
            font-size: 11px;
            font-weight: 600;
            color: var(--nature-medium-gray);
            padding: 20px 12px 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            letter-spacing: 0.5px;
        }

        .manage-labels-btn {
            color: var(--nature-light-gray);
            padding: 4px;
            border-radius: 6px;
            transition: 0.2s;
        }

        .manage-labels-btn:hover {
            background: var(--nature-bg-hover);
            color: var(--nature-red);
        }

        /* Label Items */
        .label-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 7px 12px;
            text-decoration: none;
            color: var(--nature-gray);
            font-size: 13px;
            border-radius: 8px;
            margin-bottom: 1px;
            transition: 0.2s;
        }

        .label-item:hover {
            background: var(--nature-bg-hover);
        }

        .label-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .label-count {
            font-size: 10px;
            font-weight: 600;
            color: var(--nature-medium-gray);
            background: rgba(0, 0, 0, 0.05);
            padding: 2px 6px;
            border-radius: 6px;
        }

        /* User Footer */
        .user-footer {
            padding: 16px;
            border-top: 1px solid var(--nature-border-light);
        }

        .user-card {
            background: rgba(255, 255, 255, 0.5);
            border: 1px solid var(--nature-border-light);
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 12px;
        }

        .auth-badge {
            font-size: 9px;
            font-weight: 700;
            color: #34C759; /* Apple Green */
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .auth-badge::before {
            background: #34C759;
        }

        .user-email {
            font-size: 12px;
            color: var(--nature-dark);
            font-weight: 500;
        }

        /* Footer Actions */
        .footer-actions {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            flex: 1;
            text-decoration: none;
            font-size: 11px;
            font-weight: 600;
            padding: 9px;
            border-radius: 8px;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .config-btn {
            color: var(--nature-dark);
            background: white;
            border: 1px solid var(--nature-border);
        }

        .logout-btn {
            color: #FF3B30;
            background: rgba(255, 59, 48, 0.1);
        }

        .logout-btn:hover {
            background: #FF3B30;
            color: white;
        }

        /* Sidebar Overlay */
        .sidebar-overlay {
            background: rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(4px);
        }

        @media (max-width: 768px) {
            .mobile-toggle { display: flex; }
            .sidebar { position: fixed; transform: translateX(-100%); z-index: 1000; }
            .sidebar.open { transform: translateX(0); }
        }
    </style>
</head>

<body>

    <button class="mobile-toggle" onclick="toggleSidebar()" aria-label="Toggle Menu">
        <span class="material-icons-round">menu</span>
    </button>

    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <div class="sidebar" id="mainSidebar">
        <div class="sidebar-header">
            <a href="index.php" class="logo">
                <img src="https://upload.wikimedia.org/wikipedia/en/b/b0/St._Xavier%27s_College%2C_Kolkata_logo.jpg"
                    alt="SXC Logo" class="logo-image">
                <div class="logo-text">
                    <span class="logo-title">SXC MDTS</span>
                    <span class="logo-subtitle">10.28.73.474</span>
                </div>
            </a>
        </div>

        <nav class="nav-section">
            <a href="index.php" class="nav-item <?= ($current_page == 'index') ? 'active' : ''; ?>">
                <span class="material-icons-round">edit_note</span>
                <span>Compose</span>
            </a>

            <a href="sent_history.php" class="nav-item <?= ($current_page == 'sent_history') ? 'active' : ''; ?>">
                <span class="material-icons-round">send</span>
                <span>Sent</span>
            </a>
            <a href="deleted_items.php" class="nav-item <?= ($current_page == 'deleted_items') ? 'active' : ''; ?>">
                <span class="material-icons-round">delete</span>
                <span>Deleted</span>
            </a>

            <a href="send.php" class="nav-item <?= ($current_page == 'send') ? 'active' : ''; ?>">
                <span class="material-icons-round">analytics</span>
                <span>Analytics</span>
            </a>

            <div class="nav-section-title">
                Labels
                <a href="manage_labels.php" class="manage-labels-btn" title="Manage Labels">
                    <span class="material-icons-round" style="font-size: 16px;">settings</span>
                </a>
            </div>

            <?php foreach ($sidebarLabels as $label): ?>
            <a href="sent_history.php?label_id=<?= $label['id'] ?>" class="label-item">
                <div class="label-content">
                    <div class="label-dot" style="background-color: <?= htmlspecialchars($label['label_color']) ?>;">
                    </div>
                    <span><?= htmlspecialchars($label['label_name']) ?></span>
                </div>
                <?php if (isset($label['count']) && $label['count'] > 0): ?>
                <span class="label-count"><?= $label['count'] ?></span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </nav>

        <div class="user-footer">
            <div class="user-card">
                <span class="auth-badge">Verified</span>
                <span class="user-email"><?= htmlspecialchars($userEmail) ?></span>
            </div>

            <div class="footer-actions">
                <a href="settings.php" class="action-btn config-btn">
                    <span class="material-icons-round">tune</span>
                    <span>Settings</span>
                </a>
                <a href="logout.php" class="action-btn logout-btn">
                    <span class="material-icons-round">logout</span>
                    <span>Log Out</span>
                </a>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('mainSidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
        }
    </script>
</body>
</html>