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
        /* ========== SF Pro Font Import ========== */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        :root {
            /* ========== Apple Color System (macOS Sonoma) ========== */
            --apple-blue: #007AFF;
            --apple-blue-hover: #0051D5;
            
            /* Apple Label Colors (Light Mode) */
            --label-primary: #1C1C1E;
            --label-secondary: rgba(60, 60, 67, 0.70);
            --label-tertiary: rgba(60, 60, 67, 0.45);
            --label-quaternary: rgba(60, 60, 67, 0.25);
            
            /* Icon Colors */
            --icon-inactive: #6E6E73;
            --icon-active: #007AFF;
            
            /* Glass Material System */
            --glass-panel: rgba(255, 255, 255, 0.55);
            --glass-border: rgba(0, 0, 0, 0.06);
            --glass-inner-glow: rgba(255, 255, 255, 0.35);
            --glass-card: rgba(255, 255, 255, 0.75);
            
            /* Shadows (Apple calibrated) */
            --shadow-panel: 0 6px 22px rgba(0, 0, 0, 0.06);
            --shadow-card: 0 4px 14px rgba(0, 0, 0, 0.08);
            
            /* Interactive States */
            --active-bg: rgba(0, 122, 255, 0.12);
            --hover-bg: rgba(0, 0, 0, 0.03);
            
            /* Apple 8px Rhythm */
            --space-4: 4px;
            --space-8: 8px;
            --space-10: 10px;
            --space-12: 12px;
            --space-16: 16px;
            --space-18: 18px;
            --space-22: 22px;
            --space-28: 28px;
            --space-30: 30px;
            
            /* Transitions (Apple-smooth) */
            --transition-ui: all 0.18s cubic-bezier(0.25, 0.1, 0.25, 1);
            --transition-press: transform 0.08s cubic-bezier(0.4, 0, 0.2, 1);
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
            font-feature-settings: "kern" 1;
        }

        /* ========== SIDEBAR CONTAINER ========== */
        .sidebar {
            width: 290px;
            height: 100vh;
            background: var(--glass-panel);
            backdrop-filter: blur(30px) saturate(180%);
            -webkit-backdrop-filter: blur(30px) saturate(180%);
            border-right: 1px solid var(--glass-border);
            box-shadow: inset 0 0 0 0.5px var(--glass-inner-glow), var(--shadow-panel);
            display: flex;
            flex-direction: column;
            position: sticky;
            top: 0;
            z-index: 1000;
            overflow: hidden;
        }

        /* ========== LOGO / HEADER AREA ========== */
        .sidebar-header {
            padding: var(--space-22) var(--space-22) var(--space-18);
            border-bottom: 1px solid rgba(0, 0, 0, 0.04);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 13px;
            text-decoration: none;
            transition: opacity 0.15s ease-out;
        }

        .logo:hover {
            opacity: 0.82;
        }

        .logo-image {
            width: 42px;
            height: 42px;
            border-radius: 9px;
            object-fit: cover;
            background: white;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
        }

        .logo-text {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .logo-title {
            font-size: 17px;
            font-weight: 600;
            letter-spacing: -0.42px;
            color: var(--label-primary);
            line-height: 1.2;
        }

        .logo-subtitle {
            font-size: 11px;
            font-weight: 400;
            color: var(--label-tertiary);
            letter-spacing: 0.06px;
        }

        /* ========== NAVIGATION SECTION ========== */
        .nav-section {
            flex: 1;
            padding: var(--space-18) var(--space-22);
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-width: none;
        }

        .nav-section::-webkit-scrollbar {
            display: none;
        }

        /* Navigation Items (Compose, Sent, Trash, Analytics) */
        .nav-item {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 7px 12px;
            text-decoration: none;
            color: var(--label-secondary);
            font-size: 15px;
            font-weight: 500;
            letter-spacing: -0.24px;
            border-radius: 10px;
            margin-bottom: var(--space-4);
            transition: var(--transition-ui);
            position: relative;
            cursor: pointer;
        }

        .nav-item:hover {
            background: var(--hover-bg);
            color: var(--label-primary);
        }

        .nav-item.active {
            background: var(--active-bg);
            color: var(--apple-blue);
            font-weight: 600;
        }

        /* Left accent strip for active item */
        .nav-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 18px;
            background: var(--apple-blue);
            border-radius: 0 1.5px 1.5px 0;
        }

        /* SF Symbols-style Icons */
        .nav-item .material-icons-round {
            font-size: 18px;
            font-weight: 400;
            color: var(--icon-inactive);
            transition: var(--transition-ui);
        }

        .nav-item:hover .material-icons-round {
            color: var(--label-primary);
        }

        .nav-item.active .material-icons-round {
            color: var(--icon-active);
            font-weight: 500;
        }

        /* ========== SECTION DIVIDER ========== */
        .nav-section-divider {
            height: var(--space-30);
        }

        /* ========== LABELS SECTION ========== */
        .nav-section-title {
            font-size: 11px;
            font-weight: 600;
            color: var(--label-tertiary);
            padding: var(--space-8) 12px var(--space-10);
            text-transform: uppercase;
            letter-spacing: 0.6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-8);
        }

        .label-settings-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            border-radius: 5px;
            background: transparent;
            border: none;
            cursor: pointer;
            transition: var(--transition-ui);
            text-decoration: none;
        }

        .label-settings-btn .material-icons-round {
            font-size: 16px;
            color: var(--label-tertiary);
            transition: var(--transition-ui);
        }

        .label-settings-btn:hover {
            background: rgba(0, 0, 0, 0.04);
        }

        .label-settings-btn:hover .material-icons-round {
            color: var(--label-secondary);
        }

        .label-settings-btn:active {
            transform: scale(0.96);
        }

        /* Label Items (Pastel chips like Apple Mail) */
        .label-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 6px 12px;
            text-decoration: none;
            color: var(--label-secondary);
            font-size: 13px;
            font-weight: 400;
            letter-spacing: -0.08px;
            border-radius: 7px;
            margin-bottom: var(--space-4);
            transition: var(--transition-ui);
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

        /* Pastel color dots (soft, Apple Mail style) */
        .label-dot {
            width: 10px;
            height: 10px;
            border-radius: 2.5px;
            flex-shrink: 0;
            opacity: 0.88;
            box-shadow: 0 0.5px 2px rgba(0, 0, 0, 0.08);
        }

        .label-name {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .label-count {
            font-size: 11px;
            font-weight: 600;
            background: rgba(0, 0, 0, 0.045);
            color: var(--label-tertiary);
            padding: 2px 7px;
            border-radius: 9px;
            min-width: 20px;
            text-align: center;
            letter-spacing: -0.07px;
        }

        /* ========== USER FOOTER / ACCOUNT PANEL ========== */
        .user-footer {
            padding: var(--space-16) var(--space-22) var(--space-22);
            background: rgba(255, 255, 255, 0.12);
            border-top: 1px solid rgba(0, 0, 0, 0.04);
        }

        .user-card {
            background: var(--glass-card);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            padding: var(--space-16);
            border-radius: 14px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: var(--shadow-card);
            margin-bottom: 12px;
            transition: var(--transition-ui);
        }

        .user-card:hover {
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.10);
            border-color: rgba(0, 0, 0, 0.07);
        }

        .verified-badge-row {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 7px;
        }

        .verified-badge {
            display: flex;
            align-items: center;
        }

        .verified-badge img {
            width: 13px;
            height: 13px;
            object-fit: contain;
        }

        .verified-text {
            font-size: 10px;
            font-weight: 700;
            color: var(--apple-blue);
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
            letter-spacing: -0.08px;
        }

        /* ========== ACTION BUTTONS (Settings / Sign Out) ========== */
        .footer-actions {
            display: flex;
            gap: var(--space-8);
        }

        .action-btn {
            flex: 1;
            padding: 9px 13px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            letter-spacing: -0.08px;
            text-align: center;
            transition: var(--transition-ui);
            cursor: pointer;
            border: none;
            font-family: inherit;
            display: inline-block;
        }

        /* Settings Button (Secondary) */
        .config-btn {
            background: rgba(255, 255, 255, 0.60);
            color: var(--label-secondary);
            border: 1px solid rgba(0, 0, 0, 0.08);
        }

        .config-btn:hover {
            background: rgba(255, 255, 255, 0.88);
            border-color: rgba(0, 0, 0, 0.11);
            color: var(--label-primary);
        }

        .config-btn:active {
            transform: scale(0.97);
            transition: var(--transition-press);
        }

        /* Sign Out Button (Primary) */
        .logout-btn {
            background: #0D0D0D;
            color: white;
            border: 1px solid transparent;
        }

        .logout-btn:hover {
            background: #000000;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.16);
        }

        .logout-btn:active {
            transform: scale(0.97);
            transition: var(--transition-press);
        }

        /* ========== SMOOTH SCROLLBAR (macOS native style) ========== */
        .nav-section {
            scrollbar-width: thin;
            scrollbar-color: rgba(0, 0, 0, 0.18) transparent;
        }

        .nav-section::-webkit-scrollbar {
            width: 5px;
        }

        .nav-section::-webkit-scrollbar-track {
            background: transparent;
        }

        .nav-section::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.14);
            border-radius: 10px;
        }

        .nav-section::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 0, 0, 0.22);
        }

        /* ========== ACCESSIBILITY & FOCUS STATES ========== */
        .nav-item:focus-visible,
        .label-item:focus-visible,
        .action-btn:focus-visible,
        .label-settings-btn:focus-visible {
            outline: 2px solid var(--apple-blue);
            outline-offset: 2px;
        }

        /* ========== RESPONSIVE REFINEMENTS ========== */
        @media (max-width: 1200px) {
            .sidebar {
                width: 270px;
            }
        }

        @media (max-width: 1024px) {
            .sidebar {
                width: 260px;
            }
            
            .sidebar-header {
                padding: var(--space-18) var(--space-18) var(--space-16);
            }
            
            .nav-section {
                padding: var(--space-16) var(--space-18);
            }
            
            .user-footer {
                padding: 14px var(--space-18) var(--space-18);
            }
        }

        /* ========== SUBTLE ANIMATIONS ========== */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-4px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .sidebar {
            animation: fadeIn 0.25s cubic-bezier(0.25, 0.1, 0.25, 1);
        }

        /* ========== LABEL COLOR PRESETS (Apple Mail Pastels) ========== */
        .label-dot[style*="#FF3B30"] { opacity: 0.85; } /* Red */
        .label-dot[style*="#FF9500"] { opacity: 0.85; } /* Orange */
        .label-dot[style*="#FFCC00"] { opacity: 0.85; } /* Yellow */
        .label-dot[style*="#34C759"] { opacity: 0.85; } /* Green */
        .label-dot[style*="#00C7BE"] { opacity: 0.85; } /* Teal */
        .label-dot[style*="#007AFF"] { opacity: 0.85; } /* Blue */
        .label-dot[style*="#5856D6"] { opacity: 0.85; } /* Purple */
        .label-dot[style*="#AF52DE"] { opacity: 0.85; } /* Pink */
    </style>
</head>

<body>
    <div class="sidebar" id="mainSidebar">
        <!-- ========== HEADER / LOGO ========== -->
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

        <!-- ========== NAVIGATION SECTION ========== -->
        <nav class="nav-section">
            <!-- Main Navigation Items -->
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

            <!-- Section Divider (30px gap) -->
            <div class="nav-section-divider"></div>

            <!-- Labels Section Header -->
            <div class="nav-section-title">
                Labels
                <a href="manage_labels.php" class="label-settings-btn" aria-label="Manage Labels">
                    <span class="material-icons-round">settings</span>
                </a>
            </div>

            <!-- Label Items -->
            <?php foreach ($sidebarLabels as $label): ?>
            <a href="sent_history.php?label_id=<?= $label['id'] ?>" class="label-item">
                <div class="label-content">
                    <div class="label-dot" 
                         style="background-color: <?= htmlspecialchars($label['label_color']) ?>;">
                    </div>
                    <span class="label-name"><?= htmlspecialchars($label['label_name']) ?></span>
                </div>
                <?php if (isset($label['count']) && $label['count'] > 0): ?>
                <span class="label-count"><?= $label['count'] ?></span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </nav>

        <!-- ========== USER FOOTER / ACCOUNT PANEL ========== -->
        <div class="user-footer">
            <!-- Floating Account Card -->
            <div class="user-card">
                <div class="verified-badge-row">
                    <span class="verified-badge">
                        <img src="/Assets/image/Verified_badge.png" alt="Verified">
                    </span>
                    <span class="verified-text">Verified Account</span>
                </div>
                <span class="user-email"><?= htmlspecialchars($userEmail) ?></span>
            </div>

            <!-- Action Buttons -->
            <div class="footer-actions">
                <a href="settings.php" class="action-btn config-btn">Settings</a>
                <a href="logout.php" class="action-btn logout-btn">Sign Out</a>
            </div>
        </div>
    </div>
</body>

</html>