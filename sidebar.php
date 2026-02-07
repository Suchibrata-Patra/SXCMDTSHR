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
        href="https://fonts.googleapis.com/css2?family=Harding:wght@400;500;600;700&family=Inter:wght@400;500;600;700&family=Merriweather:wght@300;400;700&display=swap"
        rel="stylesheet">

    <style>
        :root {
            --sidebar-width: 260px;
            --nature-red: #c41e3a;
            --nature-red-hover: #a01629;
            --nature-dark: #1a1a1a;
            --nature-gray: #3d3d3d;
            --nature-medium-gray: #666666;
            --nature-light-gray: #8c8c8c;
            --nature-border: #d4d4d4;
            --nature-border-light: #e5e5e5;
            --nature-bg: #ffffff;
            --nature-bg-hover: #f8f8f8;
            --nature-bg-active: #fef2f4;
            --shadow-subtle: 0 1px 3px rgba(0, 0, 0, 0.05);
            --shadow-sm: 0 2px 6px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.1);
            --transition: cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #fafafa;
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
            background: var(--nature-bg);
            border: 1px solid var(--nature-border);
            color: var(--nature-dark);
            padding: 10px;
            border-radius: 8px;
            cursor: pointer;
            box-shadow: var(--shadow-sm);
            transition: all 0.2s var(--transition);
        }

        .mobile-toggle:hover {
            background: var(--nature-red);
            color: white;
            border-color: var(--nature-red);
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--nature-bg);
            border-right: 1px solid var(--nature-border);
            display: flex;
            flex-direction: column;
            height: 100vh;
            color: var(--nature-dark);
            transition: transform 0.3s var(--transition);
            position: sticky;
            top: 0;
            box-shadow: var(--shadow-subtle);
        }

        /* Header */
        .sidebar-header {
            padding: 20px 18px 16px;
            border-bottom: 1px solid var(--nature-border-light);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            transition: opacity 0.2s;
        }

        .logo:hover {
            opacity: 0.8;
        }

        .logo-image {
            width: 44px;
            height: 44px;
            object-fit: contain;
            border-radius: 4px;
            border: 1px solid var(--nature-border-light);
        }

        .logo-text {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .logo-title {
            font-family: 'Merriweather', serif;
            font-size: 18px;
            font-weight: 700;
            color: var(--nature-dark);
            line-height: 1.2;
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
            padding: 8px 12px;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: var(--nature-border) transparent;
        }

        .nav-section::-webkit-scrollbar {
            width: 5px;
        }

        .nav-section::-webkit-scrollbar-track {
            background: transparent;
        }

        .nav-section::-webkit-scrollbar-thumb {
            background: var(--nature-border);
            border-radius: 3px;
        }

        .nav-section::-webkit-scrollbar-thumb:hover {
            background: var(--nature-red);
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
            border-radius: 6px;
            margin-bottom: 2px;
            transition: all 0.2s var(--transition);
            position: relative;
        }

        .nav-item .material-icons-round {
            font-size: 20px;
            transition: transform 0.2s;
        }

        .nav-item:hover {
            background: var(--nature-bg-hover);
            color: var(--nature-dark);
        }

        .nav-item:hover .material-icons-round {
            transform: scale(1.08);
        }

        .nav-item.active {
            background: var(--nature-bg-active);
            color: var(--nature-red);
            font-weight: 600;
        }

        .nav-item.active .material-icons-round {
            color: var(--nature-red);
        }

        .nav-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            height: 18px;
            width: 3px;
            background: var(--nature-red);
            border-radius: 0 2px 2px 0;
        }

        /* Section Title */
        .nav-section-title {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--nature-medium-gray);
            padding: 16px 12px 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            letter-spacing: 1px;
        }

        .manage-labels-btn {
            color: var(--nature-light-gray);
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 4px;
            border-radius: 4px;
            transition: all 0.2s;
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
            font-weight: 500;
            border-radius: 6px;
            margin-bottom: 1px;
            transition: all 0.2s var(--transition);
        }

        .label-item:hover {
            background: var(--nature-bg-hover);
            color: var(--nature-dark);
        }

        .label-content {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .label-dot {
            width: 8px;
            height: 8px;
            border-radius: 2px;
            transition: transform 0.2s;
        }

        .label-item:hover .label-dot {
            transform: scale(1.2);
        }

        .label-count {
            font-size: 11px;
            font-weight: 600;
            color: var(--nature-medium-gray);
            background: var(--nature-border-light);
            padding: 2px 7px;
            border-radius: 10px;
            min-width: 20px;
            text-align: center;
        }

        .label-item:hover .label-count {
            background: var(--nature-red);
            color: white;
        }

        /* User Footer */
        .user-footer {
            padding: 14px;
            border-top: 1px solid var(--nature-border-light);
            background: #fafafa;
        }

        .user-card {
            background: white;
            border: 1px solid var(--nature-border-light);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
            transition: all 0.2s;
        }

        .user-card:hover {
            border-color: var(--nature-border);
            box-shadow: var(--shadow-sm);
        }

        .auth-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 9px;
            font-weight: 600;
            color: #059669;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .auth-badge::before {
            content: '';
            width: 5px;
            height: 5px;
            background: #059669;
            border-radius: 50%;
        }

        .user-email {
            font-size: 12px;
            font-weight: 500;
            color: var(--nature-gray);
            word-break: break-all;
            line-height: 1.4;
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
            text-align: center;
            padding: 9px 10px;
            border-radius: 6px;
            transition: all 0.2s var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            border: 1px solid;
        }

        .config-btn {
            color: var(--nature-gray);
            background: white;
            border-color: var(--nature-border);
        }

        .config-btn:hover {
            background: var(--nature-dark);
            color: white;
            border-color: var(--nature-dark);
        }

        .logout-btn {
            color: white;
            background: var(--nature-red);
            border-color: var(--nature-red);
        }

        .logout-btn:hover {
            background: var(--nature-red-hover);
            border-color: var(--nature-red-hover);
        }

        .action-btn .material-icons-round {
            font-size: 15px;
        }

        /* Sidebar Overlay */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.4);
            z-index: 999;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .sidebar-overlay.active {
            display: block;
            opacity: 1;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .mobile-toggle {
                display: flex;
            }

            .sidebar {
                position: fixed;
                left: 0;
                transform: translateX(-100%);
                z-index: 1000;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            :root {
                --sidebar-width: 280px;
            }
        }

        /* Focus States */
        .nav-item:focus,
        .label-item:focus,
        .action-btn:focus {
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
                    alt="SXC Logo" class="logo-image">
                <div class="logo-text">
                    <span class="logo-title">SXC MDTS</span>
                    <span class="logo-subtitle">Mail Distribution</span>
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

        <!-- User Footer -->
        <div class="user-footer">
            <div class="user-card">
                <span class="auth-badge">Verified</span>
                <span class="user-email">
                    <?= htmlspecialchars($userEmail) ?>
                </span>
            </div>

            <div class="footer-actions">
                <a href="settings.php" class="action-btn config-btn">
                    <span class="material-icons-round">tune</span>
                    <span>Settings</span>
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

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && document.getElementById('mainSidebar').classList.contains('open')) {
                toggleSidebar();
            }
        });
    </script>
</body>

</html>