<?php
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$userEmail = $_SESSION['smtp_user'] ?? 'user@example.com';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 280px;
            /* Apple Ultra-Premium Palette */
            --apple-blue: #007AFF;
            --apple-bg: #ffffff;
            /* Silicon-style translucency */
            --glass-sidebar: rgba(255, 255, 255, 0.4); 
            --glass-border: rgba(0, 0, 0, 0.06);
            --text-main: #1d1d1f;
            --text-secondary: #86868b;
            --active-item-bg: rgba(0, 122, 255, 0.08);
            --transition-smooth: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        body {
            font-family: 'SF Pro Display', 'Inter', -apple-system, sans-serif;
            background: #f5f5f7; /* Classic Apple Product Background */
            color: var(--text-main);
            margin: 0;
            display: flex;
        }

        /* The Ultra-Premium Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--glass-sidebar);
            backdrop-filter: blur(40px) saturate(200%);
            -webkit-backdrop-filter: blur(40px) saturate(200%);
            border-right: 1px solid var(--glass-border);
            display: flex;
            flex-direction: column;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        /* Refined Header */
        .sidebar-header {
            padding: 40px 24px 20px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 14px;
            text-decoration: none;
        }

        .logo-image {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            filter: saturate(1.1);
        }

        .logo-title {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: -0.5px;
            color: var(--text-main);
        }

        .logo-subtitle {
            font-size: 10px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 2px;
        }

        /* Premium Navigation */
        .nav-section {
            flex: 1;
            padding: 16px 12px;
            overflow-y: auto;
        }

        .nav-section-title {
            font-size: 11px;
            font-weight: 600;
            color: var(--text-secondary);
            padding: 24px 16px 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
            text-decoration: none;
            color: var(--text-main);
            font-size: 14px;
            font-weight: 500;
            border-radius: 12px;
            margin-bottom: 4px;
            transition: var(--transition-smooth);
        }

        .nav-item:hover {
            background: rgba(0, 0, 0, 0.03);
            transform: translateX(2px);
        }

        .nav-item.active {
            background: var(--active-item-bg);
            color: var(--apple-blue);
            font-weight: 600;
        }

        .material-icons-round {
            font-size: 22px;
            color: var(--text-secondary);
            transition: var(--transition-smooth);
        }

        .nav-item.active .material-icons-round {
            color: var(--apple-blue);
        }

        /* Label Pills */
        .label-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 16px;
            text-decoration: none;
            color: var(--text-main);
            font-size: 13.5px;
            border-radius: 10px;
            margin-bottom: 2px;
            transition: var(--transition-smooth);
        }

        .label-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            box-shadow: 0 0 0 3px rgba(255,255,255,0.8);
        }

        .label-count {
            font-size: 11px;
            font-weight: 600;
            background: rgba(0,0,0,0.05);
            color: var(--text-secondary);
            padding: 2px 8px;
            border-radius: 20px;
        }

        /* The "Card" Footer */
        .user-footer {
            padding: 24px;
            background: rgba(255, 255, 255, 0.2);
            border-top: 1px solid var(--glass-border);
        }

        .user-card {
            background: white;
            padding: 16px;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.04);
            margin-bottom: 16px;
            border: 1px solid rgba(255,255,255,0.8);
        }

        .auth-badge {
            display: inline-block;
            background: #34C759;
            color: white;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 9px;
            font-weight: 800;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .user-email {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-main);
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* High-Gloss Action Buttons */
        .footer-actions {
            display: flex;
            gap: 10px;
        }

        .action-btn {
            flex: 1;
            padding: 12px;
            border-radius: 12px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
            transition: var(--transition-smooth);
        }

        .config-btn {
            background: #fff;
            color: var(--text-main);
            border: 1px solid var(--glass-border);
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .config-btn:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transform: translateY(-1px);
        }

        .logout-btn {
            background: #1d1d1f;
            color: white;
        }

        .logout-btn:hover {
            background: #000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        /* Smooth scrollbars */
        .nav-section::-webkit-scrollbar { width: 4px; }
        .nav-section::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.1); border-radius: 10px; }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); position: fixed; }
            .sidebar.open { transform: translateX(0); box-shadow: 20px 0 50px rgba(0,0,0,0.1); }
        }
    </style>
</head>

<body>
    <div class="sidebar" id="mainSidebar">
        <div class="sidebar-header">
            <a href="index.php" class="logo">
                <img src="https://upload.wikimedia.org/wikipedia/en/b/b0/St._Xavier%27s_College%2C_Kolkata_logo.jpg" class="logo-image">
                <div class="logo-text">
                    <span class="logo-title">SXC MDTS</span>
                    <span class="logo-subtitle">Faculty Console</span>
                </div>
            </a>
        </div>

        <nav class="nav-section">
            <div class="nav-group-label" style="padding-left:16px; font-size:10px; color:#86868b; text-transform:uppercase; letter-spacing:1px; margin-bottom:8px;">Workspace</div>
            
            <a href="index.php" class="nav-item <?= ($current_page == 'index') ? 'active' : ''; ?>">
                <span class="material-icons-round">edit_note</span>
                <span>Compose</span>
            </a>
            <a href="sent_history.php" class="nav-item <?= ($current_page == 'sent_history') ? 'active' : ''; ?>">
                <span class="material-icons-round">auto_graph</span>
                <span>Dispatch Log</span>
            </a>
            <a href="deleted_items.php" class="nav-item <?= ($current_page == 'deleted_items') ? 'active' : ''; ?>">
                <span class="material-icons-round">delete_outline</span>
                <span>Bin</span>
            </a>

            <div class="nav-section-title">Institutional Labels</div>

            <?php foreach ($sidebarLabels as $label): ?>
            <a href="sent_history.php?label_id=<?= $label['id'] ?>" class="label-item">
                <div style="display:flex; align-items:center; gap:12px;">
                    <div class="label-dot" style="background-color: <?= $label['label_color'] ?>;"></div>
                    <span><?= htmlspecialchars($label['label_name']) ?></span>
                </div>
                <?php if ($label['count'] > 0): ?>
                    <span class="label-count"><?= $label['count'] ?></span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </nav>

        <div class="user-footer">
            <div class="user-card">
                <span class="auth-badge">Verified Faculty</span>
                <span class="user-email"><?= htmlspecialchars($userEmail) ?></span>
            </div>
            <div class="footer-actions">
                <a href="settings.php" class="action-btn config-btn">Settings</a>
                <a href="logout.php" class="action-btn logout-btn">Sign Out</a>
            </div>
        </div>
    </div>
</body>
</html>