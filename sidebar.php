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
        /* === Glassmorphism Sidebar Redesign === */

        :root {
            --sidebar-glass-bg: rgba(255, 255, 255, 0.22);
            --sidebar-glass-border: rgba(255, 255, 255, 0.35);
            --sidebar-blur: 18px;

            --accent-primary: #c81445;
            --accent-primary-hover: #e21c52;

            --text-strong: #1a1a1a;
            --text-soft: #4d4d4d;
            --btn-bg: rgba(255, 255, 255, 0.18);
            --btn-bg-hover: rgba(255, 255, 255, 0.30);
        }

        /* Sidebar Glass Base */
        .sidebar {
            background: var(--sidebar-glass-bg);
            backdrop-filter: blur(var(--sidebar-blur));
            -webkit-backdrop-filter: blur(var(--sidebar-blur));
            border-right: 1px solid var(--sidebar-glass-border);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
        }

        /* Header */
        .sidebar-header {
            background: rgba(255, 255, 255, 0.28);
            border-bottom: 1px solid var(--sidebar-glass-border);
        }

        .logo-title {
            color: var(--text-strong);
        }

        /* Nav Items */
        .nav-item {
            background: transparent;
            border-radius: 10px;
            color: var(--text-soft);
            transition: background 0.25s ease, transform 0.15s ease;
        }

        .nav-item:hover {
            background: var(--btn-bg-hover);
            transform: translateX(4px);
        }

        /* Active Nav Item */
        .nav-item.active {
            background: var(--btn-bg);
            border-left: 4px solid var(--accent-primary);
            color: var(--text-strong);
        }

        .nav-item.active .material-icons {
            color: var(--accent-primary);
        }

        /* Labels Section */
        .nav-section-title {
            color: var(--text-soft);
        }

        .label-item {
            border-radius: 8px;
            transition: background 0.25s ease, transform 0.15s ease;
        }

        .label-item:hover {
            background: var(--btn-bg-hover);
            transform: translateX(4px);
        }

        /* User Footer */
        .user-footer {
            background: rgba(255, 255, 255, 0.22);
            border-top: 1px solid var(--sidebar-glass-border);
        }

        /* Buttons */
        .logout-btn {
            background: var(--accent-primary);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 8px 16px;
            transition: background 0.25s ease, transform 0.15s ease;
        }

        .logout-btn:hover {
            background: var(--accent-primary-hover);
            transform: scale(1.05);
        }

        .action-link {
            color: var(--text-soft);
            opacity: 0.8;
            transition: opacity 0.25s ease;
        }

        .action-link:hover {
            opacity: 1;
        }

        /* Mobile Toggle Button */
        .mobile-toggle {
            background: rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            border: 1px solid rgba(255, 255, 255, 0.4);
        }

        .mobile-toggle:hover {
            transform: scale(1.05);
        }

        /* Overlay */
        .sidebar-overlay.active {
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
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