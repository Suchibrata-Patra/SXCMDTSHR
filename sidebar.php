<?php
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$userEmail = $_SESSION['smtp_user'] ?? 'user@example.com';

require_once __DIR__ . '/../db_config.php';
$sidebarLabels = getLabelCounts($userEmail);
$unlabeledCount = getUnlabeledEmailCount($userEmail);
?>

<!-- Sidebar Styles -->
<style>
    :root {
        --sidebar-width: 280px;
        --nature-red: #a1021c;
        --nature-ink: #111;
        --glass-bg: rgba(255, 255, 255, 0.7);
        --glass-border: rgba(255, 255, 255, 0.3);
        --hover-bg: rgba(255, 255, 255, 0.35);
    }

    /* Global page offset for when sidebar is included */
    body {
        margin-left: var(--sidebar-width);
        font-family: "Inter", sans-serif;
    }

    /* Mobile */
    @media (max-width: 768px) {
        body {
            margin-left: 0;
        }
    }

    /* Sidebar Container */
    .sidebar {
        width: var(--sidebar-width);
        position: fixed;
        top: 0;
        left: 0;
        height: 100vh;
        display: flex;
        flex-direction: column;
        backdrop-filter: blur(16px);
        background: var(--glass-bg);
        border-right: 1px solid var(--glass-border);
        z-index: 1000;
        transition: transform .3s ease;
    }

    /* Mobile hidden by default */
    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
        }

        .sidebar.open {
            transform: translateX(0);
        }
    }

    /* Logo Section */
    .sidebar-header {
        padding: 28px 22px;
        border-bottom: 1px solid var(--glass-border);
    }

    .logo {
        display: flex;
        align-items: center;
        gap: 14px;
        text-decoration: none;
    }

    .logo-image {
        width: 52px;
        height: 52px;
        border-radius: 4px;
    }

    .logo-title {
        font-size: 22px;
        font-family: "Crimson Pro", serif;
        font-weight: 700;
        color: var(--nature-ink);
    }

    /* Navigation Section */
    .nav-section {
        flex: 1;
        padding: 20px 10px;
        overflow-y: auto;
    }

    .nav-item,
    .label-item {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 12px 16px;
        font-weight: 600;
        font-size: 15px;
        color: var(--nature-ink);
        border-radius: 8px;
        text-decoration: none;
        transition: background .25s ease, padding-left .25s ease;
    }

    .nav-item:hover,
    .label-item:hover {
        background: var(--hover-bg);
        padding-left: 20px;
    }

    .nav-item.active {
        border-left: 4px solid var(--nature-red);
        background: rgba(161, 2, 28, 0.08);
    }

    .material-icons {
        font-size: 22px;
        color: #444;
    }

    .nav-item.active .material-icons {
        color: var(--nature-red);
    }

    .nav-section-title {
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.5px;
        color: #444;
        margin: 20px 16px 6px;
    }

    .label-dot {
        width: 10px;
        height: 10px;
        border-radius: 2px;
    }

    /* User Footer */
    .user-footer {
        padding: 18px;
        border-top: 1px solid var(--glass-border);
        background: rgba(255, 255, 255, 0.35);
        backdrop-filter: blur(12px);
    }

    .logout-btn {
        color: var(--nature-red);
        border: 2px solid var(--nature-red);
        padding: 6px 12px;
        border-radius: 6px;
        font-weight: 700;
        text-decoration: none;
        font-size: 12px;
    }

    .footer-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 12px;
    }

    /* Mobile Toggle */
    .mobile-toggle {
        display: none;
    }

    @media (max-width: 768px) {
        .mobile-toggle {
            display: block;
            position: fixed;
            top: 14px;
            left: 14px;
            z-index: 1100;
            padding: 10px 12px;
            background: var(--nature-ink);
            color: #fff;
            border-radius: 8px;
            cursor: pointer;
        }

        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none;
        }

        .sidebar-overlay.active {
            display: block;
        }
    }
</style>

<!-- Mobile Toggle Button -->
<button class="mobile-toggle" onclick="toggleSidebar()">
    <span class="material-icons">menu</span>
</button>

<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<!-- Sidebar Start -->
<div class="sidebar" id="globalSidebar">

    <div class="sidebar-header">
        <a href="/index.php" class="logo">
            <img src="https://upload.wikimedia.org/wikipedia/en/b/b0/St._Xavier%27s_College%2C_Kolkata_logo.jpg"
                class="logo-image">
            <div>
                <div class="logo-title">SXC MDTS</div>
                <div style="font-size:10px; color:#777;">OFFICIAL PORTAL</div>
            </div>
        </a>
    </div>

    <nav class="nav-section">

        <a href="/index.php" class="nav-item <?= ($current_page=='index')?'active':'' ?>">
            <span class="material-icons">edit_note</span> COMPOSE
        </a>

        <a href="/sent_history.php" class="nav-item <?= ($current_page=='sent_history')?'active':'' ?>">
            <span class="material-icons">history</span> ALL MAIL
        </a>

        <a href="/send.php" class="nav-item <?= ($current_page=='send')?'active':'' ?>">
            <span class="material-icons">analytics</span> ANALYTICS
        </a>

        <div class="nav-section-title">LABELS</div>

        <?php foreach ($sidebarLabels as $label): ?>
        <a href="/sent_history.php?label_id=<?= $label['id'] ?>" class="label-item">
            <div class="label-dot" style="background: <?= htmlspecialchars($label['label_color']) ?>"></div>
            <?= htmlspecialchars($label['label_name']) ?>
        </a>
        <?php endforeach; ?>

    </nav>

    <div class="user-footer">
        <div style="font-size:11px; font-weight:800; color:#666;">Authenticated</div>
        <div style="font-weight:700; margin:10px 0;">
            <?= htmlspecialchars($userEmail) ?>
        </div>

        <div class="footer-actions">
            <a href="/settings.php" style="text-decoration:none; color:#444; font-size:12px; font-weight:700;">
                <span class="material-icons" style="font-size:16px;">tune</span> CONFIG
            </a>
            <a href="/logout.php" class="logout-btn">SIGN OUT</a>
        </div>
    </div>

</div>

<!-- Sidebar JS -->
<script>
    function toggleSidebar() {
        document.getElementById("globalSidebar").classList.toggle("open");
        document.querySelector(".sidebar-overlay").classList.toggle("active");
    }
</script>