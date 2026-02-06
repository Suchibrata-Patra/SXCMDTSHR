<?php
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$userEmail = $_SESSION['smtp_user'] ?? 'user@example.com';
$userInitial = strtoupper(substr($userEmail, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Sidebar</title>

    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-white: #ffffff;
            --secondary-white: #fafafa;
            --border-gray: #e5e7eb;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --text-muted: #9ca3af;
            --hover-bg: #f9fafb;
            --active-bg: #f3f4f6;
            --accent-black: #111827;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Sidebar Container */
        .sidebar {
            width: 280px;
            background: var(--primary-white);
            border-right: 1px solid var(--border-gray);
            display: flex;
            flex-direction: column;
            transition: var(--transition);
            position: relative;
            height: 100vh;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .sidebar.collapsed {
            width: 72px;
        }

        /* Decorative top bar */
        .sidebar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent-black) 0%, transparent 100%);
            opacity: 0.1;
        }

        /* Header Section */
        .sidebar-header {
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border-gray);
            background: var(--secondary-white);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            transition: var(--transition);
        }

        .logo:hover {
            transform: translateX(2px);
        }

        .logo-wrapper {
            position: relative;
            width: 48px;
            height: 48px;
            border-radius: 12px;
            overflow: hidden;
            background: var(--primary-white);
            border: 2px solid var(--border-gray);
            padding: 3px;
            transition: var(--transition);
        }

        .logo:hover .logo-wrapper {
            border-color: var(--accent-black);
            box-shadow: var(--shadow-md);
        }

        .logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }

        .logo-text {
            display: flex;
            flex-direction: column;
            opacity: 1;
            transition: var(--transition);
        }

        .logo-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.02em;
        }

        .logo-subtitle {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 2px;
            font-weight: 600;
        }

        .sidebar.collapsed .logo-text {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }

        /* Toggle Button */
        .toggle-sidebar {
            background: var(--primary-white);
            border: 2px solid var(--border-gray);
            cursor: pointer;
            color: var(--text-secondary);
            padding: 8px;
            border-radius: 10px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-sm);
        }

        .toggle-sidebar:hover {
            background: var(--hover-bg);
            color: var(--text-primary);
            border-color: var(--accent-black);
            transform: scale(1.05);
        }

        .toggle-sidebar:active {
            transform: scale(0.95);
        }

        .toggle-sidebar .material-icons {
            font-size: 20px;
            transition: var(--transition);
        }

        .sidebar.collapsed .toggle-sidebar .material-icons {
            transform: rotate(180deg);
        }

        /* Navigation Section */
        .nav-section {
            flex: 1;
            padding: 20px 12px;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .nav-section::-webkit-scrollbar {
            width: 6px;
        }

        .nav-section::-webkit-scrollbar-track {
            background: transparent;
        }

        .nav-section::-webkit-scrollbar-thumb {
            background: var(--border-gray);
            border-radius: 3px;
        }

        .nav-section::-webkit-scrollbar-thumb:hover {
            background: var(--text-muted);
        }

        .nav-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            padding: 0 16px 8px 16px;
            margin-top: 16px;
            opacity: 1;
            transition: var(--transition);
        }

        .sidebar.collapsed .nav-label {
            opacity: 0;
            height: 0;
            padding: 0;
            margin: 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            text-decoration: none;
            color: var(--text-secondary);
            transition: var(--transition);
            border-radius: 12px;
            margin-bottom: 6px;
            position: relative;
            overflow: hidden;
            border: 1px solid transparent;
        }

        .nav-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 0;
            background: var(--accent-black);
            transition: var(--transition);
            border-radius: 0 3px 3px 0;
        }

        .nav-item:hover {
            background: var(--hover-bg);
            color: var(--text-primary);
            border-color: var(--border-gray);
        }

        .nav-item:hover::before {
            height: 60%;
        }

        .nav-item.active {
            background: var(--active-bg);
            color: var(--text-primary);
            font-weight: 600;
            border-color: var(--border-gray);
        }

        .nav-item.active::before {
            height: 100%;
        }

        .nav-item .material-icons {
            font-size: 22px;
            min-width: 22px;
        }

        .nav-item span:not(.material-icons) {
            font-size: 14px;
            font-weight: 500;
            white-space: nowrap;
            opacity: 1;
            transition: var(--transition);
        }

        .sidebar.collapsed .nav-item span:not(.material-icons) {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }

        .sidebar.collapsed .nav-item {
            justify-content: center;
            padding: 12px;
        }

        /* User Info Section */
        .user-info {
            padding: 20px;
            border-top: 1px solid var(--border-gray);
            background: var(--secondary-white);
        }

        .user-details {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            padding: 12px;
            background: var(--primary-white);
            border-radius: 12px;
            border: 2px solid var(--border-gray);
            transition: var(--transition);
        }

        .user-details:hover {
            border-color: var(--text-muted);
            box-shadow: var(--shadow-sm);
        }

        .user-avatar {
            width: 44px;
            height: 44px;
            min-width: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, #111827 0%, #374151 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-white);
            font-weight: 700;
            font-size: 17px;
            position: relative;
            border: 3px solid var(--border-gray);
            box-shadow: var(--shadow-sm);
        }

        .user-avatar::after {
            content: '';
            position: absolute;
            bottom: -2px;
            right: -2px;
            width: 14px;
            height: 14px;
            background: #10b981;
            border: 3px solid var(--primary-white);
            border-radius: 50%;
            box-shadow: var(--shadow-sm);
        }

        .user-email-wrapper {
            flex: 1;
            overflow: hidden;
            opacity: 1;
            transition: var(--transition);
        }

        .user-label {
            font-size: 10px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 3px;
            font-weight: 700;
        }

        .user-email {
            font-size: 13px;
            color: var(--text-primary);
            font-weight: 600;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .sidebar.collapsed .user-email-wrapper {
            opacity: 0;
            width: 0;
        }

        /* Logout Button */
        .logout-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            color: var(--text-secondary);
            text-decoration: none;
            padding: 12px 16px;
            border-radius: 12px;
            transition: var(--transition);
            font-weight: 600;
            font-size: 14px;
            border: 2px solid var(--border-gray);
            background: var(--primary-white);
        }

        .logout-link:hover {
            background: #fef2f2;
            border-color: #ef4444;
            color: #dc2626;
            box-shadow: var(--shadow-sm);
        }

        .logout-link:active {
            transform: scale(0.98);
        }

        .logout-link .material-icons {
            font-size: 20px;
        }

        .logout-link span:not(.material-icons) {
            opacity: 1;
            transition: var(--transition);
        }

        .sidebar.collapsed .logout-link span:not(.material-icons) {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }

        .sidebar.collapsed .logout-link {
            padding: 12px;
        }

        /* Tooltip for collapsed state */
        .sidebar.collapsed .nav-item,
        .sidebar.collapsed .logout-link {
            position: relative;
        }

        .sidebar.collapsed .nav-item::after,
        .sidebar.collapsed .logout-link::after {
            content: attr(data-tooltip);
            position: absolute;
            left: calc(100% + 12px);
            top: 50%;
            transform: translateY(-50%) translateX(-8px);
            background: var(--accent-black);
            color: var(--primary-white);
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: var(--transition);
            box-shadow: var(--shadow-md);
            z-index: 1000;
        }

        .sidebar.collapsed .nav-item::after::before,
        .sidebar.collapsed .logout-link::after::before {
            content: '';
            position: absolute;
            right: 100%;
            top: 50%;
            transform: translateY(-50%);
            border: 6px solid transparent;
            border-right-color: var(--accent-black);
        }

        .sidebar.collapsed .nav-item:hover::after,
        .sidebar.collapsed .logout-link:hover::after {
            opacity: 1;
            transform: translateY(-50%) translateX(0);
        }

        /* Collapsed state visual indicator */
        .sidebar.collapsed .toggle-sidebar {
            background: var(--active-bg);
        }

        /* Responsive adjustments */
        @media (max-height: 600px) {
            .sidebar-header {
                padding: 16px;
            }

            .nav-section {
                padding: 12px;
            }

            .user-info {
                padding: 16px;
            }
        }

        /* Smooth animations */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-10px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .nav-item,
        .user-details,
        .logout-link {
            animation: slideIn 0.3s ease-out;
        }

        /* Focus states for accessibility */
        .toggle-sidebar:focus,
        .nav-item:focus,
        .logout-link:focus {
            outline: 2px solid var(--accent-black);
            outline-offset: 2px;
        }
    </style>
</head>

<body>

    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="index.php" class="logo">
                <div class="logo-wrapper">
                    <img src="https://upload.wikimedia.org/wikipedia/en/b/b0/St._Xavier%27s_College%2C_Kolkata_logo.jpg"
                        alt="St. Xavier's College">
                </div>
                <div class="logo-text">
                    <span class="logo-title">St. Xavier's</span>
                    <span class="logo-subtitle">Mail System</span>
                </div>
            </a>
            <button class="toggle-sidebar" id="toggleSidebar" aria-label="Toggle Sidebar" type="button">
                <span class="material-icons">chevron_left</span>
            </button>
        </div>

        <nav class="nav-section">
            <div class="nav-label">Navigation</div>

            <a href="index.php" class="nav-item <?= ($current_page == 'index') ? 'active' : ''; ?>"
                data-tooltip="Compose Email">
                <span class="material-icons">edit</span>
                <span>Compose</span>
            </a>

            <a href="settings.php" class="nav-item <?= ($current_page == 'settings') ? 'active' : ''; ?>"
                data-tooltip="Preferences">
                <span class="material-icons">settings</span>
                <span>Preference</span>
            </a>
        </nav>

        <div class="user-info">
            <div class="user-details">
                <div class="user-avatar" title="<?= htmlspecialchars($userEmail) ?>">
                    <?= $userInitial ?>
                </div>
                <div class="user-email-wrapper">
                    <div class="user-label">Signed in as</div>
                    <div class="user-email" title="<?= htmlspecialchars($userEmail) ?>">
                        <?= htmlspecialchars($userEmail) ?>
                    </div>
                </div>
            </div>

            <a href="logout.php" class="logout-link" data-tooltip="Sign Out">
                <span class="material-icons">logout</span>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <script>
        (function () {
            'use strict';

            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('toggleSidebar');

            if (!sidebar || !toggleBtn) {
                console.error('Sidebar elements not found');
                return;
            }

            // Toggle sidebar function
            function toggleSidebar(event) {
                // Prevent default button behavior and stop propagation
                if (event) {
                    event.preventDefault();
                    event.stopPropagation();
                }

                sidebar.classList.toggle('collapsed');

                // Save state to localStorage
                const isCollapsed = sidebar.classList.contains('collapsed');
                try {
                    localStorage.setItem('sidebarCollapsed', isCollapsed.toString());
                } catch (e) {
                    console.warn('Could not save sidebar state:', e);
                }

                // Update aria-expanded for accessibility
                toggleBtn.setAttribute('aria-expanded', (!isCollapsed).toString());
            }

            // Add click event listener to toggle button
            toggleBtn.addEventListener('click', toggleSidebar);

            // Restore sidebar state on load
            function restoreSidebarState() {
                try {
                    const savedState = localStorage.getItem('sidebarCollapsed');
                    if (savedState === 'true') {
                        sidebar.classList.add('collapsed');
                        toggleBtn.setAttribute('aria-expanded', 'false');
                    } else {
                        toggleBtn.setAttribute('aria-expanded', 'true');
                    }
                } catch (e) {
                    console.warn('Could not restore sidebar state:', e);
                }
            }

            // Restore state when DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', restoreSidebarState);
            } else {
                restoreSidebarState();
            }

            // Optional: Keyboard shortcut to toggle sidebar (Ctrl/Cmd + B)
            document.addEventListener('keydown', function (event) {
                if ((event.ctrlKey || event.metaKey) && event.key === 'b') {
                    event.preventDefault();
                    toggleSidebar();
                }
            });

            // Prevent any accidental form submissions
            const navItems = sidebar.querySelectorAll('.nav-item');
            navItems.forEach(item => {
                item.addEventListener('click', function (e) {
                    // Allow normal navigation, just ensure it doesn't cause issues
                    e.stopPropagation();
                });
            });

        })();
    </script>

</body>

</html>