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
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@700&family=Inter:wght@400;500;600;700&display=swap"
        rel="stylesheet">

    <style>
        :root {
            --sidebar-width: 280px;
            --nature-red: #a10420;
            --inst-black: #1a1a1a;
            --inst-gray: #555555;
            --inst-border: #d1d1d1;
            --inst-bg: #ffffff;
            --hover-bg: #f8f8f8;
            --z-index-sidebar: 1000;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Mobile Toggle Button - Hidden on Desktop */
        .mobile-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1001;
            background: var(--inst-black);
            color: white;
            border: none;
            padding: 10px;
            border-radius: 4px;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
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
            transition: transform 0.3s ease;
            position: sticky;
            top: 0;
        }

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
            height: 52px;
            object-fit: contain;
        }

        .logo-title {
            font-family: 'Crimson Pro', serif;
            font-size: 22px;
            font-weight: 700;
            color: var(--inst-black);
            line-height: 1.1;
        }

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
            font-weight: 700;
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

        .nav-item.active {
            background: #f4f4f4;
            color: black;
            border-left: 4px solid var(--nature-red);
        }

        .nav-item.active .material-icons {
            color: var(--nature-red);
        }

        .nav-section-title {
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            color: var(--inst-gray);
            padding: 20px 16px 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .manage-labels-btn {
            color: var(--nature-red);
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: opacity 0.2s;
        }

        .label-item {
            display: flex;
            align-items: center;
            padding: 10px 16px;
            text-decoration: none;
            color: var(--inst-gray);
            font-size: 14px;
            font-weight: 600;
        }

        .label-dot {
            width: 10px;
            height: 10px;
            border-radius: 2px;
            margin-right: 12px;
        }

        .user-footer {
            padding: 24px;
            border-top: 2px solid var(--inst-border);
            background: #f9f9f9;
        }

        .user-email {
            font-size: 14px;
            font-weight: 700;
            word-break: break-all;
            margin-bottom: 12px;
            display: block;
        }

        .footer-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logout-btn {
            color: var(--nature-red);
            border: 2px solid var(--nature-red);
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 800;
            text-decoration: none;
        }

        /* Responsive Breakpoints */
        @media (max-width: 768px) {
            .mobile-toggle {
                display: block;
            }

            .sidebar {
                position: fixed;
                left: 0;
                transform: translateX(-100%);
                z-index: var(--z-index-sidebar);
                box-shadow: 10px 0 30px rgba(0, 0, 0, 0.1);
            }

            .sidebar.open {
                transform: translateX(0);
            }

            /* Overlay when sidebar is open */
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100vw;
                height: 100vh;
                background: rgba(0, 0, 0, 0.5);
                z-index: 999;
            }

            .sidebar-overlay.active {
                display: block;
            }
        }
    </style>
</head>

<body>

    <button class="mobile-toggle" onclick="toggleSidebar()">
        <span class="material-icons">menu</span>
    </button>

    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <div class="sidebar" id="mainSidebar">
        <div class="sidebar-header">
            <a href="index.php" class="logo">
                <img src="https://upload.wikimedia.org/wikipedia/en/b/b0/St._Xavier%27s_College%2C_Kolkata_logo.jpg"
                    alt="Institutional Logo" class="logo-image">
                <div class="logo-text">
                    <span class="logo-title">SXC MDTS</span>
                    <span class="logo-subtitle"
                        style="font-size: 10px; font-weight: 700; color: var(--inst-gray);">OFFICIAL PORTAL</span>
                </div>
            </a>
        </div>

        <nav class="nav-section">
            <a href="index.php" class="nav-item <?= ($current_page == 'index') ? 'active' : ''; ?>">
                <span class="material-icons">edit_note</span>
                <span>COMPOSE</span>
            </a>

            <a href="sent_history.php" class="nav-item <?= ($current_page == 'sent_history') ? 'active' : ''; ?>">
                <span class="material-icons">history</span>
                <span>ALL MAIL</span>
            </a>

            <a href="send.php" class="nav-item <?= ($current_page == 'send') ? 'active' : ''; ?>">
                <span class="material-icons">analytics</span>
                <span>ANALYTICS</span>
            </a>

            <div class="nav-section-title">
                LABELS
                <a href="manage_labels.php" class="manage-labels-btn" title="Manage Labels">
                    <span class="material-icons" style="font-size: 18px;">settings</span>
                </a>
            </div>

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
            <span
                style="font-size: 10px; font-weight: 800; color: var(--inst-gray); text-transform: uppercase;">Authenticated</span>
            <span class="user-email">
                <?= htmlspecialchars($userEmail) ?>
            </span>

            <div class="footer-actions">
                <a href="settings.php" class="action-link"
                    style="text-decoration:none; color: var(--inst-gray); font-size: 12px; font-weight:700;">
                    <span class="material-icons" style="font-size:16px; vertical-align:middle;">tune</span> CONFIG
                </a>
                <a href="logout.php" class="logout-btn">SIGN OUT</a>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('mainSidebar').classList.toggle('open');
            document.querySelector('.sidebar-overlay').classList.toggle('active');
        }
    </script>
</body>

</html>