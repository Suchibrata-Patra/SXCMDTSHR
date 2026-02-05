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

    <style>
        /* Sidebar */
        .sidebar {
            width: 280px;
            background: #ffffff;
            border-right: 1px solid #e5e5e5;
            display: flex;
            flex-direction: column;
            transition: width 0.3s ease;
            position: relative;
        }

        .sidebar.collapsed {
            width: 70px;
        }

        /* Header */
        .sidebar-header {
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #e5e5e5;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .logo img {
            height: 55px;
        }

        .toggle-sidebar {
            background: none;
            border: none;
            cursor: pointer;
            color: #555;
            padding: 6px;
            border-radius: 4px;
        }

        .toggle-sidebar:hover {
            background: #f2f2f2;
        }

        .sidebar.collapsed .toggle-sidebar {
            transform: rotate(180deg);
        }

        /* Navigation */
        .nav-section {
            flex: 1;
            padding: 16px 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 20px;
            text-decoration: none;
            color: #666;
            transition: 0.2s;
            border-left: 3px solid transparent;
        }

        .nav-item:hover {
            background: #f9f9f9;
            color: #111;
        }

        .nav-item.active {
            background: #f5f5f5;
            color: #111;
            border-left-color: #111;
            font-weight: 500;
        }

        .material-icons {
            font-size: 22px;
        }

        .sidebar.collapsed .nav-item span {
            display: none;
        }

        .sidebar.collapsed .nav-item {
            justify-content: center;
            padding: 12px;
        }

        /* User Info */
        .user-info {
            padding: 20px;
            border-top: 1px solid #e5e5e5;
        }

        .user-details {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .user-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, #111, #444);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 600;
            font-size: 16px;
        }

        .user-email {
            font-size: 13px;
            color: #666;
            overflow: hidden;
        }

        .user-email strong {
            display: block;
            color: #111;
            font-size: 14px;
        }

        .sidebar.collapsed .user-email {
            display: none;
        }

        /* Logout */
        .logout-link {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #d32f2f;
            text-decoration: none;
            padding: 10px 12px;
            border-radius: 6px;
            transition: 0.2s;
            font-weight: 500;
        }

        .logout-link:hover {
            background: #ffebee;
        }

        .sidebar.collapsed .logout-link span {
            display: none;
        }

        .sidebar.collapsed .logout-link {
            justify-content: center;
        }
    </style>
</head>
<body>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="index.php" class="logo">
            <img src="https://upload.wikimedia.org/wikipedia/en/b/b0/St._Xavier%27s_College%2C_Kolkata_logo.jpg" alt="Logo">
        </a>
        <button class="toggle-sidebar" id="toggleSidebar">
            <span class="material-icons">chevron_left</span>
        </button>
    </div>

    <nav class="nav-section">
        <a href="index.php" class="nav-item <?= ($current_page == 'index') ? 'active' : ''; ?>">
            <span class="material-icons">edit</span>
            <span>Compose</span>
        </a>

        <a href="save_settings.php" class="nav-item <?= ($current_page == 'settings') ? 'active' : ''; ?>">
            <span class="material-icons">settings</span>
            <span>Preference</span>
        </a>
    </nav>

    <div class="user-info">
        <div class="user-details">
            <div class="user-avatar"><?= $userInitial ?></div>
            <div class="user-email">
                <strong>ID</strong>
                <?= htmlspecialchars($userEmail) ?>
            </div>
        </div>

        <a href="logout.php" class="logout-link">
            <span class="material-icons">logout</span>
            <span>Logout</span>
        </a>
    </div>
</div>

<script>
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('toggleSidebar');

    toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');
    });
</script>

</body>
</html>
