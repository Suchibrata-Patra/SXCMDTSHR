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
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Crimson+Pro:wght@700&family=Inter:wght@400;500;600;700&display=swap"
        rel="stylesheet" />
    <style>
        :root {
            --sidebar-width: 280px;
            --nature-red: #a10420;
            --inst-black: #121212;
            --inst-gray: #464646;
            --inst-border: #d6d6d6;
            --inst-bg: #ffffff;
            --hover-bg: #f2f2f2;
            --z-index-sidebar: 1000;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #fafafa;
            font-family: 'Inter', sans-serif;
        }

        /* Mobile Toggle Button */
        .mobile-toggle {
            display: none;
            position: fixed;
            top: 16px;
            left: 16px;
            z-index: 1100;
            background: var(--inst-black);
            color: #fff;
            border: none;
            padding: 10px;
            border-radius: 6px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: background 0.2s ease;
        }

        .mobile-toggle:hover {
            background: #000;
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--inst-bg);
            height: 100vh;
            border-right: 1px solid #e6e6e6;
            display: flex;
            flex-direction: column;
            position: sticky;
            top: 0;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .sidebar-header {
            padding: 32px 24px;
            background: #fcfcfc;
            border-bottom: 1px solid #e5e5e5;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 14px;
            text-decoration: none;
        }

        .logo-image {
            width: 54px;
            height: 54px;
            object-fit: contain;
            border-radius: 6px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .logo-title {
            font-family: 'Crimson Pro', serif;
            font-size: 22px;
            font-weight: 700;
            color: var(--inst-black);
            letter-spacing: -0.5px;
        }

        .nav-section {
            flex: 1;
            padding: 24px 12px;
            overflow-y: auto;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 15px;
            font-weight: 600;
            color: var(--inst-black);
            transition: all 0.18s ease;
        }

        .nav-item .material-icons {
            font-size: 22px;
            opacity: 0.8;
            transition: opacity 0.18s;
        }

        .nav-item:hover {
            background: var(--hover-bg);
        }

        .nav-item.active {
            background: #f4f4f4;
            border-left: 4px solid var(--nature-red);
            padding-left: 12px;
            font-weight: 700;
        }

        .nav-item.active .material-icons {
            color: var(--nature-red);
            opacity: 1;
        }

        .nav-section-title {
            margin-top: 20px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            padding: 0 16px;
            font-size: 12px;
            font-weight: 800;
            color: var(--inst-gray);
            letter-spacing: 0.5px;
        }

        .manage-labels-btn {
            text-decoration: none;
            color: var(--nature-red);
            transition: opacity 0.2s;
        }

        .manage-labels-btn:hover {
            opacity: 0.75;
        }

        .label-item {
            display: flex;
            align-items: center;
            padding: 10px 16px;
            cursor: pointer;
            text-decoration: none;
            color: var(--inst-gray);
            font-size: 14px;
            font-weight: 600;
            transition: background 0.18s ease;
        }

        .label-item:hover {
            background: #f3f3f3;
            border-radius: 8px;
        }

        .label-dot {
            width: 12px;
            height: 12px;
            border-radius: 3px;
            margin-right: 12px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
        }

        .user-footer {
            padding: 22px;
            border-top: 1px solid #e6e6e6;
            background: #fbfbfb;
        }

        .user-email {
            font-size: 14px;
            font-weight: 700;
            color: var(--inst-black);
            margin: 6px 0 14px;
            word-break: break-all;
        }

        .footer-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logout-btn {
            text-decoration: none;
            color: var(--nature-red);
            border: 1px solid var(--nature-red);
            padding: 7px 12px;
            font-size: 12px;
            font-weight: 800;
            border-radius: 5px;
            transition: background 0.25s ease, color 0.25s ease;
        }

        .logout-btn:hover {
            background: var(--nature-red);
            color: #fff;
        }

        /* Mobile Screen */
        @media (max-width: 768px) {
            .mobile-toggle {
                display: block;
            }

            .sidebar {
                position: fixed;
                left: 0;
                transform: translateX(-100%);
                width: var(--sidebar-width);
                box-shadow: 10px 0 40px rgba(0, 0, 0, 0.15);
                z-index: 1000;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100vw;
                height: 100vh;
                background: rgba(0, 0, 0, 0.45);
                z-index: 999;
            }

            .sidebar-overlay.active {
                display: block;
            }
        }
    </style>
</head>

<body>

    <button class="mobile-toggle" onclick="toggleSidebar()">
        <span class="material-icons">menu</span>
    </button>

    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <div class="sidebar" id="mainSidebar">
        <div class="sidebar-header">
            <a href="#" class="logo">
                <img src="https://upload.wikimedia.org/wikipedia/en/b/b0/St._Xavier%27s_College%2C_Kolkata_logo.jpg"
                    class="logo-image" />
                <div class="logo-text">
                    <span class="logo-title">SXC MDTS</span><br />
                    <span style="font-size: 10px; font-weight: 700; color: var(--inst-gray);">OFFICIAL PORTAL</span>
                </div>
            </a>
        </div>

        <nav class="nav-section">
            <a href="#" class="nav-item active">
                <span class="material-icons">edit_note</span>
                <span>COMPOSE</span>
            </a>

            <a href="#" class="nav-item">
                <span class="material-icons">history</span>
                <span>ALL MAIL</span>
            </a>

            <a href="#" class="nav-item">
                <span class="material-icons">analytics</span>
                <span>ANALYTICS</span>
            </a>

            <div class="nav-section-title">
                LABELS
                <a href="#" class="manage-labels-btn"><span class="material-icons"
                        style="font-size: 18px;">settings</span></a>
            </div>

            <a href="#" class="label-item">
                <div class="label-dot" style="background:#a10420"></div>Important
            </a>
            <a href="#" class="label-item">
                <div class="label-dot" style="background:#1976d2"></div>Academic
            </a>
            <a href="#" class="label-item">
                <div class="label-dot" style="background:#2e7d32"></div>Personal
            </a>

        </nav>

        <div class="user-footer">
            <div style="font-size: 10px; font-weight: 800; color: var(--inst-gray); text-transform: uppercase;">
                Authenticated</div>
            <div class="user-email">user@example.com</div>

            <div class="footer-actions">
                <a href="#" style="font-size:12px; text-decoration:none; color:var(--inst-gray); font-weight:700;">
                    <span class="material-icons" style="font-size:16px; vertical-align:middle;">tune</span> CONFIG
                </a>
                <a href="#" class="logout-btn">SIGN OUT</a>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('mainSidebar').classList.toggle('open');
            document.querySelector('.sidebar-overlay').classList.toggle('active');
        }
    </script>
</body>

</html>