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
            --primary-black: #0a0a0a;
            --secondary-black: #1a1a1a;
            --border-gray: #2a2a2a;
            --text-primary: #ffffff;
            --text-secondary: #a0a0a0;
            --text-muted: #6b6b6b;
            --hover-bg: #222222;
            --active-bg: #2d2d2d;
            --accent-white: #ffffff;
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
            background: var(--primary-black);
            border-right: 1px solid var(--border-gray);
            display: flex;
            flex-direction: column;
            transition: var(--transition);
            position: relative;
            height: 100vh;
            overflow: hidden;
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
            height: 2px;
            background: linear-gradient(90deg, var(--accent-white) 0%, transparent 100%);
            opacity: 0.3;
        }

        /* Header Section */
        .sidebar-header {
            padding: 24px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border-gray);
            position: relative;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 14px;
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
            background: var(--secondary-black);
            border: 1px solid var(--border-gray);
            padding: 2px;
        }

        .logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 10px;
        }

        .logo-text {
            display: flex;
            flex-direction: column;
            opacity: 1;
            transition: var(--transition);
        }

        .logo-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
            letter-spacing: -0.02em;
        }

        .logo-subtitle {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 2px;
        }

        .sidebar.collapsed .logo-text {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }

        /* Toggle Button */
        .toggle-sidebar {
            background: var(--secondary-black);
            border: 1px solid var(--border-gray);
            cursor: pointer;
            color: var(--text-secondary);
            padding: 8px;
            border-radius: 8px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .toggle-sidebar:hover {
            background: var(--hover-bg);
            color: var(--text-primary);
            border-color: var(--text-muted);
        }

        .toggle-sidebar .material-icons {
            font-size: 20px;
        }

        .sidebar.collapsed .toggle-sidebar {
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
            font-weight: 600;
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
            border-radius: 10px;
            margin-bottom: 4px;
            position: relative;
            overflow: hidden;
        }

        .nav-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 0;
            background: var(--accent-white);
            transition: var(--transition);
            border-radius: 0 2px 2px 0;
        }

        .nav-item:hover {
            background: var(--hover-bg);
            color: var(--text-primary);
        }

        .nav-item:hover::before {
            height: 60%;
        }

        .nav-item.active {
            background: var(--active-bg);
            color: var(--text-primary);
            font-weight: 500;
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
            background: var(--secondary-black);
        }

        .user-details {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            padding: 12px;
            background: var(--primary-black);
            border-radius: 12px;
            border: 1px solid var(--border-gray);
            transition: var(--transition);
        }

        .user-details:hover {
            border-color: var(--text-muted);
        }

        .user-avatar {
            width: 44px;
            height: 44px;
            min-width: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ffffff 0%, #d0d0d0 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-black);
            font-weight: 700;
            font-size: 17px;
            position: relative;
            border: 2px solid var(--border-gray);
        }

        .user-avatar::after {
            content: '';
            position: absolute;
            bottom: 0;
            right: 0;
            width: 12px;
            height: 12px;
            background: #4ade80;
            border: 2px solid var(--primary-black);
            border-radius: 50%;
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
            margin-bottom: 2px;
            font-weight: 600;
        }

        .user-email {
            font-size: 13px;
            color: var(--text-primary);
            font-weight: 500;
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
            border-radius: 10px;
            transition: var(--transition);
            font-weight: 500;
            font-size: 14px;
            border: 1px solid var(--border-gray);
            background: var(--primary-black);
        }

        .logout-link:hover {
            background: #1a0000;
            border-color: #ff4444;
            color: #ff4444;
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
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            background: var(--accent-white);
            color: var(--primary-black);
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: var(--transition);
            margin-left: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .sidebar.collapsed .nav-item:hover::after,
        .sidebar.collapsed .logout-link:hover::after {
            opacity: 1;
        }

        /* Responsive adjustments */
        @media (max-height: 600px) {
            .sidebar-header {
                padding: 16px 20px;
            }

            .nav-section {
                padding: 12px;
            }

            .user-info {
                padding: 16px;
            }
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
            <button class="toggle-sidebar" id="toggleSidebar" aria-label="Toggle Sidebar">
                <span class="material-icons">chevron_left</span>
            </button>
        </div>

        <nav class="nav-section">
            <div class="nav-label">Main</div>

            <a href="index.php" class="nav-item <?= ($current_page == 'index') ? 'active' : ''; ?>"
                data-tooltip="Compose">
                <span class="material-icons">edit</span>
                <span>Compose</span>
            </a>

            <a href="settings.php" class="nav-item <?= ($current_page == 'settings') ? 'active' : ''; ?>"
                data-tooltip="Preference">
                <span class="material-icons">settings</span>
                <span>Preference</span>
            </a>
        </nav>

        <div class="user-info">
            <div class="user-details">
                <div class="user-avatar">
                    <?= $userInitial ?>
                </div>
                <div class="user-email-wrapper">
                    <div class="user-label">Logged in as</div>
                    <div class="user-email" title="<?= htmlspecialchars($userEmail) ?>">
                        <?= htmlspecialchars($userEmail) ?>
                    </div>
                </div>
            </div>

            <a href="logout.php" class="logout-link" data-tooltip="Logout">
                <span class="material-icons">logout</span>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.getElementById('toggleSidebar');

        // Toggle sidebar
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');

            // Save state to localStorage
            const isCollapsed = sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebarCollapsed', isCollapsed);
        });

        // Restore sidebar state on load
        window.addEventListener('DOMContentLoaded', () => {
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (isCollapsed) {
                sidebar.classList.add('collapsed');
            }
        });

        // Add smooth transitions after page load
        window.addEventListener('load', () => {
            sidebar.style.transition = 'var(--transition)';
        });
    </script>

</body>

</html>