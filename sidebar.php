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
            --background-gray: #f8f9fa;
            --border-light: #e9ecef;
            --text-primary: #212529;
            --text-secondary: #6c757d;
            --text-muted: #adb5bd;
            --hover-bg: #f1f3f5;
            --active-bg: #e9ecef;
            --accent-primary: #0d6efd;
            --accent-dark: #0a58ca;
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --transition: all 0.2s ease;
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
            width: 260px;
            background: var(--primary-white);
            border-right: 1px solid var(--border-light);
            display: flex;
            flex-direction: column;
            height: 100vh;
            position: relative;
        }

        /* Header Section */
        .sidebar-header {
            padding: 28px 24px;
            border-bottom: 1px solid var(--border-light);
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
            border-radius: 10px;
            object-fit: cover;
        }

        .logo-text {
            display: flex;
            flex-direction: column;
        }

        .logo-title {
            font-size: 17px;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.3;
            letter-spacing: -0.01em;
        }

        .logo-subtitle {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 2px;
            font-weight: 500;
        }

        /* Navigation Section */
        .nav-section {
            flex: 1;
            padding: 24px 16px;
            overflow-y: auto;
        }

        .nav-section::-webkit-scrollbar {
            width: 6px;
        }

        .nav-section::-webkit-scrollbar-track {
            background: transparent;
        }

        .nav-section::-webkit-scrollbar-thumb {
            background: var(--border-light);
            border-radius: 3px;
        }

        .nav-section::-webkit-scrollbar-thumb:hover {
            background: var(--text-muted);
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 14px 18px;
            text-decoration: none;
            color: var(--text-secondary);
            transition: var(--transition);
            border-radius: 8px;
            margin-bottom: 6px;
            position: relative;
            font-size: 15px;
            font-weight: 500;
        }

        .nav-item .material-icons {
            font-size: 24px;
            transition: var(--transition);
        }

        .nav-item:hover {
            background: var(--hover-bg);
            color: var(--text-primary);
        }

        .nav-item:hover .material-icons {
            transform: scale(1.1);
        }

        .nav-item.active {
            background: var(--active-bg);
            color: var(--accent-primary);
            font-weight: 600;
        }

        .nav-item.active .material-icons {
            color: var(--accent-primary);
        }

        .nav-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 24px;
            background: var(--accent-primary);
            border-radius: 0 4px 4px 0;
        }

        /* User Info Section */
        .user-info {
            padding: 20px;
            border-top: 1px solid var(--border-light);
            background: var(--background-gray);
        }

        .user-details {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px;
            background: var(--primary-white);
            border-radius: 10px;
            margin-bottom: 14px;
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            min-width: 48px;
            border-radius: 50%;
            background: var(--accent-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-white);
            font-weight: 700;
            font-size: 18px;
            position: relative;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        }

        .user-avatar::after {
            content: '';
            position: absolute;
            bottom: 2px;
            right: 2px;
            width: 12px;
            height: 12px;
            background: #17dd28;
            border: 2px solid var(--primary-white);
            border-radius: 50%;
        }

        .user-email-wrapper {
            flex: 1;
            overflow: hidden;
        }

        .user-label {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
            font-weight: 600;
        }

        .user-email {
            font-size: 14px;
            color: var(--text-primary);
            font-weight: 600;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Logout Button */
        .logout-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            color: #dc3545;
            text-decoration: none;
            padding: 14px 18px;
            border-radius: 8px;
            transition: var(--transition);
            font-weight: 600;
            font-size: 15px;
            background: var(--primary-white);
        }

        .logout-link:hover {
            background: #dc3545;
            color: var(--primary-white);
        }

        .logout-link:hover .material-icons {
            transform: translateX(2px);
        }

        .logout-link .material-icons {
            font-size: 22px;
            transition: var(--transition);
        }

        /* Responsive adjustments */
        @media (max-height: 700px) {
            .sidebar-header {
                padding: 20px 24px;
            }

            .nav-section {
                padding: 16px;
            }

            .user-info {
                padding: 16px;
            }
        }

        /* Smooth entry animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-5px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .nav-item {
            animation: fadeIn 0.3s ease-out backwards;
        }

        .nav-item:nth-child(1) {
            animation-delay: 0.05s;
        }

        .nav-item:nth-child(2) {
            animation-delay: 0.1s;
        }

        .nav-item:nth-child(3) {
            animation-delay: 0.15s;
        }

        .nav-item:nth-child(4) {
            animation-delay: 0.2s;
        }
    </style>
</head>

<body>

    <div class="sidebar">
        <div class="sidebar-header">
            <a href="index.php" class="logo">
                <img src="https://upload.wikimedia.org/wikipedia/en/b/b0/St._Xavier%27s_College%2C_Kolkata_logo.jpg"
                    alt="St. Xavier's College" class="logo-image">
                <div class="logo-text">
                    <span class="logo-title">SXC MDTS</span>
                    <span class="logo-subtitle">Inhouse Mail SAAS</span>
                    <span class="logo-subtitle">10.57.28.277</span>
                </div>
            </a>
        </div>

        <nav class="nav-section">
            <a href="index.php" class="nav-item <?= ($current_page == 'index') ? 'active' : ''; ?>">
                <span class="material-icons">edit</span>
                <span>Compose</span>
            </a>

            <a href="settings.php" class="nav-item <?= ($current_page == 'settings') ? 'active' : ''; ?>">
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
                    <div class="user-label">Signed in as</div>
                    <div class="user-email" title="<?= htmlspecialchars($userEmail) ?>">
                        <?= htmlspecialchars($userEmail) ?>
                    </div>
                </div>
            </div>

            <a href="logout.php" class="logout-link">
                <span class="material-icons">logout</span>
                <span>Logout</span>
            </a>
        </div>
    </div>

</body>

</html>