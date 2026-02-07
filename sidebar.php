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
    <link
  rel="stylesheet"
  href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0"
/>

<style>
    :root {
        --sidebar-width: 260px;
        /* Apple-Inspired Color Palette */
        --nature-red: #007AFF; /* Apple Blue */
        --nature-red-hover: #0056b3;
        --nature-dark: #1c1c1e;
        --nature-gray: #3a3a3c;
        --nature-medium-gray: #8e8e93; /* Apple Gray */
        --nature-light-gray: #c7c7cc;
        --nature-border: rgba(0, 0, 0, 0.1);
        --nature-border-light: rgba(0, 0, 0, 0.05);
        --nature-bg: rgba(242, 242, 247, 0.7); /* Translucent Apple Background */
        --nature-bg-hover: rgba(0, 0, 0, 0.04);
        --nature-bg-active: rgba(0, 122, 255, 0.1); /* Light Blue Tint */
        
        --shadow-subtle: 0 1px 2px rgba(0, 0, 0, 0.05);
        --shadow-sm: 0 4px 12px rgba(0, 0, 0, 0.05);
        --transition: cubic-bezier(0.4, 0, 0.2, 1);
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', -apple-system, sans-serif;
        background: #ffffff;
        -webkit-font-smoothing: antialiased;
    }

    /* Sidebar - Adding Glassmorphism */
    .sidebar {
        width: var(--sidebar-width);
        background: var(--nature-bg);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-right: 1px solid var(--nature-border);
        display: flex;
        flex-direction: column;
        height: 100vh;
        color: var(--nature-dark);
        transition: transform 0.3s var(--transition);
        position: sticky;
        top: 0;
    }

    /* Header */
    .sidebar-header {
        padding: 24px 18px 16px;
        border-bottom: 1px solid var(--nature-border-light);
    }

    .logo-image {
        width: 38px;
        height: 38px;
        border-radius: 8px;
        filter: grayscale(1) contrast(1.2); /* Minimalist Apple-style Logo */
    }

    .logo-title {
        font-family: 'Inter', sans-serif; /* Cleaned up from Merriweather */
        font-size: 16px;
        font-weight: 700;
        color: #000;
    }

    .logo-subtitle {
        color: var(--nature-medium-gray);
    }

    /* Navigation Items */
    .nav-item {
        color: var(--nature-dark);
        border-radius: 10px; /* Slightly rounder like iOS */
        transition: background 0.2s, color 0.2s;
    }

    .nav-item:hover {
        background: var(--nature-bg-hover);
        color: #000;
    }

    .nav-item.active {
        background: var(--nature-bg-active);
        color: var(--nature-red); /* Apple Blue */
    }

    .nav-item.active::before {
        background: var(--nature-red);
        height: 16px;
        width: 4px;
        border-radius: 0 4px 4px 0;
    }

    /* Labels */
    .label-count {
        background: rgba(0, 0, 0, 0.05);
        color: var(--nature-medium-gray);
    }

    .label-item:hover .label-count {
        background: var(--nature-red);
        color: white;
    }

    /* User Footer & Buttons */
    .user-footer {
        background: transparent;
        border-top: 1px solid var(--nature-border-light);
    }

    .user-card {
        background: rgba(255, 255, 255, 0.5);
        border: 1px solid var(--nature-border-light);
    }

    .auth-badge {
        color: #34C759; /* Apple Success Green */
    }

    .auth-badge::before {
        background: #34C759;
    }

    .config-btn {
        background: white;
        color: var(--nature-dark);
        border-color: var(--nature-border);
    }

    .logout-btn {
        background: rgba(255, 59, 48, 0.1); /* Light red tint */
        color: #FF3B30; /* Apple Destructive Red */
        border-color: transparent;
    }

    .logout-btn:hover {
        background: #FF3B30;
        color: white;
    }
</style>
</head>

<body>

    <button class="mobile-toggle" onclick="toggleSidebar()" aria-label="Toggle Menu">
        <span class="material-icons-round">menu</span>
    </button>

    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <div class="sidebar" id="mainSidebar">
        <!-- Header -->
        <div class="sidebar-header">
            <a href="index.php" class="logo">
                <img src="https://upload.wikimedia.org/wikipedia/en/b/b0/St._Xavier%27s_College%2C_Kolkata_logo.jpg"
                    alt="SXC Logo" class="logo-image">
                <div class="logo-text">
                    <span class="logo-title">SXC MDTS</span>
                    <span class="logo-subtitle">10.28.73.474</span>
                </div>
            </a>
        </div>

        <!-- Navigation -->
        <nav class="nav-section">
            <a href="index.php" class="nav-item <?= ($current_page == 'index') ? 'active' : ''; ?>">
                <span class="material-icons-round">edit_note</span>
                <span>Compose</span>
            </a>

            <a href="sent_history.php" class="nav-item <?= ($current_page == 'sent_history') ? 'active' : ''; ?>">
                <span class="material-icons-round">send</span>
                <span>Sent</span>
            </a>
            <a href="deleted_items.php" class="nav-item <?= ($current_page == 'deleted_items') ? 'active' : ''; ?>">
                <span class="material-icons-round">delete</span>
                <span>Deleted</span>
            </a>

            <a href="send.php" class="nav-item <?= ($current_page == 'send') ? 'active' : ''; ?>">
                <span class="material-icons-round">analytics</span>
                <span>Analytics</span>
            </a>

            <!-- Labels Section -->
            <div class="nav-section-title">
                Labels
                <a href="manage_labels.php" class="manage-labels-btn" title="Manage Labels">
                    <span class="material-icons-round" style="font-size: 16px;">settings</span>
                </a>
            </div>

            <?php foreach ($sidebarLabels as $label): ?>
            <a href="sent_history.php?label_id=<?= $label['id'] ?>" class="label-item">
                <div class="label-content">
                    <div class="label-dot" style="background-color: <?= htmlspecialchars($label['label_color']) ?>;">
                    </div>
                    <span>
                        <?= htmlspecialchars($label['label_name']) ?>
                    </span>
                </div>
                <?php if (isset($label['count']) && $label['count'] > 0): ?>
                <span class="label-count">
                    <?= $label['count'] ?>
                </span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </nav>

        <!-- User Footer -->
        <div class="user-footer">
            <div class="user-card">
                <span class="auth-badge">Verified</span>
                <span class="user-email">
                    <?= htmlspecialchars($userEmail) ?>
                </span>
            </div>

            <div class="footer-actions">
                <a href="settings.php" class="action-btn config-btn">
                    <span class="material-icons-round">tune</span>
                    <span>Settings</span>
                </a>
                <a href="logout.php" class="action-btn logout-btn">
                    <span class="material-icons-round">logout</span>
                    <span>Log Out</span>
                </a>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('mainSidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && document.getElementById('mainSidebar').classList.contains('open')) {
                toggleSidebar();
            }
        });
    </script>
</body>

</html>