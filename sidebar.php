<?php
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$userEmail = $_SESSION['smtp_user'] ?? 'user@example.com';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --apple-blue: #007AFF;
            --apple-gray: #8E8E93;
            --sidebar-bg: rgba(242, 242, 247, 0.7); /* Translucent Apple Gray */
            --active-bg: rgba(0, 0, 0, 0.05);
            --border-color: rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            margin: 0;
            background: #fff;
        }

        /* Sidebar Container */
        .sidebar {
            width: 260px;
            height: 100vh;
            background: var(--sidebar-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            position: fixed;
            left: 0;
            top: 0;
        }

        /* Header / Logo */
        .sidebar-header {
            padding: 30px 20px 20px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: #000;
        }

        .logo-image {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            filter: grayscale(1); /* Matches the minimal Apple look */
        }

        .logo-title {
            font-size: 15px;
            font-weight: 700;
            letter-spacing: -0.2px;
        }

        /* Navigation */
        .nav-section {
            flex: 1;
            padding: 10px;
            overflow-y: auto;
        }

        .nav-group-label {
            font-size: 11px;
            font-weight: 600;
            color: var(--apple-gray);
            padding: 15px 12px 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            text-decoration: none;
            color: #1c1c1e;
            font-size: 14px;
            font-weight: 500;
            border-radius: 8px;
            transition: 0.2s;
            margin-bottom: 2px;
        }

        .nav-item:hover {
            background: rgba(0, 0, 0, 0.04);
        }

        .nav-item.active {
            background: var(--active-bg);
            color: var(--apple-blue);
        }

        .nav-item.active .material-icons {
            color: var(--apple-blue);
        }

        .material-icons {
            font-size: 20px;
            color: #555;
        }

        /* Labels */
        .label-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            border: 1.5px solid rgba(0,0,0,0.1);
        }

        .label-count {
            margin-left: auto;
            font-size: 11px;
            color: var(--apple-gray);
            background: rgba(0,0,0,0.05);
            padding: 2px 6px;
            border-radius: 6px;
        }

        /* User Footer */
        .user-footer {
            padding: 20px;
            border-top: 1px solid var(--border-color);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .avatar {
            width: 36px;
            height: 36px;
            background: var(--apple-blue);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }

        .user-details {
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .user-email {
            font-size: 12px;
            font-weight: 500;
            color: #1c1c1e;
            white-space: nowrap;
            text-overflow: ellipsis;
            overflow: hidden;
        }

        .footer-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .action-btn {
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            padding: 8px;
            border-radius: 6px;
            text-align: center;
            transition: 0.2s;
        }

        .config-btn {
            background: #fff;
            color: #1c1c1e;
            border: 1px solid var(--border-color);
        }

        .logout-btn {
            background: rgba(255, 59, 48, 0.1);
            color: #FF3B30;
        }

        .logout-btn:hover {
            background: #FF3B30;
            color: #fff;
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-header">
            <a href="index.php" class="logo">
                <img src="https://upload.wikimedia.org/wikipedia/en/b/b0/St._Xavier%27s_College%2C_Kolkata_logo.jpg" class="logo-image">
                <span class="logo-title">SXC MDTS</span>
            </a>
        </div>

        <nav class="nav-section">
            <div class="nav-group-label">Mailbox</div>
            <a href="index.php" class="nav-item <?= ($current_page == 'index') ? 'active' : ''; ?>">
                <span class="material-icons">alternate_email</span>
                <span>Compose</span>
            </a>
            <a href="sent_history.php" class="nav-item <?= ($current_page == 'sent_history') ? 'active' : ''; ?>">
                <span class="material-icons">send</span>
                <span>Sent</span>
            </a>
            <a href="deleted_items.php" class="nav-item <?= ($current_page == 'deleted_items') ? 'active' : ''; ?>">
                <span class="material-icons">delete_outline</span>
                <span>Trash</span>
            </a>

            <div class="nav-group-label">Labels</div>
            <?php foreach ($sidebarLabels as $label): ?>
            <a href="sent_history.php?label_id=<?= $label['id'] ?>" class="nav-item">
                <div class="label-dot" style="background-color: <?= $label['label_color'] ?>;"></div>
                <span><?= htmlspecialchars($label['label_name']) ?></span>
                <?php if ($label['count'] > 0): ?>
                    <span class="label-count"><?= $label['count'] ?></span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </nav>

        <div class="user-footer">
            <div class="user-info">
                <div class="avatar"><?= strtoupper(substr($userEmail, 0, 1)) ?></div>
                <div class="user-details">
                    <span class="user-email"><?= htmlspecialchars($userEmail) ?></span>
                </div>
            </div>
            <div class="footer-actions">
                <a href="settings.php" class="action-btn config-btn">Settings</a>
                <a href="logout.php" class="action-btn logout-btn">Logout</a>
            </div>
        </div>
    </div>

</body>
</html>