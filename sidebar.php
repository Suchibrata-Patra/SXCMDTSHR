<?php
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$userEmail = $_SESSION['smtp_user'] ?? 'user@example.com';
$userInitial = strtoupper(substr($userEmail, 0, 1));

require_once 'db_config.php';
$sidebarLabels = getLabelCounts($userEmail);
$unlabeledCount = getUnlabeledEmailCount($userEmail);
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
<style>
    /* ══════════════════════════════════════════════════════════
       DRIVE UI DESIGN SYSTEM - SIDEBAR
       ══════════════════════════════════════════════════════════ */
    
    :root {
        /* Foundation Colors */
        --ink:       #1a1a2e;
        --ink-2:     #2d2d44;
        --ink-3:     #6b6b8a;
        --ink-4:     #a8a8c0;
        --bg:        #f0f0f7;
        --surface:   #ffffff;
        --surface-2: #f7f7fc;
        --border:    rgba(100,100,160,0.12);
        --border-2:  rgba(100,100,160,0.22);
        
        /* Accent Colors */
        --blue:      #5781a9;
        --blue-2:    #c6d3ea;
        --blue-glow: rgba(79,70,229,0.15);
        --green:     #10b981;
        --amber:     #f59e0b;
        --purple:    #8b5cf6;
        
        /* System */
        --r:         10px;
        --r-lg:      16px;
        --shadow:    0 1px 3px rgba(79,70,229,0.08), 0 4px 16px rgba(79,70,229,0.06);
        --shadow-lg: 0 8px 32px rgba(79,70,229,0.14), 0 2px 8px rgba(0,0,0,0.06);
        --ease:      cubic-bezier(.4,0,.2,1);
        --ease-spring: cubic-bezier(.34,1.56,.64,1);
    }

    /* ══════════════════════════════════════════════════════════
       SIDEBAR CONTAINER
       ══════════════════════════════════════════════════════════ */
    .sidebar {
        width: 260px;
        height: 100vh;
        background: var(--surface);
        border-right: 1px solid var(--border);
        display: flex;
        flex-direction: column;
        position: sticky;
        top: 0;
        z-index: 1000;
        overflow: hidden;
        font-family: 'DM Sans', -apple-system, sans-serif;
        -webkit-font-smoothing: antialiased;
    }

    /* ══════════════════════════════════════════════════════════
       LOGO / HEADER AREA
       ══════════════════════════════════════════════════════════ */
    .sidebar-header {
        padding: 24px 20px;
        border-bottom: 1px solid var(--border);
        background: var(--surface);
    }

    .logo {
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
        transition: opacity .2s var(--ease);
    }

    .logo:hover {
        opacity: 0.8;
    }

    .logo-image {
        width: auto;
        height: 42px;
        object-fit: cover;
        border-radius: 8px;
        background: white;
    }

    .logo-text {
        display: flex;
        flex-direction: column;
        gap: 3px;
    }

    .logo-title {
        font-size: 17px;
        font-weight: 700;
        letter-spacing: -0.4px;
        color: var(--ink);
        line-height: 1.1;
    }

    .logo-subtitle {
        font-size: 11px;
        font-weight: 500;
        color: var(--ink-3);
        letter-spacing: 0.3px;
        font-family: 'DM Mono', monospace;
    }

    /* ══════════════════════════════════════════════════════════
       NAVIGATION SECTION
       ══════════════════════════════════════════════════════════ */
    .nav-section {
        flex: 1;
        padding: 16px 12px;
        overflow-y: auto;
        overflow-x: hidden;
    }

    .nav-section::-webkit-scrollbar {
        width: 5px;
    }

    .nav-section::-webkit-scrollbar-track {
        background: transparent;
    }

    .nav-section::-webkit-scrollbar-thumb {
        background: var(--border-2);
        border-radius: 10px;
    }

    /* Navigation Items */
    .nav-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 9px 14px;
        text-decoration: none;
        color: var(--ink-2);
        font-size: 14px;
        font-weight: 500;
        letter-spacing: -0.1px;
        border-radius: 8px;
        margin-bottom: 4px;
        transition: all .18s var(--ease);
        position: relative;
        cursor: pointer;
    }

    .nav-item:hover {
        background: var(--surface-2);
        color: var(--ink);
    }

    .nav-item.active {
        background: var(--blue);
        color: white;
        font-weight: 600;
        box-shadow: 0 2px 8px rgba(87,129,169,0.25);
    }

    .nav-item.active:hover {
        background: var(--ink-2);
    }

    /* Icons */
    .nav-item .material-icons-round {
        font-size: 20px;
        color: var(--ink-3);
        transition: color .18s var(--ease);
    }

    .nav-item:hover .material-icons-round {
        color: var(--blue);
    }

    .nav-item.active .material-icons-round {
        color: white;
    }

    /* ══════════════════════════════════════════════════════════
       SECTION DIVIDER
       ══════════════════════════════════════════════════════════ */
    .nav-section-divider {
        height: 1px;
        background: var(--border);
        margin: 16px 8px;
    }

    /* ══════════════════════════════════════════════════════════
       LABELS SECTION
       ══════════════════════════════════════════════════════════ */
    .nav-section-title {
        font-size: 11px;
        font-weight: 700;
        color: var(--ink-3);
        padding: 12px 14px 8px;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 4px;
    }

    .label-settings-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        border-radius: 6px;
        background: transparent;
        border: none;
        cursor: pointer;
        transition: all .18s var(--ease);
        text-decoration: none;
    }

    .label-settings-btn:hover {
        background: var(--surface-2);
    }

    .label-settings-btn .material-icons-round {
        font-size: 16px;
        color: var(--ink-3);
    }

    .label-settings-btn:hover .material-icons-round {
        color: var(--blue);
    }

    /* Label Items */
    .label-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 14px;
        text-decoration: none;
        color: var(--ink-2);
        font-size: 13px;
        font-weight: 500;
        border-radius: 8px;
        margin-bottom: 3px;
        transition: all .18s var(--ease);
        cursor: pointer;
    }

    .label-item:hover {
        background: var(--surface-2);
        color: var(--ink);
    }

    .label-content {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
        flex: 1;
    }

    .label-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        flex-shrink: 0;
        box-shadow: 0 1px 3px rgba(0,0,0,0.15);
    }

    .label-name {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .label-count {
        font-size: 11px;
        font-weight: 700;
        color: var(--ink-3);
        background: var(--surface-2);
        border: 1px solid var(--border-2);
        padding: 2px 8px;
        border-radius: 10px;
        min-width: 24px;
        text-align: center;
        font-family: 'DM Mono', monospace;
        flex-shrink: 0;
    }

    .label-item:hover .label-count {
        border-color: var(--blue);
        background: var(--blue-glow);
        color: var(--blue);
    }

    /* ══════════════════════════════════════════════════════════
       USER FOOTER
       ══════════════════════════════════════════════════════════ */
    .user-footer {
        padding: 16px;
        border-top: 1px solid var(--border);
        background: var(--surface-2);
    }

    .user-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--r);
        padding: 12px;
        margin-bottom: 12px;
        box-shadow: var(--shadow);
        transition: all .18s var(--ease);
    }

    .user-card:hover {
        border-color: var(--blue);
        box-shadow: var(--shadow-lg);
    }

    .verified-badge-row {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 8px;
    }

    .verified-badge {
        width: 14px;
        height: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .verified-badge img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .verified-text {
        font-size: 10px;
        font-weight: 700;
        color: var(--blue);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .user-email {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: var(--ink);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        letter-spacing: -0.1px;
    }

    /* ══════════════════════════════════════════════════════════
       ACTION BUTTONS
       ══════════════════════════════════════════════════════════ */
    .footer-actions {
        display: flex;
        gap: 8px;
    }

    .action-btn {
        flex: 1;
        height: 36px;
        padding: 0 12px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        text-align: center;
        transition: all .18s var(--ease);
        cursor: pointer;
        border: none;
        font-family: 'DM Sans', -apple-system, sans-serif;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .config-btn {
        background: var(--surface);
        color: var(--ink-2);
        border: 1.5px solid var(--border-2);
    }

    .config-btn:hover {
        background: var(--blue-glow);
        border-color: var(--blue);
        color: var(--blue);
        transform: translateY(-1px);
    }

    .config-btn:active {
        transform: translateY(0) scale(.98);
    }

    .logout-btn {
        background: var(--ink);
        color: white;
        border: 1.5px solid transparent;
    }

    .logout-btn:hover {
        background: var(--ink-2);
        box-shadow: 0 3px 10px rgba(26,26,46,0.25);
        transform: translateY(-1px);
    }

    .logout-btn:active {
        transform: translateY(0) scale(.98);
    }

    /* ══════════════════════════════════════════════════════════
       RESPONSIVE
       ══════════════════════════════════════════════════════════ */
    @media (max-width: 1200px) {
        .sidebar {
            width: 240px;
        }
    }

    @media (max-width: 1024px) {
        .sidebar {
            width: 220px;
        }
        
        .sidebar-header {
            padding: 20px 16px;
        }
        
        .nav-section {
            padding: 12px 10px;
        }
        
        .user-footer {
            padding: 12px;
        }

        .logo-image {
            height: 36px;
        }

        .logo-title {
            font-size: 15px;
        }
    }

    @media (max-width: 768px) {
        .sidebar {
            width: 200px;
        }

        .nav-item {
            padding: 8px 12px;
            font-size: 13px;
        }

        .label-item {
            padding: 7px 12px;
            font-size: 12px;
        }
    }

    /* ══════════════════════════════════════════════════════════
       BADGE ANIMATIONS
       ══════════════════════════════════════════════════════════ */
    @keyframes badgePulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.05); }
    }

    .label-count:not(:empty) {
        animation: badgePulse 2s ease-in-out infinite;
    }

    /* ══════════════════════════════════════════════════════════
       FOCUS STATES (Accessibility)
       ══════════════════════════════════════════════════════════ */
    .nav-item:focus-visible,
    .label-item:focus-visible,
    .label-settings-btn:focus-visible,
    .action-btn:focus-visible {
        outline: 2px solid var(--blue);
        outline-offset: 2px;
    }

    /* ══════════════════════════════════════════════════════════
       LOADING STATE (Optional)
       ══════════════════════════════════════════════════════════ */
    .nav-item.loading .material-icons-round,
    .label-item.loading .label-dot {
        animation: spin .8s linear infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    /* ══════════════════════════════════════════════════════════
       ENTRY ANIMATION
       ══════════════════════════════════════════════════════════ */
    .nav-item,
    .label-item {
        animation: fadeInLeft .3s var(--ease) both;
    }

    @keyframes fadeInLeft {
        from {
            opacity: 0;
            transform: translateX(-10px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    /* Stagger animation delays */
    .nav-item:nth-child(1) { animation-delay: 0s; }
    .nav-item:nth-child(2) { animation-delay: 0.03s; }
    .nav-item:nth-child(3) { animation-delay: 0.06s; }
    .nav-item:nth-child(4) { animation-delay: 0.09s; }
    .nav-item:nth-child(5) { animation-delay: 0.12s; }
    .nav-item:nth-child(6) { animation-delay: 0.15s; }
    .nav-item:nth-child(7) { animation-delay: 0.18s; }
</style>

<div class="sidebar" id="mainSidebar">
    <!-- ========== HEADER / LOGO ========== -->
    <div class="sidebar-header">
        <a href="index.php" class="logo">
            <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcT_Zqk8uEqyydQiO-nuKyebrvbWubamLz_E3Q&s"
                alt="SXC Logo"
                class="logo-image">
            <div class="logo-text">
                <span class="logo-title">SXC MDTS</span>
                <span class="logo-subtitle">v1.2.15</span>
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

        <a href="bunch_mailer.php" class="nav-item <?= ($current_page == 'bunch_mailer') ? 'active' : ''; ?>">
            <span class="material-icons-round">group</span>
            <span>Mail Merge</span>
        </a>
        
        <a href="sent_history.php" class="nav-item <?= ($current_page == 'sent_history') ? 'active' : ''; ?>">
            <span class="material-icons-round">send</span>
            <span>Sent</span>
        </a>

        <a href="inbox.php" class="nav-item <?= ($current_page == 'inbox') ? 'active' : ''; ?>">
            <span class="material-icons-round">mail</span>
            <span>Inbox</span>
        </a>
        
        <a href="drive.php" class="nav-item <?= ($current_page == 'drive') ? 'active' : ''; ?>">
            <span class="material-icons-round">add_to_drive</span>
            <span>Drive</span>
        </a>

        <a href="deleted_items.php" class="nav-item <?= ($current_page == 'deleted_items') ? 'active' : ''; ?>">
            <span class="material-icons-round">delete</span>
            <span>Trash</span>
        </a>

        <!-- Section Divider -->
        <div class="nav-section-divider"></div>

        <!-- Labels Section Header -->
        <div class="nav-section-title">
            Labels
            <a href="manage_labels.php" class="label-settings-btn" aria-label="Manage Labels" title="Manage Labels">
                <span class="material-icons-round">settings</span>
            </a>
        </div>

        <!-- Label Items -->
        <?php if (!empty($sidebarLabels)): ?>
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
        <?php else: ?>
            <div style="padding: 12px 14px; font-size: 12px; color: var(--ink-3); text-align: center;">
                No labels yet
            </div>
        <?php endif; ?>
    </nav>

    <!-- ========== USER FOOTER / ACCOUNT PANEL ========== -->
    <div class="user-footer">
        <!-- Account Card -->
        <div class="user-card">
            <div class="verified-badge-row">
                <span class="verified-badge">
                    <img src="/Assets/image/Verified_badge.png" alt="Verified">
                </span>
                <span class="verified-text">Verified</span>
            </div>
            <span class="user-email" title="<?= htmlspecialchars($userEmail) ?>">
                <?= htmlspecialchars($userEmail) ?>
            </span>
        </div>

        <!-- Action Buttons -->
        <div class="footer-actions">
            <a href="settings.php" class="action-btn config-btn">Settings</a>
            <a href="logout.php" class="action-btn logout-btn">Sign Out</a>
        </div>
    </div>
</div>