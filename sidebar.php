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

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@700&family=Inter:wght@400;500;600;700&display=swap"
        rel="stylesheet">

    <style>
        :root {
            --nature-red: #a1021c;
            --nature-ink: #111111;
            --glass-bg: rgba(255, 255, 255, 0.7);
            --glass-border: rgba(255, 255, 255, 0.35);
            --hover-bg: rgba(255, 255, 255, 0.35);
            --sidebar-width: 280px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Inter", sans-serif;
            background: #f5f5f5;
        }

        /* Mobile Toggle Button */
        .mobile-toggle {
            display: none;
            position: fixed;
            top: 16px;
            left: 16px;
            z-index: 1100;
            background: var(--nature-ink);
            color: #fff;
            border: none;
            padding: 10px 12px;
            border-radius: 8px;
            backdrop-filter: blur(8px);
            cursor: pointer;
        }

        /* Sidebar Container */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            display: flex;
            flex-direction: column;
            border-right: 1px solid var(--glass-border);
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            padding-bottom: 24px;
            transition: transform 0.3s ease;
        }

        .sidebar-header {
            padding: 32px 24px;
            border-bottom: 1px solid var(--glass-border);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 14px;
            text-decoration: none;
        }

        .logo-image {
            width: 52px;
            height: 52px;
            border-radius: 4px;
        }

        .logo-title {
            font-family: "Crimson Pro", serif;
            font-size: 22px;
            font-weight: 700;
            color: var(--nature-ink);
        }

        .nav-section {
            flex: 1;
            padding: 18px 12px;
            overflow-y: auto;
        }

        .nav-item,
        .label-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            text-decoration: none;
            color: var(--nature-ink);
            font-weight: 600;
            font-size: 15px;
            border-radius: 8px;
            transition: 0.25s ease;
        }

        .nav-item:hover,
        .label-item:hover {
            background: var(--hover-bg);
        }

        .nav-item.active {
            border-left: 4px solid var(--nature-red);
            background: rgba(161, 2, 28, 0.08);
        }

        .nav-item.active .material-icons {
            color: var(--nature-red);
        }

        .nav-section-title {
            font-size: 12px;
            font-weight: 800;
            color: #555;
            letter-spacing: 0.4px;
            margin: 20px 16px 8px;
        }

        .label-dot {
            width: 10px;
            height: 10px;
            border-radius: 2px;
        }

        .user-footer {
            padding: 20px;
            border-top: 1px solid var(--glass-border);
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(12px);
        }

        .logout-btn {
            color: var(--nature-red);
            padding: 6px 12px;
            border-radius: 6px;
            border: 2px solid var(--nature-red);
            text-decoration: none;
            font-weight: 700;
            font-size: 12px;
        }

        .footer-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 12px;
        }

        /* Mobile Sidebar Behavior */
        @media (max-width: 768px) {
            .mobile-toggle {
                display: block;
            }

            .sidebar {
                transform: translateX(-100%);
                z-index: 1090;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .sidebar-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100vw;
                height: 100vh;
                background: rgba(0, 0, 0, 0.5);
                z-index: 1080;
                display: none;
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
                <div>
                    <div class="logo-title">SXC MDTS</div>
                    <div style="font-size:10px; color:#666; font-weight:700;">OFFICIAL PORTAL</div>
                </div>
            </a>
        </div>

        <nav class="nav-section">
            <a href="index.php" class="nav-item <?= ($current_page == 'index') ? 'active' : ''; ?>">
                <span class="material-icons">edit_note</span>
                COMPOSE
            </a>

            <a href="sent_history.php" class="nav-item <?= ($current_page == 'sent_history') ? 'active' : ''; ?>">
                <span class="material-icons">history</span>
                ALL MAIL
            </a>

            <a href="send.php" class="nav-item <?= ($current_page == 'send') ? 'active' : ''; ?>">
                <span class="material-icons">analytics</span>
                ANALYTICS
            </a>

            <div class="nav-section-title">LABELS</div>

            <?php foreach ($sidebarLabels as $label): ?>
            <a href="sent_history.php?label_id=<?= $label['id'] ?>" class="label-item">
                <div class="label-dot" style="background: <?= htmlspecialchars($label['label_color']) ?>;"></div>
                <?= htmlspecialchars($label['label_name']) ?>
            </a>
            <?php endforeach; ?>
        </nav>

        <div class="user-footer">
            <span style="font-size:11px; font-weight:800; color:#555;">Authenticated</span>
            <div style="font-size:14px; font-weight:700; margin:8px 0;">
                <?= htmlspecialchars($userEmail) ?>
            </div>

            <div class="footer-actions">
                <a href="settings.php" style="text-decoration:none; color:#444; font-weight:700; font-size:12px;">
                    <span class="material-icons" style="font-size:16px;">tune</span> CONFIG
                </a>
                <a href="logout.php" class="logout-btn">SIGN OUT</a>
            </div>
        </div>

    </div>

    <script>
        function toggleSidebar() {
            document.getElementById("mainSidebar").classList.toggle("open");
            document.querySelector(".sidebar-overlay").classList.toggle("active");
        }
    </script>

</body>

</html>