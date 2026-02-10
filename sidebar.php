<?php
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$userEmail = $_SESSION['smtp_user'] ?? 'user@example.com';
$userInitial = strtoupper(substr($userEmail, 0, 1));

require_once 'db_config.php';
$sidebarLabels = getLabelCounts($userEmail);
$unlabeledCount = getUnlabeledEmailCount($userEmail);
?>

<style>
    :root {
        --apple-blue: #007AFF;
        --apple-gray: #8E8E93;
        --apple-bg: #F2F2F7;
        --glass: rgba(255, 255, 255, 0.7);
        --border: #E5E5EA;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    /* ========== SIDEBAR CONTAINER ========== */
    .sidebar {
        width: 240px;
        height: 98%;
        background: #fbfbfd;
        backdrop-filter: blur(20px) saturate(180%);
        -webkit-backdrop-filter: blur(20px) saturate(180%);
        border-right: 1px solid var(--border);
        display: flex;
        flex-direction: column;
        position: sticky;
        top: 0;
        z-index: 1000;
        overflow: hidden;
    }

    /* ========== LOGO / HEADER AREA ========== */
    .sidebar-header {
        padding: 40px 20px 30px;
        border-bottom: 1px solid var(--border);
    }

    .logo {
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
        transition: opacity 0.2s;
    }

    .logo:hover {
        opacity: 0.8;
    }

    .logo-image {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        object-fit: cover;
        background: white;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .logo-text {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .logo-title {
        font-size: 16px;
        font-weight: 600;
        letter-spacing: -0.3px;
        color: #1c1c1e;
        line-height: 1.2;
    }

    .logo-subtitle {
        font-size: 11px;
        font-weight: 400;
        color: var(--apple-gray);
        letter-spacing: 0.06px;
    }

    /* ========== NAVIGATION SECTION ========== */
    .nav-section {
        flex: 1;
        padding: 15px 10px;
        overflow-y: auto;
        overflow-x: hidden;
    }

    .nav-section::-webkit-scrollbar {
        width: 4px;
    }

    .nav-section::-webkit-scrollbar-track {
        background: transparent;
    }

    .nav-section::-webkit-scrollbar-thumb {
        background: rgba(0, 0, 0, 0.1);
        border-radius: 10px;
    }

    /* Navigation Items */
    .nav-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 15px;
        text-decoration: none;
        color: black;
        font-size: 13px;
        font-weight: 500;
        letter-spacing: -0.08px;
        border-radius: 8px;
        margin-bottom: 5px;
        transition: all 0.2s;
        position: relative;
        cursor: pointer;
    }

    .nav-item:hover {
        background: rgba(0, 0, 0, 0.05);
    }

    .nav-item.active {
        background: var(--apple-blue);
        color: white;
        font-weight: 600;
    }

    /* Icons */
    .nav-item .material-icons {
        font-size: 18px;
        color: black;
        transition: color 0.2s;
    }

    .nav-item:hover .material-icons {
        color: #1c1c1e;
    }

    .nav-item.active .material-icons {
        color: white;
    }

    /* ========== SECTION DIVIDER ========== */
    .nav-section-divider {
        height: 30px;
    }

    /* ========== LABELS SECTION ========== */
    .nav-section-title {
        font-size: 11px;
        font-weight: 700;
        color: var(--apple-gray);
        padding: 10px 15px;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }

    .label-settings-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 20px;
        height: 20px;
        border-radius: 4px;
        background: transparent;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
    }

    .label-settings-btn:hover {
        background: rgba(0, 0, 0, 0.05);
    }

    .label-settings-btn .material-icons {
        font-size: 16px;
        color: var(--apple-gray);
    }

    /* Label Items */
    .label-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 6px 15px;
        text-decoration: none;
        color: #1c1c1e;
        font-size: 13px;
        font-weight: 400;
        border-radius: 8px;
        margin-bottom: 4px;
        transition: all 0.2s;
        cursor: pointer;
    }

    .label-item:hover {
        background: rgba(0, 0, 0, 0.05);
    }

    .label-content {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .label-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .label-name {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .label-count {
        font-size: 11px;
        font-weight: 600;
        color: var(--apple-gray);
        background: #F2F2F7;
        padding: 2px 8px;
        border-radius: 10px;
        min-width: 20px;
        text-align: center;
    }

    /* ========== USER FOOTER ========== */
    .user-footer {
        padding: 16px 20px 20px;
        border-top: 1px solid var(--border);
        background: rgba(255, 255, 255, 0.5);
    }

    .user-card {
        background: white;
        border-radius: 10px;
        padding: 12px;
        margin-bottom: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        border: 1px solid var(--border);
    }

    .verified-badge-row {
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 6px;
    }

    .verified-badge {
        width: 16px;
        height: 16px;
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
        font-weight: 600;
        color: var(--apple-blue);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .user-email {
        display: block;
        font-size: 13px;
        font-weight: 500;
        color: #52525b;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        letter-spacing: -0.08px;
    }

    /* ========== ACTION BUTTONS ========== */
    .footer-actions {
        display: flex;
        gap: 8px;
    }

    .action-btn {
        flex: 1;
        padding: 8px 12px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 500;
        letter-spacing: -0.08px;
        text-align: center;
        transition: all 0.2s;
        cursor: pointer;
        border: none;
        font-family: 'Inter', -apple-system, sans-serif;
        display: inline-block;
    }

    .config-btn {
        background: white;
        color: #52525b;
        border: 1px solid var(--border);
    }

    .config-btn:hover {
        background: #F2F2F7;
        border-color: #D1D1D6;
        color: #1c1c1e;
    }

    .logout-btn {
        background: #1c1c1e;
        color: white;
        border: 1px solid transparent;
    }

    .logout-btn:hover {
        background: #000000;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
    }

    /* ========== RESPONSIVE ========== */
    @media (max-width: 1200px) {
        .sidebar {
            width: 220px;
        }
    }

    @media (max-width: 1024px) {
        .sidebar {
            width: 200px;
        }
        
        .sidebar-header {
            padding: 30px 15px 20px;
        }
        
        .nav-section {
            padding: 12px 8px;
        }
        
        .user-footer {
            padding: 12px 15px 15px;
        }
    }
</style>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
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
            <span class="material-icons">edit</span>
            <span>Compose</span>
        </a>
        
        <a href="sent_history.php" class="nav-item <?= ($current_page == 'sent_history') ? 'active' : ''; ?>">
            <span class="material-icons">send</span>
            <span>Sent</span>
        </a>
        
        <a href="deleted_items.php" class="nav-item <?= ($current_page == 'deleted_items') ? 'active' : ''; ?>">
            <span class="material-icons">delete</span>
            <span>Trash</span>
        </a>
        
        
        <a href="inbox.php" class="nav-item <?= ($current_page == 'inbox') ? 'active' : ''; ?>">
            <span class="material-icons">mail</span>
            <span>Inbox</span>
        </a>
        <a href="#" class="nav-item <?= ($current_page == 'send') ? 'active' : ''; ?>">
            <span class="material-icons">analytics</span>
            <span><span>Selivery</span><br><span>Status</span> </span>
        </a>

        <!-- Section Divider -->
        <div class="nav-section-divider"></div>

        <!-- Labels Section Header -->
        <div class="nav-section-title">
            Labels
            <a href="manage_labels.php" class="label-settings-btn" aria-label="Manage Labels">
                <span class="material-icons">settings</span>
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
        <!-- Account Card -->
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