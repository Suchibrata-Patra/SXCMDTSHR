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
    <link
        href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@700&family=Inter:wght@400;500;600;700&display=swap"
        rel="stylesheet">

    <style>
        :root {
            --sidebar-width: 320px;
            --primary-color: #0e1d32;
            --secondary-color: #f8f9fa;
            --accent-color: #d63636;
            --light-gray: #eaeef4;
            --border-color: #d1d1d1;
            --text-color: #40474f;
            --hover-bg: rgba(255, 255, 255, 0.1);
            --shadow-light: 0 2px 8px rgba(0, 0, 0, 0.1);
            --shadow-dark: 0 6px 20px rgba(0, 0, 0, 0.2);
            --transition-speed: 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #fafafa;
            color: var(--text-color);
        }

        .mobile-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 16px;
            border-radius: 8px;
            box-shadow: var(--shadow-dark);
            cursor: pointer;
            transition: background 0.3s;
        }

        .mobile-toggle:hover {
            background: rgba(14, 29, 50, 0.8);
        }

        /* Sidebar Style */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--secondary-color);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            position: sticky;
            top: 0;
            box-shadow: var(--shadow-dark);
            transition: transform 0.4s ease;
        }

        /* Header Section */
        .sidebar-header {
            display: flex;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            background: var(--primary-color);
            color: white;
            box-shadow: var(--shadow-light);
        }

        .logo {
            display: flex;
            align-items: center;
            text-decoration: none;
            transition: transform 0.3s;
        }

        .logo:hover {
            transform: scale(1.05);
        }

        .logo-image {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            box-shadow: var(--shadow-dark);
        }

        .logo-text {
            margin-left: 12px;
        }

        .logo-title {
            font-family: 'Crimson Pro', serif;
            font-size: 24px;
            font-weight: 700;
        }

        .logo-subtitle {
            font-size: 12px;
            font-weight: 500;
            color: #f8f9fa;
        }

        /* Navigation Section */
        .nav-section {
            flex: 1;
            padding: 20px 16px;
            overflow-y: auto;
        }

        /* Navigation Items */
        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            text-decoration: none;
            color: var(--text-color);
            font-size: 16px;
            transition: all var(--transition-speed);
            border-radius: 8px;
            margin-bottom: 10px;
            position: relative;
        }

        .nav-item:hover {
            background: var(--hover-bg);
            color: var(--accent-color);
        }

        .nav-item.active {
            border-left: 4px solid var(--accent-color);
            font-weight: bold;
            background: rgba(214, 54, 54, 0.1);
        }

        .nav-icon {
            font-size: 26px;
            margin-right: 8px;
            transition: all var(--transition-speed);
        }

        .nav-item:hover .nav-icon {
            transform: scale(1.2);
        }

        /* Labels Section */
        .nav-section-title {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 12px;
            color: var(--text-color);
        }

        .label-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px;
            text-decoration: none;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            margin-bottom: 8px;
            transition: all var(--transition-speed);
        }

        .label-item:hover {
            background: var(--hover-bg);
            transform: translateY(-2px);
        }

        .label-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 10px;
        }

        .label-title {
            font-size: 14px;
            color: var(--text-color);
        }

        /* User Footer */
        .user-footer {
            padding: 20px;
            border-top: 1px solid var(--border-color);
            background: var(--primary-color);
            color: white;
            text-align: center;
        }

        .user-email {
            font-size: 14px;
            font-weight: bold;
            word-break: break-all;
            margin-bottom: 8px;
        }

        .auth-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            font-size: 12px;
        }

        .footer-actions {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 10px;
        }

        .footer-btn {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            background: var(--accent-color);
            color: white;
            border-radius: 6px;
            font-size: 14px;
            text-decoration: none;
            transition: background var(--transition-speed);
        }

        .footer-btn:hover {
            background: rgba(214, 54, 54, 0.8);
        }

        /* Sidebar Overlay */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(14, 29, 50, 0.7);
            z-index: 999;
            opacity: 0;
            transition: opacity var(--transition-speed);
        }

        .sidebar-overlay.active {
            display: block;
            opacity: 1;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .mobile-toggle {
                display: block;
            }

            .sidebar {
                position: fixed;
                left: 0;
                transform: translateX(-100%);
            }

            .sidebar.open {
                transform: translateX(0);
            }

            :root {
                --sidebar-width: 280px;
            }
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
                    alt="Institutional Logo" class="logo-image">
                <div class="logo-text">
                    <span class="logo-title">SXC MDTS</span>
                    <span class="logo-subtitle">Official Portal</span>
                </div>
            </a>
        </div>

        <nav class="nav-section">
            <a href="index.php" class="nav-item <?= ($current_page == 'index') ? 'active' : ''; ?>">
                <span class="material-icons-round nav-icon">edit_note</span>
                <span>Compose</span>
            </a>

            <a href="sent_history.php" class="nav-item <?= ($current_page == 'sent_history') ? 'active' : ''; ?>">
                <span class="material-icons-round nav-icon">history</span>
                <span>All Mail</span>
            </a>

            <a href="send.php" class="nav-item <?= ($current_page == 'send') ? 'active' : ''; ?>">
                <span class="material-icons-round nav-icon">analytics</span>
                <span>Analytics</span>
            </a>

            <div class="nav-section-title">Labels</div>

            <?php foreach ($sidebarLabels as $label): ?>
            <a href="sent_history.php?label_id=<?= $label['id'] ?>" class="label-item">
                <div class="label-dot" style="background-color: <?= htmlspecialchars($label['label_color']) ?>;"></div>
                <span class="label-title">
                    <?= htmlspecialchars($label['label_name']) ?>
                </span>
            </a>
            <?php endforeach; ?>
        </nav>

        <div class="user-footer">
            <span class="auth-badge">Authenticated</span>
            <span class="user-email">
                <?= htmlspecialchars($userEmail) ?>
            </span>
            <div class="footer-actions">
                <a href="settings.php" class="footer-btn">
                    <span class="material-icons-round">tune</span> Config
                </a>
                <a href="logout.php" class="footer-btn">
                    <span class="material-icons-round">logout</span> Sign Out
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

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && document.getElementById('mainSidebar').classList.contains('open')) {
                toggleSidebar();
            }
        });
    </script>
</body>

</html>