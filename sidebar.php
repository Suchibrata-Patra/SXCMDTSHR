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
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        :root {
            /* macOS System Colors */
            --macos-blue: #007AFF;
            --macos-blue-hover: #0051D5;
            
            /* Apple Text Colors (Light Mode) */
            --label-primary: #1C1C1E;
            --label-secondary: rgba(60, 60, 67, 0.70);
            --label-tertiary: rgba(60, 60, 67, 0.45);
            --label-quaternary: rgba(60, 60, 67, 0.25);
            
            /* Glass Effect */
            --glass-bg: rgba(255, 255, 255, 0.55);
            --glass-border: rgba(0, 0, 0, 0.06);
            --glass-inner-glow: rgba(255, 255, 255, 0.35);
            
            /* Shadows */
            --shadow-sidebar: 0 6px 22px rgba(0, 0, 0, 0.06);
            --shadow-card: 0 4px 14px rgba(0, 0, 0, 0.08);
            
            /* Active States */
            --active-bg: rgba(0, 122, 255, 0.12);
            --hover-bg: rgba(0, 0, 0, 0.03);
            
            /* Spacing (8px rhythm) */
            --spacing-xs: 4px;
            --spacing-sm: 8px;
            --spacing-md: 12px;
            --spacing-lg: 16px;
            --spacing-xl: 22px;
            --spacing-2xl: 30px;
            
            /* macOS Transitions */
            --transition-default: all 0.20s cubic-bezier(0.25, 0.1, 0.25, 1);
            --transition-bounce: all 0.30s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Text', 'Inter', system-ui, sans-serif;
            background: #F5F5F7;
            color: var(--label-primary);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
        }

        /* ========== SIDEBAR CONTAINER ========== */
        .sidebar {
            width: 280px;
            height: 100vh;
            background: var(--glass-bg);
            backdrop-filter: blur(30px) saturate(180%);
            -webkit-backdrop-filter: blur(30px) saturate(180%);
            border-right: 1px solid var(--glass-border);
            box-shadow: inset 0 0 0 0.5px var(--glass-inner-glow), var(--shadow-sidebar);
            display: flex;
            flex-direction: column;
            position: sticky;
            top: 0;
            z-index: 1000;
            overflow: hidden;
        }

        /* ========== HEADER / LOGO AREA ========== */
        .sidebar-header {
            padding: var(--spacing-xl) var(--spacing-xl) 18px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.04);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            text-decoration: none;
            transition: var(--transition-default);
        }

        .logo:hover {
            opacity: 0.85;
        }

        .logo-image {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            object-fit: cover;
            background: white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .logo-text {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .logo-title {
            font-size: 17px;
            font-weight: 600;
            letter-spacing: -0.4px;
            color: var(--label-primary);
            line-height: 1.2;
        }

        .logo-subtitle {
            font-size: 11px;
            font-weight: 500;
            color: var(--label-tertiary);
            letter-spacing: 0.3px;
        }

        /* ========== NAVIGATION SECTION ========== */
        .nav-section {
            flex: 1;
            padding: 18px var(--spacing-xl);
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-width: none;
        }

        .nav-section::-webkit-scrollbar {
            display: none;
        }

        /* Navigation Item (Compose, Sent, Trash, Analytics) */
        .nav-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            padding: 8px var(--spacing-md);
            text-decoration: none;
            color: var(--label-secondary);
            font-size: 15px;
            font-weight: 500;
            border-radius: 10px;
            margin-bottom: 4px;
            transition: var(--transition-default);
            position: relative;
            cursor: pointer;
        }

        .nav-item:hover {
            background: var(--hover-bg);
            color: var(--label-primary);
        }

        .nav-item.active {
            background: var(--active-bg);
            color: var(--macos-blue);
            font-weight: 600;
        }

        .nav-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 20px;
            background: var(--macos-blue);
            border-radius: 0 2px 2px 0;
        }

        .nav-item .material-icons-round {
            font-size: 18px;
            color: #8E8E93;
            transition: var(--transition-default);
        }

        .nav-item:hover .material-icons-round {
            color: var(--label-primary);
        }

        .nav-item.active .material-icons-round {
            color: var(--macos-blue);
        }

        /* ========== LABELS SECTION ========== */
        .nav-section-divider {
            height: var(--spacing-2xl);
        }

        .nav-section-title {
            font-size: 11px;
            font-weight: 600;
            color: var(--label-tertiary);
            padding: var(--spacing-sm) var(--spacing-md) var(--spacing-sm);
            text-transform: uppercase;
            letter-spacing: 0.6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-sm);
        }

        .label-settings-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 6px;
            background: transparent;
            border: none;
            cursor: pointer;
            transition: var(--transition-default);
            text-decoration: none;
        }

        .label-settings-btn .material-icons-round {
            font-size: 16px;
            color: var(--label-tertiary);
            transition: var(--transition-default);
        }

        .label-settings-btn:hover {
            background: rgba(0, 0, 0, 0.04);
        }

        .label-settings-btn:hover .material-icons-round {
            color: var(--label-secondary);
        }

        /* Label Items */
        .label-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 7px var(--spacing-md);
            text-decoration: none;
            color: var(--label-secondary);
            font-size: 13px;
            font-weight: 400;
            border-radius: 8px;
            margin-bottom: 4px;
            transition: var(--transition-default);
        }

        .label-item:hover {
            background: rgba(0, 0, 0, 0.025);
            color: var(--label-primary);
        }

        .label-content {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            min-width: 0;
        }

        .label-dot {
            width: 12px;
            height: 12px;
            border-radius: 3px;
            flex-shrink: 0;
            opacity: 0.85;
        }

        .label-name {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .label-count {
            font-size: 11px;
            font-weight: 600;
            background: rgba(0, 0, 0, 0.05);
            color: var(--label-tertiary);
            padding: 2px 7px;
            border-radius: 10px;
            min-width: 22px;
            text-align: center;
        }

        /* ========== BOTTOM USER PANEL ========== */
        .user-footer {
            padding: var(--spacing-lg) var(--spacing-xl) var(--spacing-xl);
            background: rgba(255, 255, 255, 0.15);
            border-top: 1px solid rgba(0, 0, 0, 0.04);
        }

        .user-card {
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            padding: var(--spacing-lg);
            border-radius: 14px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: var(--shadow-card);
            margin-bottom: var(--spacing-md);
            transition: var(--transition-default);
        }

        .user-card:hover {
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.10);
        }

        .verified-badge-row {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 6px;
        }

        .verified-badge {
            display: flex;
            align-items: center;
        }

        .verified-badge img {
            width: 14px;
            height: 14px;
            object-fit: contain;
        }

        .verified-text {
            font-size: 10px;
            font-weight: 700;
            color: var(--macos-blue);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .user-email {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--label-secondary);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            padding-left: 2px;
        }

        /* ========== ACTION BUTTONS ========== */
        .footer-actions {
            display: flex;
            gap: var(--spacing-sm);
        }

        .action-btn {
            flex: 1;
            padding: 10px var(--spacing-md);
            border-radius: 10px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            text-align: center;
            transition: var(--transition-default);
            cursor: pointer;
            border: none;
            font-family: inherit;
        }

        .config-btn {
            background: rgba(255, 255, 255, 0.6);
            color: var(--label-primary);
            border: 1px solid rgba(0, 0, 0, 0.09);
        }

        .config-btn:hover {
            background: rgba(255, 255, 255, 0.85);
            border-color: rgba(0, 0, 0, 0.12);
        }

        .config-btn:active {
            transform: scale(0.97);
            transition: transform 0.08s;
        }

        .logout-btn {
            background: #1C1C1E;
            color: white;
        }

        .logout-btn:hover {
            background: #000000;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.18);
        }

        .logout-btn:active {
            transform: scale(0.97);
            transition: transform 0.08s;
        }

        /* ========== SMOOTH SCROLLBAR (macOS Style) ========== */
        .nav-section {
            scrollbar-width: thin;
            scrollbar-color: rgba(0, 0, 0, 0.2) transparent;
        }

        .nav-section::-webkit-scrollbar {
            width: 6px;
        }

        .nav-section::-webkit-scrollbar-track {
            background: transparent;
        }

        .nav-section::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.15);
            border-radius: 10px;
        }

        .nav-section::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 0, 0, 0.25);
        }

        /* ========== ACCESSIBILITY & FOCUS STATES ========== */
        .nav-item:focus-visible,
        .label-item:focus-visible,
        .action-btn:focus-visible {
            outline: 2px solid var(--macos-blue);
            outline-offset: 2px;
        }

        /* ========== RESPONSIVE ADJUSTMENTS ========== */
        @media (max-width: 1024px) {
            .sidebar {
                width: 260px;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 240px;
            }
            
            .sidebar-header {
                padding: var(--spacing-lg) var(--spacing-lg) 14px;
            }
            
            .nav-section {
                padding: 14px var(--spacing-lg);
            }
            
            .user-footer {
                padding: var(--spacing-md) var(--spacing-lg) var(--spacing-lg);
            }
        }
    </style>
</head>

<body>
    <div class="sidebar" id="mainSidebar">
        <!-- Logo Header -->
        <div class="sidebar-header">
            <a href="index.php" class="logo">
                <img src="https://upload.wikimedia.org/wikipedia/en/b/b0/St._Xavier%27s_College%2C_Kolkata_logo.jpg"
                    alt="SXC Logo"
                    class="logo-image">
                <div class="logo-text">
                    <span class="logo-title">SXC MDTS</span>
                    <span class="logo-subtitle">Version 1.2.15</span>
                </div>
            </a>
        </div>

        <!-- Navigation Section -->
        <nav class="nav-section">
            <!-- Main Navigation -->
            <a href="index.php" class="nav-item <?= ($current_page == 'index') ? 'active' : ''; ?>">
                <span class="material-icons-round">edit</span>
                <span>Compose</span>
            </a>
            <a href="sent_history.php" class="nav-item <?= ($current_page == 'sent_history') ? 'active' : ''; ?>">
                <span class="material-icons-round">send</span>
                <span>Sent</span>
            </a>
            <a href="deleted_items.php" class="nav-item <?= ($current_page == 'deleted_items') ? 'active' : ''; ?>">
                <span class="material-icons-round">delete</span>
                <span>Trash</span>
            </a>
            <a href="send.php" class="nav-item <?= ($current_page == 'send') ? 'active' : ''; ?>">
                <span class="material-icons-round">analytics</span>
                <span>Analytics</span>
            </a>

            <!-- Divider -->
            <div class="nav-section-divider"></div>

            <!-- Labels Section -->
            <div class="nav-section-title">
                Labels
                <a href="manage_labels.php" class="label-settings-btn" aria-label="Manage Labels">
                    <span class="material-icons-round">settings</span>
                </a>
            </div>

            <?php foreach ($sidebarLabels as $label): ?>
            <a href="sent_history.php?label_id=<?= $label['id'] ?>" class="label-item">
                <div class="label-content">
                    <div class="label-dot" style="background-color: <?= htmlspecialchars($label['label_color']) ?>;"></div>
                    <span class="label-name"><?= htmlspecialchars($label['label_name']) ?></span>
                </div>
                <?php if (isset($label['count']) && $label['count'] > 0): ?>
                <span class="label-count"><?= $label['count'] ?></span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </nav>

        <!-- User Footer -->
        <div class="user-footer">
            <div class="user-card">
                <div class="verified-badge-row">
                    <span class="verified-badge">
                        <img src="/Assets/image/Verified_badge.png" alt="Verified">
                    </span>
                    <span class="verified-text">Verified Account</span>
                </div>
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