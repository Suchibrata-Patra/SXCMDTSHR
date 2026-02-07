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
        href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@600;700&family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <style>
        :root {
            --sidebar-width: 300px;
            --nature-red: #a10420;
            --nature-red-hover: #8a0318;
            --inst-black: #0f1419;
            --inst-gray: #536471;
            --inst-light-gray: #8b98a5;
            --inst-border: #eff3f4;
            --inst-bg: #ffffff;
            --hover-bg: #f7f9fa;
            --active-bg: #fef3f5;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.04);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.12);
            --z-index-sidebar: 1000;
            --transition-smooth: cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #fafbfc;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Mobile Toggle Button */
        .mobile-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1002;
            background: var(--inst-black);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 12px;
            cursor: pointer;
            box-shadow: var(--shadow-lg);
            transition: all 0.3s var(--transition-smooth);
        }

        .mobile-toggle:hover {
            transform: scale(1.05);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.16);
        }

        .mobile-toggle:active {
            transform: scale(0.95);
        }

        /* Sidebar Container */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--inst-bg);
            border-right: 1px solid var(--inst-border);
            display: flex;
            flex-direction: column;
            height: 100vh;
            font-family: 'Inter', sans-serif;
            color: var(--inst-black);
            transition: transform 0.4s var(--transition-smooth);
            position: sticky;
            top: 0;
            box-shadow: var(--shadow-sm);
        }

        /* Header Section */
        .sidebar-header {
            padding: 28px 24px;
            border-bottom: 1px solid var(--inst-border);
            background: linear-gradient(135deg, #ffffff 0%, #fafbfc 100%);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 16px;
            text-decoration: none;
            transition: transform 0.3s var(--transition-smooth);
        }

        .logo:hover {
            transform: translateX(2px);
        }

        .logo-image {
            width: 56px;
            height: 56px;
            object-fit: contain;
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s var(--transition-smooth);
        }

        .logo:hover .logo-image {
            box-shadow: var(--shadow-md);
        }

        .logo-text {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .logo-title {
            font-family: 'Crimson Pro', serif;
            font-size: 24px;
            font-weight: 700;
            color: var(--inst-black);
            line-height: 1.2;
            letter-spacing: -0.5px;
        }

        .logo-subtitle {
            font-size: 10px;
            font-weight: 700;
            color: var(--inst-light-gray);
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        /* Navigation Section */
        .nav-section {
            flex: 1;
            padding: 16px 16px;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: var(--inst-border) transparent;
        }

        .nav-section::-webkit-scrollbar {
            width: 6px;
        }

        .nav-section::-webkit-scrollbar-track {
            background: transparent;
        }

        .nav-section::-webkit-scrollbar-thumb {
            background: var(--inst-border);
            border-radius: 3px;
        }

        .nav-section::-webkit-scrollbar-thumb:hover {
            background: var(--inst-light-gray);
        }

        /* Navigation Items */
        .nav-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            text-decoration: none;
            color: var(--inst-gray);
            font-size: 15px;
            font-weight: 600;
            border-radius: 10px;
            margin-bottom: 4px;
            transition: all 0.25s var(--transition-smooth);
            position: relative;
            overflow: hidden;
        }

        .nav-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 3px;
            background: var(--nature-red);
            transform: scaleY(0);
            transition: transform 0.25s var(--transition-smooth);
        }

        .nav-item .material-icons-round {
            font-size: 22px;
            transition: all 0.25s var(--transition-smooth);
        }

        .nav-item:hover {
            background: var(--hover-bg);
            color: var(--inst-black);
            transform: translateX(2px);
        }

        .nav-item:hover .material-icons-round {
            transform: scale(1.1);
        }

        .nav-item.active {
            background: var(--active-bg);
            color: var(--nature-red);
            font-weight: 700;
        }

        .nav-item.active::before {
            transform: scaleY(1);
        }

        .nav-item.active .material-icons-round {
            color: var(--nature-red);
            transform: scale(1.05);
        }

        /* Section Title */
        .nav-section-title {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            color: var(--inst-light-gray);
            padding: 24px 16px 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            letter-spacing: 0.8px;
        }

        .manage-labels-btn {
            color: var(--inst-gray);
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 4px;
            border-radius: 6px;
            transition: all 0.2s var(--transition-smooth);
        }

        .manage-labels-btn:hover {
            background: var(--hover-bg);
            color: var(--nature-red);
            transform: rotate(90deg);
        }

        /* Label Items */
        .label-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 16px;
            text-decoration: none;
            color: var(--inst-gray);
            font-size: 14px;
            font-weight: 500;
            border-radius: 8px;
            margin-bottom: 2px;
            transition: all 0.2s var(--transition-smooth);
        }

        .label-item:hover {
            background: var(--hover-bg);
            color: var(--inst-black);
            transform: translateX(2px);
        }

        .label-content {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .label-dot {
            width: 12px;
            height: 12px;
            border-radius: 3px;
            box-shadow: var(--shadow-sm);
            transition: transform 0.2s var(--transition-smooth);
        }

        .label-item:hover .label-dot {
            transform: scale(1.2);
        }

        .label-count {
            font-size: 12px;
            font-weight: 700;
            color: var(--inst-light-gray);
            background: var(--inst-border);
            padding: 2px 8px;
            border-radius: 12px;
            min-width: 24px;
            text-align: center;
        }

        /* User Footer */
        .user-footer {
            padding: 20px;
            border-top: 1px solid var(--inst-border);
            background: linear-gradient(180deg, #ffffff 0%, #fafbfc 100%);
        }

        .user-card {
            background: white;
            border: 1px solid var(--inst-border);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s var(--transition-smooth);
        }

        .user-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .auth-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 10px;
            font-weight: 700;
            color: #16a34a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            background: #f0fdf4;
            padding: 4px 8px;
            border-radius: 6px;
        }

        .auth-badge::before {
            content: '●';
            font-size: 8px;
        }

        .user-email {
            font-size: 13px;
            font-weight: 600;
            color: var(--inst-black);
            word-break: break-all;
            display: block;
        }

        /* Footer Actions */
        .footer-actions {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            flex: 1;
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
            text-align: center;
            padding: 10px 12px;
            border-radius: 8px;
            transition: all 0.25s var(--transition-smooth);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .config-btn {
            color: var(--inst-gray);
            background: var(--hover-bg);
            border: 1px solid var(--inst-border);
        }

        .config-btn:hover {
            background: var(--inst-black);
            color: white;
            border-color: var(--inst-black);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .logout-btn {
            color: white;
            background: var(--nature-red);
            border: 1px solid var(--nature-red);
        }

        .logout-btn:hover {
            background: var(--nature-red-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(161, 4, 32, 0.3);
        }

        .action-btn .material-icons-round {
            font-size: 16px;
        }

        /* Sidebar Overlay */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(15, 20, 25, 0.6);
            backdrop-filter: blur(4px);
            z-index: 999;
            opacity: 0;
            transition: opacity 0.3s var(--transition-smooth);
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
                z-index: var(--z-index-sidebar);
                box-shadow: var(--shadow-lg);
            }

            .sidebar.open {
                transform: translateX(0);
            }

            :root {
                --sidebar-width: 280px;
            }
        }

        /* Smooth Scroll */
        html {
            scroll-behavior: smooth;
        }

        /* Focus States for Accessibility */
        .nav-item:focus,
        .label-item:focus,
        .action-btn:focus,
        .manage-labels-btn:focus {
            outline: 2px solid var(--nature-red);
            outline-offset: 2px;
        }
    </style>
</head>

<body>

    <button class="mobile-toggle" onclick="toggleSidebar()" aria-label="Toggle Menu">
        <span class="material-icons-round">menu</span>
    </button>

    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <div class="sidebar" id="mainSidebar">
        <!-- Header -->
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

        <!-- Navigation -->
        <nav class="nav-section">
            <a href="index.php" class="nav-item <?= ($current_page == 'index') ? 'active' : ''; ?>">
                <span class="material-icons-round">edit_note</span>
                <span>Compose</span>
            </a>

            <a href="sent_history.php" class="nav-item <?= ($current_page == 'sent_history') ? 'active' : ''; ?>">
                <span class="material-icons-round">history</span>
                <span>All Mail</span>
            </a>

            <a href="send.php" class="nav-item <?= ($current_page == 'send') ? 'active' : ''; ?>">
                <span class="material-icons-round">analytics</span>
                <span>Analytics</span>
            </a>

            <!-- Labels Section -->
            <div class="nav-section-title">
                Labels
                <a href="manage_labels.php" class="manage-labels-btn" title="Manage Labels">
                    <span class="material-icons-round" style="font-size: 18px;">settings</span>
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

        <!-- User Footer -->
        <div class="user-footer">
            <div class="user-card">
                <span class="auth-badge">Authenticated</span>
                <span class="user-email">
                    <?= htmlspecialchars($userEmail) ?>
                </span>
            </div>

            <div class="footer-actions">
                <a href="settings.php" class="action-btn config-btn">
                    <span class="material-icons-round">tune</span>
                    <span>Config</span>
                </a>
                <a href="logout.php" class="action-btn logout-btn">
                    <span class="material-icons-round">logout</span>
                    <span>Sign Out</span>
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

        // Close sidebar on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && document.getElementById('mainSidebar').classList.contains('open')) {
                toggleSidebar();
            }
        });

        // Smooth scroll behavior
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    </script>
</body>

</html>