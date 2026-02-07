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
    <title>Sidebar</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">

    <style>
        :root {
            --sidebar-width: 230px; /* Reduced width */
            --nature-black: #222222;
            --nature-gray: #757575;
            --nature-border: #dcdcdc;
            --nature-bg: #ffffff;
            --hover-subtle: #f6f6f6;
            --accent-red: #e4002b; /* Nature-inspired accent */
        }

        .sidebar {
            width: var(--sidebar-width);
            background: var(--nature-bg);
            border-right: 1px solid var(--nature-border);
            display: flex;
            flex-direction: column;
            height: 100vh;
            font-family: 'Inter', sans-serif;
        }

        /* Compact Header */
        .sidebar-header {
            padding: 20px 16px;
            border-bottom: 1px solid var(--nature-border);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .logo-image {
            width: 36px; /* Smaller logo */
            height: 36px;
            border-radius: 4px;
        }

        .logo-title {
            font-family: 'Playfair Display', serif; /* Academic feel */
            font-size: 15px;
            font-weight: 700;
            color: var(--nature-black);
            letter-spacing: -0.02em;
        }

        .logo-subtitle {
            font-size: 10px;
            color: var(--nature-gray);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Refined Navigation */
        .nav-section {
            flex: 1;
            padding: 16px 12px;
            overflow-y: auto;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px; /* Slimmer padding */
            text-decoration: none;
            color: var(--nature-black);
            border-radius: 4px;
            margin-bottom: 2px;
            font-size: 13.5px;
            transition: background 0.2s ease;
        }

        .nav-item .material-icons {
            font-size: 18px;
            color: var(--nature-gray);
        }

        .nav-item:hover {
            background: var(--hover-subtle);
        }

        .nav-item.active {
            background: var(--hover-subtle);
            font-weight: 600;
            border-left: 3px solid var(--nature-black);
        }

        .nav-section-title {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--nature-gray);
            padding: 16px 12px 8px;
        }

        .label-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 12px;
            text-decoration: none;
            color: var(--nature-gray);
            font-size: 12.5px;
        }

        .label-color-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        /* Elegant Bottom Section */
        .user-info {
            padding: 16px;
            border-top: 1px solid var(--nature-border);
            background: #fafafa;
        }

        .user-details {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }

        .user-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--nature-black);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
        }

        .user-email {
            font-size: 12px;
            color: var(--nature-black);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 140px;
        }

        .bottom-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logout-link {
            font-size: 12px;
            color: var(--nature-gray);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: color 0.2s;
        }

        .logout-link:hover {
            color: var(--accent-red);
        }

        .logout-link .material-icons {
            font-size: 16px;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="index.php" class="logo">
                <img src="https://upload.wikimedia.org/wikipedia/en/b/b0/St._Xavier%27s_College%2C_Kolkata_logo.jpg" alt="Logo" class="logo-image">
                <div class="logo-text">
                    <span class="logo-title">SXC MDTS</span>
                    <br>
                    <span class="logo-subtitle">10.57.20.282</span>
                </div>
            </a>
        </div>

        <nav class="nav-section">
            <a href="index.php" class="nav-item <?= ($current_page == 'index') ? 'active' : ''; ?>">
                <span class="material-icons">edit</span>
                <span>Compose</span>
            </a>
            <a href="send.php" class="nav-item <?= ($current_page == 'send') ? 'active' : ''; ?>">
                <span class="material-icons">analytics</span>
                <span>Sent Mail Info</span>
            </a>
            <a href="sent_history.php" class="nav-item <?= ($current_page == 'sent_history') ? 'active' : ''; ?>">
                <span class="material-icons">archive</span>
                <span>All Mail</span>
            </a>

            <div class="nav-section-title">Labels</div>
            <?php foreach ($sidebarLabels as $label): ?>
                <a href="sent_history.php?label_id=<?= $label['id'] ?>" class="label-item">
                    <div class="label-color-indicator" style="background-color: <?= htmlspecialchars($label['label_color']) ?>;"></div>
                    <span class="label-name"><?= htmlspecialchars($label['label_name']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="user-info">
            <div class="user-details">
                <div class="user-avatar"><?= $userInitial ?></div>
                <div class="user-email" title="<?= htmlspecialchars($userEmail) ?>"><?= htmlspecialchars($userEmail) ?></div>
            </div>
            <div class="bottom-actions">
                <a href="settings.php" class="logout-link">
                    <span class="material-icons">settings</span>
                    <span>Settings</span>
                </a>
                <a href="logout.php" class="logout-link">
                    <span class="material-icons">logout</span>
                    <span>Sign Out</span>
                </a>
            </div>
        </div>
    </div>
</body>
</html>